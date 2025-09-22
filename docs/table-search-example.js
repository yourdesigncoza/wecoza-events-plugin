/**
 * Classes Table Search JavaScript for WeCoza Classes Plugin
 *
 * Implements real-time search functionality for the classes display table.
 * Searches both client ID and client name fields with debouncing for performance.
 * 
 * @package WeCozaClasses
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Search configuration
     */
    const SEARCH_CONFIG = {
        debounceDelay: 300,        // Milliseconds to wait before executing search
        minSearchLength: 0,        // Minimum characters to trigger search (0 = search on empty)
        searchInputSelector: '.search-input.search.form-control-sm',
        tableSelector: '.table-responsive table',
        tableRowSelector: 'tbody tr',
        clientColumnIndex: 1,      // 0-based index of "Client ID & Name" column
        itemsPerPage: 20           // Number of items to display per page
    };

    /**
     * Search state
     */
    let searchTimeout = null;
    let $searchInput = null;
    let $table = null;
    let $tableRows = null;
    let totalRows = 0;
    let visibleRows = 0;

    /**
     * Pagination state
     */
    let currentPage = 1;
    let totalPages = 1;
    let filteredRows = [];
    let $paginationContainer = null;

    /**
     * Initialization state
     */
    let isInitialized = false;

    /**
     * Initialize the search functionality
     */
    function classes_init_table_search() {
        // Prevent duplicate initialization
        if (isInitialized) {
            console.log('WeCoza Classes: Already initialized, skipping duplicate initialization');
            return;
        }

        // Find search elements
        $searchInput = $(SEARCH_CONFIG.searchInputSelector);
        $table = $(SEARCH_CONFIG.tableSelector);
        $tableRows = $table.find(SEARCH_CONFIG.tableRowSelector);

        // Validate elements exist
        if ($searchInput.length === 0) {
            // console.warn('WeCoza Classes: Search input not found');
            return;
        }

        if ($table.length === 0) {
            console.warn('WeCoza Classes: Table not found');
            return;
        }

        if ($tableRows.length === 0) {
            console.warn('WeCoza Classes: No table rows found');
            return;
        }

        // Initialize counters
        totalRows = $tableRows.length;
        visibleRows = totalRows;

        // Bind search event with debouncing
        $searchInput.on('input keyup paste', function() {
            const searchTerm = $(this).val();
            classes_debounced_search(searchTerm);
        });

        // Clear search on form reset
        $searchInput.closest('form').on('reset', function() {
            setTimeout(function() {
                classes_perform_search('');
            }, 10);
        });

        // Add search status indicator
        classes_add_search_status_indicator();

        // Initialize pagination
        classes_init_pagination();

        // Show initial page
        classes_update_pagination_display();

        // Mark as initialized
        isInitialized = true;

        console.log('WeCoza Classes: Table search and pagination initialized successfully');
    }

    /**
     * Debounced search function to improve performance
     * 
     * @param {string} searchTerm - The search term to filter by
     */
    function classes_debounced_search(searchTerm) {
        // Clear existing timeout
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // Set new timeout
        searchTimeout = setTimeout(function() {
            classes_perform_search(searchTerm);
        }, SEARCH_CONFIG.debounceDelay);
    }

    /**
     * Perform the actual search filtering
     *
     * @param {string} searchTerm - The search term to filter by
     */
    function classes_perform_search(searchTerm) {
        // Normalize search term
        const normalizedSearchTerm = searchTerm.toLowerCase().trim();
        filteredRows = [];

        // Filter rows based on search term
        $tableRows.each(function() {
            const $row = $(this);
            const $clientCell = $row.find('td').eq(SEARCH_CONFIG.clientColumnIndex);

            if ($clientCell.length === 0) {
                return;
            }

            // Get client data text (includes both ID and name)
            const clientText = $clientCell.text().toLowerCase().trim();

            // Check if search term matches client ID or name
            let isMatch = true;
            if (normalizedSearchTerm.length >= SEARCH_CONFIG.minSearchLength) {
                isMatch = classes_search_matches(clientText, normalizedSearchTerm);
            }

            if (isMatch) {
                filteredRows.push($row);
            }
        });

        visibleRows = filteredRows.length;

        // Reset to page 1 when search changes
        currentPage = 1;

        // Update pagination and display
        classes_update_pagination_display();
        classes_update_search_status(searchTerm, visibleRows, totalRows);
    }

    /**
     * Check if search term matches the client data
     * 
     * @param {string} clientText - The client text to search in
     * @param {string} searchTerm - The search term to look for
     * @returns {boolean} - True if match found
     */
    function classes_search_matches(clientText, searchTerm) {
        // Direct substring match (covers both ID and name)
        if (clientText.includes(searchTerm)) {
            return true;
        }

        // Split client text by common separators to search individual parts
        const clientParts = clientText.split(/[\s:,-]+/).filter(part => part.length > 0);
        
        // Check if any part starts with the search term
        return clientParts.some(part => part.startsWith(searchTerm));
    }

    /**
     * Add search status indicator to the interface
     */
    function classes_add_search_status_indicator() {
        // Check if status indicator already exists
        if ($('#classes-search-status').length > 0) {
            return;
        }
        // Create status indicator
        const $statusIndicator = $('<span>', {
            id: 'classes-search-status',
            class: 'badge badge-phoenix badge-phoenix-primary mb-2',
            style: 'display: none;'
        });

        // Insert before the table (between card header and table)
        $('#classes-table').before($statusIndicator);
    }

    /**
     * Initialize pagination functionality
     */
    function classes_init_pagination() {
        // Check if pagination container already exists
        if ($('#classes-pagination').length > 0) {
            $paginationContainer = $('#classes-pagination');
            return;
        }

        // Create pagination container
        $paginationContainer = $('<div>', {
            id: 'classes-pagination',
            class: 'd-flex justify-content-between mt-3'
        });

        // Insert pagination after the table
        $('.table-responsive').after($paginationContainer);

        // Initialize filtered rows with all rows
        filteredRows = $tableRows.toArray().map(row => $(row));
        totalRows = filteredRows.length;
        visibleRows = totalRows;

        // Calculate initial pagination
        classes_calculate_pagination_info();
    }

    /**
     * Calculate pagination information
     */
    function classes_calculate_pagination_info() {
        totalPages = Math.ceil(filteredRows.length / SEARCH_CONFIG.itemsPerPage);
        if (totalPages === 0) totalPages = 1;

        // Ensure current page is within bounds
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        if (currentPage < 1) {
            currentPage = 1;
        }
    }

    /**
     * Update pagination display and show appropriate rows
     */
    function classes_update_pagination_display() {
        // Calculate pagination info
        classes_calculate_pagination_info();

        // Hide all rows first
        $tableRows.hide();

        // Show only rows for current page
        const startIndex = (currentPage - 1) * SEARCH_CONFIG.itemsPerPage;
        const endIndex = startIndex + SEARCH_CONFIG.itemsPerPage;

        for (let i = startIndex; i < endIndex && i < filteredRows.length; i++) {
            filteredRows[i].show();
        }

        // Update pagination controls
        classes_update_pagination_controls();
    }

    /**
     * Update pagination controls HTML
     */
    function classes_update_pagination_controls() {
        if (!$paginationContainer) return;

        // Calculate display range
        const startItem = filteredRows.length === 0 ? 0 : (currentPage - 1) * SEARCH_CONFIG.itemsPerPage + 1;
        const endItem = Math.min(currentPage * SEARCH_CONFIG.itemsPerPage, filteredRows.length);
        const totalItems = filteredRows.length;

        // Build pagination HTML
        let paginationHTML = '';

        // Info display (left side)
        if (totalItems > 0) {
            paginationHTML += `<span class="d-none d-sm-inline-block" data-list-info="data-list-info">
                ${startItem} to ${endItem} <span class="text-body-tertiary"> Items of </span>${totalItems}
            </span>`;
        } else {
            paginationHTML += `<span class="d-none d-sm-inline-block" data-list-info="data-list-info">
                0 <span class="text-body-tertiary"> Items of </span>0
            </span>`;
        }

        // Navigation controls (right side) - Bootstrap pagination
        paginationHTML += '<nav aria-label="Classes pagination">';
        paginationHTML += '<ul class="pagination pagination-sm">';

        // Previous button
        const prevDisabled = currentPage <= 1;
        if (prevDisabled) {
            paginationHTML += `<li class="page-item disabled">
                <span class="page-link" aria-hidden="true">&laquo;</span>
            </li>`;
        } else {
            paginationHTML += `<li class="page-item">
                <a class="page-link" href="#" data-list-pagination="prev" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>`;
        }

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            const isActive = i === currentPage;
            if (isActive) {
                paginationHTML += `<li class="page-item active" aria-current="page">
                    <span class="page-link">${i}</span>
                </li>`;
            } else {
                paginationHTML += `<li class="page-item">
                    <a class="page-link" href="#" data-page-number="${i}">${i}</a>
                </li>`;
            }
        }

        // Next button
        const nextDisabled = currentPage >= totalPages;
        if (nextDisabled) {
            paginationHTML += `<li class="page-item disabled">
                <span class="page-link" aria-hidden="true">&raquo;</span>
            </li>`;
        } else {
            paginationHTML += `<li class="page-item">
                <a class="page-link" href="#" data-list-pagination="next" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>`;
        }

        paginationHTML += '</ul>';
        paginationHTML += '</nav>';

        // Update container
        $paginationContainer.html(paginationHTML);

        // Bind click events
        classes_bind_pagination_events();
    }

    /**
     * Bind pagination event handlers
     */
    function classes_bind_pagination_events() {
        if (!$paginationContainer) return;

        // Use event delegation to prevent rebinding issues
        $paginationContainer.off('click.pagination').on('click.pagination', '[data-list-pagination="prev"]', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (!$(this).closest('.page-item').hasClass('disabled')) {
                classes_go_to_page(currentPage - 1);
            }
        });

        $paginationContainer.off('click.pagination').on('click.pagination', '[data-list-pagination="next"]', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (!$(this).closest('.page-item').hasClass('disabled')) {
                classes_go_to_page(currentPage + 1);
            }
        });

        $paginationContainer.off('click.pagination').on('click.pagination', '[data-page-number]', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const pageNumber = parseInt($(this).data('page-number'));
            if (pageNumber !== currentPage) { // Prevent clicking same page
                classes_go_to_page(pageNumber);
            }
        });
    }

    /**
     * Navigate to specific page
     *
     * @param {number} pageNumber - Page number to navigate to
     */
    function classes_go_to_page(pageNumber) {
        if (pageNumber < 1 || pageNumber > totalPages) {
            return;
        }

        currentPage = pageNumber;
        classes_update_pagination_display();
    }

    /**
     * Update search status indicator
     * 
     * @param {string} searchTerm - The current search term
     * @param {number} visible - Number of visible rows
     * @param {number} total - Total number of rows
     */
    function classes_update_search_status(searchTerm, visible, total) {
        const $statusIndicator = $('#classes-search-status');
        
        if ($statusIndicator.length === 0) {
            return;
        }

        // Show/hide status based on search activity
        if (searchTerm.trim().length === 0) {
            $statusIndicator.hide();
            return;
        }

        // Update status text
        let statusText = '';
        if (visible === 0) {
            statusText = `No classes found matching "${searchTerm}"`;
        } else if (visible === total) {
            statusText = `Showing all ${total} classes`;
        } else {
            statusText = `Showing ${visible} of ${total} classes matching "${searchTerm}"`;
        }

        $statusIndicator.text(statusText).show();
    }

    /**
     * Reset search functionality
     */
    function classes_reset_search() {
        if ($searchInput) {
            $searchInput.val('');
            currentPage = 1;
            classes_perform_search('');
        }
    }

    /**
     * Force re-initialization (useful for debugging)
     */
    function classes_force_reinit() {
        isInitialized = false;
        classes_init_table_search();
    }

    /**
     * Reset pagination to first page
     */
    function classes_reset_pagination() {
        currentPage = 1;
        classes_update_pagination_display();
    }

    /**
     * Get current search and pagination statistics
     *
     * @returns {object} - Object containing search and pagination statistics
     */
    function classes_get_search_stats() {
        return {
            totalRows: totalRows,
            visibleRows: visibleRows,
            filteredRows: filteredRows.length,
            searchTerm: $searchInput ? $searchInput.val() : '',
            isSearchActive: $searchInput ? $searchInput.val().trim().length > 0 : false,
            currentPage: currentPage,
            totalPages: totalPages,
            itemsPerPage: SEARCH_CONFIG.itemsPerPage
        };
    }

    /**
     * Public API for external access
     */
    window.WeCozaClassesSearch = {
        init: classes_init_table_search,
        reset: classes_reset_search,
        resetPagination: classes_reset_pagination,
        goToPage: classes_go_to_page,
        getStats: classes_get_search_stats,
        forceReinit: classes_force_reinit
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Small delay to ensure all elements are rendered
        setTimeout(function() {
            classes_init_table_search();
        }, 100);
    });

})(jQuery);