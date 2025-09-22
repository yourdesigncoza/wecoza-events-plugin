/**
 * WECOZA Notifications Shortcodes JavaScript
 * Handles real-time updates and interactions for notification shortcodes
 */

(function($) {
    'use strict';

    var CLASS_STATUS_PAGE_SIZE = 50;

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
         * Initialize class status pagination and filtering for an instance
         */
        initializeClassStatusInstance: function(instance) {
            if (!instance || instance.type !== 'class_status') {
                return;
            }

            this.ensureClassStatusPagination(instance);
            this.applyRowFilters(instance.$element, instance.currentFilter || 'all', instance.currentSearch || '', {resetPage: true});
        },

        /**
         * Ensure class status pagination state and DOM references exist
         */
        ensureClassStatusPagination: function(instance) {
            if (!instance || instance.type !== 'class_status') {
                return;
            }

            if (!instance.pagination) {
                instance.pagination = this.createClassStatusPaginationState();
            }

            var pagination = instance.pagination;
            pagination.itemsPerPage = CLASS_STATUS_PAGE_SIZE;

            var $container = instance.$element.find('[data-pagination-container]');
            pagination.$container = $container;
            pagination.$summary = $container.find('[data-pagination-summary]');
            pagination.$pages = $container.find('[data-pagination-pages]');
            pagination.$prev = $container.find('[data-pagination-action="prev"]');
            pagination.$next = $container.find('[data-pagination-action="next"]');
        },

        /**
         * Create default pagination state for class status table
         */
        createClassStatusPaginationState: function() {
            return {
                itemsPerPage: CLASS_STATUS_PAGE_SIZE,
                currentPage: 1,
                totalItems: 0,
                totalPages: 1,
                visibleRows: [],
                $container: null,
                $summary: null,
                $pages: null,
                $prev: null,
                $next: null
            };
        },

        /**
         * Update pagination state values after filtering
         */
        updateClassStatusPaginationState: function(instance, matchedRows, resetPage) {
            if (!instance || instance.type !== 'class_status' || !instance.pagination) {
                return;
            }

            var pagination = instance.pagination;
            pagination.visibleRows = matchedRows;
            pagination.totalItems = matchedRows.length;
            pagination.totalPages = Math.max(1, Math.ceil(pagination.totalItems / pagination.itemsPerPage));

            if (pagination.totalItems === 0) {
                pagination.currentPage = 1;
                return;
            }

            if (resetPage) {
                pagination.currentPage = 1;
            } else if (pagination.currentPage > pagination.totalPages) {
                pagination.currentPage = pagination.totalPages;
            } else if (pagination.currentPage < 1) {
                pagination.currentPage = 1;
            }
        },

        /**
         * Render pagination controls and visible rows
         */
        renderClassStatusPagination: function(instance) {
            if (!instance || instance.type !== 'class_status' || !instance.pagination) {
                return;
            }

            this.ensureClassStatusPagination(instance);

            var pagination = instance.pagination;
            if (!pagination.$container || pagination.$container.length === 0) {
                this.updateClassStatusPaginationDisplay(instance);
                return;
            }

            var totalItems = pagination.totalItems;
            var currentPage = pagination.currentPage;
            var itemsPerPage = pagination.itemsPerPage;
            var startItem = totalItems === 0 ? 0 : ((currentPage - 1) * itemsPerPage) + 1;
            var endItem = totalItems === 0 ? 0 : Math.min(currentPage * itemsPerPage, totalItems);
            var summaryHtml;

            if (pagination.$summary && pagination.$summary.length) {
                if (totalItems > 0) {
                    summaryHtml = startItem + ' to ' + endItem + ' <span class="text-body-tertiary"> Items of </span>' + totalItems;
                } else {
                    summaryHtml = '0 <span class="text-body-tertiary"> Items of </span>0';
                }
                pagination.$summary.html(summaryHtml);
            }

            if (pagination.$pages && pagination.$pages.length) {
                pagination.$pages.html(this.buildClassStatusPagesHtml(pagination));
            }

            this.updateClassStatusNavState(pagination);
            this.bindClassStatusPaginationEvents(instance);
            this.updateClassStatusPaginationDisplay(instance);
        },

        /**
         * Build numeric pagination items markup
         */
        buildClassStatusPagesHtml: function(pagination) {
            if (!pagination || pagination.totalPages <= 0) {
                return '';
            }

            if (pagination.totalItems === 0) {
                return '<li class="page-item active"><span class="page-link" aria-current="page">1</span></li>';
            }

            var pages = this.computeClassStatusPageSequence(pagination.totalPages, pagination.currentPage);
            var html = '';

            pages.forEach(function(page) {
                if (page === 'ellipsis') {
                    html += '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                    return;
                }

                var isActive = page === pagination.currentPage;
                html += '<li class="page-item' + (isActive ? ' active' : '') + '">';
                if (isActive) {
                    html += '<span class="page-link" aria-current="page">' + page + '</span>';
                } else {
                    html += '<button type="button" class="page-link" data-page-number="' + page + '">' + page + '</button>';
                }
                html += '</li>';
            });

            return html;
        },

        /**
         * Determine which page numbers should be displayed
         */
        computeClassStatusPageSequence: function(totalPages, currentPage) {
            var pages = [];

            if (totalPages <= 5) {
                for (var i = 1; i <= totalPages; i++) {
                    pages.push(i);
                }
                return pages;
            }

            pages.push(1);

            var start = Math.max(2, currentPage - 1);
            var end = Math.min(totalPages - 1, currentPage + 1);

            if (currentPage <= 3) {
                start = 2;
                end = 4;
            } else if (currentPage >= totalPages - 2) {
                start = totalPages - 3;
                end = totalPages - 1;
            }

            if (start > 2) {
                pages.push('ellipsis');
            }

            for (var page = start; page <= end; page++) {
                if (page > 1 && page < totalPages) {
                    pages.push(page);
                }
            }

            if (end < totalPages - 1) {
                pages.push('ellipsis');
            }

            pages.push(totalPages);

            return pages;
        },

        /**
         * Update disabled state for prev/next controls
         */
        updateClassStatusNavState: function(pagination) {
            if (!pagination) {
                return;
            }

            var prevDisabled = pagination.currentPage <= 1 || pagination.totalItems === 0;
            var nextDisabled = pagination.currentPage >= pagination.totalPages || pagination.totalItems === 0;

            if (pagination.$prev && pagination.$prev.length) {
                pagination.$prev.prop('disabled', prevDisabled);
                pagination.$prev.toggleClass('disabled', prevDisabled);
                pagination.$prev.attr('aria-disabled', prevDisabled ? 'true' : 'false');
            }

            if (pagination.$next && pagination.$next.length) {
                pagination.$next.prop('disabled', nextDisabled);
                pagination.$next.toggleClass('disabled', nextDisabled);
                pagination.$next.attr('aria-disabled', nextDisabled ? 'true' : 'false');
            }
        },

        /**
         * Attach pagination button handlers
         */
        bindClassStatusPaginationEvents: function(instance) {
            if (!instance || instance.type !== 'class_status' || !instance.pagination) {
                return;
            }

            var pagination = instance.pagination;
            if (!pagination.$container || pagination.$container.length === 0) {
                return;
            }

            var self = this;
            pagination.$container.off('click.wecozaPagination');

            pagination.$container.on('click.wecozaPagination', '[data-pagination-action="prev"]', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) {
                    return;
                }
                self.goToClassStatusPage(instance, pagination.currentPage - 1);
            });

            pagination.$container.on('click.wecozaPagination', '[data-pagination-action="next"]', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) {
                    return;
                }
                self.goToClassStatusPage(instance, pagination.currentPage + 1);
            });

            pagination.$container.on('click.wecozaPagination', '[data-page-number]', function(e) {
                e.preventDefault();
                var pageNumber = parseInt($(this).data('page-number'), 10);
                if (!Number.isNaN(pageNumber)) {
                    self.goToClassStatusPage(instance, pageNumber);
                }
            });
        },

        /**
         * Navigate to a specific pagination page
         */
        goToClassStatusPage: function(instance, pageNumber) {
            if (!instance || instance.type !== 'class_status' || !instance.pagination) {
                return;
            }

            var pagination = instance.pagination;
            if (pageNumber < 1 || pageNumber > pagination.totalPages || pageNumber === pagination.currentPage) {
                return;
            }

            pagination.currentPage = pageNumber;
            this.renderClassStatusPagination(instance);
        },

        /**
         * Show only rows for the current page selection
         */
        updateClassStatusPaginationDisplay: function(instance) {
            if (!instance || instance.type !== 'class_status' || !instance.pagination) {
                return;
            }

            var pagination = instance.pagination;
            var $rows = instance.$element.find('tbody tr.wecoza-task-row');
            $rows.hide();

            if (!pagination.visibleRows || pagination.visibleRows.length === 0) {
                return;
            }

            var startIndex = (pagination.currentPage - 1) * pagination.itemsPerPage;
            var endIndex = Math.min(startIndex + pagination.itemsPerPage, pagination.visibleRows.length);

            for (var i = startIndex; i < endIndex; i++) {
                var row = pagination.visibleRows[i];
                if (row) {
                    $(row).show();
                }
            }
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

                if (shortcodeType === 'class_status') {
                    self.initializeClassStatusInstance(instance);
                }

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
                    self.applyRowFilters($container, currentFilter, query, {resetPage: true});
                } else {
                    self.applyRowFilters($container, 'all', query, {resetPage: true});
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

                        self.ensureClassStatusPagination(instance);
                        self.applyRowFilters(instance.$element, filter, search, {resetPage: false});

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
                this.applyRowFilters($container, filter, currentSearch, {resetPage: true});
            } else {
                this.applyRowFilters($container, filter, '', {resetPage: true});
            }
        },

        /**
         * Apply filter & search against table rows
         */
        applyRowFilters: function($container, filter, query, options) {
            filter = filter || 'all';
            query = (query || '').trim().toLowerCase();
            options = options || {};

            var resetPage = options.hasOwnProperty('resetPage') ? options.resetPage : true;
            var matchedRows = [];

            $container.find('tbody tr.wecoza-task-row').each(function() {
                var $row = $(this);
                var matchesFilter = (filter === 'all') || ($row.data('task-type') === filter);
                var matchesSearch = !query || $row.text().toLowerCase().indexOf(query) !== -1;
                var isMatch = matchesFilter && matchesSearch;

                $row.toggle(isMatch);

                if (isMatch) {
                    matchedRows.push(this);
                }
            });

            var containerId = $container.attr('id');
            var instance = this.instances.get(containerId);

            if (instance && instance.type === 'class_status') {
                this.ensureClassStatusPagination(instance);
                this.updateClassStatusPaginationState(instance, matchedRows, resetPage);
                this.renderClassStatusPagination(instance);
            }
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
