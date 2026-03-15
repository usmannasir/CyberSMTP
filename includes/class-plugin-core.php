<?php
if (!defined('ABSPATH')) {
    exit;
}

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
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $logs = $wpdb->prefix . 'cybersmtp_email_logs';

        $sql = "CREATE TABLE $logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255) NOT NULL,
            subject TEXT,
            body LONGTEXT,
            headers TEXT,
            provider VARCHAR(50) DEFAULT '',
            status VARCHAR(20) DEFAULT 'sent',
            message_id VARCHAR(255) DEFAULT '',
            error_message TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_status (status),
            KEY idx_created (created_at),
            KEY idx_to_email (to_email(100)),
            KEY idx_message_id (message_id(100))
        ) $charset;";

        dbDelta($sql);

        // Set default settings if none exist
        if (!get_option('cybersmtp_smtp_settings')) {
            update_option('cybersmtp_smtp_settings', [
                'provider' => '',
                'mode'     => 'api',
            ]);
        }

        update_option('cybersmtp_version', CYBERSMTP_VERSION);
    }

    public static function deactivate() {
        delete_transient('cybersmtp_cybermail_stats');
    }

    private function __construct() {
        new CyberSMTP_Mailer();

        if (is_admin()) {
            new CyberSMTP_Admin();
        }

        // Register AJAX handlers
        add_action('wp_ajax_cybersmtp_test_email', [$this, 'ajax_test_email']);
        add_action('wp_ajax_cybersmtp_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_cybersmtp_resend_email', [$this, 'ajax_resend_email']);
        add_action('wp_ajax_cybersmtp_check_dns', [$this, 'ajax_check_dns']);
        add_action('wp_ajax_cybersmtp_get_stats', [$this, 'ajax_get_stats']);
    }

    public function ajax_test_email() {
        check_ajax_referer('cybersmtp_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $to = sanitize_email($_POST['email'] ?? '');
        if (!is_email($to)) {
            wp_send_json_error('Invalid email address.');
        }

        $result = wp_mail(
            $to,
            'CyberSMTP Test Email',
            '<div style="font-family:Inter,Arial,sans-serif;max-width:500px;margin:0 auto;padding:32px;">'
            . '<div style="text-align:center;margin-bottom:24px;">'
            . '<img src="https://cyberpanel.net/wp-content/uploads/2026/03/cyberpanel-logo-icon_only.png" width="48" height="48" alt="CyberPanel">'
            . '</div>'
            . '<h2 style="color:#1a1a2e;text-align:center;">Email Delivered Successfully!</h2>'
            . '<p style="color:#555;text-align:center;">Your CyberSMTP configuration is working correctly.</p>'
            . '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;margin:24px 0;text-align:center;">'
            . '<strong style="color:#166534;">Provider:</strong> '
            . '<span style="color:#166534;">' . esc_html(self::get_provider_name()) . '</span>'
            . '</div>'
            . '<p style="color:#888;font-size:13px;text-align:center;">Sent from ' . esc_html(get_bloginfo('name')) . ' via CyberSMTP</p>'
            . '</div>',
            ['Content-Type: text/html; charset=UTF-8']
        );

        if ($result) {
            wp_send_json_success('Test email sent to ' . $to);
        } else {
            wp_send_json_error('Failed to send test email. Check your settings and logs.');
        }
    }

    public function ajax_test_connection() {
        check_ajax_referer('cybersmtp_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $settings = get_option('cybersmtp_smtp_settings', []);
        $provider = $settings['provider'] ?? '';

        if ($provider === 'cybermail') {
            $cm = new CyberSMTP_Provider_CyberMail($settings);
            if ($cm->verify_connection()) {
                $stats = $cm->get_account_stats();
                wp_send_json_success([
                    'message' => 'Connected to CyberMail successfully!',
                    'stats'   => $stats,
                ]);
            } else {
                wp_send_json_error('Could not connect to CyberMail. Check your API key.');
            }
        } elseif ($provider === 'smtp' && !empty($settings['host'])) {
            // Quick SMTP socket test
            $host = $settings['host'];
            $port = intval($settings['port'] ?? 587);
            $conn = @fsockopen($host, $port, $errno, $errstr, 10);
            if ($conn) {
                fclose($conn);
                wp_send_json_success(['message' => "Connected to $host:$port successfully!"]);
            } else {
                wp_send_json_error("Could not connect to $host:$port — $errstr");
            }
        } else {
            wp_send_json_success(['message' => 'Provider configured.']);
        }
    }

    public function ajax_resend_email() {
        check_ajax_referer('cybersmtp_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $log_id = intval($_POST['log_id'] ?? 0);
        if (!$log_id) {
            wp_send_json_error('Invalid log ID.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cybersmtp_email_logs';
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $log_id), ARRAY_A);

        if (!$log) {
            wp_send_json_error('Email log not found.');
        }

        $headers = maybe_unserialize($log['headers']);
        if (!is_array($headers)) {
            $headers = [];
        }

        $result = wp_mail($log['to_email'], $log['subject'], $log['body'], $headers);

        if ($result) {
            wp_send_json_success('Email resent to ' . $log['to_email']);
        } else {
            wp_send_json_error('Failed to resend email.');
        }
    }

    public function ajax_check_dns() {
        check_ajax_referer('cybersmtp_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        if (empty($domain)) {
            $domain = CyberSMTP_CyberPanel_Detector::get_site_domain();
        }

        $results = [
            'domain' => $domain,
            'spf'    => self::check_spf($domain),
            'dkim'   => self::check_dkim($domain),
            'dmarc'  => self::check_dmarc($domain),
            'mx'     => self::check_mx($domain),
        ];

        wp_send_json_success($results);
    }

    public function ajax_get_stats() {
        check_ajax_referer('cybersmtp_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $logger = new CyberSMTP_Email_Logger();
        $stats = $logger->get_chart_data(intval($_POST['days'] ?? 7));

        // If CyberMail, merge with API stats (transform field names for JS)
        $settings = get_option('cybersmtp_smtp_settings', []);
        if (($settings['provider'] ?? '') === 'cybermail' && !empty($settings['api_key'])) {
            $cm = new CyberSMTP_Provider_CyberMail($settings);
            $api_stats = $cm->get_account_stats();
            if ($api_stats) {
                $total = $api_stats['this_month']['total'] ?? 0;
                $bounced = $api_stats['this_month']['bounced'] ?? 0;
                $stats['cybermail'] = [
                    'sent_today'  => $api_stats['emails_sent_this_month'] ?? 0,
                    'quota'       => $api_stats['monthly_limit'] ?? 0,
                    'bounce_rate' => $total > 0 ? round(($bounced / $total) * 100, 1) : 0,
                    'plan'        => $api_stats['plan'] ?? '',
                    'remaining'   => $api_stats['emails_remaining'] ?? 0,
                ];
            }
        }

        wp_send_json_success($stats);
    }

    private static function check_spf($domain) {
        $records = @dns_get_record($domain, DNS_TXT);
        if (!$records) {
            return ['status' => 'missing', 'record' => null];
        }
        foreach ($records as $r) {
            if (!empty($r['txt']) && stripos($r['txt'], 'v=spf1') === 0) {
                return ['status' => 'found', 'record' => $r['txt']];
            }
        }
        return ['status' => 'missing', 'record' => null];
    }

    private static function check_dkim($domain) {
        $selectors = ['default', 'cybermail', 'google', 'selector1', 'selector2', 'k1', 'mail'];
        foreach ($selectors as $sel) {
            $records = @dns_get_record($sel . '._domainkey.' . $domain, DNS_TXT);
            if ($records) {
                foreach ($records as $r) {
                    if (!empty($r['txt']) && stripos($r['txt'], 'v=DKIM1') !== false) {
                        return ['status' => 'found', 'selector' => $sel, 'record' => $r['txt']];
                    }
                }
            }
        }
        return ['status' => 'missing', 'selector' => null, 'record' => null];
    }

    private static function check_dmarc($domain) {
        $records = @dns_get_record('_dmarc.' . $domain, DNS_TXT);
        if (!$records) {
            return ['status' => 'missing', 'record' => null];
        }
        foreach ($records as $r) {
            if (!empty($r['txt']) && stripos($r['txt'], 'v=DMARC1') === 0) {
                return ['status' => 'found', 'record' => $r['txt']];
            }
        }
        return ['status' => 'missing', 'record' => null];
    }

    private static function check_mx($domain) {
        $records = @dns_get_record($domain, DNS_MX);
        if (!$records || empty($records)) {
            return ['status' => 'missing', 'records' => []];
        }
        $mx = [];
        foreach ($records as $r) {
            $mx[] = ['priority' => $r['pri'] ?? 0, 'host' => $r['target'] ?? ''];
        }
        usort($mx, function ($a, $b) { return $a['priority'] - $b['priority']; });
        return ['status' => 'found', 'records' => $mx];
    }

    private static function get_provider_name() {
        $settings = get_option('cybersmtp_smtp_settings', []);
        $names = [
            'cybermail' => 'CyberMail',
            'smtp'      => 'Generic SMTP',
            'ses'       => 'Amazon SES',
            'sendgrid'  => 'SendGrid',
            'mailgun'   => 'Mailgun',
            'brevo'     => 'Brevo',
        ];
        return $names[$settings['provider'] ?? ''] ?? 'Unknown';
    }
}
