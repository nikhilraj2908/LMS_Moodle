<?php
namespace local_selfenrolnotify;

defined('MOODLE_INTERNAL') || die();

use core\message\message;

class observer {

    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        global $DB;

        // Snapshot of user_enrolments.
        $ue = $event->get_record_snapshot('user_enrolments', $event->objectid);
        if (!$ue) {
            return;
        }

        // Get enrol row to check plugin type (self/manual/guest/etc.).
        $enrol = $DB->get_record('enrol', ['id' => $ue->enrolid], 'id, enrol, courseid', IGNORE_MISSING);
        if (!$enrol || $enrol->enrol !== 'self') {
            return; // Not a self-enrolment → ignore.
        }

        // Course & user info.
        $course = $DB->get_record('course', ['id' => $enrol->courseid], '*', MUST_EXIST);
      
        // Option A: simplest (grab all fields, safe)
        $user = $DB->get_record('user', ['id' => $ue->userid], '*', MUST_EXIST);

        // Build message text.
        $courselink = new \moodle_url('/course/view.php', ['id' => $course->id]);
        $a = (object)[
            'fullname'   => fullname($user),
            'username'   => $user->email,
            'coursename' => format_string($course->fullname, true),
            'courselink' => $courselink->out(false),
        ];
        $subject = get_string('msgsubject', 'local_selfenrolnotify', $a);
        $small   = get_string('msgsmall',   'local_selfenrolnotify', $a);
        $full    = get_string('msgfull',    'local_selfenrolnotify', $a);

        // Who to notify?
        // 1) All site admins.
        $recipients = get_admins();

        // 2) Optionally notify a role in the course context (default: manager).
        $roleid = (int) get_config('local_selfenrolnotify', 'notifyroleid');
        if ($roleid) {
            $context = \context_course::instance($course->id);
            $roleusers = get_role_users($roleid, $context, false, 'u.id, u.firstname, u.lastname, u.email');
            foreach ((array)$roleusers as $ru) {
                $recipients[$ru->id] = $ru; // Deduplicate by key.
            }
        }

        if (empty($recipients)) {
            return;
        }

        // Send popup notification to each recipient via message provider.
        foreach ($recipients as $recipient) {
            $msg = new message();
            $msg->component         = 'local_selfenrolnotify';
            $msg->name              = 'user_self_enrolled'; // message provider name
            $msg->userfrom          = \core_user::get_noreply_user();
            $msg->userto            = $recipient;
            $msg->subject           = $subject;
            $msg->fullmessage       = $full;
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml   = '<p>'.format_string($full, true).'</p>';
            $msg->smallmessage      = $small;
            $msg->notification      = 1; // Flag as notification (shows in bell if enabled).
            $msg->contexturl        = $courselink->out(false);
            $msg->contexturlname    = $a->coursename;

            message_send($msg);
        }
    }
}
