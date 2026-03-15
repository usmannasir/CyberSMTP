<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class CyberSMTP_Provider_Abstract {
    protected $settings;

    public function __construct($settings = []) {
        $this->settings = $settings;
    }

    abstract public function send($email_data);

    public function get_settings_fields() {
        return [];
    }

    protected function get_phpmailer() {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }
        return new PHPMailer\PHPMailer\PHPMailer(true);
    }

    protected function send_smtp($email_data, $host, $port = 587, $encryption = 'tls') {
        $mail = $this->get_phpmailer();
        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->settings['username'] ?? '';
            $mail->Password   = $this->settings['password'] ?? '';
            $mail->Port       = intval($port);

            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure = '';
            }

            $mail->setFrom(
                $this->settings['from_email'] ?? '',
                $this->settings['from_name'] ?? get_bloginfo('name')
            );

            $to = is_array($email_data['to']) ? $email_data['to'] : [$email_data['to']];
            foreach ($to as $addr) {
                $mail->addAddress(trim($addr));
            }

            $mail->Subject = $email_data['subject'] ?? '';
            $mail->Body    = $email_data['message'] ?? '';
            $mail->isHTML(!empty($email_data['is_html']));

            if (!empty($email_data['headers'])) {
                foreach ($email_data['headers'] as $header) {
                    if (is_string($header) && stripos($header, 'Content-Type:') === false) {
                        $mail->addCustomHeader($header);
                    }
                }
            }

            if (!empty($email_data['attachments'])) {
                foreach ($email_data['attachments'] as $file) {
                    $mail->addAttachment($file);
                }
            }

            $mail->send();
            return ['success' => true, 'provider' => $this->settings['provider'] ?? 'smtp'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }
}
