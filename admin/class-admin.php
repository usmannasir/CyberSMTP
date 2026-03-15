<?php
if (!defined('ABSPATH')) {
    exit;
}

class CyberSMTP_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('in_admin_header', [$this, 'hide_admin_notices']);
    }

    public function hide_admin_notices() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'cybersmtp') !== false) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'CyberSMTP',
            'CyberSMTP',
            'manage_options',
            'cybersmtp',
            [$this, 'render_page'],
            'dashicons-email-alt',
            56
        );
    }

    public function register_settings() {
        register_setting('cybersmtp_settings_group', 'cybersmtp_smtp_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    public function sanitize_settings($input) {
        $clean = [];
        $fields = ['provider', 'mode', 'api_key', 'api_secret', 'host', 'port',
                    'username', 'password', 'encryption', 'region', 'domain',
                    'from_email', 'from_name'];

        foreach ($fields as $f) {
            if (isset($input[$f])) {
                $clean[$f] = $f === 'from_email'
                    ? sanitize_email($input[$f])
                    : sanitize_text_field($input[$f]);
            }
        }

        // Encrypt sensitive fields
        foreach (['api_key', 'api_secret', 'password'] as $secret) {
            if (!empty($clean[$secret]) && strpos($clean[$secret], '••••') === false) {
                $clean[$secret . '_encrypted'] = self::encrypt($clean[$secret]);
            } else {
                // Keep existing encrypted value
                $old = get_option('cybersmtp_smtp_settings', []);
                if (!empty($old[$secret . '_encrypted'])) {
                    $clean[$secret . '_encrypted'] = $old[$secret . '_encrypted'];
                    $clean[$secret] = self::decrypt($old[$secret . '_encrypted']);
                }
            }
        }

        delete_transient('cybersmtp_cybermail_stats');

        return $clean;
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'cybersmtp') === false) {
            return;
        }

        wp_enqueue_style('cybersmtp-admin', CYBERSMTP_URL . 'admin/assets/cybersmtp-admin.css', [], CYBERSMTP_VERSION);

        // Chart.js for dashboard (CDN — lightweight, cached)
        $tab = sanitize_text_field($_GET['tab'] ?? 'dashboard');
        if ($tab === 'dashboard' || $tab === '') {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', [], '4.4.7', true);
            wp_enqueue_script('cybersmtp-admin', CYBERSMTP_URL . 'admin/assets/cybersmtp-admin.js', ['jquery', 'chartjs'], CYBERSMTP_VERSION, true);
        } else {
            wp_enqueue_script('cybersmtp-admin', CYBERSMTP_URL . 'admin/assets/cybersmtp-admin.js', ['jquery'], CYBERSMTP_VERSION, true);
        }

        wp_localize_script('cybersmtp-admin', 'cybersmtp', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('cybersmtp_nonce'),
        ]);
    }

    public function render_page() {
        $tab = sanitize_text_field($_GET['tab'] ?? 'dashboard');
        $tabs = [
            'dashboard'      => 'Dashboard',
            'settings'       => 'Settings',
            'logs'           => 'Email Logs',
            'deliverability' => 'Deliverability',
            'test'           => 'Test Email',
        ];

        $settings = get_option('cybersmtp_smtp_settings', []);
        $provider = $settings['provider'] ?? '';
        $is_configured = $provider && (!empty($settings['api_key']) || !empty($settings['host']));
        $is_cybermail = $provider === 'cybermail';
        ?>
        <div class="wrap cybersmtp-wrap">
            <!-- Header -->
            <div class="cybersmtp-header">
                <div class="cybersmtp-header-left">
                    <img src="https://cyberpanel.net/wp-content/uploads/2026/03/cyberpanel-logo-icon_only.png"
                         alt="CyberPanel" class="cybersmtp-logo-img" width="36" height="36">
                    <div>
                        <h1 class="cybersmtp-title">CyberSMTP</h1>
                        <span class="cybersmtp-version">v<?php echo esc_html(CYBERSMTP_VERSION); ?></span>
                    </div>
                </div>
                <div class="cybersmtp-header-right">
                    <?php if ($is_configured): ?>
                        <span class="cybersmtp-status cybersmtp-status-active">
                            <span class="cybersmtp-dot"></span>
                            <?php echo esc_html(self::provider_name($provider)); ?> Connected
                        </span>
                    <?php else: ?>
                        <span class="cybersmtp-status cybersmtp-status-inactive">
                            <span class="cybersmtp-dot"></span>
                            Not Configured
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabs -->
            <nav class="cybersmtp-tabs">
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="?page=cybersmtp&tab=<?php echo esc_attr($key); ?>"
                       class="cybersmtp-tab <?php echo $tab === $key ? 'active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Content -->
            <div class="cybersmtp-content">
                <?php
                switch ($tab) {
                    case 'settings':
                        $this->render_settings();
                        break;
                    case 'logs':
                        $this->render_logs();
                        break;
                    case 'deliverability':
                        $this->render_deliverability();
                        break;
                    case 'test':
                        $this->render_test();
                        break;
                    default:
                        $this->render_dashboard();
                }
                ?>
            </div>

            <!-- Footer -->
            <div class="cybersmtp-footer">
                <a href="https://cyberpanel.net/" target="_blank">CyberPanel</a>
                &middot;
                <a href="https://platform.cyberpersons.com/email/" target="_blank">CyberMail</a>
                &middot;
                <a href="https://platform.cyberpersons.com/MailTester/MailTester" target="_blank">Mail Tester</a>
            </div>
        </div>
        <?php
    }

    // ─── DASHBOARD ──────────────────────────────────────────

    private function render_dashboard() {
        $settings = get_option('cybersmtp_smtp_settings', []);
        $provider = $settings['provider'] ?? '';
        $is_cybermail = $provider === 'cybermail';
        $is_configured = $provider && (!empty($settings['api_key']) || !empty($settings['host']));

        $logger = new CyberSMTP_Email_Logger();
        $analytics = $logger->get_analytics();

        // Show setup prompt if not configured
        if (!$is_configured) {
            $this->render_setup_prompt();
            return;
        }
        ?>
        <!-- Stats Cards -->
        <div class="cybersmtp-stats-grid">
            <div class="cybersmtp-stat-card">
                <div class="cybersmtp-stat-label">Emails Sent</div>
                <div class="cybersmtp-stat-value"><?php echo esc_html(number_format($analytics['sent'])); ?></div>
            </div>
            <div class="cybersmtp-stat-card">
                <div class="cybersmtp-stat-label">Failed</div>
                <div class="cybersmtp-stat-value cybersmtp-stat-error"><?php echo esc_html(number_format($analytics['failed'])); ?></div>
            </div>
            <div class="cybersmtp-stat-card">
                <div class="cybersmtp-stat-label">Success Rate</div>
                <div class="cybersmtp-stat-value"><?php echo esc_html($analytics['rate']); ?>%</div>
            </div>
            <div class="cybersmtp-stat-card">
                <div class="cybersmtp-stat-label">Today</div>
                <div class="cybersmtp-stat-value"><?php echo esc_html(number_format($analytics['today_sent'])); ?></div>
            </div>
        </div>

        <!-- Chart -->
        <div class="cybersmtp-card">
            <div class="cybersmtp-card-header">
                <h3>Email Activity (Last 7 Days)</h3>
                <select id="cybersmtp-chart-range" class="cybersmtp-select">
                    <option value="7">7 days</option>
                    <option value="14">14 days</option>
                    <option value="30">30 days</option>
                </select>
            </div>
            <canvas id="cybersmtp-chart" height="80"></canvas>
        </div>

        <?php if ($is_cybermail): ?>
        <!-- CyberMail Account Info -->
        <div class="cybersmtp-card cybersmtp-card-cybermail">
            <div class="cybersmtp-card-header">
                <h3>CyberMail Account</h3>
                <span class="cybersmtp-badge cybersmtp-badge-primary">Active</span>
            </div>
            <div id="cybersmtp-cybermail-stats" class="cybersmtp-cybermail-stats">
                <p class="cybersmtp-loading">Loading account stats...</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="cybersmtp-card">
            <h3>Quick Actions</h3>
            <div class="cybersmtp-actions-grid">
                <a href="?page=cybersmtp&tab=test" class="cybersmtp-action-btn">
                    <span class="dashicons dashicons-email"></span>
                    Send Test Email
                </a>
                <a href="?page=cybersmtp&tab=deliverability" class="cybersmtp-action-btn">
                    <span class="dashicons dashicons-shield"></span>
                    Check Domain Health
                </a>
                <a href="?page=cybersmtp&tab=logs" class="cybersmtp-action-btn">
                    <span class="dashicons dashicons-list-view"></span>
                    View Logs
                </a>
                <a href="https://platform.cyberpersons.com/MailTester/MailTester" target="_blank" class="cybersmtp-action-btn">
                    <span class="dashicons dashicons-awards"></span>
                    Mail Tester
                </a>
            </div>
        </div>

        <?php if (!$is_cybermail): ?>
        <!-- CyberMail promotion -->
        <div class="cybersmtp-card cybersmtp-promo">
            <div class="cybersmtp-promo-content">
                <h3>Upgrade to CyberMail</h3>
                <p>Get delivery tracking, bounce analytics, and domain health monitoring — features not available with third-party providers.</p>
                <a href="<?php echo esc_url(CyberSMTP_CyberPanel_Detector::get_signup_url()); ?>"
                   target="_blank" class="cybersmtp-btn cybersmtp-btn-primary">
                    Get Started Free
                </a>
            </div>
        </div>
        <?php endif;
    }

    private function render_setup_prompt() {
        $is_cp = CyberSMTP_CyberPanel_Detector::is_cyberpanel();
        $signup_url = CyberSMTP_CyberPanel_Detector::get_signup_url();
        ?>
        <div class="cybersmtp-setup">
            <div class="cybersmtp-setup-icon">
                <span class="dashicons dashicons-email-alt" style="font-size:48px;width:48px;height:48px;color:#6366f1;"></span>
            </div>
            <h2>Let's set up your email</h2>
            <p>WordPress needs a configured email provider to send emails reliably.
               Without one, emails may go to spam or not be delivered at all.</p>

            <div class="cybersmtp-setup-options">
                <div class="cybersmtp-setup-option cybersmtp-setup-recommended">
                    <div class="cybersmtp-recommended-badge">Recommended</div>
                    <h3>CyberMail</h3>
                    <p>Free email delivery service by CyberPanel with delivery tracking, bounce analytics, and auto-DKIM.</p>
                    <ul>
                        <li>Free to use</li>
                        <li>Delivery status tracking</li>
                        <li>Domain health monitoring</li>
                        <li>Built-in spam protection</li>
                    </ul>
                    <?php if ($is_cp): ?>
                        <a href="<?php echo esc_url($signup_url); ?>" target="_blank"
                           class="cybersmtp-btn cybersmtp-btn-primary cybersmtp-btn-lg">
                            Set Up CyberMail
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url($signup_url); ?>" target="_blank"
                           class="cybersmtp-btn cybersmtp-btn-primary cybersmtp-btn-lg">
                            Get CyberMail (Free)
                        </a>
                    <?php endif; ?>
                    <p class="cybersmtp-setup-hint">
                        After signup, paste your API key in
                        <a href="?page=cybersmtp&tab=settings">Settings</a>.
                    </p>
                </div>

                <div class="cybersmtp-setup-option">
                    <h3>Other Providers</h3>
                    <p>Use your own SMTP server, Amazon SES, SendGrid, Mailgun, or Brevo.</p>
                    <a href="?page=cybersmtp&tab=settings" class="cybersmtp-btn cybersmtp-btn-outline">
                        Configure Manually
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    // ─── SETTINGS ───────────────────────────────────────────

    private function render_settings() {
        $settings = get_option('cybersmtp_smtp_settings', []);
        $provider = $settings['provider'] ?? '';
        $mode = $settings['mode'] ?? 'api';

        $providers = [
            'cybermail' => ['label' => 'CyberMail', 'icon' => 'cybermail.svg', 'recommended' => true],
            'smtp'      => ['label' => 'Other SMTP', 'icon' => 'smtp.svg', 'recommended' => false],
            'ses'       => ['label' => 'Amazon SES', 'icon' => 'amazonses.svg', 'recommended' => false],
            'sendgrid'  => ['label' => 'SendGrid', 'icon' => 'sendgrid.svg', 'recommended' => false],
            'mailgun'   => ['label' => 'Mailgun', 'icon' => 'mailgun.svg', 'recommended' => false],
            'brevo'     => ['label' => 'Brevo', 'icon' => 'brevo.svg', 'recommended' => false],
        ];
        ?>
        <form method="post" action="options.php" id="cybersmtp-settings-form">
            <?php settings_fields('cybersmtp_settings_group'); ?>

            <!-- Provider Selection -->
            <div class="cybersmtp-card">
                <h3>Choose Your Email Provider</h3>
                <div class="cybersmtp-provider-grid">
                    <?php foreach ($providers as $key => $data):
                        $selected = ($provider === $key) ? 'selected' : '';
                        $icon_path = __DIR__ . '/assets/icons/' . $data['icon'];
                    ?>
                        <div class="cybersmtp-provider-card <?php echo $selected; ?> <?php echo $data['recommended'] ? 'cybersmtp-provider-recommended' : ''; ?>"
                             data-provider="<?php echo esc_attr($key); ?>">
                            <?php if ($data['recommended']): ?>
                                <span class="cybersmtp-provider-badge">Recommended</span>
                            <?php endif; ?>
                            <div class="cybersmtp-provider-logo">
                                <?php if ($key === 'cybermail'): ?>
                                    <img src="https://cyberpanel.net/wp-content/uploads/2026/03/cyberpanel-logo-icon_only.png"
                                         alt="CyberMail" width="36" height="36" style="border-radius:6px;">
                                <?php elseif (file_exists($icon_path)):
                                    echo file_get_contents($icon_path);
                                endif; ?>
                            </div>
                            <div class="cybersmtp-provider-label"><?php echo esc_html($data['label']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="cybersmtp_smtp_settings[provider]"
                       id="cybersmtp-provider-input" value="<?php echo esc_attr($provider); ?>">
            </div>

            <!-- CyberMail Settings -->
            <div class="cybersmtp-card cybersmtp-provider-settings" data-for="cybermail" <?php echo $provider !== 'cybermail' ? 'style="display:none"' : ''; ?>>
                <h3>CyberMail Configuration</h3>
                <?php if (CyberSMTP_CyberPanel_Detector::is_cyberpanel()): ?>
                    <div class="cybersmtp-info-box">
                        You're on a CyberPanel server! Get your free CyberMail API key from
                        <a href="<?php echo esc_url(CyberSMTP_CyberPanel_Detector::get_signup_url()); ?>" target="_blank">
                            platform.cyberpersons.com
                        </a>.
                    </div>
                <?php endif; ?>
                <table class="cybersmtp-form-table">
                    <tr>
                        <th>API Key</th>
                        <td>
                            <input type="password" name="cybersmtp_smtp_settings[api_key]"
                                   id="cybersmtp-cybermail-apikey"
                                   value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>"
                                   class="cybersmtp-input" placeholder="sk_live_...">
                            <p class="cybersmtp-field-desc">Your CyberMail API key starting with <code>sk_live_</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th>From Email</th>
                        <td>
                            <input type="email" name="cybersmtp_smtp_settings[from_email]"
                                   value="<?php echo esc_attr($settings['from_email'] ?? ''); ?>"
                                   class="cybersmtp-input" placeholder="noreply@<?php echo esc_attr(CyberSMTP_CyberPanel_Detector::get_site_domain()); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>From Name</th>
                        <td>
                            <input type="text" name="cybersmtp_smtp_settings[from_name]"
                                   value="<?php echo esc_attr($settings['from_name'] ?? get_bloginfo('name')); ?>"
                                   class="cybersmtp-input">
                        </td>
                    </tr>
                </table>
                <div class="cybersmtp-form-actions">
                    <button type="button" id="cybersmtp-test-connection" class="cybersmtp-btn cybersmtp-btn-outline">
                        Test Connection
                    </button>
                    <span id="cybersmtp-connection-result"></span>
                </div>
            </div>

            <!-- Generic SMTP Settings -->
            <div class="cybersmtp-card cybersmtp-provider-settings" data-for="smtp" <?php echo $provider !== 'smtp' ? 'style="display:none"' : ''; ?>>
                <h3>SMTP Configuration</h3>
                <table class="cybersmtp-form-table">
                    <tr>
                        <th>SMTP Host</th>
                        <td><input type="text" name="cybersmtp_smtp_settings[host]" value="<?php echo esc_attr($settings['host'] ?? ''); ?>" class="cybersmtp-input" placeholder="smtp.example.com"></td>
                    </tr>
                    <tr>
                        <th>Port</th>
                        <td><input type="number" name="cybersmtp_smtp_settings[port]" value="<?php echo esc_attr($settings['port'] ?? '587'); ?>" class="cybersmtp-input cybersmtp-input-sm" placeholder="587"></td>
                    </tr>
                    <tr>
                        <th>Username</th>
                        <td><input type="text" name="cybersmtp_smtp_settings[username]" value="<?php echo esc_attr($settings['username'] ?? ''); ?>" class="cybersmtp-input"></td>
                    </tr>
                    <tr>
                        <th>Password</th>
                        <td><input type="password" name="cybersmtp_smtp_settings[password]" value="<?php echo esc_attr($settings['password'] ?? ''); ?>" class="cybersmtp-input"></td>
                    </tr>
                    <tr>
                        <th>Encryption</th>
                        <td>
                            <select name="cybersmtp_smtp_settings[encryption]" class="cybersmtp-select">
                                <option value="tls" <?php selected($settings['encryption'] ?? '', 'tls'); ?>>TLS</option>
                                <option value="ssl" <?php selected($settings['encryption'] ?? '', 'ssl'); ?>>SSL</option>
                                <option value="" <?php selected($settings['encryption'] ?? '', ''); ?>>None</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>From Email</th>
                        <td><input type="email" name="cybersmtp_smtp_settings[from_email]" value="<?php echo esc_attr($settings['from_email'] ?? ''); ?>" class="cybersmtp-input"></td>
                    </tr>
                    <tr>
                        <th>From Name</th>
                        <td><input type="text" name="cybersmtp_smtp_settings[from_name]" value="<?php echo esc_attr($settings['from_name'] ?? get_bloginfo('name')); ?>" class="cybersmtp-input"></td>
                    </tr>
                </table>
            </div>

            <!-- SES Settings -->
            <div class="cybersmtp-card cybersmtp-provider-settings" data-for="ses" <?php echo $provider !== 'ses' ? 'style="display:none"' : ''; ?>>
                <h3>Amazon SES Configuration</h3>
                <table class="cybersmtp-form-table">
                    <tr>
                        <th>Mode</th>
                        <td>
                            <select name="cybersmtp_smtp_settings[mode]" class="cybersmtp-select cybersmtp-mode-select">
                                <option value="api" <?php selected($mode, 'api'); ?>>API</option>
                                <option value="smtp" <?php selected($mode, 'smtp'); ?>>SMTP</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="cybersmtp-mode-api"><th>Access Key</th><td><input type="text" name="cybersmtp_smtp_settings[api_key]" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-api"><th>Secret Key</th><td><input type="password" name="cybersmtp_smtp_settings[api_secret]" value="<?php echo esc_attr($settings['api_secret'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr><th>Region</th><td><input type="text" name="cybersmtp_smtp_settings[region]" value="<?php echo esc_attr($settings['region'] ?? 'us-east-1'); ?>" class="cybersmtp-input cybersmtp-input-sm" placeholder="us-east-1"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>SMTP Host</th><td><input type="text" name="cybersmtp_smtp_settings[host]" value="<?php echo esc_attr($settings['host'] ?? ''); ?>" class="cybersmtp-input" placeholder="email-smtp.us-east-1.amazonaws.com"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Port</th><td><input type="number" name="cybersmtp_smtp_settings[port]" value="<?php echo esc_attr($settings['port'] ?? '587'); ?>" class="cybersmtp-input cybersmtp-input-sm"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Username</th><td><input type="text" name="cybersmtp_smtp_settings[username]" value="<?php echo esc_attr($settings['username'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Password</th><td><input type="password" name="cybersmtp_smtp_settings[password]" value="<?php echo esc_attr($settings['password'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr><th>From Email</th><td><input type="email" name="cybersmtp_smtp_settings[from_email]" value="<?php echo esc_attr($settings['from_email'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr><th>From Name</th><td><input type="text" name="cybersmtp_smtp_settings[from_name]" value="<?php echo esc_attr($settings['from_name'] ?? get_bloginfo('name')); ?>" class="cybersmtp-input"></td></tr>
                </table>
            </div>

            <!-- SendGrid Settings -->
            <div class="cybersmtp-card cybersmtp-provider-settings" data-for="sendgrid" <?php echo $provider !== 'sendgrid' ? 'style="display:none"' : ''; ?>>
                <h3>SendGrid Configuration</h3>
                <table class="cybersmtp-form-table">
                    <tr>
                        <th>Mode</th>
                        <td>
                            <select name="cybersmtp_smtp_settings[mode]" class="cybersmtp-select cybersmtp-mode-select">
                                <option value="api" <?php selected($mode, 'api'); ?>>API</option>
                                <option value="smtp" <?php selected($mode, 'smtp'); ?>>SMTP</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="cybersmtp-mode-api"><th>API Key</th><td><input type="password" name="cybersmtp_smtp_settings[api_key]" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>SMTP Host</th><td><input type="text" name="cybersmtp_smtp_settings[host]" value="<?php echo esc_attr($settings['host'] ?? 'smtp.sendgrid.net'); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Port</th><td><input type="number" name="cybersmtp_smtp_settings[port]" value="<?php echo esc_attr($settings['port'] ?? '587'); ?>" class="cybersmtp-input cybersmtp-input-sm"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Username</th><td><input type="text" name="cybersmtp_smtp_settings[username]" value="<?php echo esc_attr($settings['username'] ?? 'apikey'); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Password</th><td><input type="password" name="cybersmtp_smtp_settings[password]" value="<?php echo esc_attr($settings['password'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr><th>From Email</th><td><input type="email" name="cybersmtp_smtp_settings[from_email]" value="<?php echo esc_attr($settings['from_email'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr><th>From Name</th><td><input type="text" name="cybersmtp_smtp_settings[from_name]" value="<?php echo esc_attr($settings['from_name'] ?? get_bloginfo('name')); ?>" class="cybersmtp-input"></td></tr>
                </table>
            </div>

            <!-- Mailgun Settings -->
            <div class="cybersmtp-card cybersmtp-provider-settings" data-for="mailgun" <?php echo $provider !== 'mailgun' ? 'style="display:none"' : ''; ?>>
                <h3>Mailgun Configuration</h3>
                <table class="cybersmtp-form-table">
                    <tr>
                        <th>Mode</th>
                        <td>
                            <select name="cybersmtp_smtp_settings[mode]" class="cybersmtp-select cybersmtp-mode-select">
                                <option value="api" <?php selected($mode, 'api'); ?>>API</option>
                                <option value="smtp" <?php selected($mode, 'smtp'); ?>>SMTP</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="cybersmtp-mode-api"><th>API Key</th><td><input type="password" name="cybersmtp_smtp_settings[api_key]" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-api"><th>Domain</th><td><input type="text" name="cybersmtp_smtp_settings[domain]" value="<?php echo esc_attr($settings['domain'] ?? ''); ?>" class="cybersmtp-input" placeholder="mg.example.com"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>SMTP Host</th><td><input type="text" name="cybersmtp_smtp_settings[host]" value="<?php echo esc_attr($settings['host'] ?? 'smtp.mailgun.org'); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Port</th><td><input type="number" name="cybersmtp_smtp_settings[port]" value="<?php echo esc_attr($settings['port'] ?? '587'); ?>" class="cybersmtp-input cybersmtp-input-sm"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Username</th><td><input type="text" name="cybersmtp_smtp_settings[username]" value="<?php echo esc_attr($settings['username'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Password</th><td><input type="password" name="cybersmtp_smtp_settings[password]" value="<?php echo esc_attr($settings['password'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr><th>From Email</th><td><input type="email" name="cybersmtp_smtp_settings[from_email]" value="<?php echo esc_attr($settings['from_email'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr><th>From Name</th><td><input type="text" name="cybersmtp_smtp_settings[from_name]" value="<?php echo esc_attr($settings['from_name'] ?? get_bloginfo('name')); ?>" class="cybersmtp-input"></td></tr>
                </table>
            </div>

            <!-- Brevo Settings -->
            <div class="cybersmtp-card cybersmtp-provider-settings" data-for="brevo" <?php echo $provider !== 'brevo' ? 'style="display:none"' : ''; ?>>
                <h3>Brevo Configuration</h3>
                <table class="cybersmtp-form-table">
                    <tr>
                        <th>Mode</th>
                        <td>
                            <select name="cybersmtp_smtp_settings[mode]" class="cybersmtp-select cybersmtp-mode-select">
                                <option value="api" <?php selected($mode, 'api'); ?>>API</option>
                                <option value="smtp" <?php selected($mode, 'smtp'); ?>>SMTP</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="cybersmtp-mode-api"><th>API Key</th><td><input type="password" name="cybersmtp_smtp_settings[api_key]" value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>SMTP Host</th><td><input type="text" name="cybersmtp_smtp_settings[host]" value="<?php echo esc_attr($settings['host'] ?? 'smtp-relay.brevo.com'); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Port</th><td><input type="number" name="cybersmtp_smtp_settings[port]" value="<?php echo esc_attr($settings['port'] ?? '587'); ?>" class="cybersmtp-input cybersmtp-input-sm"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Username</th><td><input type="text" name="cybersmtp_smtp_settings[username]" value="<?php echo esc_attr($settings['username'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr class="cybersmtp-mode-smtp"><th>Password</th><td><input type="password" name="cybersmtp_smtp_settings[password]" value="<?php echo esc_attr($settings['password'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr><th>From Email</th><td><input type="email" name="cybersmtp_smtp_settings[from_email]" value="<?php echo esc_attr($settings['from_email'] ?? ''); ?>" class="cybersmtp-input"></td></tr>
                    <tr><th>From Name</th><td><input type="text" name="cybersmtp_smtp_settings[from_name]" value="<?php echo esc_attr($settings['from_name'] ?? get_bloginfo('name')); ?>" class="cybersmtp-input"></td></tr>
                </table>
            </div>

            <div class="cybersmtp-form-actions">
                <button type="submit" class="cybersmtp-btn cybersmtp-btn-primary">Save Settings</button>
            </div>
        </form>
        <?php
    }

    // ─── LOGS ───────────────────────────────────────────────

    private function render_logs() {
        $logger = new CyberSMTP_Email_Logger();

        $result = $logger->get_logs([
            'per_page'  => 20,
            'page'      => max(1, intval($_GET['paged'] ?? 1)),
            'status'    => sanitize_text_field($_GET['status'] ?? ''),
            'search'    => sanitize_text_field($_GET['s'] ?? ''),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to'   => sanitize_text_field($_GET['date_to'] ?? ''),
        ]);

        $logs = $result['logs'];
        $total = $result['total'];
        $total_pages = $result['total_pages'];
        $current_page = $result['page'];
        ?>
        <!-- Filters -->
        <div class="cybersmtp-card">
            <form method="get" class="cybersmtp-log-filters">
                <input type="hidden" name="page" value="cybersmtp">
                <input type="hidden" name="tab" value="logs">
                <div class="cybersmtp-filter-row">
                    <input type="text" name="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>"
                           placeholder="Search recipient or subject..." class="cybersmtp-input">
                    <select name="status" class="cybersmtp-select">
                        <option value="">All Status</option>
                        <option value="sent" <?php selected($_GET['status'] ?? '', 'sent'); ?>>Sent</option>
                        <option value="error" <?php selected($_GET['status'] ?? '', 'error'); ?>>Failed</option>
                    </select>
                    <input type="date" name="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>" class="cybersmtp-input cybersmtp-input-sm">
                    <span>to</span>
                    <input type="date" name="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>" class="cybersmtp-input cybersmtp-input-sm">
                    <button type="submit" class="cybersmtp-btn cybersmtp-btn-outline">Filter</button>
                    <?php if (!empty($_GET['s']) || !empty($_GET['status']) || !empty($_GET['date_from'])): ?>
                        <a href="?page=cybersmtp&tab=logs" class="cybersmtp-btn cybersmtp-btn-text">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="cybersmtp-card cybersmtp-card-table">
            <div class="cybersmtp-table-header">
                <span><?php echo esc_html(number_format($total)); ?> emails</span>
            </div>
            <table class="cybersmtp-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Provider</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="cybersmtp-empty">No emails found.</td></tr>
                    <?php else: foreach ($logs as $log): ?>
                        <tr class="cybersmtp-log-row" data-id="<?php echo intval($log['id']); ?>">
                            <td>
                                <?php if ($log['status'] === 'sent'): ?>
                                    <span class="cybersmtp-badge cybersmtp-badge-success">Sent</span>
                                <?php else: ?>
                                    <span class="cybersmtp-badge cybersmtp-badge-error">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td class="cybersmtp-cell-email"><?php echo esc_html($log['to_email']); ?></td>
                            <td class="cybersmtp-cell-subject"><?php echo esc_html(wp_trim_words($log['subject'], 8)); ?></td>
                            <td><span class="cybersmtp-provider-tag"><?php echo esc_html($log['provider'] ?: '—'); ?></span></td>
                            <td class="cybersmtp-cell-date"><?php echo esc_html(
                                human_time_diff(strtotime($log['created_at']), current_time('timestamp')) . ' ago'
                            ); ?></td>
                            <td>
                                <button type="button" class="cybersmtp-btn-icon cybersmtp-toggle-detail" title="Details">
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                                <button type="button" class="cybersmtp-btn-icon cybersmtp-resend-btn"
                                        data-id="<?php echo intval($log['id']); ?>" title="Resend">
                                    <span class="dashicons dashicons-controls-repeat"></span>
                                </button>
                            </td>
                        </tr>
                        <tr class="cybersmtp-log-detail" style="display:none">
                            <td colspan="6">
                                <div class="cybersmtp-detail-content">
                                    <div class="cybersmtp-detail-row">
                                        <strong>To:</strong> <?php echo esc_html($log['to_email']); ?>
                                    </div>
                                    <div class="cybersmtp-detail-row">
                                        <strong>Subject:</strong> <?php echo esc_html($log['subject']); ?>
                                    </div>
                                    <div class="cybersmtp-detail-row">
                                        <strong>Date:</strong> <?php echo esc_html($log['created_at']); ?>
                                    </div>
                                    <?php if (!empty($log['message_id'])): ?>
                                    <div class="cybersmtp-detail-row">
                                        <strong>Message ID:</strong> <code><?php echo esc_html($log['message_id']); ?></code>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($log['error_message'])): ?>
                                    <div class="cybersmtp-detail-row cybersmtp-detail-error">
                                        <strong>Error:</strong> <?php echo esc_html($log['error_message']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="cybersmtp-detail-row">
                                        <strong>Preview:</strong>
                                        <div class="cybersmtp-email-preview">
                                            <?php echo wp_kses_post(wp_trim_words(strip_tags($log['body']), 100)); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="cybersmtp-pagination">
                <?php
                $base_url = add_query_arg([
                    'page' => 'cybersmtp', 'tab' => 'logs',
                    's' => $_GET['s'] ?? '', 'status' => $_GET['status'] ?? '',
                    'date_from' => $_GET['date_from'] ?? '', 'date_to' => $_GET['date_to'] ?? '',
                ], admin_url('admin.php'));

                if ($current_page > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $base_url)); ?>"
                       class="cybersmtp-btn cybersmtp-btn-sm">&laquo; Prev</a>
                <?php endif; ?>
                <span class="cybersmtp-page-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $base_url)); ?>"
                       class="cybersmtp-btn cybersmtp-btn-sm">Next &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─── DELIVERABILITY ─────────────────────────────────────

    private function render_deliverability() {
        $domain = CyberSMTP_CyberPanel_Detector::get_site_domain();
        ?>
        <div class="cybersmtp-card">
            <h3>Domain Health Check</h3>
            <p>Verify your email authentication records to improve deliverability and avoid spam folders.</p>

            <div class="cybersmtp-dns-check-form">
                <input type="text" id="cybersmtp-dns-domain" value="<?php echo esc_attr($domain); ?>"
                       class="cybersmtp-input" placeholder="example.com">
                <button type="button" id="cybersmtp-check-dns" class="cybersmtp-btn cybersmtp-btn-primary">
                    Check Domain
                </button>
            </div>
        </div>

        <div id="cybersmtp-dns-results" style="display:none">
            <div class="cybersmtp-dns-grid">
                <div class="cybersmtp-dns-card" id="cybersmtp-dns-spf">
                    <div class="cybersmtp-dns-icon"></div>
                    <h4>SPF Record</h4>
                    <p class="cybersmtp-dns-status"></p>
                    <code class="cybersmtp-dns-record"></code>
                    <div class="cybersmtp-dns-help"></div>
                </div>

                <div class="cybersmtp-dns-card" id="cybersmtp-dns-dkim">
                    <div class="cybersmtp-dns-icon"></div>
                    <h4>DKIM Record</h4>
                    <p class="cybersmtp-dns-status"></p>
                    <code class="cybersmtp-dns-record"></code>
                    <div class="cybersmtp-dns-help"></div>
                </div>

                <div class="cybersmtp-dns-card" id="cybersmtp-dns-dmarc">
                    <div class="cybersmtp-dns-icon"></div>
                    <h4>DMARC Record</h4>
                    <p class="cybersmtp-dns-status"></p>
                    <code class="cybersmtp-dns-record"></code>
                    <div class="cybersmtp-dns-help"></div>
                </div>

                <div class="cybersmtp-dns-card" id="cybersmtp-dns-mx">
                    <div class="cybersmtp-dns-icon"></div>
                    <h4>MX Records</h4>
                    <p class="cybersmtp-dns-status"></p>
                    <div class="cybersmtp-dns-record"></div>
                </div>
            </div>
        </div>

        <div class="cybersmtp-card">
            <h3>Test Your Email Score</h3>
            <p>Check how your emails perform across spam filters, blacklists, and authentication checks.</p>
            <a href="https://platform.cyberpersons.com/MailTester/MailTester" target="_blank"
               class="cybersmtp-btn cybersmtp-btn-primary">
                Open Mail Tester
            </a>
        </div>

        <?php
        $settings = get_option('cybersmtp_smtp_settings', []);
        if (($settings['provider'] ?? '') !== 'cybermail'):
        ?>
        <div class="cybersmtp-card cybersmtp-promo">
            <div class="cybersmtp-promo-content">
                <h3>Want automatic DNS setup?</h3>
                <p>CyberMail can automatically configure SPF, DKIM, and DMARC for your domain. No manual DNS editing needed.</p>
                <a href="<?php echo esc_url(CyberSMTP_CyberPanel_Detector::get_signup_url()); ?>"
                   target="_blank" class="cybersmtp-btn cybersmtp-btn-primary">
                    Try CyberMail Free
                </a>
            </div>
        </div>
        <?php endif;
    }

    // ─── TEST EMAIL ─────────────────────────────────────────

    private function render_test() {
        ?>
        <div class="cybersmtp-card" style="max-width:560px">
            <h3>Send a Test Email</h3>
            <p>Verify your email configuration is working correctly.</p>

            <div class="cybersmtp-test-form">
                <input type="email" id="cybersmtp-test-email"
                       value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"
                       class="cybersmtp-input" placeholder="recipient@example.com">
                <button type="button" id="cybersmtp-send-test" class="cybersmtp-btn cybersmtp-btn-primary">
                    Send Test Email
                </button>
            </div>
            <div id="cybersmtp-test-result"></div>
        </div>
        <?php
    }

    // ─── HELPERS ────────────────────────────────────────────

    private static function provider_name($key) {
        $names = [
            'cybermail' => 'CyberMail',
            'smtp'      => 'SMTP',
            'ses'       => 'Amazon SES',
            'sendgrid'  => 'SendGrid',
            'mailgun'   => 'Mailgun',
            'brevo'     => 'Brevo',
        ];
        return $names[$key] ?? $key;
    }

    public static function encrypt($value) {
        if (empty($value)) return '';
        $key = wp_salt('auth');
        $iv = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return $encrypted !== false ? base64_encode($encrypted) : '';
    }

    public static function decrypt($value) {
        if (empty($value)) return '';
        $key = wp_salt('auth');
        $iv = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
        $decrypted = openssl_decrypt(base64_decode($value), 'AES-256-CBC', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : '';
    }
}
