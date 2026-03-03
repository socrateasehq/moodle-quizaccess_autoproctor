<?php

// Plugin information
$string['pluginname'] = 'AutoProctor Integration';

// Client credentials
$string['client_id'] = 'AutoProctor Client ID';
$string['client_id_desc'] = 'Enter your AutoProctor Client ID from the dashboard';
$string['client_secret'] = 'AutoProctor Client Secret';
$string['client_secret_desc'] = 'Enter your AutoProctor Client Secret from the dashboard';

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
$string['proctoringheader'] = 'You will need to grant access to some or all of the following to attempt this quiz:';
$string['proctoringconsent'] = 'I consent to granting access to the above permissions';
$string['proctoringpermissions'] = '<ul style="display: block; list-style-type: none; padding-left: 0; margin: auto;"><li>1. Screen</li><li>2. Microphone</li><li>3. Camera</ul>';

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
$string['tracking_group_idcard'] = 'ID Card Verification (<a href="https://www.autoproctor.co/pricing/" target="_blank">costs extra credits</a>)';

// Proctoring options - labels
$string['tracking_audio'] = 'Audio Detection';
$string['tracking_numHumans'] = 'Number of Humans';
$string['tracking_tabSwitch'] = 'Detect Tab Switch';
$string['tracking_disableCopyPaste'] = 'Disable Copy/Paste';
$string['tracking_captureSwitchedTab'] = 'Capture Switched Tab';
$string['tracking_photosAtRandom'] = 'Take photos at random';
$string['tracking_recordSession'] = 'Record screen session (<a href="https://www.autoproctor.co/pricing/" target="_blank">costs extra</a>)';
$string['tracking_detectMultipleScreens'] = 'Detect Multiple Screens';
$string['tracking_testTakerPhoto'] = 'Take photo of test taker';
$string['tracking_forceFullScreen'] = 'Force full screen';
$string['tracking_forceDesktop'] = 'Force desktop device';
$string['tracking_multiSessionAttempt'] = 'Multi-session detection';
$string['tracking_auxiliaryDevice'] = 'Auxiliary device (<a href="https://www.autoproctor.co/pricing/" target="_blank">costs extra</a>)';
$string['tracking_impersonation'] = 'Impersonation detection (<a href="https://www.autoproctor.co/pricing/" target="_blank">costs extra</a>)';
$string['tracking_idCardVerification_face'] = 'Verify face matches ID';
$string['tracking_idCardVerification_name'] = 'Verify name matches ID';
$string['tracking_idCardVerification_expiryDate'] = 'Check ID expiry date';

// Proctoring options - help text
$string['tracking_audio_help'] = 'Detect audio activity from the microphone during the test.';
$string['tracking_numHumans_help'] = 'Detect if no human or multiple people are visible in the camera frame.';
$string['tracking_tabSwitch_help'] = 'Detect when the student switches to another browser tab or application.';
$string['tracking_disableCopyPaste_help'] = 'Block copy (Ctrl+C / Cmd+C) and paste (Ctrl+V / Cmd+V) keyboard shortcuts during the test.';
$string['tracking_captureSwitchedTab_help'] = 'Take a screenshot when the student switches away from the test tab.';
$string['tracking_photosAtRandom_help'] = 'Capture webcam photos at random intervals during the test.';
$string['tracking_recordSession_help'] = 'Record the student\'s screen throughout the entire test session. Costs extra credits.';
$string['tracking_detectMultipleScreens_help'] = 'Detect if the student has multiple monitors connected.';
$string['tracking_testTakerPhoto_help'] = 'Capture a photo of the test taker at the beginning of the test for identity verification.';
$string['tracking_forceFullScreen_help'] = 'Force the browser into fullscreen mode during the test.';
$string['tracking_forceDesktop_help'] = 'Require the candidate to use a desktop or laptop device (no mobile devices).';
$string['tracking_multiSessionAttempt_help'] = 'Detect when the same test attempt is accessed from multiple devices, browsers, or IP addresses.';
$string['tracking_auxiliaryDevice_help'] = 'Require the candidate to pair their phone for 360-degree proctoring. Test content will not load without pairing. Costs extra credits.';
$string['tracking_impersonation_help'] = 'Compare random photos with the initial candidate photo to detect if a different person is taking the test. Costs extra credits.';
$string['tracking_idCardVerification_face_help'] = 'Compare the face on the ID card with the candidate\'s selfie. Automatically enables test taker photo.';
$string['tracking_idCardVerification_name_help'] = 'Extract and compare the name on the ID card with the candidate\'s registered name.';
$string['tracking_idCardVerification_expiryDate_help'] = 'Check whether the ID card has expired.';
