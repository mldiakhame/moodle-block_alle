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

namespace block_alle\local\rag;

use block_alle\local\providers\llm_provider;

/**
 * Asks the language model to order passages by how well they answer the question.
 *
 * Vector similarity finds passages on the right topic; the model is better at
 * telling which of them actually answer the question.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reranker {
    /** @var int Characters of each passage shown to the model, to keep the prompt short. */
    public const PREVIEW_LENGTH = 400;

    /**
     * Constructor.
     *
     * @param llm_provider $llm
     */
    public function __construct(
        /** @var llm_provider Language model. */
        protected llm_provider $llm,
    ) {
    }

    /**
     * Reorder passages, most relevant first. Passages the model leaves out go last.
     *
     * If the model cannot be reached, the vector search order is kept.
     *
     * @param string $question
     * @param array[] $passages
     * @return array[]
     */
    public function rerank(string $question, array $passages): array {
        $passages = array_values($passages);
        if (count($passages) <= 3) {
            return $passages;
        }

        $list = '';
        foreach ($passages as $i => $passage) {
            $list .= '[' . ($i + 1) . '] ' . \core_text::substr($passage['content'], 0, self::PREVIEW_LENGTH) . "\n\n";
        }
        $prompt = "You are a relevance ranking assistant.\n"
            . "Given the question below and the numbered passages, return ONLY the passage numbers "
            . "ordered from most relevant to least relevant, separated by commas.\n"
            . "Include only passages that are genuinely relevant. Omit irrelevant ones entirely.\n\n"
            . "Question: " . $question . "\n\n"
            . "Passages:\n" . $list
            . "Answer (comma-separated numbers only, e.g. 3,1,5):";

        try {
            $response = $this->llm->generate($prompt);
        } catch (\moodle_exception $e) {
            debugging('ALL-E reranking skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $passages;
        }
        return self::apply_order($response, $passages);
    }

    /**
     * Reorder passages following the numbers found in the model answer.
     *
     * @param string $response Model answer, for instance "3, 1, 5".
     * @param array[] $passages
     * @return array[]
     */
    public static function apply_order(string $response, array $passages): array {
        preg_match_all('/\d+/', $response, $matches);
        $ranked = [];
        foreach ($matches[0] as $number) {
            $index = (int) $number - 1;
            if (isset($passages[$index]) && !isset($ranked[$index])) {
                $ranked[$index] = $passages[$index];
            }
        }
        foreach ($passages as $index => $passage) {
            if (!isset($ranked[$index])) {
                $ranked[$index] = $passage;
            }
        }
        return array_values($ranked);
    }
}
