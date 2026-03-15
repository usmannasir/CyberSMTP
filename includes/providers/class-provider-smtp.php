<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

class CyberSMTP_Provider_SMTP extends CyberSMTP_Provider_Abstract {

    public function send($email_data) {
        return $this->send_smtp(
            $email_data,
            $this->settings['host'] ?? 'localhost',
            $this->settings['port'] ?? 587,
            $this->settings['encryption'] ?? 'tls'
        );
    }
}
