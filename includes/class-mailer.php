<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-email-logger.php';

use PHPMailer\PHPMailer\PHPMailer;

class CyberSMTP_Mailer {
    protected $provider;
    protected $logger;
    protected $last_error = '';
    protected $debug_log;
    protected static $phpmailer_hooked = false;

    public function __construct() {
        $this->debug_log = __DIR__ . '/../cybersmtp-debug.log';
        $settings = get_option('cybersmtp_smtp_settings', array());
        $provider_key = $settings['provider'] ?? 'smtp';
        $provider_class = [
            'smtp' => 'CyberSMTP_Provider_SMTP',
            'ses' => 'CyberSMTP_Provider_SES',
            'sendgrid' => 'CyberSMTP_Provider_SendGrid',
            'mailgun' => 'CyberSMTP_Provider_Mailgun',
            'brevo' => 'CyberSMTP_Provider_Brevo',
        ][$provider_key] ?? 'CyberSMTP_Provider_SMTP';
        if (!class_exists($provider_class)) {
            require_once __DIR__ . '/providers/class-provider-' . strtolower($provider_key) . '.php';
        }
        $this->provider = new $provider_class($settings);
        $this->logger = new CyberSMTP_Email_Logger();
        if (!self::$phpmailer_hooked) {
            add_action('phpmailer_init', array($this, 'handle_phpmailer_init'));
            add_action('phpmailer_exception', array($this, 'handle_phpmailer_exception'));
            self::$phpmailer_hooked = true;
        }
        add_action('admin_notices', array($this, 'show_error_notice'));
    }

    public function handle_phpmailer_init($phpmailer) {
        file_put_contents($this->debug_log, "phpmailer_init FIRED\n", FILE_APPEND);
        // Log email details
        $to = $phpmailer->getToAddresses();
        $to_emails = array_map(function($item) { return $item[0]; }, $to);
        $subject = $phpmailer->Subject;
        $body = $phpmailer->Body;
        $headers = $phpmailer->getCustomHeaders();
        $settings = get_option('cybersmtp_smtp_settings', array());
        $provider = $settings['provider'] ?? 'smtp';
        $log_data = array(
            'to_email' => implode(',', $to_emails),
            'subject' => $subject,
            'body' => $body,
            'headers' => maybe_serialize($headers),
            'provider' => $provider,
            'status' => 'sent', // Assume sent unless error is caught elsewhere
            'response_data' => '',
        );
        file_put_contents($this->debug_log, "phpmailer_init log_email: " . print_r($log_data, true) . "\n", FILE_APPEND);
        $this->logger->log_email($log_data);
    }

    public function handle_phpmailer_exception($exception) {
        file_put_contents($this->debug_log, "PHPMailer Exception: " . $exception->getMessage() . "\n", FILE_APPEND);
        $this->last_error = $exception->getMessage();
    }

    public function show_error_notice() {
        if (!empty($this->last_error)) {
            echo '<div class="notice notice-error"><p>CyberSMTP Error: ' . esc_html($this->last_error) . '</p></div>';
            $this->last_error = '';
        }
    }

    public function send_test_email($to) {
        file_put_contents($this->debug_log, "send_test_email called: $to\n", FILE_APPEND);
        $subject = 'CyberSMTP Test Email';
        $message = 'This is a test email sent from CyberSMTP.';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }

    public function send_via_provider($email_data) {
        return $this->provider->send($email_data);
    }
    // Add methods for sending, configuring, and testing SMTP
} 