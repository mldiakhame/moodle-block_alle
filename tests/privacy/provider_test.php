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

namespace block_alle\privacy;

use block_alle\local\conversation_store;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests of the privacy provider.
 *
 * @package    block_alle
 * @category   test
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_alle\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass Course. */
    protected \stdClass $course;

    /** @var \context_course Course context. */
    protected \context_course $context;

    /** @var \stdClass Learner with two exchanges. */
    protected \stdClass $learner;

    /** @var \stdClass Teacher who wrote the prompt. */
    protected \stdClass $teacher;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->context = \context_course::instance($this->course->id);
        $this->learner = $this->getDataGenerator()->create_user();
        $this->teacher = $this->getDataGenerator()->create_user();
        conversation_store::add($this->course->id, $this->learner->id, 'Q1', 'A1');
        conversation_store::add($this->course->id, $this->learner->id, 'Q2', 'A2');
        $DB->insert_record('block_alle_prompts', (object) [
            'courseid' => $this->course->id,
            'prompt' => 'Course prompt',
            'usermodified' => $this->teacher->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_contexts_and_users(): void {
        $this->assertSame([$this->context->id], provider::get_contexts_for_userid($this->learner->id)->get_contextids());
        $this->assertSame([$this->context->id], provider::get_contexts_for_userid($this->teacher->id)->get_contextids());

        $userlist = new userlist($this->context, 'block_alle');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->learner->id, $this->teacher->id], $userlist->get_userids());
    }

    public function test_export(): void {
        $this->export_context_data_for_user($this->learner->id, $this->context, 'block_alle');
        $writer = writer::with_context($this->context);
        $data = $writer->get_data([get_string('pluginname', 'block_alle'), get_string('privacy:conversations', 'block_alle')]);
        $this->assertCount(2, $data->conversations);
        $this->assertSame('Q1', $data->conversations[0]->question);
    }

    public function test_deleting_a_user_keeps_the_course_prompt(): void {
        global $DB;
        provider::delete_data_for_user(new approved_contextlist($this->learner, 'block_alle', [$this->context->id]));
        provider::delete_data_for_user(new approved_contextlist($this->teacher, 'block_alle', [$this->context->id]));

        $this->assertFalse($DB->record_exists('block_alle_conversations', ['userid' => $this->learner->id]));
        $prompt = $DB->get_record('block_alle_prompts', ['courseid' => $this->course->id]);
        $this->assertSame('Course prompt', $prompt->prompt);
        $this->assertEquals(0, $prompt->usermodified);
    }

    public function test_delete_users_and_whole_context(): void {
        global $DB;
        provider::delete_data_for_users(new approved_userlist($this->context, 'block_alle', [$this->learner->id]));
        $this->assertSame(0, $DB->count_records('block_alle_conversations'));

        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(0, $DB->count_records('block_alle_prompts'));
    }
}
