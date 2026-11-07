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
 * Gets the text out of a PDF.
 *
 * pdftotext is tried first: it is free, local and fast. Adobe PDF Services is
 * the fallback for documents pdftotext cannot read, such as scans.
 *
 * @package    block_alle
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_extractor {
    /**
     * Constructor.
     *
     * @param string $pdftotext Path to the pdftotext executable, empty to skip it.
     * @param adobe_pdf_services|null $adobe Adobe client, null when not configured.
     */
    public function __construct(
        /** @var string Path to the pdftotext executable. */
        protected string $pdftotext = '',
        /** @var adobe_pdf_services|null Adobe client. */
        protected ?adobe_pdf_services $adobe = null,
    ) {
    }

    /**
     * Build the extractor from the plugin settings.
     *
     * @return self
     */
    public static function from_config(): self {
        $config = get_config('block_alle');
        $adobe = null;
        if (!empty($config->adobe_client_id) && !empty($config->adobe_client_secret)) {
            $adobe = new adobe_pdf_services($config->adobe_client_id, $config->adobe_client_secret);
        }
        return new self((string) ($config->pathtopdftotext ?? ''), $adobe);
    }

    /**
     * Extract the text of a PDF.
     *
     * @param string $path Local path of the PDF.
     * @return string
     * @throws \moodle_exception When no method could read the document.
     */
    public function extract(string $path): string {
        $text = $this->run_pdftotext($path);
        if ($text !== '') {
            return $text;
        }
        if ($this->adobe !== null) {
            return $this->adobe->extract_text($path);
        }
        throw new \moodle_exception('error:pdfnotreadable', 'block_alle');
    }

    /**
     * Run pdftotext, returning an empty string when it is unavailable or finds no text.
     *
     * @param string $path
     * @return string
     */
    protected function run_pdftotext(string $path): string {
        if ($this->pdftotext === '' || !is_executable($this->pdftotext)) {
            return '';
        }
        $output = $path . '.txt';
        $command = escapeshellarg($this->pdftotext) . ' -enc UTF-8 ' . escapeshellarg($path) . ' ' . escapeshellarg($output);
        exec($command . ' 2>/dev/null', $ignored, $status);
        if ($status !== 0 || !is_readable($output)) {
            return '';
        }
        $text = trim((string) file_get_contents($output));
        @unlink($output);
        return $text;
    }
}
