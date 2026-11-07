<?php
/*
Plugin Name: CyberSMTP
Plugin URI: https://cyberpanel.net/cybersmtp
Description: The official CyberPanel email plugin. Send WordPress emails reliably via CyberMail, Amazon SES, SendGrid, Mailgun, Brevo, or any SMTP server. Built-in deliverability tools, email logs, and analytics.
Version: 2.0.1
Author: CyberPanel
Author URI: https://cyberpanel.net
Text Domain: cybersmtp
License: GPLv2 or later
*/

if (!defined('ABSPATH')) {
    exit;
}

define('CYBERSMTP_VERSION', '2.0.1');
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
        $atts = apply_filters('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));
        $to = $atts['to'];
        $subject = $atts['subject'];
        $message = $atts['message'];
        $headers = $atts['headers'];
        $attachments = $atts['attachments'];
        $short_circuit = apply_filters('pre_wp_mail', null, $atts);
        if (null !== $short_circuit) {
            return $short_circuit;
        }

        $parsed = cybersmtp_parse_headers($headers);

        $settings = get_option('cybersmtp_smtp_settings', []);
        $from_email = apply_filters('wp_mail_from', $parsed['from_email'] ?: ($settings['from_email'] ?? ''));
        $from_name = apply_filters('wp_mail_from_name', $parsed['from_name'] !== '' ? $parsed['from_name'] : ($settings['from_name'] ?? get_bloginfo('name')));
        $content_type = apply_filters('wp_mail_content_type', $parsed['content_type']);
        $charset    = apply_filters('wp_mail_charset', $parsed['charset']);

        if (!is_array($to)) {
            $to = cybersmtp_split_addresses($to);
        }
        if (!is_array($attachments)) {
            $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
        }

        $mailer = new CyberSMTP_Mailer();
        $result = $mailer->send_via_provider([
            'to'          => array_values(array_filter(array_map('trim', $to))),
            'subject'     => $subject,
            'message'     => $message,
            'headers'     => $parsed['custom'],
            'original_headers' => is_array($headers) ? $headers : preg_split('/\r\n|\r|\n/', $headers),
            'from_email'  => $from_email,
            'from_name'   => $from_name,
            'reply_to'    => $parsed['reply_to'],
            'cc'          => $parsed['cc'],
            'bcc'         => $parsed['bcc'],
            'content_type' => $content_type,
            'is_html'     => stripos($content_type, 'text/html') !== false,
            'charset'     => $charset,
            'attachments' => array_filter((array) $attachments),
        ]);

        if (empty($result['success'])) {
            do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $result['error'] ?? '', $atts));
        } else {
            do_action('wp_mail_succeeded', $atts);
        }

        return !empty($result['success']);
    }
}

/** Split address lists without splitting quoted names or angle addresses. */
function cybersmtp_split_addresses($value) {
    $result = [];
    $current = '';
    $quoted = false;
    $escaped = false;
    $angle = 0;
    foreach (str_split((string) $value) as $char) {
        if ($escaped) {
            $escaped = false;
        } elseif ($char === "\\" && $quoted) {
            $escaped = true;
        } elseif ($char === '"') {
            $quoted = !$quoted;
        } elseif (!$quoted && $char === '<') {
            $angle++;
        } elseif (!$quoted && $char === '>') {
            $angle = max(0, $angle - 1);
        } elseif (!$quoted && !$angle && $char === ',') {
            if (trim($current) !== '') { $result[] = trim($current); }
            $current = '';
            continue;
        }
        $current .= $char;
    }
    if (trim($current) !== '') { $result[] = trim($current); }
    return $result;
}

/** Preserve display names; PHPMailer validates the resulting mailbox. */
function cybersmtp_address_parts($address) {
    if (preg_match('/^(.*?)\s*<([^<>]+)>\s*$/', trim($address), $matches)) {
        return [trim($matches[2]), trim(trim($matches[1]), '"')];
    }
    return [trim($address), ''];
}

/**
 * Split wp_mail() headers (string or array) the way core does.
 * From/Reply-To/Cc/Bcc/Content-Type become structured fields; everything
 * else is returned as "Name: value" custom headers. Blank or malformed
 * lines are dropped.
 */
function cybersmtp_parse_headers($headers) {
    $out = [
        'from_email' => '', 'from_name' => '', 'reply_to' => [], 'cc' => [], 'bcc' => [],
        'content_type' => 'text/plain', 'charset' => get_bloginfo('charset') ?: 'UTF-8', 'custom' => [],
    ];
    if (empty($headers)) {
        return $out;
    }
    $lines = [];
    foreach (is_array($headers) ? $headers : preg_split('/\r\n|\r|\n/', $headers) as $name => $value) {
        $lines[] = is_string($name) && is_string($value) ? $name . ': ' . $value : $value;
    }

    foreach ($lines as $line) {
        if (!is_string($line) || strpos($line, ':') === false) {
            continue;
        }
        list($name, $value) = explode(':', $line, 2);
        $name  = trim($name);
        $value = trim($value);
        if ($name === '' || $value === '') {
            continue;
        }
        switch (strtolower($name)) {
            case 'from':
                $out['from_name'] = '';
                if (preg_match('/^(.*?)\s*<(.+)>\s*$/', $value, $m)) {
                    $out['from_name']  = trim(trim($m[1]), '"');
                    $out['from_email'] = trim($m[2]);
                } else {
                    $out['from_email'] = $value;
                }
                break;
            case 'reply-to':
            case 'cc':
            case 'bcc':
                $key = strtolower($name) === 'reply-to' ? 'reply_to' : strtolower($name);
                $out[$key] = array_merge($out[$key], array_filter(array_map('trim', cybersmtp_split_addresses($value))));
                break;
            case 'content-type':
                $parts = explode(';', $value);
                $out['content_type'] = trim($parts[0]);
                foreach (array_slice($parts, 1) as $param) {
                    if (stripos($param, 'charset=') !== false) {
                        $out['charset'] = trim(str_ireplace('charset=', '', $param), " \t\"'");
                    }
                }
                break;
            default:
                $out['custom'][] = $name . ': ' . $value;
        }
    }
    return $out;
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
