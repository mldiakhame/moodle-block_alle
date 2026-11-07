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
 * Embeddings through the Hugging Face feature extraction pipeline.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class huggingface_embedder implements embedder {
    /** @var string Feature extraction URL; %s is replaced by the model identifier. */
    public const DEFAULT_ENDPOINT = 'https://router.huggingface.co/hf-inference/models/%s/pipeline/feature-extraction';

    /** @var int Number of texts sent in one request. */
    public const BATCH_SIZE = 32;

    /**
     * Constructor.
     *
     * @param string $apikey Hugging Face access token.
     * @param string $model Embedding model, for instance BAAI/bge-m3.
     * @param string $endpoint Feature extraction URL, %s being the model.
     * @param http_client|null $http HTTP client, replaceable in tests.
     */
    public function __construct(
        /** @var string Hugging Face access token. */
        protected string $apikey,
        /** @var string Embedding model. */
        protected string $model,
        /** @var string Feature extraction URL. */
        protected string $endpoint = self::DEFAULT_ENDPOINT,
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
        $url = sprintf($this->endpoint, $this->model);
        $vectors = [];
        foreach (array_chunk(array_values($texts), self::BATCH_SIZE) as $batch) {
            $response = $this->http->post_json($url, ['inputs' => $batch], ['Authorization: Bearer ' . $this->apikey]);
            // A single input may come back as a flat vector.
            if (isset($response[0]) && is_numeric($response[0])) {
                $response = [$response];
            }
            if (count($response) !== count($batch)) {
                throw new \moodle_exception('error:badresponse', 'block_alle', '', 'Hugging Face embeddings');
            }
            array_push($vectors, ...$response);
        }
        return $vectors;
    }

    #[\Override]
    public function get_label(): string {
        return 'Hugging Face (' . $this->model . ')';
    }
}
