<?php
if (!defined('ABSPATH')) {
    exit;
}

class CyberSMTP_Settings {

    public static function get($key = null, $default = '') {
        $settings = get_option('cybersmtp_smtp_settings', []);

        if ($key === null) {
            return $settings;
        }

        return $settings[$key] ?? $default;
    }

    public static function get_provider() {
        return self::get('provider', '');
    }

    public static function is_configured() {
        $provider = self::get_provider();
        return $provider && (!empty(self::get('api_key')) || !empty(self::get('host')));
    }

    public static function is_cybermail() {
        return self::get_provider() === 'cybermail';
    }
}
