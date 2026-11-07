<?php
/**
 * Tool 3 — Query rewriter.
 *
 * When retrieval quality is insufficient, asks the LLM to reformulate
 * the question using different, more domain-specific keywords.
 *
 * @package    block_uteluqchatbot
 * @copyright  2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 */

namespace block_uteluqchatbot\agentic_rag;

defined('MOODLE_INTERNAL') || die();

class tool_query_rewriter {

    private $llm;

    public function __construct(\block_uteluqchatbot\providers\llm_provider_interface $llm) {
        $this->llm = $llm;
    }

    /**
     * Reformulate the question to improve retrieval coverage.
     *
     * @param  string      $original_question The question as first typed by the user.
     * @param  string      $current_question  The last formulation used (may differ after prior rewrites).
     * @return string|null The rewritten question, or null on LLM failure.
     */
    public function execute(string $original_question, string $current_question): ?string {
        $prompt = "You are a search query reformulation assistant for a course knowledge base.\n"
            . "The following question did not retrieve sufficiently relevant passages from the course materials.\n\n"
            . "Original question: " . $original_question . "\n"
            . "Last formulation tried: " . $current_question . "\n\n"
            . "Rewrite the question using different, more specific, or more technical keywords "
            . "that are likely to appear verbatim in course documents (lecture notes, textbooks, slides).\n"
            . "Keep the same language as the original question.\n"
            . "Respond with ONLY the rewritten question — no explanation, no quotes.";

        $rewritten = $this->llm->generate($prompt);

        return $rewritten ? trim($rewritten) : null;
    }
}
