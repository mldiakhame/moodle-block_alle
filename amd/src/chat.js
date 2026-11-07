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

/**
 * Chat interface of ALL-E: questions, teacher prompt and document upload.
 *
 * @module     block_alle/chat
 * @copyright  2025 Université TÉLUQ and Université Gaston Berger de Saint-Louis
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str'], function(Ajax, Str) {

    const MAX_FILE_SIZE = 10 * 1024 * 1024;
    const EXTENSIONS = ['pdf', 'txt', 'text', 'md'];

    /**
     * Escape text before it is inserted as HTML.
     *
     * @param {String} text
     * @returns {String}
     */
    const escapeHtml = (text) => text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    /**
     * Render the small Markdown subset the assistant uses. The text is escaped first.
     *
     * @param {String} text
     * @returns {String} HTML
     */
    const renderMarkdown = (text) => {
        let html = escapeHtml(text);
        html = html.replace(/```\w*\n([\s\S]*?)```/g, (match, code) => '<pre><code>' + code.trimEnd() + '</code></pre>');
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
        html = html.replace(/^#{1,3} (.+)$/gm, '<h5>$1</h5>');
        html = html.replace(/((?:^[-*+] .+\n?)+)/gm, (block) => '<ul>' + block.trim().split('\n')
            .map((line) => '<li>' + line.replace(/^[-*+] /, '') + '</li>').join('') + '</ul>');
        html = html.replace(/((?:^\d+\. .+\n?)+)/gm, (block) => '<ol>' + block.trim().split('\n')
            .map((line) => '<li>' + line.replace(/^\d+\. /, '') + '</li>').join('') + '</ol>');
        html = '<p>' + html.replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>') + '</p>';
        return html.replace(/<p>(<(?:pre|ul|ol|h5)>)/g, '$1').replace(/(<\/(?:pre|ul|ol|h5)>)<\/p>/g, '$1');
    };

    /**
     * Read a file as base64, without the data URL prefix.
     *
     * @param {File} file
     * @returns {Promise<String>}
     */
    const toBase64 = (file) => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result).split(',')[1] || '');
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(file);
    });

    /**
     * Wire one instance of the block.
     *
     * @param {String} rootId ID of the block root element.
     * @param {Number} courseId
     */
    const init = (rootId, courseId) => {
        const root = document.getElementById(rootId);
        if (!root) {
            return;
        }
        const region = (name) => root.querySelector('[data-region="' + name + '"]');
        const messages = region('messages');
        const errorBox = region('error');
        const questionForm = region('questionform');
        const questionInput = questionForm.querySelector('textarea');
        let selectedFiles = [];

        const showError = (message) => {
            errorBox.textContent = message;
            setTimeout(() => {
                errorBox.textContent = '';
            }, 8000);
        };

        const addMessage = (cssClass, html) => {
            const row = document.createElement('div');
            row.className = 'chatbot-message ' + cssClass;
            row.innerHTML = '<div class="msg-bubble">' + html + '</div>';
            messages.appendChild(row);
            messages.scrollTop = messages.scrollHeight;
            return row;
        };

        const toggleDialog = (dialog, open) => {
            dialog.hidden = !open;
            dialog.classList.toggle('show', open);
            if (open) {
                const field = dialog.querySelector('textarea, input');
                if (field) {
                    field.focus();
                }
            }
        };

        // Questions.
        questionInput.addEventListener('input', () => {
            questionInput.style.height = 'auto';
            questionInput.style.height = Math.min(questionInput.scrollHeight, 120) + 'px';
        });
        questionInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                questionForm.requestSubmit();
            }
        });
        questionForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const question = questionInput.value.trim();
            if (!question) {
                return;
            }
            questionInput.value = '';
            questionInput.style.height = '';
            addMessage('user-msg', escapeHtml(question));
            const typing = addMessage('bot-msg typing-row',
                '<span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>');

            Ajax.call([{methodname: 'block_alle_send_question', args: {courseid: courseId, question: question}}])[0]
                .then((response) => {
                    typing.remove();
                    if (!response.success) {
                        showError(response.error);
                        return null;
                    }
                    addMessage('bot-msg', renderMarkdown(response.answer));
                    return null;
                })
                .catch((error) => {
                    typing.remove();
                    showError(error.message);
                });
        });

        // Teacher tools.
        root.querySelectorAll('[data-action="open"]').forEach((button) => {
            button.addEventListener('click', () => toggleDialog(region(button.dataset.target), true));
        });
        root.querySelectorAll('[data-action="close"]').forEach((button) => {
            button.addEventListener('click', () => toggleDialog(button.closest('.modal'), false));
        });
        root.querySelectorAll('.modal').forEach((dialog) => {
            dialog.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    toggleDialog(dialog, false);
                }
            });
        });

        const promptForm = region('promptform');
        if (promptForm) {
            promptForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const prompt = promptForm.querySelector('textarea').value;
                Ajax.call([{methodname: 'block_alle_save_prompt', args: {courseid: courseId, prompt: prompt}}])[0]
                    .then(() => {
                        toggleDialog(region('promptdialog'), false);
                        return Str.getString('promptsaved', 'block_alle');
                    })
                    .then((message) => addMessage('bot-msg', escapeHtml(message)))
                    .catch((error) => showError(error.message));
            });
        }

        const uploadForm = region('uploadform');
        if (uploadForm) {
            const fileInput = uploadForm.querySelector('input[type="file"]');
            const fileList = region('filelist');
            const dropzone = region('dropzone');

            const refreshList = () => {
                fileList.innerHTML = '';
                selectedFiles.forEach((file, index) => {
                    const item = document.createElement('li');
                    item.className = 'uploaded-file';
                    item.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB) ';
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'btn btn-link btn-sm remove-file-btn';
                    remove.textContent = '×';
                    remove.setAttribute('aria-label', file.name);
                    remove.addEventListener('click', () => {
                        selectedFiles.splice(index, 1);
                        refreshList();
                    });
                    item.appendChild(remove);
                    fileList.appendChild(item);
                });
            };

            const addFiles = (files) => {
                Array.from(files).forEach((file) => {
                    const extension = file.name.split('.').pop().toLowerCase();
                    if (EXTENSIONS.indexOf(extension) === -1) {
                        Str.getString('error:filetype', 'block_alle').then(showError).catch(() => null);
                        return;
                    }
                    if (file.size > MAX_FILE_SIZE) {
                        Str.getString('error:filetoobig', 'block_alle', '10 MB').then(showError).catch(() => null);
                        return;
                    }
                    if (!selectedFiles.some((selected) => selected.name === file.name)) {
                        selectedFiles.push(file);
                    }
                });
                refreshList();
            };

            fileInput.addEventListener('change', () => {
                addFiles(fileInput.files);
                fileInput.value = '';
            });
            ['dragenter', 'dragover'].forEach((type) => dropzone.addEventListener(type, (e) => {
                e.preventDefault();
                dropzone.classList.add('highlight');
            }));
            ['dragleave', 'drop'].forEach((type) => dropzone.addEventListener(type, (e) => {
                e.preventDefault();
                dropzone.classList.remove('highlight');
            }));
            dropzone.addEventListener('drop', (e) => addFiles(e.dataTransfer.files));

            uploadForm.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!selectedFiles.length) {
                    return;
                }
                const files = selectedFiles.slice();
                selectedFiles = [];
                refreshList();
                toggleDialog(region('uploaddialog'), false);
                const status = region('uploadstatus');
                status.hidden = false;
                status.classList.add('show');

                const readFile = (file) => toBase64(file).then((content) => ({filename: file.name, content: content}));
                Promise.all(files.map(readFile))
                    .then((payload) => Ajax.call([{
                        methodname: 'block_alle_upload_files',
                        args: {courseid: courseId, files: payload},
                    }])[0])
                    .then((response) => {
                        const problems = response.errors.map((error) =>
                            (error.filename ? error.filename + ': ' : '') + error.message);
                        if (problems.length) {
                            showError(problems.join(' | '));
                        }
                        if (!response.success) {
                            return null;
                        }
                        return Str.getString('indexed', 'block_alle', {files: response.indexed, passages: response.passages});
                    })
                    .then((message) => {
                        if (message) {
                            addMessage('bot-msg', escapeHtml(message));
                        }
                        return null;
                    })
                    .catch((error) => showError(error.message))
                    .finally(() => {
                        status.hidden = true;
                        status.classList.remove('show');
                    });
            });
        }
    };

    return {init: init};
});
