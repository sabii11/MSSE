<?php

if (!defined('ABSPATH')) {
    exit;
}

class TRS_API {
    
    private $namespace = 'trs/v1';
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        // Authentication endpoint
        register_rest_route($this->namespace, '/auth', array(
            'methods' => 'POST',
            'callback' => array($this, 'authenticate'),
            'permission_callback' => '__return_true'
        ));
        
        // Dashboard stats
        register_rest_route($this->namespace, '/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_dashboard'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Get reservations
        register_rest_route($this->namespace, '/reservations', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_reservations'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Get single reservation
        register_rest_route($this->namespace, '/reservations/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_reservation'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Update reservation status
        register_rest_route($this->namespace, '/reservations/(?P<id>\d+)/status', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_reservation_status'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Get tables
        register_rest_route($this->namespace, '/tables', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_tables'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Toggle booking status
        register_rest_route($this->namespace, '/settings/bookings/toggle', array(
            'methods' => 'POST',
            'callback' => array($this, 'toggle_bookings'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Get settings
        register_rest_route($this->namespace, '/settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_settings'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
    }
    
    public function authenticate($request) {
        $username = sanitize_text_field($request->get_param('username'));
        $password = sanitize_text_field($request->get_param('password'));
        
        if (empty($username) || empty($password)) {
            return new WP_Error('missing_credentials', 'Username and password are required', array('status' => 400));
        }
        
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            return new WP_Error('invalid_credentials', 'Invalid username or password', array('status' => 401));
        }
        
        if (!user_can($user, 'manage_options')) {
            return new WP_Error('insufficient_permissions', 'User does not have admin permissions', array('status' => 403));
        }
        
        // Generate API token (simple JWT-like token)
        $token = $this->generate_token($user->ID);
        
        return rest_ensure_response(array(
            'success' => true,
            'token' => $token,
            'user' => array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name
            ),
            'expires_in' => 24 * 60 * 60 // 24 hours
        ));
    }
    
    public function get_dashboard($request) {
        $stats = TRS_Database::get_dashboard_stats();
        
        // Get today's reservations
        $today_reservations = TRS_Database::get_reservations(array(
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d')
        ));
        
        // Get upcoming reservations (next 2 hours)
        $upcoming_cutoff = date('H:i', strtotime('+2 hours'));
        $upcoming_reservations = array_filter($today_reservations, function($reservation) use ($upcoming_cutoff) {
            return $reservation->reservation_time >= date('H:i') && 
                   $reservation->reservation_time <= $upcoming_cutoff &&
                   in_array($reservation->status, array('confirmed', 'seated'));
        });
        
        // Get booking status
        $booking_status = TRS_Database::get_setting('accept_bookings', '1');
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'stats' => $stats,
                'today_reservations' => array_values($today_reservations),
                'upcoming_reservations' => array_values($upcoming_reservations),
                'booking_status' => $booking_status == '1',
                'current_time' => current_time('mysql'),
                'restaurant_name' => TRS_Database::get_setting('restaurant_name', 'Restaurant')
            )
        ));
    }
    
    public function get_reservations($request) {
        $date_from = $request->get_param('date_from') ?: date('Y-m-d');
        $date_to = $request->get_param('date_to') ?: date('Y-m-d');
        $status = $request->get_param('status');
        $page = (int) ($request->get_param('page') ?: 1);
        $per_page = (int) ($request->get_param('per_page') ?: 20);
        
        $filters = array(
            'date_from' => $date_from,
            'date_to' => $date_to
        );
        
        if (!empty($status)) {
            $filters['status'] = $status;
        }
        
        $reservations = TRS_Database::get_reservations($filters);
        
        // Simple pagination
        $total = count($reservations);
        $offset = ($page - 1) * $per_page;
        $paged_reservations = array_slice($reservations, $offset, $per_page);
        
        // Format reservations for mobile
        $formatted_reservations = array_map(array($this, 'format_reservation_for_mobile'), $paged_reservations);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'reservations' => $formatted_reservations,
                'pagination' => array(
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => ceil($total / $per_page)
                )
            )
        ));
    }
    
    public function get_reservation($request) {
        $reservation_id = (int) $request->get_param('id');
        
        if (!$reservation_id) {
            return new WP_Error('invalid_id', 'Invalid reservation ID', array('status' => 400));
        }
        
        $reservation = TRS_Database::get_reservation($reservation_id);
        
        if (!$reservation) {
            return new WP_Error('not_found', 'Reservation not found', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $this->format_reservation_for_mobile($reservation)
        ));
    }
    
    public function update_reservation_status($request) {
        $reservation_id = (int) $request->get_param('id');
        $status = sanitize_text_field($request->get_param('status'));
        
        if (!$reservation_id) {
            return new WP_Error('invalid_id', 'Invalid reservation ID', array('status' => 400));
        }
        
        $valid_statuses = array('confirmed', 'seated', 'completed', 'cancelled', 'no_show');
        if (!in_array($status, $valid_statuses)) {
            return new WP_Error('invalid_status', 'Invalid status', array('status' => 400));
        }
        
        $result = TRS_Database::update_reservation_status($reservation_id, $status);
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update reservation status', array('status' => 500));
        }
        
        // Get updated reservation
        $reservation = TRS_Database::get_reservation($reservation_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $this->format_reservation_for_mobile($reservation)
        ));
    }
    
    public function get_tables($request) {
        $tables = TRS_Database::get_tables(false);
        
        $formatted_tables = array_map(function($table) {
            return array(
                'id' => (int) $table->id,
                'table_number' => $table->table_number,
                'capacity' => (int) $table->capacity,
                'is_active' => (bool) $table->is_active
            );
        }, $tables);
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $formatted_tables
        ));
    }
    
    public function toggle_bookings($request) {
        $current_status = TRS_Database::get_setting('accept_bookings', '1');
        $new_status = $current_status == '1' ? '0' : '1';
        
        TRS_Database::update_setting('accept_bookings', $new_status);
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => $new_status == '1' ? 'Bookings are now open' : 'Bookings are now closed',
            'data' => array(
                'booking_status' => $new_status == '1'
            )
        ));
    }
    
    public function get_settings($request) {
        $settings = array(
            'restaurant_name' => TRS_Database::get_setting('restaurant_name', 'Restaurant'),
            'restaurant_email' => TRS_Database::get_setting('restaurant_email', get_option('admin_email')),
            'booking_hours_start' => TRS_Database::get_setting('booking_hours_start', '10:00'),
            'booking_hours_end' => TRS_Database::get_setting('booking_hours_end', '22:00'),
            'booking_interval' => (int) TRS_Database::get_setting('booking_interval', 30),
            'reservation_duration' => (int) TRS_Database::get_setting('reservation_duration', 120),
            'accept_bookings' => TRS_Database::get_setting('accept_bookings', '1') == '1'
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $settings
        ));
    }
    
    public function check_admin_permission($request) {
        $token = $request->get_header('Authorization');
        
        if (!$token) {
            return new WP_Error('missing_token', 'Authorization token required', array('status' => 401));
        }
        
        // Remove "Bearer " prefix if present
        $token = str_replace('Bearer ', '', $token);
        
        $user_id = $this->verify_token($token);
        
        if (!$user_id) {
            return new WP_Error('invalid_token', 'Invalid or expired token', array('status' => 401));
        }
        
        if (!user_can($user_id, 'manage_options')) {
            return new WP_Error('insufficient_permissions', 'Insufficient permissions', array('status' => 403));
        }
        
        // Set current user for the request
        wp_set_current_user($user_id);
        
        return true;
    }
    
    private function format_reservation_for_mobile($reservation) {
        return array(
            'id' => (int) $reservation->id,
            'table_number' => $reservation->table_number,
            'table_capacity' => (int) $reservation->capacity,
            'customer_name' => $reservation->customer_name,
            'customer_email' => $reservation->customer_email,
            'customer_phone' => $reservation->customer_phone,
            'party_size' => (int) $reservation->party_size,
            'reservation_date' => $reservation->reservation_date,
            'reservation_time' => $reservation->reservation_time,
            'formatted_date' => date('l, M j, Y', strtotime($reservation->reservation_date)),
            'formatted_time' => date('g:i A', strtotime($reservation->reservation_time)),
            'special_request' => $reservation->special_request,
            'status' => $reservation->status,
            'status_label' => ucfirst(str_replace('_', ' ', $reservation->status)),
            'created_at' => $reservation->created_at,
            'updated_at' => $reservation->updated_at,
            'is_today' => $reservation->reservation_date == date('Y-m-d'),
            'is_upcoming' => strtotime($reservation->reservation_date . ' ' . $reservation->reservation_time) > time(),
            'time_until' => $this->get_time_until_reservation($reservation->reservation_date, $reservation->reservation_time)
        );
    }
    
    private function get_time_until_reservation($date, $time) {
        $reservation_time = strtotime($date . ' ' . $time);
        $current_time = time();
        
        if ($reservation_time <= $current_time) {
            return null;
        }
        
        $diff = $reservation_time - $current_time;
        
        if ($diff < 3600) { // Less than 1 hour
            return round($diff / 60) . ' minutes';
        } elseif ($diff < 86400) { // Less than 1 day
            return round($diff / 3600) . ' hours';
        } else {
            return round($diff / 86400) . ' days';
        }
    }
    
    private function generate_token($user_id) {
        $header = json_encode(array('typ' => 'JWT', 'alg' => 'HS256'));
        $payload = json_encode(array(
            'user_id' => $user_id,
            'exp' => time() + (24 * 60 * 60), // 24 hours
            'iat' => time()
        ));
        
        $base64_header = base64_encode($header);
        $base64_payload = base64_encode($payload);
        
        $signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, AUTH_SALT, true);
        $base64_signature = base64_encode($signature);
        
        return $base64_header . '.' . $base64_payload . '.' . $base64_signature;
    }
    
    private function verify_token($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header, $payload, $signature) = $parts;
        
        // Verify signature
        $expected_signature = hash_hmac('sha256', $header . '.' . $payload, AUTH_SALT, true);
        
        if (!hash_equals(base64_decode($signature), $expected_signature)) {
            return false;
        }
        
        // Decode payload
        $payload_data = json_decode(base64_decode($payload), true);
        
        if (!$payload_data || !isset($payload_data['user_id']) || !isset($payload_data['exp'])) {
            return false;
        }
        
        // Check expiration
        if ($payload_data['exp'] < time()) {
            return false;
        }
        
        return $payload_data['user_id'];
    }
}