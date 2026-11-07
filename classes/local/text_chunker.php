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
 * Splits course text into overlapping passages before they are embedded.
 *
 * Passages are cut between words. Each one repeats the end of the previous
 * passage, so that an explanation falling on a boundary is still complete in
 * at least one of them.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_chunker {
    /**
     * Constructor.
     *
     * @param int $size Maximum passage length, in characters.
     * @param int $overlap Characters repeated from the previous passage.
     */
    public function __construct(
        /** @var int Maximum passage length, in characters. */
        protected int $size = 1500,
        /** @var int Characters repeated from the previous passage. */
        protected int $overlap = 300,
    ) {
        if ($size <= 0 || $overlap < 0 || $overlap >= $size) {
            throw new \coding_exception('The overlap must be positive and smaller than the passage size.');
        }
    }

    /**
     * Normalise extracted text: valid UTF-8, no control characters, compact spacing.
     *
     * @param string $text
     * @return string
     */
    public function clean(string $text): string {
        $text = \core_text::convert($text, 'utf-8', 'utf-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    /**
     * Split a text into passages.
     *
     * @param string $text Cleaned text.
     * @return string[]
     */
    public function split(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $words = preg_split('/ +/', $text);
        $count = count($words);
        $chunks = [];
        $start = 0;

        while ($start < $count) {
            $chunk = [];
            $length = 0;
            $end = $start;
            while ($end < $count) {
                $wordlength = \core_text::strlen($words[$end]) + 1;
                if ($length + $wordlength > $this->size && !empty($chunk)) {
                    break;
                }
                $chunk[] = $words[$end];
                $length += $wordlength;
                $end++;
            }
            $chunks[] = implode(' ', $chunk);

            if ($end >= $count) {
                break;
            }

            // Step back over the last words until about $overlap characters are repeated.
            $repeated = 0;
            $repeatedlength = 0;
            for ($i = count($chunk) - 1; $i > 0 && $repeatedlength < $this->overlap; $i--) {
                $repeatedlength += \core_text::strlen($chunk[$i]) + 1;
                $repeated++;
            }
            $start += max(1, count($chunk) - $repeated);
        }
        return $chunks;
    }
}
