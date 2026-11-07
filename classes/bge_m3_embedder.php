<?php
/**
 * BGE-M3 embedder via HuggingFace Inference API.
 *
 * Generates dense vector embeddings (1024-dim) using the BAAI/bge-m3 model.
 * Embeddings are passed directly to Weaviate (vectorizer: none).
 *
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_uteluqchatbot;

defined('MOODLE_INTERNAL') || die();

class bge_m3_embedder {

    private const MODEL_ID  = 'BAAI/bge-m3';
    private const API_URL   = 'https://api-inference.huggingface.co/models/' . self::MODEL_ID;
    private const BATCH_MAX = 32;

    private string $api_key;
    private ?string $last_error = null;

    public function __construct(string $api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Embed a single text string.
     *
     * @param string $text
     * @return float[]|null 1024-dimensional vector, or null on failure
     */
    public function embed(string $text): ?array {
        $result = $this->embed_batch([$text]);
        return $result ? $result[0] : null;
    }

    /**
     * Embed multiple texts in batches.
     *
     * @param string[] $texts
     * @return float[][]|null Array of vectors, or null on failure
     */
    public function embed_batch(array $texts): ?array {
        $all_vectors = [];

        foreach (array_chunk($texts, self::BATCH_MAX) as $chunk) {
            $vectors = $this->call_api($chunk);
            if ($vectors === null) {
                return null;
            }
            $all_vectors = array_merge($all_vectors, $vectors);
        }

        return $all_vectors;
    }

    /**
     * @param string[] $inputs
     * @return float[][]|null
     */
    private function call_api(array $inputs): ?array {
        $data = ['inputs' => $inputs];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
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

        // HuggingFace returns 503 while the model is loading — retry once after a short wait.
        if ($http_code === 503) {
            $body = json_decode($response, true);
            $wait = $body['estimated_time'] ?? 20;
            sleep(min((int)$wait, 30));
            return $this->call_api($inputs);
        }

        if ($http_code !== 200) {
            $this->last_error = 'HTTP ' . $http_code . ': ' . $response;
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = 'JSON decode error: ' . json_last_error_msg();
            return null;
        }

        // The API returns either float[][] directly or a nested structure.
        // BGE-M3 via feature-extraction returns float[][] (one vector per input).
        if (!is_array($decoded) || empty($decoded)) {
            $this->last_error = 'Empty or invalid embedding response.';
            return null;
        }

        // Single input returns a flat float[] — normalise to float[][].
        if (is_float($decoded[0]) || is_int($decoded[0])) {
            return [$decoded];
        }

        return $decoded;
    }

    /**
     * Test the connection and return a diagnostic message.
     *
     * @return array{success: bool, message: string}
     */
    public function test_connection(): array {
        $vector = $this->embed('test connection');
        if ($vector === null) {
            return ['success' => false, 'message' => $this->last_error ?? 'Unknown error'];
        }
        return [
            'success' => true,
            'message' => 'BGE-M3 OK — embedding dimension: ' . count($vector),
        ];
    }

    public function get_last_error(): ?string {
        return $this->last_error;
    }
}
