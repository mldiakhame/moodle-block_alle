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
 * Writes the final answer from the best passages, citing them as [Source N].
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class answer_generator {
    /** @var int Passages given to the model. */
    public const MAX_PASSAGES = 6;

    /**
     * Constructor.
     *
     * @param llm_provider $llm
     * @param prompt_builder $prompts
     */
    public function __construct(
        /** @var llm_provider Language model. */
        protected llm_provider $llm,
        /** @var prompt_builder Course instructions. */
        protected prompt_builder $prompts,
    ) {
    }

    /**
     * Answer a question from ranked passages.
     *
     * @param string $question
     * @param array[] $passages Most relevant first.
     * @return string
     * @throws \moodle_exception
     */
    public function generate(string $question, array $passages): string {
        return $this->llm->generate($this->build_prompt($question, $passages));
    }

    /**
     * Full prompt: course instructions, numbered sources and the question.
     *
     * @param string $question
     * @param array[] $passages Most relevant first.
     * @return string
     */
    public function build_prompt(string $question, array $passages): string {
        $sources = '';
        foreach (array_slice(array_values($passages), 0, self::MAX_PASSAGES) as $i => $passage) {
            $sources .= '[Source ' . ($i + 1) . '] ' . $passage['content'] . "\n\n";
        }
        return $this->prompts->build_instructions($question) . "\n\n"
            . "--- Course content excerpts ---\n"
            . $sources
            . "--- End of excerpts ---\n\n"
            . "Instruction: cite your sources inline using [Source N] whenever you use information "
            . "from an excerpt. If no excerpt is relevant, say so honestly.\n\n"
            . "Question: " . $question;
    }
}
