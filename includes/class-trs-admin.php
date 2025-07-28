<?php

if (!defined('ABSPATH')) {
    exit;
}

class TRS_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_trs_add_table', array($this, 'add_table'));
        add_action('wp_ajax_trs_update_table', array($this, 'update_table'));
        add_action('wp_ajax_trs_delete_table', array($this, 'delete_table'));
        add_action('wp_ajax_trs_toggle_bookings', array($this, 'toggle_bookings'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Table Reservations',
            'Reservations',
            'manage_options',
            'trs-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'trs-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'trs-dashboard',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'trs-dashboard',
            'Reservations',
            'Reservations',
            'manage_options',
            'trs-reservations',
            array($this, 'reservations_page')
        );
        
        add_submenu_page(
            'trs-dashboard',
            'Tables',
            'Tables',
            'manage_options',
            'trs-tables',
            array($this, 'tables_page')
        );
        
        add_submenu_page(
            'trs-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'trs-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_init() {
        register_setting('trs_settings', 'trs_settings');
    }
    
    public function dashboard_page() {
        $stats = TRS_Database::get_dashboard_stats();
        $today_reservations = TRS_Database::get_reservations(array(
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d')
        ));
        
        $booking_status = TRS_Database::get_setting('accept_bookings', '1');
        
        ?>
        <div class="wrap">
            <h1>Reservation Dashboard</h1>
            
            <div class="trs-dashboard-stats">
                <div class="trs-stat-box">
                    <h3><?php echo $stats['today_reservations']; ?></h3>
                    <p>Today's Reservations</p>
                </div>
                <div class="trs-stat-box">
                    <h3><?php echo $stats['week_reservations']; ?></h3>
                    <p>This Week</p>
                </div>
                <div class="trs-stat-box">
                    <h3><?php echo $stats['month_reservations']; ?></h3>
                    <p>This Month</p>
                </div>
                <div class="trs-stat-box">
                    <h3><?php echo $stats['pending_confirmations']; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            
            <div class="trs-booking-control">
                <h2>Booking Control</h2>
                <button id="toggle-bookings" class="button button-<?php echo $booking_status == '1' ? 'secondary' : 'primary'; ?>">
                    <?php echo $booking_status == '1' ? 'Close Bookings' : 'Open Bookings'; ?>
                </button>
                <p class="description">
                    Status: <strong><?php echo $booking_status == '1' ? 'Accepting Bookings' : 'Bookings Closed'; ?></strong>
                </p>
            </div>
            
            <div class="trs-today-reservations">
                <h2>Today's Reservations</h2>
                <?php if (empty($today_reservations)): ?>
                    <p>No reservations for today.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Customer</th>
                                <th>Table</th>
                                <th>Party Size</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo date('h:i A', strtotime($reservation->reservation_time)); ?></td>
                                    <td>
                                        <strong><?php echo esc_html($reservation->customer_name); ?></strong><br>
                                        <small><?php echo esc_html($reservation->customer_email); ?></small>
                                    </td>
                                    <td><?php echo esc_html($reservation->table_number); ?></td>
                                    <td><?php echo $reservation->party_size; ?></td>
                                    <td>
                                        <span class="trs-status trs-status-<?php echo $reservation->status; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $reservation->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <select class="status-select" data-reservation-id="<?php echo $reservation->id; ?>">
                                            <option value="confirmed" <?php selected($reservation->status, 'confirmed'); ?>>Confirmed</option>
                                            <option value="seated" <?php selected($reservation->status, 'seated'); ?>>Seated</option>
                                            <option value="completed" <?php selected($reservation->status, 'completed'); ?>>Completed</option>
                                            <option value="no_show" <?php selected($reservation->status, 'no_show'); ?>>No Show</option>
                                            <option value="cancelled" <?php selected($reservation->status, 'cancelled'); ?>>Cancelled</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function reservations_page() {
        $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d');
        $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d', strtotime('+7 days'));
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $filters = array(
            'date_from' => $date_from,
            'date_to' => $date_to
        );
        
        if (!empty($status_filter)) {
            $filters['status'] = $status_filter;
        }
        
        $reservations = TRS_Database::get_reservations($filters);
        
        ?>
        <div class="wrap">
            <h1>Reservations</h1>
            
            <div class="trs-filters">
                <form method="get">
                    <input type="hidden" name="page" value="trs-reservations">
                    
                    <label for="date_from">From:</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                    
                    <label for="date_to">To:</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                    
                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>>Confirmed</option>
                        <option value="seated" <?php selected($status_filter, 'seated'); ?>>Seated</option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                        <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>Cancelled</option>
                        <option value="no_show" <?php selected($status_filter, 'no_show'); ?>>No Show</option>
                    </select>
                    
                    <input type="submit" class="button" value="Filter">
                </form>
            </div>
            
            <?php if (empty($reservations)): ?>
                <p>No reservations found for the selected criteria.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Table</th>
                            <th>Party Size</th>
                            <th>Status</th>
                            <th>Special Request</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td><?php echo $reservation->id; ?></td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($reservation->reservation_date)); ?><br>
                                    <small><?php echo date('h:i A', strtotime($reservation->reservation_time)); ?></small>
                                </td>
                                <td><?php echo esc_html($reservation->customer_name); ?></td>
                                <td>
                                    <?php echo esc_html($reservation->customer_email); ?><br>
                                    <small><?php echo esc_html($reservation->customer_phone); ?></small>
                                </td>
                                <td><?php echo esc_html($reservation->table_number); ?></td>
                                <td><?php echo $reservation->party_size; ?></td>
                                <td>
                                    <span class="trs-status trs-status-<?php echo $reservation->status; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $reservation->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($reservation->special_request); ?></td>
                                <td><?php echo date('M j, Y h:i A', strtotime($reservation->created_at)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function tables_page() {
        $tables = TRS_Database::get_tables(false);
        
        ?>
        <div class="wrap">
            <h1>Table Management</h1>
            
            <div class="trs-add-table">
                <h2>Add New Table</h2>
                <form id="add-table-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="table_number">Table Number</label></th>
                            <td><input type="text" id="table_number" name="table_number" required></td>
                        </tr>
                        <tr>
                            <th><label for="capacity">Capacity</label></th>
                            <td>
                                <select id="capacity" name="capacity" required>
                                    <?php for ($i = 1; $i <= 15; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> person<?php echo $i > 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Add Table">
                    </p>
                </form>
            </div>
            
            <div class="trs-existing-tables">
                <h2>Existing Tables</h2>
                <?php if (empty($tables)): ?>
                    <p>No tables found.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Table Number</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <td><?php echo esc_html($table->table_number); ?></td>
                                    <td><?php echo $table->capacity; ?> person<?php echo $table->capacity > 1 ? 's' : ''; ?></td>
                                    <td>
                                        <span class="trs-table-status trs-table-<?php echo $table->is_active ? 'active' : 'inactive'; ?>">
                                            <?php echo $table->is_active ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="button button-small edit-table" 
                                                data-id="<?php echo $table->id; ?>" 
                                                data-number="<?php echo esc_attr($table->table_number); ?>" 
                                                data-capacity="<?php echo $table->capacity; ?>" 
                                                data-active="<?php echo $table->is_active; ?>">
                                            Edit
                                        </button>
                                        <button class="button button-small button-link-delete delete-table" 
                                                data-id="<?php echo $table->id; ?>">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Edit Table Modal -->
        <div id="edit-table-modal" style="display: none;">
            <div class="trs-modal-content">
                <h3>Edit Table</h3>
                <form id="edit-table-form">
                    <input type="hidden" id="edit_table_id" name="table_id">
                    <table class="form-table">
                        <tr>
                            <th><label for="edit_table_number">Table Number</label></th>
                            <td><input type="text" id="edit_table_number" name="table_number" required></td>
                        </tr>
                        <tr>
                            <th><label for="edit_capacity">Capacity</label></th>
                            <td>
                                <select id="edit_capacity" name="capacity" required>
                                    <?php for ($i = 1; $i <= 15; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> person<?php echo $i > 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_is_active">Status</label></th>
                            <td>
                                <select id="edit_is_active" name="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Update Table">
                        <input type="button" class="button" value="Cancel" onclick="closeEditModal()">
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $settings = array(
                'restaurant_name' => sanitize_text_field($_POST['restaurant_name']),
                'restaurant_email' => sanitize_email($_POST['restaurant_email']),
                'booking_hours_start' => sanitize_text_field($_POST['booking_hours_start']),
                'booking_hours_end' => sanitize_text_field($_POST['booking_hours_end']),
                'booking_interval' => intval($_POST['booking_interval']),
                'max_advance_days' => intval($_POST['max_advance_days']),
                'reservation_duration' => intval($_POST['reservation_duration']),
                'auto_release_time' => intval($_POST['auto_release_time']),
                'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0'
            );
            
            foreach ($settings as $key => $value) {
                TRS_Database::update_setting($key, $value);
            }
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $settings = array(
            'restaurant_name' => TRS_Database::get_setting('restaurant_name', 'My Restaurant'),
            'restaurant_email' => TRS_Database::get_setting('restaurant_email', get_option('admin_email')),
            'booking_hours_start' => TRS_Database::get_setting('booking_hours_start', '10:00'),
            'booking_hours_end' => TRS_Database::get_setting('booking_hours_end', '22:00'),
            'booking_interval' => TRS_Database::get_setting('booking_interval', '30'),
            'max_advance_days' => TRS_Database::get_setting('max_advance_days', '30'),
            'reservation_duration' => TRS_Database::get_setting('reservation_duration', '120'),
            'auto_release_time' => TRS_Database::get_setting('auto_release_time', '60'),
            'email_notifications' => TRS_Database::get_setting('email_notifications', '1')
        );
        
        ?>
        <div class="wrap">
            <h1>Reservation Settings</h1>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="restaurant_name">Restaurant Name</label></th>
                        <td><input type="text" id="restaurant_name" name="restaurant_name" value="<?php echo esc_attr($settings['restaurant_name']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="restaurant_email">Restaurant Email</label></th>
                        <td><input type="email" id="restaurant_email" name="restaurant_email" value="<?php echo esc_attr($settings['restaurant_email']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="booking_hours_start">Booking Hours Start</label></th>
                        <td><input type="time" id="booking_hours_start" name="booking_hours_start" value="<?php echo esc_attr($settings['booking_hours_start']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="booking_hours_end">Booking Hours End</label></th>
                        <td><input type="time" id="booking_hours_end" name="booking_hours_end" value="<?php echo esc_attr($settings['booking_hours_end']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="booking_interval">Booking Interval (minutes)</label></th>
                        <td>
                            <select id="booking_interval" name="booking_interval">
                                <option value="15" <?php selected($settings['booking_interval'], '15'); ?>>15 minutes</option>
                                <option value="30" <?php selected($settings['booking_interval'], '30'); ?>>30 minutes</option>
                                <option value="60" <?php selected($settings['booking_interval'], '60'); ?>>60 minutes</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_advance_days">Maximum Advance Booking (days)</label></th>
                        <td><input type="number" id="max_advance_days" name="max_advance_days" value="<?php echo esc_attr($settings['max_advance_days']); ?>" min="1" max="365"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reservation_duration">Reservation Duration (minutes)</label></th>
                        <td><input type="number" id="reservation_duration" name="reservation_duration" value="<?php echo esc_attr($settings['reservation_duration']); ?>" min="30" max="480"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="auto_release_time">Auto Release Time (minutes)</label></th>
                        <td>
                            <input type="number" id="auto_release_time" name="auto_release_time" value="<?php echo esc_attr($settings['auto_release_time']); ?>" min="15" max="180">
                            <p class="description">Tables are automatically released if not marked as "Seated" within this time.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Email Notifications</th>
                        <td>
                            <label for="email_notifications">
                                <input type="checkbox" id="email_notifications" name="email_notifications" value="1" <?php checked($settings['email_notifications'], '1'); ?>>
                                Send email notifications for new reservations
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    public function add_table() {
        check_ajax_referer('trs_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $table_number = sanitize_text_field($_POST['table_number']);
        $capacity = intval($_POST['capacity']);
        
        if (empty($table_number) || $capacity < 1 || $capacity > 15) {
            wp_send_json_error('Invalid table data');
        }
        
        $result = TRS_Database::add_table($table_number, $capacity);
        
        if ($result) {
            wp_send_json_success('Table added successfully');
        } else {
            wp_send_json_error('Failed to add table');
        }
    }
    
    public function update_table() {
        check_ajax_referer('trs_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $table_id = intval($_POST['table_id']);
        $table_number = sanitize_text_field($_POST['table_number']);
        $capacity = intval($_POST['capacity']);
        $is_active = intval($_POST['is_active']);
        
        if ($table_id < 1 || empty($table_number) || $capacity < 1 || $capacity > 15) {
            wp_send_json_error('Invalid table data');
        }
        
        $result = TRS_Database::update_table($table_id, $table_number, $capacity, $is_active);
        
        if ($result !== false) {
            wp_send_json_success('Table updated successfully');
        } else {
            wp_send_json_error('Failed to update table');
        }
    }
    
    public function delete_table() {
        check_ajax_referer('trs_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $table_id = intval($_POST['table_id']);
        
        if ($table_id < 1) {
            wp_send_json_error('Invalid table ID');
        }
        
        $result = TRS_Database::delete_table($table_id);
        
        if ($result) {
            wp_send_json_success('Table deleted successfully');
        } else {
            wp_send_json_error('Failed to delete table');
        }
    }
    
    public function toggle_bookings() {
        check_ajax_referer('trs_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $current_status = TRS_Database::get_setting('accept_bookings', '1');
        $new_status = $current_status == '1' ? '0' : '1';
        
        TRS_Database::update_setting('accept_bookings', $new_status);
        
        wp_send_json_success(array(
            'status' => $new_status,
            'message' => $new_status == '1' ? 'Bookings are now open' : 'Bookings are now closed'
        ));
    }
}