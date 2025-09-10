<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        // Use the invokable handler class ( __invoke ).
        'callback' => \local_aisummary\hook\output\before_standard_head_html_generation::class,
    ],
];
