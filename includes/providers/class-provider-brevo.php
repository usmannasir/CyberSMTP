<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

use PHPMailer\PHPMailer\PHPMailer;
use GuzzleHttp\Client;
use Brevo\Client\Configuration;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Model\SendSmtpEmail;

class CyberSMTP_Provider_Brevo extends CyberSMTP_Provider_Abstract {
    protected $settings;

    public function __construct($settings = array()) {
        $this->settings = $settings;
    }

    public function send($email_data) {
        if (($this->settings['mode'] ?? 'smtp') === 'api' && !empty($this->settings['api_key'])) {
            // Send via Brevo API using official SDK
            try {
                $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $this->settings['api_key']);
                $apiInstance = new TransactionalEmailsApi(null, $config);
                $from_email = $this->settings['from_email'] ?? 'from@example.com';
                $from_name = $this->settings['from_name'] ?? 'WordPress';
                $to = is_array($email_data['to']) ? $email_data['to'] : [$email_data['to']];
                $to_arr = array_map(function($addr) { return ['email' => $addr]; }, $to);
                $sendSmtpEmail = new SendSmtpEmail([
                    'subject' => $email_data['subject'],
                    $email_data['is_html'] ? 'htmlContent' : 'textContent' => $email_data['message'],
                    'sender' => ['name' => $from_name, 'email' => $from_email],
                    'to' => $to_arr,
                ]);
                $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
                file_put_contents(dirname(__DIR__, 2) . '/cybersmtp-debug.log', "Brevo API response: " . print_r($result, true) . "\n", FILE_APPEND);
                if (isset($result['messageId']) || isset($result->messageId)) {
                    return ['success' => true];
                } else {
                    return ['success' => false, 'error' => 'Brevo API error: ' . print_r($result, true)];
                }
            } catch (\Exception $e) {
                file_put_contents(dirname(__DIR__, 2) . '/cybersmtp-debug.log', "Brevo API exception: " . $e->getMessage() . "\n", FILE_APPEND);
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }
        // Fallback to SMTP
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->settings['host'] ?? 'smtp-relay.brevo.com';
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