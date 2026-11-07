<?php
/**
 * External service for sending questions to chatbot.
 *
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_uteluqchatbot\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_course;
use invalid_parameter_exception;
use dml_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class send_question extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'question' => new external_value(PARAM_RAW,  'The question to send'),
            'userid'   => new external_value(PARAM_INT,  'User ID'),
            'courseid' => new external_value(PARAM_INT,  'Course ID'),
            'sansrag'  => new external_value(PARAM_BOOL, 'Without RAG', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute($question, $userid, $courseid, $sansrag = false) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'question' => $question,
            'userid'   => $userid,
            'courseid' => $courseid,
            'sansrag'  => $sansrag,
        ]);

        require_login();
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        if ($USER->id != $params['userid']) {
            throw new invalid_parameter_exception('Invalid user');
        }

        try {
            $question = self::sanitize_chatbot_input(trim($params['question']));
            if (empty($question)) {
                throw new invalid_parameter_exception(
                    get_string('invalid_question_after_sanitize', 'block_uteluqchatbot')
                );
            }

            // Build the provider and connector.
            $connector = self::build_connector();

            $course_record   = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
            $course_name     = $course_record->fullname;
            $collection_name = 'Collection_course_' . $params['courseid'];

            if ($params['sansrag']) {
                $answer = $connector->get_llm_response($question);
            } else {
                $rag_mode = get_config('block_uteluqchatbot', 'rag_mode') ?: 'normal';

                if ($rag_mode === 'agentic') {
                    $provider_id = get_config('block_uteluqchatbot', 'llm_provider') ?: 'openrouter';
                    $embedder    = self::build_embedder($provider_id);
                    $llm         = self::build_llm_provider($provider_id);
                    $ag_agent    = new \block_uteluqchatbot\agentic_rag\agent(
                        $embedder, $llm, $connector, $collection_name
                    );
                    $answer = $ag_agent->answer(
                        $question, $course_name,
                        $params['userid'], $params['courseid']
                    );
                    if ($answer === null) {
                        $error = $ag_agent->get_last_error();
                        throw new \moodle_exception('empty_response_from_api', 'block_uteluqchatbot', '', $error);
                    }
                } else {
                    $answer = $connector->get_question_answer(
                        $course_name,
                        $collection_name,
                        $question,
                        $params['userid'],
                        $params['courseid']
                    );
                }
            }

            if ($answer === null || $answer === false) {
                $error = $connector->get_last_error();
                throw new \moodle_exception(
                    'empty_response_from_api',
                    'block_uteluqchatbot',
                    '',
                    $error
                );
            }

            self::save_conversation((int)$params['userid'], (int)$params['courseid'], $question, $answer);

            return [
                'status' => 'success',
                'answer' => is_string($answer) ? $answer : json_encode($answer),
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error'  => $e->getMessage(),
            ];
        }
    }

    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status of the request'),
            'answer' => new external_value(PARAM_RAW,  'The chatbot answer', VALUE_OPTIONAL),
            'error'  => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the weaviate_connector from plugin configuration.
     *
     * The embedder and LLM provider both come from the same platform:
     * - openrouter → OpenRouter embeddings + OpenRouter generation
     * - huggingface → HuggingFace embeddings + HuggingFace generation
     *
     * @return \block_uteluqchatbot\weaviate_connector
     * @throws \moodle_exception
     */
    public static function build_connector(): \block_uteluqchatbot\weaviate_connector {
        $weaviate_url = get_config('block_uteluqchatbot', 'weaviate_api_url');
        $weaviate_key = get_config('block_uteluqchatbot', 'weaviate_api_key');
        $provider_id  = get_config('block_uteluqchatbot', 'llm_provider') ?: 'openrouter';

        if (empty($weaviate_url)) {
            throw new \moodle_exception('llm_provider_not_configured', 'block_uteluqchatbot');
        }

        if (strpos($weaviate_url, 'https://') !== 0) {
            $weaviate_url = 'https://' . $weaviate_url;
        }

        $embedder = self::build_embedder($provider_id);
        $llm      = self::build_llm_provider($provider_id);

        return new \block_uteluqchatbot\weaviate_connector($weaviate_url, $weaviate_key, $embedder, $llm);
    }

    /**
     * Instantiate the embedder matching the selected provider.
     *
     * @param string $provider_id 'openrouter' | 'huggingface'
     * @return \block_uteluqchatbot\providers\embedder_interface
     * @throws \moodle_exception
     */
    public static function build_embedder(string $provider_id): \block_uteluqchatbot\providers\embedder_interface {
        if ($provider_id === 'huggingface') {
            $key   = get_config('block_uteluqchatbot', 'huggingface_api_key');
            $model = get_config('block_uteluqchatbot', 'huggingface_embedding_model') ?: 'BAAI/bge-m3';
            if (empty($key)) {
                throw new \moodle_exception('embedding_not_configured', 'block_uteluqchatbot');
            }
            return new \block_uteluqchatbot\providers\huggingface_embedder($key, $model);
        }

        // Default: OpenRouter
        $key   = get_config('block_uteluqchatbot', 'openrouter_api_key');
        $model = get_config('block_uteluqchatbot', 'openrouter_embedding_model') ?: 'openai/text-embedding-3-small';
        if (empty($key)) {
            throw new \moodle_exception('embedding_not_configured', 'block_uteluqchatbot');
        }
        return new \block_uteluqchatbot\providers\openrouter_embedder($key, $model);
    }

    /**
     * Instantiate the LLM provider for text generation.
     *
     * @param string $provider_id 'openrouter' | 'huggingface'
     * @return \block_uteluqchatbot\providers\llm_provider_interface
     * @throws \moodle_exception
     */
    public static function build_llm_provider(string $provider_id): \block_uteluqchatbot\providers\llm_provider_interface {
        if ($provider_id === 'huggingface') {
            $key   = get_config('block_uteluqchatbot', 'huggingface_api_key');
            $model = get_config('block_uteluqchatbot', 'huggingface_model') ?: 'mistralai/Mistral-7B-Instruct-v0.3';
            if (empty($key)) {
                throw new \moodle_exception('llm_provider_not_configured', 'block_uteluqchatbot');
            }
            return new \block_uteluqchatbot\providers\huggingface_provider($key, $model);
        }

        // Default: OpenRouter
        $key   = get_config('block_uteluqchatbot', 'openrouter_api_key');
        $model = get_config('block_uteluqchatbot', 'openrouter_model') ?: 'openai/gpt-4o-mini';
        if (empty($key)) {
            throw new \moodle_exception('llm_provider_not_configured', 'block_uteluqchatbot');
        }
        return new \block_uteluqchatbot\providers\openrouter_provider($key, $model);
    }

    private static function sanitize_chatbot_input(string $input): string {
        $dangerous_tags = ['script', 'iframe', 'object', 'embed', 'style', 'form', 'input', 'applet', 'link', 'meta'];
        foreach ($dangerous_tags as $tag) {
            $input = preg_replace('/<\s*\/?\s*' . $tag . '[^>]*>/i', '', $input);
        }
        $input = preg_replace('/\s*on\w+\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/i', '', $input);
        $input = preg_replace('/javascript\s*:/i', '', $input);

        $dangerous_functions = ['eval', 'system', 'exec', 'passthru', 'shell_exec', 'base64_decode', 'phpinfo', 'proc_open', 'popen'];
        foreach ($dangerous_functions as $func) {
            $input = preg_replace('/\b' . preg_quote($func, '/') . '\s*\(/i', '', $input);
        }

        $input = mb_substr($input, 0, 100000, 'UTF-8');
        return $input;
    }

    public static function save_conversation(int $userid, int $courseid, string $question, string $answer): void {
        global $DB;

        $max_conversations = 10;

        $record              = new \stdClass();
        $record->userid      = $userid;
        $record->courseid    = $courseid;
        $record->question    = $question;
        $record->answer      = is_string($answer) ? $answer : json_encode($answer);
        $record->timecreated = time();

        try {
            $count = $DB->count_records(
                'block_uteluqchatbot_conversations',
                ['userid' => $userid, 'courseid' => $courseid]
            );

            if ($count >= $max_conversations) {
                $oldest_ids = $DB->get_fieldset_sql(
                    "SELECT id FROM {block_uteluqchatbot_conversations}
                      WHERE userid = :userid AND courseid = :courseid
                   ORDER BY timecreated ASC",
                    ['userid' => $userid, 'courseid' => $courseid],
                    0,
                    $count - $max_conversations + 1
                );
                foreach ($oldest_ids as $old_id) {
                    $DB->delete_records('block_uteluqchatbot_conversations', ['id' => $old_id]);
                }
            }

            $DB->insert_record('block_uteluqchatbot_conversations', $record);

        } catch (dml_exception $e) {
            throw new \moodle_exception('error_saving_conversation', 'block_uteluqchatbot', '', $e->getMessage());
        }
    }
}
