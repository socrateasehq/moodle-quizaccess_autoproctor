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
 * Backup subplugin class for quizaccess_autoproctor.
 *
 * @package    quizaccess_autoproctor
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/backup_mod_quiz_access_subplugin.class.php');

/**
 * Provides the backup steps for the autoproctor quiz access rule.
 *
 * @package    quizaccess_autoproctor
 * @category   backup
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_quizaccess_autoproctor_subplugin extends backup_mod_quiz_access_subplugin {

    /**
     * Returns the subplugin information to attach to the quiz element.
     *
     * @return backup_subplugin_element
     */
    protected function define_quiz_subplugin_structure() {
        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subpluginsettings = new backup_nested_element('autoproctor_settings', null, [
            'proctoring_enabled',
            'tracking_options',
            'timecreated',
            'timemodified',
        ]);

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginsettings);

        // Set source to populate the data.
        $subpluginsettings->set_source_table(
            'quizaccess_autoproctor',
            ['quiz_id' => backup::VAR_ACTIVITYID]
        );

        return $subplugin;
    }

    /**
     * Returns the subplugin information to attach to the quiz_attempt element.
     *
     * @return backup_subplugin_element
     */
    protected function define_attempt_subplugin_structure() {
        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subpluginsession = new backup_nested_element('autoproctor_session', null, [
            'test_attempt_id',
            'tracking_options',
            'started_at',
            'timecreated',
            'timemodified',
        ]);

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginsession);

        // Set source to populate the data.
        $subpluginsession->set_source_table(
            'quizaccess_autoproctor_sessions',
            ['quiz_attempt_id' => backup::VAR_PARENTID]
        );

        return $subplugin;
    }
}
