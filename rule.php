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

class quizaccess_autoproctor extends quizaccess_autoproctor_parent_class_alias
{
    // Environment URLs
    private const AP_CDN_PRODUCTION = 'https://cdn.autoproctor.co/ap-entry-moodle.js';
    private const AP_CDN_DEVELOPMENT = 'https://ap-development.s3.ap-south-1.amazonaws.com/ap-entry-moodle.js';
    private const AP_DOMAIN_PRODUCTION = 'https://www.autoproctor.co';
    private const AP_DOMAIN_DEVELOPMENT = 'https://dev.autoproctor.co';

    /** @var quizaccess_autoproctor_quiz_settings_class_alias */
    protected $quizobj;

    /** @var string */
    protected $testAttemptId;

    public function __construct($quizobj, $timenow)
    {
        parent::__construct($quizobj, $timenow);
        $this->quizobj = $quizobj;
    }

    public static function make(quizaccess_autoproctor_quiz_settings_class_alias $quizobj, $timenow, $canignoretimelimits)
    {
        $quizid = $quizobj->get_quiz()->id;
        $proctoring_enabled = self::get_ap_settings($quizid)->proctoring_enabled;
        if (empty($proctoring_enabled))
            return null;

        return new self($quizobj, $timenow);
    }

    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform)
    {
        // Get the current autoproctor settings for the quiz
        $ap_settings = self::get_ap_settings($quizform->get_current()->id);

        // Create header for tracking options
        $mform->addElement('header', 'autoproctorsettings', get_string('autoproctorsettings', 'quizaccess_autoproctor'));

        // Add main AutoProctor toggle
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
            $ap_settings->proctoring_enabled ?? get_config(
                'quizaccess_autoproctor',
                'enable_by_default'
            )
        );

        // Add nested tracking options (boolean options)
        $tracking_options = [
            'activity' => [
                'audio' => get_string('tracking_audio', 'quizaccess_autoproctor'),
                'numHumans' => get_string('tracking_numHumans', 'quizaccess_autoproctor'),
                'tabSwitch' => get_string('tracking_tabSwitch', 'quizaccess_autoproctor'),
                'disableCopyPaste' => get_string('tracking_disableCopyPaste', 'quizaccess_autoproctor'),
                'multiSessionAttempt' => get_string('tracking_multiSessionAttempt', 'quizaccess_autoproctor'),
            ],
            'camera' => [
                'testTakerPhoto' => get_string('tracking_testTakerPhoto', 'quizaccess_autoproctor'),
                'photosAtRandom' => get_string('tracking_photosAtRandom', 'quizaccess_autoproctor'),
                'impersonation' => get_string('tracking_impersonation', 'quizaccess_autoproctor'),
            ],
            'screen' => [
                'captureSwitchedTab' => get_string('tracking_captureSwitchedTab', 'quizaccess_autoproctor'),
                'recordSession' => get_string('tracking_recordSession', 'quizaccess_autoproctor'),
                'detectMultipleScreens' => get_string('tracking_detectMultipleScreens', 'quizaccess_autoproctor'),
                'forceFullScreen' => get_string('tracking_forceFullScreen', 'quizaccess_autoproctor'),
                'forceDesktop' => get_string('tracking_forceDesktop', 'quizaccess_autoproctor'),
            ],
            'idcard' => [
                'idCardVerification_face' => get_string('tracking_idCardVerification_face', 'quizaccess_autoproctor'),
                'idCardVerification_name' => get_string('tracking_idCardVerification_name', 'quizaccess_autoproctor'),
                'idCardVerification_expiryDate' => get_string('tracking_idCardVerification_expiryDate', 'quizaccess_autoproctor'),
            ],
        ];

        // Default values for options (false = off by default, true = on by default)
        $option_defaults = [
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
            'idCardVerification_face' => 0,
            'idCardVerification_name' => 0,
            'idCardVerification_expiryDate' => 0,
        ];

        foreach ($tracking_options as $group => $options) {
            // Add group header
            $mform->addElement(
                'static',
                $group . '_header',
                '',
                '<strong>' . get_string('tracking_group_' . $group, 'quizaccess_autoproctor') . '</strong>'
            );

            foreach ($options as $option => $string) {
                $element_name = "tracking_{$option}";
                $mform->addElement(
                    'selectyesno',
                    $element_name,
                    $string
                );
                $mform->addHelpButton($element_name, "tracking_{$option}", 'quizaccess_autoproctor');

                // Handle idCardVerification options specially (nested structure)
                if (strpos($option, 'idCardVerification_') === 0) {
                    $subkey = str_replace('idCardVerification_', '', $option);
                    $stored_value = $ap_settings->tracking_options['idCardVerification'][$subkey] ?? null;
                    $default_value = $stored_value !== null ? (int) $stored_value : ($option_defaults[$option] ?? 0);
                } else {
                    $default_value = $ap_settings->tracking_options[$option] ?? $option_defaults[$option] ?? 0;
                }

                $mform->setDefault($element_name, $default_value);
                $mform->disabledIf($element_name, 'requireautoproctor', 'eq', 0);
                $mform->setType($element_name, PARAM_INT);
            }
        }
    }

    public static function save_settings($quiz)
    {
        global $DB;

        // Get existing settings to preserve tracking options
        $existing = $DB->get_record('quizaccess_autoproctor', ['quiz_id' => $quiz->id]);
        $existing_options = $existing ? json_decode($existing->tracking_options, true) : [];

        // Prepare record for database
        $record = new stdClass();
        $record->quiz_id = $quiz->id;
        $record->proctoring_enabled = empty($quiz->requireautoproctor) ? 0 : 1;

        // Prepare tracking options
        $tracking_options = new stdClass();

        // Boolean options with their default values
        $boolean_options = [
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

        foreach ($boolean_options as $option => $default) {
            $form_field = "tracking_{$option}";
            // If proctoring is enabled, use form values, otherwise keep existing values
            if ($record->proctoring_enabled) {
                $tracking_options->$option = isset($quiz->$form_field) ? (bool) $quiz->$form_field : $default;
            } else {
                $tracking_options->$option = $existing_options[$option] ?? $default;
            }
            unset($quiz->$form_field);
        }

        // Handle idCardVerification as an object (or null if all disabled)
        $idcard_options = ['face', 'name', 'expiryDate'];
        $idcard_config = new stdClass();
        $idcard_enabled = false;

        foreach ($idcard_options as $idopt) {
            $form_field = "tracking_idCardVerification_{$idopt}";
            if ($record->proctoring_enabled) {
                $value = isset($quiz->$form_field) ? (bool) $quiz->$form_field : false;
            } else {
                $value = $existing_options['idCardVerification'][$idopt] ?? false;
            }
            $idcard_config->$idopt = $value;
            if ($value) {
                $idcard_enabled = true;
            }
            unset($quiz->$form_field);
        }

        // Only set idCardVerification if at least one option is enabled
        // If all disabled, omit the key entirely from JSON
        if ($idcard_enabled) {
            $tracking_options->idCardVerification = $idcard_config;
        }

        $record->tracking_options = json_encode($tracking_options);

        // Insert or update the record
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
    public function description()
    {
        $messages = [get_string('autoproctor_desc_headsup', 'quizaccess_autoproctor')];
        $messages[] = $this->get_download_config_button();

        return $messages;
    }

    /**
     * Whether this rule requires a preflight check before starting a new attempt.
     */
    public function is_preflight_check_required($attemptid)
    {
        global $PAGE;

        // Check if proctoring is enabled for this quiz
        $proctoring_enabled = self::get_ap_settings($this->quizobj->get_quiz()->id)->proctoring_enabled;
        if (!$proctoring_enabled) {
            return false;
        }

        // Only require preflight check on view.php
        $is_view_page = strpos($PAGE->url->get_path(), '/mod/quiz/view.php') !== false;
        return $is_view_page;
    }

    /**
     * Add any fields that this rule requires to the quiz settings form.
     */
    public function add_preflight_check_form_fields(
        quizaccess_autoproctor_preflight_form_alias $quizform,
        MoodleQuickForm $mform,
        $attemptid
    ) {

        // Add consent checkbox
        $mform->addElement(
            'header',
            'autoproctorheader',
            get_string('proctoringheader', 'quizaccess_autoproctor')
        );

        $mform->addElement(
            'static',
            'autoproctor_permissions',
            '',
            get_string('proctoringpermissions', 'quizaccess_autoproctor')
        );
        $mform->addElement(
            'checkbox',
            'autoproctor_consent',
            get_string('proctoringconsent', 'quizaccess_autoproctor')
        );
        $mform->addRule('autoproctor_consent', null, 'required', null, 'client');

        // Start proctoring session
        global $PAGE, $DB, $USER;

        // Get client credentials
        $creds = self::get_credentials();
        if (empty($creds['clientId']) || empty($creds['clientSecret'])) {
            \core\notification::error(get_string('credentials_not_set', 'quizaccess_autoproctor'));
            return;
        }

        // Check for unfinished attempt
        $unfinishedattempt = quiz_get_user_attempt_unfinished($this->quizobj->get_quiz()->id, $USER->id);

        // If there is an unfinished attempt, check if a session already exists for it
        $session = $unfinishedattempt ? self::get_ap_session($unfinishedattempt->id) : null;

        // Get the test attempt ID from the URL or generate a new one
        $testAttemptId = optional_param('test-attempt-id', uniqid('ap_'), PARAM_RAW);
        $tracking_options = self::get_ap_settings($this->quizobj->get_quiz()->id)->tracking_options;

        // Build user details to pass to AutoProctor
        $userdetails = [
            'name' => fullname($USER),
            'email' => $USER->email ?? '',
        ];

        if ($session) {
            // If session exists, use that test attempt ID
            $testAttemptId = $session->test_attempt_id;
        }

        // Get environment configuration
        $envConfig = self::get_environment_config();

        // Include AutoProctor SDK
        $PAGE->requires->js(new moodle_url($envConfig['apEntryUrl']), true);

        $this->testAttemptId = $testAttemptId;

        // Compute hash server-side to avoid exposing client secret to browser
        $hashedTestAttemptId = self::hash_test_attempt_id($testAttemptId, $creds['clientSecret']);

        // Include necessary scripts/styles for AutoProctor during preflight check
        $PAGE->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'init', [
            'clientId' => $creds['clientId'],
            'hashedTestAttemptId' => $hashedTestAttemptId,
            'testAttemptId' => $testAttemptId,
            'trackingOptions' => $tracking_options,
            'cmid' => $this->quizobj->get_quiz()->cmid,
            'lookupKey' => $this->get_lookup_key(),
            'apDomain' => $envConfig['apDomain'],
            'apEnv' => $envConfig['apEnv'],
            'userDetails' => $userdetails,
        ]);
    }

    /**
     * Validate the preflight check
     * @param array $data
     * @param array $files
     * @param array $errors
     * @param int $attemptid
     * @return array
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid)
    {
        // Ignore all AutoProctor preflight check if $errors is not empty.
        if (!empty($errors)) {
            return $errors;
        }

        if (empty($data['autoproctor_consent'])) {
            $errors['autoproctor_consent'] = get_string('mustacceptproctoring', 'quizaccess_autoproctor');
        }

        return $errors;
    }

    /**
     * Get the AutoProctor settings for a quiz from the database
     * @param int $quizid
     * @return stdClass
     */
    private static function get_ap_settings($quizid)
    {
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
     * Get the AutoProctor session for an attempt
     * @param int $attemptid
     * @return stdClass
     */
    private static function get_ap_session($attemptid)
    {
        global $DB;
        return $DB->get_record('quizaccess_autoproctor_sessions', ['quiz_attempt_id' => $attemptid]);
    }

    /**
     * Get the lookup key for the AutoProctor report
     * Lookup key = <siteidentifier>_<cmid>_<quizid>
     * @return string
     */
    private function get_lookup_key()
    {
        global $CFG;
        return $CFG->siteidentifier . '_' . $this->quizobj->get_quiz()->cmid . '_' . $this->quizobj->get_quiz()->id;
    }

    /**
     * Get environment configuration based on hostname.
     * Returns URLs and settings for development vs production environment.
     *
     * @return array [
     *     'isLocalhost' => bool,
     *     'apDomain' => string,
     *     'apEnv' => string,
     *     'apEntryUrl' => string
     * ]
     */
    private static function get_environment_config(): array
    {
        $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
            || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;

        return [
            'isLocalhost' => $isLocalhost,
            'apDomain' => $isLocalhost ? self::AP_DOMAIN_DEVELOPMENT : self::AP_DOMAIN_PRODUCTION,
            'apEnv' => $isLocalhost ? 'development' : 'production',
            'apEntryUrl' => $isLocalhost ? self::AP_CDN_DEVELOPMENT : self::AP_CDN_PRODUCTION
        ];
    }

    /**
     * Get AutoProctor API credentials from plugin settings.
     *
     * @return array ['clientId' => string, 'clientSecret' => string]
     */
    private static function get_credentials(): array
    {
        return [
            'clientId' => get_config('quizaccess_autoproctor', 'client_id'),
            'clientSecret' => get_config('quizaccess_autoproctor', 'client_secret')
        ];
    }

    /**
     * Generate HMAC-SHA256 hash of test attempt ID for SDK authentication.
     * This is computed server-side to avoid exposing the client secret to the browser.
     *
     * @param string $testAttemptId The test attempt ID to hash
     * @param string $clientSecret The client secret key
     * @return string Base64-encoded HMAC-SHA256 hash
     */
    private static function hash_test_attempt_id(string $testAttemptId, string $clientSecret): string
    {
        $hash = hash_hmac('sha256', $testAttemptId, $clientSecret, true);
        return base64_encode($hash);
    }

    /**
     * Sets up the attempt (review or summary) page with any special extra
     * properties required by this rule.
     *
     * @param moodle_page $page the page object to initialise.
     */
    public function setup_attempt_page($page) {
        global $DB, $USER;

        // Only add report button on review.php
        $is_review_page = strpos($page->url->get_path(), '/mod/quiz/review.php') !== false;
        if (!$is_review_page) {
            return;
        }

        // Check if user has permission to view reports
        $context = context_module::instance($this->quizobj->get_quiz()->cmid, MUST_EXIST);
        if (!has_capability('quizaccess/autoproctor:viewreport', $context, $USER->id)) {
            return;
        }

        // Get the attempt ID from URL
        $attemptid = optional_param('attempt', 0, PARAM_INT);
        if (empty($attemptid)) {
            return;
        }

        // Get the session for this attempt
        $session = self::get_ap_session($attemptid);
        if (!$session || empty($session->test_attempt_id)) {
            return;
        }

        // Get credentials
        $creds = self::get_credentials();
        if (empty($creds['clientId']) || empty($creds['clientSecret'])) {
            return;
        }

        // Get environment configuration
        $envConfig = self::get_environment_config();

        // Build the report URL
        $reportBaseUrl = get_string('viewattemptreportlink', 'quizaccess_autoproctor');
        $reportUrl = $reportBaseUrl . $session->test_attempt_id . '/';
        $buttonLabel = get_string('viewattemptreport', 'quizaccess_autoproctor');

        // Include AutoProctor SDK
        $page->requires->js(new moodle_url($envConfig['apEntryUrl']), true);

        // Get tracking options from session to determine which tabs to show
        $tracking_options = json_decode($session->tracking_options, true) ?? [];

        // Compute hash server-side to avoid exposing client secret to browser
        $hashedTestAttemptId = self::hash_test_attempt_id($session->test_attempt_id, $creds['clientSecret']);

        // Call JS to add the report button
        $page->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'addReportButton', [
            'reportUrl' => $reportUrl,
            'buttonLabel' => $buttonLabel,
            'clientId' => $creds['clientId'],
            'hashedTestAttemptId' => $hashedTestAttemptId,
            'testAttemptId' => $session->test_attempt_id,
            'trackingOptions' => $tracking_options,
            'apDomain' => $envConfig['apDomain'],
            'apEnv' => $envConfig['apEnv'],
        ]);
    }

    /**
     * Get a button to view the Proctoring report.
     *
     * @return string A link to view report
     * @throws coding_exception
     */
    private function get_download_config_button(): string
    {
        global $OUTPUT, $USER;

        $context = context_module::instance($this->quizobj->get_quiz()->cmid, MUST_EXIST);

        if (has_capability('quizaccess/autoproctor:viewreport', $context, $USER->id)) {
            $httplink = get_string('autoproctorresultslink', 'quizaccess_autoproctor');
            $httplink = "$httplink?lookup_key={$this->get_lookup_key()}";
            $button = $OUTPUT->single_button($httplink, get_string('autoproctorresults', 'quizaccess_autoproctor'), 'get', [
                'style' => 'text-align: center; border: 1px solid #106bbf; background-color: white; color: #106bbf; padding: 5px 10px; display: inline-block;',
                'onmouseover' => "this.style.backgroundColor='#106bbf'; this.style.color='white';",
                'onmouseout' => "this.style.backgroundColor='white'; this.style.color='#106bbf';"
            ]);
            return $button;
        } else {
            return '';
        }
    }

}
