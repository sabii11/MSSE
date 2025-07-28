<?php

if (!defined('ABSPATH')) {
    exit;
}

class TRS_Ajax {
    
    public static function get_available_times() {
        check_ajax_referer('trs_nonce', 'nonce');
        
        $date = sanitize_text_field($_POST['date']);
        $party_size = intval($_POST['party_size']);
        
        if (empty($date) || $party_size < 1) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Check if bookings are accepting
        $booking_status = TRS_Database::get_setting('accept_bookings', '1');
        if ($booking_status != '1') {
            wp_send_json_error('Reservations are currently closed');
        }
        
        // Check if date is valid (not in the past, within booking window)
        $today = date('Y-m-d');
        $max_advance_days = TRS_Database::get_setting('max_advance_days', 30);
        $max_date = date('Y-m-d', strtotime("+{$max_advance_days} days"));
        
        if ($date < $today || $date > $max_date) {
            wp_send_json_error('Invalid date selected');
        }
        
        $available_times = TRS_Database::get_available_times($date, $party_size);
        
        if (empty($available_times)) {
            wp_send_json_success(array(
                'times' => array(),
                'message' => 'No available times for the selected date and party size.'
            ));
        } else {
            $formatted_times = array();
            foreach ($available_times as $time) {
                $formatted_times[] = array(
                    'value' => $time,
                    'display' => date('g:i A', strtotime($time))
                );
            }
            
            wp_send_json_success(array(
                'times' => $formatted_times,
                'message' => ''
            ));
        }
    }
    
    public static function submit_reservation() {
        check_ajax_referer('trs_nonce', 'nonce');
        
        // Validate input data
        $party_size = intval($_POST['party_size']);
        $reservation_date = sanitize_text_field($_POST['reservation_date']);
        $reservation_time = sanitize_text_field($_POST['reservation_time']);
        $customer_name = sanitize_text_field($_POST['customer_name']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $customer_phone = sanitize_text_field($_POST['customer_phone']);
        $special_request = sanitize_textarea_field($_POST['special_request']);
        
        // Basic validation
        if (empty($party_size) || empty($reservation_date) || empty($reservation_time) || 
            empty($customer_name) || empty($customer_email) || empty($customer_phone)) {
            wp_send_json_error('All required fields must be filled');
        }
        
        if ($party_size < 1 || $party_size > 15) {
            wp_send_json_error('Invalid party size');
        }
        
        if (!is_email($customer_email)) {
            wp_send_json_error('Invalid email address');
        }
        
        // Check if bookings are accepting
        $booking_status = TRS_Database::get_setting('accept_bookings', '1');
        if ($booking_status != '1') {
            wp_send_json_error('Reservations are currently closed');
        }
        
        // Validate date and time
        $today = date('Y-m-d');
        $max_advance_days = TRS_Database::get_setting('max_advance_days', 30);
        $max_date = date('Y-m-d', strtotime("+{$max_advance_days} days"));
        
        if ($reservation_date < $today || $reservation_date > $max_date) {
            wp_send_json_error('Invalid reservation date');
        }
        
        // Check if the selected time is still available
        $available_table = TRS_Database::find_available_table($party_size, $reservation_date, $reservation_time);
        
        if (!$available_table) {
            wp_send_json_error('The selected time slot is no longer available. Please choose a different time.');
        }
        
        // Create reservation data
        $reservation_data = array(
            'table_id' => $available_table->id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'party_size' => $party_size,
            'reservation_date' => $reservation_date,
            'reservation_time' => $reservation_time,
            'special_request' => $special_request,
            'status' => 'confirmed'
        );
        
        // Insert reservation
        $result = TRS_Database::create_reservation($reservation_data);
        
        if ($result) {
            global $wpdb;
            $reservation_id = $wpdb->insert_id;
            
            // Send email notifications
            $email_notifications = TRS_Database::get_setting('email_notifications', '1');
            if ($email_notifications == '1') {
                TRS_Email::send_customer_confirmation($reservation_id);
                TRS_Email::send_admin_notification($reservation_id);
            }
            
            // Prepare response data
            $response_data = array(
                'reservation_id' => $reservation_id,
                'customer_name' => $customer_name,
                'reservation_date' => TRS_Frontend::format_date_for_display($reservation_date),
                'reservation_time' => TRS_Frontend::format_time_for_display($reservation_time),
                'party_size' => $party_size,
                'table_number' => $available_table->table_number
            );
            
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error('Failed to create reservation. Please try again.');
        }
    }
    
    public static function update_reservation_status() {
        check_ajax_referer('trs_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $reservation_id = intval($_POST['reservation_id']);
        $status = sanitize_text_field($_POST['status']);
        
        if ($reservation_id < 1) {
            wp_send_json_error('Invalid reservation ID');
        }
        
        $valid_statuses = array('confirmed', 'seated', 'completed', 'cancelled', 'no_show');
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error('Invalid status');
        }
        
        $result = TRS_Database::update_reservation_status($reservation_id, $status);
        
        if ($result !== false) {
            // Send email notification for specific status changes
            if ($status == 'cancelled') {
                TRS_Email::send_cancellation_email($reservation_id);
            }
            
            wp_send_json_success('Reservation status updated successfully');
        } else {
            wp_send_json_error('Failed to update reservation status');
        }
    }
    
    public static function get_reservation_details() {
        check_ajax_referer('trs_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $reservation_id = intval($_POST['reservation_id']);
        
        if ($reservation_id < 1) {
            wp_send_json_error('Invalid reservation ID');
        }
        
        $reservation = TRS_Database::get_reservation($reservation_id);
        
        if ($reservation) {
            wp_send_json_success($reservation);
        } else {
            wp_send_json_error('Reservation not found');
        }
    }
    
    public static function check_availability() {
        check_ajax_referer('trs_nonce', 'nonce');
        
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $party_size = intval($_POST['party_size']);
        
        if (empty($date) || empty($time) || $party_size < 1) {
            wp_send_json_error('Invalid parameters');
        }
        
        $available_table = TRS_Database::find_available_table($party_size, $date, $time);
        
        if ($available_table) {
            wp_send_json_success(array(
                'available' => true,
                'table' => $available_table
            ));
        } else {
            wp_send_json_success(array(
                'available' => false,
                'message' => 'This time slot is no longer available'
            ));
        }
    }
}