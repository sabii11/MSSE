<?php

if (!defined('ABSPATH')) {
    exit;
}

class TRS_Frontend {
    
    public function __construct() {
        add_action('wp_footer', array($this, 'add_booking_modal'));
    }
    
    public function render_booking_form() {
        $booking_status = TRS_Database::get_setting('accept_bookings', '1');
        
        if ($booking_status != '1') {
            return '<div class="trs-booking-closed"><p>Sorry, we are not accepting reservations at this time.</p></div>';
        }
        
        ob_start();
        ?>
        <div id="trs-booking-form" class="trs-booking-container">
            <div class="trs-form-header">
                <h3>Make a Reservation</h3>
                <div class="trs-step-indicator">
                    <div class="trs-step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-title">Party Size</span>
                    </div>
                    <div class="trs-step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-title">Date & Time</span>
                    </div>
                    <div class="trs-step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-title">Details</span>
                    </div>
                    <div class="trs-step" data-step="4">
                        <span class="step-number">4</span>
                        <span class="step-title">Review</span>
                    </div>
                </div>
            </div>
            
            <form id="trs-reservation-form" method="post">
                <!-- Step 1: Party Size -->
                <div class="trs-form-step" id="step-1">
                    <h4>How many people will be dining?</h4>
                    <div class="trs-party-size-grid">
                        <?php for ($i = 1; $i <= 15; $i++): ?>
                            <button type="button" class="trs-party-size-btn" data-size="<?php echo $i; ?>">
                                <?php echo $i; ?> <?php echo $i == 1 ? 'Person' : 'People'; ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="party_size" name="party_size" required>
                    
                    <div class="trs-form-navigation">
                        <button type="button" class="trs-btn trs-btn-primary trs-next-step" disabled>Next</button>
                    </div>
                </div>
                
                <!-- Step 2: Date & Time -->
                <div class="trs-form-step" id="step-2" style="display: none;">
                    <h4>Select your preferred date and time</h4>
                    
                    <div class="trs-date-time-selection">
                        <div class="trs-date-section">
                            <label for="reservation_date">Date:</label>
                            <input type="date" id="reservation_date" name="reservation_date" required 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+' . TRS_Database::get_setting('max_advance_days', 30) . ' days')); ?>">
                        </div>
                        
                        <div class="trs-time-section">
                            <label>Available Times:</label>
                            <div id="available-times" class="trs-time-slots">
                                <p>Please select a date first.</p>
                            </div>
                            <input type="hidden" id="reservation_time" name="reservation_time" required>
                        </div>
                    </div>
                    
                    <div class="trs-form-navigation">
                        <button type="button" class="trs-btn trs-btn-secondary trs-prev-step">Back</button>
                        <button type="button" class="trs-btn trs-btn-primary trs-next-step" disabled>Next</button>
                    </div>
                </div>
                
                <!-- Step 3: Customer Details -->
                <div class="trs-form-step" id="step-3" style="display: none;">
                    <h4>Please provide your contact information</h4>
                    
                    <div class="trs-form-row">
                        <div class="trs-form-group">
                            <label for="customer_name">Full Name *</label>
                            <input type="text" id="customer_name" name="customer_name" required>
                        </div>
                        
                        <div class="trs-form-group">
                            <label for="customer_email">Email Address *</label>
                            <input type="email" id="customer_email" name="customer_email" required>
                        </div>
                    </div>
                    
                    <div class="trs-form-row">
                        <div class="trs-form-group">
                            <label for="customer_phone">Phone Number *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" required>
                        </div>
                    </div>
                    
                    <div class="trs-form-group">
                        <label for="special_request">Special Requests (Optional)</label>
                        <textarea id="special_request" name="special_request" rows="3" 
                                  placeholder="Any dietary restrictions, special occasions, or other requests..."></textarea>
                    </div>
                    
                    <div class="trs-form-navigation">
                        <button type="button" class="trs-btn trs-btn-secondary trs-prev-step">Back</button>
                        <button type="button" class="trs-btn trs-btn-primary trs-next-step">Review Reservation</button>
                    </div>
                </div>
                
                <!-- Step 4: Review & Confirm -->
                <div class="trs-form-step" id="step-4" style="display: none;">
                    <h4>Review Your Reservation</h4>
                    
                    <div class="trs-reservation-summary">
                        <div class="trs-summary-section">
                            <h5>Reservation Details</h5>
                            <div class="trs-summary-item">
                                <span class="label">Date:</span>
                                <span class="value" id="summary-date">-</span>
                            </div>
                            <div class="trs-summary-item">
                                <span class="label">Time:</span>
                                <span class="value" id="summary-time">-</span>
                            </div>
                            <div class="trs-summary-item">
                                <span class="label">Party Size:</span>
                                <span class="value" id="summary-party-size">-</span>
                            </div>
                        </div>
                        
                        <div class="trs-summary-section">
                            <h5>Contact Information</h5>
                            <div class="trs-summary-item">
                                <span class="label">Name:</span>
                                <span class="value" id="summary-name">-</span>
                            </div>
                            <div class="trs-summary-item">
                                <span class="label">Email:</span>
                                <span class="value" id="summary-email">-</span>
                            </div>
                            <div class="trs-summary-item">
                                <span class="label">Phone:</span>
                                <span class="value" id="summary-phone">-</span>
                            </div>
                            <div class="trs-summary-item" id="summary-special-request-container" style="display: none;">
                                <span class="label">Special Requests:</span>
                                <span class="value" id="summary-special-request">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="trs-terms-section">
                        <label class="trs-checkbox-label">
                            <input type="checkbox" id="terms_agreement" required>
                            <span class="checkmark"></span>
                            I agree to the reservation terms and conditions
                        </label>
                    </div>
                    
                    <div class="trs-form-navigation">
                        <button type="button" class="trs-btn trs-btn-secondary trs-prev-step">Back</button>
                        <button type="submit" class="trs-btn trs-btn-success" id="submit-reservation">Confirm Reservation</button>
                    </div>
                </div>
                
                <!-- Loading State -->
                <div class="trs-form-step trs-loading-step" id="loading-step" style="display: none;">
                    <div class="trs-loading-spinner"></div>
                    <p>Processing your reservation...</p>
                </div>
                
                <!-- Success State -->
                <div class="trs-form-step trs-success-step" id="success-step" style="display: none;">
                    <div class="trs-success-icon">✓</div>
                    <h4>Reservation Confirmed!</h4>
                    <p>Thank you for your reservation. You will receive a confirmation email shortly.</p>
                    <div id="reservation-details"></div>
                    <button type="button" class="trs-btn trs-btn-primary" onclick="location.reload()">Make Another Reservation</button>
                </div>
                
                <!-- Error State -->
                <div class="trs-form-step trs-error-step" id="error-step" style="display: none;">
                    <div class="trs-error-icon">⚠</div>
                    <h4>Reservation Failed</h4>
                    <p id="error-message">An error occurred while processing your reservation. Please try again.</p>
                    <button type="button" class="trs-btn trs-btn-primary" onclick="location.reload()">Try Again</button>
                </div>
                
                <?php wp_nonce_field('trs_nonce', 'trs_nonce'); ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_booking_modal() {
        // Add modal structure for popup bookings if needed
        ?>
        <div id="trs-booking-modal" class="trs-modal" style="display: none;">
            <div class="trs-modal-content">
                <span class="trs-close">&times;</span>
                <div id="trs-modal-booking-form"></div>
            </div>
        </div>
        <?php
    }
    
    public static function format_date_for_display($date) {
        return date('l, F j, Y', strtotime($date));
    }
    
    public static function format_time_for_display($time) {
        return date('g:i A', strtotime($time));
    }
}