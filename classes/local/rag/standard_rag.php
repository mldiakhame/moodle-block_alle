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
 * Single-pass retrieval-augmented generation: one search, then one answer.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class standard_rag implements rag_pipeline {
    /**
     * Constructor.
     *
     * @param retriever $retriever
     * @param llm_provider $llm
     * @param prompt_builder $prompts
     */
    public function __construct(
        /** @var retriever Course search. */
        protected retriever $retriever,
        /** @var llm_provider Language model. */
        protected llm_provider $llm,
        /** @var prompt_builder Course instructions. */
        protected prompt_builder $prompts,
    ) {
    }

    #[\Override]
    public function answer(string $question): string {
        $passages = $this->retriever->retrieve($question);
        if (!$passages) {
            throw new \moodle_exception('error:nocontent', 'block_alle');
        }
        return $this->llm->generate($this->build_prompt($question, $passages));
    }

    /**
     * Full prompt: course instructions, retrieved excerpts and the question.
     *
     * @param string $question
     * @param array[] $passages
     * @return string
     */
    public function build_prompt(string $question, array $passages): string {
        $excerpts = '';
        foreach ($passages as $passage) {
            $excerpts .= $passage['content'] . "\n\n";
        }
        return $this->prompts->build_instructions($question) . "\n\n"
            . "--- Course content excerpts ---\n"
            . $excerpts
            . "--- End of excerpts ---\n\n"
            . "Question: " . $question;
    }
}
