<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

class CyberSMTP_Provider_Brevo extends CyberSMTP_Provider_Abstract {

    public function send($email_data) {
        $mode = $this->settings['mode'] ?? 'smtp';

        if ($mode === 'api' && !empty($this->settings['api_key'])) {
            return $this->send_via_api($email_data);
        }

        return $this->send_smtp(
            $email_data,
            $this->settings['host'] ?? 'smtp-relay.brevo.com',
            $this->settings['port'] ?? 587,
            $this->settings['encryption'] ?? 'tls'
        );
    }

    protected function send_via_api($email_data) {
        $to = is_array($email_data['to']) ? $email_data['to'] : [$email_data['to']];
        $to_list = array_map(function($addr) {
            return ['email' => trim($addr)];
        }, $to);

        $payload = [
            'sender'  => [
                'name'  => $this->settings['from_name'] ?? get_bloginfo('name'),
                'email' => $this->settings['from_email'] ?? '',
            ],
            'to'      => $to_list,
            'subject' => $email_data['subject'] ?? '',
        ];

        if (!empty($email_data['is_html'])) {
            $payload['htmlContent'] = $email_data['message'] ?? '';
        } else {
            $payload['textContent'] = $email_data['message'] ?? '';
        }

        $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', [
            'headers' => [
                'api-key'      => $this->settings['api_key'],
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 201) {
            return ['success' => true, 'provider' => 'brevo'];
        }

        $body = wp_remote_retrieve_body($response);
        return ['success' => false, 'error' => 'Brevo API error (HTTP ' . $code . '): ' . $body];
    }
}
