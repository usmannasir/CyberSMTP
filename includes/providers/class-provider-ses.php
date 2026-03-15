<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-provider-abstract.php';

class CyberSMTP_Provider_SES extends CyberSMTP_Provider_Abstract {

    public function send($email_data) {
        $mode = $this->settings['mode'] ?? 'smtp';

        if ($mode === 'api' && !empty($this->settings['api_key']) && !empty($this->settings['api_secret'])) {
            return $this->send_via_api($email_data);
        }

        $region = $this->settings['region'] ?? 'us-east-1';
        return $this->send_smtp(
            $email_data,
            $this->settings['host'] ?? "email-smtp.{$region}.amazonaws.com",
            $this->settings['port'] ?? 587,
            $this->settings['encryption'] ?? 'tls'
        );
    }

    protected function send_via_api($email_data) {
        $region = $this->settings['region'] ?? 'us-east-1';
        $access_key = $this->settings['api_key'];
        $secret_key = $this->settings['api_secret'];

        $to = is_array($email_data['to']) ? $email_data['to'] : [$email_data['to']];
        $from = $this->settings['from_email'] ?? '';
        $subject = $email_data['subject'] ?? '';
        $body_content = $email_data['message'] ?? '';
        $is_html = !empty($email_data['is_html']);

        // SES v2 SendEmail via REST
        $endpoint = "https://email.{$region}.amazonaws.com/v2/email/outbound-emails";

        $payload = [
            'FromEmailAddress' => $from,
            'Destination' => [
                'ToAddresses' => $to,
            ],
            'Content' => [
                'Simple' => [
                    'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                    'Body' => $is_html
                        ? ['Html' => ['Data' => $body_content, 'Charset' => 'UTF-8']]
                        : ['Text' => ['Data' => $body_content, 'Charset' => 'UTF-8']],
                ],
            ],
        ];

        $json_payload = wp_json_encode($payload);
        $date = gmdate('Ymd\THis\Z');
        $date_short = gmdate('Ymd');
        $host = "email.{$region}.amazonaws.com";

        // AWS Signature V4
        $canonical_uri = '/v2/email/outbound-emails';
        $canonical_querystring = '';
        $canonical_headers = "content-type:application/json\nhost:{$host}\nx-amz-date:{$date}\n";
        $signed_headers = 'content-type;host;x-amz-date';
        $payload_hash = hash('sha256', $json_payload);

        $canonical_request = "POST\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";

        $scope = "{$date_short}/{$region}/ses/aws4_request";
        $string_to_sign = "AWS4-HMAC-SHA256\n{$date}\n{$scope}\n" . hash('sha256', $canonical_request);

        $signing_key = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', 'ses',
                hash_hmac('sha256', $region,
                    hash_hmac('sha256', $date_short, 'AWS4' . $secret_key, true),
                true),
            true),
        true);

        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        $auth_header = "AWS4-HMAC-SHA256 Credential={$access_key}/{$scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Host'          => $host,
                'X-Amz-Date'    => $date,
                'Authorization' => $auth_header,
            ],
            'body'    => $json_payload,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return ['success' => true, 'provider' => 'ses'];
        }

        $body = wp_remote_retrieve_body($response);
        return ['success' => false, 'error' => 'SES API error (HTTP ' . $code . '): ' . $body];
    }
}
