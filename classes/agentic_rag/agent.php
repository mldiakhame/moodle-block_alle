<?php
/**
 * Agentic RAG orchestrator.
 *
 * Coordinates four tools in a decision loop:
 *   1. Retrieval   — semantic search in Weaviate
 *   2. Reranker    — LLM-based relevance re-scoring
 *   3. Query Rewriter — reformulation when retrieval quality is low
 *   4. Answer Generator — final response with source citations
 *
 * @package    block_uteluqchatbot
 * @copyright  2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 */

namespace block_uteluqchatbot\agentic_rag;

defined('MOODLE_INTERNAL') || die();

class agent {

    private $embedder;
    private $llm;
    private $connector;
    private $collection;
    private ?string $last_error = null;

    /** Maximum query-rewrite attempts before giving up. */
    private int $max_rewrites = 2;

    /** Weaviate cosine distance above which retrieval is considered insufficient. */
    private float $relevance_threshold = 0.55;

    public function __construct(
        \block_uteluqchatbot\providers\embedder_interface   $embedder,
        \block_uteluqchatbot\providers\llm_provider_interface $llm,
        \block_uteluqchatbot\weaviate_connector             $connector,
        string                                              $collection
    ) {
        $this->embedder   = $embedder;
        $this->llm        = $llm;
        $this->connector  = $connector;
        $this->collection = $collection;
    }

    /**
     * Run the full agentic pipeline and return an answer.
     */
    public function answer(
        string $question,
        string $course_name,
        int    $user_id,
        int    $course_id
    ): ?string {

        $retrieval = new tool_retrieval($this->embedder, $this->connector, $this->collection);
        $reranker  = new tool_reranker($this->llm);
        $rewriter  = new tool_query_rewriter($this->llm);
        $generator = new tool_answer_generator($this->llm);

        $current_question = $question;
        $chunks           = [];

        // ── Retrieval loop with optional query rewriting ──────────────────────
        for ($attempt = 0; $attempt <= $this->max_rewrites; $attempt++) {

            $chunks = $retrieval->execute($current_question);

            if ($chunks === null) {
                $this->last_error = $this->connector->get_last_error()
                    ?? $this->embedder->get_last_error();
                return null;
            }

            if ($this->chunks_are_sufficient($chunks) || $attempt === $this->max_rewrites) {
                break;
            }

            // Tool 3 — rewrite and retry
            $rewritten = $rewriter->execute($question, $current_question);
            if ($rewritten === null || trim($rewritten) === trim($current_question)) {
                break;
            }
            $current_question = $rewritten;
        }

        if (empty($chunks)) {
            $this->last_error = 'No relevant content found in the course knowledge base.';
            return null;
        }

        // ── Tool 2 — Reranker ─────────────────────────────────────────────────
        $ranked = $reranker->execute($question, $chunks);

        // ── Tool 4 — Answer Generator ─────────────────────────────────────────
        $answer = $generator->execute($question, $ranked, $course_name, $user_id, $course_id);

        if ($answer === null) {
            $this->last_error = $this->llm->get_last_error();
        }

        return $answer;
    }

    public function get_last_error(): ?string {
        return $this->last_error;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function chunks_are_sufficient(array $chunks): bool {
        if (empty($chunks)) {
            return false;
        }

        $relevant = 0;
        foreach ($chunks as $chunk) {
            $distance = $chunk['_additional']['distance'] ?? 1.0;
            if ($distance < $this->relevance_threshold && !empty(trim($chunk['texte'] ?? ''))) {
                $relevant++;
            }
        }

        return $relevant >= 2;
    }
}
