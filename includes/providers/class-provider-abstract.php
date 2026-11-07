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

            $mail->CharSet = $email_data['charset'] ?? 'UTF-8';

            // Caller's From (wp_mail header / wp_mail_from filter) wins over the settings default.
            $mail->setFrom(
                !empty($email_data['from_email']) ? $email_data['from_email'] : ($this->settings['from_email'] ?? ''),
                $email_data['from_name'] ?? ($this->settings['from_name'] ?? get_bloginfo('name'))
            );

            $to = is_array($email_data['to']) ? $email_data['to'] : cybersmtp_split_addresses($email_data['to']);
            foreach ($to as $addr) {
                $parts = cybersmtp_address_parts($addr);
                $mail->addAddress($parts[0], $parts[1]);
            }
            foreach ($email_data['reply_to'] ?? [] as $addr) {
                $parts = cybersmtp_address_parts($addr);
                $mail->addReplyTo($parts[0], $parts[1]);
            }
            foreach ($email_data['cc'] ?? [] as $addr) {
                $parts = cybersmtp_address_parts($addr);
                $mail->addCC($parts[0], $parts[1]);
            }
            foreach ($email_data['bcc'] ?? [] as $addr) {
                $parts = cybersmtp_address_parts($addr);
                $mail->addBCC($parts[0], $parts[1]);
            }

            $mail->Subject = $email_data['subject'] ?? '';
            $mail->Body    = $email_data['message'] ?? '';
            $mail->isHTML(!empty($email_data['is_html']));
            if (!empty($email_data['content_type'])) {
                $mail->ContentType = $email_data['content_type'];
            }

            // Only non-structured headers remain here (see cybersmtp_parse_headers()).
            foreach ($email_data['headers'] ?? [] as $header) {
                $mail->addCustomHeader($header);
            }

            if (!empty($email_data['attachments'])) {
                foreach ($email_data['attachments'] as $file) {
                    $mail->addAttachment($file);
                }
            }

            do_action_ref_array('phpmailer_init', [&$mail]);
            $mail->send();
            return ['success' => true, 'provider' => $this->settings['provider'] ?? 'smtp'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
        }
    }
}
