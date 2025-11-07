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
 * Admin settings configuration for jumbotron section.
 *
 * @package    theme_apas
 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

// Jumbotron.
$temp = new admin_settingpage('theme_apas_jumbotron', get_string('jumbotronheading', 'theme_apas'));

// Jumbotron heading.
$name = 'theme_apas_jumbotronheading';
$heading = get_string('jumbotronheading', 'theme_apas');
$information = '';
$setting = new admin_setting_heading($name, $heading, $information);
$temp->add($setting);

// Jumbotron Enable or disable option.
$name = 'theme_apas/jumbotronstatus';
$title = get_string('status', 'theme_apas');
$description = get_string('statusdesc', 'theme_apas');
$default = NO;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$temp->add($setting);

// Jumbotron Title.
$name = 'theme_apas/jumbotrontitle';
$title = get_string('title', 'theme_apas');
$description = get_string('titledesc', 'theme_apas');
$default = 'lang:learnanytime';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$temp->add($setting);

// Jumbotron Description.
$name = 'theme_apas/jumbotrondesc';
$title = get_string('description', 'theme_apas');
$description = get_string('description_desc', 'theme_apas');
$default = 'lang:learnanytimedesc';
$setting = new admin_setting_configtextarea($name, $title, $description, $default, PARAM_TEXT);
$temp->add($setting);

// Jumbotron button text.
$name = 'theme_apas/jumbotronbtntext';
$title = get_string('buttontxt', 'theme_apas');
$description = get_string('jumbotronbtntext_desc', 'theme_apas');
$default = 'lang:viewallcourses';
$setting = new admin_setting_configtext($name, $title, $description, $default, PARAM_TEXT);
$temp->add($setting);

// Jumbotron button link.
$name = 'theme_apas/jumbotronbtnlink';
$title = get_string('buttonlink', 'theme_apas');
$description = get_string('jumbotronbtnlink_desc', 'theme_apas');
$default = 'http://www.example.com/';
$setting = new admin_setting_configtext($name, $title, $description, $default, PARAM_URL);
$temp->add($setting);

// Jumbotron button target.
$name = 'theme_apas/jumbotronbtntarget';
$title = get_string('buttontarget', 'theme_apas');
$description = get_string('jumbotronbtntarget_desc', 'theme_apas');
$default = NEWWINDOW;
$choices = [
    SAMEWINDOW => get_string('sameWindow', 'theme_apas'),
    NEWWINDOW => get_string('newWindow', 'theme_apas'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);
$settings->add($temp);
