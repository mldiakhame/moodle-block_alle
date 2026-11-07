<?php
/**
 * HuggingFace LLM provider for TÉLUQ Chatbot.
 *
 * Uses the HuggingFace Inference API chat completions endpoint
 * (OpenAI-compatible) available for instruction-tuned models.
 *
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_uteluqchatbot\providers;

defined('MOODLE_INTERNAL') || die();

class huggingface_provider implements llm_provider_interface {

    private const API_BASE = 'https://api-inference.huggingface.co/models/%s/v1/chat/completions';

    private string $api_key;
    private string $model;
    private ?string $last_error = null;

    public function __construct(string $api_key, string $model) {
        $this->api_key = $api_key;
        $this->model   = $model;
    }

    public function generate(string $prompt): ?string {
        $url  = sprintf(self::API_BASE, urlencode($this->model));
        $data = [
            'model'    => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 2048,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 120,
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
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = 'JSON decode error: ' . json_last_error_msg();
            return null;
        }

        $text = $decoded['choices'][0]['message']['content'] ?? null;
        if ($text === null) {
            $this->last_error = 'Unexpected response structure: ' . $response;
        }

        return $text;
    }

    public function stream_generate(string $prompt, callable $on_token): bool {
        $url  = sprintf(self::API_BASE, urlencode($this->model));
        $data = [
            'model'       => $this->model,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'  => 2048,
            'stream'      => true,
        ];

        $buffer    = '';
        $http_code = 0;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use (&$buffer, $on_token) {
                $buffer .= $chunk;
                $lines   = explode("\n", $buffer);
                $buffer  = array_pop($lines);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'data: ') !== 0) {
                        continue;
                    }
                    $payload = substr($line, 6);
                    if ($payload === '[DONE]') {
                        continue;
                    }
                    $json  = json_decode($payload, true);
                    $token = $json['choices'][0]['delta']['content'] ?? '';
                    if ($token !== '') {
                        $on_token($token);
                    }
                }
                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            $this->last_error = 'cURL error: ' . $curl_err;
            return false;
        }
        if ($http_code !== 200) {
            $this->last_error = 'HTTP ' . $http_code;
            return false;
        }
        return true;
    }

    public function get_last_error(): ?string {
        return $this->last_error;
    }
}
