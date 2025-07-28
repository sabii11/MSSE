<?php

if (!defined('ABSPATH')) {
    exit;
}

class TRS_Automation {
    
    public function __construct() {
        // Hook into WordPress cron system
        add_action('trs_hourly_cleanup', array($this, 'release_expired_reservations'));
        add_action('trs_daily_reminders', array($this, 'send_daily_reminders'));
        
        // Schedule daily reminders if not already scheduled
        if (!wp_next_scheduled('trs_daily_reminders')) {
            wp_schedule_event(time(), 'daily', 'trs_daily_reminders');
        }
    }
    
    /**
     * Release expired reservations that haven't been marked as seated
     */
    public static function release_expired_reservations() {
        $expired_reservations = TRS_Database::get_expired_reservations();
        
        $count = 0;
        foreach ($expired_reservations as $reservation) {
            // Update status to no_show
            $result = TRS_Database::update_reservation_status($reservation->id, 'no_show');
            
            if ($result) {
                $count++;
                
                // Log the automatic release
                error_log("TRS: Automatically released expired reservation #{$reservation->id} for {$reservation->customer_name}");
                
                // Optionally send notification email to customer
                $auto_email = TRS_Database::get_setting('send_auto_release_email', '0');
                if ($auto_email == '1') {
                    self::send_auto_release_notification($reservation->id);
                }
            }
        }
        
        if ($count > 0) {
            error_log("TRS: Released {$count} expired reservations");
        }
        
        return $count;
    }
    
    /**
     * Send daily reminder emails for upcoming reservations
     */
    public static function send_daily_reminders() {
        // Get reservations for tomorrow
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $upcoming_reservations = TRS_Database::get_reservations(array(
            'date_from' => $tomorrow,
            'date_to' => $tomorrow,
            'status' => 'confirmed'
        ));
        
        $reminder_enabled = TRS_Database::get_setting('send_reminder_emails', '0');
        
        if ($reminder_enabled != '1' || empty($upcoming_reservations)) {
            return 0;
        }
        
        $count = 0;
        foreach ($upcoming_reservations as $reservation) {
            $result = TRS_Email::send_reminder_email($reservation->id);
            
            if ($result) {
                $count++;
                error_log("TRS: Sent reminder email for reservation #{$reservation->id}");
            }
        }
        
        if ($count > 0) {
            error_log("TRS: Sent {$count} reminder emails for tomorrow's reservations");
        }
        
        return $count;
    }
    
    /**
     * Clean up old reservation data
     */
    public static function cleanup_old_reservations() {
        global $wpdb;
        
        $cleanup_days = TRS_Database::get_setting('cleanup_old_reservations_days', '90');
        
        if ($cleanup_days == '0') {
            return 0; // Cleanup disabled
        }
        
        $cutoff_date = date('Y-m-d', strtotime("-{$cleanup_days} days"));
        
        $table_name = $wpdb->prefix . 'trs_reservations';
        
        $result = $wpdb->delete(
            $table_name,
            array(
                'reservation_date' => $cutoff_date,
                'status' => array('completed', 'no_show', 'cancelled')
            ),
            array('%s', '%s')
        );
        
        if ($result) {
            error_log("TRS: Cleaned up {$result} old reservations older than {$cleanup_days} days");
        }
        
        return $result;
    }
    
    /**
     * Update reservation statuses based on current time
     */
    public static function update_overdue_reservations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'trs_reservations';
        $grace_period = TRS_Database::get_setting('late_arrival_grace_period', '15'); // minutes
        
        $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$grace_period} minutes"));
        
        // Find confirmed reservations that are overdue
        $overdue_reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE status = 'confirmed' 
             AND CONCAT(reservation_date, ' ', reservation_time) < %s",
            $cutoff_time
        ));
        
        $count = 0;
        foreach ($overdue_reservations as $reservation) {
            // Mark as no_show
            $result = TRS_Database::update_reservation_status($reservation->id, 'no_show');
            
            if ($result) {
                $count++;
                error_log("TRS: Marked overdue reservation #{$reservation->id} as no-show");
            }
        }
        
        return $count;
    }
    
    /**
     * Send automated email notification for released reservations
     */
    private static function send_auto_release_notification($reservation_id) {
        $reservation = TRS_Database::get_reservation($reservation_id);
        
        if (!$reservation) {
            return false;
        }
        
        $restaurant_name = TRS_Database::get_setting('restaurant_name', 'Restaurant');
        $restaurant_email = TRS_Database::get_setting('restaurant_email', get_option('admin_email'));
        
        $subject = sprintf('Reservation Auto-Released - %s', $restaurant_name);
        
        $message = self::get_auto_release_template($reservation, $restaurant_name);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>'
        );
        
        return wp_mail($reservation->customer_email, $subject, $message, $headers);
    }
    
    /**
     * Generate auto-release email template
     */
    private static function get_auto_release_template($reservation, $restaurant_name) {
        $reservation_date = TRS_Frontend::format_date_for_display($reservation->reservation_date);
        $reservation_time = TRS_Frontend::format_time_for_display($reservation->reservation_time);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reservation Auto-Released</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffc107; color: #212529; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .reservation-details { background: #fff3cd; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($restaurant_name); ?></h1>
                    <p>Reservation Auto-Released</p>
                </div>
                
                <div class="content">
                    <h2>Your reservation has been automatically released</h2>
                    
                    <p>Dear <?php echo esc_html($reservation->customer_name); ?>,</p>
                    
                    <p>We're writing to inform you that your reservation has been automatically released due to non-arrival within our grace period.</p>
                    
                    <div class="reservation-details">
                        <h3>Released Reservation Details</h3>
                        <div class="detail-row">
                            <strong>Confirmation #:</strong>
                            <span><?php echo $reservation->id; ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Date:</strong>
                            <span><?php echo esc_html($reservation_date); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Time:</strong>
                            <span><?php echo esc_html($reservation_time); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Party Size:</strong>
                            <span><?php echo $reservation->party_size; ?> <?php echo $reservation->party_size == 1 ? 'person' : 'people'; ?></span>
                        </div>
                    </div>
                    
                    <p>If you were delayed or there was a misunderstanding, please contact us directly. We may be able to accommodate you depending on availability.</p>
                    
                    <p>We hope to have the opportunity to serve you in the future. Please feel free to make a new reservation at your convenience.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate daily reports for admin
     */
    public static function generate_daily_report() {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Get yesterday's stats
        $yesterday_stats = array(
            'total' => 0,
            'completed' => 0,
            'no_show' => 0,
            'cancelled' => 0
        );
        
        $yesterday_reservations = TRS_Database::get_reservations(array(
            'date_from' => $yesterday,
            'date_to' => $yesterday
        ));
        
        foreach ($yesterday_reservations as $reservation) {
            $yesterday_stats['total']++;
            if (isset($yesterday_stats[$reservation->status])) {
                $yesterday_stats[$reservation->status]++;
            }
        }
        
        // Get today's upcoming reservations
        $today_reservations = TRS_Database::get_reservations(array(
            'date_from' => $today,
            'date_to' => $today
        ));
        
        $today_confirmed = 0;
        $today_seated = 0;
        foreach ($today_reservations as $reservation) {
            if ($reservation->status == 'confirmed') {
                $today_confirmed++;
            } elseif ($reservation->status == 'seated') {
                $today_seated++;
            }
        }
        
        $report_data = array(
            'date' => $today,
            'yesterday' => array(
                'date' => $yesterday,
                'stats' => $yesterday_stats
            ),
            'today' => array(
                'total_reservations' => count($today_reservations),
                'confirmed' => $today_confirmed,
                'seated' => $today_seated
            )
        );
        
        // Send report email if enabled
        $daily_reports = TRS_Database::get_setting('send_daily_reports', '0');
        if ($daily_reports == '1') {
            self::send_daily_report_email($report_data);
        }
        
        return $report_data;
    }
    
    /**
     * Send daily report email to admin
     */
    private static function send_daily_report_email($report_data) {
        $restaurant_name = TRS_Database::get_setting('restaurant_name', 'Restaurant');
        $restaurant_email = TRS_Database::get_setting('restaurant_email', get_option('admin_email'));
        
        $subject = sprintf('Daily Reservation Report - %s', $restaurant_name);
        
        $message = self::get_daily_report_template($report_data, $restaurant_name);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: WordPress <' . get_option('admin_email') . '>'
        );
        
        return wp_mail($restaurant_email, $subject, $message, $headers);
    }
    
    /**
     * Generate daily report email template
     */
    private static function get_daily_report_template($report_data, $restaurant_name) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Daily Reservation Report</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .stats-section { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .stat-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
                .stat-number { font-weight: bold; color: #28a745; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Daily Report</h1>
                    <p><?php echo esc_html($restaurant_name); ?></p>
                    <p><?php echo date('F j, Y'); ?></p>
                </div>
                
                <div class="content">
                    <h2>Yesterday's Performance</h2>
                    <p><strong><?php echo date('F j, Y', strtotime($report_data['yesterday']['date'])); ?></strong></p>
                    
                    <div class="stats-section">
                        <div class="stat-row">
                            <span>Total Reservations:</span>
                            <span class="stat-number"><?php echo $report_data['yesterday']['stats']['total']; ?></span>
                        </div>
                        <div class="stat-row">
                            <span>Completed:</span>
                            <span class="stat-number"><?php echo $report_data['yesterday']['stats']['completed']; ?></span>
                        </div>
                        <div class="stat-row">
                            <span>No Shows:</span>
                            <span class="stat-number"><?php echo $report_data['yesterday']['stats']['no_show']; ?></span>
                        </div>
                        <div class="stat-row">
                            <span>Cancelled:</span>
                            <span class="stat-number"><?php echo $report_data['yesterday']['stats']['cancelled']; ?></span>
                        </div>
                    </div>
                    
                    <h2>Today's Schedule</h2>
                    <p><strong><?php echo date('F j, Y'); ?></strong></p>
                    
                    <div class="stats-section">
                        <div class="stat-row">
                            <span>Total Reservations:</span>
                            <span class="stat-number"><?php echo $report_data['today']['total_reservations']; ?></span>
                        </div>
                        <div class="stat-row">
                            <span>Confirmed:</span>
                            <span class="stat-number"><?php echo $report_data['today']['confirmed']; ?></span>
                        </div>
                        <div class="stat-row">
                            <span>Currently Seated:</span>
                            <span class="stat-number"><?php echo $report_data['today']['seated']; ?></span>
                        </div>
                    </div>
                    
                    <p><a href="<?php echo admin_url('admin.php?page=trs-dashboard'); ?>" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">View Full Dashboard</a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Schedule weekly maintenance tasks
     */
    public static function weekly_maintenance() {
        // Clean up old reservations
        $cleaned = self::cleanup_old_reservations();
        
        // Update any stale reservation statuses
        $updated = self::update_overdue_reservations();
        
        error_log("TRS: Weekly maintenance completed. Cleaned: {$cleaned}, Updated: {$updated}");
        
        return array(
            'cleaned' => $cleaned,
            'updated' => $updated
        );
    }
}