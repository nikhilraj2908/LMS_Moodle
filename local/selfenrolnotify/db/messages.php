<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'user_self_enrolled' => [
        'capability' => 'local/selfenrolnotify:berecipient',
        // Default routing for this provider:
        'defaults' => [
            // Show in the bell when the recipient is logged in.
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN,
            // Do NOT send email by default (both logged in/out).
            'email' => MESSAGE_PERMITTED,
        ],
    ],
];
