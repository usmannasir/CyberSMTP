<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

class CyberSMTP_Provider_CyberMail extends CyberSMTP_Provider_Abstract {
    protected $settings;
    protected $api_base = 'https://platform.cyberpersons.com/email/v1';

    public function __construct($settings = array()) {
        $this->settings = $settings;
    }

    public function send($email_data) {
        $mode = $this->settings['mode'] ?? 'api';

        if ($mode === 'smtp') {
            return $this->send_via_smtp($email_data);
        }

        return $this->send_via_api($email_data);
    }

    protected function send_via_api($email_data) {
        $api_key = $this->settings['api_key'] ?? '';
        if (empty($api_key)) {
            return ['success' => false, 'error' => 'CyberMail API key is not configured.'];
        }

        $to = $email_data['to'];
        if (is_array($to)) {
            $to = implode(',', $to);
        }

        $payload = [
            'from'    => $this->settings['from_email'] ?? '',
            'to'      => $to,
            'subject' => $email_data['subject'] ?? '',
        ];

        if (!empty($this->settings['from_name'])) {
            $payload['from_name'] = $this->settings['from_name'];
        }

        if (!empty($email_data['is_html']) && $email_data['is_html']) {
            $payload['html'] = $email_data['message'];
        } else {
            $payload['text'] = $email_data['message'];
        }

        if (!empty($email_data['from_email'])) {
            $payload['from'] = $email_data['from_email'];
            $payload['from_name'] = $email_data['from_name'] ?? '';
        }
        if (!empty($email_data['cc'])) {
            $payload['cc'] = implode(',', $email_data['cc']);
        }
        if (!empty($email_data['bcc'])) {
            $payload['bcc'] = implode(',', $email_data['bcc']);
        }
        if (!empty($email_data['reply_to'])) {
            $payload['reply_to'] = implode(',', $email_data['reply_to']);
        }

        if (!empty($email_data['attachments'])) {
            // Attachments not supported via API - fall back to SMTP
            return $this->send_via_smtp($email_data);
        }

        $response = wp_remote_post($this->api_base . '/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'CyberSMTP-WordPress/' . CYBERSMTP_VERSION,
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (($code === 200 || $code === 202) && !empty($body['success'])) {
            return [
                'success'    => true,
                'message_id' => $body['data']['message_id'] ?? '',
                'provider'   => 'cybermail',
            ];
        }

        $error = self::parse_api_error($body, $code);
        return ['success' => false, 'error' => $error];
    }

    protected function send_via_smtp($email_data) {
        $result = $this->send_smtp($email_data, $this->settings['host'] ?? 'mail.cyberpersons.com', 587, 'tls');
        if (!empty($result['success'])) {
            $result['provider'] = 'cybermail-smtp';
        }
        return $result;
    }

    /**
     * Get delivery status for a specific message (CyberMail exclusive).
     */
    public function get_message_status($message_id) {
        $api_key = $this->settings['api_key'] ?? '';
        if (empty($api_key) || empty($message_id)) {
            return null;
        }

        $response = wp_remote_get($this->api_base . '/messages/' . urlencode($message_id), [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent'    => 'CyberSMTP-WordPress/' . CYBERSMTP_VERSION,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['success'])) {
            return $body['data'] ?? null;
        }
        return null;
    }

    /**
     * Get account statistics (CyberMail exclusive).
     */
    public function get_account_stats() {
        $api_key = $this->settings['api_key'] ?? '';
        if (empty($api_key)) {
            return null;
        }

        $cached = get_transient('cybersmtp_cybermail_stats');
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($this->api_base . '/account/stats', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent'    => 'CyberSMTP-WordPress/' . CYBERSMTP_VERSION,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['success'])) {
            $stats = $body['data'] ?? null;
            if ($stats) {
                set_transient('cybersmtp_cybermail_stats', $stats, 300);
            }
            return $stats;
        }
        return null;
    }

    /**
     * Verify API key is valid.
     * Try stats endpoint first; if permission denied, try a lightweight send dry-run.
     */
    public function verify_connection() {
        $api_key = $this->settings['api_key'] ?? '';
        if (empty($api_key)) {
            return false;
        }

        // Try the stats endpoint
        $response = wp_remote_get($this->api_base . '/account/stats', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent'    => 'CyberSMTP-WordPress/' . CYBERSMTP_VERSION,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);

        // 200 = valid key with stats permission
        if ($code === 200) {
            return true;
        }

        // 401 = invalid key
        if ($code === 401) {
            return false;
        }

        // 403 = valid key but no stats permission — still connected
        if ($code === 403) {
            return true;
        }

        return false;
    }

    /**
     * Parse error from CyberMail API response.
     * API returns: {"success": false, "error": {"type": "...", "message": "..."}}
     */
    protected static function parse_api_error($body, $http_code = 0) {
        if (!is_array($body)) {
            return 'HTTP ' . $http_code;
        }

        $error = $body['error'] ?? null;

        // Nested error object: {"error": {"type": "...", "message": "..."}}
        if (is_array($error)) {
            $msg = $error['message'] ?? 'Unknown error';
            $type = $error['type'] ?? '';
            return $type ? "[$type] $msg" : $msg;
        }

        // Flat error string
        if (is_string($error) && $error !== '') {
            return $error;
        }

        return 'HTTP ' . $http_code;
    }

    public function get_settings_fields() {
        return [
            'api_key'    => ['label' => 'API Key', 'type' => 'password', 'required' => true],
            'from_email' => ['label' => 'From Email', 'type' => 'email', 'required' => true],
            'from_name'  => ['label' => 'From Name', 'type' => 'text'],
        ];
    }
}
