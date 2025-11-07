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
 * Admin settings configuration for home page slider section.
 *
 * @package    theme_apas

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

// Home page slider.
$temp = new admin_settingpage('theme_apas_slideshow', get_string('slideshowheading', 'theme_apas'));
$temp->add(new admin_setting_heading('theme_apas_slideshow', get_string('slideshowheadingsub', 'theme_apas'),
format_text(get_string('slideshowdesc', 'theme_apas'), FORMAT_MARKDOWN)));

// Enable or disable option for slider show / hide in the home page.
$name = 'theme_apas/toggleslideshow';
$title = get_string('toggleslideshow', 'theme_apas');
$description = get_string('toggleslideshowdesc', 'theme_apas');
$default = YES;
$choices = [
    YES => get_string('yes'),
    NO => get_string('no'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

// Enable or diable option for home page slider auto scroll.
$name = 'theme_apas/autoslideshow';
$title = get_string('autoslideshow', 'theme_apas');
$description = get_string('autoslideshowdesc', 'theme_apas');
$default = YES;
$choices = [
    YES => get_string('yes'),
    NO => get_string('no'),
];
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$temp->add($setting);

// Give interval time for home page slider.
$name = 'theme_apas/slideinterval';
$title = get_string('slideinterval', 'theme_apas');
$description = get_string('slideintervaldesc', 'theme_apas');
$default = 3500;
$setting = new admin_setting_configtext($name, $title, $description, $default, PARAM_INT);
$temp->add($setting);

// Select the overlay opacity value for the home page slider.
$name = 'theme_apas/slideOverlay';
$title = get_string('slideOverlay', 'theme_apas');
$description = get_string('slideOverlay_desc', 'theme_apas');
$opacity = [];
$opacity = array_combine(range(0, 1, 0.1 ), range(0, 1, 0.1 ));
$setting = new admin_setting_configselect($name, $title, $description, '0.4', $opacity);
$setting->set_updatedcallback('theme_reset_all_caches');
$temp->add($setting);

// Select the number of slides show in the homepage.
$name = 'theme_apas/numberofslides';
$title = get_string('numberofslides', 'theme_apas');
$description = get_string('numberofslides_desc', 'theme_apas');
$default = 3;
$choices = [
    1 => '1',
    2 => '2',
    3 => '3',
    4 => '4',
    5 => '5',
    6 => '6',
    7 => '7',
    8 => '8',
    9 => '9',
    10 => '10',
    11 => '11',
    12 => '12',
];
$temp->add(new admin_setting_configselect($name, $title, $description, $default, $choices));

// Slideshow settings.
$numberofslides = get_config('theme_apas', 'numberofslides');
for ($i = 1; $i <= $numberofslides; $i++) {

    // This is the descriptor for Slide.
    $name = 'theme_apas/slide' . $i . 'info';
    $heading = get_string('slideno', 'theme_apas', ['slide' => $i]);
    $information = get_string('slidenodesc', 'theme_apas', ['slide' => $i]);
    $setting = new admin_setting_heading($name, $heading, $information);
    $temp->add($setting);

    // Enable or disable option for slide show.
    $name = 'theme_apas/slide' . $i .'status';
    $title = get_string('slideStatus', 'theme_apas', ['slide' => $i]);
    $description = get_string('slideStatus_desc', 'theme_apas', ['slide' => $i]);
    $default = YES;
    $choices = [
        YES => get_string('enable', 'theme_apas'),
        NO => get_string('disable', 'theme_apas'),
    ];
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $temp->add($setting);

    // Slider image uploaded option.
    $name = 'theme_apas/slide' . $i . 'image';
    $title = get_string('slideimage', 'theme_apas');
    $description = get_string('slideimagedesc', 'theme_apas');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'slide' . $i . 'image');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    // Enable or disable option for SlideShow content.
    $name = 'theme_apas/slide' . $i .'contentstatus';
    $title = get_string('slidecontentstatus', 'theme_apas', ['slide' => $i]);
    $description = get_string('slidecontentstatus_desc', 'theme_apas', ['slide' => $i]);
    $default = YES;
    $setting = new admin_setting_configcheckbox($name, $title, $description, $default);
    $temp->add($setting);

    // Give a caption for the home page slider.
    $name = 'theme_apas/slide' . $i . 'caption';
    $title = get_string('slidecaption', 'theme_apas');
    $description = get_string('slidecaptiondesc', 'theme_apas');
    $default = get_string('slidecaptiondefault', 'theme_apas', ['slideno' => sprintf('%02d', $i)]);
    $setting = new admin_setting_configtext($name, $title, $description, $default, PARAM_TEXT);
    $temp->add($setting);

    // Give a description for the home page slider.
    $name = 'theme_apas/slide' . $i . 'desc';
    $title = get_string('slidedesc', 'theme_apas');
    $description = get_string('slidedesctext', 'theme_apas');
    $default = get_string('slidedescdefault', 'theme_apas');
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $temp->add($setting);

    // Give a text for the home page slider button.
    $name = 'theme_apas/slide' . $i . 'btntext';
    $title = get_string('slidebtntext', 'theme_apas');
    $description = get_string('slidebtntext_desc', 'theme_apas');
    $default = 'lang:knowmore';
    $setting = new admin_setting_configtext($name, $title, $description, $default, PARAM_TEXT);
    $temp->add($setting);

    // Give a url for the home page slider button.
    $name = 'theme_apas/slide' . $i . 'btnurl';
    $title = get_string('slidebtnlink', 'theme_apas');
    $description = get_string('slidebtnlink_desc', 'theme_apas');
    $default = 'http://www.example.com/';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $temp->add($setting);

    // Select the target of the button for the home page slider.
    $name = 'theme_apas/slide' . $i . 'btntarget';
    $title = get_string('slidebtntarget', 'theme_apas');
    $description = get_string('slidebtntarget_desc', 'theme_apas', ['slide' => $i]);
    $default = NEWWINDOW;
    $choices = [
        SAMEWINDOW => get_string('sameWindow', 'theme_apas'),
        NEWWINDOW => get_string('newWindow', 'theme_apas'),
    ];
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $temp->add($setting);

    // Give a content width for the home page slider.
    $name = 'theme_apas/slide' . $i . 'contFullwidth';
    $title = get_string('slideCont_full', 'theme_apas');
    $description = get_string('slideCont_fulldesc', 'theme_apas');
    $default = "50";
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    // Select the content position option for the home page slider.
    $name = 'theme_apas/slide' . $i . 'contentPosition';
    $title = get_string('slidecontent', 'theme_apas', ['slide' => $i]);
    $description = get_string('slidecontentdesc', 'theme_apas');
    $default = 'centerRight';
    $choices = [
        "topLeft" => get_string("topLeft", "theme_apas"),
        "topCenter" => get_string("topCenter", "theme_apas"),
        "topRight" => get_string("topRight", "theme_apas"),
        "centerLeft" => get_string("centerLeft", "theme_apas"),
        "center" => get_string("center", "theme_apas"),
        "centerRight" => get_string("centerRight", "theme_apas"),
        "bottomLeft" => get_string("bottomLeft", "theme_apas"),
        "bottomCenter" => get_string("bottomCenter", "theme_apas"),
        "bottomRight" => get_string("bottomRight", "theme_apas"),
        ];
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $temp->add($setting);
}
/* Slideshow Settings End*/
$settings->add($temp);
