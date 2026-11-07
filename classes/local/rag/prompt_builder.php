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

use block_alle\local\conversation_store;

/**
 * Builds the instructions sent to the language model.
 *
 * The instructions come from the teacher prompt of the course, or the default
 * empathetic prompt, with three placeholders filled in: [[ coursename ]],
 * [[ question ]] and [[ history ]].
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt_builder {
    /**
     * Constructor.
     *
     * @param int $courseid Course the learner is in.
     * @param int $userid Learner asking the question.
     */
    public function __construct(
        /** @var int Course the learner is in. */
        protected int $courseid,
        /** @var int Learner asking the question. */
        protected int $userid,
    ) {
    }

    /**
     * Prompt template of a course: the teacher's one, or the default.
     *
     * @param int $courseid
     * @return string
     */
    public static function get_course_prompt(int $courseid): string {
        global $DB;
        $prompt = $DB->get_field('block_alle_prompts', 'prompt', ['courseid' => $courseid]);
        return ($prompt !== false && trim($prompt) !== '') ? $prompt : get_string('defaultprompt', 'block_alle');
    }

    /**
     * Instructions for a question, with the placeholders filled in.
     *
     * @param string $question
     * @return string
     */
    public function build_instructions(string $question): string {
        $coursename = format_string(get_course($this->courseid)->fullname, true, [
            'context' => \context_course::instance($this->courseid),
        ]);
        return str_replace(
            ['[[ coursename ]]', '[[ question ]]', '[[ history ]]'],
            [$coursename, $question, $this->format_history()],
            self::get_course_prompt($this->courseid)
        );
    }

    /**
     * Recent exchanges of the learner in this course, as text.
     *
     * @return string
     */
    protected function format_history(): string {
        $history = conversation_store::get_history($this->courseid, $this->userid);
        if (!$history) {
            return '';
        }
        $text = get_string('historyheading', 'block_alle') . "\n\n";
        foreach ($history as $index => $exchange) {
            $text .= get_string('historyquestion', 'block_alle', $index + 1) . ' ' . $exchange->question . "\n";
            $text .= get_string('historyanswer', 'block_alle') . ' ' . $exchange->answer . "\n\n";
        }
        return $text;
    }
}
