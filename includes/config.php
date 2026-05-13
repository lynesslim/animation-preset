<?php
if (!defined('ABSPATH')) {
    exit;
}

$config_path = plugin_dir_path(__FILE__) . '../supercraft-config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

if (!defined('SUPERCRAFT_VALIDATION_ENDPOINT')) {
    define('SUPERCRAFT_VALIDATION_ENDPOINT', 'https://superapp.supercraft.my/api/public/validate-embed');
}

if (!defined('SUPERCRAFT_DELETE_REGISTRATION_ENDPOINT')) {
    define('SUPERCRAFT_DELETE_REGISTRATION_ENDPOINT', 'https://superapp.supercraft.my/api/public/validate-embed/delete-registration');
}

if (!defined('SUPERCRAFT_PLUGIN_NAME')) {
    define('SUPERCRAFT_PLUGIN_NAME', 'supercraft-superanimation');
}
