<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_texttospeech', get_string('pluginname', 'local_texttospeech'));
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'local_texttospeech/enable',
            get_string('enable', 'local_texttospeech'),
            get_string('enable_desc', 'local_texttospeech'),
            1
        ));
        $settings->add(new admin_setting_configtext(
            'local_texttospeech/defaultspeed',
            get_string('defaultspeed', 'local_texttospeech'),
            get_string('defaultspeed_desc', 'local_texttospeech'),
            '1.0',
            PARAM_RAW_TRIMMED
        ));
        $settings->add(new admin_setting_configtext(
            'local_texttospeech/highlightcolor',
            get_string('highlightcolor', 'local_texttospeech'),
            get_string('highlightcolor_desc', 'local_texttospeech'),
            '#c8facc',
            PARAM_RAW_TRIMMED
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_texttospeech/showbubble',
            get_string('showbubble', 'local_texttospeech'),
            get_string('showbubble_desc', 'local_texttospeech'),
            1
        ));
    }
    $ADMIN->add('localplugins', $settings);
}
