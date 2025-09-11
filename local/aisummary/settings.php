<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aisummary', get_string('pluginname', 'local_aisummary'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'local_aisummary/apibase',
            get_string('apibase', 'local_aisummary'),
            get_string('apibase_desc', 'local_aisummary'),
            'https://openrouter.ai/api/v1/chat/completions'
        ));

        $settings->add(new admin_setting_configpasswordunmask(
            'local_aisummary/apikey',
            get_string('apikey', 'local_aisummary'),
            get_string('apikey_desc', 'local_aisummary'),
            ''
        ));

        $settings->add(new admin_setting_configtext(
            'local_aisummary/model',
            get_string('model', 'local_aisummary'),
            get_string('model_desc', 'local_aisummary'),
            'meta-llama/llama-3.1-8b-instruct:free'
        ));

        $settings->add(new admin_setting_configtext(
            'local_aisummary/maxtokens',
            get_string('maxtokens', 'local_aisummary'),
            '',
            180
        ));
    }
}
