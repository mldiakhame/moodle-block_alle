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
 * Rephrases a question with the vocabulary of course documents.
 *
 * Learners often ask informally; the rewritten query is more likely to share
 * words with the lecture notes, which helps the vector search.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class query_rewriter {
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
     * Rewrite a question that did not retrieve enough relevant passages.
     *
     * @param string $original Question as the learner typed it.
     * @param string $current Last formulation tried.
     * @return string|null The new formulation, or null when the model gave none.
     */
    public function rewrite(string $original, string $current): ?string {
        $prompt = "You are a search query reformulation assistant for a course knowledge base.\n"
            . "The following question did not retrieve sufficiently relevant passages from the course materials.\n\n"
            . "Original question: " . $original . "\n"
            . "Last formulation tried: " . $current . "\n\n"
            . "Rewrite the question using different, more specific, or more technical keywords "
            . "that are likely to appear verbatim in course documents (lecture notes, textbooks, slides).\n"
            . "Keep the same language as the original question.\n"
            . "Respond with ONLY the rewritten question, with no explanation and no quotes.";

        try {
            $rewritten = trim($this->llm->generate($prompt));
        } catch (\moodle_exception $e) {
            debugging('ALL-E query rewriting skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
        return $rewritten !== '' ? $rewritten : null;
    }
}
