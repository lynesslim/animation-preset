<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'supercraft_anim_enqueue_assets');
add_action('elementor/frontend/after_enqueue_scripts', 'supercraft_anim_enqueue_assets');
add_action('elementor/preview/enqueue_scripts', 'supercraft_anim_enqueue_assets');

function supercraft_is_elementor_editor_context() {
    if (isset($_GET['elementor-preview'])) {
        return true;
    }

    if (class_exists('\Elementor\Plugin')) {
        $plugin = \Elementor\Plugin::$instance;
        if ($plugin) {
            if (isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode()) {
                return true;
            }
            if (isset($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode()) {
                return true;
            }
        }
    }

    return false;
}

function supercraft_anim_enqueue_assets() {
    if (!supercraft_is_validated()) {
        return;
    }

    $base_path = plugin_dir_path(dirname(__FILE__)) . '/';
    $css_version = file_exists($base_path . 'animation-preset-plugin.css')
        ? filemtime($base_path . 'animation-preset-plugin.css')
        : '0.1.1';
    $js_version = file_exists($base_path . 'animation-preset-plugin.js')
        ? filemtime($base_path . 'animation-preset-plugin.js')
        : '0.1.1';

    if (!wp_script_is('gsap', 'registered')) {
        wp_register_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/gsap.min.js', [], '3.12.4', true);
    }
    if (!wp_script_is('gsap-scrolltrigger', 'registered')) {
        wp_register_script('gsap-scrolltrigger', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js', ['gsap'], '3.12.5', true);
    }
    if (!wp_script_is('split-type', 'registered')) {
        wp_register_script('split-type', 'https://unpkg.com/split-type', [], '0.3.3', true);
    }
    if (!wp_script_is('lenis', 'registered')) {
        wp_register_script('lenis', 'https://cdn.jsdelivr.net/npm/lenis@1.1.20/dist/lenis.min.js', [], '1.1.20', true);
    }

    wp_register_style(
        'supercraft-anim',
        plugins_url('animation-preset-plugin.css', dirname(__FILE__)),
        [],
        $css_version
    );
    wp_register_script(
        'supercraft-anim',
        plugins_url('animation-preset-plugin.js', dirname(__FILE__)),
        ['gsap', 'gsap-scrolltrigger', 'split-type', 'lenis'],
        $js_version,
        true
    );

    wp_enqueue_style('supercraft-anim');

    wp_enqueue_script('supercraft-anim');

    $lenis_enabled = get_option('supercraft_lenis_enabled', '1');
    wp_add_inline_script('supercraft-anim', 'window.supercraftLenisEnabled = ' . $lenis_enabled . ';', 'before');
}

add_action('elementor/editor/after_enqueue_scripts', function () {
    if (!supercraft_is_validated()) {
        return;
    }

    $base_path = plugin_dir_path(dirname(__FILE__)) . '/';
    $editor_js_version = file_exists($base_path . 'supercraft-anim-editor.js')
        ? filemtime($base_path . 'supercraft-anim-editor.js')
        : '0.1.1';

    wp_enqueue_script(
        'supercraft-anim-editor',
        plugins_url('supercraft-anim-editor.js', dirname(__FILE__)),
        ['jquery'],
        $editor_js_version,
        true
    );
}, 1);

add_action('elementor/frontend/after_enqueue_scripts', function () {
    if (!supercraft_is_validated()) {
        return;
    }

    if (!supercraft_is_elementor_editor_context()) {
        return;
    }

    $base_path = plugin_dir_path(dirname(__FILE__)) . '/';
    $editor_js_version = file_exists($base_path . 'supercraft-anim-editor.js')
        ? filemtime($base_path . 'supercraft-anim-editor.js')
        : '0.1.1';

    wp_enqueue_script(
        'supercraft-anim-editor',
        plugins_url('supercraft-anim-editor.js', dirname(__FILE__)),
        ['jquery'],
        $editor_js_version,
        true
    );
}, 1);

add_action('elementor/frontend/widget/before_render', 'supercraft_apply_attrs', 1);
add_action('elementor/frontend/container/before_render', 'supercraft_apply_attrs', 1);