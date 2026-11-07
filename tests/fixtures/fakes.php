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
 * Offline stand-ins for the AI services, used by the unit tests.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_alle\tests;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

use block_alle\local\providers\embedder;
use block_alle\local\providers\llm_provider;
use block_alle\local\vector_store;

defined('MOODLE_INTERNAL') || die();

/**
 * Language model that answers from a script and records every prompt.
 */
class fake_llm implements llm_provider {
    /** @var string[] Prompts received, in order. */
    public array $prompts = [];

    /**
     * Constructor.
     *
     * @param callable $responder Receives the prompt, returns the answer or throws.
     */
    public function __construct(
        /** @var callable Receives the prompt, returns the answer. */
        protected $responder,
    ) {
    }

    #[\Override]
    public function generate(string $prompt): string {
        $this->prompts[] = $prompt;
        return ($this->responder)($prompt);
    }

    #[\Override]
    public function get_label(): string {
        return 'fake';
    }

    /**
     * Prompts sent by one agent tool, recognised by their first words.
     *
     * @param string $start Beginning of the prompt.
     * @return string[]
     */
    public function prompts_starting_with(string $start): array {
        return array_values(array_filter($this->prompts, fn($prompt) => str_starts_with($prompt, $start)));
    }
}

/**
 * Embedder returning a vector derived from the text, so that queries are distinguishable.
 */
class fake_embedder implements embedder {
    #[\Override]
    public function embed(string $text): array {
        return [(float) crc32($text)];
    }

    #[\Override]
    public function embed_batch(array $texts): array {
        return array_map([$this, 'embed'], $texts);
    }

    #[\Override]
    public function get_label(): string {
        return 'fake';
    }
}

/**
 * Vector store returning prepared results, one list per search in turn.
 */
class fake_store implements vector_store {
    /** @var int Number of searches made. */
    public int $searches = 0;

    /**
     * Constructor.
     *
     * @param array[] $results One list of passages per search; the last one repeats.
     */
    public function __construct(
        /** @var array[] Prepared results. */
        protected array $results,
    ) {
    }

    #[\Override]
    public function reset_collection(string $collection): void {
    }

    #[\Override]
    public function add_passages(string $collection, array $passages, array $vectors, int $courseid): void {
    }

    #[\Override]
    public function search(string $collection, array $vector, int $limit): array {
        $result = $this->results[min($this->searches, count($this->results) - 1)];
        $this->searches++;
        return array_slice($result, 0, $limit);
    }

    /**
     * Build passages from distances.
     *
     * @param float[] $distances
     * @return array[]
     */
    public static function passages(array $distances): array {
        $passages = [];
        foreach ($distances as $i => $distance) {
            $passages[] = ['content' => 'Passage ' . ($i + 1), 'source' => 'module.pdf', 'distance' => $distance];
        }
        return $passages;
    }
}
