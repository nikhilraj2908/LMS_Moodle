<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/selfenrolnotify:berecipient' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype'     => 'read',
        'contextlevel'=> CONTEXT_SYSTEM,
        'archetypes'  => [
            'manager' => CAP_ALLOW,
            'coursecreator' => CAP_PREVENT,
        ],
    ],
];
