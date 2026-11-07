<?php
/**
 * Weaviate Connector class for Moodle.
 *
 * Manages all interactions with a Weaviate instance:
 * - Collection management (vectorizer: none — embeddings handled externally)
 * - Document indexing with pre-computed BGE-M3 vectors
 * - Semantic search via nearVector
 * - RAG generation via an injected LLM provider
 *
 * @package    block_uteluqchatbot
 * @copyright  2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_uteluqchatbot;

use block_uteluqchatbot\providers\llm_provider_interface;
use block_uteluqchatbot\providers\embedder_interface;

class weaviate_connector {

    private string $api_url;
    private string $api_key;

    /** @var embedder_interface */
    private embedder_interface $embedder;

    /** @var llm_provider_interface */
    private llm_provider_interface $llm;

    private ?string $last_error = null;
    private int $batch_size = 50;

    /**
     * @param string               $api_url  Weaviate instance URL
     * @param string               $api_key  Weaviate API key
     * @param embedder_interface   $embedder Embedding provider instance
     * @param llm_provider_interface $llm    LLM provider for generation
     */
    public function __construct(
        string $api_url,
        string $api_key,
        embedder_interface $embedder,
        llm_provider_interface $llm
    ) {
        $this->api_url  = rtrim($api_url, '/');
        $this->api_key  = $api_key;
        $this->embedder = $embedder;
        $this->llm      = $llm;
    }

    // =========================================================================
    // Public API — generation
    // =========================================================================

    /**
     * Generate a direct LLM response without RAG context.
     *
     * @param string $question
     * @return string|null
     */
    public function get_llm_response(string $question): ?string {
        $response = $this->llm->generate($question);
        if ($response === null) {
            $this->last_error = $this->llm->get_last_error();
        }
        return $response;
    }

    /**
     * Full RAG pipeline: embed question → search Weaviate → generate answer.
     *
     * @param string $course_name
     * @param string $collection   Weaviate collection name
     * @param string $question
     * @param int    $user_id
     * @param int    $course_id
     * @return string|null
     */
    public function get_question_answer(
        string $course_name,
        string $collection,
        string $question,
        int $user_id,
        int $course_id
    ): ?string {
        global $DB;

        // 1. Embed the question.
        $vector = $this->embedder->embed($question);
        if ($vector === null) {
            $this->last_error = $this->embedder->get_last_error();
            return null;
        }

        // 2. Retrieve relevant chunks from Weaviate.
        $chunks = $this->search_near_vector($collection, $vector, 10);
        if ($chunks === null) {
            return null;
        }

        // 3. Build context from retrieved chunks.
        $context = '';
        foreach ($chunks as $chunk) {
            if (!empty($chunk['texte'])) {
                $context .= $chunk['texte'] . "\n\n";
            }
        }

        // 4. Load conversation history.
        $historique   = $this->build_history($user_id);

        // 5. Load teacher prompt.
        $prompt_record = $DB->get_record(
            'block_uteluqchatbot_prompts',
            ['userid' => $user_id, 'courseid' => $course_id]
        );
        $task = $prompt_record ? $prompt_record->prompt : get_string('default_prompt', 'block_uteluqchatbot');

        $task = str_replace('[[ coursename ]]', $course_name, $task);
        $task = str_replace('[[ question ]]',   $question,    $task);
        $task = str_replace('[[ history ]]',    $historique,  $task);

        // 6. Build the final prompt with retrieved context.
        $full_prompt = $task . "\n\n"
            . "--- Course content excerpts ---\n"
            . $context
            . "--- End of excerpts ---\n\n"
            . "Question: " . $question;

        // 7. Generate the answer.
        $answer = $this->llm->generate($full_prompt);
        if ($answer === null) {
            $this->last_error = $this->llm->get_last_error();
        }

        return $answer;
    }

    // =========================================================================
    // Public API — vector search
    // =========================================================================

    /**
     * Search a collection using a pre-computed vector (nearVector).
     *
     * @param string  $collection
     * @param float[] $vector
     * @param int     $limit
     * @return array[]|null  Array of result objects, or null on error
     */
    public function search_near_vector(string $collection, array $vector, int $limit = 5): ?array {
        $vector_json = json_encode($vector);
        $query = [
            'query' => "{
                Get {
                    $collection (
                        limit: $limit
                        nearVector: {
                            vector: $vector_json
                        }
                    ) {
                        texte
                        cours
                        _additional { distance }
                    }
                }
            }"
        ];

        $response = $this->graphql_request($query);
        if ($response === null) {
            return null;
        }

        return $response['data']['Get'][$collection] ?? [];
    }

    // =========================================================================
    // Public API — collection management
    // =========================================================================

    /**
     * Create a collection with vectorizer "none" (externally managed embeddings).
     *
     * @param string $class_name
     * @return bool
     */
    public function create_collection(string $class_name): bool {
        $endpoint = $this->api_url . '/v1/schema';
        $data = [
            'class'      => $class_name,
            'vectorizer' => 'none',
            'properties' => [
                ['name' => 'texte',    'dataType' => ['text']],
                ['name' => 'cours',    'dataType' => ['text']],
                ['name' => 'filepath', 'dataType' => ['text']],
            ],
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => $this->base_headers(),
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $this->last_error = curl_error($ch);
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        if ($http_code !== 200) {
            $this->last_error = get_string('http_error', 'block_uteluqchatbot') . $http_code . ': ' . $response;
            return false;
        }

        return true;
    }

    /**
     * Delete a collection.
     *
     * @param string $class_name
     * @return bool
     */
    public function delete_collection(string $class_name): bool {
        $endpoint = $this->api_url . '/v1/schema/' . urlencode($class_name);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => $this->base_headers(),
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $this->last_error = curl_error($ch);
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        if ($http_code !== 200 && $http_code !== 204) {
            $this->last_error = get_string('http_error', 'block_uteluqchatbot') . $http_code . ': ' . $response;
            return false;
        }

        return true;
    }

    /**
     * Check whether a collection exists in Weaviate.
     *
     * @param string $class_name
     * @return bool
     */
    public function collection_exists(string $class_name): bool {
        $endpoint = $this->api_url . '/v1/schema';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => $this->base_headers(),
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $this->last_error = curl_error($ch);
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        if ($http_code !== 200) {
            $this->last_error = get_string('http_error', 'block_uteluqchatbot') . $http_code . ': ' . $response;
            return false;
        }

        $schema = json_decode($response, true);
        if (isset($schema['classes'])) {
            foreach ($schema['classes'] as $class) {
                if ($class['class'] === $class_name) {
                    return true;
                }
            }
        }

        return false;
    }

    // =========================================================================
    // Public API — indexing
    // =========================================================================

    /**
     * Index a text file into Weaviate using BGE-M3 embeddings.
     *
     * If the collection already exists it is deleted and recreated so that
     * the vectorizer is always "none" (externally managed vectors).
     *
     * @param string $file_path
     * @param string $collection
     * @param string $course_id
     * @return bool
     */
    public function index_text_file(string $file_path, string $collection, string $course_id, bool $recreate = true): bool {
        if (!file_exists($file_path)) {
            $this->last_error = get_string('file_not_found', 'block_uteluqchatbot') . $file_path;
            return false;
        }

        $text = file_get_contents($file_path);
        if ($text === false) {
            $this->last_error = get_string('unable_to_read_file', 'block_uteluqchatbot');
            return false;
        }

        $chunks = $this->chunk_text($this->clean_text($text));
        if (empty($chunks)) {
            $this->last_error = 'No text chunks produced from file.';
            return false;
        }

        // Recreate collection only on first file of the batch.
        if ($recreate) {
            if ($this->collection_exists($collection)) {
                if (!$this->delete_collection($collection)) {
                    return false;
                }
            }
            if (!$this->create_collection($collection)) {
                return false;
            }
        }

        // Index in batches.
        $batch_endpoint = $this->api_url . '/v1/batch/objects';

        foreach (array_chunk($chunks, $this->batch_size) as $chunk_batch) {
            // Embed the whole batch in one API call.
            $vectors = $this->embedder->embed_batch($chunk_batch);
            if ($vectors === null) {
                $this->last_error = 'Embedding failed: ' . $this->embedder->get_last_error();
                return false;
            }

            $objects = [];
            foreach ($chunk_batch as $i => $chunk_text) {
                $objects[] = [
                    'class'      => $collection,
                    'properties' => [
                        'texte'    => $chunk_text,
                        'cours'    => $course_id,
                        'filepath' => $file_path,
                    ],
                    'vector' => $vectors[$i],
                ];
            }

            if (!$this->batch_insert($batch_endpoint, $objects)) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function get_last_error(): ?string {
        return $this->last_error;
    }

    public function set_batch_size(int $size): void {
        $this->batch_size = $size;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function build_history(int $user_id): string {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT question, answer
               FROM {block_uteluqchatbot_conversations}
              WHERE userid = :userid
           ORDER BY timecreated DESC
              LIMIT 10",
            ['userid' => $user_id]
        );

        if (empty($rows)) {
            return '';
        }

        // Reverse so history is presented oldest-first (chronological order for the LLM).
        $rows = array_reverse(array_values($rows));

        $historique = get_string('previous_interactions_history', 'block_uteluqchatbot') . "\n\n";
        $i = 1;
        foreach ($rows as $row) {
            $historique .= get_string('previous_question', 'block_uteluqchatbot', $i) . $row->question . "\n";
            $historique .= get_string('answer', 'block_uteluqchatbot') . $row->answer . "\n\n";
            $i++;
        }

        return $historique;
    }

    private function graphql_request(array $query): ?array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->api_url . '/v1/graphql',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($query),
            CURLOPT_HTTPHEADER     => $this->base_headers(),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch) || $http_code !== 200) {
            $this->last_error = curl_error($ch) ?: get_string('http_code', 'block_uteluqchatbot') . $http_code;
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = get_string('json_decode_error', 'block_uteluqchatbot') . json_last_error_msg();
            return null;
        }

        return $decoded;
    }

    private function batch_insert(string $endpoint, array $objects): bool {
        $json_data = json_encode(['objects' => $objects]);

        $max_retries   = 5;
        $retry_delay   = 1;
        $max_delay     = 8;

        for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
            if ($attempt > 0) {
                sleep(min($retry_delay * (2 ** ($attempt - 1)), $max_delay));
            }

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json_data,
                CURLOPT_HTTPHEADER     => $this->base_headers(),
                CURLOPT_TIMEOUT        => 300,
            ]);

            $response  = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err  = curl_error($ch);
            curl_close($ch);

            if ($curl_err) {
                $this->last_error = $curl_err;
                continue;
            }

            if ($http_code >= 500) {
                $this->last_error = get_string('http_error', 'block_uteluqchatbot') . $http_code;
                continue;
            }

            if ($http_code < 200 || $http_code >= 300) {
                $this->last_error = get_string('http_error', 'block_uteluqchatbot') . $http_code . ': ' . $response;
                return false;
            }

            return true;
        }

        $this->last_error = get_string('failure_after_retries', 'block_uteluqchatbot')
            . $max_retries
            . get_string('last_error', 'block_uteluqchatbot')
            . ($http_code ?? '?');
        return false;
    }

    private function base_headers(): array {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key,
        ];
    }

    private function clean_text(string $text): string {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = str_replace(['\\', "\f", "\b"], ['\\\\', '', ''], $text);
        return trim($text);
    }

    private function chunk_text(string $text, int $chunk_size = 1500, int $overlap = 300): array {
        $words   = explode(' ', $text);
        $chunks  = [];
        $i       = 0;
        $n       = count($words);

        while ($i < $n) {
            $current_chunk = [];
            $current_len   = 0;
            $j             = $i;

            while ($j < $n) {
                $wlen = mb_strlen($words[$j], 'UTF-8');
                if ($current_len + $wlen + 1 > $chunk_size && !empty($current_chunk)) {
                    break;
                }
                $current_chunk[] = $words[$j];
                $current_len    += $wlen + 1;
                $j++;
            }

            $chunks[] = implode(' ', $current_chunk);

            // The last words have been consumed: stop, otherwise the overlap step
            // below keeps emitting ever-shorter copies of the text's tail.
            if ($j >= $n) {
                break;
            }

            // Advance by (chunk - overlap) so next chunk reuses the last ~overlap chars.
            $overlap_words = 0;
            $overlap_len   = 0;
            for ($k = count($current_chunk) - 1; $k >= 0; $k--) {
                $overlap_len += mb_strlen($current_chunk[$k], 'UTF-8') + 1;
                $overlap_words++;
                if ($overlap_len >= $overlap) {
                    break;
                }
            }

            $advance = max(1, count($current_chunk) - $overlap_words);
            $i      += $advance;
        }

        return $chunks;
    }
}
