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
 * A rule controlling the AutoProctor.
 *
 * @package   quizaccess_autoproctor
 * @copyright 2024 AutoProctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizaccess_autoproctor extends quizaccess_autoproctor_parent_class_alias {
    /** @var quizaccess_autoproctor_quiz_settings_class_alias */
    protected $quizobj;

    public function __construct($quizobj, $timenow) {
        parent::__construct($quizobj, $timenow);
        $this->quizobj = $quizobj;
    }

    public static function make(quizaccess_autoproctor_quiz_settings_class_alias $quizobj, $timenow, $canignoretimelimits) {
        if (empty($quizobj->get_quiz()->requireautoproctor)) {
            // return null;
        }

        return new self($quizobj, $timenow);
    }

    public function is_preflight_check_required($attemptid) {
        // Check if the quiz requires autoproctor
        if (empty($this->quizobj->get_quiz()->requireautoproctor)) {
            // return false;
        }
        return true;
    }

    public function add_preflight_check_form_fields(quizaccess_autoproctor_preflight_form_alias $quizform, MoodleQuickForm $mform, $attemptid) {
        global $PAGE, $DB;

        // Get client credentials
        $clientId = get_config('quizaccess_autoproctor', 'client_id');
        $clientSecret = get_config('quizaccess_autoproctor', 'client_secret');

        // Check if client credentials are set
        if (empty($clientId) || empty($clientSecret)) {
            $mform->addElement('static', 'proctoringerror', '',
                get_string('proctoring_required', 'quizaccess_autoproctor'));
            return;
        }

        // Get the current attempt ID or generate a new test attempt ID
        // Mostly for development purposes, usually the test attempt ID won't be provided in the URL
        $testAttemptId = optional_param(
            'test-attempt-id',
            uniqid('ap_'),
            PARAM_RAW
        );

        $tracking_options = self::get_ap_settings($this->quizobj->get_quiz()->id)->tracking_options;

        // Store in database if this is the first preflight check
        if (empty($attemptid)) {
            // TODO: handle case where attemptid is empty
            return;
        } else {
            $session = new stdClass();
            $session->quiz_id = $this->quizobj->get_quiz()->id;
            $session->quiz_attempt_id = $attemptid;
            $session->test_attempt_id = $testAttemptId;
            $session->started_at = time();
            $session->tracking_options = json_encode($tracking_options);
            $session->timecreated = $session->timemodified = time();
            
            $DB->insert_record('quizaccess_autoproctor_sessions', $session);
        }

        // Include the scripts and styles
        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>';
        echo '<script src="https://ap-development.s3.amazonaws.com/autoproctor.4.2.4.min.js"></script>';
        echo '<link rel="stylesheet" href="https://ap-development.s3.amazonaws.com/autoproctor.4.2.4.min.css"/>';

        // Include necessary scripts/styles for AutoProctor during preflight check
        $PAGE->requires->js_call_amd('quizaccess_autoproctor/proctoring', 'init', [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'testAttemptId' => $testAttemptId,
            'trackingOptions' => $tracking_options
        ]);

        // Add a hidden confirmation checkbox that will be checked via JavaScript when monitoring starts
        $mform->addElement('html', '<div id="ap-status-message">Setting up AutoProctor...<br>Waiting for proctoring to start...</div>');
    }

    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        // TODO: check if autoproctor setup is complete
        return $errors;
    }

    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        // Get the current autoproctor settings for the quiz
        $ap_settings = self::get_ap_settings($quizform->get_current()->id);

        // Add settings for enabling/disabling AutoProctor for a quiz
        $mform->addElement('selectyesno', 'requireautoproctor',
            get_string('requireautoproctor', 'quizaccess_autoproctor'));
        $mform->addHelpButton('requireautoproctor',
            'requireautoproctor', 'quizaccess_autoproctor');
        $mform->setDefault(
            'requireautoproctor',
            $ap_settings->proctoring_enabled ?? get_config(
                'quizaccess_autoproctor',
                'enable_by_default'
            )
        );

        // Add all tracking options
        $tracking_options = [
            'audio' => get_string('tracking_audio', 'quizaccess_autoproctor'),
            'numHumans' => get_string('tracking_numHumans', 'quizaccess_autoproctor'),
            'tabSwitch' => get_string('tracking_tabSwitch', 'quizaccess_autoproctor'),
            'captureSwitchedTab' => get_string('tracking_captureSwitchedTab', 'quizaccess_autoproctor'),
            'photosAtRandom' => get_string('tracking_photosAtRandom', 'quizaccess_autoproctor'),
            'recordSession' => get_string('tracking_recordSession', 'quizaccess_autoproctor'),
            'detectMultipleScreens' => get_string('tracking_detectMultipleScreens', 'quizaccess_autoproctor'),
            'testTakerPhoto' => get_string('tracking_testTakerPhoto', 'quizaccess_autoproctor'),
            'showCamPreview' => get_string('tracking_showCamPreview', 'quizaccess_autoproctor'),
            'forceFullScreen' => get_string('tracking_forceFullScreen', 'quizaccess_autoproctor')
        ];

        foreach ($tracking_options as $option => $string) {
            $element_name = "tracking_{$option}";
            $mform->addElement('selectyesno', $element_name, $string);
            $mform->addHelpButton($element_name, "tracking_{$option}", 'quizaccess_autoproctor');
            $mform->setDefault($element_name, $ap_settings->tracking_options[$option] ?? 1);
            $mform->disabledIf($element_name, 'requireautoproctor', 'eq', 0);
            $mform->setType($element_name, PARAM_INT);
        }
    }

    public static function save_settings($quiz) {
        global $DB;
        
        // Prepare record for database
        $record = new stdClass();
        $record->quiz_id = $quiz->id;
        $record->proctoring_enabled = empty($quiz->requireautoproctor) ? 0 : 1;

        // Prepare tracking options
        $tracking_options = new stdClass();
        $options = [
            'audio', 'numHumans', 'tabSwitch', 'captureSwitchedTab',
            'photosAtRandom', 'recordSession', 'detectMultipleScreens',
            'testTakerPhoto', 'showCamPreview', 'forceFullScreen'
        ];
        foreach ($options as $option) {
            $form_field = "tracking_{$option}";
            if (isset($quiz->$form_field)) {
                $tracking_options->$option = (bool)$quiz->$form_field;
                unset($quiz->$form_field);
            }
        }
        $record->tracking_options = json_encode($tracking_options);

        // Insert or update the record
        if ($DB->record_exists('quizaccess_autoproctor', ['quiz_id' => $quiz->id])) {
            $record->timemodified = time();
            $existing = $DB->get_record('quizaccess_autoproctor', ['quiz_id' => $quiz->id]);
            $record->id = $existing->id;
            $DB->update_record('quizaccess_autoproctor', $record);
        } else {
            $record->timecreated = time();
            $record->timemodified = time();
            $DB->insert_record('quizaccess_autoproctor', $record);
        }

        return true;
    }

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
}
