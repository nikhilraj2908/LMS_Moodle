<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_selfenrolnotify', get_string('pluginname', 'local_selfenrolnotify'));

    // Dropdown of roles; default = Manager.
    $roles = role_fix_names(get_all_roles());
    $roleoptions = [0 => get_string('none')] + array_reduce($roles, function($carry, $r) {
        $carry[$r->id] = $r->localname ?? $r->name;
        return $carry;
    }, []);

    $settings->add(new admin_setting_configselect(
        'local_selfenrolnotify/notifyroleid',
        get_string('notifyroleid', 'local_selfenrolnotify'),
        get_string('notifyroleid_desc', 'local_selfenrolnotify'),
        0, // None by default (admins only). Choose Manager to include managers.
        $roleoptions
    ));

    $ADMIN->add('localplugins', $settings);
}
