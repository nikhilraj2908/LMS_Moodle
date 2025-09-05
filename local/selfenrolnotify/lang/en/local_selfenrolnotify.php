<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Self-enrol notifications';
$string['privacy:metadata'] = 'This plugin stores no personal data.';

// REQUIRED: label shown on the Messaging settings page for this provider.
$string['messageprovider:user_self_enrolled'] = 'User self-enrolled in a course';

$string['selfenrolnotify:berecipient'] = 'Receive self-enrolment notifications';

$string['notifyroleid'] = 'Also notify this course role';
$string['notifyroleid_desc'] = 'If set, users with this role in the course (e.g., Manager) will also be notified.';

$string['msgsubject'] = 'Self-enrolment: {$a->fullname} joined "{$a->coursename}"';
$string['msgsmall']   = '{$a->fullname} self-enrolled in {$a->coursename}';
$string['msgfull']    = '{$a->fullname} ({$a->username}) has self-enrolled in the course "{$a->coursename}". Visit: {$a->courselink}';
