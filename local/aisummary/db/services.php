<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_aisummary_generate_summary' => [
        'classname'   => 'local_aisummary\external\generate_summary',
        'methodname'  => 'execute',
        'description' => 'Generate a course summary (bulleted) from the title via AI.',
        'type'        => 'read',
        'ajax'        => true,
    ],
];
