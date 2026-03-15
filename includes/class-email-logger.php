<?php
if (!defined('ABSPATH')) {
    exit;
}

class CyberSMTP_Email_Logger {
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cybersmtp_email_logs';
    }

    public function log_email($data) {
        global $wpdb;

        $wpdb->insert($this->table, [
            'to_email'      => sanitize_text_field($data['to_email'] ?? ''),
            'subject'       => sanitize_text_field($data['subject'] ?? ''),
            'body'          => $data['body'] ?? '',
            'headers'       => maybe_serialize($data['headers'] ?? ''),
            'provider'      => sanitize_text_field($data['provider'] ?? ''),
            'status'        => sanitize_text_field($data['status'] ?? 'sent'),
            'message_id'    => sanitize_text_field($data['message_id'] ?? ''),
            'error_message' => $data['error_message'] ?? '',
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    public function get_logs($args = []) {
        global $wpdb;

        $defaults = [
            'per_page'  => 20,
            'page'      => 1,
            'status'    => '',
            'recipient' => '',
            'search'    => '',
            'date_from' => '',
            'date_to'   => '',
            'order'     => 'DESC',
        ];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $params = [];

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['recipient'])) {
            $where .= ' AND to_email LIKE %s';
            $params[] = '%' . $wpdb->esc_like($args['recipient']) . '%';
        }

        if (!empty($args['search'])) {
            $where .= ' AND (subject LIKE %s OR to_email LIKE %s)';
            $term = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($args['date_from'])) {
            $where .= ' AND created_at >= %s';
            $params[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where .= ' AND created_at <= %s';
            $params[] = $args['date_to'] . ' 23:59:59';
        }

        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $offset = max(0, ($args['page'] - 1) * $args['per_page']);

        // Get total
        $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE $where";
        $total = $params
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$params))
            : (int) $wpdb->get_var($count_sql);

        // Get rows
        $sql = "SELECT * FROM {$this->table} WHERE $where ORDER BY created_at $order LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return [
            'logs'       => $rows ?: [],
            'total'      => $total,
            'page'       => $args['page'],
            'per_page'   => $args['per_page'],
            'total_pages'=> ceil($total / $args['per_page']),
        ];
    }

    public function get_analytics() {
        global $wpdb;

        $sent = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'sent'");
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'error'");
        $today_sent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = 'sent' AND created_at >= %s",
            current_time('Y-m-d') . ' 00:00:00'
        ));

        return [
            'sent'       => $sent,
            'failed'     => $failed,
            'total'      => $sent + $failed,
            'today_sent' => $today_sent,
            'rate'       => ($sent + $failed) > 0 ? round(($sent / ($sent + $failed)) * 100, 1) : 0,
        ];
    }

    public function get_chart_data($days = 7) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, status, COUNT(*) as count
             FROM {$this->table}
             WHERE created_at >= DATE_SUB(%s, INTERVAL %d DAY)
             GROUP BY DATE(created_at), status
             ORDER BY date ASC",
            current_time('mysql'),
            $days
        ), ARRAY_A);

        $labels = [];
        $sent = [];
        $failed = [];

        // Fill all days
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days", strtotime(current_time('Y-m-d'))));
            $labels[] = $date;
            $sent[$date] = 0;
            $failed[$date] = 0;
        }

        foreach ($results as $row) {
            $d = $row['date'];
            if ($row['status'] === 'sent') {
                $sent[$d] = (int) $row['count'];
            } else {
                $failed[$d] = (int) $row['count'];
            }
        }

        return [
            'labels' => $labels,
            'sent'   => array_values($sent),
            'failed' => array_values($failed),
        ];
    }

    public function cleanup($days = 90) {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}
