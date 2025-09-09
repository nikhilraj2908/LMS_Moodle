<?php
defined('MOODLE_INTERNAL') || die();

function local_texttospeech_extend_navigation(\navigation_node $nav) {
    global $PAGE;

    if (!get_config('local_texttospeech', 'enable')) {
        return;
    }

    $config = [
        'defaultSpeed' => (float)(get_config('local_texttospeech', 'defaultspeed') ?? 1.0),
        'highlight'    => (string)(get_config('local_texttospeech', 'highlightcolor') ?? '#c8facc'),
        'showBubble'   => (bool)(get_config('local_texttospeech', 'showbubble') ?? true),
    ];

    $PAGE->requires->css(new moodle_url('/local/texttospeech/styles.css'));
    $PAGE->requires->js_call_amd('local_texttospeech/main', 'init', [$config]);
}
