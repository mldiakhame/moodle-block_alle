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
 * Storage and similarity search of course passages.
 *
 * A passage is an array with the keys 'content' (string), 'source' (string) and,
 * in search results, 'distance' (cosine distance to the query, lower is closer).
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface vector_store {
    /**
     * Drop and recreate a collection, removing every passage it held.
     *
     * @param string $collection
     * @throws \moodle_exception
     */
    public function reset_collection(string $collection): void;

    /**
     * Add passages with their precomputed vectors.
     *
     * @param string $collection
     * @param array[] $passages Each with 'content' and 'source'.
     * @param float[][] $vectors One vector per passage, same order.
     * @param int $courseid Course the passages belong to.
     * @throws \moodle_exception
     */
    public function add_passages(string $collection, array $passages, array $vectors, int $courseid): void;

    /**
     * Return the passages closest to a vector, closest first.
     *
     * @param string $collection
     * @param float[] $vector
     * @param int $limit
     * @return array[] Passages with 'content', 'source' and 'distance'.
     * @throws \moodle_exception
     */
    public function search(string $collection, array $vector, int $limit): array;
}
