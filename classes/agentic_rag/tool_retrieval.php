<?php
/**
 * Tool 1 — Semantic retrieval from Weaviate.
 *
 * @package    block_uteluqchatbot
 * @copyright  2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 */

namespace block_uteluqchatbot\agentic_rag;

defined('MOODLE_INTERNAL') || die();

class tool_retrieval {

    private $embedder;
    private $connector;
    private string $collection;

    public function __construct(
        \block_uteluqchatbot\providers\embedder_interface $embedder,
        \block_uteluqchatbot\weaviate_connector           $connector,
        string                                            $collection
    ) {
        $this->embedder   = $embedder;
        $this->connector  = $connector;
        $this->collection = $collection;
    }

    /**
     * Embed the question and perform a nearVector search.
     *
     * @param  string     $question
     * @return array|null Array of Weaviate result objects, or null on error.
     */
    public function execute(string $question): ?array {
        $vector = $this->embedder->embed($question);
        if ($vector === null) {
            return null;
        }

        return $this->connector->search_near_vector($this->collection, $vector, 10);
    }
}
