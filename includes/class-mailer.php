<?php
if (!defined('ABSPATH')) {
    exit;
}

class CyberSMTP_Mailer {
    protected $provider;
    protected $logger;

    public function __construct() {
        $settings = get_option('cybersmtp_smtp_settings', []);
        $provider_key = $settings['provider'] ?? 'smtp';

        $provider_classes = [
            'cybermail' => 'CyberSMTP_Provider_CyberMail',
            'smtp'      => 'CyberSMTP_Provider_SMTP',
            'ses'       => 'CyberSMTP_Provider_SES',
            'sendgrid'  => 'CyberSMTP_Provider_SendGrid',
            'mailgun'   => 'CyberSMTP_Provider_Mailgun',
            'brevo'     => 'CyberSMTP_Provider_Brevo',
        ];

        $class = $provider_classes[$provider_key] ?? 'CyberSMTP_Provider_SMTP';
        $this->provider = new $class($settings);
        $this->logger = new CyberSMTP_Email_Logger();
    }

    public function send_via_provider($email_data) {
        $result = $this->provider->send($email_data);

        $to = $email_data['to'];
        if (is_array($to)) {
            $to = implode(', ', $to);
        }

        $this->logger->log_email([
            'to_email'      => $to,
            'subject'       => $email_data['subject'] ?? '',
            'body'          => $email_data['message'] ?? '',
            'headers'       => $email_data['headers'] ?? '',
            'provider'      => $result['provider'] ?? (get_option('cybersmtp_smtp_settings', [])['provider'] ?? 'smtp'),
            'status'        => $result['success'] ? 'sent' : 'error',
            'message_id'    => $result['message_id'] ?? '',
            'error_message' => $result['error'] ?? '',
        ]);

        return $result;
    }

    public function send_test_email($to) {
        return wp_mail(
            $to,
            'CyberSMTP Test Email',
            '<p>This is a test email from CyberSMTP.</p>',
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
}
