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

namespace block_alle\external;

use block_alle\local\conversation_store;
use block_alle\local\provider_factory;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Answers a learner question from the documents of the course.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_question extends external_api {
    /** @var int Longest question accepted, in characters. */
    public const MAX_QUESTION_LENGTH = 4000;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'question' => new external_value(PARAM_RAW, 'Learner question'),
        ]);
    }

    /**
     * Ask the assistant.
     *
     * Failures of the AI services are returned as a readable error rather than an
     * exception, so that the chat can show them without a JavaScript error dialog.
     *
     * @param int $courseid
     * @param string $question
     * @return array
     */
    public static function execute(int $courseid, string $question): array {
        global $USER;

        ['courseid' => $courseid, 'question' => $question] = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'question' => $question]
        );
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('block/alle:ask', $context);

        $question = \core_text::substr(trim(clean_param($question, PARAM_NOTAGS)), 0, self::MAX_QUESTION_LENGTH);
        if ($question === '') {
            throw new \invalid_parameter_exception('Empty question');
        }

        try {
            $answer = (new provider_factory())->get_pipeline($courseid, $USER->id)->answer($question);
        } catch (\moodle_exception $e) {
            return ['success' => false, 'answer' => '', 'error' => $e->getMessage()];
        }

        conversation_store::add($courseid, $USER->id, $question, $answer);
        return ['success' => true, 'answer' => $answer, 'error' => ''];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether an answer was produced'),
            'answer' => new external_value(PARAM_RAW, 'Answer, in Markdown'),
            'error' => new external_value(PARAM_TEXT, 'Error message when no answer was produced'),
        ]);
    }
}
