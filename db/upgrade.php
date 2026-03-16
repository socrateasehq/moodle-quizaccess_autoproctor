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
 * Quiz access proctoring plugin upgrade code.
 *
 * @package   quizaccess_autoproctor
 * @copyright 2024 AutoProctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade step to add new fields to sessions table.
 *
 * @param database_manager $dbman The database manager.
 * @return void
 */
function quizaccess_autoproctor_upgrade_add_session_fields($dbman) {
    global $DB;

    $table = new xmldb_table('quizaccess_autoproctor_sessions');

    // Define new fields with default values.
    $quizidfield = new xmldb_field('quiz_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $startedatfield = new xmldb_field('started_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $endedatfield = new xmldb_field('ended_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $trackingfield = new xmldb_field('tracking_options', XMLDB_TYPE_TEXT, null, null, null, null, null);

    // Add fields if they don't exist.
    if (!$dbman->field_exists($table, $quizidfield)) {
        $dbman->add_field($table, $quizidfield);
    }
    if (!$dbman->field_exists($table, $startedatfield)) {
        $dbman->add_field($table, $startedatfield);
    }
    if (!$dbman->field_exists($table, $endedatfield)) {
        $dbman->add_field($table, $endedatfield);
    }
    if (!$dbman->field_exists($table, $trackingfield)) {
        $dbman->add_field($table, $trackingfield);
    }

    // Add foreign key for quiz_id.
    $key = new xmldb_key('quiz_id', XMLDB_KEY_FOREIGN, ['quiz_id'], 'quiz', ['id']);
    $dbman->add_key($table, $key);

    // Update existing records with default value.
    $DB->execute("UPDATE {quizaccess_autoproctor_sessions} SET tracking_options = ? WHERE tracking_options IS NULL", ['{}']);

    // Now modify the field to be NOT NULL.
    $trackingfieldnotnull = new xmldb_field('tracking_options', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, '{}');
    $dbman->change_field_notnull($table, $trackingfieldnotnull);
}

/**
 * Upgrade step to drop deprecated session fields.
 *
 * @param database_manager $dbman The database manager.
 * @return void
 */
function quizaccess_autoproctor_upgrade_drop_deprecated_fields($dbman) {
    debugging('Executing upgrade step for dropping session_id and status fields', DEBUG_DEVELOPER);

    $table = new xmldb_table('quizaccess_autoproctor_sessions');

    // Define fields to be dropped.
    $sessionidfield = new xmldb_field('session_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
    $statusfield = new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');

    // Drop fields if they exist.
    if ($dbman->field_exists($table, $sessionidfield)) {
        $dbman->drop_field($table, $sessionidfield);
    }
    if ($dbman->field_exists($table, $statusfield)) {
        $dbman->drop_field($table, $statusfield);
    }
}

/**
 * Upgrade step to create the autoproctor settings table.
 *
 * @param database_manager $dbman The database manager.
 * @return void
 */
function quizaccess_autoproctor_upgrade_create_settings_table($dbman) {
    $table = new xmldb_table('quizaccess_autoproctor');

    // Add fields.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('quiz_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('proctoring_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('tracking_options', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, '{}');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    // Add keys.
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('quiz_id', XMLDB_KEY_FOREIGN, ['quiz_id'], 'quiz', ['id']);

    // Create the table if it doesn't exist.
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }
}

/**
 * Upgrade step to drop ended_at field.
 *
 * @param database_manager $dbman The database manager.
 * @return void
 */
function quizaccess_autoproctor_upgrade_drop_ended_at($dbman) {
    $table = new xmldb_table('quizaccess_autoproctor_sessions');
    $endedatfield = new xmldb_field('ended_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    if ($dbman->field_exists($table, $endedatfield)) {
        $dbman->drop_field($table, $endedatfield);
    }
}

/**
 * Upgrade step to recreate settings table with correct schema.
 *
 * @param database_manager $dbman The database manager.
 * @return void
 */
function quizaccess_autoproctor_upgrade_recreate_settings_table($dbman) {
    $table = new xmldb_table('quizaccess_autoproctor');

    // Drop table if it exists with wrong schema.
    if ($dbman->table_exists($table)) {
        $dbman->drop_table($table);
    }

    // Create table with correct schema.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('quiz_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('proctoring_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('tracking_options', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('quiz_id', XMLDB_KEY_FOREIGN, ['quiz_id'], 'quiz', ['id']);

    $dbman->create_table($table);
}

/**
 * Upgrade step to recreate sessions table with correct schema.
 *
 * @param database_manager $dbman The database manager.
 * @return void
 */
function quizaccess_autoproctor_upgrade_recreate_sessions_table($dbman) {
    $table = new xmldb_table('quizaccess_autoproctor_sessions');

    // Drop table if it exists with wrong schema.
    if ($dbman->table_exists($table)) {
        $dbman->drop_table($table);
    }

    // Create table with correct schema.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('quiz_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('quiz_attempt_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('test_attempt_id', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
    $table->add_field('tracking_options', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
    $table->add_field('started_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('quiz_attempt_id', XMLDB_KEY_FOREIGN, ['quiz_attempt_id'], 'quiz_attempts', ['id']);

    $dbman->create_table($table);
}

/**
 * Upgrade step to add unique index on quiz_attempt_id.
 *
 * @param database_manager $dbman The database manager.
 * @return void
 */
function quizaccess_autoproctor_upgrade_add_unique_index($dbman) {
    $table = new xmldb_table('quizaccess_autoproctor_sessions');
    $index = new xmldb_index('quiz_attempt_id_unique', XMLDB_INDEX_UNIQUE, ['quiz_attempt_id']);

    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
}

/**
 * Function to upgrade quizaccess_autoproctor.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Result.
 */
function xmldb_quizaccess_autoproctor_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024111106) {
        quizaccess_autoproctor_upgrade_add_session_fields($dbman);
        upgrade_plugin_savepoint(true, 2024111106, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2024111109) {
        quizaccess_autoproctor_upgrade_drop_deprecated_fields($dbman);
        upgrade_plugin_savepoint(true, 2024111109, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2024111112) {
        quizaccess_autoproctor_upgrade_create_settings_table($dbman);
        upgrade_plugin_savepoint(true, 2024111112, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2024120601) {
        quizaccess_autoproctor_upgrade_drop_ended_at($dbman);
        upgrade_plugin_savepoint(true, 2024120601, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2025022501) {
        quizaccess_autoproctor_upgrade_recreate_settings_table($dbman);
        upgrade_plugin_savepoint(true, 2025022501, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2025022502) {
        quizaccess_autoproctor_upgrade_recreate_sessions_table($dbman);
        upgrade_plugin_savepoint(true, 2025022502, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2025022801) {
        quizaccess_autoproctor_upgrade_add_unique_index($dbman);
        upgrade_plugin_savepoint(true, 2025022801, 'quizaccess', 'autoproctor');
    }

    return true;
}
