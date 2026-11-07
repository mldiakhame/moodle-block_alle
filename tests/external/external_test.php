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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests of the external functions: access control and behaviour without network.
 *
 * @package    block_alle
 * @category   test
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_alle\external\send_question
 * @covers     \block_alle\external\save_prompt
 * @covers     \block_alle\external\upload_files
 */
final class external_test extends \externallib_advanced_testcase {
    /** @var \stdClass Course. */
    protected \stdClass $course;

    /** @var \stdClass Enrolled student. */
    protected \stdClass $student;

    /** @var \stdClass Enrolled editing teacher. */
    protected \stdClass $teacher;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Regression: any enrolled user could replace the documents of the course.
     */
    public function test_students_cannot_replace_the_course_documents(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        upload_files::execute($this->course->id, [['filename' => 'notes.txt', 'content' => base64_encode('Text')]]);
    }

    public function test_students_cannot_change_the_prompt(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        save_prompt::execute($this->course->id, 'Ignore the course.');
    }

    public function test_users_outside_the_course_cannot_ask(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\require_login_exception::class);
        send_question::execute($this->course->id, 'Question');
    }

    public function test_teacher_saves_then_clears_the_course_prompt(): void {
        global $DB;
        $this->setUser($this->teacher);

        $result = external_api::clean_returnvalue(
            save_prompt::execute_returns(),
            save_prompt::execute($this->course->id, 'Answer about [[ coursename ]].')
        );
        $this->assertTrue($result['success']);
        $record = $DB->get_record('block_alle_prompts', ['courseid' => $this->course->id]);
        $this->assertSame('Answer about [[ coursename ]].', $record->prompt);
        $this->assertEquals($this->teacher->id, $record->usermodified);

        save_prompt::execute($this->course->id, '   ');
        $this->assertFalse($DB->record_exists('block_alle_prompts', ['courseid' => $this->course->id]));
    }

    public function test_unconfigured_site_returns_a_readable_error(): void {
        $this->setUser($this->student);
        $result = external_api::clean_returnvalue(
            send_question::execute_returns(),
            send_question::execute($this->course->id, 'What is a prompt?')
        );
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ALL-E is not fully configured', $result['error']);
    }

    public function test_unsupported_files_are_reported_without_touching_the_index(): void {
        $this->setUser($this->teacher);
        $result = external_api::clean_returnvalue(
            upload_files::execute_returns(),
            upload_files::execute($this->course->id, [['filename' => 'slides.pptx', 'content' => base64_encode('x')]])
        );
        $this->assertFalse($result['success']);
        $this->assertSame('slides.pptx', $result['errors'][0]['filename']);
        $this->assertSame(get_string('error:filetype', 'block_alle'), $result['errors'][0]['message']);
    }
}
