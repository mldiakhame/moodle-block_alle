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
 * External functions of ALL-E.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_alle_send_question' => [
        'classname' => 'block_alle\external\send_question',
        'description' => 'Ask the course assistant a question.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/alle:ask',
    ],
    'block_alle_save_prompt' => [
        'classname' => 'block_alle\external\save_prompt',
        'description' => 'Save the teacher prompt of a course.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/alle:managecontent',
    ],
    'block_alle_upload_files' => [
        'classname' => 'block_alle\external\upload_files',
        'description' => 'Replace the documents the assistant answers from.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/alle:managecontent',
    ],
];
