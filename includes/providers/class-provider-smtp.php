<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

use PHPMailer\PHPMailer\PHPMailer;

class CyberSMTP_Provider_SMTP extends CyberSMTP_Provider_Abstract {
    protected $settings;

    public function __construct($settings = array()) {
        $this->settings = $settings;
    }

    public function send($email_data) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->settings['host'] ?? 'smtp.example.com';
            $mail->SMTPAuth = true;
            $mail->Username = $this->settings['username'] ?? 'user@example.com';
            $mail->Password = $this->settings['password'] ?? 'password';
            $mail->SMTPSecure = $this->settings['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->settings['port'] ?? 587;
            $mail->setFrom($this->settings['from_email'] ?? 'from@example.com', $this->settings['from_name'] ?? 'WordPress');
            $mail->addAddress($email_data['to']);
            $mail->Subject = $email_data['subject'];
            $mail->Body = $email_data['message'];
            $mail->isHTML($email_data['is_html'] ?? false);
            if (!empty($email_data['headers'])) {
                foreach ($email_data['headers'] as $header) {
                    $mail->addCustomHeader($header);
                }
            }
            $mail->send();
            return array('success' => true);
        } catch (\Exception $e) {
            return array('success' => false, 'error' => $mail->ErrorInfo);
        }
    }

    public function get_settings_fields() {
        // Return settings fields for the admin UI
        return array();
    }
} 