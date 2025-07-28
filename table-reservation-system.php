<?php
/**
 * Plugin Name: Table Reservation System
 * Plugin URI: https://example.com/table-reservation-system
 * Description: A comprehensive table reservation system with admin management, booking form, and automation features.
 * Version: 1.0.0
 * Author: Restaurant Manager
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TRS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TRS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TRS_VERSION', '1.0.0');

class TableReservationSystem {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load plugin components
        $this->load_dependencies();
        $this->init_hooks();
        
        // Initialize database tables
        $this->create_tables();
        
        // Initialize admin
        if (is_admin()) {
            new TRS_Admin();
        }
        
        // Initialize frontend
        new TRS_Frontend();
        
        // Initialize email system
        new TRS_Email();
        
        // Initialize automation
        new TRS_Automation();
        
        // Initialize API
        new TRS_API();
    }
    
    private function load_dependencies() {
        require_once TRS_PLUGIN_PATH . 'includes/class-trs-database.php';
        require_once TRS_PLUGIN_PATH . 'includes/class-trs-admin.php';
        require_once TRS_PLUGIN_PATH . 'includes/class-trs-frontend.php';
        require_once TRS_PLUGIN_PATH . 'includes/class-trs-email.php';
        require_once TRS_PLUGIN_PATH . 'includes/class-trs-automation.php';
        require_once TRS_PLUGIN_PATH . 'includes/class-trs-ajax.php';
        require_once TRS_PLUGIN_PATH . 'includes/class-trs-api.php';
    }
    
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_trs_submit_reservation', array('TRS_Ajax', 'submit_reservation'));
        add_action('wp_ajax_nopriv_trs_submit_reservation', array('TRS_Ajax', 'submit_reservation'));
        add_action('wp_ajax_trs_get_available_times', array('TRS_Ajax', 'get_available_times'));
        add_action('wp_ajax_nopriv_trs_get_available_times', array('TRS_Ajax', 'get_available_times'));
        add_action('wp_ajax_trs_update_reservation_status', array('TRS_Ajax', 'update_reservation_status'));
        
        // Shortcode for booking form
        add_shortcode('table_reservation_form', array($this, 'booking_form_shortcode'));
        
        // Schedule automated tasks
        add_action('trs_hourly_cleanup', array('TRS_Automation', 'release_expired_reservations'));
        if (!wp_next_scheduled('trs_hourly_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'trs_hourly_cleanup');
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('trs-frontend', TRS_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), TRS_VERSION, true);
        wp_enqueue_style('trs-frontend', TRS_PLUGIN_URL . 'assets/css/frontend.css', array(), TRS_VERSION);
        
        wp_localize_script('trs-frontend', 'trs_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('trs_nonce')
        ));
    }
    
    public function admin_enqueue_scripts() {
        wp_enqueue_script('trs-admin', TRS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), TRS_VERSION, true);
        wp_enqueue_style('trs-admin', TRS_PLUGIN_URL . 'assets/css/admin.css', array(), TRS_VERSION);
        
        wp_localize_script('trs-admin', 'trs_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('trs_admin_nonce')
        ));
    }
    
    public function booking_form_shortcode($atts) {
        $frontend = new TRS_Frontend();
        return $frontend->render_booking_form();
    }
    
    public function activate() {
        $this->create_tables();
        
        // Create default tables
        $this->create_default_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('trs_hourly_cleanup');
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tables table
        $table_tables = $wpdb->prefix . 'trs_tables';
        $sql_tables = "CREATE TABLE $table_tables (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            table_number varchar(10) NOT NULL,
            capacity tinyint(2) NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY table_number (table_number)
        ) $charset_collate;";
        
        // Reservations table
        $table_reservations = $wpdb->prefix . 'trs_reservations';
        $sql_reservations = "CREATE TABLE $table_reservations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            table_id mediumint(9) NOT NULL,
            customer_name varchar(100) NOT NULL,
            customer_email varchar(100) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            party_size tinyint(2) NOT NULL,
            reservation_date date NOT NULL,
            reservation_time time NOT NULL,
            special_request text,
            status enum('pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show') DEFAULT 'confirmed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY table_id (table_id),
            KEY reservation_datetime (reservation_date, reservation_time),
            KEY status (status)
        ) $charset_collate;";
        
        // Settings table
        $table_settings = $wpdb->prefix . 'trs_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(50) NOT NULL,
            setting_value text,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_tables);
        dbDelta($sql_reservations);
        dbDelta($sql_settings);
        
        // Insert default settings
        $this->insert_default_settings();
    }
    
    private function create_default_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_tables';
        
        // Check if tables already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($existing == 0) {
            // Create sample tables with different capacities
            $default_tables = array(
                array('table_number' => 'T01', 'capacity' => 2),
                array('table_number' => 'T02', 'capacity' => 2),
                array('table_number' => 'T03', 'capacity' => 4),
                array('table_number' => 'T04', 'capacity' => 4),
                array('table_number' => 'T05', 'capacity' => 6),
                array('table_number' => 'T06', 'capacity' => 6),
                array('table_number' => 'T07', 'capacity' => 8),
                array('table_number' => 'T08', 'capacity' => 10),
                array('table_number' => 'T09', 'capacity' => 12),
                array('table_number' => 'T10', 'capacity' => 15),
            );
            
            foreach ($default_tables as $table) {
                $wpdb->insert($table_name, $table);
            }
        }
    }
    
    private function insert_default_settings() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_settings';
        
        $default_settings = array(
            array('setting_key' => 'restaurant_name', 'setting_value' => 'My Restaurant'),
            array('setting_key' => 'restaurant_email', 'setting_value' => get_option('admin_email')),
            array('setting_key' => 'booking_hours_start', 'setting_value' => '10:00'),
            array('setting_key' => 'booking_hours_end', 'setting_value' => '22:00'),
            array('setting_key' => 'booking_interval', 'setting_value' => '30'),
            array('setting_key' => 'max_advance_days', 'setting_value' => '30'),
            array('setting_key' => 'reservation_duration', 'setting_value' => '120'),
            array('setting_key' => 'auto_release_time', 'setting_value' => '60'),
            array('setting_key' => 'accept_bookings', 'setting_value' => '1'),
            array('setting_key' => 'email_notifications', 'setting_value' => '1'),
        );
        
        foreach ($default_settings as $setting) {
            $wpdb->replace($table_name, $setting);
        }
    }
}

// Initialize the plugin
new TableReservationSystem();