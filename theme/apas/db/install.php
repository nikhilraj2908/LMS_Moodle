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
 * Define install function.
 *
 * @package    theme_apas
 * @copyright  2015 onwards AlogicData Development Team (https://alogicdata.com)
 * @author     AlogicData Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Theme_apas install function.
 *
 * @return void
 */
function xmldb_theme_apas_install() {
    global $CFG;

    if (method_exists('core_plugin_manager', 'reset_caches')) {
        core_plugin_manager::reset_caches();
    }

    $fs = get_file_storage();

        // Slider images.
        $i = 1;
        $fs = get_file_storage();
        $filerecord = new stdClass();
        $filerecord->component = 'theme_apas';
        $filerecord->contextid = context_system::instance()->id;
        $filerecord->userid = get_admin()->id;
        $filerecord->filearea = 'slide1image';
        $filerecord->filepath = '/';
        $filerecord->itemid = 0;
        $filerecord->filename = 'slide1image.jpg';
        $fs->create_file_from_pathname($filerecord, $CFG->dirroot . '/theme/apas/pix/home/slide1.jpg');

        // Logo image.
        $fs = get_file_storage();
        $filerecord = new stdClass();
        $filerecord->component = 'theme_apas';
        $filerecord->contextid = context_system::instance()->id;
        $filerecord->userid = get_admin()->id;
        $filerecord->filearea = 'logo';
        $filerecord->filepath = '/';
        $filerecord->itemid = 0;
        $filerecord->filename = 'logo.png';
        $fs->create_file_from_pathname($filerecord, $CFG->dirroot . '/theme/apas/pix/home/logo.png');

        // Footer logo image.
        $fs = get_file_storage();
        $filerecord = new stdClass();
        $filerecord->component = 'theme_apas';
        $filerecord->contextid = context_system::instance()->id;
        $filerecord->userid = get_admin()->id;
        $filerecord->filearea = 'footerlogo';
        $filerecord->filepath = '/';
        $filerecord->itemid = 0;
        $filerecord->filename = 'footerlogo.png';
        $fs->create_file_from_pathname($filerecord, $CFG->dirroot . '/theme/apas/pix/home/footerlogo.png');

        // Marketing spot image.
        $fs = get_file_storage();
        $filerecord = new stdClass();
        $filerecord->component = 'theme_apas';
        $filerecord->contextid = context_system::instance()->id;
        $filerecord->userid = get_admin()->id;
        $filerecord->filearea = 'mspotmedia';
        $filerecord->filepath = '/';
        $filerecord->itemid = 0;
        $filerecord->filename = 'mspotmedia.png';
        $fs->create_file_from_pathname($filerecord, $CFG->dirroot . '/theme/apas/pix/home/mspotmedia.png');
}
