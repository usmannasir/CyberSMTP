<?php
/*
Plugin Name: CyberSMTP
Plugin URI: https://cyberpanel.net/cybersmtp
Description: The official CyberPanel email plugin. Send WordPress emails reliably via CyberMail, Amazon SES, SendGrid, Mailgun, Brevo, or any SMTP server. Built-in deliverability tools, email logs, and analytics.
Version: 2.0.0
Author: CyberPanel
Author URI: https://cyberpanel.net
Text Domain: cybersmtp
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit;
}

define('CYBERSMTP_VERSION', '2.0.0');
define('CYBERSMTP_PATH', plugin_dir_path(__FILE__));
define('CYBERSMTP_URL', plugin_dir_url(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'CyberSMTP_') !== 0) {
        return;
    }

    $map = [
        'CyberSMTP_Plugin_Core'        => 'includes/class-plugin-core.php',
        'CyberSMTP_Admin'              => 'admin/class-admin.php',
        'CyberSMTP_Mailer'             => 'includes/class-mailer.php',
        'CyberSMTP_Email_Logger'       => 'includes/class-email-logger.php',
        'CyberSMTP_Settings'           => 'includes/class-settings.php',
        'CyberSMTP_CyberPanel_Detector'=> 'includes/class-cyberpanel-detector.php',
        'CyberSMTP_Provider_Abstract'  => 'includes/providers/class-provider-abstract.php',
        'CyberSMTP_Provider_CyberMail' => 'includes/providers/class-provider-cybermail.php',
        'CyberSMTP_Provider_SMTP'      => 'includes/providers/class-provider-smtp.php',
        'CyberSMTP_Provider_SES'       => 'includes/providers/class-provider-ses.php',
        'CyberSMTP_Provider_SendGrid'  => 'includes/providers/class-provider-sendgrid.php',
        'CyberSMTP_Provider_Mailgun'   => 'includes/providers/class-provider-mailgun.php',
        'CyberSMTP_Provider_Brevo'     => 'includes/providers/class-provider-brevo.php',
    ];

    if (isset($map[$class])) {
        require_once CYBERSMTP_PATH . $map[$class];
    }
});

// Activation/Deactivation
register_activation_hook(__FILE__, ['CyberSMTP_Plugin_Core', 'activate']);
register_deactivation_hook(__FILE__, ['CyberSMTP_Plugin_Core', 'deactivate']);

// Initialize
add_action('plugins_loaded', function () {
    CyberSMTP_Plugin_Core::instance();
});

// Override wp_mail
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
        $mailer = new CyberSMTP_Mailer();

        $is_html = false;
        $headers_arr = [];

        if (!empty($headers)) {
            $headers_arr = is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", $headers));
            foreach ($headers_arr as $header) {
                if (stripos($header, 'Content-Type:') !== false && stripos($header, 'text/html') !== false) {
                    $is_html = true;
                }
            }
        }

        $result = $mailer->send_via_provider([
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $message,
            'headers'     => $headers_arr,
            'is_html'     => $is_html,
            'attachments' => $attachments,
        ]);

        return $result['success'];
    }
}

// Admin notice: unconfigured
add_action('admin_notices', function () {
    if (!current_user_can('manage_options') || !is_admin()) {
        return;
    }

    $user_id = get_current_user_id();
    if (get_user_meta($user_id, 'cybersmtp_notice_dismissed', true)) {
        return;
    }

    $settings = get_option('cybersmtp_smtp_settings', []);
    $provider = $settings['provider'] ?? '';
    $has_key = !empty($settings['api_key']) || !empty($settings['host']);

    if ($provider && $has_key) {
        return;
    }

    $settings_url = admin_url('admin.php?page=cybersmtp');
    $is_cp = CyberSMTP_CyberPanel_Detector::is_cyberpanel();

    if ($is_cp) {
        $msg = '<strong>CyberSMTP:</strong> You\'re running CyberPanel! '
             . 'Set up <strong>CyberMail</strong> for free email delivery with deliverability tracking. '
             . '<a href="' . esc_url($settings_url) . '"><strong>Configure Now</strong></a>';
    } else {
        $msg = '<strong>CyberSMTP:</strong> Email is not configured. '
             . 'Set up <strong>CyberMail</strong> (free) or your preferred SMTP provider. '
             . '<a href="' . esc_url($settings_url) . '"><strong>Configure Now</strong></a>';
    }

    echo '<div class="notice notice-warning is-dismissible cybersmtp-admin-notice">'
       . '<p>' . $msg . '</p></div>';
});

// Dismiss notice AJAX
add_action('admin_enqueue_scripts', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    wp_add_inline_script('jquery-core',
        'jQuery(document).on("click",".cybersmtp-admin-notice .notice-dismiss",function(){jQuery.post(ajaxurl,{action:"cybersmtp_dismiss_notice"})});'
    );
});

add_action('wp_ajax_cybersmtp_dismiss_notice', function () {
    update_user_meta(get_current_user_id(), 'cybersmtp_notice_dismissed', 1);
    wp_die();
});
