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
 * Standalone page to load AutoProctor report for a specific attempt.
 *
 * @package    quizaccess_autoproctor
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

use core\exception\moodle_exception;

// Security: Require user to be logged in
require_login();

// Get attempt ID from query parameter
$attemptid = optional_param('ap_attempt_id', 0, PARAM_ALPHANUMEXT);
if (!$attemptid) {
    throw new moodle_exception('invalidaccess', 'quizaccess_autoproctor');
}

// Look up the session to get the quiz context for capability check
global $DB;
$session = $DB->get_record('quizaccess_autoproctor_sessions', ['test_attempt_id' => $attemptid]);
if (!$session) {
    throw new moodle_exception('invalidaccess', 'quizaccess_autoproctor');
}

// Get the quiz to find the course module
$quiz = $DB->get_record('quiz', ['id' => $session->quiz_id], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
$context = context_module::instance($cm->id);

// Set page context before capability check
$PAGE->set_context($context);

// Security: Require capability to view reports
require_capability('quizaccess/autoproctor:viewreport', $context);

$PAGE->set_title(get_string('reportpagetitle', 'quizaccess_autoproctor'));
$PAGE->set_url(new moodle_url(
    '/mod/quiz/accessrule/autoproctor/loadreport.php',
    ['ap_attempt_id' => $attemptid]
));

// Get client ID and secret from config.
$client_id = get_config('quizaccess_autoproctor', 'client_id');
$client_secret = get_config('quizaccess_autoproctor', 'client_secret');

// Compute hash server-side to avoid exposing client secret to browser.
$hashed_test_attempt_id = base64_encode(hash_hmac('sha256', $attemptid, $client_secret, true));

// Determine environment based on hostname.
$is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
$ap_domain = $is_localhost ? 'https://dev.autoproctor.co' : 'https://autoproctor.co';
$ap_env = $is_localhost ? 'development' : 'production';
$ap_entry_url = $is_localhost
    ? 'https://ap-development.s3.ap-south-1.amazonaws.com/ap-entry-moodle.js'
    : 'https://cdn.autoproctor.co/ap-entry-moodle.js';

// Load AutoProctor SDK using Moodle's proper JS loading mechanism.
$PAGE->requires->js(new moodle_url($ap_entry_url), true);

// Load autoproctor js module (will be called after SDK loads).
$PAGE->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'loadReport', [
    'clientId' => $client_id,
    'hashedTestAttemptId' => $hashed_test_attempt_id,
    'testAttemptId' => $attemptid,
    'apDomain' => $ap_domain,
    'apEnv' => $ap_env,
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('quizaccess_autoproctor/autoproctor', []);
echo $OUTPUT->footer();
