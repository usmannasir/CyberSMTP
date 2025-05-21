<?php
if (!defined('ABSPATH')) {
    exit;
}

class CyberSMTP_Email_Logger {
    protected $table;
    protected $debug_log;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cybersmtp_email_logs';
        $this->debug_log = __DIR__ . '/../cybersmtp-debug.log';
    }

    public function log_email($data) {
        global $wpdb;
        file_put_contents($this->debug_log, "log_email called: " . print_r($data, true) . "\n", FILE_APPEND);
        $wpdb->insert($this->table, array(
            'to_email' => $data['to_email'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'headers' => $data['headers'],
            'provider' => $data['provider'],
            'status' => $data['status'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'response_data' => $data['response_data'],
        ));
        if ($wpdb->last_error) {
            file_put_contents($this->debug_log, "DB error: " . $wpdb->last_error . "\n", FILE_APPEND);
        }
    }

    public function get_logs($limit = 50, $status = '', $recipient = '') {
        global $wpdb;
        $where = '1=1';
        $params = array();
        if ($status) {
            $where .= ' AND status = %s';
            $params[] = $status;
        }
        if ($recipient) {
            $where .= ' AND to_email = %s';
            $params[] = $recipient;
        }
        $sql = "SELECT * FROM {$this->table} WHERE $where ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }

    public function get_analytics() {
        global $wpdb;
        $sent = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'sent'");
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'error'");
        return array('sent' => $sent, 'failed' => $failed);
    }
} 