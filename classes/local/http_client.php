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
 * Thin wrapper around Moodle's curl class for the JSON APIs the assistant talks to.
 *
 * Going through core curl means the site proxy and the outgoing HTTP security
 * settings apply to every call, and TLS certificates are always verified.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class http_client {
    /**
     * Constructor.
     *
     * @param int $timeout Request timeout in seconds.
     */
    public function __construct(
        /** @var int Request timeout in seconds. */
        protected int $timeout = 60,
    ) {
    }

    /**
     * Send a JSON body and decode the JSON answer.
     *
     * @param string $url Endpoint.
     * @param array $payload Body, encoded as JSON.
     * @param string[] $headers Extra headers, for instance the Authorization one.
     * @return array Decoded response.
     * @throws \moodle_exception When the request fails or the answer is not JSON.
     */
    public function post_json(string $url, array $payload, array $headers = []): array {
        $curl = $this->new_curl();
        $curl->setHeader(array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers));
        $body = $curl->post($url, json_encode($payload), ['CURLOPT_TIMEOUT' => $this->timeout]);
        return $this->decode($curl, $url, $body);
    }

    /**
     * Send a GET request and decode the JSON answer.
     *
     * @param string $url Endpoint.
     * @param string[] $headers Extra headers.
     * @return array Decoded response.
     * @throws \moodle_exception When the request fails or the answer is not JSON.
     */
    public function get_json(string $url, array $headers = []): array {
        $curl = $this->new_curl();
        $curl->setHeader(array_merge(['Accept: application/json'], $headers));
        $body = $curl->get($url, [], ['CURLOPT_TIMEOUT' => $this->timeout]);
        return $this->decode($curl, $url, $body);
    }

    /**
     * Send a DELETE request.
     *
     * @param string $url Endpoint.
     * @param string[] $headers Extra headers.
     * @return int HTTP status code.
     * @throws \moodle_exception When the request cannot be sent.
     */
    public function delete(string $url, array $headers = []): int {
        $curl = $this->new_curl();
        $curl->setHeader($headers);
        $curl->delete($url, [], ['CURLOPT_TIMEOUT' => $this->timeout]);
        $this->check_transport($curl, $url);
        return (int) ($curl->get_info()['http_code'] ?? 0);
    }

    /**
     * Create a configured curl instance.
     *
     * @return \curl
     */
    protected function new_curl(): \curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        return new \curl();
    }

    /**
     * Turn a raw curl answer into an array, or throw a readable error.
     *
     * @param \curl $curl The curl instance used for the request.
     * @param string $url Endpoint, for error messages.
     * @param mixed $body Raw response body.
     * @return array
     * @throws \moodle_exception
     */
    protected function decode(\curl $curl, string $url, $body): array {
        $this->check_transport($curl, $url);
        $status = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new \moodle_exception('error:http', 'block_alle', '', (object) [
                'host' => parse_url($url, PHP_URL_HOST),
                'status' => $status,
                'detail' => \core_text::substr(trim((string) $body), 0, 300),
            ]);
        }
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('error:badresponse', 'block_alle', '', parse_url($url, PHP_URL_HOST));
        }
        return $decoded;
    }

    /**
     * Throw when curl could not reach the server at all.
     *
     * @param \curl $curl The curl instance used for the request.
     * @param string $url Endpoint, for error messages.
     * @throws \moodle_exception
     */
    protected function check_transport(\curl $curl, string $url): void {
        if ($curl->get_errno()) {
            throw new \moodle_exception('error:unreachable', 'block_alle', '', (object) [
                'host' => parse_url($url, PHP_URL_HOST),
                'detail' => $curl->error,
            ]);
        }
    }
}
