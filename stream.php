<?php
/**
 * Streaming endpoint for UGB Chatbot.
 *
 * Outputs Server-Sent Events (SSE) — one token at a time — using the
 * configured LLM provider (OpenRouter or HuggingFace).
 *
 * Security: follows Moodle standards (require_login, confirm_sesskey,
 * required_param, capability check, $DB API).
 *
 * @package    block_uteluqchatbot
 * @copyright  2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Prevent any PHP notice/warning from corrupting the SSE stream.
@ini_set('display_errors', 0);
error_reporting(0);

// This is a web-entry point — no MOODLE_INTERNAL guard needed here.
require_once('../../config.php');

// ── Authentication & CSRF ──────────────────────────────────────────────────

require_login();
require_sesskey();   // validates the posted 'sesskey' param

// ── Input validation (Moodle API — no raw $_POST) ─────────────────────────

$question = required_param('question', PARAM_RAW);
$courseid = required_param('courseid', PARAM_INT);
$userid   = required_param('userid',   PARAM_INT);
$sansrag  = optional_param('sansrag',  0, PARAM_BOOL);

// ── Authorisation ─────────────────────────────────────────────────────────

if ((int)$USER->id !== (int)$userid) {
    http_response_code(403);
    die('Forbidden');
}

$course_context = context_course::instance($courseid);
if (!is_enrolled($course_context, $USER->id) &&
    !has_capability('moodle/course:view', $course_context)) {
    http_response_code(403);
    die('Forbidden');
}

// ── Sanitize question ─────────────────────────────────────────────────────
// Strip dangerous HTML tags and JS — but do NOT htmlspecialchars() here
// because the question goes to the LLM API, not to the browser.

$question = trim($question);

$dangerous_tags = ['script','iframe','object','embed','style','form','input','applet','link','meta'];
foreach ($dangerous_tags as $tag) {
    $question = preg_replace('/<\s*\/?\s*' . $tag . '[^>]*>/i', '', $question);
}
$question = preg_replace('/\s*on\w+\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/i', '', $question);
$question = preg_replace('/javascript\s*:/i', '', $question);
$question = mb_substr($question, 0, 100000, 'UTF-8');

if (empty($question)) {
    http_response_code(400);
    die('Empty question');
}

// ── SSE Setup ─────────────────────────────────────────────────────────────

// Disable gzip/deflate — they buffer the entire response until connection closes.
@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 0);
@ini_set('implicit_flush', true);

// Release PHP session lock so the browser can make other Moodle requests
// while the stream is open (DB calls below still work without the session).
session_write_close();

// Kill every output buffer Moodle may have opened during bootstrap.
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');   // disable nginx proxy buffering
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Send an initial padding comment so reverse-proxies flush their buffers.
echo ':' . str_repeat(' ', 2048) . "\n\n";
flush();

/**
 * Send one SSE data frame and flush immediately.
 */
function sse(array $payload): void {
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

/**
 * Send an error event then terminate the stream.
 */
function sse_fatal(string $message): void {
    sse(['error' => $message]);
    echo "data: [DONE]\n\n";
    flush();
    exit;
}

// ── Build provider ────────────────────────────────────────────────────────

$provider_id = get_config('block_uteluqchatbot', 'llm_provider') ?: 'openrouter';

try {
    $embedder = \block_uteluqchatbot\external\send_question::build_embedder($provider_id);
    $llm      = \block_uteluqchatbot\external\send_question::build_llm_provider($provider_id);
} catch (\moodle_exception $e) {
    sse_fatal($e->getMessage());
}

// ── Build prompt ──────────────────────────────────────────────────────────

$full_prompt = '';

if (!$sansrag) {

    // 1. Embed the question.
    $vector = $embedder->embed($question);
    if ($vector === null) {
        sse_fatal(get_string('embedding_failed', 'block_uteluqchatbot'));
    }

    // 2. Vector search in Weaviate.
    $weaviate_url = get_config('block_uteluqchatbot', 'weaviate_api_url');
    $weaviate_key = get_config('block_uteluqchatbot', 'weaviate_api_key');

    if (strpos($weaviate_url, 'https://') !== 0) {
        $weaviate_url = 'https://' . $weaviate_url;
    }

    $collection  = 'Collection_course_' . $courseid;
    $vector_json = json_encode($vector);
    $graphql     = [
        'query' => "{
            Get {
                $collection (
                    limit: 10
                    nearVector: { vector: $vector_json }
                ) {
                    texte
                    cours
                }
            }
        }"
    ];

    $ch = curl_init($weaviate_url . '/v1/graphql');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($graphql),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $weaviate_key,
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $weaviate_response = curl_exec($ch);
    curl_close($ch);

    $weaviate_data = json_decode($weaviate_response, true);
    $chunks        = $weaviate_data['data']['Get'][$collection] ?? [];

    $context_text = '';
    foreach ($chunks as $chunk) {
        if (!empty($chunk['texte'])) {
            $context_text .= $chunk['texte'] . "\n\n";
        }
    }

    // 3. Conversation history.
    $last_conversations = $DB->get_records_sql(
        "SELECT question, answer
           FROM {block_uteluqchatbot_conversations}
          WHERE userid = :userid
       ORDER BY timecreated DESC
          LIMIT 10",
        ['userid' => $userid]
    );

    $historique = '';
    if (!empty($last_conversations)) {
        $historique = get_string('previous_interactions_history', 'block_uteluqchatbot') . "\n\n";
        $i = 1;
        foreach (array_values($last_conversations) as $conv) {
            $historique .= get_string('previous_question', 'block_uteluqchatbot', $i) . $conv->question . "\n";
            $historique .= get_string('answer', 'block_uteluqchatbot') . $conv->answer . "\n\n";
            $i++;
        }
    }

    // 4. Teacher prompt.
    $course_record = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $course_name   = $course_record->fullname;

    $prompt_record = $DB->get_record(
        'block_uteluqchatbot_prompts',
        ['userid' => $userid, 'courseid' => $courseid]
    );
    $task = $prompt_record
        ? $prompt_record->prompt
        : get_string('default_prompt', 'block_uteluqchatbot');

    $task = str_replace('[[ coursename ]]', $course_name, $task);
    $task = str_replace('[[ question ]]',   $question,    $task);
    $task = str_replace('[[ history ]]',    $historique,  $task);

    $full_prompt = $task
        . "\n\n--- Course content excerpts ---\n"
        . $context_text
        . "--- End of excerpts ---\n\n"
        . "Question: " . $question;

} else {
    $full_prompt = $question;
}

// ── Stream LLM response ───────────────────────────────────────────────────

$full_answer = '';

$ok = $llm->stream_generate(
    $full_prompt,
    function (string $token) use (&$full_answer): void {
        $full_answer .= $token;
        sse(['token' => $token]);
    }
);

if (!$ok) {
    sse_fatal($llm->get_last_error() ?? 'Streaming failed');
}

echo "data: [DONE]\n\n";
flush();

// ── Save conversation ─────────────────────────────────────────────────────

if (!empty($full_answer)) {
    \block_uteluqchatbot\external\send_question::save_conversation(
        (int)$userid,
        (int)$courseid,
        $question,
        $full_answer
    );
}
