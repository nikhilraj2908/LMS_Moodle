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
 * Admin settings configuration for footer section.
 *
 * @package   theme_apas
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// Footer.
$temp = new admin_settingpage('theme_apas_footer', get_string('footerheading', 'theme_apas'));

// Footer general block heading.
$name = 'theme_apas_footergeneralheading';
$heading = get_string('footerblockgeneral', 'theme_apas');
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Footer background image file setting.
$name = 'theme_apas/footerbgimg';
$title = get_string('footerbgimg', 'theme_apas');
$description = get_string('footerbgimgdesc', 'theme_apas');
$setting = new admin_setting_configstoredfile($name, $title, $description, 'footerbgimg');
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Footer background Overlay Opacity.
$name = 'theme_apas/footerbgOverlay';
$title = get_string('footerbgOverlay', 'theme_apas');
$description = get_string('footerbgOverlay_desc', 'theme_apas');
$opacity = array_combine(range(0, 1, 0.1), range(0, 1, 0.1));
$setting = new admin_setting_configselect($name, $title, $description, '0.4', $opacity);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Copyright (AlogicData default).
$name = 'theme_apas/copyright_footer';
$title = get_string('copyright_footer', 'theme_apas');
$description = '';
$default = '© ' . date('Y') . ' AlogicData. All rights reserved.';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

/* =========================
 * Footer Block 1 (About)
 * ========================= */
$name = 'theme_apas_footerblock1heading';
$heading = get_string('footerblock', 'theme_apas') . ' 1 ';
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Enable block 1 by default.
$name = 'theme_apas/footerb1_status';
$title = get_string('status', 'theme_apas');
$description = get_string('fblock_statusdesc', 'theme_apas');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Title.
$name = 'theme_apas/footerbtitle1';
$title = get_string('title', 'theme_apas');
$description = get_string('footerbtitledesc', 'theme_apas');
$default = 'About AlogicData';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Show logo in footer.
$name = 'theme_apas/footlogostatus';
$title = get_string('footerenable', 'theme_apas');
$description = '';
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Footer Logo upload.
$name = 'theme_apas/footerlogo';
$title = get_string('footerlogo', 'theme_apas');
$description = get_string('footerlogodesc', 'theme_apas');
$setting = new admin_setting_configstoredfile($name, $title, $description, 'footerlogo');
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Footnote / About text (HTML).
$name = 'theme_apas/footnote';
$title = get_string('footnote', 'theme_apas');
$description = get_string('footnotedesc', 'theme_apas');
$default = '<p><strong>AlogicData</strong> builds premium e-learning, LMS, and custom software solutions. '
    . 'APAS is our modern Moodle theme, crafted with a brand palette of <code>#204070</code> and <code>#3b3c41</code> for a clean, accessible experience.</p>';
$setting = new admin_setting_confightmleditor($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

/* =========================
 * Footer Block 2 (Resources)
 * ========================= */
$name = 'theme_apas_footerblock2heading';
$heading = get_string('footerblock', 'theme_apas') . ' 2 ';
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Enable block 2 by default.
$name = 'theme_apas/footerb2_status';
$title = get_string('status', 'theme_apas');
$description = get_string('fblock_statusdesc', 'theme_apas');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Title.
$name = 'theme_apas/footerbtitle2';
$title = get_string('title', 'theme_apas');
$description = get_string('footerbtitledesc', 'theme_apas');
$default = 'Resources';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Info links (Label | URL per line).
$name = 'theme_apas/infolink';
$title = get_string('infolink', 'theme_apas');
$description = get_string('infolink_desc', 'theme_apas');
$default = implode("\n", [
    'AlogicData | https://alogicdata.com',
    'Support | mailto:support@alogicdata.com',
    'APAS Theme | https://alogicdata.com/apas',
    'Privacy Policy | https://alogicdata.com/privacy'
]);
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$temp->add($setting);

/* =========================
 * Footer Block 3 (Contact)
 * ========================= */
$name = 'theme_apas_footerblock3heading';
$heading = get_string('footerblock', 'theme_apas') . ' 3 ';
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Enable block 3.
$name = 'theme_apas/footerb3_status';
$title = get_string('status', 'theme_apas');
$description = get_string('fblock_statusdesc', 'theme_apas');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Title.
$name = 'theme_apas/footerbtitle3';
$title = get_string('title', 'theme_apas');
$description = get_string('footerbtitledesc', 'theme_apas');
$default = 'Contact';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Address / Email / Phone (AlogicData defaults – edit if needed).
$name = 'theme_apas/address';
$title = get_string('address', 'theme_apas');
$description = '';
$default = 'AlogicData, Pune, Maharashtra, India';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

$name = 'theme_apas/emailid';
$title = get_string('emailid', 'theme_apas');
$description = '';
$default = 'support@alogicdata.com';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

$name = 'theme_apas/phoneno';
$title = get_string('phoneno', 'theme_apas');
$description = '';
$default = '+91-99999-99999';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

/* =========================
 * Footer Block 4 (Social)
 * ========================= */
$name = 'theme_apas_footerblock4heading';
$heading = get_string('footerblock', 'theme_apas') . ' 4 ';
$information = get_string('socialmediadesc', 'theme_apas');
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Enable block 4.
$name = 'theme_apas/footerb4_status';
$title = get_string('status', 'theme_apas');
$description = get_string('fblock_statusdesc', 'theme_apas');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Title.
$name = 'theme_apas/footerbtitle4';
$title = get_string('title', 'theme_apas');
$description = get_string('footerbtitledesc', 'theme_apas');
$default = 'Follow us';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Number of social items.
$name = 'theme_apas/numofsocialmedia';
$title = get_string('numofsocialmedia', 'theme_apas');
$description = get_string('numofsocialmediadesc', 'theme_apas');
$default = 4;
$choices = array_combine(range(1, 8), range(1, 8));
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

// Read chosen amount and define defaults.
$numofsocialmedia = get_config('theme_apas', 'numofsocialmedia');
for ($f = 1; $f <= $numofsocialmedia; $f++) {

    // Heading.
    $name = 'theme_apas_socialmeida' . $f;
    $heading = get_string('socialmeida', 'theme_apas', ['socialmedia' => $f]);
    $information = '';
    $setting = new admin_setting_heading($name, $heading, $information);
    $temp->add($setting);

    // Enabled.
    $name = 'theme_apas/socialmedia' . $f . '_status';
    $title = get_string('smediastatus', 'theme_apas');
    $description = get_string('smediastatus_desc', 'theme_apas');
    $default = 1;
    $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
    $temp->add($setting);

    // Icon class.
    $defaulticons = [
        1 => 'fa-brands fa-linkedin',
        2 => 'fa-brands fa-x-twitter',
        3 => 'fa-brands fa-facebook',
        4 => 'fa-solid fa-globe'
    ];
    $name = 'theme_apas/socialmedia' . $f . '_icon';
    $title = get_string('icon', 'theme_apas');
    $description = get_string('socialmediaicon_desc', 'theme_apas');
    $default = $defaulticons[$f] ?? 'fa-solid fa-globe';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $temp->add($setting);

    // URL.
    $defaulturls = [
        1 => 'https://www.linkedin.com/company/alogicdata', // change if needed
        2 => 'https://twitter.com/alogicdata',
        3 => 'https://facebook.com/alogicdata',
        4 => 'https://alogicdata.com'
    ];
    $name = 'theme_apas/socialmedia' . $f . '_url';
    $title = get_string('url', 'theme_apas');
    $description = get_string('socialmediaurl_desc', 'theme_apas');
    $default = $defaulturls[$f] ?? 'https://alogicdata.com';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $temp->add($setting);

    // Icon color (use brand primary by default).
    $name = 'theme_apas/socialmedia' . $f . '_iconcolor';
    $title = get_string('iconcolor', 'theme_apas');
    $description = get_string('socialmediaiconcolor_desc', 'theme_apas');
    $default = '#204070';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $temp->add($setting);
}

$settings->add($temp);
