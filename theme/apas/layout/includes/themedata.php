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
 * Theme layout data
 * @package    theme_apas
 * @copyright  2015 onwards AlogicData Development Team (https://alogicdata.com)
 * @author    AlogicData Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__) .'/footer.php');
require_once($CFG->dirroot.'/theme/apas/lib.php');

$logourl = theme_apas_get_logo_url('header');
$phoneno = theme_apas_get_setting('phoneno');
$emailid = theme_apas_get_setting('emailid');
$themestyleheader = theme_apas_get_setting('themestyleheader');
$navstyle = theme_apas_get_setting('navstyle');
// NEW: mobile logo URL (if uploaded).
$smalllogourl = $PAGE->theme->setting_file_url('smalllogo', 'smalllogo');



switch ($navstyle) {
    case LOGO:
        $showlogo = true;
        $showsitename = false;
        break;
    case SITENAME:
        $showsitename = true;
        $showlogo = false;
        break;
    case LOGOANDSITENAME:
        $showsitename = true;
        $showlogo = true;
        break;
}

$custommenu = $OUTPUT->custom_menu();
if ($custommenu == "") {
    $navbarclass = "navbar-toggler d-lg-none nocontent-navbar";
} else {
    $navbarclass = "navbar-toggler d-lg-none";
}

$templatecontext = [
    "logourl" => $logourl,
    "navbarclass" => $navbarclass,
    "themestyleheader" => $themestyleheader,
    'showsitename' => $showsitename,
    'showlogo' => $showlogo,
    "smalllogourl" => $smalllogourl,   ///////////added for small logo
];
$templatecontext += footer();
