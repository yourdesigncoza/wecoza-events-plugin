<?php
/**
 * Material Tracking Dashboard Template
 *
 * @var array<int, array<string, mixed>> $records Formatted tracking records
 * @var array<string, mixed> $statistics Formatted statistics
 * @var array<string, mixed> $filters Applied filters
 * @var bool $can_manage Whether user can manage tracking
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wecoza-material-tracking-dashboard">
    <div class="card h-100">
        <!-- Header -->
        <div class="card-header p-3 border-bottom">
            <div class="row g-3 justify-content-between align-items-center mb-3">
                <div class="col-12 col-md">
                    <h4 class="text-body-emphasis mb-0">
                        Material Delivery Tracking
                        <i class="bi bi-box-seam ms-2"></i>
                    </h4>
                </div>
                <div class="search-box col-auto">
                    <form class="position-relative">
                        <input 
                            type="search" 
                            class="form-control search-input search form-control-sm" 
                            id="material-tracking-search" 
                            placeholder="Search by class code, subject, or client..."
                            aria-label="Search">
                        <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
                        </svg>
                    </form>
                </div>
                <div class="col-auto">
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" id="status-filter" style="width: auto;">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="notified">Notified</option>
                            <option value="delivered">Delivered</option>
                        </select>
                        <select class="form-select form-select-sm" id="notification-type-filter" style="width: auto;">
                            <option value="all">All Types</option>
                            <option value="orange">Orange (7d)</option>
                            <option value="red">Red (5d)</option>
                        </select>
                        <button class="btn btn-phoenix-secondary btn-sm" id="refresh-dashboard">
                            Refresh
                            <i class="bi bi-arrow-clockwise ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Strip -->
            <?php echo $this->render('material-tracking/statistics', ['statistics' => $statistics]); ?>
        </div>

        <!-- Tracking Records Table -->
        <div class="card-body p-0">
            <div class="table-responsive scrollbar" style="max-height: 600px; overflow-y: auto;">
                <table id="material-tracking-table" class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                    <thead class="border-bottom sticky-top bg-body">
                        <tr>
                            <th scope="col" class="border-0 ps-3" data-sortable="true" data-sort-key="class_code" data-sort-type="text" style="cursor: pointer;">
                                Class Code/Subject
                                <span class="sort-indicator ms-1 d-none"><i class="bi bi-chevron-up"></i></span>
                            </th>
                            <th scope="col" class="border-0" data-sortable="true" data-sort-key="client_name" data-sort-type="text" style="cursor: pointer;">
                                Client/Site
                                <span class="sort-indicator ms-1 d-none"><i class="bi bi-chevron-up"></i></span>
                            </th>
                            <th scope="col" class="border-0" data-sortable="true" data-sort-key="start_date" data-sort-type="date" style="cursor: pointer;">
                                Class Start Date
                                <span class="sort-indicator ms-1 d-none"><i class="bi bi-chevron-up"></i></span>
                            </th>
                            <th scope="col" class="border-0" data-sortable="true" data-sort-key="notification_type" data-sort-type="text" style="cursor: pointer;">
                                Notification Type
                                <span class="sort-indicator ms-1 d-none"><i class="bi bi-chevron-up"></i></span>
                            </th>
                            <th scope="col" class="border-0" data-sortable="true" data-sort-key="status" data-sort-type="text" style="cursor: pointer;">
                                Status
                                <span class="sort-indicator ms-1 d-none"><i class="bi bi-chevron-up"></i></span>
                            </th>
                            <th scope="col" class="border-0 text-center pe-3" data-sortable="false">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <?php echo $this->render('material-tracking/empty-state', []); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            // Generate shared nonce for all mark-as-delivered checkboxes
                            $tracking_nonce = wp_create_nonce('wecoza_material_tracking_action');
                            ?>
                            <?php foreach ($records as $record): ?>
                                <?php echo $this->render('material-tracking/list-item', [
                                    'record' => $record, 
                                    'can_manage' => $can_manage,
                                    'tracking_nonce' => $tracking_nonce
                                ]); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="card-footer border-top text-center py-2">
            <p class="mb-0 text-body-tertiary fs-10">
                <span id="last-updated">Last updated: Just now</span> • 
                Showing <span id="visible-count"><?php echo count($records); ?></span> of 
                <span id="total-count"><?php echo $statistics['total']['count']; ?></span> records
            </p>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="material-tracking-alert-container" class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 9999; min-width: 400px; max-width: 600px;">
        <!-- Alerts will be dynamically inserted here -->
    </div>
</div>

<style>
.wecoza-material-tracking-dashboard .search-input {
    border-radius: 0.375rem;
}

/* Table styling */
#material-tracking-table {
    min-width: 100%;
}

#material-tracking-table thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    white-space: nowrap;
    font-weight: 600;
    font-size: 0.8125rem;
}

#material-tracking-table thead th[data-sortable="true"] {
    user-select: none;
}

#material-tracking-table thead th[data-sortable="true"]:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

#material-tracking-table tbody tr {
    transition: background-color 0.15s ease;
}

#material-tracking-table tbody td {
    vertical-align: middle;
    font-size: 0.875rem;
}

#material-tracking-table .sort-indicator {
    display: inline-block;
    transition: opacity 0.2s;
    font-size: 0.75rem;
}

#material-tracking-table .sort-indicator i {
    transition: transform 0.2s;
}

/* Checkbox styling */
.mark-delivered-checkbox {
    cursor: pointer;
    width: 1.125rem;
    height: 1.125rem;
}

.mark-delivered-checkbox:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.mark-delivered-checkbox:not(:disabled):hover {
    transform: scale(1.1);
    transition: transform 0.15s;
}

/* Compact table spacing */
.table-sm td, .table-sm th {
    padding: 0.5rem;
}

/* Badge adjustments for table cells */
.badge {
    display: inline-block;
    white-space: nowrap;
}
</style>

<script>
jQuery(document).ready(function($) {
    const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    let lastUpdateTime = Date.now();
    let currentSort = { key: null, direction: 'asc' };
    
    // Update last updated time
    function updateLastUpdatedTime() {
        const secondsAgo = Math.floor((Date.now() - lastUpdateTime) / 1000);
        let timeText;
        if (secondsAgo < 60) {
            timeText = 'Just now';
        } else if (secondsAgo < 3600) {
            timeText = Math.floor(secondsAgo / 60) + ' minutes ago';
        } else {
            timeText = Math.floor(secondsAgo / 3600) + ' hours ago';
        }
        $('#last-updated').text('Last updated: ' + timeText);
    }
    
    setInterval(updateLastUpdatedTime, 10000); // Update every 10 seconds
    
    // Show alert notification
    function showAlert(type, message) {
        let alertClass, iconClass, iconColor;
        
        if (type === 'success') {
            alertClass = 'alert-subtle-success';
            iconClass = 'fa-check-circle';
            iconColor = 'text-success';
        } else if (type === 'error') {
            alertClass = 'alert-subtle-danger';
            iconClass = 'fa-times-circle';
            iconColor = 'text-danger';
        } else {
            alertClass = 'alert-subtle-info';
            iconClass = 'fa-info-circle';
            iconColor = 'text-info';
        }
        
        const alertId = 'alert-' + Date.now();
        const alertHtml = `
            <div id="${alertId}" class="alert ${alertClass} d-flex align-items-center mb-2" role="alert">
                <span class="fas ${iconClass} ${iconColor} fs-5 me-3"></span>
                <p class="mb-0 flex-1">${message}</p>
                <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        $('#material-tracking-alert-container').append(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('#' + alertId).fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Column sorting
    function sortTable(sortKey, sortType) {
        const tbody = $('#material-tracking-table tbody');
        const rows = tbody.find('tr').get();
        
        // Determine sort direction
        if (currentSort.key === sortKey) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.key = sortKey;
            currentSort.direction = 'asc';
        }
        
        // Sort rows
        rows.sort(function(a, b) {
            let aVal, bVal;
            
            if (sortType === 'text') {
                if (sortKey === 'class_code') {
                    aVal = $(a).data('class-code') || '';
                } else if (sortKey === 'client_name') {
                    aVal = $(a).data('client-name') || '';
                } else if (sortKey === 'notification_type') {
                    aVal = $(a).data('notification-type') || '';
                } else if (sortKey === 'status') {
                    aVal = $(a).data('status') || '';
                }
                
                if (sortKey === 'class_code') {
                    bVal = $(b).data('class-code') || '';
                } else if (sortKey === 'client_name') {
                    bVal = $(b).data('client-name') || '';
                } else if (sortKey === 'notification_type') {
                    bVal = $(b).data('notification-type') || '';
                } else if (sortKey === 'status') {
                    bVal = $(b).data('status') || '';
                }
                
                aVal = aVal.toString().toLowerCase();
                bVal = bVal.toString().toLowerCase();
            } else if (sortType === 'date') {
                aVal = new Date($(a).data('start-date') || 0).getTime();
                bVal = new Date($(b).data('start-date') || 0).getTime();
            }
            
            if (currentSort.direction === 'asc') {
                return aVal > bVal ? 1 : -1;
            } else {
                return aVal < bVal ? 1 : -1;
            }
        });
        
        // Reorder DOM
        $.each(rows, function(index, row) {
            tbody.append(row);
        });
        
        // Update sort indicators
        $('th[data-sortable="true"]').each(function() {
            const th = $(this);
            const indicator = th.find('.sort-indicator');
            
            if (th.data('sort-key') === sortKey) {
                indicator.removeClass('d-none');
                const icon = indicator.find('i');
                if (currentSort.direction === 'asc') {
                    icon.removeClass('bi-chevron-down').addClass('bi-chevron-up');
                } else {
                    icon.removeClass('bi-chevron-up').addClass('bi-chevron-down');
                }
            } else {
                indicator.addClass('d-none');
            }
        });
    }
    
    // Sort column click handler
    $('th[data-sortable="true"]').on('click', function() {
        const sortKey = $(this).data('sort-key');
        const sortType = $(this).data('sort-type');
        sortTable(sortKey, sortType);
    });
    
    // Mark as delivered checkbox handler
    $(document).on('change', '.mark-delivered-checkbox:not(:disabled)', function() {
        const checkbox = $(this);
        const classId = checkbox.data('class-id');
        const nonce = checkbox.data('nonce');
        const row = checkbox.closest('tr');
        
        // Disable checkbox immediately
        checkbox.prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'wecoza_mark_material_delivered',
                class_id: classId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.data.message);
                    
                    // Update UI - keep checkbox checked and disabled
                    checkbox.prop('checked', true).prop('disabled', true);
                    row.find('.delivery-status-badge').html('<span class="badge badge-phoenix badge-phoenix-success fs-10">✅ Delivered</span>');
                    row.data('status', 'delivered');
                    
                    // Update statistics
                    const notifiedCount = parseInt($('#stat-notified').text()) - 1;
                    const deliveredCount = parseInt($('#stat-delivered').text()) + 1;
                    $('#stat-notified').text(notifiedCount);
                    $('#stat-delivered').text(deliveredCount);
                    
                    lastUpdateTime = Date.now();
                    updateLastUpdatedTime();
                } else {
                    showAlert('error', response.data.message || 'Failed to mark as delivered');
                    checkbox.prop('checked', false).prop('disabled', false);
                }
            },
            error: function() {
                showAlert('error', 'An error occurred. Please try again.');
                checkbox.prop('checked', false).prop('disabled', false);
            }
        });
    });
    
    // Search and filter
    function filterRecords() {
        const searchTerm = $('#material-tracking-search').val().toLowerCase();
        const statusFilter = $('#status-filter').val();
        const typeFilter = $('#notification-type-filter').val();
        let visibleCount = 0;
        
        $('#material-tracking-table tbody tr').each(function() {
            const row = $(this);
            const text = row.text().toLowerCase();
            const status = row.data('status');
            const type = row.data('notification-type');
            
            let visible = true;
            
            if (searchTerm && !text.includes(searchTerm)) {
                visible = false;
            }
            
            if (statusFilter !== 'all' && status !== statusFilter) {
                visible = false;
            }
            
            if (typeFilter !== 'all' && type !== typeFilter) {
                visible = false;
            }
            
            row.toggle(visible);
            if (visible) visibleCount++;
        });
        
        $('#visible-count').text(visibleCount);
    }
    
    $('#material-tracking-search').on('input', filterRecords);
    $('#status-filter, #notification-type-filter').on('change', filterRecords);
    
    // Refresh dashboard
    $('#refresh-dashboard').on('click', function() {
        location.reload();
    });
    
    // Statistics item click to filter
    $('.stat-item').on('click', function() {
        const status = $(this).data('status');
        $('#status-filter').val(status).trigger('change');
    });
});
</script>
