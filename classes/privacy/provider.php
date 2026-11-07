<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_alle\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for ALL-E.
 *
 * Learners' recent questions and answers are stored per course. Questions,
 * course passages and teacher prompts are sent to the AI services configured
 * by the administrator. Teacher prompts belong to the course: when a teacher's
 * data is deleted, the prompt is kept but no longer linked to them.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_alle_conversations', [
            'courseid' => 'privacy:metadata:conversations:courseid',
            'userid' => 'privacy:metadata:conversations:userid',
            'question' => 'privacy:metadata:conversations:question',
            'answer' => 'privacy:metadata:conversations:answer',
            'timecreated' => 'privacy:metadata:conversations:timecreated',
        ], 'privacy:metadata:conversations');

        $collection->add_database_table('block_alle_prompts', [
            'courseid' => 'privacy:metadata:prompts:courseid',
            'prompt' => 'privacy:metadata:prompts:prompt',
            'usermodified' => 'privacy:metadata:prompts:usermodified',
            'timemodified' => 'privacy:metadata:prompts:timemodified',
        ], 'privacy:metadata:prompts');

        $collection->add_external_location_link('languagemodel', [
            'question' => 'privacy:metadata:languagemodel:question',
            'history' => 'privacy:metadata:languagemodel:history',
            'prompt' => 'privacy:metadata:languagemodel:prompt',
        ], 'privacy:metadata:languagemodel');

        $collection->add_external_location_link('embeddings', [
            'question' => 'privacy:metadata:embeddings:question',
        ], 'privacy:metadata:embeddings');

        $collection->add_external_location_link('vectorstore', [
            'courseid' => 'privacy:metadata:vectorstore:courseid',
        ], 'privacy:metadata:vectorstore');

        $collection->add_external_location_link('adobepdf', [
            'file' => 'privacy:metadata:adobepdf:file',
        ], 'privacy:metadata:adobepdf');

        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {block_alle_conversations} c ON c.courseid = ctx.instanceid AND ctx.contextlevel = :level1
                 WHERE c.userid = :userid1
                 UNION
                SELECT ctx.id
                  FROM {context} ctx
                  JOIN {block_alle_prompts} p ON p.courseid = ctx.instanceid AND ctx.contextlevel = :level2
                 WHERE p.usermodified = :userid2";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'level1' => CONTEXT_COURSE,
            'userid1' => $userid,
            'level2' => CONTEXT_COURSE,
            'userid2' => $userid,
        ]);
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $params = ['courseid' => $context->instanceid];
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {block_alle_conversations} WHERE courseid = :courseid',
            $params
        );
        $userlist->add_from_sql(
            'usermodified',
            'SELECT usermodified FROM {block_alle_prompts} WHERE courseid = :courseid',
            $params
        );
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $subcontext = [get_string('pluginname', 'block_alle')];

            $conversations = $DB->get_records(
                'block_alle_conversations',
                ['courseid' => $context->instanceid, 'userid' => $userid],
                'timecreated ASC, id ASC'
            );
            if ($conversations) {
                $data = array_map(fn($c) => (object) [
                    'question' => $c->question,
                    'answer' => $c->answer,
                    'timecreated' => transform::datetime($c->timecreated),
                ], array_values($conversations));
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:conversations', 'block_alle')]),
                    (object) ['conversations' => $data]
                );
            }

            $prompt = $DB->get_record('block_alle_prompts', ['courseid' => $context->instanceid, 'usermodified' => $userid]);
            if ($prompt) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:prompt', 'block_alle')]),
                    (object) ['prompt' => $prompt->prompt, 'timemodified' => transform::datetime($prompt->timemodified)]
                );
            }
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof \context_course) {
            return;
        }
        $DB->delete_records('block_alle_conversations', ['courseid' => $context->instanceid]);
        $DB->delete_records('block_alle_prompts', ['courseid' => $context->instanceid]);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $DB->delete_records('block_alle_conversations', ['courseid' => $context->instanceid, 'userid' => $userid]);
            $DB->set_field('block_alle_prompts', 'usermodified', 0, [
                'courseid' => $context->instanceid,
                'usermodified' => $userid,
            ]);
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select('block_alle_conversations', "courseid = :courseid AND userid $insql", $params);
        $DB->set_field_select('block_alle_prompts', 'usermodified', 0, "courseid = :courseid AND usermodified $insql", $params);
    }
}
