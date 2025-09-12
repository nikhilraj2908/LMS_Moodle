<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_aisummary_generate_summary' => [
        'classname'   => 'local_aisummary\\external\\generate_summary',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Generate course summary from a title using an AI chat API.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> '' // logged-in required; we check in PHP
    ],
];
