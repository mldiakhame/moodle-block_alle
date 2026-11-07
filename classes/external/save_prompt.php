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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Saves the prompt that frames the assistant's answers in a course.
 *
 * An empty prompt removes the teacher prompt, so the default one applies again.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_prompt extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'prompt' => new external_value(PARAM_RAW, 'Prompt text'),
        ]);
    }

    /**
     * Save the prompt.
     *
     * @param int $courseid
     * @param string $prompt
     * @return array
     */
    public static function execute(int $courseid, string $prompt): array {
        global $DB, $USER;

        ['courseid' => $courseid, 'prompt' => $prompt] = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'prompt' => $prompt]
        );
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('block/alle:managecontent', $context);

        $prompt = trim(clean_param($prompt, PARAM_NOTAGS));
        $existing = $DB->get_record('block_alle_prompts', ['courseid' => $courseid]);
        $now = time();

        if ($prompt === '') {
            if ($existing) {
                $DB->delete_records('block_alle_prompts', ['id' => $existing->id]);
            }
        } else if ($existing) {
            $existing->prompt = $prompt;
            $existing->usermodified = $USER->id;
            $existing->timemodified = $now;
            $DB->update_record('block_alle_prompts', $existing);
        } else {
            $DB->insert_record('block_alle_prompts', (object) [
                'courseid' => $courseid,
                'prompt' => $prompt,
                'usermodified' => $USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        return ['success' => true];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the prompt was saved'),
        ]);
    }
}
