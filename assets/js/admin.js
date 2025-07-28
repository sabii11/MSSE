jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize admin functionality
    initializeAdmin();
    
    function initializeAdmin() {
        // Booking toggle
        $('#toggle-bookings').on('click', function() {
            toggleBookings();
        });
        
        // Status change
        $('.status-select').on('change', function() {
            const reservationId = $(this).data('reservation-id');
            const newStatus = $(this).val();
            updateReservationStatus(reservationId, newStatus);
        });
        
        // Table management
        $('#add-table-form').on('submit', function(e) {
            e.preventDefault();
            addTable();
        });
        
        $('.edit-table').on('click', function() {
            const tableData = {
                id: $(this).data('id'),
                number: $(this).data('number'),
                capacity: $(this).data('capacity'),
                active: $(this).data('active')
            };
            openEditModal(tableData);
        });
        
        $('#edit-table-form').on('submit', function(e) {
            e.preventDefault();
            updateTable();
        });
        
        $('.delete-table').on('click', function() {
            const tableId = $(this).data('id');
            if (confirm('Are you sure you want to delete this table? This action cannot be undone.')) {
                deleteTable(tableId);
            }
        });
        
        // Filter form auto-submit
        $('.trs-filters input, .trs-filters select').on('change', function() {
            $(this).closest('form').submit();
        });
    }
    
    function toggleBookings() {
        const $button = $('#toggle-bookings');
        
        // Show loading state
        $button.prop('disabled', true).addClass('trs-loading');
        
        $.ajax({
            url: trs_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'trs_toggle_bookings',
                nonce: trs_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update button state
                    const isOpen = response.data.status === '1';
                    $button
                        .removeClass('button-secondary button-primary')
                        .addClass(isOpen ? 'button-secondary' : 'button-primary')
                        .text(isOpen ? 'Close Bookings' : 'Open Bookings');
                    
                    // Update description
                    $('.trs-booking-control .description strong').text(
                        isOpen ? 'Accepting Bookings' : 'Bookings Closed'
                    );
                    
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data || 'Failed to toggle bookings', 'error');
                }
            },
            error: function() {
                showMessage('Network error. Please try again.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).removeClass('trs-loading');
            }
        });
    }
    
    function updateReservationStatus(reservationId, status) {
        const $select = $(`.status-select[data-reservation-id="${reservationId}"]`);
        const originalValue = $select.data('original-value') || $select.val();
        
        // Store original value if not already stored
        if (!$select.data('original-value')) {
            $select.data('original-value', originalValue);
        }
        
        // Show loading state
        $select.prop('disabled', true);
        
        $.ajax({
            url: trs_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'trs_update_reservation_status',
                reservation_id: reservationId,
                status: status,
                nonce: trs_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update status badge if it exists
                    const $statusBadge = $select.closest('tr').find('.trs-status');
                    if ($statusBadge.length) {
                        $statusBadge
                            .removeClass(function(index, className) {
                                return (className.match(/\btrs-status-\S+/g) || []).join(' ');
                            })
                            .addClass(`trs-status-${status}`)
                            .text(status.replace('_', ' ').charAt(0).toUpperCase() + status.replace('_', ' ').slice(1));
                    }
                    
                    // Update stored original value
                    $select.data('original-value', status);
                    
                    showMessage('Status updated successfully', 'success');
                } else {
                    // Revert to original value
                    $select.val(originalValue);
                    showMessage(response.data || 'Failed to update status', 'error');
                }
            },
            error: function() {
                // Revert to original value
                $select.val(originalValue);
                showMessage('Network error. Please try again.', 'error');
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    }
    
    function addTable() {
        const $form = $('#add-table-form');
        const $submitButton = $form.find('input[type="submit"]');
        
        // Get form data
        const tableNumber = $('#table_number').val().trim();
        const capacity = $('#capacity').val();
        
        if (!tableNumber) {
            showMessage('Please enter a table number', 'error');
            return;
        }
        
        // Show loading state
        $submitButton.prop('disabled', true).addClass('trs-loading');
        
        $.ajax({
            url: trs_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'trs_add_table',
                table_number: tableNumber,
                capacity: capacity,
                nonce: trs_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Table added successfully', 'success');
                    
                    // Reset form
                    $form[0].reset();
                    
                    // Reload page to show new table
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(response.data || 'Failed to add table', 'error');
                }
            },
            error: function() {
                showMessage('Network error. Please try again.', 'error');
            },
            complete: function() {
                $submitButton.prop('disabled', false).removeClass('trs-loading');
            }
        });
    }
    
    function openEditModal(tableData) {
        // Populate form fields
        $('#edit_table_id').val(tableData.id);
        $('#edit_table_number').val(tableData.number);
        $('#edit_capacity').val(tableData.capacity);
        $('#edit_is_active').val(tableData.active);
        
        // Show modal
        $('#edit-table-modal').show();
    }
    
    function updateTable() {
        const $form = $('#edit-table-form');
        const $submitButton = $form.find('input[type="submit"]');
        
        // Get form data
        const tableId = $('#edit_table_id').val();
        const tableNumber = $('#edit_table_number').val().trim();
        const capacity = $('#edit_capacity').val();
        const isActive = $('#edit_is_active').val();
        
        if (!tableNumber) {
            showMessage('Please enter a table number', 'error');
            return;
        }
        
        // Show loading state
        $submitButton.prop('disabled', true).addClass('trs-loading');
        
        $.ajax({
            url: trs_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'trs_update_table',
                table_id: tableId,
                table_number: tableNumber,
                capacity: capacity,
                is_active: isActive,
                nonce: trs_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Table updated successfully', 'success');
                    
                    // Close modal
                    closeEditModal();
                    
                    // Reload page to show updated table
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(response.data || 'Failed to update table', 'error');
                }
            },
            error: function() {
                showMessage('Network error. Please try again.', 'error');
            },
            complete: function() {
                $submitButton.prop('disabled', false).removeClass('trs-loading');
            }
        });
    }
    
    function deleteTable(tableId) {
        $.ajax({
            url: trs_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'trs_delete_table',
                table_id: tableId,
                nonce: trs_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage('Table deleted successfully', 'success');
                    
                    // Remove table row from DOM
                    $(`.delete-table[data-id="${tableId}"]`).closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    showMessage(response.data || 'Failed to delete table', 'error');
                }
            },
            error: function() {
                showMessage('Network error. Please try again.', 'error');
            }
        });
    }
    
    function closeEditModal() {
        $('#edit-table-modal').hide();
    }
    
    // Make closeEditModal available globally for the Cancel button
    window.closeEditModal = closeEditModal;
    
    // Close modal on click outside
    $(document).on('click', '#edit-table-modal', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });
    
    // Close modal on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#edit-table-modal').is(':visible')) {
            closeEditModal();
        }
    });
    
    function showMessage(message, type) {
        // Remove existing messages
        $('.trs-message').remove();
        
        // Create new message
        const $message = $(`<div class="trs-message ${type}">${message}</div>`);
        
        // Insert message at the top of the content
        $('.wrap').prepend($message);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll to message
        $message[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    // Real-time search functionality
    function initializeSearch() {
        const $searchInput = $('#reservation-search');
        if ($searchInput.length) {
            $searchInput.on('input', debounce(function() {
                const searchTerm = $(this).val().toLowerCase();
                filterReservations(searchTerm);
            }, 300));
        }
    }
    
    function filterReservations(searchTerm) {
        $('.wp-list-table tbody tr').each(function() {
            const $row = $(this);
            const rowText = $row.text().toLowerCase();
            
            if (searchTerm === '' || rowText.includes(searchTerm)) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = function() {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Initialize search if on reservations page
    if (window.location.href.includes('trs-reservations')) {
        initializeSearch();
    }
    
    // Export functionality
    function initializeExport() {
        $('.export-reservations').on('click', function(e) {
            e.preventDefault();
            
            const format = $(this).data('format') || 'csv';
            const filters = getActiveFilters();
            
            exportReservations(format, filters);
        });
    }
    
    function getActiveFilters() {
        const filters = {};
        
        // Get filter values from the current page
        const $filterForm = $('.trs-filters form');
        if ($filterForm.length) {
            $filterForm.find('input, select').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                const value = $input.val();
                
                if (name && value && name !== 'page') {
                    filters[name] = value;
                }
            });
        }
        
        return filters;
    }
    
    function exportReservations(format, filters) {
        // Create download link
        const params = new URLSearchParams(filters);
        params.append('action', 'trs_export_reservations');
        params.append('format', format);
        params.append('nonce', trs_admin_ajax.nonce);
        
        const downloadUrl = `${trs_admin_ajax.ajax_url}?${params.toString()}`;
        
        // Create temporary download link
        const $downloadLink = $('<a>')
            .attr('href', downloadUrl)
            .attr('download', `reservations_${new Date().toISOString().split('T')[0]}.${format}`)
            .appendTo('body');
        
        $downloadLink[0].click();
        $downloadLink.remove();
    }
    
    // Bulk actions
    function initializeBulkActions() {
        $('#doaction, #doaction2').on('click', function(e) {
            const action = $(this).siblings('select').val();
            
            if (action === '-1') {
                e.preventDefault();
                return;
            }
            
            const $checkedBoxes = $('.wp-list-table tbody input[type="checkbox"]:checked');
            
            if ($checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one item.');
                return;
            }
            
            if (action === 'delete') {
                if (!confirm(`Are you sure you want to delete ${$checkedBoxes.length} item(s)?`)) {
                    e.preventDefault();
                    return;
                }
            }
        });
        
        // Select all functionality
        $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('.wp-list-table tbody input[type="checkbox"]').prop('checked', isChecked);
        });
    }
    
    // Quick stats refresh
    function initializeStatsRefresh() {
        $('.refresh-stats').on('click', function() {
            const $button = $(this);
            $button.prop('disabled', true).addClass('trs-loading');
            
            // Reload the page to refresh stats
            setTimeout(function() {
                location.reload();
            }, 500);
        });
    }
    
    // Date picker enhancements
    function initializeDatePickers() {
        // Add date picker for better UX if available
        if ($.fn.datepicker) {
            $('.date-picker').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true
            });
        }
    }
    
    // Time slot management
    function initializeTimeSlots() {
        $('.time-slot').on('click', function() {
            $(this).toggleClass('blocked');
            // Here you could add AJAX to save the blocked time slot
        });
    }
    
    // Initialize all additional features
    initializeExport();
    initializeBulkActions();
    initializeStatsRefresh();
    initializeDatePickers();
    initializeTimeSlots();
    
    // Dashboard widget refresh
    if ($('.trs-dashboard-widget').length) {
        setInterval(function() {
            refreshDashboardWidget();
        }, 60000); // Refresh every minute
    }
    
    function refreshDashboardWidget() {
        $.ajax({
            url: trs_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'trs_refresh_dashboard_widget',
                nonce: trs_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.trs-dashboard-widget').html(response.data.html);
                }
            }
        });
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save current form
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const $form = $('form:visible').first();
            if ($form.length) {
                $form.submit();
            }
        }
        
        // Ctrl/Cmd + N to add new table (on tables page)
        if ((e.ctrlKey || e.metaKey) && e.key === 'n' && window.location.href.includes('trs-tables')) {
            e.preventDefault();
            $('#table_number').focus();
        }
    });
    
    // Form validation enhancements
    function validateForm($form) {
        let isValid = true;
        
        $form.find('input[required], select[required]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();
            
            if (!value) {
                $field.addClass('form-invalid');
                isValid = false;
            } else {
                $field.removeClass('form-invalid');
            }
        });
        
        return isValid;
    }
    
    // Add form validation to all forms
    $('form').on('submit', function(e) {
        if (!validateForm($(this))) {
            e.preventDefault();
            showMessage('Please fill in all required fields', 'error');
        }
    });
    
    // Remove validation styling on input
    $(document).on('input change', 'input, select', function() {
        $(this).removeClass('form-invalid');
    });
});