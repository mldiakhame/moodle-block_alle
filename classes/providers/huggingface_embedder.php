<?php
/**
 * HuggingFace Inference API embedder.
 *
 * Uses the feature-extraction pipeline endpoint.
 * Default model: BAAI/bge-m3 (1024-dim, multilingual).
 *
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_uteluqchatbot\providers;

defined('MOODLE_INTERNAL') || die();

class huggingface_embedder implements embedder_interface {

    private const API_BASE  = 'https://api-inference.huggingface.co/models/%s';
    private const BATCH_MAX = 32;

    private string $api_key;
    private string $model;
    private ?string $last_error = null;

    public function __construct(string $api_key, string $model = 'BAAI/bge-m3') {
        $this->api_key = $api_key;
        $this->model   = $model;
    }

    public function embed(string $text): ?array {
        $result = $this->embed_batch([$text]);
        return $result ? $result[0] : null;
    }

    public function embed_batch(array $texts): ?array {
        $all = [];
        foreach (array_chunk($texts, self::BATCH_MAX) as $chunk) {
            $vectors = $this->call_api($chunk);
            if ($vectors === null) {
                return null;
            }
            $all = array_merge($all, $vectors);
        }
        return $all;
    }

    public function test_connection(): array {
        $vector = $this->embed('test');
        if ($vector === null) {
            return ['success' => false, 'message' => $this->last_error ?? 'Unknown error'];
        }
        return [
            'success' => true,
            'message' => 'HuggingFace embedder OK (' . $this->model . ') — dim: ' . count($vector),
        ];
    }

    public function get_last_error(): ?string {
        return $this->last_error;
    }

    private function call_api(array $inputs): ?array {
        $url = sprintf(self::API_BASE, urlencode($this->model));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['inputs' => $inputs]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            $this->last_error = 'cURL error: ' . $curl_err;
            return null;
        }

        // Model loading — retry once after the estimated wait time.
        if ($http_code === 503) {
            $body = json_decode($response, true);
            $wait = min((int)($body['estimated_time'] ?? 20), 30);
            sleep($wait);
            return $this->call_api($inputs);
        }

        if ($http_code !== 200) {
            $this->last_error = 'HTTP ' . $http_code . ': ' . $response;
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded)) {
            $this->last_error = 'Invalid embedding response.';
            return null;
        }

        // Single input → flat float[] → normalise to float[][].
        if (is_numeric($decoded[0])) {
            return [$decoded];
        }

        return $decoded;
    }
}
