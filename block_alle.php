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

/**
 * ALL-E block.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_alle\local\rag\prompt_builder;

/**
 * ALL-E, the Assistant for Lifelong Learning, intelligent and Empathic.
 *
 * Learners chat with an assistant that answers from the documents of their
 * course; teachers choose those documents and the prompt that frames answers.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_alle extends block_base {
    /**
     * Set the block title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_alle');
    }

    #[\Override]
    public function applicable_formats() {
        return ['course-view' => true, 'site' => false, 'mod' => false, 'my' => false];
    }

    #[\Override]
    public function instance_allow_multiple() {
        return false;
    }

    #[\Override]
    public function has_config() {
        return true;
    }

    #[\Override]
    public function get_content() {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];

        $course = $this->page->course;
        $context = context_course::instance($course->id);
        if (!has_capability('block/alle:ask', $context)) {
            return $this->content;
        }

        $canmanage = has_capability('block/alle:managecontent', $context);
        $uniqid = html_writer::random_id('block_alle_');
        $this->content->text = $OUTPUT->render_from_template('block_alle/chat', [
            'uniqid' => $uniqid,
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'canmanage' => $canmanage,
            'prompt' => $canmanage ? prompt_builder::get_course_prompt($course->id) : '',
            'maxfilesize' => display_size(\block_alle\external\upload_files::MAX_FILE_SIZE),
        ]);
        $this->page->requires->js_call_amd('block_alle/chat', 'init', [$uniqid, (int) $course->id]);

        return $this->content;
    }
}
