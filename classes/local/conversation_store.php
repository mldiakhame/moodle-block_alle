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

namespace block_alle\local;

/**
 * Short conversation memory of a learner in a course.
 *
 * Only the most recent exchanges are kept: they let the assistant notice when
 * a learner did not understand a previous explanation.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation_store {
    /** @var int Number of exchanges kept per learner and course. */
    public const MAX_EXCHANGES = 10;

    /**
     * Recent exchanges, oldest first.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass[] Records with question and answer.
     */
    public static function get_history(int $courseid, int $userid): array {
        global $DB;
        $records = $DB->get_records(
            'block_alle_conversations',
            ['courseid' => $courseid, 'userid' => $userid],
            'timecreated DESC, id DESC',
            'id, question, answer',
            0,
            self::MAX_EXCHANGES
        );
        return array_reverse(array_values($records));
    }

    /**
     * Store an exchange and forget the oldest ones beyond the limit.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $question
     * @param string $answer
     */
    public static function add(int $courseid, int $userid, string $question, string $answer): void {
        global $DB;
        $DB->insert_record('block_alle_conversations', (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'question' => $question,
            'answer' => $answer,
            'timecreated' => time(),
        ]);

        $ids = $DB->get_fieldset_sql(
            'SELECT id
               FROM {block_alle_conversations}
              WHERE courseid = :courseid AND userid = :userid
           ORDER BY timecreated DESC, id DESC',
            ['courseid' => $courseid, 'userid' => $userid]
        );
        $obsolete = array_slice($ids, self::MAX_EXCHANGES);
        if ($obsolete) {
            $DB->delete_records_list('block_alle_conversations', 'id', $obsolete);
        }
    }
}
