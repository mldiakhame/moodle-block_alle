/**
 * UGB Chatbot — main AMD module.
 * Handles chat, file upload (with drag-and-drop), and prompt management.
 *
 * @copyright 2025 Université TÉLUQ and the UNIVERSITÉ GASTON BERGER DE SAINT-LOUIS
 */

define(['jquery', 'core/str', 'core/ajax'], function($, str, ajax) {

    // =========================================================================
    // Markdown renderer
    // =========================================================================
    function renderMarkdown(text) {
        let s = text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        s = s.replace(/```(\w*)\n([\s\S]*?)```/g, function(_, lang, code) {
            return '<pre><code class="language-' + (lang || 'text') + '">' + code.trimEnd() + '</code></pre>';
        });
        s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
        s = s.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
        s = s.replace(/^&gt;\s?(.+)/gm, '<blockquote>$1</blockquote>');
        s = s.replace(/((?:^[-*+]\s.+\n?)+)/gm, function(block) {
            return '<ul>' + block.trim().split('\n').map(function(l) {
                return '<li>' + l.replace(/^[-*+]\s/, '') + '</li>';
            }).join('') + '</ul>';
        });
        s = s.replace(/((?:^\d+\.\s.+\n?)+)/gm, function(block) {
            return '<ol>' + block.trim().split('\n').map(function(l) {
                return '<li>' + l.replace(/^\d+\.\s/, '') + '</li>';
            }).join('') + '</ol>';
        });
        s = s.replace(/^### (.+)/gm, '<h5>$1</h5>');
        s = s.replace(/^## (.+)/gm,  '<h4>$1</h4>');
        s = s.replace(/^# (.+)/gm,   '<h3>$1</h3>');
        s = s.replace(/^---$/gm, '<hr>');
        s = s.replace(/\n\n+/g, '</p><p>').replace(/\n/g, '<br>');
        s = '<p>' + s + '</p>';
        s = s.replace(/<p>(<(?:pre|ul|ol|h[3-6]|hr|blockquote)[^>]*>)/g, '$1');
        s = s.replace(/(<\/(?:pre|ul|ol|h[3-6]|hr|blockquote)>)<\/p>/g, '$1');
        return s;
    }

    // =========================================================================
    // UI helpers
    // =========================================================================
    function scrollToBottom(el) { el.scrollTop = el.scrollHeight; }

    function escapeHtml(t) {
        return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function botAvatarSvg() {
        return '<svg viewBox="0 0 24 24" fill="none">' +
            '<circle cx="12" cy="12" r="12" fill="url(#hg2)"/>' +
            '<path d="M8 10a4 4 0 0 1 8 0v2a4 4 0 0 1-8 0v-2z" fill="white" opacity=".9"/>' +
            '<defs><linearGradient id="hg2" x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse">' +
            '<stop stop-color="#6366f1"/><stop offset="1" stop-color="#8b5cf6"/>' +
            '</linearGradient></defs></svg>';
    }

    function appendUserMessage(el, text) {
        var row = document.createElement('div');
        row.className = 'chatbot-message user-msg';
        row.innerHTML = '<div class="msg-bubble">' + escapeHtml(text) + '</div>';
        el.appendChild(row);
        scrollToBottom(el);
    }

    function appendTypingIndicator(el) {
        var row = document.createElement('div');
        row.className = 'chatbot-message bot-msg typing-row';
        row.innerHTML =
            '<div class="msg-avatar" aria-hidden="true">' + botAvatarSvg() + '</div>' +
            '<div class="msg-bubble"><div class="typing-indicator" aria-label="..." role="status">' +
            '<span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>' +
            '</div></div>';
        el.appendChild(row);
        scrollToBottom(el);
        return row;
    }

    function replaceTypingWithAnswer(row, el, answer) {
        var newRow = document.createElement('div');
        newRow.className = 'chatbot-message bot-msg';
        newRow.innerHTML =
            '<div class="msg-avatar" aria-hidden="true">' + botAvatarSvg() + '</div>' +
            '<div class="msg-bubble">' + renderMarkdown(answer) + '</div>';
        row.replaceWith(newRow);
        scrollToBottom(el);
    }

    function showError(el, msg) {
        el.textContent = msg;
        setTimeout(function() { el.textContent = ''; }, 8000);
    }

    // =========================================================================
    // Upload status bar
    // =========================================================================
    function showUploadStatus(scope, count) {
        var bar   = document.querySelector(scope + ' .cb-upload-status');
        var label = document.querySelector(scope + ' .cb-upload-label');
        if (!bar) { return; }
        if (label) {
            label.textContent = 'Indexation de ' + count + ' fichier' + (count > 1 ? 's' : '') + ' en cours…';
        }
        bar.style.display = 'flex';
    }

    function hideUploadStatus(scope) {
        var bar = document.querySelector(scope + ' .cb-upload-status');
        if (bar) { bar.style.display = 'none'; }
    }

    // =========================================================================
    // File helpers
    // =========================================================================
    function convertFilesToBase64(files) {
        return Promise.all(files.map(function(file) {
            return new Promise(function(resolve, reject) {
                var reader = new FileReader();
                reader.onload = function() {
                    var parts = reader.result.split(',');
                    resolve({ filename: file.name, filecontent: parts[1] || '' });
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }));
    }

    function validateFile(file) {
        if (file.size > 10 * 1024 * 1024) {
            return { valid: false, message: 'File exceeds 10 MB limit: ' + file.name };
        }
        var ext = '.' + file.name.split('.').pop().toLowerCase();
        if (['.txt', '.text', '.md', '.pdf'].indexOf(ext) === -1) {
            return { valid: false, message: 'Unsupported file type: ' + file.name };
        }
        return { valid: true };
    }

    // =========================================================================
    // Module
    // =========================================================================
    return {
        init: function(wwwroot, sesskey, userid, courseid) {

            var scope      = '.block_uteluqchatbot';
            var messagesEl = document.querySelector(scope + ' #chat-messages');
            var errorEl    = document.querySelector(scope + ' #error-message');
            var chatForm   = document.querySelector(scope + ' #chatform');
            var questionEl = document.querySelector(scope + ' #question');

            // Single source of truth for selected upload files
            var accumulatedFiles = [];

            // ---- Auto-resize textarea ----
            if (questionEl) {
                questionEl.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                    this.style.overflowY = this.scrollHeight > 120 ? 'auto' : 'hidden';
                });
                questionEl.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        if (chatForm) { chatForm.dispatchEvent(new Event('submit', {bubbles:true, cancelable:true})); }
                    }
                });
            }

            // ---- Prompt modal ----
            window.togglePromptModal = function() {
                var modal = document.querySelector(scope + ' #promptModal');
                if (!modal) { return; }
                modal.classList.toggle('show');
                modal.style.display = modal.classList.contains('show') ? 'flex' : 'none';
            };

            // ---- File upload modal ----
            function openFileUploadModal() {
                var modal = document.querySelector('#fileUploadModal');
                if (modal) { modal.style.display = 'flex'; }
            }

            function closeFileUploadModal() {
                var modal = document.querySelector('#fileUploadModal');
                if (modal) { modal.style.display = 'none'; }
                var container = document.querySelector('#uploadedFilesContainer');
                if (container) { container.innerHTML = ''; }
                var form = document.querySelector('#fileUploadForm');
                if (form) { form.reset(); }
                accumulatedFiles = [];
            }

            window.toggleFileUploadModal = function() {
                var modal = document.querySelector('#fileUploadModal');
                if (!modal) { return; }
                if (modal.style.display === 'flex') {
                    closeFileUploadModal();
                } else {
                    openFileUploadModal();
                }
            };

            // ---- File display ----
            function refreshFileList() {
                var container = document.querySelector('#uploadedFilesContainer');
                if (!container) { return; }
                container.innerHTML = '';
                accumulatedFiles.forEach(function(file, idx) {
                    var item = document.createElement('div');
                    item.className = 'uploaded-file';
                    var info = document.createElement('div');
                    info.style.cssText = 'display:flex;flex-direction:column';
                    var name = document.createElement('span');
                    name.className = 'file-name';
                    name.style.fontWeight = 'bold';
                    name.textContent = file.name;
                    var size = document.createElement('span');
                    size.className = 'file-size';
                    size.style.cssText = 'font-size:0.9em;color:#666';
                    size.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                    info.appendChild(name);
                    info.appendChild(size);
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'remove-file-btn';
                    btn.setAttribute('aria-label', 'Remove file');
                    btn.style.marginLeft = 'auto';
                    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M19 6.41L17.59 5L12 10.59L6.41 5L5 6.41L10.59 12L5 17.59L6.41 19L12 13.41L17.59 19L19 17.59L13.41 12L19 6.41Z" fill="currentColor"/></svg>';
                    (function(i) {
                        btn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            accumulatedFiles.splice(i, 1);
                            refreshFileList();
                        });
                    })(idx);
                    item.appendChild(info);
                    item.appendChild(btn);
                    container.appendChild(item);
                });
            }

            function addFiles(newFiles) {
                Array.from(newFiles).forEach(function(f) {
                    var v = validateFile(f);
                    if (!v.valid) {
                        var errDiv = document.createElement('div');
                        errDiv.style.cssText = 'color:red;margin-top:8px;font-size:0.85rem';
                        errDiv.textContent = v.message;
                        var c = document.querySelector('#uploadedFilesContainer');
                        if (c) { c.appendChild(errDiv); }
                        return;
                    }
                    if (!accumulatedFiles.find(function(x) { return x.name === f.name; })) {
                        accumulatedFiles.push(f);
                    }
                });
                refreshFileList();
            }

            // ---- File input listener ----
            var fileInput = document.querySelector('#file');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    addFiles(e.target.files);
                    // Reset input value so the same file can be re-selected if removed
                    fileInput.value = '';
                });

                var uploadBox = document.querySelector('.file-upload-box');
                if (uploadBox) {
                    uploadBox.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        fileInput.click();
                    });
                }
            }

            // ---- Drag and drop ----
            var dropZone = document.querySelector('.file-upload-container');
            if (dropZone) {
                ['dragenter','dragover','dragleave','drop'].forEach(function(ev) {
                    dropZone.addEventListener(ev, function(e) { e.preventDefault(); e.stopPropagation(); }, false);
                    document.body.addEventListener(ev, function(e) { e.preventDefault(); e.stopPropagation(); }, false);
                });
                ['dragenter','dragover'].forEach(function(ev) {
                    dropZone.addEventListener(ev, function() {
                        dropZone.style.borderColor = '#007bff';
                        dropZone.style.backgroundColor = '#f8f9fa';
                    }, false);
                });
                ['dragleave','drop'].forEach(function(ev) {
                    dropZone.addEventListener(ev, function() {
                        dropZone.style.borderColor = '';
                        dropZone.style.backgroundColor = '';
                    }, false);
                });
                dropZone.addEventListener('drop', function(e) {
                    addFiles(e.dataTransfer.files);
                }, false);
            }

            // ---- Chat form ----
            if (chatForm) {
                chatForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var question = questionEl ? questionEl.value.trim() : '';
                    if (!question) { return; }
                    if (questionEl) { questionEl.value = ''; questionEl.style.height = ''; }

                    appendUserMessage(messagesEl, question);
                    var typingRow = appendTypingIndicator(messagesEl);

                    ajax.call([{
                        methodname: 'block_uteluqchatbot_send_question',
                        args: { question: question, userid: userid, courseid: courseid, sansrag: false }
                    }])[0]
                    .then(function(data) {
                        if (data.status === 'error') {
                            typingRow.remove();
                            showError(errorEl, data.error || 'Error');
                            return;
                        }
                        replaceTypingWithAnswer(typingRow, messagesEl, data.answer || '');
                    })
                    .catch(function(err) {
                        typingRow.remove();
                        showError(errorEl, (err && err.message) ? err.message : 'An error occurred');
                    });
                });
            }

            // ---- Prompt form ----
            var promptForm = document.querySelector(scope + ' #promptform');
            if (promptForm) {
                promptForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var prompttextEl = document.querySelector(scope + ' #prompttext');
                    var prompttext   = prompttextEl ? prompttextEl.value : '';
                    ajax.call([{
                        methodname: 'block_uteluqchatbot_save_prompt',
                        args: { prompttext: prompttext, userid: userid, courseid: courseid }
                    }])[0]
                    .then(function(data) {
                        if (data.status === 'error') { showError(errorEl, data.error || 'Error'); return; }
                        window.togglePromptModal();
                        setTimeout(function() { location.reload(); }, 1200);
                    })
                    .catch(function(err) {
                        showError(errorEl, (err && err.message) ? err.message : 'Error saving prompt');
                    });
                });
            }

            // ---- File upload form ----
            var fileUploadForm = document.querySelector('#fileUploadForm');
            if (fileUploadForm) {
                fileUploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (accumulatedFiles.length === 0) {
                        showError(errorEl, 'No files selected.');
                        return;
                    }

                    // Snapshot BEFORE closing modal clears accumulatedFiles
                    var filesToUpload = accumulatedFiles.slice();
                    var submitBtn     = fileUploadForm.querySelector('[type="submit"]');

                    closeFileUploadModal();
                    showUploadStatus(scope, filesToUpload.length);
                    if (submitBtn) { submitBtn.disabled = true; }

                    convertFilesToBase64(filesToUpload)
                    .then(function(filesData) {
                        return ajax.call([{
                            methodname: 'block_uteluqchatbot_upload_files',
                            args: { courseid: courseid, files: filesData }
                        }])[0];
                    })
                    .then(function(response) {
                        hideUploadStatus(scope);
                        if (submitBtn) { submitBtn.disabled = false; }
                        if (response && response.success) {
                            setTimeout(function() { location.reload(); }, 800);
                        } else {
                            showError(errorEl, (response && response.message) ? response.message : 'Error processing files');
                        }
                    })
                    .catch(function(err) {
                        hideUploadStatus(scope);
                        if (submitBtn) { submitBtn.disabled = false; }
                        showError(errorEl, (err && err.message) ? err.message : 'Error uploading files');
                    });
                });
            }
        }
    };
});
