<?php
/**
 * OpenRouter embedder — uses the OpenAI-compatible /api/v1/embeddings endpoint.
 *
 * Default model: text-embedding-3-small (widely available on OpenRouter).
 * Any OpenRouter embedding model can be configured.
 *
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_uteluqchatbot\providers;

defined('MOODLE_INTERNAL') || die();

class openrouter_embedder implements embedder_interface {

    private const API_URL   = 'https://openrouter.ai/api/v1/embeddings';
    private const BATCH_MAX = 64;

    private string $api_key;
    private string $model;
    private ?string $last_error = null;

    public function __construct(string $api_key, string $model = 'openai/text-embedding-3-small') {
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
            'message' => 'OpenRouter embedder OK (' . $this->model . ') — dim: ' . count($vector),
        ];
    }

    public function get_last_error(): ?string {
        return $this->last_error;
    }

    /**
     * OpenRouter uses the OpenAI-compatible embeddings format:
     * POST /api/v1/embeddings  {"model": "...", "input": [...]}
     * Response: {"data": [{"embedding": [...], "index": 0}, ...]}
     */
    private function call_api(array $inputs): ?array {
        $data = [
            'model' => $this->model,
            'input' => count($inputs) === 1 ? $inputs[0] : $inputs,
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
                'HTTP-Referer: moodle-block-uteluqchatbot',
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

        if ($http_code !== 200) {
            $this->last_error = 'HTTP ' . $http_code . ': ' . $response;
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['data'])) {
            $this->last_error = 'Invalid embedding response: ' . $response;
            return null;
        }

        // Sort by index to preserve order, then extract vectors.
        $items = $decoded['data'];
        usort($items, function($a, $b) { return $a['index'] <=> $b['index']; });

        return array_map(function($item) { return $item['embedding']; }, $items);
    }
}
