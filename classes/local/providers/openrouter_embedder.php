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
 * Embeddings through OpenRouter's OpenAI compatible endpoint.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openrouter_embedder implements embedder {
    /** @var string Embeddings endpoint. */
    public const ENDPOINT = 'https://openrouter.ai/api/v1/embeddings';

    /** @var int Number of texts sent in one request. */
    public const BATCH_SIZE = 64;

    /**
     * Constructor.
     *
     * @param string $apikey OpenRouter API key.
     * @param string $model Embedding model, for instance baai/bge-m3.
     * @param http_client|null $http HTTP client, replaceable in tests.
     */
    public function __construct(
        /** @var string OpenRouter API key. */
        protected string $apikey,
        /** @var string Embedding model. */
        protected string $model,
        /** @var http_client|null HTTP client. */
        protected ?http_client $http = null,
    ) {
        $this->http = $http ?? new http_client(60);
    }

    #[\Override]
    public function embed(string $text): array {
        return $this->embed_batch([$text])[0];
    }

    #[\Override]
    public function embed_batch(array $texts): array {
        $vectors = [];
        foreach (array_chunk(array_values($texts), self::BATCH_SIZE) as $batch) {
            $response = $this->http->post_json(self::ENDPOINT, [
                'model' => $this->model,
                'input' => $batch,
            ], ['Authorization: Bearer ' . $this->apikey]);

            $items = $response['data'] ?? null;
            if (!is_array($items) || count($items) !== count($batch)) {
                throw new \moodle_exception('error:badresponse', 'block_alle', '', 'OpenRouter embeddings');
            }
            usort($items, fn($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));
            foreach ($items as $item) {
                $vectors[] = $item['embedding'];
            }
        }
        return $vectors;
    }

    #[\Override]
    public function get_label(): string {
        return 'OpenRouter (' . $this->model . ')';
    }
}
