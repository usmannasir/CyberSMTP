<?php
if (!defined('ABSPATH')) {
    exit;
}

class CyberSMTP_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('CyberSMTP', 'cyberpanel.net'),
            __('CyberSMTP', 'cyberpanel.net'),
            'manage_options',
            'cybersmtp',
            array($this, 'render_page'),
            'dashicons-email-alt',
            56
        );
    }

    public function register_settings() {
        register_setting('cybersmtp_settings_group', 'cybersmtp_smtp_settings');
    }

    public function enqueue_admin_styles($hook) {
        // Only load on our plugin page
        if (isset($_GET['page']) && $_GET['page'] === 'cybersmtp') {
            wp_enqueue_style('cybersmtp-admin', plugin_dir_url(__FILE__) . 'assets/cybersmtp-admin.css', array(), '1.0');
        }
    }

    public function render_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        echo '<div class="wrap">';
        // Modern header
        echo '<div class="cybersmtp-header">';
        echo '<div class="cybersmtp-logo"><img src="https://cyberpanel.net/wp-content/uploads/2025/04/cyberpanel-logo-icon_only.png" alt="CyberPanel Logo" style="width: 40px; height: 40px; object-fit: contain;" /></div>';
        echo '<div>';
        echo '<div class="cybersmtp-welcome">Welcome to CyberSMTP</div>';
        echo '<div style="color:#666;">Configure your email provider and settings below. Test your email score for free, <a href="https://platform.cyberpersons.com/MailTester/MailTester" target="_blank" rel="noopener">click here</a>.</div>';
        echo '</div>';
        echo '</div>';
        // Tabs
        echo '<nav class="nav-tab-wrapper">';
        echo '<a href="?page=cybersmtp&tab=settings" class="nav-tab' . ($tab === 'settings' ? ' nav-tab-active' : '') . '">⚙️ Settings</a>';
        echo '<a href="?page=cybersmtp&tab=test" class="nav-tab' . ($tab === 'test' ? ' nav-tab-active' : '') . '">✉️ Test Email</a>';
        echo '<a href="?page=cybersmtp&tab=logs" class="nav-tab' . ($tab === 'logs' ? ' nav-tab-active' : '') . '">📋 Logs</a>';
        echo '</nav>';
        if ($tab === 'logs') {
            $this->render_logs_page();
        } elseif ($tab === 'test') {
            $this->render_test_email_page();
        } else {
            $this->render_settings_page();
        }
        // Footer
        echo '<div class="cybersmtp-footer">CyberSMTP &copy; ' . date('Y') . ' &mdash; <a href="https://cyberpanel.net/" target="_blank">Documentation</a></div>';
        echo '</div>';
    }

    public function render_settings_page() {
        $settings = get_option('cybersmtp_smtp_settings', array());
        $provider = $settings['provider'] ?? 'smtp';
        $mode = $settings['mode'] ?? 'smtp';
        $providers = [
            'smtp' => [
                'label' => 'Generic SMTP',
                'icon' => 'smtp.svg',
            ],
            'ses' => [
                'label' => 'Amazon SES',
                'icon' => 'amazonses.svg',
            ],
            'sendgrid' => [
                'label' => 'SendGrid',
                'icon' => 'sendgrid.svg',
            ],
            'mailgun' => [
                'label' => 'Mailgun',
                'icon' => 'mailgun.svg',
            ],
            'brevo' => [
                'label' => 'Brevo',
                'icon' => 'brevo.svg',
            ],
        ];
        echo '<form method="post" action="options.php" id="cybersmtp-settings-form">';
        settings_fields('cybersmtp_settings_group');
        // Provider Selection Section
        echo '<div class="cybersmtp-section"><h2>Provider Selection</h2>';
        echo '<div class="cybersmtp-provider-grid">';
        foreach ($providers as $key => $data) {
            $selected = ($provider === $key) ? 'selected' : '';
            echo '<div class="cybersmtp-provider-card ' . $selected . '" data-provider="' . esc_attr($key) . '">';
            echo '<div class="cybersmtp-provider-logo">';
            $icon_path = __DIR__ . '/assets/icons/' . $data['icon'];
            if (file_exists($icon_path)) {
                echo file_get_contents($icon_path);
            }
            echo '</div>';
            echo '<div class="cybersmtp-provider-label">' . esc_html($data['label']) . '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<input type="hidden" name="cybersmtp_smtp_settings[provider]" id="cybersmtp-provider-select" value="' . esc_attr($provider) . '" />';
        echo '</div>';
        // SMTP/API Settings Section
        echo '<div class="cybersmtp-section"><h2>SMTP/API Settings</h2>';
        echo '<table class="form-table">';
        echo '<tr class="cybersmtp-field cybersmtp-field-mode"><th scope="row">Mode <span class="cybersmtp-help" title="Choose SMTP for standard email sending or API for direct integration with supported providers.">?</span></th><td>
            <select name="cybersmtp_smtp_settings[mode]" id="cybersmtp-mode-select">
                <option value="smtp" ' . selected($mode, 'smtp', false) . '>SMTP</option>
                <option value="api" ' . selected($mode, 'api', false) . '>API</option>
            </select>
            <span class="description">(For SendGrid/Mailgun/SES/Brevo only)</span>
        </td></tr>';
        echo '<tr class="cybersmtp-field cybersmtp-field-api-key"><th scope="row">API Key <span class="cybersmtp-help" title="Your API key from the selected provider. Required for API mode.">?</span></th><td><input type="text" name="cybersmtp_smtp_settings[api_key]" value="' . esc_attr($settings['api_key'] ?? '') . '" class="regular-text"></td></tr>';
        echo '<tr class="cybersmtp-field cybersmtp-field-api-secret"><th scope="row">API Secret <span class="cybersmtp-help" title="Some providers require both an API key and secret.">?</span></th><td><input type="text" name="cybersmtp_smtp_settings[api_secret]" value="' . esc_attr($settings['api_secret'] ?? '') . '" class="regular-text"></td></tr>';
        echo '<tr class="cybersmtp-field cybersmtp-field-domain"><th scope="row">Mailgun Domain <span class="cybersmtp-help" title="Your Mailgun sending domain, e.g. mg.example.com">?</span></th><td><input type="text" name="cybersmtp_smtp_settings[domain]" value="' . esc_attr($settings['domain'] ?? '') . '" class="regular-text"><span class="description">(e.g. mg.example.com)</span></td></tr>';
        echo '<tr class="cybersmtp-field cybersmtp-field-host"><th scope="row">SMTP Host <span class="cybersmtp-help" title="The SMTP server address provided by your email provider.">?</span></th><td><input type="text" name="cybersmtp_smtp_settings[host]" value="' . esc_attr($settings['host'] ?? '') . '" class="regular-text"></td></tr>';
        echo '<tr class="cybersmtp-field cybersmtp-field-port"><th scope="row">SMTP Port <span class="cybersmtp-help" title="The port number for your SMTP server, usually 587 (TLS) or 465 (SSL).">?</span></th><td><input type="number" name="cybersmtp_smtp_settings[port]" value="' . esc_attr($settings['port'] ?? '587') . '" class="small-text"></td></tr>';
        echo '<tr class="cybersmtp-field cybersmtp-field-username"><th scope="row">Username <span class="cybersmtp-help" title="Your SMTP or API username, often your email address.">?</span></th><td><input type="text" name="cybersmtp_smtp_settings[username]" value="' . esc_attr($settings['username'] ?? '') . '" class="regular-text"></td></tr>';
        echo '<tr class="cybersmtp-field cybersmtp-field-password"><th scope="row">Password <span class="cybersmtp-help" title="Your SMTP or API password/secret.">?</span></th><td><input type="password" name="cybersmtp_smtp_settings[password]" value="' . esc_attr($settings['password'] ?? '') . '" class="regular-text"></td></tr>';
        echo '<tr class="cybersmtp-field cybersmtp-field-encryption"><th scope="row">Encryption <span class="cybersmtp-help" title="Choose TLS or SSL for secure connections. Use None only if your provider instructs.">?</span></th><td>
            <select name="cybersmtp_smtp_settings[encryption]">'
                . '<option value="tls" ' . selected($settings['encryption'] ?? '', 'tls', false) . '>TLS</option>'
                . '<option value="ssl" ' . selected($settings['encryption'] ?? '', 'ssl', false) . '>SSL</option>'
                . '<option value="" ' . selected($settings['encryption'] ?? '', '', false) . '>None</option>'
            . '</select>
        </td></tr>';
        echo '<tr class="cybersmtp-field cybersmtp-field-region"><th scope="row">SES Region <span class="cybersmtp-help" title="The AWS region for your SES account, e.g. us-east-1.">?</span></th><td><input type="text" name="cybersmtp_smtp_settings[region]" value="' . esc_attr($settings['region'] ?? '') . '" class="regular-text"><span class="description">(For Amazon SES only, e.g. us-east-1)</span></td></tr>';
        echo '</table>';
        echo '</div>';
        // Sender Details Section
        echo '<div class="cybersmtp-section"><h2>Sender Details</h2>';
        echo '<table class="form-table">';
        echo '<tr><th scope="row">From Email <span class="cybersmtp-help" title="The email address that will appear as the sender.">?</span></th><td><input type="email" name="cybersmtp_smtp_settings[from_email]" value="' . esc_attr($settings['from_email'] ?? '') . '" class="regular-text" required></td></tr>';
        echo '<tr><th scope="row">From Name <span class="cybersmtp-help" title="The name that will appear as the sender.">?</span></th><td><input type="text" name="cybersmtp_smtp_settings[from_name]" value="' . esc_attr($settings['from_name'] ?? '') . '" class="regular-text"></td></tr>';
        echo '</table>';
        echo '</div>';
        echo '<div style="display:flex;gap:12px;align-items:center;margin-top:16px;">';
        echo '<input type="submit" class="button button-primary" value="Save Settings" />';
        echo '</form>';
        // Add JS for provider card selection and field updates
        echo "<script>
(function($){
    $(document).on('click', '.cybersmtp-provider-card', function() {
        $('.cybersmtp-provider-card').removeClass('selected');
        $(this).addClass('selected');
        var provider = $(this).data('provider');
        $('#cybersmtp-provider-select').val(provider).trigger('change');
    });
    function updateFields() {
        var provider = $('#cybersmtp-provider-select').val();
        var mode = $('#cybersmtp-mode-select').val();
        $('.cybersmtp-field').hide();
        // Show/hide fields for each provider/mode
        if (provider === 'ses') {
            $('.cybersmtp-field-mode').show();
            if (mode === 'api') {
                $('.cybersmtp-field-api-key, .cybersmtp-field-api-secret, .cybersmtp-field-region').show();
            } else {
                $('.cybersmtp-field-host, .cybersmtp-field-port, .cybersmtp-field-username, .cybersmtp-field-password, .cybersmtp-field-encryption, .cybersmtp-field-region').show();
            }
        } else if (provider === 'sendgrid') {
            $('.cybersmtp-field-mode').show();
            if (mode === 'api') {
                $('.cybersmtp-field-api-key').show();
            } else {
                $('.cybersmtp-field-host, .cybersmtp-field-port, .cybersmtp-field-username, .cybersmtp-field-password, .cybersmtp-field-encryption').show();
            }
        } else if (provider === 'mailgun') {
            $('.cybersmtp-field-mode').show();
            if (mode === 'api') {
                $('.cybersmtp-field-api-key, .cybersmtp-field-domain').show();
            } else {
                $('.cybersmtp-field-host, .cybersmtp-field-port, .cybersmtp-field-username, .cybersmtp-field-password, .cybersmtp-field-encryption').show();
            }
        } else if (provider === 'brevo') {
            $('.cybersmtp-field-mode').show();
            if (mode === 'api') {
                $('.cybersmtp-field-api-key').show();
            } else {
                $('.cybersmtp-field-host, .cybersmtp-field-port, .cybersmtp-field-username, .cybersmtp-field-password, .cybersmtp-field-encryption').show();
            }
        } else {
            // Generic SMTP
            $('.cybersmtp-field-host, .cybersmtp-field-port, .cybersmtp-field-username, .cybersmtp-field-password, .cybersmtp-field-encryption').show();
        }
        if (provider === 'smtp') {
            $('.cybersmtp-field-host label').text('SMTP Host');
        } else if (provider === 'ses') {
            $('.cybersmtp-field-host label').text('SES SMTP Host');
        } else if (provider === 'sendgrid') {
            $('.cybersmtp-field-host label').text('SendGrid SMTP Host');
        } else if (provider === 'mailgun') {
            $('.cybersmtp-field-host label').text('Mailgun SMTP Host');
        } else if (provider === 'brevo') {
            $('.cybersmtp-field-host label').text('Brevo SMTP Host');
        }
    }
    $(document).ready(function(){
        updateFields();
        $('#cybersmtp-provider-select, #cybersmtp-mode-select').on('change', updateFields);
        // Tooltip
        $('.cybersmtp-help').hover(function(){
            $(this).addClass('active');
        }, function(){
            $(this).removeClass('active');
        });
    });
})(jQuery);
</script>";
    }

    public function render_test_email_page() {
        $test_email_result = '';
        if (isset($_POST['cybersmtp_test_email'])) {
            file_put_contents(dirname(__DIR__) . '/cybersmtp-debug.log', "ADMIN: Test email POST received\n", FILE_APPEND);
            if (!check_admin_referer('cybersmtp_test_email_action', 'cybersmtp_test_email_nonce')) {
                file_put_contents(dirname(__DIR__) . '/cybersmtp-debug.log', "ADMIN: Nonce check failed\n", FILE_APPEND);
            } else {
                $test_email = sanitize_email($_POST['cybersmtp_test_email_address']);
                if (is_email($test_email)) {
                    file_put_contents(dirname(__DIR__) . '/cybersmtp-debug.log', "ADMIN: Sending test email to $test_email\n", FILE_APPEND);
                    $mailer = new CyberSMTP_Mailer();
                    $mailer->send_test_email($test_email);
                    $test_email_result = '<div class="notice notice-success"><p>Test email sent to ' . esc_html($test_email) . ' (check your inbox and logs tab).</p></div>';
                } else {
                    file_put_contents(dirname(__DIR__) . '/cybersmtp-debug.log', "ADMIN: Invalid email address: $test_email\n", FILE_APPEND);
                    $test_email_result = '<div class="notice notice-error"><p>Invalid email address.</p></div>';
                }
            }
        }
        echo '<div style="max-width:500px;margin:32px auto 0 auto;background:#fff;padding:32px 24px 24px 24px;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,0.04);">';
        echo '<h2>Send Test Email</h2>';
        echo $test_email_result;
        echo '<form method="post">';
        wp_nonce_field('cybersmtp_test_email_action', 'cybersmtp_test_email_nonce');
        echo '<input type="email" name="cybersmtp_test_email_address" placeholder="Recipient Email" required style="width:70%;margin-right:10px;" />';
        echo '<input type="submit" name="cybersmtp_test_email" class="button button-secondary" value="Send Test Email" />';
        echo '</form>';
        echo '</div>';
    }

    public function render_logs_page() {
        require_once dirname(__FILE__) . '/../includes/class-email-logger.php';
        $logger = new CyberSMTP_Email_Logger();

        // Filtering
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $recipient_filter = isset($_GET['recipient']) ? sanitize_email($_GET['recipient']) : '';
        $logs = $logger->get_logs(100, $status_filter, $recipient_filter);

        // Analytics
        $analytics = $logger->get_analytics();
        $sent_count = $analytics['sent'] ?? 0;
        $failed_count = $analytics['failed'] ?? 0;
        $total = $sent_count + $failed_count;
        $success_rate = $total > 0 ? round(($sent_count / $total) * 100, 1) : 0;
        echo '<h2>Email Logs</h2>';
        echo '<div style="margin-bottom:16px;">';
        echo '<strong>Sent:</strong> ' . esc_html($sent_count) . ' | ';
        echo '<strong>Failed:</strong> ' . esc_html($failed_count) . ' | ';
        echo '<strong>Success Rate:</strong> ' . esc_html($success_rate) . '%';
        echo '</div>';
        // Filter form
        echo '<form method="get" style="margin-bottom:16px;">';
        echo '<input type="hidden" name="page" value="cybersmtp">';
        echo '<input type="hidden" name="tab" value="logs">';
        echo 'Status: <select name="status"><option value="">All</option><option value="sent"' . selected($status_filter, 'sent', false) . '>Sent</option><option value="error"' . selected($status_filter, 'error', false) . '>Failed</option></select> ';
        echo 'Recipient: <input type="email" name="recipient" value="' . esc_attr($recipient_filter) . '" placeholder="user@example.com"> ';
        echo '<input type="submit" class="button" value="Filter">';
        echo '</form>';
        // Logs table
        echo '<table class="widefat fixed striped"><thead><tr>';
        echo '<th>Date</th><th>To</th><th>Subject</th><th>Status</th><th>Error</th>';
        echo '</tr></thead><tbody>';
        if ($logs) {
            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td>' . esc_html($log['created_at']) . '</td>';
                echo '<td>' . esc_html($log['to_email']) . '</td>';
                echo '<td>' . esc_html($log['subject']) . '</td>';
                echo '<td>' . esc_html($log['status']) . '</td>';
                echo '<td>' . esc_html($log['response_data']) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="5">No logs found.</td></tr>';
        }
        echo '</tbody></table>';
    }
} 