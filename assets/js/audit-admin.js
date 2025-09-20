jQuery(document).ready(function($) {
    'use strict';

    var AuditManager = {
        charts: {},

        init: function() {
            this.bindEvents();
            this.initCharts();
            this.setupAutoRefresh();
        },

        bindEvents: function() {
            // Export functionality
            $(document).on('click', '#export-logs-csv', this.exportLogs.bind(this, 'csv'));
            $(document).on('click', '#export-logs-json', this.exportLogs.bind(this, 'json'));

            // Cleanup functionality
            $(document).on('click', '#cleanup-logs', this.cleanupLogs.bind(this));

            // Context modal
            $(document).on('click', '.show-context', this.showContext.bind(this));
            $(document).on('click', '.modal-close', this.closeModal.bind(this));
            $(document).on('click', '.audit-modal', function(e) {
                if (e.target === this) {
                    AuditManager.closeModal.call(this, e);
                }
            });

            // System health refresh
            $(document).on('click', '#refresh-health', this.refreshSystemHealth.bind(this));

            // Filter form enhancement
            $(document).on('change', '.audit-filters select, .audit-filters input', this.onFilterChange.bind(this));
        },

        initCharts: function() {
            if (typeof auditStats !== 'undefined') {
                this.renderLevelsChart();
                this.renderActivityChart();
                this.renderActionsChart();
            }

            if ($('#system-health-chart').length) {
                this.renderSystemHealthChart();
            }
        },

        renderLevelsChart: function() {
            var ctx = document.getElementById('levels-chart');
            if (!ctx) return;

            var levelData = auditStats.by_level || [];
            var labels = [];
            var data = [];
            var colors = [];

            var colorMap = {
                'info': '#0073aa',
                'warning': '#ffb900',
                'error': '#dc3232',
                'critical': '#8b0000'
            };

            levelData.forEach(function(item) {
                labels.push(item.level.charAt(0).toUpperCase() + item.level.slice(1));
                data.push(parseInt(item.count));
                colors.push(colorMap[item.level] || '#666');
            });

            this.charts.levels = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        renderActivityChart: function() {
            var ctx = document.getElementById('activity-chart');
            if (!ctx) return;

            var dailyData = auditStats.daily_counts || [];
            var labels = [];
            var data = [];

            dailyData.forEach(function(item) {
                labels.push(item.date);
                data.push(parseInt(item.count));
            });

            this.charts.activity = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Log Entries',
                        data: data,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        renderActionsChart: function() {
            var ctx = document.getElementById('actions-chart');
            if (!ctx) return;

            var actionData = auditStats.by_action || [];
            var labels = [];
            var data = [];

            // Take top 10 actions
            actionData.slice(0, 10).forEach(function(item) {
                labels.push(item.action.replace(/_/g, ' '));
                data.push(parseInt(item.count));
            });

            this.charts.actions = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Occurrences',
                        data: data,
                        backgroundColor: '#0073aa',
                        borderColor: '#005a87',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        },

        renderSystemHealthChart: function() {
            // Placeholder for system health visualization
            // Implementation would depend on health data structure
        },

        exportLogs: function(format, e) {
            e.preventDefault();

            var $btn = $(e.target);
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Exporting...');

            var filters = this.getCurrentFilters();

            var data = {
                action: 'wecoza_export_audit_logs',
                nonce: wecoza_audit.nonce,
                format: format,
                filters: filters
            };

            $.post(wecoza_audit.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        AuditManager.downloadData(response.data.data, 'audit-logs-' + format, format);
                        AuditManager.showNotice('success', wecoza_audit.strings.export_success);
                    } else {
                        AuditManager.showNotice('error', response.data || wecoza_audit.strings.export_error);
                    }
                })
                .fail(function() {
                    AuditManager.showNotice('error', wecoza_audit.strings.export_error);
                })
                .always(function() {
                    $btn.prop('disabled', false).text(originalText);
                });
        },

        cleanupLogs: function(e) {
            e.preventDefault();

            if (!confirm(wecoza_audit.strings.cleanup_confirm)) {
                return;
            }

            var $btn = $(e.target);
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Cleaning...');

            var data = {
                action: 'wecoza_cleanup_audit_logs',
                nonce: wecoza_audit.nonce,
                retention_days: 90 // Could be configurable
            };

            $.post(wecoza_audit.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        AuditManager.showNotice('success', wecoza_audit.strings.cleanup_success);
                        location.reload(); // Refresh to show updated log list
                    } else {
                        AuditManager.showNotice('error', response.data);
                    }
                })
                .fail(function() {
                    AuditManager.showNotice('error', 'Failed to cleanup logs');
                })
                .always(function() {
                    $btn.prop('disabled', false).text(originalText);
                });
        },

        showContext: function(e) {
            e.preventDefault();

            var contextData = $(e.target).data('context');
            try {
                var formattedContext = JSON.stringify(JSON.parse(contextData), null, 2);
                $('#context-content').text(formattedContext);
            } catch (ex) {
                $('#context-content').text(contextData);
            }

            this.showModal('#context-modal');
        },

        refreshSystemHealth: function(e) {
            e.preventDefault();

            var $btn = $(e.target);
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Refreshing...');

            var data = {
                action: 'wecoza_get_system_health',
                nonce: wecoza_audit.nonce
            };

            $.post(wecoza_audit.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        AuditManager.updateHealthDisplay(response.data);
                        AuditManager.showNotice('success', wecoza_audit.strings.health_updated);
                    } else {
                        AuditManager.showNotice('error', response.data);
                    }
                })
                .fail(function() {
                    AuditManager.showNotice('error', 'Failed to refresh system health');
                })
                .always(function() {
                    $btn.prop('disabled', false).text(originalText);
                });
        },

        updateHealthDisplay: function(healthData) {
            // Update overall status
            var $overallStatus = $('.overall-status');
            $overallStatus.removeClass('status-healthy status-warning status-critical')
                         .addClass('status-' + healthData.overall_status);
            $overallStatus.find('h2').text(healthData.overall_status.charAt(0).toUpperCase() + healthData.overall_status.slice(1));

            // Update individual checks
            $.each(healthData.checks, function(checkName, checkData) {
                var $check = $('.health-check').filter(function() {
                    return $(this).find('h3').text().toLowerCase().includes(checkName.replace('_', ' '));
                });

                if ($check.length) {
                    $check.removeClass('status-ok status-warning status-critical')
                          .addClass('status-' + checkData.status);
                    $check.find('.status-indicator').text(checkData.status.charAt(0).toUpperCase() + checkData.status.slice(1));
                    $check.find('.check-message').text(checkData.message);

                    // Update details
                    var $details = $check.find('.check-details');
                    $details.empty();
                    if (checkData.details) {
                        $.each(checkData.details, function(key, value) {
                            var formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                            $details.append(
                                '<div class="detail-item">' +
                                '<span class="detail-key">' + formattedKey + ':</span>' +
                                '<span class="detail-value">' + value + '</span>' +
                                '</div>'
                            );
                        });
                    }
                }
            });
        },

        getCurrentFilters: function() {
            var filters = {};
            var $form = $('.audit-filters form');

            $form.find('select, input[type="text"], input[type="date"]').each(function() {
                var $input = $(this);
                var value = $input.val();
                if (value) {
                    filters[$input.attr('name')] = value;
                }
            });

            return filters;
        },

        onFilterChange: function() {
            // Auto-submit form when filters change (with debouncing)
            clearTimeout(this.filterTimeout);
            this.filterTimeout = setTimeout(function() {
                $('.audit-filters form').submit();
            }, 1000);
        },

        setupAutoRefresh: function() {
            // Auto-refresh health status every 5 minutes
            if ($('.system-health-container').length) {
                setInterval(function() {
                    $('#refresh-health').click();
                }, 300000); // 5 minutes
            }

            // Auto-refresh charts every 2 minutes on analytics page
            if ($('.audit-analytics-container').length) {
                setInterval(function() {
                    location.reload();
                }, 120000); // 2 minutes
            }
        },

        showModal: function(selector) {
            $(selector).fadeIn();
        },

        closeModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            $('.audit-modal').fadeOut();
        },

        downloadData: function(data, filename, format) {
            var blob, url, a;

            if (format === 'csv') {
                var csvContent = '';
                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(function(row) {
                        csvContent += row.join(',') + '\n';
                    });
                }
                blob = new Blob([csvContent], { type: 'text/csv' });
                filename += '.csv';
            } else {
                blob = new Blob([typeof data === 'string' ? data : JSON.stringify(data, null, 2)], { type: 'application/json' });
                filename += '.json';
            }

            url = window.URL.createObjectURL(blob);
            a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
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
    AuditManager.init();

    // Handle real-time updates
    $(document).on('wecoza_audit_update', function(e, data) {
        if (data.type === 'health_status') {
            AuditManager.updateHealthDisplay(data.health);
        }
    });

    // Level-specific row styling
    $('.audit-logs tbody tr').each(function() {
        var $row = $(this);
        var level = $row.find('.level-badge').text().toLowerCase();
        $row.addClass('log-level-' + level);
    });

    // Enhanced context display
    $('.show-context').on('mouseenter', function() {
        var $this = $(this);
        if (!$this.data('tooltip-shown')) {
            $this.attr('title', 'Click to view detailed context information');
            $this.data('tooltip-shown', true);
        }
    });

    // Performance monitoring display
    if ($('.performance-metrics').length) {
        $('.metric-value').each(function() {
            var $metric = $(this);
            var value = parseFloat($metric.text());
            var threshold = parseFloat($metric.data('threshold') || 0);

            if (threshold > 0 && value > threshold) {
                $metric.addClass('metric-warning');
            }
        });
    }

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+E for export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            $('#export-logs-csv').click();
        }

        // Escape to close modals
        if (e.key === 'Escape') {
            $('.audit-modal').fadeOut();
        }

        // Ctrl+R for refresh health (on health page)
        if (e.ctrlKey && e.key === 'r' && $('#refresh-health').length) {
            e.preventDefault();
            $('#refresh-health').click();
        }
    });

    // Chart responsiveness
    $(window).on('resize', function() {
        Object.keys(AuditManager.charts).forEach(function(chartKey) {
            if (AuditManager.charts[chartKey]) {
                AuditManager.charts[chartKey].resize();
            }
        });
    });

    // Advanced filtering
    $('.audit-filters').on('click', '.filter-preset', function(e) {
        e.preventDefault();
        var preset = $(this).data('preset');

        switch (preset) {
            case 'errors-today':
                $('select[name="level"]').val('error');
                $('input[name="date_from"]').val(new Date().toISOString().split('T')[0]);
                break;
            case 'critical-week':
                $('select[name="level"]').val('critical');
                var weekAgo = new Date();
                weekAgo.setDate(weekAgo.getDate() - 7);
                $('input[name="date_from"]').val(weekAgo.toISOString().split('T')[0]);
                break;
        }

        $('.audit-filters form').submit();
    });
});