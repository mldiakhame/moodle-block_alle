<?php
/**
 * API key testing page for TÉLUQ Chatbot plugin.
 *
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/uteluqchatbot/test_api_keys.php');
$PAGE->set_title(get_string('test_api_keys', 'block_uteluqchatbot'));
$PAGE->set_heading(get_string('test_api_keys', 'block_uteluqchatbot'));

// ============================================================================
// Test functions
// ============================================================================

function test_adobe_pdf($client_id, $client_secret): array {
    if (empty($client_id) || empty($client_secret)) {
        return ['success' => false, 'message' => get_string('adobe_pdf_credentials_not_configured', 'block_uteluqchatbot')];
    }

    $curl = new curl();
    $post_data = 'client_id=' . urlencode($client_id) . '&client_secret=' . urlencode($client_secret);
    $options = [
        'returntransfer' => true,
        'post'           => true,
        'postfields'     => $post_data,
        'httpheader'     => ['Content-Type: application/x-www-form-urlencoded'],
        'ssl_verifypeer' => false,
    ];

    $response    = $curl->post('https://pdf-services.adobe.io/token', $post_data, $options);
    $http_status = $curl->get_info()['http_code'];

    if ($curl->get_errno() || $http_status != 200) {
        return ['success' => false, 'message' => get_string('adobe_invalid_credentials', 'block_uteluqchatbot')];
    }

    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        return ['success' => true, 'message' => get_string('adobe_valid_credentials', 'block_uteluqchatbot')];
    }

    return ['success' => false, 'message' => get_string('adobe_invalid_credentials', 'block_uteluqchatbot')];
}

function test_weaviate($api_url, $api_key): array {
    if (empty($api_url)) {
        return ['success' => false, 'message' => get_string('weaviate_invalid_key_or_url', 'block_uteluqchatbot') . '—'];
    }

    if (strpos($api_url, 'https://') !== 0) {
        $api_url = 'https://' . $api_url;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $api_url . '/v1/schema',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return ['success' => false, 'message' => get_string('weaviate_connection_error', 'block_uteluqchatbot') . $curl_err];
    }
    if ($http_code !== 200) {
        return ['success' => false, 'message' => get_string('weaviate_invalid_key_or_url', 'block_uteluqchatbot') . $http_code];
    }

    $schema = json_decode($response, true);
    $count  = isset($schema['classes']) ? count($schema['classes']) : 0;
    return [
        'success' => true,
        'message' => get_string('weaviate_valid_key_and_url', 'block_uteluqchatbot') . " ($count collection(s) found)",
    ];
}

function test_embedder(string $provider_id): array {
    if ($provider_id === 'huggingface') {
        $key   = get_config('block_uteluqchatbot', 'huggingface_api_key');
        $model = get_config('block_uteluqchatbot', 'huggingface_embedding_model') ?: 'BAAI/bge-m3';
        if (empty($key)) {
            return ['success' => false, 'message' => get_string('embedding_not_configured', 'block_uteluqchatbot')];
        }
        $embedder = new \block_uteluqchatbot\providers\huggingface_embedder($key, $model);
    } else {
        $key   = get_config('block_uteluqchatbot', 'openrouter_api_key');
        $model = get_config('block_uteluqchatbot', 'openrouter_embedding_model') ?: 'openai/text-embedding-3-small';
        if (empty($key)) {
            return ['success' => false, 'message' => get_string('embedding_not_configured', 'block_uteluqchatbot')];
        }
        $embedder = new \block_uteluqchatbot\providers\openrouter_embedder($key, $model);
    }
    return $embedder->test_connection();
}

function test_llm_provider(string $provider_id): array {
    if ($provider_id === 'huggingface') {
        $key   = get_config('block_uteluqchatbot', 'huggingface_api_key');
        $model = get_config('block_uteluqchatbot', 'huggingface_model') ?: 'mistralai/Mistral-7B-Instruct-v0.3';
        if (empty($key)) {
            return ['success' => false, 'message' => get_string('llm_provider_not_configured', 'block_uteluqchatbot')];
        }
        $provider = new \block_uteluqchatbot\providers\huggingface_provider($key, $model);
        $label    = "HuggingFace ($model)";
    } else {
        $key   = get_config('block_uteluqchatbot', 'openrouter_api_key');
        $model = get_config('block_uteluqchatbot', 'openrouter_model') ?: 'openai/gpt-4o-mini';
        if (empty($key)) {
            return ['success' => false, 'message' => get_string('llm_provider_not_configured', 'block_uteluqchatbot')];
        }
        $provider = new \block_uteluqchatbot\providers\openrouter_provider($key, $model);
        $label    = "OpenRouter ($model)";
    }

    $response = $provider->generate('Reply with exactly one word: OK');
    if ($response === null) {
        return ['success' => false, 'message' => "$label — " . $provider->get_last_error()];
    }

    return ['success' => true, 'message' => "$label — response received: " . mb_substr(trim($response), 0, 80)];
}

// ============================================================================
// Run tests
// ============================================================================

$provider_id = get_config('block_uteluqchatbot', 'llm_provider') ?: 'openrouter';

$results = [
    'Weaviate'                                      => test_weaviate(
        get_config('block_uteluqchatbot', 'weaviate_api_url'),
        get_config('block_uteluqchatbot', 'weaviate_api_key')
    ),
    'Embeddings — ' . ucfirst($provider_id)        => test_embedder($provider_id),
    'LLM generation — ' . ucfirst($provider_id)    => test_llm_provider($provider_id),
    'Adobe PDF Services'                            => test_adobe_pdf(
        get_config('block_uteluqchatbot', 'adobe_pdf_client_id'),
        get_config('block_uteluqchatbot', 'adobe_pdf_client_secret')
    ),
];

// ============================================================================
// Output
// ============================================================================

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('test_api_keys', 'block_uteluqchatbot'));

foreach ($results as $service => $result) {
    $css = $result['success'] ? 'alert alert-success' : 'alert alert-danger';
    echo html_writer::div(
        html_writer::tag('strong', $service . ': ') . htmlspecialchars($result['message']),
        $css
    );
}

echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url('/admin/settings.php', ['section' => 'blocksettingchatbot']),
        get_string('back', 'block_uteluqchatbot'),
        'get'
    ),
    'mt-3'
);

echo $OUTPUT->footer();
