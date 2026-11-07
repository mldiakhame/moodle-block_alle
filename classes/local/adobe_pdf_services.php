<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_alle\local;

/**
 * Text extraction with the Adobe PDF Services "Extract PDF" operation.
 *
 * Used for PDFs that pdftotext cannot read, typically scanned documents.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adobe_pdf_services {
    /** @var string API base URL. */
    public const API = 'https://pdf-services.adobe.io';

    /** @var int Seconds between two status checks of an extraction job. */
    public const POLL_INTERVAL = 2;

    /** @var int Maximum number of status checks before giving up (about five minutes). */
    public const MAX_POLLS = 150;

    /** @var string|null OAuth token for the current extraction. */
    protected ?string $token = null;

    /**
     * Constructor.
     *
     * @param string $clientid Adobe client ID.
     * @param string $clientsecret Adobe client secret.
     */
    public function __construct(
        /** @var string Adobe client ID. */
        protected string $clientid,
        /** @var string Adobe client secret. */
        protected string $clientsecret,
    ) {
    }

    /**
     * Check that the credentials are accepted.
     *
     * @throws \moodle_exception When they are not.
     */
    public function check_credentials(): void {
        $this->authenticate();
    }

    /**
     * Extract the text of a PDF.
     *
     * @param string $path Local path of the PDF.
     * @return string Extracted text, paragraphs separated by blank lines.
     * @throws \moodle_exception
     */
    public function extract_text(string $path): string {
        $this->authenticate();
        $assetid = $this->upload($path);
        $status = $this->start_job($assetid);

        for ($poll = 0; $poll < self::MAX_POLLS; $poll++) {
            sleep(self::POLL_INTERVAL);
            $job = $this->request('GET', $status);
            if (($job['status'] ?? '') === 'done') {
                $result = $this->request('GET', $job['content']['downloadUri'] ?? '', null, false);
                return $this->elements_to_text($result);
            }
            if (($job['status'] ?? '') === 'failed') {
                throw new \moodle_exception('error:pdfextraction', 'block_alle', '', 'Adobe job failed');
            }
        }
        throw new \moodle_exception('error:pdfextraction', 'block_alle', '', 'Adobe job timed out');
    }

    /**
     * Join the text elements of an Extract PDF result.
     *
     * @param array $result Decoded structuredData JSON.
     * @return string
     */
    public function elements_to_text(array $result): string {
        $paragraphs = [];
        foreach ($result['elements'] ?? [] as $element) {
            if (isset($element['Text']) && trim($element['Text']) !== '') {
                $paragraphs[] = trim($element['Text']);
            }
        }
        return implode("\n\n", $paragraphs);
    }

    /**
     * Obtain an access token.
     *
     * @throws \moodle_exception
     */
    protected function authenticate(): void {
        $curl = $this->new_curl();
        $body = $curl->post(self::API . '/token', [
            'client_id' => $this->clientid,
            'client_secret' => $this->clientsecret,
        ], ['CURLOPT_TIMEOUT' => 30]);
        $data = $this->check($curl, $body);
        if (empty($data['access_token'])) {
            throw new \moodle_exception('error:pdfextraction', 'block_alle', '', 'no access token');
        }
        $this->token = $data['access_token'];
    }

    /**
     * Upload the PDF and return its asset ID.
     *
     * @param string $path
     * @return string
     * @throws \moodle_exception
     */
    protected function upload(string $path): string {
        $asset = $this->request('POST', self::API . '/assets', ['mediaType' => 'application/pdf']);
        if (empty($asset['uploadUri']) || empty($asset['assetID'])) {
            throw new \moodle_exception('error:pdfextraction', 'block_alle', '', 'no upload URI');
        }
        $curl = $this->new_curl();
        $curl->setHeader(['Content-Type: application/pdf']);
        $curl->put($asset['uploadUri'], ['file' => $path], ['CURLOPT_TIMEOUT' => 120]);
        $this->check($curl, '{}');
        return $asset['assetID'];
    }

    /**
     * Start the extraction job and return the URL to poll.
     *
     * @param string $assetid
     * @return string
     * @throws \moodle_exception
     */
    protected function start_job(string $assetid): string {
        $curl = $this->new_curl();
        $curl->setHeader($this->headers());
        $body = $curl->post(self::API . '/operation/extractpdf', json_encode([
            'assetID' => $assetid,
            'elementsToExtract' => ['text'],
        ]), ['CURLOPT_TIMEOUT' => 30]);
        $this->check($curl, $body ?: '{}');
        foreach ($curl->getResponse() as $name => $value) {
            if (strcasecmp($name, 'location') === 0) {
                return trim($value);
            }
        }
        throw new \moodle_exception('error:pdfextraction', 'block_alle', '', 'no job location');
    }

    /**
     * Authenticated JSON request.
     *
     * @param string $method GET or POST.
     * @param string $url
     * @param array|null $payload JSON body for POST.
     * @param bool $authenticated Whether to send the Adobe headers (not wanted on pre-signed URLs).
     * @return array
     * @throws \moodle_exception
     */
    protected function request(string $method, string $url, ?array $payload = null, bool $authenticated = true): array {
        $curl = $this->new_curl();
        if ($authenticated) {
            $curl->setHeader($this->headers());
        }
        $body = $method === 'POST'
            ? $curl->post($url, json_encode($payload ?? []), ['CURLOPT_TIMEOUT' => 60])
            : $curl->get($url, [], ['CURLOPT_TIMEOUT' => 60]);
        return $this->check($curl, $body);
    }

    /**
     * Headers expected by the Adobe API.
     *
     * @return string[]
     */
    protected function headers(): array {
        return [
            'Authorization: Bearer ' . $this->token,
            'X-API-Key: ' . $this->clientid,
            'Content-Type: application/json',
        ];
    }

    /**
     * Fail on transport or HTTP errors and decode JSON bodies.
     *
     * @param \curl $curl
     * @param mixed $body
     * @return array
     * @throws \moodle_exception
     */
    protected function check(\curl $curl, $body): array {
        $status = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno() || $status < 200 || $status >= 300) {
            $detail = $curl->get_errno() ? $curl->error : "HTTP $status";
            throw new \moodle_exception('error:pdfextraction', 'block_alle', '', $detail);
        }
        $data = json_decode((string) $body, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Create a curl instance.
     *
     * @return \curl
     */
    protected function new_curl(): \curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        return new \curl();
    }
}
