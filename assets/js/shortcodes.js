/**
 * WECOZA Notifications Shortcodes JavaScript
 * Handles real-time updates and interactions for notification shortcodes
 */

(function($) {
    'use strict';

    /**
     * WECOZA Shortcode Manager Class
     */
    window.WecozaShortcodeManager = function() {
        this.instances = new Map();
        this.pollInterval = 30000; // 30 seconds default
        this.intervalId = null;

        this.init();
    };

    WecozaShortcodeManager.prototype = {

        /**
         * Initialize the shortcode manager
         */
        init: function() {
            this.findAndRegisterShortcodes();
            this.bindEvents();
            // this.startPolling();

        },

        /**
         * Find and register all WECOZA shortcodes on the page
         */
        findAndRegisterShortcodes: function() {
            var self = this;

            $('.wecoza-shortcode-container').each(function() {
                var $container = $(this);
                var shortcodeType = $container.data('wecoza-shortcode');
                var params = $container.data('wecoza-params') || {};
                var refreshInterval = $container.data('refresh-interval') || 30;

                var instance = {
                    id: $container.attr('id'),
                    type: shortcodeType,
                    params: params,
                    refreshInterval: refreshInterval * 1000,
                    lastUpdated: Date.now(),
                    $element: $container,
                    currentFilter: 'all',
                    currentSearch: ''
                };

                self.instances.set(instance.id, instance);

            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Task completion buttons
            $(document).on('click', '.wecoza-complete-task', function(e) {
                e.preventDefault();
                self.handleTaskCompletion($(this));
            });

            // Manual refresh buttons
            $(document).on('click', '.wecoza-refresh-shortcode', function(e) {
                e.preventDefault();
                var containerId = $(this).closest('.wecoza-shortcode-container').attr('id');
                self.refreshShortcode(containerId);
            });

            // Manual dashboard sync buttons
            $(document).on('click', '.wecoza-run-dashboard-sync', function(e) {
                e.preventDefault();
                self.handleManualSync($(this));
            });

            // Task filter buttons
            $(document).on('click', '.wecoza-task-filter', function(e) {
                e.preventDefault();
                self.handleTaskFilter($(this));
            });

            // Table search input
            $(document).on('input', '.wecoza-class-status-search', function() {
                var $input = $(this);
                var query = ($input.val() || '').toLowerCase();
                var $container = $input.closest('.wecoza-shortcode-container');
                var containerId = $container.attr('id');
                var instance = self.instances.get(containerId);

                if (instance) {
                    instance.currentSearch = query;
                    var currentFilter = instance.currentFilter || 'all';
                    self.applyRowFilters($container, currentFilter, query);
                } else {
                    self.applyRowFilters($container, 'all', query);
                }
            });

        },

        /**
         * Start polling for updates
         */
        // startPolling: function() {
        //     var self = this;

        //     if (this.intervalId) {
        //         clearInterval(this.intervalId);
        //     }

        //     this.intervalId = setInterval(function() {
        //         self.pollForUpdates();
        //     }, this.pollInterval);
        // },

        /**
         * Poll for updates on all shortcodes
         */
        // pollForUpdates: function() {
        //     var self = this;

        //     this.instances.forEach(function(instance, id) {
        //         var timeSinceUpdate = Date.now() - instance.lastUpdated;

        //         if (timeSinceUpdate >= instance.refreshInterval) {
        //             self.refreshShortcode(id);
        //         }
        //     });
        // },

        /**
         * Refresh a specific shortcode
         */
        refreshShortcode: function(containerId) {
            var instance = this.instances.get(containerId);
            if (!instance) return;

            var self = this;

            // Add loading state
            instance.$element.addClass('wecoza-loading');

            $.ajax({
                url: wecoza_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wecoza_update_shortcode',
                    nonce: wecoza_ajax.nonce,
                    shortcode_type: instance.type,
                    params: JSON.stringify(instance.params),
                    container_id: containerId
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        // Update content
                        instance.$element.html(response.data.html);
                        instance.lastUpdated = Date.now();

                        // Trigger custom event
                        instance.$element.trigger('wecoza:shortcode-updated', {
                            type: instance.type,
                            id: containerId
                        });

                        var filter = instance.currentFilter || 'all';
                        var search = instance.currentSearch || '';

                        var $filterButtons = instance.$element.find('.wecoza-task-filter');
                        if ($filterButtons.length) {
                            $filterButtons.removeClass('active btn-phoenix-primary text-body-tertiary').addClass('btn-subtle-primary');
                            $filterButtons.each(function() {
                                var $button = $(this);
                                if ($button.data('task-filter') === filter) {
                                    $button.removeClass('btn-subtle-primary').addClass('active btn-phoenix-primary text-body-tertiary');
                                }
                            });
                        }

                        var $searchInput = instance.$element.find('.wecoza-class-status-search');
                        if ($searchInput.length) {
                            $searchInput.val(search);
                        }

                        self.applyRowFilters(instance.$element, filter, search);

                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to refresh shortcode:', error);
                },
                complete: function() {
                    instance.$element.removeClass('wecoza-loading');
                }
            });
        },

        /**
         * Refresh all shortcodes
         */
        refreshAllShortcodes: function() {
            var self = this;
            this.instances.forEach(function(instance, id) {
                self.refreshShortcode(id);
            });
        },

        /**
         * Handle task completion
         */
        handleTaskCompletion: function($button) {
            var classId = $button.data('class-id');
            var taskType = $button.data('task');

            if (!classId || !taskType) {
                this.showMessage('Error: Missing task information', 'error');
                return;
            }

            var self = this;
            var $container = $button.closest('.wecoza-shortcode-container');
            var containerId = $container.attr('id');
            var originalLabel = $button.html();

            // Disable button and show loading
            $button.prop('disabled', true).text('Completing...');

            $.ajax({
                url: wecoza_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wecoza_complete_task',
                    nonce: wecoza_ajax.nonce,
                    class_id: classId,
                    task_type: taskType
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessage('Task marked as complete!', 'success');
                        if (containerId) {
                            self.refreshShortcode(containerId);
                        } else {
                            self.refreshAllShortcodes();
                        }

                    } else {
                        self.showMessage(response.data.message || 'Failed to complete task', 'error');
                        $button.prop('disabled', false).html(originalLabel);
                    }
                },
                error: function() {
                    self.showMessage('Error: Failed to complete task', 'error');
                    $button.prop('disabled', false).html(originalLabel);
                },
                complete: function() {
                    if ($button.prop('disabled')) {
                        $button.prop('disabled', false).html(originalLabel);
                    }
                }
            });
        },

        /**
         * Handle manual dashboard sync action
         */
        handleManualSync: function($button) {
            var self = this;

            if ($button.hasClass('is-loading')) {
                return;
            }

            var $container = $button.closest('.wecoza-shortcode-container');
            var containerId = $container.attr('id');
            var $status = $button.siblings('.wecoza-sync-status');
            var defaultLabel = $button.data('label') || $button.text();
            var loadingLabel = $button.data('loading-label') || 'Syncing...';

            $button.data('default-label', defaultLabel);

            $button.addClass('is-loading').prop('disabled', true).text(loadingLabel);
            if ($status.length) {
                $status.text('Sync in progress...');
            }

            $.ajax({
                url: wecoza_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wecoza_run_dashboard_sync',
                    nonce: wecoza_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var successMessage = (response.data && response.data.message) ? response.data.message : 'Dashboard synced successfully.';
                        self.showMessage(successMessage, 'success');
                        if ($status.length) {
                            $status.text('Last synced just now');
                        }

                        if (containerId) {
                            self.refreshShortcode(containerId);
                        } else {
                            self.refreshAllShortcodes();
                        }
                    } else {
                        var errorMessage = (response.data && response.data.message) ? response.data.message : 'Failed to sync dashboard.';
                        self.showMessage(errorMessage, 'error');
                        if ($status.length) {
                            $status.text(errorMessage);
                        }
                    }
                },
                error: function(xhr) {
                    var message = 'Failed to sync dashboard.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message = xhr.responseJSON.data.message;
                    }
                    self.showMessage(message, 'error');
                    if ($status.length) {
                        $status.text(message);
                    }
                },
                complete: function() {
                    var finalLabel = $button.data('default-label') || defaultLabel;
                    $button.removeClass('is-loading').prop('disabled', false).text(finalLabel);
                }
            });
        },

        /**
         * Handle task filtering
         */
        handleTaskFilter: function($button) {
            var filter = $button.data('task-filter') || 'all';
            var $group = $button.closest('.wecoza-task-filter-group');
            var $container = $button.closest('.wecoza-shortcode-container');
            var containerId = $container.attr('id');
            var instance = this.instances.get(containerId);

            if ($group.length) {
                $group.find('.wecoza-task-filter').each(function() {
                    var $btn = $(this);
                    $btn.removeClass('active btn-phoenix-primary text-body-tertiary').addClass('btn-subtle-primary');
                });

                $button.addClass('active').removeClass('btn-subtle-primary').addClass('btn-phoenix-primary text-body-tertiary');
            }

            if (instance) {
                instance.currentFilter = filter;
                var currentSearch = instance.currentSearch || '';
                this.applyRowFilters($container, filter, currentSearch);
            } else {
                this.applyRowFilters($container, filter, '');
            }
        },

        /**
         * Apply filter & search against table rows
         */
        applyRowFilters: function($container, filter, query) {
            filter = filter || 'all';
            query = (query || '').trim().toLowerCase();

            $container.find('tbody tr.wecoza-task-row').each(function() {
                var $row = $(this);
                var matchesFilter = (filter === 'all') || ($row.data('task-type') === filter);
                var matchesSearch = !query || $row.text().toLowerCase().indexOf(query) !== -1;
                $row.toggle(matchesFilter && matchesSearch);
            });
        },

        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            type = type || 'info';

            // Create message element
            var $message = $('<div class="wecoza-message wecoza-message-' + type + '">' + message + '</div>');

            // Add to page
            $('body').append($message);

            // Position and show
            $message.css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: 10000,
                padding: '12px 20px',
                borderRadius: '4px',
                color: 'white',
                fontSize: '14px',
                fontWeight: 'bold',
                opacity: 0
            });

            // Set background color based on type
            var backgroundColor = {
                'success': '#25b003',
                'error': '#fa3b1d',
                'warning': '#e5780b',
                'info': '#3874ff'
            }[type] || '#3874ff';

            $message.css('backgroundColor', backgroundColor);

            // Animate in
            $message.animate({opacity: 1}, 300);

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $message.animate({opacity: 0}, 300, function() {
                    $message.remove();
                });
            }, 5000);
        },

        /**
         * Get shortcode instance data
         */
        getInstance: function(containerId) {
            return this.instances.get(containerId);
        },

        /**
         * Update shortcode parameters
         */
        updateParams: function(containerId, newParams) {
            var instance = this.instances.get(containerId);
            if (instance) {
                instance.params = $.extend(instance.params, newParams);
                this.refreshShortcode(containerId);
            }
        },

        /**
         * Destroy the manager
         */
        destroy: function() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }

            this.instances.clear();

            // Unbind events
            $(document).off('click', '.wecoza-complete-task');
            $(document).off('click', '.wecoza-refresh-shortcode');
        }
    };

    /**
     * Utility functions
     */
    window.WecozaShortcodeUtils = {

        /**
         * Format date for display
         */
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        },

        /**
         * Calculate days difference
         */
        daysDifference: function(dateString) {
            var date = new Date(dateString);
            var now = new Date();
            var diffTime = now - date;
            return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var later = function() {
                    clearTimeout(timeout);
                    func.apply(this, arguments);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize if we have WECOZA shortcodes on the page
        if ($('.wecoza-shortcode-container').length > 0) {
            window.wecozaShortcodeManager = new WecozaShortcodeManager();
        }
    });

})(jQuery);

window.syncClassData = function(containerId) {
    var $ = window.jQuery;

    if (!$) {
        console.error('syncClassData requires jQuery.');
        return;
    }

    if (!window.wecozaShortcodeManager) {
        return;
    }

    var manager = window.wecozaShortcodeManager;
    var instance = manager.instances.get(containerId);
    if (!instance) {
        return;
    }

    var $container = instance.$element;
    var $button = $container.find('.wecoza-class-status-sync-btn');
    var originalLabel = $button.length ? $button.html() : '';

    var hideAlerts = function(context) {
        (context || $container)
            .find('.wecoza-sync-alert-success, .wecoza-sync-alert-error')
            .addClass('d-none');
    };

    var showAlert = function(type, message, context) {
        var $context = context || $container;
        var selector = type === 'success' ? '.wecoza-sync-alert-success' : '.wecoza-sync-alert-error';
        var $target = $context.find(selector);

        hideAlerts($context);

        if ($target.length) {
            $target.removeClass('d-none').text(message);
        } else {
            var level = type === 'success' ? 'success' : 'error';
            manager.showMessage(message, level);
        }
    };

    hideAlerts($container);

    if ($button.length) {
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    }

    $.ajax({
        url: wecoza_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'wecoza_run_dashboard_sync',
            nonce: wecoza_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                var successMessage = (response.data && response.data.message) ? response.data.message : 'Dashboard data synced successfully.';

                $container.one('wecoza:shortcode-updated', function() {
                    showAlert('success', successMessage, $container);
                });

                manager.refreshShortcode(containerId);
            } else {
                var message = (response.data && response.data.message) ? response.data.message : 'Failed to sync dashboard data.';
                showAlert('error', message, $container);
            }
        },
        error: function(xhr) {
            var message = 'Failed to sync dashboard data.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            }
            showAlert('error', message, $container);
        },
        complete: function() {
            if ($button.length) {
                $button.prop('disabled', false).html(originalLabel);
            }
        }
    });
};

window.exportClassStatus = function(containerId) {
    var $ = window.jQuery;

    if (!$) {
        console.error('exportClassStatus requires jQuery.');
        return;
    }

    if (!window.wecozaShortcodeManager) {
        return;
    }

    var manager = window.wecozaShortcodeManager;
    var instance = manager.instances.get(containerId);
    if (!instance) {
        manager.showMessage('Unable to locate table data.', 'error');
        return;
    }

    var $container = instance.$element;
    var headers = [];
    $container.find('table thead tr th').each(function() {
        headers.push($(this).text().trim());
    });

    var rows = [headers];
    $container.find('tbody tr.wecoza-task-row:visible').each(function() {
        var rowData = [];
        $(this).find('td').each(function() {
            rowData.push($(this).text().trim());
        });
        rows.push(rowData);
    });

    if (rows.length <= 1) {
        manager.showMessage('Nothing to export.', 'info');
        return;
    }

    var csv = rows.map(function(cols) {
        return cols.map(function(col) {
            return '"' + col.replace(/"/g, '""') + '"';
        }).join(',');
    }).join('\n');

    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    var fileName = 'class-status-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.csv';

    if (navigator.msSaveBlob) {
        navigator.msSaveBlob(blob, fileName);
    } else {
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    manager.showMessage('Export generated.', 'success');
};
