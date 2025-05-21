<?php
/*
Plugin Name: CyberSMTP
Description: Advanced SMTP plugin for WordPress with multi-provider support, logging, analytics, and security features.
Version: 1.0.0
Author: CyberPanel - Usman
Text Domain: cyberpanel.net
Domain Path: /cyber-smtp
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load Composer autoloader if present
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Explicitly load the core class for activation/deactivation
require_once __DIR__ . '/includes/class-plugin-core.php';

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'CyberSMTP_') === 0) {
        $file = plugin_dir_path(__FILE__) . 'includes/' . strtolower(str_replace('CyberSMTP_', 'class-', $class)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Activation/Deactivation hooks
register_activation_hook(__FILE__, ['CyberSMTP_Plugin_Core', 'activate']);
register_deactivation_hook(__FILE__, ['CyberSMTP_Plugin_Core', 'deactivate']);

// Initialize plugin core
add_action('plugins_loaded', function () {
    if (class_exists('CyberSMTP_Plugin_Core')) {
        CyberSMTP_Plugin_Core::instance();
    }
});

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        $mailer = new CyberSMTP_Mailer();
        $is_html = false;
        $headers_arr = array();
        if (!empty($headers)) {
            if (is_array($headers)) {
                $headers_arr = $headers;
            } else {
                $headers_arr = explode("\n", str_replace("\r\n", "\n", $headers));
            }
            foreach ($headers_arr as $header) {
                if (stripos($header, 'Content-Type:') !== false && stripos($header, 'text/html') !== false) {
                    $is_html = true;
                }
            }
        }
        $email_data = array(
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers_arr,
            'is_html' => $is_html,
            'attachments' => $attachments,
        );
        $result = $mailer->send_via_provider($email_data);
        if (!$result['success']) {
            file_put_contents(__DIR__ . '/cybersmtp-debug.log', "wp_mail error: " . $result['error'] . "\n", FILE_APPEND);
        }
        return $result['success'];
    }
}

// Admin notice for SMTP configuration
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    if (!is_admin()) return;
    // Dismiss logic
    $user_id = get_current_user_id();
    if (get_user_meta($user_id, 'cybersmtp_notice_dismissed', true)) return;
    // Check if SMTP is configured (provider and host or API key)
    $settings = get_option('cybersmtp_smtp_settings', array());
    $provider = $settings['provider'] ?? '';
    $host = $settings['host'] ?? '';
    $api_key = $settings['api_key'] ?? '';
    if (!$provider || (!$host && !$api_key)) {
        $settings_url = admin_url('admin.php?page=cybersmtp&tab=settings');
        echo '<div class="notice notice-warning is-dismissible cybersmtp-admin-notice">
            <strong>CyberSMTP:</strong> Please configure your SMTP settings. Without configuration, WordPress will not be able to send emails. '
            . '<a href="' . esc_url($settings_url) . '"><strong>Go to Settings</strong></a>' .
        '</div>';
    }
});

// Handle dismiss via AJAX
add_action('admin_enqueue_scripts', function() {
    if (!current_user_can('manage_options')) return;
    wp_add_inline_script('jquery-core',
        'jQuery(document).on("click", ".cybersmtp-admin-notice .notice-dismiss", function(){
            jQuery.post(ajaxurl, {action: "cybersmtp_dismiss_notice"});
        });'
    );
});

add_action('wp_ajax_cybersmtp_dismiss_notice', function() {
    update_user_meta(get_current_user_id(), 'cybersmtp_notice_dismissed', 1);
    wp_die();
}); 