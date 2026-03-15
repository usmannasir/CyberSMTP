<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detects if the site is running on a CyberPanel server and provides
 * auto-configuration helpers for CyberMail.
 */
class CyberSMTP_CyberPanel_Detector {

    private static $is_cyberpanel = null;

    /**
     * Check if this WordPress site is hosted on CyberPanel.
     */
    public static function is_cyberpanel() {
        if (self::$is_cyberpanel !== null) {
            return self::$is_cyberpanel;
        }

        self::$is_cyberpanel = (
            is_dir('/usr/local/CyberCP') ||
            is_dir('/usr/local/lsws') ||
            file_exists('/usr/local/CyberCP/CyberCP/settings.py')
        );

        return self::$is_cyberpanel;
    }

    /**
     * Try to read local CyberMail credentials for this domain.
     */
    public static function get_local_cybermail_credentials() {
        $cred_file = '/etc/cybermail/credentials.json';
        if (!file_exists($cred_file) || !is_readable($cred_file)) {
            return null;
        }

        $data = json_decode(file_get_contents($cred_file), true);
        if (!is_array($data) || empty($data)) {
            return null;
        }

        $site_domain = self::get_site_domain();

        // Find a credential that includes the site's domain
        foreach ($data as $cred) {
            if (!empty($cred['domains']) && is_array($cred['domains'])) {
                foreach ($cred['domains'] as $domain) {
                    if (strcasecmp($domain, $site_domain) === 0) {
                        return [
                            'username' => $cred['username'] ?? '',
                            'domain'   => $site_domain,
                            'host'     => 'mail.cyberpersons.com',
                            'port'     => 587,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if CyberMail service is reachable.
     */
    public static function test_cybermail_connectivity() {
        $response = wp_remote_get('https://platform.cyberpersons.com/email/v1/health', [
            'timeout' => 10,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get the primary domain of this WordPress site.
     */
    public static function get_site_domain() {
        $url = get_site_url();
        $parsed = wp_parse_url($url);
        return $parsed['host'] ?? '';
    }

    /**
     * Get the signup URL for CyberMail.
     */
    public static function get_signup_url() {
        $domain = self::get_site_domain();
        return 'https://platform.cyberpersons.com/email/get-started/?domain=' . urlencode($domain) . '&source=cybersmtp';
    }

    /**
     * Check if CyberMail is already configured.
     */
    public static function is_cybermail_configured() {
        $settings = get_option('cybersmtp_smtp_settings', []);
        return ($settings['provider'] ?? '') === 'cybermail' && !empty($settings['api_key']);
    }
}
