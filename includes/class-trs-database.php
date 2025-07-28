<?php

if (!defined('ABSPATH')) {
    exit;
}

class TRS_Database {
    
    public static function get_tables($active_only = true) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_tables';
        $where = $active_only ? 'WHERE is_active = 1' : '';
        
        return $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY capacity ASC, table_number ASC");
    }
    
    public static function get_table($table_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_tables';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $table_id));
    }
    
    public static function add_table($table_number, $capacity) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_tables';
        
        return $wpdb->insert(
            $table_name,
            array(
                'table_number' => $table_number,
                'capacity' => $capacity,
                'is_active' => 1
            ),
            array('%s', '%d', '%d')
        );
    }
    
    public static function update_table($table_id, $table_number, $capacity, $is_active = 1) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_tables';
        
        return $wpdb->update(
            $table_name,
            array(
                'table_number' => $table_number,
                'capacity' => $capacity,
                'is_active' => $is_active
            ),
            array('id' => $table_id),
            array('%s', '%d', '%d'),
            array('%d')
        );
    }
    
    public static function delete_table($table_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_tables';
        return $wpdb->delete($table_name, array('id' => $table_id), array('%d'));
    }
    
    public static function get_reservations($filters = array()) {
        global $wpdb;
        
        $reservations_table = $wpdb->prefix . 'trs_reservations';
        $tables_table = $wpdb->prefix . 'trs_tables';
        
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'r.reservation_date >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'r.reservation_date <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = 'r.status = %s';
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['table_id'])) {
            $where_conditions[] = 'r.table_id = %d';
            $where_values[] = $filters['table_id'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT r.*, t.table_number, t.capacity 
                FROM $reservations_table r 
                LEFT JOIN $tables_table t ON r.table_id = t.id 
                $where_clause 
                ORDER BY r.reservation_date DESC, r.reservation_time DESC";
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($sql, $where_values));
        } else {
            return $wpdb->get_results($sql);
        }
    }
    
    public static function get_reservation($reservation_id) {
        global $wpdb;
        
        $reservations_table = $wpdb->prefix . 'trs_reservations';
        $tables_table = $wpdb->prefix . 'trs_tables';
        
        $sql = "SELECT r.*, t.table_number, t.capacity 
                FROM $reservations_table r 
                LEFT JOIN $tables_table t ON r.table_id = t.id 
                WHERE r.id = %d";
        
        return $wpdb->get_row($wpdb->prepare($sql, $reservation_id));
    }
    
    public static function create_reservation($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_reservations';
        
        return $wpdb->insert(
            $table_name,
            array(
                'table_id' => $data['table_id'],
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'],
                'party_size' => $data['party_size'],
                'reservation_date' => $data['reservation_date'],
                'reservation_time' => $data['reservation_time'],
                'special_request' => $data['special_request'],
                'status' => isset($data['status']) ? $data['status'] : 'confirmed'
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    public static function update_reservation_status($reservation_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_reservations';
        
        return $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $reservation_id),
            array('%s'),
            array('%d')
        );
    }
    
    public static function find_available_table($party_size, $date, $time) {
        global $wpdb;
        
        $tables_table = $wpdb->prefix . 'trs_tables';
        $reservations_table = $wpdb->prefix . 'trs_reservations';
        
        // Find tables that can accommodate the party size
        $suitable_tables = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tables_table 
             WHERE capacity >= %d AND is_active = 1 
             ORDER BY capacity ASC",
            $party_size
        ));
        
        if (empty($suitable_tables)) {
            return false;
        }
        
        // Check each table for availability
        foreach ($suitable_tables as $table) {
            if (self::is_table_available($table->id, $date, $time)) {
                return $table;
            }
        }
        
        return false;
    }
    
    public static function is_table_available($table_id, $date, $time) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_reservations';
        $reservation_duration = self::get_setting('reservation_duration', 120); // minutes
        
        // Convert time to datetime for easier calculation
        $requested_datetime = strtotime($date . ' ' . $time);
        $requested_end = $requested_datetime + ($reservation_duration * 60);
        
        // Check for overlapping reservations
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE table_id = %d 
             AND reservation_date = %s 
             AND status NOT IN ('cancelled', 'no_show', 'completed')",
            $table_id, $date
        ));
        
        foreach ($existing as $reservation) {
            $existing_datetime = strtotime($reservation->reservation_date . ' ' . $reservation->reservation_time);
            $existing_end = $existing_datetime + ($reservation_duration * 60);
            
            // Check for overlap
            if (($requested_datetime >= $existing_datetime && $requested_datetime < $existing_end) ||
                ($requested_end > $existing_datetime && $requested_end <= $existing_end) ||
                ($requested_datetime <= $existing_datetime && $requested_end >= $existing_end)) {
                return false;
            }
        }
        
        return true;
    }
    
    public static function get_available_times($date, $party_size) {
        global $wpdb;
        
        $start_time = self::get_setting('booking_hours_start', '10:00');
        $end_time = self::get_setting('booking_hours_end', '22:00');
        $interval = self::get_setting('booking_interval', 30); // minutes
        
        $available_times = array();
        $current_time = strtotime($date . ' ' . $start_time);
        $end_timestamp = strtotime($date . ' ' . $end_time);
        
        while ($current_time <= $end_timestamp) {
            $time_slot = date('H:i', $current_time);
            
            // Check if any table is available for this time slot
            $available_table = self::find_available_table($party_size, $date, $time_slot);
            
            if ($available_table) {
                $available_times[] = $time_slot;
            }
            
            $current_time += ($interval * 60);
        }
        
        return $available_times;
    }
    
    public static function get_setting($key, $default = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_settings';
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table_name WHERE setting_key = %s",
            $key
        ));
        
        return $value !== null ? $value : $default;
    }
    
    public static function update_setting($key, $value) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_settings';
        
        return $wpdb->replace(
            $table_name,
            array(
                'setting_key' => $key,
                'setting_value' => $value
            ),
            array('%s', '%s')
        );
    }
    
    public static function get_expired_reservations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_reservations';
        $auto_release_time = self::get_setting('auto_release_time', 60); // minutes
        
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$auto_release_time} minutes"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE status = 'confirmed' 
             AND CONCAT(reservation_date, ' ', reservation_time) < %s",
            $cutoff_time
        ));
    }
    
    public static function get_dashboard_stats() {
        global $wpdb;
        
        $reservations_table = $wpdb->prefix . 'trs_reservations';
        $today = date('Y-m-d');
        
        $stats = array();
        
        // Today's reservations
        $stats['today_reservations'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $reservations_table WHERE reservation_date = %s AND status NOT IN ('cancelled', 'no_show')",
            $today
        ));
        
        // This week's reservations
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        $stats['week_reservations'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $reservations_table WHERE reservation_date BETWEEN %s AND %s AND status NOT IN ('cancelled', 'no_show')",
            $week_start, $week_end
        ));
        
        // This month's reservations
        $month_start = date('Y-m-01');
        $month_end = date('Y-m-t');
        $stats['month_reservations'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $reservations_table WHERE reservation_date BETWEEN %s AND %s AND status NOT IN ('cancelled', 'no_show')",
            $month_start, $month_end
        ));
        
        // Pending confirmations
        $stats['pending_confirmations'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $reservations_table WHERE status = 'pending'"
        );
        
        return $stats;
    }
}