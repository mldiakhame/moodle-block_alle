<?php
/**
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_uteluqchatbot\providers;

defined('MOODLE_INTERNAL') || die();

interface llm_provider_interface {

    /**
     * Generate a response from the LLM given a full prompt string.
     *
     * @param string $prompt The complete prompt (system instructions + context + question)
     * @return string|null Generated text, or null on failure
     */
    public function generate(string $prompt): ?string;

    /**
     * Stream a response token-by-token via a callback.
     *
     * The callback receives each text token as it arrives.
     * Returns true on success, false on error (check get_last_error()).
     *
     * @param string   $prompt
     * @param callable $on_token  function(string $token): void
     * @return bool
     */
    public function stream_generate(string $prompt, callable $on_token): bool;

    /**
     * Return the last error message, if any.
     *
     * @return string|null
     */
    public function get_last_error(): ?string;
}
