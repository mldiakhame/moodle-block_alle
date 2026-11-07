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

namespace block_alle\local;

/**
 * Tests of the text chunker.
 *
 * @package    block_alle
 * @category   test
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_alle\local\text_chunker
 */
final class text_chunker_test extends \basic_testcase {
    /**
     * A text of $count numbered words.
     *
     * @param int $count
     * @return string
     */
    protected function words(int $count): string {
        return implode(' ', array_map(fn($i) => 'word' . $i, range(1, $count)));
    }

    public function test_empty_and_short_texts(): void {
        $chunker = new text_chunker();
        $this->assertSame([], $chunker->split(''));
        $this->assertSame(['Une seule phrase.'], $chunker->split('Une seule phrase.'));
    }

    public function test_chunks_respect_size_and_cover_the_whole_text(): void {
        $chunker = new text_chunker(1500, 300);
        $text = $this->words(3000);
        $chunks = $chunker->split($text);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(1500, \core_text::strlen($chunk));
        }
        $this->assertStringStartsWith('word1 ', $chunks[0]);
        $this->assertStringEndsWith('word3000', end($chunks));
    }

    public function test_consecutive_chunks_overlap(): void {
        $chunks = (new text_chunker(1500, 300))->split($this->words(3000));
        for ($i = 1; $i < count($chunks); $i++) {
            $previouswords = explode(' ', $chunks[$i - 1]);
            $firstword = explode(' ', $chunks[$i])[0];
            $position = array_search($firstword, $previouswords, true);
            $this->assertNotFalse($position, "Chunk $i should start inside chunk " . ($i - 1));
            $repeated = implode(' ', array_slice($previouswords, $position));
            $this->assertGreaterThanOrEqual(300, \core_text::strlen($repeated) + 1);
        }
    }

    /**
     * Regression: the end of the text used to be emitted again and again, one word shorter each time.
     */
    public function test_no_shrinking_copies_of_the_tail(): void {
        $chunks = (new text_chunker(1500, 300))->split($this->words(3000));
        $this->assertCount(count(array_unique($chunks)), $chunks);
        $this->assertSame(1, count(array_filter($chunks, fn($chunk) => str_ends_with($chunk, 'word3000'))));
        $expected = (int) ceil((\core_text::strlen($this->words(3000)) - 300) / 1200);
        $this->assertLessThanOrEqual($expected + 1, count($chunks));
    }

    public function test_clean_normalises_spacing_and_control_characters(): void {
        $chunker = new text_chunker();
        $this->assertSame("Titre\n\nTexte avec espaces", $chunker->clean("  Titre\r\n\r\n\r\n\r\nTexte\x07 avec \t  espaces  "));
    }

    public function test_invalid_overlap_is_rejected(): void {
        $this->expectException(\coding_exception::class);
        new text_chunker(100, 100);
    }
}
