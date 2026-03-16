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

namespace quizaccess_autoproctor\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use dml_exception;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/**
 * External function to create a proctoring session for quiz attempts.
 *
 * @package    quizaccess_autoproctor
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_session extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'The quiz attempt ID'),
            'test_attempt_id' => new external_value(PARAM_ALPHANUMEXT, 'The AutoProctor test attempt ID'),
            'tracking_options' => new external_value(PARAM_RAW, 'JSON string of tracking options'),
        ]);
    }

    /**
     * Creates a proctoring session for the given quiz attempt.
     *
     * @param int $attemptid The quiz attempt ID
     * @param string $testattemptid The AutoProctor test attempt ID
     * @param string $trackingoptions JSON string of tracking options
     * @return array Result array with success status and data
     */
    public static function execute(int $attemptid, string $testattemptid, string $trackingoptions): array {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'attemptid' => $attemptid,
            'test_attempt_id' => $testattemptid,
            'tracking_options' => $trackingoptions,
        ]);

        $attemptid = $params['attemptid'];
        $testattemptid = $params['test_attempt_id'];
        $trackingoptions = $params['tracking_options'];

        // Validate tracking_options is valid JSON.
        $trackingopts = json_decode($trackingoptions, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => get_string('error_invalidtrackingoptions', 'quizaccess_autoproctor'),
                'session_id' => 0,
                'is_new_session' => false,
            ];
        }

        try {
            // Get quiz attempt record to get quiz id.
            $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*');
            if (!$attempt) {
                return [
                    'success' => false,
                    'error' => get_string('error_invalidattempt', 'quizaccess_autoproctor'),
                    'session_id' => 0,
                    'is_new_session' => false,
                ];
            }

            // Security: Verify the current user owns this attempt.
            if ($attempt->userid != $USER->id) {
                return [
                    'success' => false,
                    'error' => get_string('error_accessdenied', 'quizaccess_autoproctor'),
                    'session_id' => 0,
                    'is_new_session' => false,
                ];
            }

            // Validate context.
            $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            self::validate_context($context);

            $quizid = $attempt->quiz;

            // See if the session already exists.
            $session = $DB->get_record('quizaccess_autoproctor_sessions', ['quiz_attempt_id' => $attemptid], '*');
            if ($session) {
                return [
                    'success' => true,
                    'error' => '',
                    'session_id' => (int)$session->id,
                    'is_new_session' => false,
                ];
            }

            // Create the session object.
            $session = new \stdClass();
            $session->quiz_id = $quizid;
            $session->quiz_attempt_id = $attemptid;
            $session->test_attempt_id = $testattemptid;
            $session->started_at = time();
            $session->tracking_options = $trackingoptions;
            $session->timecreated = time();
            $session->timemodified = time();

            // Insert the session into the database.
            $session->id = $DB->insert_record('quizaccess_autoproctor_sessions', $session);

            return [
                'success' => true,
                'error' => '',
                'session_id' => (int)$session->id,
                'is_new_session' => true,
            ];

        } catch (dml_exception $e) {
            // Database errors - don't expose details.
            debugging('[AutoProctor] Session creation database error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => false,
                'error' => get_string('error_database', 'quizaccess_autoproctor'),
                'session_id' => 0,
                'is_new_session' => false,
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'error' => new external_value(PARAM_TEXT, 'Error message if not successful'),
            'session_id' => new external_value(PARAM_INT, 'The session ID if successful'),
            'is_new_session' => new external_value(PARAM_BOOL, 'Whether a new session was created'),
        ]);
    }
}
