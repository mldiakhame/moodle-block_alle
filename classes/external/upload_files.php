<?php
/**
 * External API for file upload and indexing
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 */

namespace block_uteluqchatbot\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_course;
use Exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class upload_files extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, get_string('course_id', 'block_uteluqchatbot')),
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'filename' => new external_value(PARAM_TEXT, get_string('file_name', 'block_uteluqchatbot')),
                    'filecontent' => new external_value(PARAM_RAW, get_string('file_content_base64', 'block_uteluqchatbot')),
                ])
            )
        ]);
    }

    /**
     * Extract text from a PDF file.
     * Tries pdftotext first (free, no quota). Falls back to Adobe PDF Services if available.
     * @param string $filepath Absolute path to the PDF on disk.
     * @param string $filename Original filename (for error messages).
     * @return string Extracted plain text.
     * @throws Exception on failure.
     */
    private static function extract_pdf_text($filepath, $filename) {
        // 1. Try pdftotext (poppler-utils — available on most Linux servers).
        $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null'));
        if (!empty($pdftotext) && is_executable($pdftotext)) {
            $dest_txt = $filepath . '.txt';
            $cmd      = escapeshellcmd($pdftotext) . ' -enc UTF-8 '
                      . escapeshellarg($filepath) . ' '
                      . escapeshellarg($dest_txt)
                      . ' 2>&1';
            $out = shell_exec($cmd);
            if (file_exists($dest_txt)) {
                $text = file_get_contents($dest_txt);
                unlink($dest_txt);
                if (!empty(trim($text))) {
                    return $text;
                }
            }
            // pdftotext produced no text (scanned PDF?) — fall through to Adobe.
        }

        // 2. Fall back to Adobe PDF Services.
        $adobe_id     = get_config('block_uteluqchatbot', 'adobe_pdf_client_id');
        $adobe_secret = get_config('block_uteluqchatbot', 'adobe_pdf_client_secret');

        if (empty($adobe_id) || empty($adobe_secret)) {
            throw new Exception(
                'Cannot extract text from "' . $filename . '": pdftotext is not available and Adobe PDF credentials are not configured.'
            );
        }

        if (!class_exists('\block_uteluqchatbot\pdf_extract_api')) {
            throw new Exception(get_string('pdf_extractor_not_found', 'block_uteluqchatbot'));
        }

        $extractor = new \block_uteluqchatbot\pdf_extract_api($adobe_id, $adobe_secret);
        return $extractor->process_pdf($filepath);
    }

    /**
     * Get file extension
     * @param string $filename
     * @return string
     */
    private static function get_file_extension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Check if file is PDF
     * @param string $filename
     * @return bool
     */
    private static function is_pdf($filename) {
        return self::get_file_extension($filename) === 'pdf';
    }

    /**
     * Check if file is text file
     * @param string $filename
     * @return bool
     */
    private static function is_text_file($filename) {
        $textExtensions = ['txt', 'text', 'md'];
        return in_array(self::get_file_extension($filename), $textExtensions);
    }

    /**
     * Execute the file upload and indexing
     * @param int $courseid
     * @param array $files
     * @return array
     */
    public static function execute($courseid, $files) {
        global $CFG, $USER, $DB;

        try {
            // Validate parameters
            $params = self::validate_parameters(self::execute_parameters(), [
                'courseid' => $courseid,
                'files' => $files
            ]);

            // Validate context and capabilities
            $context = context_course::instance($params['courseid']);
            self::validate_context($context);
            
            // Check capabilities with more flexible requirement
            try {
                require_capability('moodle/course:update', $context);
            } catch (Exception $e) {
                // Try alternative capabilities
                if (has_capability('moodle/course:managefiles', $context) || 
                    has_capability('moodle/course:view', $context) || 
                    is_enrolled($context, $USER->id)) {
                    // OK
                } else {
                    throw new Exception(get_string('insufficient_permissions', 'block_uteluqchatbot'));
                }
            }

            // Build connector via shared factory (validates config internally).
            try {
                $connector = \block_uteluqchatbot\external\send_question::build_connector();
            } catch (\moodle_exception $e) {
                throw new Exception($e->getMessage());
            }

            $courseName = 'Collection_course_' . $params['courseid'];

            // Reset the Weaviate collection ONCE before processing any file.
            // index_text_file() is called with $recreate=false for every file so
            // that subsequent files append to the same collection instead of
            // destroying what the previous file just indexed.
            if ($connector->collection_exists($courseName)) {
                if (!$connector->delete_collection($courseName)) {
                    throw new Exception('Failed to reset collection: ' . $connector->get_last_error());
                }
            }
            if (!$connector->create_collection($courseName)) {
                throw new Exception('Failed to create collection: ' . $connector->get_last_error());
            }

            // Create or get a temporary directory for the plugin
            $temppath = make_temp_directory('block_uteluqchatbot');
            $uploadDir = $temppath . '/uploads/';

            // If the uploads subdirectory doesn't exist, create it
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new Exception(get_string('failed_create_upload_directory', 'block_uteluqchatbot'));
                }
            }

            $processedFiles = 0;
            $errors = [];

            foreach ($params['files'] as $index => $file) {
                try {
                    // Validate filename
                    if (empty($file['filename'])) {
                        throw new Exception(get_string('empty_filename', 'block_uteluqchatbot'));
                    }

                    // Check if file type is supported
                    if (!self::is_pdf($file['filename']) && !self::is_text_file($file['filename'])) {
                        throw new Exception(get_string('unsupported_file_type', 'block_uteluqchatbot') . $file['filename']);
                    }

                    // Decode base64 content
                    $fileContent = base64_decode($file['filecontent']);
                    if ($fileContent === false) {
                        throw new Exception(get_string('invalid_file_data', 'block_uteluqchatbot') . $file['filename']);
                    }

                    // Generate unique filename
                    $newFileName = uniqid('file_', true) . '-' . clean_filename($file['filename']);
                    $destination = $uploadDir . $newFileName;

                    // Save file temporarily
                    if (file_put_contents($destination, $fileContent) === false) {
                        throw new Exception(get_string('failed_save_file', 'block_uteluqchatbot') . $file['filename']);
                    }

                    $extractedText = '';
                    $destinationTxt = '';

                    // Process based on file type
                    if (self::is_pdf($file['filename'])) {
                        $extractedText = self::extract_pdf_text($destination, $file['filename']);

                        if (empty($extractedText)) {
                            throw new Exception(get_string('failed_extract_pdf_text', 'block_uteluqchatbot') . $file['filename']);
                        }

                        // Generate a unique name for the text file
                        $newFileTxtName = uniqid('file_', true) . '-' . $file['filename'] . '.txt';
                        $destinationTxt = $uploadDir . $newFileTxtName;

                        if (file_put_contents($destinationTxt, $extractedText) === false) {
                            throw new Exception(get_string('failed_save_extracted_text', 'block_uteluqchatbot') . $file['filename']);
                        }

                    } else if (self::is_text_file($file['filename'])) {
                        // Text file processing - use the file directly
                        $destinationTxt = $destination;
                        $extractedText = $fileContent;
                    }

                    // Append chunks to the collection (already created above).
                    $indexed = $connector->index_text_file($destinationTxt, $courseName, $courseName, false);

                    if (!$indexed) {
                        $error = $connector->get_last_error();
                        throw new Exception(get_string('error_indexing_file_unknown', 'block_uteluqchatbot') . $file['filename'] . ': ' . ($error ? $error : get_string('unknown_error', 'block_uteluqchatbot')));
                    }

                    // Clean up temporary files
                    if (file_exists($destinationTxt) && $destinationTxt !== $destination) {
                        unlink($destinationTxt);
                    }
                    if (file_exists($destination)) {
                        unlink($destination);
                    }

                    $processedFiles++;

                } catch (Exception $e) {
                    $errors[] = $file['filename'] . ': ' . $e->getMessage();
                    
                    // Clean up files in case of error
                    if (isset($destinationTxt) && file_exists($destinationTxt) && $destinationTxt !== $destination) {
                        unlink($destinationTxt);
                    }
                    if (isset($destination) && file_exists($destination)) {
                        unlink($destination);
                    }
                }
            }

            // Return results
            if ($processedFiles > 0) {
                $message = $processedFiles . get_string('files_indexed_successfully', 'block_uteluqchatbot');
                if (!empty($errors)) {
                    $message .= get_string('errors_occurred', 'block_uteluqchatbot') . implode('; ', $errors);
                }
                
                return [
                    'success' => true,
                    'message' => $message,
                    'processedfiles' => $processedFiles
                ];
            } else {
                return [
                    'success' => false,
                    'message' => get_string('no_files_processed', 'block_uteluqchatbot') . implode('; ', $errors),
                    'processedfiles' => 0
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'processedfiles' => 0
            ];
        }
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, get_string('operation_successful', 'block_uteluqchatbot')),
            'message' => new external_value(PARAM_TEXT, get_string('response_message', 'block_uteluqchatbot')),
            'processedfiles' => new external_value(PARAM_INT, get_string('processed_files_count', 'block_uteluqchatbot'))
        ]);
    }
}