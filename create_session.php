<?php
/**
 * Creates a proctoring session for quiz attempts.
 *
 * @package    quizaccess_autoproctor
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

global $DB;

// Security: Require login and session key validation
require_login();
require_sesskey();

header("Content-Type: application/json; charset=UTF-8");

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate parameters
$attemptid = required_param('attemptid', PARAM_INT);
$test_attempt_id = required_param('test_attempt_id', PARAM_ALPHANUMEXT);
$tracking_options_raw = required_param('tracking_options', PARAM_RAW);

// Validate tracking_options is valid JSON
$tracking_options = json_decode($tracking_options_raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid tracking options format']);
    exit;
}

try {
    // Get quiz attempt record to get quiz id
    $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*');
    if (!$attempt) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid attempt']);
        exit;
    }
    $quizid = $attempt->quiz;

    // See if the session already exists
    $session = $DB->get_record('quizaccess_autoproctor_sessions', ['quiz_attempt_id' => $attemptid], '*');
    if ($session) {
        echo json_encode(['success' => true, 'data' => $session, 'is_new_session' => false]);
        exit;
    }

    // Create the session object
    $session = new stdClass();
    $session->quiz_id = $quizid;
    $session->quiz_attempt_id = $attemptid;
    $session->test_attempt_id = $test_attempt_id;
    $session->started_at = time();
    $session->tracking_options = $tracking_options_raw;
    $session->timecreated = time();
    $session->timemodified = time();

    // Insert the session into the database
    $session->id = $DB->insert_record('quizaccess_autoproctor_sessions', $session);
    echo json_encode(['success' => true, 'data' => $session, 'is_new_session' => true]);

} catch (dml_exception $e) {
    // Database errors - don't expose details
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    error_log('[AutoProctor] Session creation database error: ' . $e->getMessage());

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
    error_log('[AutoProctor] Session creation error: ' . $e->getMessage());
}
