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
 * A rule controlling the AutoProctor.
 *
 * @package   quizaccess_autoproctor
 * @copyright 2024 AutoProctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Compatibility check for Moodle versions.
if (class_exists('\mod_quiz\local\access_rule_base')) {
    class_alias('\mod_quiz\local\access_rule_base', '\quizaccess_autoproctor_parent_class_alias');
    class_alias('\mod_quiz\form\preflight_check_form', '\quizaccess_autoproctor_preflight_form_alias');
    class_alias('\mod_quiz\quiz_settings', '\quizaccess_autoproctor_quiz_settings_class_alias');
} else {
    require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
    class_alias('\quiz_access_rule_base', '\quizaccess_autoproctor_parent_class_alias');
    class_alias('\mod_quiz_preflight_check_form', '\quizaccess_autoproctor_preflight_form_alias');
    class_alias('\quiz', '\quizaccess_autoproctor_quiz_settings_class_alias');
}

/**
 * Quiz access rule for AutoProctor proctoring.
 *
 * @package   quizaccess_autoproctor
 * @copyright 2024 AutoProctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_autoproctor extends quizaccess_autoproctor_parent_class_alias {
    /** @var string CDN URL for production environment. */
    private const AP_CDN_PRODUCTION = 'https://cdn.autoproctor.co/ap-entry-moodle.js';

    /** @var string CDN URL for development environment. */
    private const AP_CDN_DEVELOPMENT = 'https://ap-development.s3.ap-south-1.amazonaws.com/ap-entry-moodle.js';

    /** @var string Domain URL for production environment. */
    private const AP_DOMAIN_PRODUCTION = 'https://www.autoproctor.co';

    /** @var string Domain URL for development environment. */
    private const AP_DOMAIN_DEVELOPMENT = 'https://dev.autoproctor.co';

    /** @var quizaccess_autoproctor_quiz_settings_class_alias The quiz object. */
    protected $quizobj;

    /** @var string The test attempt ID. */
    protected $testattemptid;

    /**
     * Constructor.
     *
     * @param quizaccess_autoproctor_quiz_settings_class_alias $quizobj The quiz object.
     * @param int $timenow The current time.
     */
    public function __construct($quizobj, $timenow) {
        parent::__construct($quizobj, $timenow);
        $this->quizobj = $quizobj;
    }

    /**
     * Factory method to create the rule if applicable.
     *
     * @param quizaccess_autoproctor_quiz_settings_class_alias $quizobj The quiz object.
     * @param int $timenow The current time.
     * @param bool $canignoretimelimits Whether time limits can be ignored.
     * @return quizaccess_autoproctor|null The rule instance or null.
     */
    public static function make(quizaccess_autoproctor_quiz_settings_class_alias $quizobj, $timenow, $canignoretimelimits) {
        $quizid = $quizobj->get_quiz()->id;
        $proctoringenabled = self::get_ap_settings($quizid)->proctoring_enabled;
        if (empty($proctoringenabled)) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Add any fields to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform The quiz form.
     * @param MoodleQuickForm $mform The form object.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        // Get the current autoproctor settings for the quiz.
        $apsettings = self::get_ap_settings($quizform->get_current()->id);

        // Create header for tracking options.
        $mform->addElement('header', 'autoproctorsettings', get_string('autoproctorsettings', 'quizaccess_autoproctor'));

        // Add main AutoProctor toggle.
        $mform->addElement(
            'selectyesno',
            'requireautoproctor',
            get_string('requireautoproctor', 'quizaccess_autoproctor')
        );
        $mform->addHelpButton(
            'requireautoproctor',
            'requireautoproctor',
            'quizaccess_autoproctor'
        );
        $mform->setDefault(
            'requireautoproctor',
            $apsettings->proctoring_enabled ?? get_config(
                'quizaccess_autoproctor',
                'enable_by_default'
            )
        );

        // Add nested tracking options (boolean options).
        $trackingoptions = [
            'activity' => [
                'audio' => get_string('tracking_audio', 'quizaccess_autoproctor'),
                'numHumans' => get_string('tracking_numHumans', 'quizaccess_autoproctor'),
                'disableCopyPaste' => get_string('tracking_disableCopyPaste', 'quizaccess_autoproctor'),
                'multiSessionAttempt' => get_string('tracking_multiSessionAttempt', 'quizaccess_autoproctor'),
            ],
            'camera' => [
                'testTakerPhoto' => get_string('tracking_testTakerPhoto', 'quizaccess_autoproctor'),
                'photosAtRandom' => get_string('tracking_photosAtRandom', 'quizaccess_autoproctor'),
                'impersonation' => get_string('tracking_impersonation', 'quizaccess_autoproctor'),
                'idCardVerification' => get_string('tracking_idCardVerification', 'quizaccess_autoproctor'),
            ],
            'screen' => [
                'tabSwitch' => get_string('tracking_tabSwitch', 'quizaccess_autoproctor'),
                'captureSwitchedTab' => get_string('tracking_captureSwitchedTab', 'quizaccess_autoproctor'),
                'detectMultipleScreens' => get_string('tracking_detectMultipleScreens', 'quizaccess_autoproctor'),
                'forceFullScreen' => get_string('tracking_forceFullScreen', 'quizaccess_autoproctor'),
                'forceDesktop' => get_string('tracking_forceDesktop', 'quizaccess_autoproctor'),
                'recordSession' => get_string('tracking_recordSession', 'quizaccess_autoproctor'),
            ],
        ];

        // Default values for options.
        $optiondefaults = [
            'audio' => 1,
            'numHumans' => 1,
            'tabSwitch' => 1,
            'disableCopyPaste' => 0,
            'multiSessionAttempt' => 0,
            'testTakerPhoto' => 0,
            'photosAtRandom' => 1,
            'impersonation' => 0,
            'captureSwitchedTab' => 1,
            'recordSession' => 0,
            'detectMultipleScreens' => 1,
            'forceFullScreen' => 0,
            'forceDesktop' => 0,
            'idCardVerification' => 0,
        ];

        foreach ($trackingoptions as $group => $options) {
            // Add group header.
            $mform->addElement(
                'static',
                $group . '_header',
                '',
                '<strong>' . get_string('tracking_group_' . $group, 'quizaccess_autoproctor') . '</strong>'
            );

            foreach ($options as $option => $string) {
                $elementname = "tracking_{$option}";
                $mform->addElement(
                    'selectyesno',
                    $elementname,
                    $string
                );
                $mform->addHelpButton($elementname, "tracking_{$option}", 'quizaccess_autoproctor');

                // Handle idCardVerification option (check if enabled as object).
                if ($option === 'idCardVerification') {
                    $defaultvalue = !empty($apsettings->tracking_options['idCardVerification']) ? 1 : 0;
                } else {
                    $defaultvalue = $apsettings->tracking_options[$option] ?? $optiondefaults[$option] ?? 0;
                }

                $mform->setDefault($elementname, $defaultvalue);
                $mform->disabledIf($elementname, 'requireautoproctor', 'eq', 0);
                $mform->setType($elementname, PARAM_INT);
            }
        }
    }

    /**
     * Save the quiz settings from the form.
     *
     * @param stdClass $quiz The quiz data from the form.
     * @return bool True on success.
     */
    public static function save_settings($quiz) {
        global $DB;

        // Get existing settings to preserve tracking options.
        $existing = $DB->get_record('quizaccess_autoproctor', ['quiz_id' => $quiz->id]);
        $existingoptions = $existing ? json_decode($existing->tracking_options, true) : [];

        // Prepare record for database.
        $record = new stdClass();
        $record->quiz_id = $quiz->id;
        $record->proctoring_enabled = empty($quiz->requireautoproctor) ? 0 : 1;

        // Prepare tracking options.
        $trackingoptions = new stdClass();

        // Boolean options with their default values.
        $booleanoptions = [
            'audio' => true,
            'numHumans' => true,
            'tabSwitch' => true,
            'disableCopyPaste' => false,
            'captureSwitchedTab' => true,
            'photosAtRandom' => true,
            'recordSession' => false,
            'detectMultipleScreens' => true,
            'testTakerPhoto' => false,
            'forceFullScreen' => false,
            'forceDesktop' => false,
            'multiSessionAttempt' => false,
            'impersonation' => false,
        ];

        foreach ($booleanoptions as $option => $default) {
            $formfield = "tracking_{$option}";
            // If proctoring is enabled, use form values, otherwise keep existing values.
            if ($record->proctoring_enabled) {
                $trackingoptions->$option = isset($quiz->$formfield) ? (bool) $quiz->$formfield : $default;
            } else {
                $trackingoptions->$option = $existingoptions[$option] ?? $default;
            }
            unset($quiz->$formfield);
        }

        // Handle idCardVerification - when enabled, set all sub-options to true.
        $formfield = "tracking_idCardVerification";
        if ($record->proctoring_enabled) {
            $idcardenabled = isset($quiz->$formfield) ? (bool) $quiz->$formfield : false;
        } else {
            $idcardenabled = !empty($existingoptions['idCardVerification']);
        }
        unset($quiz->$formfield);

        // If idCardVerification is enabled, set all sub-options to true.
        if ($idcardenabled) {
            $trackingoptions->idCardVerification = (object) [
                'face' => true,
                'name' => true,
                'expiryDate' => true,
            ];
        }

        $record->tracking_options = json_encode($trackingoptions);

        // Insert or update the record.
        if ($existing) {
            $record->timemodified = time();
            $record->id = $existing->id;
            $DB->update_record('quizaccess_autoproctor', $record);
        } else {
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('quizaccess_autoproctor', $record);
        }

        return true;
    }

    /**
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     * There is no obligation to return anything. If it is not appropriate to tell students
     * about this rule, then just return ''.
     *
     * @return mixed a message, or array of messages, explaining the restriction
     * @throws coding_exception
     */
    public function description() {
        $messages = [get_string('autoproctor_desc_headsup', 'quizaccess_autoproctor')];
        $messages[] = $this->get_download_config_button();

        return $messages;
    }

    /**
     * Whether this rule requires a preflight check before starting a new attempt.
     *
     * @param int|null $attemptid The attempt ID.
     * @return bool Whether a preflight check is required.
     */
    public function is_preflight_check_required($attemptid) {
        global $PAGE;

        // Check if proctoring is enabled for this quiz.
        $proctoringenabled = self::get_ap_settings($this->quizobj->get_quiz()->id)->proctoring_enabled;
        if (!$proctoringenabled) {
            return false;
        }

        // Only require preflight check on view.php.
        $isviewpage = strpos($PAGE->url->get_path(), '/mod/quiz/view.php') !== false;
        return $isviewpage;
    }

    /**
     * Add any fields that this rule requires to the preflight check form.
     *
     * @param quizaccess_autoproctor_preflight_form_alias $quizform The preflight form.
     * @param MoodleQuickForm $mform The form object.
     * @param int $attemptid The attempt ID.
     */
    public function add_preflight_check_form_fields(
        quizaccess_autoproctor_preflight_form_alias $quizform,
        MoodleQuickForm $mform,
        $attemptid
    ) {
        global $PAGE, $DB, $USER;

        // Get tracking options to determine which permissions are needed.
        $trackingoptions = self::get_ap_settings($this->quizobj->get_quiz()->id)->tracking_options;

        // Build dynamic permissions list based on tracking options.
        $permissionshtml = $this->build_permissions_list($trackingoptions);

        // Add consent checkbox.
        $mform->addElement(
            'header',
            'autoproctorheader',
            get_string('proctoringheader', 'quizaccess_autoproctor')
        );

        $mform->addElement(
            'static',
            'autoproctor_permissions',
            '',
            $permissionshtml
        );
        $mform->addElement(
            'checkbox',
            'autoproctor_consent',
            get_string('proctoringconsent', 'quizaccess_autoproctor')
        );
        $mform->addRule('autoproctor_consent', null, 'required', null, 'client');

        // Get client credentials.
        $creds = self::get_credentials();
        if (empty($creds['clientid']) || empty($creds['clientsecret'])) {
            \core\notification::error(get_string('credentials_not_set', 'quizaccess_autoproctor'));
            return;
        }

        // Check for unfinished attempt.
        $unfinishedattempt = quiz_get_user_attempt_unfinished($this->quizobj->get_quiz()->id, $USER->id);

        // If there is an unfinished attempt, check if a session already exists for it.
        $session = $unfinishedattempt ? self::get_ap_session($unfinishedattempt->id) : null;

        // Get the test attempt ID from the URL or generate a cryptographically secure one.
        $testattemptid = optional_param('test-attempt-id', 'ap_' . bin2hex(random_bytes(16)), PARAM_ALPHANUMEXT);
        $trackingoptions = self::get_ap_settings($this->quizobj->get_quiz()->id)->tracking_options;

        // Build user details to pass to AutoProctor.
        $userdetails = [
            'name' => fullname($USER),
            'email' => $USER->email ?? '',
        ];

        if ($session) {
            // If session exists, use that test attempt ID.
            $testattemptid = $session->test_attempt_id;
        }

        // Get environment configuration.
        $envconfig = self::get_environment_config();

        // Include AutoProctor SDK.
        $PAGE->requires->js(new moodle_url($envconfig['apentryurl']), true);

        $this->testattemptid = $testattemptid;

        // Compute hash server-side to avoid exposing client secret to browser.
        $hashedtestattemptid = self::hash_test_attempt_id($testattemptid, $creds['clientsecret']);

        // Include necessary scripts/styles for AutoProctor during preflight check.
        $PAGE->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'init', [
            'clientId' => $creds['clientid'],
            'hashedTestAttemptId' => $hashedtestattemptid,
            'testAttemptId' => $testattemptid,
            'trackingOptions' => $trackingoptions,
            'cmid' => $this->quizobj->get_quiz()->cmid,
            'lookupKey' => $this->get_lookup_key(),
            'apDomain' => $envconfig['apdomain'],
            'apEnv' => $envconfig['apenv'],
            'userDetails' => $userdetails,
        ]);
    }

    /**
     * Validate the preflight check.
     *
     * @param array $data The form data.
     * @param array $files The uploaded files.
     * @param array $errors Existing errors.
     * @param int $attemptid The attempt ID.
     * @return array The errors array.
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        // Ignore all AutoProctor preflight check if errors is not empty.
        if (!empty($errors)) {
            return $errors;
        }

        if (empty($data['autoproctor_consent'])) {
            $errors['autoproctor_consent'] = get_string('mustacceptproctoring', 'quizaccess_autoproctor');
        }

        return $errors;
    }

    /**
     * Get the AutoProctor settings for a quiz from the database.
     *
     * @param int $quizid The quiz ID.
     * @return stdClass The settings object.
     */
    private static function get_ap_settings($quizid) {
        global $DB;
        $result = new stdClass();
        $record = $DB->get_record('quizaccess_autoproctor', ['quiz_id' => $quizid]);

        if ($record) {
            $result->tracking_options = json_decode($record->tracking_options, true);
            $result->proctoring_enabled = $record->proctoring_enabled;
        } else {
            $result->tracking_options = [];
            $result->proctoring_enabled = 0;
        }
        return $result;
    }

    /**
     * Get the AutoProctor session for an attempt.
     *
     * @param int $attemptid The attempt ID.
     * @return stdClass|false The session record or false.
     */
    private static function get_ap_session($attemptid) {
        global $DB;
        return $DB->get_record('quizaccess_autoproctor_sessions', ['quiz_attempt_id' => $attemptid]);
    }

    /**
     * Get the lookup key for the AutoProctor report.
     * Lookup key = siteidentifier_cmid_quizid.
     *
     * @return string The lookup key.
     */
    private function get_lookup_key() {
        global $CFG;
        return $CFG->siteidentifier . '_' . $this->quizobj->get_quiz()->cmid . '_' . $this->quizobj->get_quiz()->id;
    }

    /**
     * Get environment configuration based on hostname.
     * Returns URLs and settings for development vs production environment.
     *
     * @return array Configuration array with isLocalhost, apDomain, apEnv, apEntryUrl.
     */
    private static function get_environment_config(): array {
        $islocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
            || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;

        return [
            'islocalhost' => $islocalhost,
            'apdomain' => $islocalhost ? self::AP_DOMAIN_DEVELOPMENT : self::AP_DOMAIN_PRODUCTION,
            'apenv' => $islocalhost ? 'development' : 'production',
            'apentryurl' => $islocalhost ? self::AP_CDN_DEVELOPMENT : self::AP_CDN_PRODUCTION,
        ];
    }

    /**
     * Get AutoProctor API credentials from plugin settings.
     *
     * @return array Credentials array with clientid and clientsecret.
     */
    private static function get_credentials(): array {
        return [
            'clientid' => get_config('quizaccess_autoproctor', 'client_id'),
            'clientsecret' => get_config('quizaccess_autoproctor', 'client_secret'),
        ];
    }

    /**
     * Generate HMAC-SHA256 hash of test attempt ID for SDK authentication.
     * This is computed server-side to avoid exposing the client secret to the browser.
     *
     * @param string $testattemptid The test attempt ID to hash.
     * @param string $clientsecret The client secret key.
     * @return string Base64-encoded HMAC-SHA256 hash.
     */
    private static function hash_test_attempt_id(string $testattemptid, string $clientsecret): string {
        $hash = hash_hmac('sha256', $testattemptid, $clientsecret, true);
        return base64_encode($hash);
    }

    /**
     * Build the permissions list HTML based on which tracking options are enabled.
     *
     * @param array $trackingoptions The tracking options for this quiz.
     * @return string HTML for the permissions list.
     */
    private function build_permissions_list(array $trackingoptions): string {
        $permissions = [];

        // Screen is needed for: recordSession, captureSwitchedTab, detectMultipleScreens, forceFullScreen.
        $needsscreen = !empty($trackingoptions['recordSession'])
            || !empty($trackingoptions['captureSwitchedTab'])
            || !empty($trackingoptions['detectMultipleScreens'])
            || !empty($trackingoptions['forceFullScreen']);

        // Microphone is needed for: audio.
        $needsmicrophone = !empty($trackingoptions['audio']);

        // Camera is needed for: testTakerPhoto, photosAtRandom, numHumans, impersonation, idCardVerification.
        $needscamera = !empty($trackingoptions['testTakerPhoto'])
            || !empty($trackingoptions['photosAtRandom'])
            || !empty($trackingoptions['numHumans'])
            || !empty($trackingoptions['impersonation'])
            || !empty($trackingoptions['idCardVerification']);

        $counter = 1;
        if ($needsscreen) {
            $permissions[] = '<li>' . $counter++ . '. ' . get_string('permission_screen', 'quizaccess_autoproctor') . '</li>';
        }
        if ($needsmicrophone) {
            $permissions[] = '<li>' . $counter++ . '. ' . get_string('permission_microphone', 'quizaccess_autoproctor') . '</li>';
        }
        if ($needscamera) {
            $permissions[] = '<li>' . $counter++ . '. ' . get_string('permission_camera', 'quizaccess_autoproctor') . '</li>';
        }

        if (empty($permissions)) {
            return '';
        }

        return '<ul class="autoproctor-permissions-list">' . implode('', $permissions) . '</ul>';
    }

    /**
     * Sets up the attempt (review or summary) page with any special extra
     * properties required by this rule.
     *
     * @param moodle_page $page the page object to initialise.
     */
    public function setup_attempt_page($page) {
        global $DB, $USER;

        // Only add report button on review.php.
        $isreviewpage = strpos($page->url->get_path(), '/mod/quiz/review.php') !== false;
        if (!$isreviewpage) {
            return;
        }

        // Check if user has permission to view reports.
        $context = context_module::instance($this->quizobj->get_quiz()->cmid, MUST_EXIST);
        if (!has_capability('quizaccess/autoproctor:viewreport', $context, $USER->id)) {
            return;
        }

        // Get the attempt ID from URL.
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (empty($attemptid)) {
            return;
        }

        // Get the session for this attempt.
        $session = self::get_ap_session($attemptid);
        if (!$session || empty($session->test_attempt_id)) {
            return;
        }

        // Get credentials.
        $creds = self::get_credentials();
        if (empty($creds['clientid']) || empty($creds['clientsecret'])) {
            return;
        }

        // Get environment configuration.
        $envconfig = self::get_environment_config();

        // Build the report URL.
        $reportbaseurl = get_string('viewattemptreportlink', 'quizaccess_autoproctor');
        $reporturl = $reportbaseurl . $session->test_attempt_id . '/';
        $buttonlabel = get_string('viewattemptreport', 'quizaccess_autoproctor');

        // Include AutoProctor SDK.
        $page->requires->js(new moodle_url($envconfig['apentryurl']), true);

        // Get tracking options from session to determine which tabs to show.
        $trackingoptions = json_decode($session->tracking_options, true) ?? [];

        // Compute hash server-side to avoid exposing client secret to browser.
        $hashedtestattemptid = self::hash_test_attempt_id($session->test_attempt_id, $creds['clientsecret']);

        // Call JS to add the report button.
        $page->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'addReportButton', [
            'reportUrl' => $reporturl,
            'buttonLabel' => $buttonlabel,
            'clientId' => $creds['clientid'],
            'hashedTestAttemptId' => $hashedtestattemptid,
            'testAttemptId' => $session->test_attempt_id,
            'trackingOptions' => $trackingoptions,
            'apDomain' => $envconfig['apdomain'],
            'apEnv' => $envconfig['apenv'],
        ]);
    }

    /**
     * Get a button to view the Proctoring report.
     *
     * @return string A link to view report.
     * @throws coding_exception
     */
    private function get_download_config_button(): string {
        global $OUTPUT, $USER;

        $context = context_module::instance($this->quizobj->get_quiz()->cmid, MUST_EXIST);

        if (has_capability('quizaccess/autoproctor:viewreport', $context, $USER->id)) {
            $httplink = get_string('autoproctorresultslink', 'quizaccess_autoproctor');
            $httplink = "$httplink?lookup_key={$this->get_lookup_key()}";
            $button = $OUTPUT->single_button($httplink, get_string('autoproctorresults', 'quizaccess_autoproctor'), 'get', [
                'style' => 'text-align: center; border: 1px solid #106bbf; background-color: white; ' .
                    'color: #106bbf; padding: 5px 10px; display: inline-block;',
                'onmouseover' => "this.style.backgroundColor='#106bbf'; this.style.color='white';",
                'onmouseout' => "this.style.backgroundColor='white'; this.style.color='#106bbf';",
            ]);
            return $button;
        } else {
            return '';
        }
    }
}
