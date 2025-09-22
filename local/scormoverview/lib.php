<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add link in the module navigation (older/secondary nav).
 */
function local_scormoverview_extend_navigation_module($navigation, $cm) {
    global $COURSE;
    if ($cm->modname !== 'scorm') { return; }
    $context = \context_module::instance($cm->id);

    if (!has_capability('mod/scorm:viewreport', $context)
        && !has_capability('local/scormoverview:view', $context)) {
        return;
    }

    $url = new \moodle_url('/local/scormoverview/index.php', [
        'cmid' => $cm->id,
        'scormid' => $cm->instance,
        'courseid' => $COURSE->id
    ]);

    $node = \navigation_node::create(
        get_string('navlabel', 'local_scormoverview'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'local_scormoverview'
    );
    $navigation->add_node($node);
}

/**
 * Add link in the cog/More menu (reliable on Moodle 3.9–4.x and most themes).
 */
function local_scormoverview_extend_settings_navigation(\settings_navigation $settingsnav, \context $context) {
    global $COURSE;

    if ($context->contextlevel !== CONTEXT_MODULE) { return; }
    if (!$cm = get_coursemodule_from_id(null, $context->instanceid, 0, false, IGNORE_MISSING)) { return; }
    if ($cm->modname !== 'scorm') { return; }

    if (!has_capability('mod/scorm:viewreport', $context)
        && !has_capability('local/scormoverview:view', $context)) {
        return;
    }

    $url = new \moodle_url('/local/scormoverview/index.php', [
        'cmid' => $cm->id,
        'scormid' => $cm->instance,
        'courseid' => $COURSE->id
    ]);

    if ($mods = $settingsnav->find('modulesettings', \navigation_node::TYPE_SETTING)) {
        $mods->add(get_string('navlabel', 'local_scormoverview'), $url,
            \navigation_node::TYPE_SETTING, null, 'local_scormoverview');
    } else {
        $settingsnav->add(get_string('navlabel', 'local_scormoverview'), $url,
            \navigation_node::TYPE_SETTING, null, 'local_scormoverview');
    }
}
