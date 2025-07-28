# Table Reservation System WordPress Plugin

A comprehensive WordPress plugin for managing restaurant table reservations with a beautiful multi-step booking form, admin dashboard, and automation features.

## Features

### 🔧 Admin Functionalities
- **Table Management**: Add/manage tables with sitting capacities (1–15 people)
- **Reservation Dashboard**: View all reservations with statuses (Confirmed, Seated, Completed, Cancelled, No Show)
- **Status Management**: Manually update reservation statuses
- **Booking Control**: Temporarily close bookings when restaurant is full
- **Calendar View**: View booking calendar and past statistics
- **Settings Panel**: Configure restaurant details, booking hours, and automation

### 🧾 Frontend Booking Form
Beautiful 4-step booking process:
1. **Party Size**: Select number of people (1-15)
2. **Date & Time**: Choose preferred date and available time slots
3. **Contact Details**: Name, email, phone, and special requests
4. **Review & Confirm**: Summary review before submitting

### 🔄 Automation Logic
- **Smart Table Matching**: System automatically matches party size to available tables
- **Conflict Prevention**: Prevents double booking for same table and time slot
- **Auto-Release**: Tables automatically released after 1 hour if not marked "Seated"
- **Status Updates**: Automated status updates based on time and conditions

### ✉️ Email Notifications
- **Customer Confirmation**: Detailed confirmation email with reservation details
- **Admin Alerts**: New reservation notifications for restaurant staff
- **Status Updates**: Cancellation and reminder emails
- **Auto-Release Notifications**: Optional emails when reservations are auto-released

### 🌐 WordPress Integration
- **Custom Plugin**: Fully integrated WordPress plugin
- **Shortcode Support**: Easy embedding with `[table_reservation_form]`
- **WordPress Database**: Uses WordPress database with proper prefixes
- **Admin Dashboard**: Native WordPress admin interface
- **Responsive Design**: Mobile-friendly booking form and admin interface

## Installation

### Method 1: Direct Upload (Recommended)

1. **Download the Plugin**
   - Download all plugin files to your computer
   - Ensure the directory structure is maintained

2. **Upload to WordPress**
   ```bash
   # Via FTP/cPanel File Manager
   wp-content/plugins/table-reservation-system/
   ```

3. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Table Reservation System"
   - Click "Activate"

### Method 2: WordPress Admin Upload

1. **Create Plugin Zip**
   ```bash
   zip -r table-reservation-system.zip table-reservation-system/
   ```

2. **Upload via WordPress Admin**
   - Go to Plugins → Add New → Upload Plugin
   - Choose the zip file and install
   - Activate the plugin

## Configuration

### Initial Setup

1. **Access Plugin Settings**
   - Go to WordPress Admin → Reservations → Settings

2. **Configure Restaurant Details**
   ```php
   Restaurant Name: Your Restaurant Name
   Restaurant Email: admin@yourrestaurant.com
   ```

3. **Set Booking Hours**
   ```php
   Start Time: 10:00 AM
   End Time: 10:00 PM
   Booking Interval: 30 minutes
   ```

4. **Configure Automation**
   ```php
   Reservation Duration: 120 minutes
   Auto Release Time: 60 minutes
   Maximum Advance Days: 30 days
   ```

### Table Management

1. **Add Tables**
   - Go to Reservations → Tables
   - Add tables with unique numbers and capacities
   - Default tables (T01-T10) are created automatically

2. **Example Table Setup**
   ```php
   T01 - 2 people (couple tables)
   T02 - 2 people
   T03 - 4 people (small family)
   T04 - 4 people
   T05 - 6 people (medium groups)
   T06 - 6 people
   T07 - 8 people (large groups)
   T08 - 10 people
   T09 - 12 people
   T10 - 15 people (maximum capacity)
   ```

## Usage

### Adding Booking Form to Pages

#### Using Shortcode
```php
[table_reservation_form]
```

#### PHP Template Code
```php
<?php
if (shortcode_exists('table_reservation_form')) {
    echo do_shortcode('[table_reservation_form]');
}
?>
```

#### Gutenberg Block
Add the shortcode block and enter: `[table_reservation_form]`

#### Elementor Integration
Use the shortcode widget with: `[table_reservation_form]`

### Managing Reservations

#### Dashboard Overview
- **Today's Reservations**: Quick view of current day
- **Statistics**: Week/month reservation counts
- **Booking Control**: Open/close reservations instantly

#### Reservation Statuses
- **Confirmed**: New reservation awaiting arrival
- **Seated**: Customer has arrived and been seated
- **Completed**: Meal finished, table available
- **Cancelled**: Reservation cancelled by customer/admin
- **No Show**: Customer didn't arrive (auto or manual)

#### Status Workflow
1. Customer books → **Confirmed**
2. Customer arrives → **Seated** (manually updated)
3. Customer leaves → **Completed** (manually updated)
4. Auto-release → **No Show** (after 60 minutes)

### Email Configuration

#### WordPress Email Setup
Ensure WordPress can send emails:
```php
// In wp-config.php (if needed)
define('SMTP_HOST', 'your-smtp-host.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@domain.com');
define('SMTP_PASSWORD', 'your-password');
```

#### Email Templates
All emails are professionally designed with:
- Restaurant branding
- Reservation details
- Confirmation numbers
- Contact information

## Customization

### Styling the Booking Form

#### Custom CSS
```css
/* Customize booking form colors */
.trs-booking-container {
    border-radius: 15px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.trs-form-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.trs-btn-primary {
    background: #your-brand-color;
}
```

#### Theme Integration
```php
// In your theme's functions.php
function custom_trs_styles() {
    wp_enqueue_style(
        'custom-trs', 
        get_template_directory_uri() . '/css/custom-reservations.css',
        array('trs-frontend'),
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'custom_trs_styles');
```

### Custom Email Templates

Create custom email templates by copying the email classes and modifying:

```php
// Custom email template
class Custom_TRS_Email extends TRS_Email {
    protected static function get_customer_confirmation_template($reservation, $restaurant_name) {
        // Your custom template code
    }
}
```

### Database Schema

#### Tables Created
```sql
-- Restaurant tables
wp_trs_tables
- id (Primary Key)
- table_number (Unique)
- capacity (1-15)
- is_active (Boolean)
- created_at (Timestamp)

-- Reservations
wp_trs_reservations
- id (Primary Key)
- table_id (Foreign Key)
- customer_name
- customer_email
- customer_phone
- party_size
- reservation_date
- reservation_time
- special_request
- status (Enum)
- created_at
- updated_at

-- Settings
wp_trs_settings
- id (Primary Key)
- setting_key (Unique)
- setting_value
```

## API Reference

### Shortcode Attributes
```php
[table_reservation_form class="custom-class" style="custom-style"]
```

### WordPress Hooks

#### Actions
```php
// Before reservation is created
do_action('trs_before_reservation_create', $reservation_data);

// After reservation is created
do_action('trs_after_reservation_create', $reservation_id, $reservation_data);

// Status change
do_action('trs_reservation_status_changed', $reservation_id, $old_status, $new_status);
```

#### Filters
```php
// Modify available times
apply_filters('trs_available_times', $times, $date, $party_size);

// Customize email content
apply_filters('trs_email_content', $content, $reservation, $type);

// Modify form fields
apply_filters('trs_form_fields', $fields);
```

## Troubleshooting

### Common Issues

#### 1. Emails Not Sending
```php
// Test WordPress email function
wp_mail('test@example.com', 'Test', 'Test message');

// Install SMTP plugin if needed
// WP Mail SMTP or Easy WP SMTP
```

#### 2. Times Not Loading
- Check JavaScript console for errors
- Verify AJAX URL is correct
- Ensure nonce values are valid

#### 3. Database Errors
```php
// Check WordPress debug log
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check /wp-content/debug.log
```

#### 4. Styling Issues
- Clear caching plugins
- Check theme compatibility
- Verify CSS files are loading

### Debug Mode

Enable debug mode for troubleshooting:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Performance Optimization

### Caching Considerations
```php
// Exclude reservation pages from caching
// Add to .htaccess or caching plugin settings
/reservation-form/
/wp-admin/admin-ajax.php
```

### Database Optimization
```sql
-- Add indexes for better performance
ALTER TABLE wp_trs_reservations 
ADD INDEX idx_date_time (reservation_date, reservation_time);

ALTER TABLE wp_trs_reservations 
ADD INDEX idx_status (status);
```

## Security Features

- **Nonce Verification**: All AJAX requests verified
- **Data Sanitization**: All input properly sanitized
- **SQL Injection Protection**: Prepared statements used
- **XSS Prevention**: Output properly escaped
- **Capability Checks**: Admin functions protected

## Support

### Documentation
- Full code documentation in source files
- Inline comments explaining functionality
- WordPress coding standards followed

### Customization Services
For custom modifications or additional features:
- Theme integration
- Custom email templates
- Additional notification methods
- Advanced reporting features

## Changelog

### Version 1.0.0
- Initial release
- Multi-step booking form
- Admin dashboard
- Email notifications
- Table management
- Automation features
- WordPress integration

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## Credits

Created with modern web technologies:
- **Frontend**: HTML5, CSS3 (Grid/Flexbox), JavaScript (ES6+)
- **Backend**: PHP 7.4+, WordPress 5.0+
- **Database**: MySQL 5.7+
- **Email**: WordPress wp_mail() function
- **Design**: Responsive, mobile-first approach

---

**Enjoy your new table reservation system!** 🍽️✨