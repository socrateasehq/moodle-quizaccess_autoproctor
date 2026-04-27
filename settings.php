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
 * Administration settings for AutoProctor quiz access rule.
 *
 * @package    quizaccess_autoproctor
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Info-only setting that renders on the upgrade settings page.
 *
 * Moodle's upgradesettings.php only shows settings where get_setting() returns null.
 * admin_setting_heading and admin_setting_description always return true, so they
 * are filtered out. This class returns null until the settings are first saved,
 * allowing the info text to appear during initial plugin installation.
 */
if (!class_exists('quizaccess_autoproctor_setting_info')) {
class quizaccess_autoproctor_setting_info extends admin_setting {
    public function __construct($name, $description) {
        parent::__construct($name, '', $description, '');
    }

    public function get_setting() {
        return $this->config_read($this->name);
    }

    public function get_defaultsetting() {
        return '';
    }

    public function write_setting($data) {
        $this->config_write($this->name, '1');
        return '';
    }

    public function output_html($data, $query = '') {
        $html = '<div class="form-item row" id="admin-' . $this->name . '">';
        $html .= '<div class="form-label col-sm-3"></div>';
        $html .= '<div class="form-setting col-sm-9">';
        $html .= '<div class="form-description mt-3 mb-3">' . highlight($query, $this->description) . '</div>';
        $html .= '<input type="hidden" name="' . $this->get_full_name() . '" value="1" />';
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
}
}

if ($hassiteconfig) {
    $settings = new admin_settingpage('quizaccess_autoproctor', get_string('pluginname', 'quizaccess_autoproctor'));
    $ADMIN->add('modsettings', $settings);

    // Credentials info - custom class so it renders on upgradesettings.php during first install.
    $settings->add(new quizaccess_autoproctor_setting_info(
        'quizaccess_autoproctor/credentials_info',
        get_string('credentials_info', 'quizaccess_autoproctor')
    ));

    // AutoProctor API Settings
    $settings->add(new admin_setting_configtext(
        'quizaccess_autoproctor/client_id',
        get_string('client_id', 'quizaccess_autoproctor'),
        get_string('client_id_desc', 'quizaccess_autoproctor'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'quizaccess_autoproctor/client_secret',
        get_string('client_secret', 'quizaccess_autoproctor'),
        get_string('client_secret_desc', 'quizaccess_autoproctor'),
        '',
    ));

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'quizaccess_autoproctor/enable_by_default',
            get_string('enable_by_default', 'quizaccess_autoproctor'),
            get_string('enable_by_default_desc', 'quizaccess_autoproctor'),
            0
        ));
    }
} 