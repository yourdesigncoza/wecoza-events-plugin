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

        <!-- Tracking Records List -->
        <div class="card-body py-0 scrollbar material-tracking-list-body" style="max-height: 600px; overflow-y: auto;">
            <?php if (empty($records)): ?>
                <?php echo $this->render('material-tracking/empty-state', []); ?>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <?php echo $this->render('material-tracking/list-item', ['record' => $record, 'can_manage' => $can_manage]); ?>
                <?php endforeach; ?>
            <?php endif; ?>
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

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <div id="material-tracking-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto toast-title">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body toast-message">
                Message
            </div>
        </div>
    </div>
</div>

<style>
.wecoza-material-tracking-dashboard .search-input {
    border-radius: 0.375rem;
}
.material-tracking-list-body {
    min-height: 300px;
}
.material-tracking-list-item {
    transition: background-color 0.2s;
}
.material-tracking-list-item:first-child {
    border-top: none !important;
}
.material-tracking-list-item:hover {
    background-color: rgba(0, 0, 0, 0.02);
}
.mark-delivered-btn {
    opacity: 0.9;
    transition: opacity 0.2s;
}
.mark-delivered-btn:hover {
    opacity: 1;
}
</style>

<script>
jQuery(document).ready(function($) {
    const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    let lastUpdateTime = Date.now();
    
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
    
    // Show toast notification
    function showToast(type, message) {
        const toast = $('#material-tracking-toast');
        const toastEl = new bootstrap.Toast(toast[0]);
        
        toast.removeClass('bg-success bg-danger bg-info');
        if (type === 'success') {
            toast.addClass('bg-success text-white');
            $('.toast-title').text('Success');
        } else if (type === 'error') {
            toast.addClass('bg-danger text-white');
            $('.toast-title').text('Error');
        } else {
            toast.addClass('bg-info text-white');
            $('.toast-title').text('Info');
        }
        
        $('.toast-message').text(message);
        toastEl.show();
    }
    
    // Mark as delivered
    $(document).on('click', '.mark-delivered-btn', function() {
        const button = $(this);
        const classId = button.data('class-id');
        const nonce = button.data('nonce');
        const originalHtml = button.html();
        
        button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Processing...');
        
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
                    showToast('success', response.data.message);
                    
                    // Update UI
                    const listItem = button.closest('.material-tracking-list-item');
                    listItem.find('.delivery-status-badge').html('<span class="badge badge-phoenix badge-phoenix-success fs-10">✅ Delivered</span>');
                    button.replaceWith('<span class="text-success fw-bold fs-9">✓ Confirmed</span>');
                    
                    // Update statistics
                    const notifiedCount = parseInt($('#stat-notified').text()) - 1;
                    const deliveredCount = parseInt($('#stat-delivered').text()) + 1;
                    $('#stat-notified').text(notifiedCount);
                    $('#stat-delivered').text(deliveredCount);
                    
                    lastUpdateTime = Date.now();
                    updateLastUpdatedTime();
                } else {
                    showToast('error', response.data.message || 'Failed to mark as delivered');
                    button.prop('disabled', false).html(originalHtml);
                }
            },
            error: function() {
                showToast('error', 'An error occurred. Please try again.');
                button.prop('disabled', false).html(originalHtml);
            }
        });
    });
    
    // Search and filter
    function filterRecords() {
        const searchTerm = $('#material-tracking-search').val().toLowerCase();
        const statusFilter = $('#status-filter').val();
        const typeFilter = $('#notification-type-filter').val();
        let visibleCount = 0;
        
        $('.material-tracking-list-item').each(function() {
            const item = $(this);
            const text = item.text().toLowerCase();
            const status = item.data('status');
            const type = item.data('notification-type');
            
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
            
            item.toggle(visible);
            if (visible) visibleCount++;
        });
        
        $('#visible-count').text(visibleCount);
        
        // Show/hide empty state
        if (visibleCount === 0) {
            $('.material-tracking-empty-state').show();
        } else {
            $('.material-tracking-empty-state').hide();
        }
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
