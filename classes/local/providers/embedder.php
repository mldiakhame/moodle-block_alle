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

namespace block_alle\local\providers;

/**
 * A model that turns text into dense vectors.
 *
 * Documents and questions must be embedded by the same model, otherwise their
 * vectors are not comparable.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface embedder {
    /**
     * Embed one text.
     *
     * @param string $text
     * @return float[]
     * @throws \moodle_exception
     */
    public function embed(string $text): array;

    /**
     * Embed several texts, keeping their order.
     *
     * @param string[] $texts
     * @return float[][]
     * @throws \moodle_exception
     */
    public function embed_batch(array $texts): array;

    /**
     * Human readable name of the provider and model, for the settings test page.
     *
     * @return string
     */
    public function get_label(): string;
}
