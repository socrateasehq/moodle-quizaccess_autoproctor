<?php

/**
 * @package autoproctor
 * @copyright 2024
 * @author AutoProctor <autoproctor.co>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @var stdClass $plugin
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2025022801;
$plugin->requires = 2022112800;
$plugin->component = 'quizaccess_autoproctor';
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = 'v0.1';
$plugin->dependencies = [
    'theme_boost' => ANY_VERSION,
];