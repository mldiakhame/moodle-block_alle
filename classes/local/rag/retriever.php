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

use block_alle\local\providers\embedder;
use block_alle\local\vector_store;

/**
 * Finds the course passages closest to a question.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class retriever {
    /** @var int Passages returned by a search. */
    public const LIMIT = 10;

    /**
     * Constructor.
     *
     * @param embedder $embedder Same model as the one used to index the course.
     * @param vector_store $store Where the course passages are stored.
     * @param string $collection Collection of the course.
     */
    public function __construct(
        /** @var embedder Embedding model. */
        protected embedder $embedder,
        /** @var vector_store Passage store. */
        protected vector_store $store,
        /** @var string Collection of the course. */
        protected string $collection,
    ) {
    }

    /**
     * Search the course for a query.
     *
     * @param string $query
     * @return array[] Passages, closest first.
     * @throws \moodle_exception
     */
    public function retrieve(string $query): array {
        $passages = $this->store->search($this->collection, $this->embedder->embed($query), self::LIMIT);
        return array_values(array_filter($passages, fn(array $passage) => trim($passage['content']) !== ''));
    }
}
