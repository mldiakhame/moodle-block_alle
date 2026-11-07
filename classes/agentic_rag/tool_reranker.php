<?php
/**
 * Tool 2 — LLM-based reranker.
 *
 * Asks the LLM to order retrieved chunks by contextual relevance to the
 * question, returning a re-sorted subset of the original list.
 *
 * @package    block_uteluqchatbot
 * @copyright  2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 */

namespace block_uteluqchatbot\agentic_rag;

defined('MOODLE_INTERNAL') || die();

class tool_reranker {

    private $llm;

    /** Max chars of each chunk shown to the LLM during reranking (keeps prompt short). */
    private int $preview_length = 400;

    public function __construct(\block_uteluqchatbot\providers\llm_provider_interface $llm) {
        $this->llm = $llm;
    }

    /**
     * Re-rank chunks by relevance to the question.
     *
     * @param  string  $question
     * @param  array   $chunks   Raw Weaviate result objects.
     * @return array   Chunks sorted from most to least relevant.
     */
    public function execute(string $question, array $chunks): array {
        if (count($chunks) <= 3) {
            return $chunks;
        }

        $passages = '';
        foreach ($chunks as $i => $chunk) {
            $preview   = mb_substr($chunk['texte'] ?? '', 0, $this->preview_length, 'UTF-8');
            $passages .= '[' . ($i + 1) . '] ' . $preview . "\n\n";
        }

        $prompt = "You are a relevance ranking assistant.\n"
            . "Given the question below and the numbered passages, return ONLY the passage numbers "
            . "ordered from most relevant to least relevant, separated by commas.\n"
            . "Include only passages that are genuinely relevant. Omit irrelevant ones entirely.\n\n"
            . "Question: " . $question . "\n\n"
            . "Passages:\n" . $passages
            . "Answer (comma-separated numbers only, e.g. 3,1,5):";

        $response = $this->llm->generate($prompt);

        if ($response === null) {
            return $chunks;
        }

        // Parse comma-separated numbers from the LLM response.
        preg_match_all('/\d+/', $response, $matches);
        $seen    = [];
        $ranked  = [];

        foreach ($matches[0] as $raw) {
            $idx = (int)$raw - 1;
            if (isset($chunks[$idx]) && !isset($seen[$idx])) {
                $ranked[]    = $chunks[$idx];
                $seen[$idx]  = true;
            }
        }

        // Append unreferenced chunks at the end (fallback).
        foreach ($chunks as $idx => $chunk) {
            if (!isset($seen[$idx])) {
                $ranked[] = $chunk;
            }
        }

        return $ranked;
    }
}
