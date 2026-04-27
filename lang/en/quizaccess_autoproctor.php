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
 * Language strings for the AutoProctor quiz access rule.
 *
 * @package    quizaccess_autoproctor
 * @copyright  2024 AutoProctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin information
$string['pluginname'] = 'AutoProctor Integration';

// Client credentials
$string['credentials_info'] = 'Don\'t have credentials yet? <a href="https://www.autoproctor.co/developers/register/" target="_blank">Get your Client ID and Secret here</a>.';
$string['client_id'] = 'AutoProctor Client ID';
$string['client_id_desc'] = 'Enter your AutoProctor Client ID. Don\'t have credentials yet? <a href="https://www.autoproctor.co/developers/register/" target="_blank">Get your Client ID and Secret here</a>.';
$string['client_secret'] = 'AutoProctor Client Secret';
$string['client_secret_desc'] = 'Enter your AutoProctor Client Secret.';

// Proctoring requirements and status
$string['proctoring_required'] = 'This quiz requires proctoring';
$string['credentials_not_set'] = 'AutoProctor key-pair (credentials) are not set. If you dont have them, you can get your key-pair <a href="https://autoproctor.co/developers/register/" target="_blank">here</a>.';
$string['start_proctoring'] = 'Start Proctored Session';
$string['proctoring_not_ready'] = 'AutoProctor is not ready yet. You can only attempt the quiz when AutoProctor setup is complete.';

// Default settings
$string['enable_by_default'] = 'Enable AutoProctor by default';
$string['enable_by_default_desc'] = 'If enabled, AutoProctor will be enabled for all new quizzes by default';

// Help and permissions
$string['autoproctor_desc_headsup'] = 'This quiz is using AutoProctor to proctor the test. You can only attempt the quiz when AutoProctor is running.';
$string['requireautoproctor_help'] = 'If enabled, students can only attempt the quiz when AutoProctor is running';
$string['proctoringheader'] = 'You will need to grant access to the following to attempt this quiz:';
$string['proctoringconsent'] = 'I consent to granting access to the above permissions';
$string['permission_screen'] = 'Screen';
$string['permission_microphone'] = 'Microphone';
$string['permission_camera'] = 'Camera';

// Results and reporting
$string['autoproctorresults'] = 'View AutoProctor Results';
$string['autoproctorresultslink'] = 'https://autoproctor.co/test-admin/developers/results/';
$string['autoproctor:viewreport'] = 'View AutoProctor Results';
$string['viewattemptreport'] = 'View Proctoring Report';
$string['viewattemptreportlink'] = 'https://www.autoproctor.co/test-admin/developers/test-attempts/';

// AutoProctor settings
$string['autoproctorsettings'] = 'AutoProctor Settings';
$string['requireautoproctor'] = 'Turn AutoProctor On';
$string['requireautoproctor_desc'] = 'If enabled, students can only attempt the quiz when AutoProctor is running';

// Tracking groups
$string['tracking_group_camera'] = 'Camera Settings';
$string['tracking_group_activity'] = 'Activity Tracking';
$string['tracking_group_screen'] = 'Screen Tracking';
$string['tracking_group_security'] = 'Advanced Security';

// Proctoring options - labels
$string['tracking_audio'] = 'Detect Audio';
$string['tracking_numHumans'] = 'Detect Face';
$string['tracking_tabSwitch'] = 'Detect Switched Tab';
$string['tracking_disableCopyPaste'] = 'Disable Copy/Paste';
$string['tracking_captureSwitchedTab'] = 'Switched Tab Screenshot';
$string['tracking_photosAtRandom'] = 'Take Random Photos';
$string['tracking_recordSession'] = 'Record User Session (<a href="https://www.autoproctor.co/pricing/" target="_blank">costs extra</a>)';
$string['tracking_detectMultipleScreens'] = 'Detect Multiple Monitors';
$string['tracking_testTakerPhoto'] = 'Capture Photo Before Start Test';
$string['tracking_forceFullScreen'] = 'Enforce Full Screen';
$string['tracking_forceDesktop'] = 'Enforce Desktop';
$string['tracking_multiSessionAttempt'] = 'Multi-session detection';
$string['tracking_impersonation'] = 'Impersonation detection (<a href="https://www.autoproctor.co/pricing/" target="_blank">costs extra</a>)';
$string['tracking_idCardVerification'] = 'ID Card Verification (<a href="https://www.autoproctor.co/pricing/" target="_blank">costs extra</a>)';

// Proctoring options - help text
$string['tracking_audio_help'] = 'Record noise and audio cues in the background.';
$string['tracking_numHumans_help'] = 'Capture a photo if the camera detects no faces or multiple faces.';
$string['tracking_tabSwitch_help'] = 'When user switches to a different tab/application, we detect this.';
$string['tracking_disableCopyPaste_help'] = 'Block copy (Ctrl+C / Cmd+C) and paste (Ctrl+V / Cmd+V) keyboard shortcuts during the test.';
$string['tracking_captureSwitchedTab_help'] = 'When user switches to a different tab/application, capture screenshot (Works only on supported browsers).';
$string['tracking_photosAtRandom_help'] = 'Capture a few photos of the candidate throughout the test.';
$string['tracking_recordSession_help'] = 'Records the candidate\'s screens and actions (mouse clicks, keyboard typing), as they attempt the test.';
$string['tracking_detectMultipleScreens_help'] = 'Detect if the candidate is connected to more than one monitor (Works only on supported browsers).';
$string['tracking_testTakerPhoto_help'] = 'Take a photo of the candidate\'s face before every test starts. For the first test, it is enabled by default.';
$string['tracking_forceFullScreen_help'] = 'Candidates cannot take the test without entering full-screen mode when this is enabled. Recommended to avoid tab switching and cheating.';
$string['tracking_forceDesktop_help'] = 'Useful if you are conducting coding challenges, etc which requires the user to have a large screen to work with.';
$string['tracking_multiSessionAttempt_help'] = 'Detect when the same test attempt is accessed from multiple devices, browsers, or IP addresses.';
$string['tracking_impersonation_help'] = 'Compare random photos with the initial candidate photo to detect if a different person is taking the test. Costs extra credits.';
$string['tracking_idCardVerification_help'] = 'Verify the candidate\'s identity by comparing their face with their ID card photo, extracting their name, and checking ID expiry date. Costs extra credits.';

// Privacy API strings
$string['privacy:metadata:quizaccess_autoproctor_sessions'] = 'Stores proctoring session data for quiz attempts.';
$string['privacy:metadata:quizaccess_autoproctor_sessions:quiz_id'] = 'The ID of the quiz being proctored.';
$string['privacy:metadata:quizaccess_autoproctor_sessions:quiz_attempt_id'] = 'The ID of the quiz attempt being proctored.';
$string['privacy:metadata:quizaccess_autoproctor_sessions:test_attempt_id'] = 'The unique AutoProctor test attempt identifier.';
$string['privacy:metadata:quizaccess_autoproctor_sessions:tracking_options'] = 'JSON configuration of which proctoring features were enabled for this attempt.';
$string['privacy:metadata:quizaccess_autoproctor_sessions:started_at'] = 'The timestamp when the proctoring session started.';
$string['privacy:metadata:quizaccess_autoproctor_sessions:timecreated'] = 'The timestamp when the session record was created.';
$string['privacy:metadata:quizaccess_autoproctor_sessions:timemodified'] = 'The timestamp when the session record was last modified.';
$string['privacy:metadata:autoproctor_external'] = 'Proctoring data is sent to and stored on external AutoProctor servers for analysis.';
$string['privacy:metadata:autoproctor_external:webcam_photos'] = 'Photos captured from the user\'s webcam during the quiz attempt.';
$string['privacy:metadata:autoproctor_external:audio_recordings'] = 'Audio captured from the user\'s microphone during the quiz attempt.';
$string['privacy:metadata:autoproctor_external:screen_recordings'] = 'Screen recordings captured during the quiz attempt.';
$string['privacy:metadata:autoproctor_external:test_attempt_id'] = 'The unique identifier linking this data to the quiz attempt.';
$string['privacy:metadata:autoproctor_external:tracking_options'] = 'Configuration of which proctoring features were active.';
$string['privacy:externaldatanote'] = 'Additional proctoring data (webcam photos, audio, screen recordings) is stored on external AutoProctor servers and must be requested directly from AutoProctor.';

// Error messages
$string['invalidaccess'] = 'Invalid or missing proctoring session. Please start your quiz attempt again.';
$string['mustacceptproctoring'] = 'You must accept the proctoring requirements to attempt this quiz.';
$string['error_methodnotallowed'] = 'Method not allowed';
$string['error_invalidtrackingoptions'] = 'Invalid tracking options format';
$string['error_invalidattempt'] = 'Invalid attempt';
$string['error_accessdenied'] = 'Access denied';
$string['error_database'] = 'Database error occurred';
$string['error_general'] = 'An error occurred';

// Page titles
$string['reportpagetitle'] = 'AutoProctor Report';

// Template strings
$string['report_title'] = 'AutoProctor';
$string['report_welcome'] = 'Welcome to the AutoProctor Plugin! AutoProctor is an automated tool which ensures that users do not cheat on exams while taking online exams. It tracks their environment and activities using their camera, mic and screen they are sharing. Based on these violations, it calculates a Trust Score and also generates a report of the violations.';
$string['loader_setup'] = 'Setting Up AutoProctor. If this process is very slow, it means you need better internet connectivity.';
$string['loader_wait'] = 'Please wait for up to a minute for the system to be set up. If it still doesn\'t load,';
$string['loader_clickhere'] = 'click here';
$string['loader_followsteps'] = 'for some steps you can follow.';
