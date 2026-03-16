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
 * Restore subplugin class for quizaccess_autoproctor.
 *
 * @package    quizaccess_autoproctor
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/backup/moodle2/restore_mod_quiz_access_subplugin.class.php');

/**
 * Provides the restore steps for the autoproctor quiz access rule.
 *
 * @package    quizaccess_autoproctor
 * @category   backup
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_quizaccess_autoproctor_subplugin extends restore_mod_quiz_access_subplugin {
    /**
     * Returns the paths to be handled by the subplugin at quiz level.
     *
     * @return array
     */
    protected function define_quiz_subplugin_structure() {
        $paths = [];

        // Add path for autoproctor settings.
        $elepath = $this->get_pathfor('/autoproctor_settings');
        $paths[] = new restore_path_element('quizaccess_autoproctor_settings', $elepath);

        return $paths;
    }

    /**
     * Returns the paths to be handled by the subplugin at attempt level.
     *
     * @return array
     */
    protected function define_attempt_subplugin_structure() {
        $paths = [];

        // Add path for autoproctor sessions.
        $elepath = $this->get_pathfor('/autoproctor_session');
        $paths[] = new restore_path_element('quizaccess_autoproctor_session', $elepath);

        return $paths;
    }

    /**
     * Process the autoproctor settings data.
     *
     * @param array $data The data from backup file.
     */
    public function process_quizaccess_autoproctor_settings($data) {
        global $DB;

        $data = (object)$data;
        $data->quiz_id = $this->get_new_parentid('quiz');

        // Check if settings already exist for this quiz.
        $existing = $DB->get_record('quizaccess_autoproctor', ['quiz_id' => $data->quiz_id]);
        if ($existing) {
            $data->id = $existing->id;
            $data->timemodified = time();
            $DB->update_record('quizaccess_autoproctor', $data);
        } else {
            $DB->insert_record('quizaccess_autoproctor', $data);
        }
    }

    /**
     * Process the autoproctor session data.
     *
     * @param array $data The data from backup file.
     */
    public function process_quizaccess_autoproctor_session($data) {
        global $DB;

        $data = (object)$data;

        // Get the new quiz attempt ID from the mapping.
        $newattemptid = $this->get_new_parentid('quiz_attempt');
        if (!$newattemptid) {
            return;
        }

        $data->quiz_attempt_id = $newattemptid;

        // Get the new quiz ID from the mapping.
        $data->quiz_id = $this->get_mappingid('quiz', $this->task->get_old_moduleid());
        if (!$data->quiz_id) {
            // Try to get it from the quiz attempt.
            $attempt = $DB->get_record('quiz_attempts', ['id' => $newattemptid], 'quiz');
            if ($attempt) {
                $data->quiz_id = $attempt->quiz;
            } else {
                return;
            }
        }

        // Check if session already exists for this attempt.
        $existing = $DB->get_record('quizaccess_autoproctor_sessions', ['quiz_attempt_id' => $newattemptid]);
        if (!$existing) {
            $DB->insert_record('quizaccess_autoproctor_sessions', $data);
        }
    }
}
