<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

use PHPMailer\PHPMailer\PHPMailer;
use GuzzleHttp\Client;
use SendGrid; // from sendgrid-php SDK
use SendGrid\Mail\Mail;

class CyberSMTP_Provider_SendGrid extends CyberSMTP_Provider_Abstract {
    protected $settings;

    public function __construct($settings = array()) {
        $this->settings = $settings;
    }

    public function send($email_data) {
        if (($this->settings['mode'] ?? 'smtp') === 'api' && !empty($this->settings['api_key'])) {
            // Send via SendGrid API using official SDK
            try {
                $email = new Mail();
                $from_email = $this->settings['from_email'] ?? 'from@example.com';
                $from_name = $this->settings['from_name'] ?? 'WordPress';
                $email->setFrom($from_email, $from_name);
                $email->setSubject($email_data['subject']);
                $to = is_array($email_data['to']) ? $email_data['to'] : [$email_data['to']];
                foreach ($to as $to_addr) {
                    $email->addTo($to_addr);
                }
                $email->addContent($email_data['is_html'] ? 'text/html' : 'text/plain', $email_data['message']);
                $sendgrid = new SendGrid($this->settings['api_key']);
                $response = $sendgrid->send($email);
                file_put_contents(dirname(__DIR__, 2) . '/cybersmtp-debug.log', "SendGrid API response: " . print_r([
                    'status' => $response->statusCode(),
                    'body' => $response->body(),
                    'headers' => $response->headers(),
                ], true) . "\n", FILE_APPEND);
                if ($response->statusCode() === 202) {
                    return ['success' => true];
                } else {
                    return ['success' => false, 'error' => 'SendGrid API error: ' . $response->statusCode() . ' - ' . $response->body()];
                }
            } catch (\Exception $e) {
                file_put_contents(dirname(__DIR__, 2) . '/cybersmtp-debug.log', "SendGrid API exception: " . $e->getMessage() . "\n", FILE_APPEND);
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }
        // Fallback to SMTP
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->settings['host'] ?? 'smtp.sendgrid.net';
            $mail->SMTPAuth = true;
            $mail->Username = $this->settings['username'] ?? 'apikey';
            $mail->Password = $this->settings['password'] ?? '';
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
        return array();
    }
} 