<?php
defined('MOODLE_INTERNAL') || die();

$plugin = new stdClass();
$plugin->component = 'local_texttospeech'; // MUST match local/texttospeech
$plugin->version   = 2025090900;          // integer, < 2147483647
$plugin->requires  = 2018051700;          // Moodle 3.5+; raise if you run 4.x
$plugin->maturity  = MATURITY_RC;
$plugin->release   = '1.0.0';
