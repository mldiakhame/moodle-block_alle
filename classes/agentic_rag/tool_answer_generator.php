<?php
/**
 * Tool 4 — Answer generator with source citations.
 *
 * Synthesises the final answer from the re-ranked chunks, instructing the
 * LLM to cite each source as [Source N] so learners can trace information.
 *
 * @package    block_uteluqchatbot
 * @copyright  2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 */

namespace block_uteluqchatbot\agentic_rag;

defined('MOODLE_INTERNAL') || die();

class tool_answer_generator {

    private $llm;

    /** Maximum number of chunks included in the generation context. */
    private int $max_chunks = 6;

    public function __construct(\block_uteluqchatbot\providers\llm_provider_interface $llm) {
        $this->llm = $llm;
    }

    /**
     * Generate the final answer with inline source citations.
     *
     * @param  string  $question
     * @param  array   $chunks      Re-ranked Weaviate result objects.
     * @param  string  $course_name
     * @param  int     $user_id
     * @param  int     $course_id
     * @return string|null
     */
    public function execute(
        string $question,
        array  $chunks,
        string $course_name,
        int    $user_id,
        int    $course_id
    ): ?string {
        global $DB;

        // Keep only the top N chunks for context length management.
        $chunks = array_slice($chunks, 0, $this->max_chunks);

        // Build numbered source context.
        $context = '';
        foreach ($chunks as $i => $chunk) {
            if (!empty(trim($chunk['texte'] ?? ''))) {
                $context .= '[Source ' . ($i + 1) . '] ' . $chunk['texte'] . "\n\n";
            }
        }

        // Load conversation history (chronological order).
        $rows = $DB->get_records_sql(
            "SELECT question, answer
               FROM {block_uteluqchatbot_conversations}
              WHERE userid = :userid
           ORDER BY timecreated DESC
              LIMIT 10",
            ['userid' => $user_id]
        );
        $history = '';
        if (!empty($rows)) {
            $rows = array_reverse(array_values($rows));
            $history = get_string('previous_interactions_history', 'block_uteluqchatbot') . "\n\n";
            foreach ($rows as $k => $row) {
                $history .= get_string('previous_question', 'block_uteluqchatbot', $k + 1) . $row->question . "\n";
                $history .= get_string('answer', 'block_uteluqchatbot') . $row->answer . "\n\n";
            }
        }

        // Load teacher prompt.
        $record = $DB->get_record('block_uteluqchatbot_prompts', ['userid' => $user_id, 'courseid' => $course_id]);
        $task   = $record ? $record->prompt : get_string('default_prompt', 'block_uteluqchatbot');
        $task   = str_replace('[[ coursename ]]', $course_name, $task);
        $task   = str_replace('[[ question ]]',   $question,    $task);
        $task   = str_replace('[[ history ]]',    $history,     $task);

        $full_prompt = $task . "\n\n"
            . "--- Course content excerpts ---\n"
            . $context
            . "--- End of excerpts ---\n\n"
            . "Instruction: cite your sources inline using [Source N] whenever you use information "
            . "from an excerpt. If no excerpt is relevant, say so honestly.\n\n"
            . "Question: " . $question;

        return $this->llm->generate($full_prompt);
    }
}
