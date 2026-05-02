<?php
if (!defined('ABSPATH')) {
    exit;
}

$config_path = plugin_dir_path(__FILE__) . '../supercraft-config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}