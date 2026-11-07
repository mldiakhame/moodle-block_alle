<?php
/**
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_uteluqchatbot\providers;

defined('MOODLE_INTERNAL') || die();

interface embedder_interface {

    /**
     * Embed a single text string.
     *
     * @param string $text
     * @return float[]|null Dense vector, or null on failure
     */
    public function embed(string $text): ?array;

    /**
     * Embed multiple texts (implementation may batch internally).
     *
     * @param string[] $texts
     * @return float[][]|null Array of vectors, or null on failure
     */
    public function embed_batch(array $texts): ?array;

    /**
     * Quick connectivity test.
     *
     * @return array{success: bool, message: string}
     */
    public function test_connection(): array;

    /**
     * @return string|null
     */
    public function get_last_error(): ?string;
}
