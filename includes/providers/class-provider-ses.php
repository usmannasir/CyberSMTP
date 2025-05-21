<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

use PHPMailer\PHPMailer\PHPMailer;
use GuzzleHttp\Client;
use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

class CyberSMTP_Provider_SES extends CyberSMTP_Provider_Abstract {
    protected $settings;

    public function __construct($settings = array()) {
        $this->settings = $settings;
    }

    public function send($email_data) {
        if (($this->settings['mode'] ?? 'smtp') === 'api' && !empty($this->settings['api_key']) && !empty($this->settings['api_secret']) && !empty($this->settings['region'])) {
            // Send via AWS SES API using AWS SDK
            try {
                $client = new SesClient([
                    'version' => '2010-12-01',
                    'region'  => $this->settings['region'],
                    'credentials' => [
                        'key'    => $this->settings['api_key'],
                        'secret' => $this->settings['api_secret'],
                    ],
                ]);
                $from = $this->settings['from_email'] ?? 'from@example.com';
                $to = is_array($email_data['to']) ? $email_data['to'] : [$email_data['to']];
                $subject = $email_data['subject'];
                $body = $email_data['message'];
                $isHtml = $email_data['is_html'] ?? false;
                $msg = [
                    'Source' => $from,
                    'Destination' => [
                        'ToAddresses' => $to,
                    ],
                    'Message' => [
                        'Subject' => [
                            'Data' => $subject,
                            'Charset' => 'UTF-8',
                        ],
                        'Body' => $isHtml ? [
                            'Html' => [
                                'Data' => $body,
                                'Charset' => 'UTF-8',
                            ],
                        ] : [
                            'Text' => [
                                'Data' => $body,
                                'Charset' => 'UTF-8',
                            ],
                        ],
                    ],
                ];
                $result = $client->sendEmail($msg);
                file_put_contents(dirname(__DIR__, 2) . '/cybersmtp-debug.log', "SES API sendEmail result: " . print_r($result, true) . "\n", FILE_APPEND);
                return ['success' => true];
            } catch (AwsException $e) {
                file_put_contents(dirname(__DIR__, 2) . '/cybersmtp-debug.log', "SES API AwsException: " . $e->getAwsErrorMessage() . "\n", FILE_APPEND);
                return ['success' => false, 'error' => $e->getAwsErrorMessage()];
            } catch (\Exception $e) {
                file_put_contents(dirname(__DIR__, 2) . '/cybersmtp-debug.log', "SES API exception: " . $e->getMessage() . "\n", FILE_APPEND);
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }
        // Fallback to SMTP
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->settings['host'] ?? 'email-smtp.' . ($this->settings['region'] ?? 'us-east-1') . '.amazonaws.com';
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