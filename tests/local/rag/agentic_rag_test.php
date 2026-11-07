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

use block_alle\tests\fake_embedder;
use block_alle\tests\fake_llm;
use block_alle\tests\fake_store;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/alle/tests/fixtures/fakes.php');

/**
 * Tests of the agentic pipeline and its tools.
 *
 * @package    block_alle
 * @category   test
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_alle\local\rag\agentic_rag
 * @covers     \block_alle\local\rag\reranker
 * @covers     \block_alle\local\rag\answer_generator
 */
final class agentic_rag_test extends \advanced_testcase {
    /** @var string Beginning of the rewriting prompt. */
    private const REWRITE = 'You are a search query reformulation assistant';

    /** @var string Beginning of the reranking prompt. */
    private const RERANK = 'You are a relevance ranking assistant';

    /**
     * Build a pipeline around fakes.
     *
     * @param fake_store $store
     * @param fake_llm $llm
     * @param float $threshold
     * @return agentic_rag
     */
    protected function pipeline(fake_store $store, fake_llm $llm, float $threshold = 0.55): agentic_rag {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Generative AI in education']);
        $user = $this->getDataGenerator()->create_user();
        return new agentic_rag(
            new retriever(new fake_embedder(), $store, 'Alle_course_' . $course->id),
            new reranker($llm),
            new query_rewriter($llm),
            new answer_generator($llm, new prompt_builder($course->id, $user->id)),
            $threshold
        );
    }

    /**
     * Language model that rewrites with a counter, ranks 2,1 and answers "Answer".
     *
     * @return fake_llm
     */
    protected function llm(): fake_llm {
        $rewrites = 0;
        return new fake_llm(function (string $prompt) use (&$rewrites) {
            if (str_starts_with($prompt, self::REWRITE)) {
                return 'rewritten ' . (++$rewrites);
            }
            return str_starts_with($prompt, self::RERANK) ? '2, 1' : 'Answer';
        });
    }

    public function test_good_retrieval_is_answered_without_rewriting(): void {
        $this->resetAfterTest();
        $store = new fake_store([fake_store::passages([0.20, 0.30, 0.70, 0.80])]);
        $llm = $this->llm();
        $rag = $this->pipeline($store, $llm);

        $this->assertSame('Answer', $rag->answer('What is a prompt?'));
        $this->assertSame(1, $store->searches);
        $this->assertCount(0, $llm->prompts_starting_with(self::REWRITE));
        $this->assertSame(['retrieve', 'rerank', 'generate'], array_column($rag->get_trace(), 'step'));
    }

    public function test_poor_retrieval_is_rewritten_at_most_twice(): void {
        $this->resetAfterTest();
        $store = new fake_store([fake_store::passages([0.60, 0.70, 0.80, 0.90])]);
        $llm = $this->llm();
        $rag = $this->pipeline($store, $llm);

        $this->assertSame('Answer', $rag->answer('c koi un prompt'));
        $this->assertSame(3, $store->searches);
        $this->assertCount(2, $llm->prompts_starting_with(self::REWRITE));
        $queries = array_column(array_filter($rag->get_trace(), fn($s) => $s['step'] === 'retrieve'), 'query');
        $this->assertSame(['c koi un prompt', 'rewritten 1', 'rewritten 2'], array_values($queries));
    }

    public function test_loop_stops_once_a_rewrite_finds_relevant_passages(): void {
        $this->resetAfterTest();
        $store = new fake_store([
            fake_store::passages([0.60, 0.70, 0.80, 0.90]),
            fake_store::passages([0.20, 0.25, 0.80, 0.90]),
        ]);
        $llm = $this->llm();
        $this->pipeline($store, $llm)->answer('c koi un prompt');

        $this->assertSame(2, $store->searches);
        $this->assertCount(1, $llm->prompts_starting_with(self::REWRITE));
    }

    public function test_loop_stops_when_the_rewrite_changes_nothing(): void {
        $this->resetAfterTest();
        $store = new fake_store([fake_store::passages([0.60, 0.70, 0.80, 0.90])]);
        $llm = new fake_llm(fn($prompt) => str_starts_with($prompt, self::REWRITE) ? 'same question' : 'Answer');

        $this->pipeline($store, $llm)->answer('same question');
        $this->assertSame(1, $store->searches);
    }

    public function test_threshold_decides_what_is_relevant(): void {
        $this->resetAfterTest();
        $passages = fake_store::passages([0.50, 0.52, 0.80]);
        $llm = $this->llm();
        $this->assertTrue($this->pipeline(new fake_store([$passages]), $llm, 0.55)->is_sufficient($passages));
        $this->assertFalse($this->pipeline(new fake_store([$passages]), $llm, 0.45)->is_sufficient($passages));
    }

    public function test_answer_cites_the_reranked_passages_first(): void {
        $this->resetAfterTest();
        $store = new fake_store([fake_store::passages([0.20, 0.30, 0.40, 0.50])]);
        $llm = $this->llm();
        $this->pipeline($store, $llm)->answer('What is a prompt?');

        $generation = end($llm->prompts);
        $this->assertStringContainsString("[Source 1] Passage 2\n\n[Source 2] Passage 1", $generation);
        $this->assertStringContainsString('Generative AI in education', $generation);
        $this->assertStringEndsWith('Question: What is a prompt?', $generation);
    }

    public function test_generation_is_limited_to_six_passages(): void {
        $this->resetAfterTest();
        $store = new fake_store([fake_store::passages([0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1])]);
        $llm = $this->llm();
        $this->pipeline($store, $llm)->answer('Question');

        $generation = end($llm->prompts);
        $this->assertStringContainsString('[Source 6]', $generation);
        $this->assertStringNotContainsString('[Source 7]', $generation);
    }

    public function test_reranking_failure_keeps_the_search_order(): void {
        $this->resetAfterTest();
        $store = new fake_store([fake_store::passages([0.20, 0.30, 0.40, 0.50])]);
        $llm = new fake_llm(function (string $prompt) {
            if (str_starts_with($prompt, self::RERANK)) {
                throw new \moodle_exception('error:badresponse', 'block_alle', '', 'test');
            }
            return 'Answer';
        });
        $this->assertSame('Answer', $this->pipeline($store, $llm)->answer('Question'));
        $this->assertStringContainsString('[Source 1] Passage 1', end($llm->prompts));
        $this->assertDebuggingCalled();
    }

    public function test_empty_course_raises_a_clear_error(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:nocontent', 'block_alle'));
        $this->pipeline(new fake_store([[]]), $this->llm())->answer('Question');
    }

    public function test_apply_order_ignores_unknown_and_repeated_numbers(): void {
        $passages = fake_store::passages([0.1, 0.2, 0.3, 0.4]);
        $ranked = reranker::apply_order('3, 3, 9, 1', $passages);
        $this->assertSame(['Passage 3', 'Passage 1', 'Passage 2', 'Passage 4'], array_column($ranked, 'content'));
    }
}
