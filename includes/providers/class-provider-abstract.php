<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class CyberSMTP_Provider_Abstract {
    abstract public function send($email_data);
    abstract public function get_settings_fields();
    // Add common provider methods and properties
} 