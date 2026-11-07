<?php
/**
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 */

require_once($CFG->dirroot . '/blocks/uteluqchatbot/classes/admin_setting_test_button.php');

defined('MOODLE_INTERNAL') || die();

if (has_capability('block/uteluqchatbot:manage', context_system::instance()) && $ADMIN->fulltree) {

    // -------------------------------------------------------------------------
    // Section : RAG Mode
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'block_uteluqchatbot/heading_rag_mode',
        get_string('heading_rag_mode', 'block_uteluqchatbot'),
        get_string('heading_rag_mode_desc', 'block_uteluqchatbot')
    ));

    $settings->add(new admin_setting_configselect(
        'block_uteluqchatbot/rag_mode',
        get_string('rag_mode', 'block_uteluqchatbot'),
        get_string('rag_mode_desc', 'block_uteluqchatbot'),
        'normal',
        [
            'normal'  => get_string('rag_mode_normal', 'block_uteluqchatbot'),
            'agentic' => get_string('rag_mode_agentic', 'block_uteluqchatbot'),
        ]
    ));

    // -------------------------------------------------------------------------
    // Section : LLM Provider (génération)
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'block_uteluqchatbot/heading_llm',
        get_string('heading_llm', 'block_uteluqchatbot'),
        get_string('heading_llm_desc', 'block_uteluqchatbot')
    ));

    $settings->add(new admin_setting_configselect(
        'block_uteluqchatbot/llm_provider',
        get_string('llm_provider', 'block_uteluqchatbot'),
        get_string('llm_provider_desc', 'block_uteluqchatbot'),
        'openrouter',
        [
            'openrouter'   => get_string('provider_openrouter', 'block_uteluqchatbot'),
            'huggingface'  => get_string('provider_huggingface', 'block_uteluqchatbot'),
        ]
    ));

    // OpenRouter
    $settings->add(new admin_setting_heading(
        'block_uteluqchatbot/heading_openrouter',
        get_string('heading_openrouter', 'block_uteluqchatbot'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/openrouter_api_key',
        get_string('openrouter_api_key', 'block_uteluqchatbot'),
        get_string('openrouter_api_key_desc', 'block_uteluqchatbot'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/openrouter_model',
        get_string('openrouter_model', 'block_uteluqchatbot'),
        get_string('openrouter_model_desc', 'block_uteluqchatbot'),
        'openai/gpt-4o-mini',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/openrouter_embedding_model',
        get_string('openrouter_embedding_model', 'block_uteluqchatbot'),
        get_string('openrouter_embedding_model_desc', 'block_uteluqchatbot'),
        'openai/text-embedding-3-small',
        PARAM_TEXT
    ));

    // HuggingFace (génération + embedding BGE-M3)
    $settings->add(new admin_setting_heading(
        'block_uteluqchatbot/heading_huggingface',
        get_string('heading_huggingface', 'block_uteluqchatbot'),
        get_string('heading_huggingface_desc', 'block_uteluqchatbot')
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/huggingface_api_key',
        get_string('huggingface_api_key', 'block_uteluqchatbot'),
        get_string('huggingface_api_key_desc', 'block_uteluqchatbot'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/huggingface_model',
        get_string('huggingface_model', 'block_uteluqchatbot'),
        get_string('huggingface_model_desc', 'block_uteluqchatbot'),
        'mistralai/Mistral-7B-Instruct-v0.3',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/huggingface_embedding_model',
        get_string('huggingface_embedding_model', 'block_uteluqchatbot'),
        get_string('huggingface_embedding_model_desc', 'block_uteluqchatbot'),
        'BAAI/bge-m3',
        PARAM_TEXT
    ));

    // -------------------------------------------------------------------------
    // Section : Weaviate
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'block_uteluqchatbot/heading_weaviate',
        get_string('heading_weaviate', 'block_uteluqchatbot'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/weaviate_api_url',
        get_string('weaviate_api_url', 'block_uteluqchatbot'),
        get_string('weaviate_api_url_desc', 'block_uteluqchatbot'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/weaviate_api_key',
        get_string('weaviate_api_key', 'block_uteluqchatbot'),
        get_string('weaviate_api_key_desc', 'block_uteluqchatbot'),
        '',
        PARAM_TEXT
    ));

    // -------------------------------------------------------------------------
    // Section : Adobe PDF Services
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_heading(
        'block_uteluqchatbot/heading_adobe',
        get_string('heading_adobe', 'block_uteluqchatbot'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/adobe_pdf_client_id',
        get_string('adobe_pdf_client_id', 'block_uteluqchatbot'),
        get_string('adobe_pdf_client_id_desc', 'block_uteluqchatbot'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_uteluqchatbot/adobe_pdf_client_secret',
        get_string('adobe_pdf_client_secret', 'block_uteluqchatbot'),
        get_string('adobe_pdf_client_secret_desc', 'block_uteluqchatbot'),
        '',
        PARAM_TEXT
    ));

    // -------------------------------------------------------------------------
    // Bouton de test
    // -------------------------------------------------------------------------
    $settings->add(new admin_setting_test_button(
        'block_uteluqchatbot/test_api_keys',
        get_string('test_api_keys', 'block_uteluqchatbot'),
        get_string('test_api_keys_desc', 'block_uteluqchatbot'),
        ''
    ));
}
