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
use block_alle\tests\fake_embedder;
use block_alle\tests\fake_llm;
use block_alle\tests\fake_store;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/alle/tests/fixtures/fakes.php');

/**
 * Tests of the prompt, the conversation memory and the standard pipeline.
 *
 * @package    block_alle
 * @category   test
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_alle\local\rag\prompt_builder
 * @covers     \block_alle\local\rag\standard_rag
 * @covers     \block_alle\local\conversation_store
 */
final class prompt_builder_test extends \advanced_testcase {
    /**
     * Regression: the teacher prompt was stored for the teacher and never reached learners.
     */
    public function test_teacher_prompt_applies_to_every_learner_of_the_course(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Statistics']);
        $other = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $DB->insert_record('block_alle_prompts', (object) [
            'courseid' => $course->id,
            'prompt' => 'Teach [[ coursename ]] and answer: [[ question ]]',
            'usermodified' => $teacher->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $prompt = (new prompt_builder($course->id, $learner->id))->build_instructions('What is a median?');
        $this->assertSame('Teach Statistics and answer: What is a median?', $prompt);

        $default = (new prompt_builder($other->id, $learner->id))->build_instructions('Q');
        $this->assertStringContainsString('Situation Context', $default);
    }

    /**
     * Regression: the history of one course used to leak into the prompts of another.
     */
    public function test_history_is_limited_to_the_learner_and_the_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $classmate = $this->getDataGenerator()->create_user();

        conversation_store::add($course->id, $learner->id, 'Question in this course', 'A');
        conversation_store::add($other->id, $learner->id, 'Question in another course', 'B');
        conversation_store::add($course->id, $classmate->id, 'Question of a classmate', 'C');

        $prompt = (new prompt_builder($course->id, $learner->id))->build_instructions('New question');
        $this->assertStringContainsString('Question in this course', $prompt);
        $this->assertStringNotContainsString('Question in another course', $prompt);
        $this->assertStringNotContainsString('Question of a classmate', $prompt);
    }

    public function test_only_the_last_exchanges_are_kept_oldest_first(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        for ($i = 1; $i <= conversation_store::MAX_EXCHANGES + 3; $i++) {
            conversation_store::add($course->id, $learner->id, "Q$i", "A$i");
        }
        $history = conversation_store::get_history($course->id, $learner->id);
        $this->assertCount(conversation_store::MAX_EXCHANGES, $history);
        $this->assertSame('Q4', reset($history)->question);
        $this->assertSame('Q13', end($history)->question);
    }

    public function test_standard_rag_sends_every_retrieved_passage(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $learner = $this->getDataGenerator()->create_user();
        $llm = new fake_llm(fn() => 'Answer');
        $rag = new standard_rag(
            new retriever(new fake_embedder(), new fake_store([fake_store::passages([0.2, 0.9, 0.95])]), 'Alle_course_1'),
            $llm,
            new prompt_builder($course->id, $learner->id)
        );

        $this->assertSame('Answer', $rag->answer('Question'));
        $this->assertCount(1, $llm->prompts);
        foreach (['Passage 1', 'Passage 2', 'Passage 3', '--- Course content excerpts ---'] as $expected) {
            $this->assertStringContainsString($expected, $llm->prompts[0]);
        }
        $this->assertStringNotContainsString('[Source', $llm->prompts[0]);
    }
}
