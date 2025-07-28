jQuery(document).ready(function($) {
    'use strict';
    
    // Global variables
    let currentStep = 1;
    const totalSteps = 4;
    let formData = {};
    
    // Initialize the form
    initializeForm();
    
    function initializeForm() {
        // Party size selection
        $('.trs-party-size-btn').on('click', function() {
            $('.trs-party-size-btn').removeClass('selected');
            $(this).addClass('selected');
            
            const partySize = $(this).data('size');
            $('#party_size').val(partySize);
            formData.party_size = partySize;
            
            // Enable next button
            $('#step-1 .trs-next-step').prop('disabled', false);
        });
        
        // Date selection
        $('#reservation_date').on('change', function() {
            const selectedDate = $(this).val();
            const partySize = formData.party_size;
            
            if (selectedDate && partySize) {
                loadAvailableTimes(selectedDate, partySize);
            }
        });
        
        // Time slot selection
        $(document).on('click', '.trs-time-slot:not(.unavailable)', function() {
            $('.trs-time-slot').removeClass('selected');
            $(this).addClass('selected');
            
            const selectedTime = $(this).data('time');
            $('#reservation_time').val(selectedTime);
            formData.reservation_time = selectedTime;
            
            // Enable next button
            $('#step-2 .trs-next-step').prop('disabled', false);
        });
        
        // Form field validation for step 3
        $('#step-3 input[required]').on('input', function() {
            validateStep3();
        });
        
        // Navigation buttons
        $('.trs-next-step').on('click', function() {
            if (validateCurrentStep()) {
                nextStep();
            }
        });
        
        $('.trs-prev-step').on('click', function() {
            prevStep();
        });
        
        // Form submission
        $('#trs-reservation-form').on('submit', function(e) {
            e.preventDefault();
            
            if (validateCurrentStep()) {
                submitReservation();
            }
        });
        
        // Terms checkbox
        $('#terms_agreement').on('change', function() {
            $('#submit-reservation').prop('disabled', !$(this).is(':checked'));
        });
    }
    
    function loadAvailableTimes(date, partySize) {
        const $timeSlotsContainer = $('#available-times');
        
        // Show loading state
        $timeSlotsContainer.html('<div class="trs-loading-spinner"></div><p>Loading available times...</p>');
        
        $.ajax({
            url: trs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'trs_get_available_times',
                date: date,
                party_size: partySize,
                nonce: trs_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayAvailableTimes(response.data.times, response.data.message);
                } else {
                    showError(response.data || 'Failed to load available times');
                }
            },
            error: function() {
                showError('Network error. Please try again.');
            }
        });
    }
    
    function displayAvailableTimes(times, message) {
        const $timeSlotsContainer = $('#available-times');
        
        if (times.length === 0) {
            $timeSlotsContainer.html(`<p class="no-times-message">${message}</p>`);
            $('#step-2 .trs-next-step').prop('disabled', true);
            return;
        }
        
        let timeSlotsHtml = '';
        times.forEach(function(time) {
            timeSlotsHtml += `<div class="trs-time-slot" data-time="${time.value}">${time.display}</div>`;
        });
        
        $timeSlotsContainer.html(timeSlotsHtml);
        $('#step-2 .trs-next-step').prop('disabled', true);
    }
    
    function validateCurrentStep() {
        switch (currentStep) {
            case 1:
                return validateStep1();
            case 2:
                return validateStep2();
            case 3:
                return validateStep3();
            case 4:
                return validateStep4();
            default:
                return true;
        }
    }
    
    function validateStep1() {
        const partySize = $('#party_size').val();
        if (!partySize) {
            showError('Please select your party size');
            return false;
        }
        return true;
    }
    
    function validateStep2() {
        const date = $('#reservation_date').val();
        const time = $('#reservation_time').val();
        
        if (!date) {
            showError('Please select a date');
            return false;
        }
        
        if (!time) {
            showError('Please select a time');
            return false;
        }
        
        formData.reservation_date = date;
        formData.reservation_time = time;
        
        return true;
    }
    
    function validateStep3() {
        const name = $('#customer_name').val().trim();
        const email = $('#customer_email').val().trim();
        const phone = $('#customer_phone').val().trim();
        
        let isValid = true;
        
        if (!name) {
            isValid = false;
        }
        
        if (!email || !isValidEmail(email)) {
            isValid = false;
        }
        
        if (!phone) {
            isValid = false;
        }
        
        if (isValid) {
            formData.customer_name = name;
            formData.customer_email = email;
            formData.customer_phone = phone;
            formData.special_request = $('#special_request').val().trim();
        }
        
        $('#step-3 .trs-next-step').prop('disabled', !isValid);
        
        return isValid;
    }
    
    function validateStep4() {
        const termsAccepted = $('#terms_agreement').is(':checked');
        if (!termsAccepted) {
            showError('Please accept the terms and conditions');
            return false;
        }
        return true;
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function nextStep() {
        if (currentStep < totalSteps) {
            // Hide current step
            $(`#step-${currentStep}`).hide();
            
            // Update step indicator
            $(`.trs-step[data-step="${currentStep}"]`).removeClass('active').addClass('completed');
            
            currentStep++;
            
            // Show next step
            $(`#step-${currentStep}`).show();
            
            // Update step indicator
            $(`.trs-step[data-step="${currentStep}"]`).addClass('active');
            
            // Update summary if on step 4
            if (currentStep === 4) {
                updateSummary();
            }
            
            // Scroll to top
            $('.trs-booking-container')[0].scrollIntoView({ behavior: 'smooth' });
        }
    }
    
    function prevStep() {
        if (currentStep > 1) {
            // Hide current step
            $(`#step-${currentStep}`).hide();
            
            // Update step indicator
            $(`.trs-step[data-step="${currentStep}"]`).removeClass('active');
            
            currentStep--;
            
            // Show previous step
            $(`#step-${currentStep}`).show();
            
            // Update step indicator
            $(`.trs-step[data-step="${currentStep}"]`).removeClass('completed').addClass('active');
            
            // Scroll to top
            $('.trs-booking-container')[0].scrollIntoView({ behavior: 'smooth' });
        }
    }
    
    function updateSummary() {
        // Format date for display
        const dateObj = new Date(formData.reservation_date);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Format time for display
        const timeObj = new Date(`2000-01-01 ${formData.reservation_time}`);
        const formattedTime = timeObj.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        
        // Update summary fields
        $('#summary-date').text(formattedDate);
        $('#summary-time').text(formattedTime);
        $('#summary-party-size').text(`${formData.party_size} ${formData.party_size == 1 ? 'person' : 'people'}`);
        $('#summary-name').text(formData.customer_name);
        $('#summary-email').text(formData.customer_email);
        $('#summary-phone').text(formData.customer_phone);
        
        // Handle special request
        if (formData.special_request) {
            $('#summary-special-request').text(formData.special_request);
            $('#summary-special-request-container').show();
        } else {
            $('#summary-special-request-container').hide();
        }
    }
    
    function submitReservation() {
        // Show loading state
        $('.trs-form-step').hide();
        $('#loading-step').show();
        
        // Prepare form data
        const submitData = {
            action: 'trs_submit_reservation',
            party_size: formData.party_size,
            reservation_date: formData.reservation_date,
            reservation_time: formData.reservation_time,
            customer_name: formData.customer_name,
            customer_email: formData.customer_email,
            customer_phone: formData.customer_phone,
            special_request: formData.special_request,
            nonce: trs_ajax.nonce
        };
        
        $.ajax({
            url: trs_ajax.ajax_url,
            type: 'POST',
            data: submitData,
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data);
                } else {
                    showSubmissionError(response.data || 'Failed to submit reservation');
                }
            },
            error: function() {
                showSubmissionError('Network error. Please try again.');
            }
        });
    }
    
    function showSuccess(data) {
        $('#loading-step').hide();
        
        // Update success message with reservation details
        const detailsHtml = `
            <div class="trs-reservation-summary">
                <div class="trs-summary-section">
                    <h5>Your Reservation Details</h5>
                    <div class="trs-summary-item">
                        <span class="label">Confirmation Number:</span>
                        <span class="value">#${data.reservation_id}</span>
                    </div>
                    <div class="trs-summary-item">
                        <span class="label">Date:</span>
                        <span class="value">${data.reservation_date}</span>
                    </div>
                    <div class="trs-summary-item">
                        <span class="label">Time:</span>
                        <span class="value">${data.reservation_time}</span>
                    </div>
                    <div class="trs-summary-item">
                        <span class="label">Party Size:</span>
                        <span class="value">${data.party_size} ${data.party_size == 1 ? 'person' : 'people'}</span>
                    </div>
                    <div class="trs-summary-item">
                        <span class="label">Table:</span>
                        <span class="value">${data.table_number}</span>
                    </div>
                </div>
            </div>
        `;
        
        $('#reservation-details').html(detailsHtml);
        $('#success-step').show();
        
        // Scroll to top
        $('.trs-booking-container')[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    function showSubmissionError(message) {
        $('#loading-step').hide();
        $('#error-message').text(message);
        $('#error-step').show();
        
        // Scroll to top
        $('.trs-booking-container')[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    function showError(message) {
        // Create or update error message
        let $errorDiv = $('.trs-error-message');
        
        if ($errorDiv.length === 0) {
            $errorDiv = $('<div class="trs-error-message"></div>');
            $('.trs-booking-container').prepend($errorDiv);
        }
        
        $errorDiv.html(`<p>${message}</p>`).show();
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $errorDiv.fadeOut();
        }, 5000);
        
        // Scroll to error message
        $errorDiv[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    // Modal functionality
    function openModal() {
        $('#trs-booking-modal').show();
        $('body').addClass('trs-modal-open');
    }
    
    function closeModal() {
        $('#trs-booking-modal').hide();
        $('body').removeClass('trs-modal-open');
    }
    
    // Close modal on click outside or on close button
    $('.trs-close').on('click', closeModal);
    
    $(window).on('click', function(event) {
        if (event.target.id === 'trs-booking-modal') {
            closeModal();
        }
    });
    
    // Make functions available globally if needed
    window.TRS = {
        openModal: openModal,
        closeModal: closeModal
    };
    
    // Add error message styles if not already present
    if ($('.trs-error-message').length === 0) {
        $('<style>')
            .prop('type', 'text/css')
            .html(`
                .trs-error-message {
                    background: #f8d7da;
                    color: #721c24;
                    padding: 15px;
                    border: 1px solid #f5c6cb;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    display: none;
                }
                .trs-error-message p {
                    margin: 0;
                }
                body.trs-modal-open {
                    overflow: hidden;
                }
            `)
            .appendTo('head');
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(event) {
        // You could implement step navigation based on URL state here if needed
    });
    
    // Form validation helpers
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
    
    // Add real-time validation with debouncing
    $('#customer_email').on('input', debounce(function() {
        const email = $(this).val().trim();
        const $field = $(this);
        
        if (email && !isValidEmail(email)) {
            $field.addClass('invalid');
        } else {
            $field.removeClass('invalid');
        }
    }, 500));
    
    // Add phone number formatting (optional)
    $('#customer_phone').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        
        if (value.length >= 6) {
            value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
        } else if (value.length >= 3) {
            value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
        }
        
        $(this).val(value);
    });
    
    // Add field validation styles
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .trs-form-group input.invalid,
            .trs-form-group textarea.invalid {
                border-color: #dc3545;
                box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
            }
            .no-times-message {
                padding: 20px;
                text-align: center;
                color: #666;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
        `)
        .appendTo('head');
});