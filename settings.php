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
 * Site settings of ALL-E.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_alle\local\provider_factory;
use block_alle\local\providers\huggingface_embedder;
use block_alle\local\providers\huggingface_provider;

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_description(
        'block_alle/testsettings',
        get_string('testsettings', 'block_alle'),
        html_writer::link(
            new moodle_url('/blocks/alle/testsettings.php'),
            get_string('testsettings_run', 'block_alle'),
            ['class' => 'btn btn-secondary']
        )
    ));

    // How answers are produced.
    $settings->add(new admin_setting_heading('block_alle/heading_rag', get_string('heading_rag', 'block_alle'), ''));
    $settings->add(new admin_setting_configselect(
        'block_alle/rag_mode',
        get_string('rag_mode', 'block_alle'),
        get_string('rag_mode_desc', 'block_alle'),
        'agentic',
        [
            'standard' => get_string('rag_mode_standard', 'block_alle'),
            'agentic' => get_string('rag_mode_agentic', 'block_alle'),
        ]
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/relevance_threshold',
        get_string('relevance_threshold', 'block_alle'),
        get_string('relevance_threshold_desc', 'block_alle'),
        provider_factory::DEFAULT_RELEVANCE_THRESHOLD,
        PARAM_FLOAT,
        6
    ));

    // AI provider.
    $settings->add(new admin_setting_heading(
        'block_alle/heading_provider',
        get_string('heading_provider', 'block_alle'),
        get_string('heading_provider_desc', 'block_alle')
    ));
    $settings->add(new admin_setting_configselect(
        'block_alle/provider',
        get_string('provider', 'block_alle'),
        get_string('provider_desc', 'block_alle'),
        'openrouter',
        [
            'openrouter' => get_string('provider_openrouter', 'block_alle'),
            'huggingface' => get_string('provider_huggingface', 'block_alle'),
        ]
    ));

    $settings->add(new admin_setting_heading('block_alle/heading_openrouter', get_string('provider_openrouter', 'block_alle'), ''));
    $settings->add(new admin_setting_configpasswordunmask(
        'block_alle/openrouter_api_key',
        get_string('openrouter_api_key', 'block_alle'),
        get_string('openrouter_api_key_desc', 'block_alle'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/openrouter_model',
        get_string('openrouter_model', 'block_alle'),
        get_string('openrouter_model_desc', 'block_alle'),
        provider_factory::DEFAULT_OPENROUTER_MODEL
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/openrouter_embedding_model',
        get_string('openrouter_embedding_model', 'block_alle'),
        get_string('embedding_model_desc', 'block_alle'),
        provider_factory::DEFAULT_OPENROUTER_EMBEDDING_MODEL
    ));

    $settings->add(new admin_setting_heading(
        'block_alle/heading_huggingface',
        get_string('provider_huggingface', 'block_alle'),
        ''
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'block_alle/huggingface_api_key',
        get_string('huggingface_api_key', 'block_alle'),
        get_string('huggingface_api_key_desc', 'block_alle'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/huggingface_model',
        get_string('huggingface_model', 'block_alle'),
        get_string('huggingface_model_desc', 'block_alle'),
        provider_factory::DEFAULT_HUGGINGFACE_MODEL
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/huggingface_endpoint',
        get_string('huggingface_endpoint', 'block_alle'),
        get_string('huggingface_endpoint_desc', 'block_alle'),
        huggingface_provider::DEFAULT_ENDPOINT,
        PARAM_URL
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/huggingface_embedding_model',
        get_string('huggingface_embedding_model', 'block_alle'),
        get_string('embedding_model_desc', 'block_alle'),
        provider_factory::DEFAULT_HUGGINGFACE_EMBEDDING_MODEL
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/huggingface_embedding_endpoint',
        get_string('huggingface_embedding_endpoint', 'block_alle'),
        get_string('huggingface_embedding_endpoint_desc', 'block_alle'),
        huggingface_embedder::DEFAULT_ENDPOINT,
        PARAM_RAW_TRIMMED
    ));

    // Vector database.
    $settings->add(new admin_setting_heading(
        'block_alle/heading_weaviate',
        get_string('heading_weaviate', 'block_alle'),
        get_string('heading_weaviate_desc', 'block_alle')
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/weaviate_url',
        get_string('weaviate_url', 'block_alle'),
        get_string('weaviate_url_desc', 'block_alle'),
        '',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'block_alle/weaviate_api_key',
        get_string('weaviate_api_key', 'block_alle'),
        get_string('weaviate_api_key_desc', 'block_alle'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/collection_prefix',
        get_string('collection_prefix', 'block_alle'),
        get_string('collection_prefix_desc', 'block_alle'),
        'Alle',
        PARAM_ALPHANUM
    ));

    // Reading PDF documents.
    $settings->add(new admin_setting_heading(
        'block_alle/heading_pdf',
        get_string('heading_pdf', 'block_alle'),
        get_string('heading_pdf_desc', 'block_alle')
    ));
    $settings->add(new admin_setting_configexecutable(
        'block_alle/pathtopdftotext',
        get_string('pathtopdftotext', 'block_alle'),
        get_string('pathtopdftotext_desc', 'block_alle'),
        '/usr/bin/pdftotext'
    ));
    $settings->add(new admin_setting_configtext(
        'block_alle/adobe_client_id',
        get_string('adobe_client_id', 'block_alle'),
        get_string('adobe_client_id_desc', 'block_alle'),
        ''
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'block_alle/adobe_client_secret',
        get_string('adobe_client_secret', 'block_alle'),
        get_string('adobe_client_secret_desc', 'block_alle'),
        ''
    ));
}
