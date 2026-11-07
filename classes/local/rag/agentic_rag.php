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

/**
 * Retrieval with a self-check: the agent judges its own search before answering.
 *
 * After each search the agent counts the passages that are close enough to the
 * question. When there are too few, it rewrites the question and searches again,
 * at most twice. It then reranks the passages and answers with cited sources.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class agentic_rag implements rag_pipeline {
    /** @var int Rewrites allowed before answering with what was found. */
    public const MAX_REWRITES = 2;

    /** @var int Close passages needed to consider a search good enough. */
    public const MIN_RELEVANT_PASSAGES = 2;

    /** @var array[] Steps of the last answer, for diagnostics and tests. */
    protected array $trace = [];

    /**
     * Constructor.
     *
     * @param retriever $retriever
     * @param reranker $reranker
     * @param query_rewriter $rewriter
     * @param answer_generator $generator
     * @param float $threshold Cosine distance under which a passage counts as relevant.
     */
    public function __construct(
        /** @var retriever Course search. */
        protected retriever $retriever,
        /** @var reranker Passage ordering. */
        protected reranker $reranker,
        /** @var query_rewriter Question rewriting. */
        protected query_rewriter $rewriter,
        /** @var answer_generator Final answer. */
        protected answer_generator $generator,
        /** @var float Relevance threshold. */
        protected float $threshold = 0.55,
    ) {
    }

    #[\Override]
    public function answer(string $question): string {
        $this->trace = [];
        $query = $question;
        $passages = [];

        for ($attempt = 0; $attempt <= self::MAX_REWRITES; $attempt++) {
            $passages = $this->retriever->retrieve($query);
            $sufficient = $this->is_sufficient($passages);
            $this->trace[] = ['step' => 'retrieve', 'query' => $query, 'passages' => count($passages), 'sufficient' => $sufficient];

            if ($sufficient || $attempt === self::MAX_REWRITES) {
                break;
            }
            $rewritten = $this->rewriter->rewrite($question, $query);
            if ($rewritten === null || $rewritten === $query) {
                break;
            }
            $this->trace[] = ['step' => 'rewrite', 'query' => $rewritten];
            $query = $rewritten;
        }

        if (!$passages) {
            throw new \moodle_exception('error:nocontent', 'block_alle');
        }

        $ranked = $this->reranker->rerank($question, $passages);
        $this->trace[] = ['step' => 'rerank', 'passages' => count($ranked)];
        $answer = $this->generator->generate($question, $ranked);
        $this->trace[] = ['step' => 'generate'];
        return $answer;
    }

    /**
     * Whether enough passages are close to the query.
     *
     * @param array[] $passages
     * @return bool
     */
    public function is_sufficient(array $passages): bool {
        $relevant = array_filter(
            $passages,
            fn(array $passage) => $passage['distance'] !== null && $passage['distance'] < $this->threshold
        );
        return count($relevant) >= self::MIN_RELEVANT_PASSAGES;
    }

    /**
     * Steps taken to produce the last answer.
     *
     * @return array[]
     */
    public function get_trace(): array {
        return $this->trace;
    }
}
