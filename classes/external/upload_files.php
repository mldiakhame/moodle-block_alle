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

namespace block_alle\external;

use block_alle\local\pdf_extractor;
use block_alle\local\provider_factory;
use block_alle\local\text_chunker;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Replaces the documents the assistant answers from in a course.
 *
 * All files are read and split before anything is sent to the vector store, so
 * a file that cannot be read never wipes the documents already indexed.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_files extends external_api {
    /** @var int Largest accepted file, in bytes. */
    public const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /** @var string[] Accepted extensions. */
    public const EXTENSIONS = ['pdf', 'txt', 'text', 'md'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'filename' => new external_value(PARAM_FILE, 'File name'),
                    'content' => new external_value(PARAM_RAW, 'File content, base64 encoded'),
                ])
            ),
        ]);
    }

    /**
     * Index the files.
     *
     * @param int $courseid
     * @param array $files
     * @return array
     */
    public static function execute(int $courseid, array $files): array {
        ['courseid' => $courseid, 'files' => $files] = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'files' => $files]
        );
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('block/alle:managecontent', $context);
        \core_php_time_limit::raise(600);

        $chunker = new text_chunker();
        $extractor = pdf_extractor::from_config();
        $directory = make_request_directory();
        $passages = [];
        $indexed = 0;
        $errors = [];

        foreach ($files as $file) {
            try {
                $text = self::read_file($file, $directory, $extractor);
                foreach ($chunker->split($chunker->clean($text)) as $chunk) {
                    $passages[] = ['content' => $chunk, 'source' => $file['filename']];
                }
                $indexed++;
            } catch (\moodle_exception $e) {
                $errors[] = ['filename' => $file['filename'], 'message' => $e->getMessage()];
            }
        }

        if (!$passages) {
            return ['success' => false, 'indexed' => 0, 'passages' => 0, 'errors' => $errors];
        }

        try {
            $factory = new provider_factory();
            $vectors = $factory->get_embedder()->embed_batch(array_column($passages, 'content'));
            $store = $factory->get_vector_store();
            $collection = $factory->get_collection_name($courseid);
            $store->reset_collection($collection);
            $store->add_passages($collection, $passages, $vectors, $courseid);
        } catch (\moodle_exception $e) {
            $errors[] = ['filename' => '', 'message' => $e->getMessage()];
            return ['success' => false, 'indexed' => 0, 'passages' => 0, 'errors' => $errors];
        }

        return ['success' => true, 'indexed' => $indexed, 'passages' => count($passages), 'errors' => $errors];
    }

    /**
     * Decode one uploaded file and return its text.
     *
     * @param array $file With filename and base64 content.
     * @param string $directory Temporary directory of the request.
     * @param pdf_extractor $extractor
     * @return string
     * @throws \moodle_exception
     */
    protected static function read_file(array $file, string $directory, pdf_extractor $extractor): string {
        $extension = \core_text::strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new \moodle_exception('error:filetype', 'block_alle');
        }
        $content = base64_decode($file['content'], true);
        if ($content === false || $content === '') {
            throw new \moodle_exception('error:fileunreadable', 'block_alle');
        }
        if (strlen($content) > self::MAX_FILE_SIZE) {
            throw new \moodle_exception('error:filetoobig', 'block_alle', '', display_size(self::MAX_FILE_SIZE));
        }
        if ($extension !== 'pdf') {
            return $content;
        }
        $path = $directory . '/' . uniqid('alle_', true) . '.pdf';
        file_put_contents($path, $content);
        return $extractor->extract($path);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the course documents were replaced'),
            'indexed' => new external_value(PARAM_INT, 'Files indexed'),
            'passages' => new external_value(PARAM_INT, 'Passages stored'),
            'errors' => new external_multiple_structure(new external_single_structure([
                'filename' => new external_value(PARAM_FILE, 'File concerned, empty for global errors'),
                'message' => new external_value(PARAM_TEXT, 'Error message'),
            ])),
        ]);
    }
}
