<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/admin/class-admin.php';

class CyberSMTP_Plugin_Core {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        $logs_table = $wpdb->prefix . 'cybersmtp_email_logs';
        $settings_table = $wpdb->prefix . 'cybersmtp_settings';

        $sql_logs = "CREATE TABLE $logs_table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255),
            subject TEXT,
            body LONGTEXT,
            headers TEXT,
            provider VARCHAR(50),
            status VARCHAR(20),
            created_at DATETIME,
            updated_at DATETIME,
            response_data TEXT
        ) $charset_collate;";

        $sql_settings = "CREATE TABLE $settings_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            option_name VARCHAR(255),
            option_value LONGTEXT,
            autoload VARCHAR(3) DEFAULT 'yes'
        ) $charset_collate;";

        dbDelta($sql_logs);
        dbDelta($sql_settings);
    }

    public static function deactivate() {
        // Cleanup tasks if needed
    }

    private function __construct() {
        // Initialize plugin components
        new CyberSMTP_Mailer();
        if (is_admin()) {
            new CyberSMTP_Admin();
        }
        // e.g., settings, logger, etc.
    }
} 