<?php
require_once('../../../../config.php');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_login();
require_sesskey();

$attemptid = required_param('attemptid', PARAM_INT);
$tracking_options = required_param('tracking_options', PARAM_RAW);
$test_attempt_id = required_param('test_attempt_id', PARAM_RAW);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        // Get quiz attempt record to get quiz id
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $quizid = $attempt->quiz;

        // See if the session already exists
        $session = $DB->get_record('quizaccess_autoproctor_sessions', ['quiz_attempt_id' => $attemptid], '*');
        if ($session) {
            echo json_encode(['success' => true, 'data' => $session, 'is_new_session' => false], JSON_PRETTY_PRINT);
            exit;
        }

        // Create the session object
        $session = new stdClass();
        $session->quiz_id = $quizid;
        $session->quiz_attempt_id = $attemptid;
        $session->test_attempt_id = $test_attempt_id;
        $session->started_at = time();
        $session->tracking_options = $tracking_options;
        $session->timecreated = $session->timemodified = time();

        // Insert the session into the database and return the session
        $DB->insert_record('quizaccess_autoproctor_sessions', $session);
        echo json_encode(['success' => true, 'data' => $session, 'is_new_session' => true], JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
