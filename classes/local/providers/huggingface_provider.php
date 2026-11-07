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

namespace block_alle\local\providers;

use block_alle\local\http_client;

/**
 * Text generation through Hugging Face.
 *
 * The endpoint is configurable so that a self-hosted Text Generation Inference
 * server exposing the same OpenAI compatible API can be used instead, which keeps
 * course content inside the institution's network.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class huggingface_provider implements llm_provider {
    /** @var string Default chat completions endpoint of the Hugging Face router. */
    public const DEFAULT_ENDPOINT = 'https://router.huggingface.co/v1/chat/completions';

    /**
     * Constructor.
     *
     * @param string $apikey Hugging Face access token.
     * @param string $model Model identifier on the Hub.
     * @param string $endpoint Chat completions URL.
     * @param http_client|null $http HTTP client, replaceable in tests.
     */
    public function __construct(
        /** @var string Hugging Face access token. */
        protected string $apikey,
        /** @var string Model identifier. */
        protected string $model,
        /** @var string Chat completions URL. */
        protected string $endpoint = self::DEFAULT_ENDPOINT,
        /** @var http_client|null HTTP client. */
        protected ?http_client $http = null,
    ) {
        $this->http = $http ?? new http_client(120);
    }

    #[\Override]
    public function generate(string $prompt): string {
        $response = $this->http->post_json($this->endpoint, [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 2048,
        ], ['Authorization: Bearer ' . $this->apikey]);
        $text = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($text)) {
            throw new \moodle_exception('error:badresponse', 'block_alle', '', 'Hugging Face');
        }
        return $text;
    }

    #[\Override]
    public function get_label(): string {
        return 'Hugging Face (' . $this->model . ')';
    }
}
