<?php

if (!defined('ABSPATH')) {
    exit;
}

class TRS_Email {
    
    public static function send_customer_confirmation($reservation_id) {
        $reservation = TRS_Database::get_reservation($reservation_id);
        
        if (!$reservation) {
            return false;
        }
        
        $restaurant_name = TRS_Database::get_setting('restaurant_name', 'Restaurant');
        $restaurant_email = TRS_Database::get_setting('restaurant_email', get_option('admin_email'));
        
        $subject = sprintf('Reservation Confirmation - %s', $restaurant_name);
        
        $message = self::get_customer_confirmation_template($reservation, $restaurant_name);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>'
        );
        
        return wp_mail($reservation->customer_email, $subject, $message, $headers);
    }
    
    public static function send_admin_notification($reservation_id) {
        $reservation = TRS_Database::get_reservation($reservation_id);
        
        if (!$reservation) {
            return false;
        }
        
        $restaurant_name = TRS_Database::get_setting('restaurant_name', 'Restaurant');
        $restaurant_email = TRS_Database::get_setting('restaurant_email', get_option('admin_email'));
        
        $subject = sprintf('New Reservation - %s', $restaurant_name);
        
        $message = self::get_admin_notification_template($reservation, $restaurant_name);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: WordPress <' . get_option('admin_email') . '>'
        );
        
        return wp_mail($restaurant_email, $subject, $message, $headers);
    }
    
    public static function send_cancellation_email($reservation_id) {
        $reservation = TRS_Database::get_reservation($reservation_id);
        
        if (!$reservation) {
            return false;
        }
        
        $restaurant_name = TRS_Database::get_setting('restaurant_name', 'Restaurant');
        $restaurant_email = TRS_Database::get_setting('restaurant_email', get_option('admin_email'));
        
        $subject = sprintf('Reservation Cancelled - %s', $restaurant_name);
        
        $message = self::get_cancellation_template($reservation, $restaurant_name);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>'
        );
        
        return wp_mail($reservation->customer_email, $subject, $message, $headers);
    }
    
    private static function get_customer_confirmation_template($reservation, $restaurant_name) {
        $reservation_date = TRS_Frontend::format_date_for_display($reservation->reservation_date);
        $reservation_time = TRS_Frontend::format_time_for_display($reservation->reservation_time);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reservation Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .reservation-details { background: #f1f3f4; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; font-size: 14px; color: #666; }
                .confirmation-number { background: #4CAF50; color: white; padding: 8px 16px; border-radius: 4px; display: inline-block; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($restaurant_name); ?></h1>
                    <p>Reservation Confirmation</p>
                </div>
                
                <div class="content">
                    <h2>Thank you for your reservation!</h2>
                    
                    <p>Dear <?php echo esc_html($reservation->customer_name); ?>,</p>
                    
                    <p>We're pleased to confirm your reservation. Here are the details:</p>
                    
                    <div class="confirmation-number">
                        Confirmation #<?php echo $reservation->id; ?>
                    </div>
                    
                    <div class="reservation-details">
                        <h3>Reservation Details</h3>
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
                        <div class="detail-row">
                            <strong>Table:</strong>
                            <span><?php echo esc_html($reservation->table_number); ?></span>
                        </div>
                        <?php if (!empty($reservation->special_request)): ?>
                        <div class="detail-row">
                            <strong>Special Requests:</strong>
                            <span><?php echo esc_html($reservation->special_request); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <h3>Important Information</h3>
                    <ul>
                        <li>Please arrive on time for your reservation</li>
                        <li>If you need to cancel or modify your reservation, please contact us as soon as possible</li>
                        <li>Tables are held for 15 minutes past the reservation time</li>
                    </ul>
                    
                    <p>We look forward to serving you!</p>
                </div>
                
                <div class="footer">
                    <p>If you have any questions, please don't hesitate to contact us.</p>
                    <p><strong><?php echo esc_html($restaurant_name); ?></strong></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private static function get_admin_notification_template($reservation, $restaurant_name) {
        $reservation_date = TRS_Frontend::format_date_for_display($reservation->reservation_date);
        $reservation_time = TRS_Frontend::format_time_for_display($reservation->reservation_time);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>New Reservation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2271b1; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .reservation-details { background: #f1f3f4; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
                .customer-info { background: #fff3cd; padding: 15px; border-radius: 6px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>New Reservation Alert</h1>
                    <p><?php echo esc_html($restaurant_name); ?></p>
                </div>
                
                <div class="content">
                    <h2>New Reservation Received</h2>
                    
                    <p>A new reservation has been made through your website.</p>
                    
                    <div class="reservation-details">
                        <h3>Reservation Details</h3>
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
                        <div class="detail-row">
                            <strong>Table:</strong>
                            <span><?php echo esc_html($reservation->table_number); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Status:</strong>
                            <span><?php echo ucfirst($reservation->status); ?></span>
                        </div>
                    </div>
                    
                    <div class="customer-info">
                        <h3>Customer Information</h3>
                        <div class="detail-row">
                            <strong>Name:</strong>
                            <span><?php echo esc_html($reservation->customer_name); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Email:</strong>
                            <span><?php echo esc_html($reservation->customer_email); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Phone:</strong>
                            <span><?php echo esc_html($reservation->customer_phone); ?></span>
                        </div>
                        <?php if (!empty($reservation->special_request)): ?>
                        <div class="detail-row">
                            <strong>Special Requests:</strong>
                            <span><?php echo esc_html($reservation->special_request); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <p><a href="<?php echo admin_url('admin.php?page=trs-reservations'); ?>" style="background: #2271b1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">View in Admin Dashboard</a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private static function get_cancellation_template($reservation, $restaurant_name) {
        $reservation_date = TRS_Frontend::format_date_for_display($reservation->reservation_date);
        $reservation_time = TRS_Frontend::format_time_for_display($reservation->reservation_time);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reservation Cancelled</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .reservation-details { background: #f8d7da; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; font-size: 14px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($restaurant_name); ?></h1>
                    <p>Reservation Cancelled</p>
                </div>
                
                <div class="content">
                    <h2>Reservation Cancellation</h2>
                    
                    <p>Dear <?php echo esc_html($reservation->customer_name); ?>,</p>
                    
                    <p>We're writing to confirm that your reservation has been cancelled.</p>
                    
                    <div class="reservation-details">
                        <h3>Cancelled Reservation Details</h3>
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
                    
                    <p>We're sorry to see you go, but we understand that plans can change. We hope to have the opportunity to serve you in the future.</p>
                    
                    <p>If you'd like to make a new reservation, please visit our website or contact us directly.</p>
                </div>
                
                <div class="footer">
                    <p>Thank you for considering <?php echo esc_html($restaurant_name); ?>.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    public static function send_reminder_email($reservation_id) {
        $reservation = TRS_Database::get_reservation($reservation_id);
        
        if (!$reservation || $reservation->status != 'confirmed') {
            return false;
        }
        
        $restaurant_name = TRS_Database::get_setting('restaurant_name', 'Restaurant');
        $restaurant_email = TRS_Database::get_setting('restaurant_email', get_option('admin_email'));
        
        $subject = sprintf('Reservation Reminder - %s', $restaurant_name);
        
        $message = self::get_reminder_template($reservation, $restaurant_name);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $restaurant_name . ' <' . $restaurant_email . '>'
        );
        
        return wp_mail($reservation->customer_email, $subject, $message, $headers);
    }
    
    private static function get_reminder_template($reservation, $restaurant_name) {
        $reservation_date = TRS_Frontend::format_date_for_display($reservation->reservation_date);
        $reservation_time = TRS_Frontend::format_time_for_display($reservation->reservation_time);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reservation Reminder</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #17a2b8; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px; }
                .content { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
                .reservation-details { background: #d1ecf1; padding: 15px; border-radius: 6px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($restaurant_name); ?></h1>
                    <p>Reservation Reminder</p>
                </div>
                
                <div class="content">
                    <h2>Don't forget about your reservation!</h2>
                    
                    <p>Dear <?php echo esc_html($reservation->customer_name); ?>,</p>
                    
                    <p>This is a friendly reminder about your upcoming reservation with us.</p>
                    
                    <div class="reservation-details">
                        <h3>Your Reservation</h3>
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
                        <div class="detail-row">
                            <strong>Confirmation #:</strong>
                            <span><?php echo $reservation->id; ?></span>
                        </div>
                    </div>
                    
                    <p>We look forward to seeing you!</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}