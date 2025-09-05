<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => '\local_selfenrolnotify\observer::user_enrolment_created',
        'priority'    => 9999,
        'internal'    => false,
    ],
];
