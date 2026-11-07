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

use block_alle\local\providers\embedder;
use block_alle\local\providers\huggingface_embedder;
use block_alle\local\providers\huggingface_provider;
use block_alle\local\providers\llm_provider;
use block_alle\local\providers\openrouter_embedder;
use block_alle\local\providers\openrouter_provider;
use block_alle\local\rag\agentic_rag;
use block_alle\local\rag\answer_generator;
use block_alle\local\rag\prompt_builder;
use block_alle\local\rag\query_rewriter;
use block_alle\local\rag\rag_pipeline;
use block_alle\local\rag\reranker;
use block_alle\local\rag\retriever;
use block_alle\local\rag\standard_rag;

/**
 * Builds the configured providers and pipelines from the plugin settings.
 *
 * Generation and embeddings always come from the same provider, so that the
 * documents of a course and the questions about it share one vector space.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_factory {
    /** @var string Default OpenRouter generation model. */
    public const DEFAULT_OPENROUTER_MODEL = 'openai/gpt-4o-mini';

    /** @var string Default OpenRouter embedding model. */
    public const DEFAULT_OPENROUTER_EMBEDDING_MODEL = 'baai/bge-m3';

    /** @var string Default Hugging Face generation model. */
    public const DEFAULT_HUGGINGFACE_MODEL = 'mistralai/Mistral-7B-Instruct-v0.3';

    /** @var string Default Hugging Face embedding model. */
    public const DEFAULT_HUGGINGFACE_EMBEDDING_MODEL = 'BAAI/bge-m3';

    /** @var float Default cosine distance under which a retrieved passage counts as relevant. */
    public const DEFAULT_RELEVANCE_THRESHOLD = 0.55;

    /** @var \stdClass Plugin settings. */
    protected \stdClass $config;

    /**
     * Constructor.
     *
     * @param \stdClass|null $config Settings, read from the database when omitted.
     */
    public function __construct(?\stdClass $config = null) {
        $this->config = $config ?? get_config('block_alle');
    }

    /**
     * Selected provider: 'openrouter' or 'huggingface'.
     *
     * @return string
     */
    public function get_provider_name(): string {
        return ($this->config->provider ?? '') === 'huggingface' ? 'huggingface' : 'openrouter';
    }

    /**
     * Language model used for generation, rewriting and reranking.
     *
     * @return llm_provider
     * @throws \moodle_exception When the provider is not configured.
     */
    public function get_llm(): llm_provider {
        if ($this->get_provider_name() === 'huggingface') {
            return new huggingface_provider(
                $this->require_setting('huggingface_api_key'),
                $this->setting('huggingface_model', self::DEFAULT_HUGGINGFACE_MODEL),
                $this->setting('huggingface_endpoint', huggingface_provider::DEFAULT_ENDPOINT),
            );
        }
        return new openrouter_provider(
            $this->require_setting('openrouter_api_key'),
            $this->setting('openrouter_model', self::DEFAULT_OPENROUTER_MODEL),
        );
    }

    /**
     * Embedding model for documents and questions.
     *
     * @return embedder
     * @throws \moodle_exception When the provider is not configured.
     */
    public function get_embedder(): embedder {
        if ($this->get_provider_name() === 'huggingface') {
            return new huggingface_embedder(
                $this->require_setting('huggingface_api_key'),
                $this->setting('huggingface_embedding_model', self::DEFAULT_HUGGINGFACE_EMBEDDING_MODEL),
                $this->setting('huggingface_embedding_endpoint', huggingface_embedder::DEFAULT_ENDPOINT),
            );
        }
        return new openrouter_embedder(
            $this->require_setting('openrouter_api_key'),
            $this->setting('openrouter_embedding_model', self::DEFAULT_OPENROUTER_EMBEDDING_MODEL),
        );
    }

    /**
     * Vector database client.
     *
     * @return weaviate_connector
     * @throws \moodle_exception When Weaviate is not configured.
     */
    public function get_vector_store(): weaviate_connector {
        return new weaviate_connector($this->require_setting('weaviate_url'), $this->setting('weaviate_api_key', ''));
    }

    /**
     * Name of the Weaviate collection holding the documents of a course.
     *
     * The site prefix keeps the collections of several Moodle sites apart when
     * they share one Weaviate cluster.
     *
     * @param int $courseid
     * @return string
     */
    public function get_collection_name(int $courseid): string {
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', $this->setting('collection_prefix', 'Alle'));
        return ucfirst($prefix !== '' ? $prefix : 'Alle') . '_course_' . $courseid;
    }

    /**
     * Whether the agentic pipeline is enabled.
     *
     * @return bool
     */
    public function is_agentic(): bool {
        return ($this->config->rag_mode ?? 'agentic') === 'agentic';
    }

    /**
     * Distance threshold of the agentic retrieval check.
     *
     * @return float
     */
    public function get_relevance_threshold(): float {
        $value = $this->config->relevance_threshold ?? '';
        return is_numeric($value) ? (float) $value : self::DEFAULT_RELEVANCE_THRESHOLD;
    }

    /**
     * Build the pipeline that answers learners' questions in a course.
     *
     * @param int $courseid
     * @param int $userid Learner asking the questions.
     * @return rag_pipeline
     */
    public function get_pipeline(int $courseid, int $userid): rag_pipeline {
        $llm = $this->get_llm();
        $retriever = new retriever($this->get_embedder(), $this->get_vector_store(), $this->get_collection_name($courseid));
        $prompts = new prompt_builder($courseid, $userid);

        if (!$this->is_agentic()) {
            return new standard_rag($retriever, $llm, $prompts);
        }
        return new agentic_rag(
            $retriever,
            new reranker($llm),
            new query_rewriter($llm),
            new answer_generator($llm, $prompts),
            $this->get_relevance_threshold(),
        );
    }

    /**
     * Read an optional setting.
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    protected function setting(string $name, string $default): string {
        $value = trim((string) ($this->config->$name ?? ''));
        return $value !== '' ? $value : $default;
    }

    /**
     * Read a setting that must be filled in.
     *
     * @param string $name
     * @return string
     * @throws \moodle_exception
     */
    protected function require_setting(string $name): string {
        $value = trim((string) ($this->config->$name ?? ''));
        if ($value === '') {
            throw new \moodle_exception('error:notconfigured', 'block_alle', '', get_string($name, 'block_alle'));
        }
        return $value;
    }
}
