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
 * A language model that turns a prompt into text.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface llm_provider {
    /**
     * Generate a completion for a single user prompt.
     *
     * @param string $prompt Full prompt: instructions, course excerpts and question.
     * @return string The model answer.
     * @throws \moodle_exception When the provider cannot be reached or answers badly.
     */
    public function generate(string $prompt): string;

    /**
     * Human readable name of the provider and model, for the settings test page.
     *
     * @return string
     */
    public function get_label(): string;
}
