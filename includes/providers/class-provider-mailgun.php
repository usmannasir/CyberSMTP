<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

use PHPMailer\PHPMailer\PHPMailer;
use GuzzleHttp\Client;
use Mailgun\Mailgun;

class CyberSMTP_Provider_Mailgun extends CyberSMTP_Provider_Abstract {
    protected $settings;

    public function __construct($settings = array()) {
        $this->settings = $settings;
    }

    public function send($email_data) {
        if (($this->settings['mode'] ?? 'smtp') === 'api' && !empty($this->settings['api_key']) && !empty($this->settings['domain'])) {
            // Send via Mailgun API using official SDK
            try {
                $mg = Mailgun::create($this->settings['api_key']);
                $from = ($this->settings['from_name'] ?? 'WordPress') . ' <' . ($this->settings['from_email'] ?? 'from@example.com') . '>';
                $to = is_array($email_data['to']) ? $email_data['to'] : [$email_data['to']];
                $params = [
                    'from'    => $from,
                    'to'      => implode(',', $to),
                    'subject' => $email_data['subject'],
                ];
                if ($email_data['is_html']) {
                    $params['html'] = $email_data['message'];
                } else {
                    $params['text'] = $email_data['message'];
                }
                $response = $mg->messages()->send($this->settings['domain'], $params);
                file_put_contents(dirname(__DIR__, 2) . '/cybersmtp-debug.log', "Mailgun API response: " . print_r($response, true) . "\n", FILE_APPEND);
                if ($response->getId()) {
                    return ['success' => true];
                } else {
                    return ['success' => false, 'error' => 'Mailgun API error: ' . print_r($response, true)];
                }
            } catch (\Exception $e) {
                file_put_contents(dirname(__DIR__, 2) . '/cybersmtp-debug.log', "Mailgun API exception: " . $e->getMessage() . "\n", FILE_APPEND);
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }
        // Fallback to SMTP
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->settings['host'] ?? 'smtp.mailgun.org';
            $mail->SMTPAuth = true;
            $mail->Username = $this->settings['username'] ?? '';
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