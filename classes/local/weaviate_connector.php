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
 * Weaviate implementation of the vector store.
 *
 * Collections use the "none" vectorizer: vectors are always computed by the
 * plugin's embedder, so the same model embeds documents and questions.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class weaviate_connector implements vector_store {
    /** @var int Objects sent in one batch request. */
    public const BATCH_SIZE = 50;

    /** @var string Base URL, without trailing slash. */
    protected string $url;

    /**
     * Constructor.
     *
     * @param string $url Weaviate URL; https:// is added when no scheme is given.
     * @param string $apikey Weaviate API key.
     * @param http_client|null $http HTTP client, replaceable in tests.
     */
    public function __construct(
        string $url,
        /** @var string Weaviate API key. */
        protected string $apikey,
        /** @var http_client|null HTTP client. */
        protected ?http_client $http = null,
    ) {
        $url = rtrim(trim($url), '/');
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        $this->url = $url;
        $this->http = $http ?? new http_client(120);
    }

    #[\Override]
    public function reset_collection(string $collection): void {
        $this->check_name($collection);
        if (in_array($collection, $this->list_collections(), true)) {
            $status = $this->http->delete($this->url . '/v1/schema/' . $collection, $this->auth());
            if ($status !== 200 && $status !== 204) {
                throw new \moodle_exception('error:vectorstore', 'block_alle', '', "DELETE schema, HTTP $status");
            }
        }
        $this->http->post_json($this->url . '/v1/schema', [
            'class' => $collection,
            'vectorizer' => 'none',
            'vectorIndexConfig' => ['distance' => 'cosine'],
            'properties' => [
                ['name' => 'content', 'dataType' => ['text']],
                ['name' => 'source', 'dataType' => ['text']],
                ['name' => 'courseid', 'dataType' => ['int']],
            ],
        ], $this->auth());
    }

    #[\Override]
    public function add_passages(string $collection, array $passages, array $vectors, int $courseid): void {
        $this->check_name($collection);
        if (count($passages) !== count($vectors)) {
            throw new \coding_exception('One vector is expected per passage.');
        }
        $objects = [];
        foreach (array_values($passages) as $i => $passage) {
            $objects[] = [
                'class' => $collection,
                'properties' => [
                    'content' => $passage['content'],
                    'source' => $passage['source'] ?? '',
                    'courseid' => $courseid,
                ],
                'vector' => $vectors[$i],
            ];
        }
        foreach (array_chunk($objects, self::BATCH_SIZE) as $batch) {
            $results = $this->http->post_json($this->url . '/v1/batch/objects', ['objects' => $batch], $this->auth());
            foreach ($results as $result) {
                $errors = $result['result']['errors']['error'] ?? [];
                if (!empty($errors)) {
                    throw new \moodle_exception('error:vectorstore', 'block_alle', '', $errors[0]['message'] ?? 'batch insert');
                }
            }
        }
    }

    #[\Override]
    public function search(string $collection, array $vector, int $limit): array {
        $this->check_name($collection);
        $vectorjson = json_encode(array_map('floatval', $vector));
        $limit = max(1, $limit);
        $query = "{ Get { $collection (limit: $limit, nearVector: {vector: $vectorjson}) " .
            "{ content source _additional { distance } } } }";

        $response = $this->http->post_json($this->url . '/v1/graphql', ['query' => $query], $this->auth());
        if (!empty($response['errors'])) {
            throw new \moodle_exception('error:vectorstore', 'block_alle', '', $response['errors'][0]['message'] ?? 'GraphQL');
        }

        $passages = [];
        foreach ($response['data']['Get'][$collection] ?? [] as $hit) {
            $passages[] = [
                'content' => (string) ($hit['content'] ?? ''),
                'source' => (string) ($hit['source'] ?? ''),
                'distance' => isset($hit['_additional']['distance']) ? (float) $hit['_additional']['distance'] : null,
            ];
        }
        return $passages;
    }

    /**
     * Count the passages of a collection.
     *
     * @param string $collection
     * @return int
     */
    public function count(string $collection): int {
        $this->check_name($collection);
        $response = $this->http->post_json($this->url . '/v1/graphql', [
            'query' => "{ Aggregate { $collection { meta { count } } } }",
        ], $this->auth());
        return (int) ($response['data']['Aggregate'][$collection][0]['meta']['count'] ?? 0);
    }

    /**
     * Names of the existing collections.
     *
     * @return string[]
     */
    public function list_collections(): array {
        $schema = $this->http->get_json($this->url . '/v1/schema', $this->auth());
        return array_column($schema['classes'] ?? [], 'class');
    }

    /**
     * Authorization header.
     *
     * @return string[]
     */
    protected function auth(): array {
        return ['Authorization: Bearer ' . $this->apikey];
    }

    /**
     * Collection names end up inside GraphQL queries, so only safe names are accepted.
     *
     * @param string $collection
     * @throws \coding_exception
     */
    protected function check_name(string $collection): void {
        if (!preg_match('/^[A-Z][A-Za-z0-9_]{0,99}$/', $collection)) {
            throw new \coding_exception('Invalid Weaviate collection name: ' . $collection);
        }
    }
}
