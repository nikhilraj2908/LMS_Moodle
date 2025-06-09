<?php
namespace theme_academi\output;
defined('MOODLE_INTERNAL') || die();

use core\output\dashboard as core_dashboard;

class core_dashboard_renderer extends \core\output\dashboard implements \templatable {

    public function export_for_template(\renderer_base $output) {
        global $USER, $DB;

        // 1) Pull in the “core” data structure
        $data = parent::export_for_template($output);
        $data['username']    = fullname($USER);
        $data['userpicture'] = $this->output->user_picture($USER);

        // 2) Site admin?
        if (is_siteadmin($USER->id)) {
            // ** MUST be “component/templatename” **
            // i.e. theme_academi/core_dashboard_admin WITHOUT any extra slash in the second half
            $data['template'] = 'theme_academi/core_dashboard_admin';

            // … populate ALL the admin data …
            $data['regionCount']        = $DB->count_records('course_categories', ['parent' => 1]);
            $regionalmgrroleid          = $DB->get_field('role', 'id', ['shortname' => 'regionalmanager']);
            $data['regionMgrCount']     = $DB->count_records('role_assignments', ['roleid' => $regionalmgrroleid]);
            $data['totalUserCount']     = $DB->count_records('user', ['deleted' => 0]);
            $data['totalCourseCount']   = $DB->count_records('course', ['visible' => 1]);

            $sql2 = "
                SELECT u.id, u.username, c.name AS regionname
                  FROM {role_assignments} ra
                  JOIN {user} u ON u.id = ra.userid
                  JOIN {context} ctx ON ctx.id = ra.contextid
                  JOIN {course_categories} c ON c.id = ctx.instanceid
                 WHERE ra.roleid = :rmrole
                   AND ctx.contextlevel = :ctxlevel
                 ORDER BY ra.id DESC
                 LIMIT 3
            ";
            $params2 = [
                'rmrole'  => $regionalmgrroleid,
                'ctxlevel'=> CONTEXT_COURSECAT
            ];
            $rms = $DB->get_records_sql($sql2, $params2);
            $data['recentRegionManagers'] = [];
            foreach ($rms as $rm) {
                $data['recentRegionManagers'][] = [
                    'username'   => fullname($rm),
                    'regionname' => $rm->regionname
                ];
            }

            // Generate a placeholder “global hours” array here
            $globalHours = [];
            for ($i = 0; $i < 7; $i++) {
                $globalHours[] = rand(0, 10);
            }
            $data['globalHoursActivity'] = json_encode($globalHours);

            // Pass that roleid so the “List Regional Managers” link in Mustache works:
            $data['regionMgrRoleId'] = $regionalmgrroleid;

            return $data;
        }

        // 3) Not site admin; is Regional Manager?
        $isregionmgr = false;
        $regioncategoryid = null;
        $sql = "
            SELECT ctx.instanceid AS categoryid
              FROM {context} ctx
              JOIN {role_assignments} ra ON ra.contextid = ctx.id
              JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
             WHERE rc.capability = :capability
               AND rc.permission = :allow
               AND ctx.contextlevel = :ctxlevel
               AND ra.userid = :userid
             LIMIT 1
        ";
        $params = [
            'capability' => 'moodle/category:create',
            'allow'      => CAP_ALLOW,
            'ctxlevel'   => CONTEXT_COURSECAT,
            'userid'     => $USER->id
        ];
        if ($row = $DB->get_record_sql($sql, $params)) {
            $isregionmgr = true;
            $regioncategoryid = (int)$row->categoryid;
        }

        if ($isregionmgr && $regioncategoryid !== null) {
            // ** also “component/templatename” format here **
            $data['template'] = 'theme_academi/core_dashboard_manager';

            // 3.a) Region name/ID
            $category               = $DB->get_record('course_categories', ['id' => $regioncategoryid]);
            $data['regionname']     = $category->name;
            $data['regionid']       = $regioncategoryid;

            // 3.b) Active users in that category
            $sql3 = "
                SELECT COUNT(DISTINCT ue.userid) 
                  FROM {enrol} e
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {course} c ON c.id = e.courseid
                 WHERE c.category = :catid
            ";
            $data['activeUserCount']    = (int)$DB->get_field_sql($sql3, ['catid' => $regioncategoryid]);
            $data['totalCoursesInRegion']= $DB->count_records('course', ['category' => $regioncategoryid, 'visible' => 1]);

            // 3.c) Average completion (placeholder or real logic)
            $data['avgCompletionRate']  = round(rand(50, 90), 1);
            $data['newEnrollments7d']   = rand(0, 20);

            // 3.d) “Top 3 courses” placeholder
            $data['topCourses'] = [
                ['coursename' => 'Course A', 'completionPercentage' => 82],
                ['coursename' => 'Course B', 'completionPercentage' => 75],
                ['coursename' => 'Course C', 'completionPercentage' => 63]
            ];

            // 3.e) Region hours (7 days) placeholder
            $regionHours = [];
            for ($i = 0; $i < 7; $i++) {
                $regionHours[] = rand(0, 8);
            }
            $data['regionHoursActivity'] = json_encode($regionHours);

            return $data;
        }

        // 4) Otherwise → regular student
        $data['template'] = 'theme_academi/core_dashboard';

        // … fill in “student” fields (recentCourse, enrolledCourses, learning path, hoursActivity, points, etc.) …
        // Example placeholders:
        $data['recentCourse']         = [ /* … */ ];
        $data['enrolledCourses']      = [ /* … */ ];
        $data['learningPathPercentage']= 18;
        $data['completedCourses']     = 2;
        $data['remainingCourses']     = 9;
        $data['totalModules']         = 11;
        $data['hoursActivity']        = json_encode([1,0,2,3,1,0,1]);
        $data['percentageChange']     = 0;
        $data['isIncrease']           = true;
        $data['currentWeekTotal']     = 0;
        $data['previousWeekTotal']    = 0;
        $data['courses']              = [ /* … points per course … */ ];
        $data['totalPoints']          = 80;
        $data['totalPossiblePoints']  = 200;
        $data['course_award_url']     = (new \moodle_url('/theme/academi/pix/award.png'))->out(false);
        $data['totalCourses']         = 2;
        $data['course_enrolled_url']  = (new \moodle_url('/theme/academi/pix/courses.png'))->out(false);

        return $data;
    }
}
