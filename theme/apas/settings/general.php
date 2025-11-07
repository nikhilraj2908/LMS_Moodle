<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin settings configuration for general section
 *
 * @package   theme_apas
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// General section.
$temp = new admin_settingpage('theme_apas_header', get_string('headerheading', 'theme_apas'));

/* ---------------------------
 * Header / branding
 * --------------------------- */

// Nav style select option.
$name = 'theme_apas/navstyle';
$title = get_string('navstyle', 'theme_apas');
$description = get_string('navstyle_desc', 'theme_apas');
$default = LOGO;
$choices = [
    LOGO => get_string('logo', 'theme_apas'),
    SITENAME => get_string('sitename', 'theme_apas'),
    LOGOANDSITENAME => get_string('logoandsitename', 'theme_apas'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

// Logo file upload option.
$setting = new admin_setting_configstoredfile(
    'theme_apas/logo',
    get_string('logo', 'theme_apas'),
    get_string('logodesc', 'theme_apas'),
    'logo'
);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Small (mobile) logo when responsive.
$setting = new admin_setting_configstoredfile(
    'theme_apas/smalllogo',
    get_string('smalllogo', 'theme_apas'),
    get_string('smalllogodesc', 'theme_apas'),
    'smalllogo'
);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Favicon.
$setting = new admin_setting_configstoredfile(
    'theme_apas/favicon',
    get_string('favicon', 'theme_apas', null, true),
    get_string('favicon_desc', 'theme_apas', null, true),
    'favicon',
    0
);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

/* ---------------------------
 * Brand colors (APAS defaults)
 * --------------------------- */

// Primary color – default to APAS brand.
$name = 'theme_apas/primarycolor';
$title = get_string('primarycolor', 'theme_apas');
$description = get_string('primarycolor_desc', 'theme_apas');
$default = '#204070';
$setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Secondary color – default to APAS brand.
$name = 'theme_apas/secondarycolor';
$title = get_string('secondarycolor', 'theme_apas');
$description = get_string('secondarycolor_desc', 'theme_apas');
$default = '#3b3c41';
$setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

/* ---------------------------
 * Header style / layout
 * --------------------------- */

$name = 'theme_apas/themestyleheader';
$title = get_string('themestyleheader', 'theme_apas');
$description = get_string('themestyleheader_desc', 'theme_apas');
$default = THEMEBASED;
$choices = [
    THEMEBASED => get_string('themebased', 'theme_apas'),
    MOODLEBASED => get_string('moodlebased', 'theme_apas'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

// Site inner page width.
$name = 'theme_apas/pagesize';
$title = get_string('pagesize', 'theme_apas');
$description = get_string('pagesize_desc', 'theme_apas');
$default = 'container'; // was '1' – use a valid key
$choices = [
    'container' => get_string('container', 'theme_apas'),
    'default'   => get_string('moodledefault', 'theme_apas'),
    'custom'    => get_string('custom', 'theme_apas'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

// Custom page width when 'custom' is selected.
$name = 'theme_apas/pagesizecustomval';
$title = get_string('pagesizecustomval', 'theme_apas');
$description = get_string('pagesizecustomval_desc', 'theme_apas');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default, PARAM_INT);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Content font size.
$name = 'theme_apas/fontsize';
$title = get_string('fontsize', 'theme_apas');
$description = get_string('fontsize_desc', 'theme_apas');
$default = THEMEDEFAULT;
$sizes = [
    THEMEDEFAULT => get_string('default'),
    SMALL        => get_string('small', 'theme_apas'),
    MEDIUM       => get_string('medium', 'theme_apas'),
    LARGE        => get_string('large', 'theme_apas'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $sizes);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Available course type.
$name = 'theme_apas/availablecoursetype';
$title = get_string('availablecoursetype', 'theme_apas');
$description = get_string('availablecoursetype_desc', 'theme_apas');
$default = CAROUSEL;
$choices = [
    CAROUSEL   => get_string('carousel', 'theme_apas'),
    MOODLEBASED => get_string('moodlebased', 'theme_apas'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

// Combo list box type.
$name = 'theme_apas/comboListboxType';
$title = get_string('comboListboxType', 'theme_apas');
$description = get_string('comboListboxType_desc', 'theme_apas');
$default = COLLAPSE;
$choices = [
    EXPAND   => get_string('expand', 'theme_apas'),
    COLLAPSE => get_string('collapse', 'theme_apas'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

// Background image (Boost setting area preserved).
$name = 'theme_boost/backgroundimage';
$title = get_string('backgroundimage', 'theme_boost');
$description = get_string('backgroundimage_desc', 'theme_apas');
$setting = new admin_setting_configstoredfile($name, $title, $description, 'backgroundimage');
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Login page background image.
$name = 'theme_apas/loginbg';
$title = get_string('loginbg', 'theme_apas');
$description = get_string('loginbg_desc', 'theme_apas');
$setting = new admin_setting_configstoredfile($name, $title, $description, 'loginbg', 0);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Back to top toggle.
$name = 'theme_apas/backToTop_status';
$title = get_string('backToTop_status', 'theme_apas');
$description = get_string('backToTop_statusdesc', 'theme_apas');
$default = YES;
$choices = [
    YES => get_string('yes'),
    NO  => get_string('no'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

// Custom CSS box.
$name = 'theme_apas/customcss';
$title = get_string('customcss', 'theme_apas');
$description = get_string('customcssdesc', 'theme_apas');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

/* ---------------------------
 * Presets (add APAS + set default)
 * --------------------------- */

// Heading.
$name = 'theme_apas/presetheading';
$title = get_string('presetheading', 'theme_apas', null, true);
$setting = new admin_setting_heading($name, $title, null);
$temp->add($setting);

// Replicate Boost preset setting, using APAS file area.
$name = 'theme_apas/preset';
$title = get_string('preset', 'theme_boost', null, true);
$description = get_string('preset_desc', 'theme_boost', null, true);

// Build choices from uploaded files first.
$context = context_system::instance();
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'theme_apas', 'preset', 0, 'itemid, filepath, filename', false);

$choices = [];
foreach ($files as $file) {
    $choices[$file->get_filename()] = $file->get_filename();
}

// Ensure bundled presets are available as choices.
$choices['apas.scss']     = 'APAS';      // our brand preset
$choices['default.scss']  = 'Default';
$choices['plain.scss']    = 'Plain';
$choices['eguru.scss']    = 'Eguru';
$choices['klass.scss']    = 'Klass';
$choices['enlightlite.scss'] = 'Enlightlite';

// Make APAS the default preset.
$default = 'apas.scss';

$setting = new admin_setting_configthemepreset($name, $title, $description, $default, $choices, 'apas');

$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Allow uploading additional .scss presets.
$name = 'theme_apas/presetfiles';
$title = get_string('presetfiles', 'theme_boost', null, true);
$description = get_string('presetfiles_desc', 'theme_boost', null, true);
$setting = new admin_setting_configstoredfile($name, $title, $description, 'preset', 0,
    ['maxfiles' => 20, 'accepted_types' => ['.scss']]);
$temp->add($setting);

$settings->add($temp);
