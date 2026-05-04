<?php
/**
 * Plugin Name: Superanimate GSAP Elementor
 * Description: GSAP-based animation presets with Elementor controls.
 * Version: 0.1.2
 * Author: Supercraft
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/config.php';
require_once plugin_dir_path(__FILE__) . 'includes/validation.php';
require_once plugin_dir_path(__FILE__) . 'includes/render-attributes.php';
require_once plugin_dir_path(__FILE__) . 'includes/assets.php';
require_once plugin_dir_path(__FILE__) . 'includes/elementor-controls.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin.php';

// Initialize Plugin Update Checker for automatic GitHub updates
require_once plugin_dir_path(__FILE__) . 'includes/plugin-update-checker/plugin-update-checker.php';
$supercraftUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/lynesslim/supercraft-animation-plugin/',
    __FILE__,
    'supercraft-animation-plugin'
);
$supercraftUpdateChecker->setBranch('main');

if (!supercraft_is_validated()) {
    remove_action('wp_enqueue_scripts', 'supercraft_anim_enqueue_assets');
    remove_action('elementor/frontend/after_enqueue_scripts', 'supercraft_anim_enqueue_assets');
    remove_action('elementor/preview/enqueue_scripts', 'supercraft_anim_enqueue_assets');
    remove_action('elementor/frontend/widget/before_render', 'supercraft_apply_attrs', 1);
    remove_action('elementor/frontend/container/before_render', 'supercraft_apply_attrs', 1);
}