<?php
defined('MOODLE_INTERNAL') || die();

if ($h = get_string_manager()->string_exists('pluginname', 'local_aisummary')) {
    $settings = new admin_settingpage('local_aisummary', get_string('pluginname', 'local_aisummary'));

    // API base
    $settings->add(new admin_setting_configtext(
        'local_aisummary/apibase',
        get_string('apibase', 'local_aisummary'),
        get_string('apibase_desc', 'local_aisummary'),
        'https://openrouter.ai/api/v1/chat/completions',
        PARAM_RAW_TRIMMED
    ));

    // API key
    $settings->add(new admin_setting_configpasswordunmask(
        'local_aisummary/apikey',
        get_string('apikey', 'local_aisummary'),
        get_string('apikey_desc', 'local_aisummary'),
        ''
    ));

    // Model
    $settings->add(new admin_setting_configtext(
        'local_aisummary/model',
        get_string('model', 'local_aisummary'),
        get_string('model_desc', 'local_aisummary'),
        'meta-llama/llama-3-8b-instruct:free',
        PARAM_RAW_TRIMMED
    ));

    // Max tokens
    $settings->add(new admin_setting_configtext(
        'local_aisummary/maxtokens',
        get_string('maxtokens', 'local_aisummary'),
        get_string('maxtokens_desc', 'local_aisummary'),
        600,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
