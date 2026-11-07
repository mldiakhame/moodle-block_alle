<?php
/**
 * PDF Extract API class for Moodle.
 *
 * @package    block_uteluqchatbot
 * @subpackage pdfextractapi
 * @copyright  2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_uteluqchatbot;

class pdf_extract_api
{
    private $client_id;
    private $client_secret;
    private $access_token;

    public function __construct($client_id, $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
    }

    private function curl_exec_or_throw($ch, string $context): string
    {
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \Exception($context . ': cURL error: ' . $err);
        }
        return $response;
    }

    public function get_access_token(): void
    {
        $ch = curl_init();
        $post_data = 'client_id=' . urlencode($this->client_id) . '&client_secret=' . urlencode($this->client_secret);
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://pdf-services.adobe.io/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post_data,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response    = $this->curl_exec_or_throw($ch, 'get_access_token');
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status !== 200) {
            throw new \Exception('Failed to obtain Adobe access token. HTTP ' . $http_status);
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new \Exception('Adobe API did not return an access_token. Response: ' . $response);
        }

        $this->access_token = $data['access_token'];
    }

    public function upload_asset(string $file_path, string $media_type = 'application/pdf'): string
    {
        // Step 1 — request pre-signed upload URI.
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://pdf-services.adobe.io/assets',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['mediaType' => $media_type]),
            CURLOPT_HTTPHEADER     => [
                'X-API-Key: ' . $this->client_id,
                'Authorization: Bearer ' . $this->access_token,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response    = $this->curl_exec_or_throw($ch, 'upload_asset/get-uri');
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status !== 200) {
            throw new \Exception('Failed to get upload URI. HTTP ' . $http_status . '. Response: ' . $response);
        }

        $data = json_decode($response, true);
        if (empty($data['uploadUri']) || empty($data['assetID'])) {
            throw new \Exception('Adobe API did not return uploadUri/assetID. Response: ' . $response);
        }

        $upload_uri = $data['uploadUri'];
        $asset_id   = $data['assetID'];

        // Step 2 — PUT the file to the pre-signed URI.
        $fh = fopen($file_path, 'r');
        if ($fh === false) {
            throw new \Exception('Cannot open file for upload: ' . $file_path);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $upload_uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PUT            => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: ' . $media_type],
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_INFILE         => $fh,
            CURLOPT_INFILESIZE     => filesize($file_path),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 120,
        ]);

        $this->curl_exec_or_throw($ch, 'upload_asset/put-file');
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($http_status !== 200) {
            throw new \Exception('Failed to upload PDF file. HTTP ' . $http_status);
        }

        return $asset_id;
    }

    public function create_job(string $asset_id): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://pdf-services.adobe.io/operation/extractpdf',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'assetID'              => $asset_id,
                'getCharBounds'        => false,
                'includeStyling'       => false,
                'elementsToExtract'    => ['text'],
                'tableOutputFormat'    => 'xlsx',
                'renditionsToExtract'  => [],
                'includeHeaderFooter'  => false,
                'tagEncapsulatedText'  => [],
                'notifiers'            => [],
            ]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->access_token,
                'x-api-key: ' . $this->client_id,
                'Content-Type: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response    = $this->curl_exec_or_throw($ch, 'create_job');
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($response, 0, $header_size);
        $body    = substr($response, $header_size);

        if ($http_status !== 201) {
            throw new \Exception('Failed to create extraction job. HTTP ' . $http_status . '. Body: ' . $body);
        }

        if (!preg_match('/location:\s*([^\r\n]+)/i', $headers, $m)) {
            throw new \Exception('Job created but no Location header found. Headers: ' . $headers);
        }

        return trim($m[1]);
    }

    public function get_job_status(string $status_url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $status_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->access_token,
                'x-api-key: ' . $this->client_id,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response    = $this->curl_exec_or_throw($ch, 'get_job_status');
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status !== 200) {
            throw new \Exception('Failed to get job status. HTTP ' . $http_status . '. Response: ' . $response);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['status'])) {
            throw new \Exception('Invalid JSON in job status response: ' . $response);
        }

        $status = $data['status'];

        if ($status === 'done') {
            if (isset($data['content']['downloadUri'])) {
                return ['status' => 'done', 'downloadUri' => $data['content']['downloadUri']];
            }
            throw new \Exception('Job done but downloadUri missing in response.');
        }

        if ($status === 'failed') {
            throw new \Exception('Adobe extraction job failed.');
        }

        return ['status' => 'in progress'];
    }

    public function download_asset(string $download_uri): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $download_uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response    = $this->curl_exec_or_throw($ch, 'download_asset');
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status !== 200) {
            throw new \Exception('Failed to download asset. HTTP ' . $http_status);
        }

        return $response;
    }

    public function extraire_texte(string $contenu): string
    {
        $donnees = json_decode($contenu, true);
        if ($donnees === null) {
            throw new \Exception('Error decoding JSON from Adobe extract response.');
        }

        $texte = '';
        if (isset($donnees['elements']) && is_array($donnees['elements'])) {
            foreach ($donnees['elements'] as $element) {
                if (isset($element['Text'])) {
                    $texte .= $element['Text'] . "\n\n";
                }
            }
        }

        return trim($texte);
    }

    public function process_pdf(string $file_path): string
    {
        $this->get_access_token();
        $asset_id    = $this->upload_asset($file_path);
        $location    = $this->create_job($asset_id);

        $download_uri = null;
        while (true) {
            sleep(2);
            $job = $this->get_job_status($location);
            if ($job['status'] === 'done') {
                $download_uri = $job['downloadUri'];
                break;
            }
        }

        $raw  = $this->download_asset($download_uri);
        return $this->extraire_texte($raw);
    }
}
