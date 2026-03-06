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
 * Privacy Subsystem implementation for quizaccess_autoproctor.
 *
 * @package    quizaccess_autoproctor
 * @copyright  2024 AutoProctor <autoproctor.co>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_autoproctor\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for quizaccess_autoproctor.
 *
 * This plugin collects proctoring data including webcam photos, audio recordings,
 * and screen recordings during quiz attempts. This data is transmitted to and
 * stored on external AutoProctor servers.
 */
class provider implements
    metadata_provider,
    plugin_provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns metadata about the personal data stored by this plugin.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        // Data stored in the local database.
        $collection->add_database_table(
            'quizaccess_autoproctor_sessions',
            [
                'quiz_id' => 'privacy:metadata:quizaccess_autoproctor_sessions:quiz_id',
                'quiz_attempt_id' => 'privacy:metadata:quizaccess_autoproctor_sessions:quiz_attempt_id',
                'test_attempt_id' => 'privacy:metadata:quizaccess_autoproctor_sessions:test_attempt_id',
                'tracking_options' => 'privacy:metadata:quizaccess_autoproctor_sessions:tracking_options',
                'started_at' => 'privacy:metadata:quizaccess_autoproctor_sessions:started_at',
                'timecreated' => 'privacy:metadata:quizaccess_autoproctor_sessions:timecreated',
                'timemodified' => 'privacy:metadata:quizaccess_autoproctor_sessions:timemodified',
            ],
            'privacy:metadata:quizaccess_autoproctor_sessions'
        );

        // Data sent to external AutoProctor service.
        $collection->add_external_location_link(
            'autoproctor_external',
            [
                'webcam_photos' => 'privacy:metadata:autoproctor_external:webcam_photos',
                'audio_recordings' => 'privacy:metadata:autoproctor_external:audio_recordings',
                'screen_recordings' => 'privacy:metadata:autoproctor_external:screen_recordings',
                'test_attempt_id' => 'privacy:metadata:autoproctor_external:test_attempt_id',
                'tracking_options' => 'privacy:metadata:autoproctor_external:tracking_options',
            ],
            'privacy:metadata:autoproctor_external'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quiz} q ON q.id = cm.instance
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN {quizaccess_autoproctor_sessions} aps ON aps.quiz_attempt_id = qa.id
                 WHERE qa.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $sql = "SELECT DISTINCT qa.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {quiz} q ON q.id = cm.instance
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN {quizaccess_autoproctor_sessions} aps ON aps.quiz_attempt_id = qa.id
                 WHERE cm.id = :cmid";

        $params = ['cmid' => $context->instanceid];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $sql = "SELECT aps.*
                      FROM {quizaccess_autoproctor_sessions} aps
                      JOIN {quiz_attempts} qa ON qa.id = aps.quiz_attempt_id
                     WHERE qa.quiz = :quizid AND qa.userid = :userid";

            $params = [
                'quizid' => $cm->instance,
                'userid' => $user->id,
            ];

            $sessions = $DB->get_records_sql($sql, $params);

            if (!empty($sessions)) {
                $exportdata = [];
                foreach ($sessions as $session) {
                    $exportdata[] = [
                        'quiz_attempt_id' => $session->quiz_attempt_id,
                        'test_attempt_id' => $session->test_attempt_id,
                        'tracking_options' => $session->tracking_options,
                        'started_at' => $session->started_at ?
                            transform::datetime($session->started_at) : null,
                        'timecreated' => transform::datetime($session->timecreated),
                        'timemodified' => transform::datetime($session->timemodified),
                        'external_data_note' => get_string(
                            'privacy:externaldatanote',
                            'quizaccess_autoproctor'
                        ),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'quizaccess_autoproctor')],
                    (object) ['sessions' => $exportdata]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        // Delete all session records for this quiz.
        $DB->delete_records('quizaccess_autoproctor_sessions', ['quiz_id' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user to delete data for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('quiz', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Get quiz attempts for this user.
            $attemptids = $DB->get_fieldset_select(
                'quiz_attempts',
                'id',
                'quiz = :quizid AND userid = :userid',
                ['quizid' => $cm->instance, 'userid' => $user->id]
            );

            if (!empty($attemptids)) {
                list($insql, $inparams) = $DB->get_in_or_equal($attemptids);
                $DB->delete_records_select(
                    'quizaccess_autoproctor_sessions',
                    "quiz_attempt_id $insql",
                    $inparams
                );
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('quiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Get quiz attempts for these users.
        $sql = "SELECT id FROM {quiz_attempts}
                 WHERE quiz = :quizid AND userid $usersql";
        $params = array_merge(['quizid' => $cm->instance], $userparams);

        $attemptids = $DB->get_fieldset_sql($sql, $params);

        if (!empty($attemptids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($attemptids);
            $DB->delete_records_select(
                'quizaccess_autoproctor_sessions',
                "quiz_attempt_id $insql",
                $inparams
            );
        }
    }
}
