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
 * Text generation through OpenRouter, which gives access to many hosted models.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openrouter_provider implements llm_provider {
    /** @var string Chat completions endpoint. */
    public const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Constructor.
     *
     * @param string $apikey OpenRouter API key.
     * @param string $model Model identifier, for instance openai/gpt-4o.
     * @param http_client|null $http HTTP client, replaceable in tests.
     */
    public function __construct(
        /** @var string OpenRouter API key. */
        protected string $apikey,
        /** @var string Model identifier. */
        protected string $model,
        /** @var http_client|null HTTP client. */
        protected ?http_client $http = null,
    ) {
        $this->http = $http ?? new http_client(120);
    }

    #[\Override]
    public function generate(string $prompt): string {
        $response = $this->http->post_json(self::ENDPOINT, [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ], [
            'Authorization: Bearer ' . $this->apikey,
            'X-Title: ALL-E for Moodle',
        ]);
        $text = $response['choices'][0]['message']['content'] ?? null;
        if (!is_string($text)) {
            throw new \moodle_exception('error:badresponse', 'block_alle', '', 'OpenRouter');
        }
        return $text;
    }

    #[\Override]
    public function get_label(): string {
        return 'OpenRouter (' . $this->model . ')';
    }
}
