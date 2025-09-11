<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_aisummary_generate_summary' => [
        'classname'   => 'local_aisummary\\external\\generate_summary',
        'methodname'  => 'execute',
        'description' => 'Generate a course summary from the title using an AI API.',
        'type'        => 'read',
        'ajax'        => true,
    ],
];
