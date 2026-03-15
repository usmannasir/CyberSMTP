<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

class CyberSMTP_Provider_Mailgun extends CyberSMTP_Provider_Abstract {

    public function send($email_data) {
        $mode = $this->settings['mode'] ?? 'smtp';

        if ($mode === 'api' && !empty($this->settings['api_key']) && !empty($this->settings['domain'])) {
            return $this->send_via_api($email_data);
        }

        return $this->send_smtp(
            $email_data,
            $this->settings['host'] ?? 'smtp.mailgun.org',
            $this->settings['port'] ?? 587,
            $this->settings['encryption'] ?? 'tls'
        );
    }

    protected function send_via_api($email_data) {
        $domain = $this->settings['domain'];
        $from_name = $this->settings['from_name'] ?? get_bloginfo('name');
        $from_email = $this->settings['from_email'] ?? '';

        $to = is_array($email_data['to']) ? $email_data['to'] : [$email_data['to']];

        $body = [
            'from'    => "$from_name <$from_email>",
            'to'      => implode(',', $to),
            'subject' => $email_data['subject'] ?? '',
        ];

        if (!empty($email_data['is_html'])) {
            $body['html'] = $email_data['message'] ?? '';
        } else {
            $body['text'] = $email_data['message'] ?? '';
        }

        $response = wp_remote_post("https://api.mailgun.net/v3/{$domain}/messages", [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode('api:' . $this->settings['api_key']),
            ],
            'body'    => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return ['success' => true, 'provider' => 'mailgun'];
        }

        $resp_body = wp_remote_retrieve_body($response);
        return ['success' => false, 'error' => 'Mailgun API error (HTTP ' . $code . '): ' . $resp_body];
    }
}
