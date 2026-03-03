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
 * Quiz access proctoring plugin upgrade code
 *
 * @package   quizaccess_autoproctor
 * @copyright 2024 AutoProctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to upgrade quizaccess_autoproctor.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_quizaccess_autoproctor_upgrade($oldversion)
{
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024111106) {
        // Define table
        $table = new xmldb_table('quizaccess_autoproctor_sessions');

        // Define new fields with default values
        $quiz_id = new xmldb_field(
            'quiz_id',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0'
        );
        $started_at = new xmldb_field(
            'started_at',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0'
        );
        $ended_at = new xmldb_field(
            'ended_at',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            null,
            null,
            null
        );
        $tracking_options = new xmldb_field(
            'tracking_options',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,  // Initially allow NULL
            null,
            null
        );

        // Add fields if they don't exist
        if (!$dbman->field_exists($table, $quiz_id)) {
            $dbman->add_field($table, $quiz_id);
        }
        if (!$dbman->field_exists($table, $started_at)) {
            $dbman->add_field($table, $started_at);
        }
        if (!$dbman->field_exists($table, $ended_at)) {
            $dbman->add_field($table, $ended_at);
        }
        if (!$dbman->field_exists($table, $tracking_options)) {
            $dbman->add_field($table, $tracking_options);
        }

        // Add foreign key for quiz_id if needed
        $key = new xmldb_key('quiz_id', XMLDB_KEY_FOREIGN, ['quiz_id'], 'quiz', ['id']);
        $dbman->add_key($table, $key);

        // Update existing records with default value
        $DB->execute("UPDATE {quizaccess_autoproctor_sessions} SET tracking_options = ? WHERE tracking_options IS NULL", ['{}']);

        // Now modify the field to be NOT NULL
        $tracking_options = new xmldb_field(
            'tracking_options',
            XMLDB_TYPE_TEXT,
            null,
            null,
            XMLDB_NOTNULL,
            null,
            '{}'
        );
        $dbman->change_field_notnull($table, $tracking_options);

        upgrade_plugin_savepoint(true, 2024111106, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2024111109) {
        debugging('Executing upgrade step for dropping session_id and status fields', DEBUG_DEVELOPER);
        
        // Remove session_id and status fields from table quizaccess_autoproctor_sessions
        $table = new xmldb_table('quizaccess_autoproctor_sessions');
        
        // Define fields to be dropped
        $session_id = new xmldb_field('session_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $status = new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
    
        // Drop fields if they exist
        if ($dbman->field_exists($table, $session_id)) {
            $dbman->drop_field($table, $session_id);
        }
        if ($dbman->field_exists($table, $status)) {
            $dbman->drop_field($table, $status);
        }
    
        upgrade_plugin_savepoint(true, 2024111109, 'quizaccess', 'autoproctor');
    }
    

    if ($oldversion < 2024111112) {
        // Define table quizaccess_autoproctor
        $table = new xmldb_table('quizaccess_autoproctor');

        // Add fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('quiz_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('proctoring_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('tracking_options', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, '{}');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Add keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('quiz_id', XMLDB_KEY_FOREIGN, ['quiz_id'], 'quiz', ['id']);

        // Create the table if it doesn't exist
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2024111112, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2024120601) {
        // Delete ended_at field from table quizaccess_autoproctor_sessions
        $table = new xmldb_table('quizaccess_autoproctor_sessions');
        $ended_at = new xmldb_field('ended_at', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if ($dbman->field_exists($table, $ended_at)) {
            $dbman->drop_field($table, $ended_at);
        }

        upgrade_plugin_savepoint(true, 2024120601, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2025022501) {
        // Recreate quizaccess_autoproctor table with correct schema
        $table = new xmldb_table('quizaccess_autoproctor');

        // Drop table if it exists with wrong schema
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Create table with correct schema
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('quiz_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('proctoring_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('tracking_options', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('quiz_id', XMLDB_KEY_FOREIGN, ['quiz_id'], 'quiz', ['id']);

        $dbman->create_table($table);

        upgrade_plugin_savepoint(true, 2025022501, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2025022502) {
        // Recreate quizaccess_autoproctor_sessions table with correct schema
        $table = new xmldb_table('quizaccess_autoproctor_sessions');

        // Drop table if it exists with wrong schema
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Create table with correct schema
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

        upgrade_plugin_savepoint(true, 2025022502, 'quizaccess', 'autoproctor');
    }

    if ($oldversion < 2025022801) {
        // Add unique index on quiz_attempt_id to prevent duplicate sessions
        $table = new xmldb_table('quizaccess_autoproctor_sessions');
        $index = new xmldb_index('quiz_attempt_id_unique', XMLDB_INDEX_UNIQUE, ['quiz_attempt_id']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2025022801, 'quizaccess', 'autoproctor');
    }

    return true;
}