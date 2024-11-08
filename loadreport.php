<?php
require_once('../../../../config.php');

use core\exception\moodle_exception;

$PAGE->set_context(context_system::instance());

// Get attempt ID from query parameter
$attemptid = optional_param('ap_attempt_id', 0, PARAM_ALPHANUMEXT);
if (!$attemptid) {
    throw new moodle_exception('invalidaccess', 'quizaccess_autoproctor');
}

$PAGE->set_title('AutoProctor Test');
$PAGE->set_url(new moodle_url(
    '/mod/quiz/accessrule/autoproctor/loadreport.php', 
    ['ap_attempt_id' => $attemptid]
));

// get client ID and secret from config
$clientId = get_config('quizaccess_autoproctor', 'client_id');
$clientSecret = get_config('quizaccess_autoproctor', 'client_secret');

// Include the scripts and styles
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>';
echo '<script src="https://ap-development.s3.amazonaws.com/autoproctor.4.2.4.min.js"></script>';
echo '<link rel="stylesheet" href="https://ap-development.s3.amazonaws.com/autoproctor.4.2.4.min.css"/>';

// load autoproctor js module
$PAGE->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'loadReport', [
    'clientId' => $clientId,
    'clientSecret' => $clientSecret,
    'testAttemptId' => $attemptid,
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('quizaccess_autoproctor/autoproctor', []);
echo $OUTPUT->footer();
