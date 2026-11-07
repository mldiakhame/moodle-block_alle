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

/**
 * Checks that every service configured for ALL-E answers.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_alle\local\adobe_pdf_services;
use block_alle\local\provider_factory;

require_once(__DIR__ . '/../../config.php');

require_admin();
$url = new moodle_url('/blocks/alle/testsettings.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('testsettings', 'block_alle'));
$PAGE->set_heading(get_string('testsettings', 'block_alle'));

$run = optional_param('run', 0, PARAM_BOOL);

echo $OUTPUT->header();

if (!$run) {
    // The checks call paid APIs, so they only run on explicit request.
    echo html_writer::tag('p', get_string('testsettings_intro', 'block_alle'));
    $runurl = new moodle_url($url, ['run' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($runurl, get_string('testsettings_run', 'block_alle'));
    echo $OUTPUT->footer();
    die();
}
require_sesskey();

$factory = new provider_factory();
$config = get_config('block_alle');
$checks = [
    get_string('heading_weaviate', 'block_alle') => function () use ($factory) {
        $count = count($factory->get_vector_store()->list_collections());
        return get_string('check_weaviate_ok', 'block_alle', $count);
    },
    get_string('check_embeddings', 'block_alle') => function () use ($factory) {
        $embedder = $factory->get_embedder();
        $dimensions = count($embedder->embed('ALL-E'));
        return get_string('check_embeddings_ok', 'block_alle', (object) [
            'provider' => $embedder->get_label(),
            'dimensions' => $dimensions,
        ]);
    },
    get_string('check_generation', 'block_alle') => function () use ($factory) {
        $llm = $factory->get_llm();
        $answer = core_text::substr(trim($llm->generate('Reply with exactly one word: OK')), 0, 80);
        return get_string('check_generation_ok', 'block_alle', (object) ['provider' => $llm->get_label(), 'answer' => $answer]);
    },
    get_string('pathtopdftotext', 'block_alle') => function () use ($config) {
        $path = (string) ($config->pathtopdftotext ?? '');
        if ($path === '' || !is_executable($path)) {
            throw new moodle_exception('check_pdftotext_missing', 'block_alle', '', s($path));
        }
        return get_string('check_pdftotext_ok', 'block_alle', s($path));
    },
    get_string('heading_pdf', 'block_alle') . ' (Adobe)' => function () use ($config) {
        if (empty($config->adobe_client_id) || empty($config->adobe_client_secret)) {
            return get_string('check_adobe_skipped', 'block_alle');
        }
        (new adobe_pdf_services($config->adobe_client_id, $config->adobe_client_secret))->check_credentials();
        return get_string('check_adobe_ok', 'block_alle');
    },
];

foreach ($checks as $service => $check) {
    try {
        $message = $check();
        $type = \core\output\notification::NOTIFY_SUCCESS;
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    echo $OUTPUT->notification(html_writer::tag('strong', s($service)) . ': ' . s($message), $type, false);
}

echo $OUTPUT->single_button(
    new moodle_url('/admin/settings.php', ['section' => 'blocksettingalle']),
    get_string('backtosettings', 'block_alle'),
    'get'
);
echo $OUTPUT->footer();
