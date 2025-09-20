jQuery(document).ready(function($) {
    'use strict';

    var TemplateManager = {
        editors: {},
        currentTemplate: null,

        init: function() {
            this.initTabs();
            this.initEditors();
            this.bindEvents();
            this.loadCurrentTemplate();
        },

        initTabs: function() {
            if ($('.editor-tabs').length) {
                $('.editor-tabs').tabs({
                    activate: function(event, ui) {
                        // Refresh editors when tab is activated
                        if (TemplateManager.editors.body) {
                            TemplateManager.editors.body.refresh();
                        }
                        if (TemplateManager.editors.css) {
                            TemplateManager.editors.css.refresh();
                        }
                    }
                });
            }
        },

        initEditors: function() {
            // Initialize CodeMirror for template body
            if ($('#template-body').length) {
                this.editors.body = CodeMirror.fromTextArea(document.getElementById('template-body'), {
                    mode: 'htmlmixed',
                    theme: 'material',
                    lineNumbers: true,
                    lineWrapping: true,
                    autoCloseTags: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    extraKeys: {
                        'Ctrl-Space': 'autocomplete'
                    }
                });
            }

            // Initialize CodeMirror for custom CSS
            if ($('#template-css').length) {
                this.editors.css = CodeMirror.fromTextArea(document.getElementById('template-css'), {
                    mode: 'css',
                    theme: 'material',
                    lineNumbers: true,
                    lineWrapping: true,
                    autoCloseBrackets: true,
                    matchBrackets: true
                });
            }
        },

        bindEvents: function() {
            // Save template
            $(document).on('click', '#save-template', this.saveTemplate.bind(this));

            // Preview template
            $(document).on('click', '#preview-current, .preview-template', this.previewTemplate.bind(this));

            // Restore version
            $(document).on('click', '.restore-version', this.restoreVersion.bind(this));

            // Delete version
            $(document).on('click', '.delete-version', this.deleteVersion.bind(this));

            // Export template
            $(document).on('click', '.export-template', this.exportTemplate.bind(this));

            // Import template
            $(document).on('click', '#import-template-btn', this.showImportModal.bind(this));
            $(document).on('submit', '#template-import-form', this.importTemplate.bind(this));

            // Insert variable
            $(document).on('click', '.insert-variable', this.insertVariable.bind(this));

            // Modal controls
            $(document).on('click', '.modal-close', this.closeModal.bind(this));
            $(document).on('click', '.template-modal', function(e) {
                if (e.target === this) {
                    TemplateManager.closeModal.call(this, e);
                }
            });

            // Preview controls
            $(document).on('change', '#preview-sample-data', this.updatePreview.bind(this));
            $(document).on('click', '#refresh-preview', this.updatePreview.bind(this));

            // Auto-save (every 2 minutes)
            setInterval(this.autoSave.bind(this), 120000);
        },

        loadCurrentTemplate: function() {
            var templateId = $('#current-template-id').val();
            if (templateId) {
                this.currentTemplate = templateId;
            }
        },

        saveTemplate: function(e) {
            e.preventDefault();

            if (!this.currentTemplate) {
                this.showNotice('error', 'No template selected');
                return;
            }

            var data = {
                action: 'wecoza_save_template',
                nonce: wecoza_templates.nonce,
                template_id: this.currentTemplate,
                subject: $('#template-subject').val(),
                body: this.editors.body ? this.editors.body.getValue() : $('#template-body').val(),
                custom_css: this.editors.css ? this.editors.css.getValue() : $('#template-css').val()
            };

            var $saveBtn = $('#save-template');
            $saveBtn.prop('disabled', true).text('Saving...');

            $.post(wecoza_templates.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        TemplateManager.showNotice('success', wecoza_templates.strings.save_success);
                        // Reload versions sidebar
                        location.reload();
                    } else {
                        TemplateManager.showNotice('error', response.data || wecoza_templates.strings.save_error);
                    }
                })
                .fail(function() {
                    TemplateManager.showNotice('error', wecoza_templates.strings.save_error);
                })
                .always(function() {
                    $saveBtn.prop('disabled', false).text('Save Changes');
                });
        },

        autoSave: function() {
            if (this.currentTemplate && this.hasUnsavedChanges()) {
                this.saveTemplate({ preventDefault: function() {} });
            }
        },

        hasUnsavedChanges: function() {
            // Simple check - in a real implementation, you'd track changes more precisely
            return this.editors.body && this.editors.body.getValue().length > 0;
        },

        previewTemplate: function(e) {
            e.preventDefault();

            var templateId = $(e.target).data('template') || this.currentTemplate;
            if (!templateId) {
                this.showNotice('error', 'No template selected');
                return;
            }

            var sampleData = $('#preview-sample-data').val() || 'default';

            this.showModal('#template-preview-modal');
            $('#preview-content').html('<div class="preview-loading">' + wecoza_templates.strings.preview_loading + '</div>');

            var data = {
                action: 'wecoza_preview_template',
                nonce: wecoza_templates.nonce,
                template_id: templateId,
                sample_data: sampleData
            };

            $.post(wecoza_templates.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $('#preview-content').html(response.data.html);
                    } else {
                        $('#preview-content').html('<p>Error loading preview: ' + (response.data || 'Unknown error') + '</p>');
                    }
                })
                .fail(function() {
                    $('#preview-content').html('<p>Error loading preview</p>');
                });
        },

        updatePreview: function(e) {
            e.preventDefault();
            this.previewTemplate(e);
        },

        restoreVersion: function(e) {
            e.preventDefault();

            if (!confirm(wecoza_templates.strings.confirm_restore)) {
                return;
            }

            var versionId = $(e.target).data('version');
            var data = {
                action: 'wecoza_restore_template',
                nonce: wecoza_templates.nonce,
                version_id: versionId
            };

            var $btn = $(e.target);
            $btn.prop('disabled', true);

            $.post(wecoza_templates.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        TemplateManager.showNotice('success', response.data);
                        location.reload();
                    } else {
                        TemplateManager.showNotice('error', response.data);
                    }
                })
                .fail(function() {
                    TemplateManager.showNotice('error', 'Failed to restore version');
                })
                .always(function() {
                    $btn.prop('disabled', false);
                });
        },

        deleteVersion: function(e) {
            e.preventDefault();

            if (!confirm(wecoza_templates.strings.confirm_delete)) {
                return;
            }

            var versionId = $(e.target).data('version');
            var data = {
                action: 'wecoza_delete_template_version',
                nonce: wecoza_templates.nonce,
                version_id: versionId
            };

            var $btn = $(e.target);
            $btn.prop('disabled', true);

            $.post(wecoza_templates.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        $btn.closest('.version-item').fadeOut();
                        TemplateManager.showNotice('success', response.data);
                    } else {
                        TemplateManager.showNotice('error', response.data);
                    }
                })
                .fail(function() {
                    TemplateManager.showNotice('error', 'Failed to delete version');
                })
                .always(function() {
                    $btn.prop('disabled', false);
                });
        },

        exportTemplate: function(e) {
            e.preventDefault();

            var templateId = $(e.target).data('template');
            var data = {
                action: 'wecoza_export_template',
                nonce: wecoza_templates.nonce,
                template_id: templateId
            };

            $.post(wecoza_templates.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        TemplateManager.downloadJson(response.data.data, templateId + '-template.json');
                        TemplateManager.showNotice('success', wecoza_templates.strings.export_success);
                    } else {
                        TemplateManager.showNotice('error', response.data);
                    }
                })
                .fail(function() {
                    TemplateManager.showNotice('error', 'Failed to export template');
                });
        },

        downloadJson: function(data, filename) {
            var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        },

        showImportModal: function(e) {
            e.preventDefault();
            this.showModal('#template-import-modal');
        },

        importTemplate: function(e) {
            e.preventDefault();

            var formData = new FormData();
            var fileInput = $('#template-import-file')[0];

            if (!fileInput.files.length) {
                this.showNotice('error', 'Please select a file');
                return;
            }

            formData.append('action', 'wecoza_import_template');
            formData.append('nonce', wecoza_templates.nonce);
            formData.append('template_file', fileInput.files[0]);
            formData.append('overwrite_existing', $('input[name="overwrite_existing"]').is(':checked') ? '1' : '0');

            var $submitBtn = $('#template-import-form button[type="submit"]');
            $submitBtn.prop('disabled', true).text('Importing...');

            $.ajax({
                url: wecoza_templates.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(function(response) {
                if (response.success) {
                    TemplateManager.showNotice('success', wecoza_templates.strings.import_success);
                    TemplateManager.closeModal();
                    location.reload();
                } else {
                    TemplateManager.showNotice('error', response.data);
                }
            })
            .fail(function() {
                TemplateManager.showNotice('error', 'Failed to import template');
            })
            .always(function() {
                $submitBtn.prop('disabled', false).text('Import');
            });
        },

        insertVariable: function(e) {
            e.preventDefault();

            var variable = $(e.target).data('variable');
            var placeholder = '{{' + variable + '}}';

            // Insert into currently focused editor
            var activeTab = $('.editor-tabs .ui-tabs-active a').attr('href');

            if (activeTab === '#subject-tab') {
                var $subject = $('#template-subject');
                var pos = $subject[0].selectionStart;
                var val = $subject.val();
                $subject.val(val.slice(0, pos) + placeholder + val.slice(pos));
            } else if (activeTab === '#body-tab' && this.editors.body) {
                var cursor = this.editors.body.getCursor();
                this.editors.body.replaceRange(placeholder, cursor);
                this.editors.body.focus();
            }
        },

        showModal: function(selector) {
            $(selector).fadeIn();
        },

        closeModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            $('.template-modal').fadeOut();
        },

        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);

            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        }
    };

    // Initialize when DOM is ready
    TemplateManager.init();
});