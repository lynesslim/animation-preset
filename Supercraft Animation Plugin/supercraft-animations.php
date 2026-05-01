<?php
/**
 * Plugin Name: Superanimate GSAP Elementor
 * Description: GSAP-based animation presets with Elementor controls.
 * Version: 0.1.0
 * Author: Supercraft
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load Supabase config early
$config_path = plugin_dir_path(__FILE__) . 'supercraft-config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

// Validation status helper (must be defined early)
function supercraft_is_validated() {
    if (!defined('SUPERCRAFT_SUPABASE_URL')) {
        return true;
    }
    $status = get_option('supercraft_validation_status', 'not_set');
    return $status === 'valid';
}

// Enqueue frontend and Elementor preview assets
add_action('wp_enqueue_scripts', 'supercraft_anim_enqueue_assets');
add_action('elementor/frontend/after_enqueue_scripts', 'supercraft_anim_enqueue_assets');
add_action('elementor/preview/enqueue_scripts', 'supercraft_anim_enqueue_assets');

function supercraft_is_elementor_editor_context() {
    if (isset($_GET['elementor-preview'])) {
        return true;
    }

    if (class_exists('\\Elementor\\Plugin')) {
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
    $base_path = plugin_dir_path(__FILE__);
    $css_version = file_exists($base_path . 'animation-preset-plugin.css')
        ? filemtime($base_path . 'animation-preset-plugin.css')
        : '0.1.1';
    $js_version = file_exists($base_path . 'animation-preset-plugin.js')
        ? filemtime($base_path . 'animation-preset-plugin.js')
        : '0.1.1';

    // Avoid duplicate GSAP loads if other plugins/themes already registered
    if (!wp_script_is('gsap', 'registered')) {
        wp_register_script('gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/gsap.min.js', [], '3.12.4', true);
    }
    if (!wp_script_is('gsap-scrolltrigger', 'registered')) {
        wp_register_script('gsap-scrolltrigger', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js', ['gsap'], '3.12.5', true);
    }
    if (!wp_script_is('split-type', 'registered')) {
        wp_register_script('split-type', 'https://unpkg.com/split-type', [], '0.3.3', true);
    }

    wp_register_style(
        'supercraft-anim',
        plugins_url('animation-preset-plugin.css', __FILE__),
        [],
        $css_version
    );
    wp_register_script(
        'supercraft-anim',
        plugins_url('animation-preset-plugin.js', __FILE__),
        ['gsap', 'gsap-scrolltrigger', 'split-type'],
        $js_version,
        true
    );

    wp_enqueue_style('supercraft-anim');

    wp_enqueue_script('supercraft-anim');
}

// Elementor editor-specific helper (preview/play controls)
// Load editor helper in both panel and preview (guarded by editor-active body)
add_action('elementor/editor/after_enqueue_scripts', function () {
    $base_path = plugin_dir_path(__FILE__);
    $editor_js_version = file_exists($base_path . 'supercraft-anim-editor.js')
        ? filemtime($base_path . 'supercraft-anim-editor.js')
        : '0.1.1';

    wp_enqueue_script(
        'supercraft-anim-editor',
        plugins_url('supercraft-anim-editor.js', __FILE__),
        ['jquery'],
        $editor_js_version,
        true
    );
}, 1);
add_action('elementor/frontend/after_enqueue_scripts', function () {
    if (!supercraft_is_elementor_editor_context()) {
        return;
    }

    $base_path = plugin_dir_path(__FILE__);
    $editor_js_version = file_exists($base_path . 'supercraft-anim-editor.js')
        ? filemtime($base_path . 'supercraft-anim-editor.js')
        : '0.1.1';

    wp_enqueue_script(
        'supercraft-anim-editor',
        plugins_url('supercraft-anim-editor.js', __FILE__),
        ['jquery'],
        $editor_js_version,
        true
    );
}, 1);
add_action('elementor/frontend/widget/before_render', 'supercraft_apply_attrs', 1);
add_action('elementor/frontend/container/before_render', 'supercraft_apply_attrs', 1);

// Elementor controls
$supercraft_controls_callback = function ($element, $section_id) {
    if (method_exists($element, 'get_name')) {
        $widget_name = $element->get_name();
        if ($widget_name === 'tabs' || $widget_name === 'nested-tabs') {
            return;
        }
    }

    $ease_options = [
        'none' => __('Immediate (none)', 'supercraft-anim'),
        'linear' => __('Linear', 'supercraft-anim'),
        'power1.in' => __('Gentle In (power1.in)', 'supercraft-anim'),
        'power1.out' => __('Gentle Out (power1.out)', 'supercraft-anim'),
        'power1.inOut' => __('Gentle In-Out (power1.inOut)', 'supercraft-anim'),
        'power2.in' => __('Smooth In (power2.in)', 'supercraft-anim'),
        'power2.out' => __('Smooth Out (power2.out)', 'supercraft-anim'),
        'power2.inOut' => __('Smooth In-Out (power2.inOut)', 'supercraft-anim'),
        'power3.in' => __('Strong In (power3.in)', 'supercraft-anim'),
        'power3.out' => __('Strong Out (power3.out)', 'supercraft-anim'),
        'power3.inOut' => __('Strong In-Out (power3.inOut)', 'supercraft-anim'),
        'power4.in' => __('Very Strong In (power4.in)', 'supercraft-anim'),
        'power4.out' => __('Very Strong Out (power4.out)', 'supercraft-anim'),
        'power4.inOut' => __('Very Strong In-Out (power4.inOut)', 'supercraft-anim'),
        'expo.in' => __('Exponential In (expo.in)', 'supercraft-anim'),
        'expo.out' => __('Exponential Out (expo.out)', 'supercraft-anim'),
        'expo.inOut' => __('Exponential In-Out (expo.inOut)', 'supercraft-anim'),
        'circ.in' => __('Circular In (circ.in)', 'supercraft-anim'),
        'circ.out' => __('Circular Out (circ.out)', 'supercraft-anim'),
        'circ.inOut' => __('Circular In-Out (circ.inOut)', 'supercraft-anim'),
        'back.in(1.7)' => __('Overshoot In (back.in)', 'supercraft-anim'),
        'back.out(1.7)' => __('Overshoot Out (back.out)', 'supercraft-anim'),
        'back.inOut(1.7)' => __('Overshoot In-Out (back.inOut)', 'supercraft-anim'),
        'elastic.in(0.5,0.3)' => __('Elastic In', 'supercraft-anim'),
        'elastic.out(0.5,0.3)' => __('Elastic Out', 'supercraft-anim'),
        'elastic.inOut(0.5,0.3)' => __('Elastic In-Out', 'supercraft-anim'),
        'bounce.in' => __('Bounce In', 'supercraft-anim'),
        'bounce.out' => __('Bounce Out', 'supercraft-anim'),
        'bounce.inOut' => __('Bounce In-Out', 'supercraft-anim'),
    ];

    $cat_options = [
        '' => __('None', 'supercraft-anim'),
        'scroll-transform' => __('Scroll Transform', 'supercraft-anim'),
        'split-text' => __('Split Text', 'supercraft-anim'),
        'image-reveal' => __('Image Reveal', 'supercraft-anim'),
        'container-reveal' => __('Container Reveal', 'supercraft-anim'),
        'scroll-fill-text' => __('Scroll Fill Text', 'supercraft-anim'),
    ];

    $element->start_controls_section(
        'supercraft_anim_section',
        [
            'label' => __('Superanimate', 'supercraft-anim'),
            'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
            'icon' => 'eicon-animation', // Elementor icon for animations
        ]
    );

    $element->add_control(
        'supercraft_anim_category',
        [
            'label' => __('Animation Category', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $cat_options,
            'default' => '',
            'frontend_available' => false,
        ]
    );

    // Presets per category
    $scroll_presets = [
        'custom' => __('Custom', 'supercraft-anim'),
        'fade-left' => __('Fade Left', 'supercraft-anim'),
        'fade-right' => __('Fade Right', 'supercraft-anim'),
        'fade-up' => __('Fade Up', 'supercraft-anim'),
        'fade-down' => __('Fade Down', 'supercraft-anim'),
        'zoom-in' => __('Zoom In', 'supercraft-anim'),
        'zoom-out' => __('Zoom Out', 'supercraft-anim'),
        'blur-fade' => __('Blur Fade', 'supercraft-anim'),
        'blur-fade-left' => __('Blur Fade Left', 'supercraft-anim'),
        'blur-fade-right' => __('Blur Fade Right', 'supercraft-anim'),
        'blur-fade-up' => __('Blur Fade Up', 'supercraft-anim'),
        'blur-fade-down' => __('Blur Fade Down', 'supercraft-anim'),
        'blur-zoom-in' => __('Blur Zoom In', 'supercraft-anim'),
        'blur-zoom-out' => __('Blur Zoom Out', 'supercraft-anim'),
        'fade' => __('Fade Only', 'supercraft-anim'),
    ];

    $element->add_control(
        'supercraft_scroll_preset',
        [
            'label' => __('Preset', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $scroll_presets,
            'default' => 'fade-up',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => ['scroll-transform'],
            ],
        ]
    );

    // Scrub toggle for Scroll Transform
    $element->add_control(
        'supercraft_scroll_scrub',
        [
            'label' => __('Enable Scroll Scrub', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
            ],
        ]
    );

    // Preview controls for Scroll Transform
    $element->add_control(
        'supercraft_preview_state',
        [
            'label' => __('Preview State (Editor)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'end' => __('Show End State', 'supercraft-anim'),
                'start' => __('Show Start State', 'supercraft-anim'),
            ],
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_preset' => 'custom',
            ],
            'default' => 'end',
        ]
    );

    $element->add_control(
        'supercraft_preview_play_btn',
        [
            'label' => __('Play in Editor', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::BUTTON,
            'text' => __('Play', 'supercraft-anim'),
            'event' => 'supercraft_preview_play',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category!' => '',
                'supercraft_anim_category!' => 'container-reveal',
                'supercraft_scroll_scrub!' => 'yes',
                'supercraft_split_scrub!' => 'yes',
                'supercraft_container_scrub!' => 'yes',
            ],
        ]
    );

    // Shared minimal controls for non-custom presets
    $element->add_control(
        'supercraft_trigger',
        [
            'label' => __('Trigger (e.g. top 85%)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 85%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_preset!' => 'custom',
                'supercraft_scroll_scrub!' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_delay',
        [
            'label' => __('Delay (s)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'step' => 0.1,
            'default' => 0,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_preset!' => 'custom',
                'supercraft_scroll_scrub!' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_preset_duration',
        [
            'label' => __('Duration (s)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'step' => 0.1,
            'default' => 1,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_preset!' => 'custom',
                'supercraft_scroll_scrub!' => 'yes',
            ],
        ]
    );

    $ease_options = [
        'none' => __('Immediate (none)', 'supercraft-anim'),
        'linear' => __('Linear', 'supercraft-anim'),
        'power1.in' => __('Gentle In (power1.in)', 'supercraft-anim'),
        'power1.out' => __('Gentle Out (power1.out)', 'supercraft-anim'),
        'power1.inOut' => __('Gentle In-Out (power1.inOut)', 'supercraft-anim'),
        'power2.in' => __('Smooth In (power2.in)', 'supercraft-anim'),
        'power2.out' => __('Smooth Out (power2.out)', 'supercraft-anim'),
        'power2.inOut' => __('Smooth In-Out (power2.inOut)', 'supercraft-anim'),
        'power3.in' => __('Strong In (power3.in)', 'supercraft-anim'),
        'power3.out' => __('Strong Out (power3.out)', 'supercraft-anim'),
        'power3.inOut' => __('Strong In-Out (power3.inOut)', 'supercraft-anim'),
        'power4.in' => __('Very Strong In (power4.in)', 'supercraft-anim'),
        'power4.out' => __('Very Strong Out (power4.out)', 'supercraft-anim'),
        'power4.inOut' => __('Very Strong In-Out (power4.inOut)', 'supercraft-anim'),
        'expo.in' => __('Exponential In (expo.in)', 'supercraft-anim'),
        'expo.out' => __('Exponential Out (expo.out)', 'supercraft-anim'),
        'expo.inOut' => __('Exponential In-Out (expo.inOut)', 'supercraft-anim'),
        'circ.in' => __('Circular In (circ.in)', 'supercraft-anim'),
        'circ.out' => __('Circular Out (circ.out)', 'supercraft-anim'),
        'circ.inOut' => __('Circular In-Out (circ.inOut)', 'supercraft-anim'),
        'back.in(1.7)' => __('Overshoot In (back.in)', 'supercraft-anim'),
        'back.out(1.7)' => __('Overshoot Out (back.out)', 'supercraft-anim'),
        'back.inOut(1.7)' => __('Overshoot In-Out (back.inOut)', 'supercraft-anim'),
        'elastic.in(0.5,0.3)' => __('Elastic In', 'supercraft-anim'),
        'elastic.out(0.5,0.3)' => __('Elastic Out', 'supercraft-anim'),
        'elastic.inOut(0.5,0.3)' => __('Elastic In-Out', 'supercraft-anim'),
        'bounce.in' => __('Bounce In', 'supercraft-anim'),
        'bounce.out' => __('Bounce Out', 'supercraft-anim'),
        'bounce.inOut' => __('Bounce In-Out', 'supercraft-anim'),
    ];

    $element->add_control(
        'supercraft_ease',
        [
            'label' => __('Ease', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $ease_options,
            'default' => 'power2.out',
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_preset!' => 'custom',
                'supercraft_scroll_scrub!' => 'yes',
            ],
        ]
    );

    // Custom Scroll Transform controls (full set)
    $ct_fields = [
        'start_x' => ['label' => __('Start X (px)', 'supercraft-anim'), 'default' => 0],
        'start_y' => ['label' => __('Start Y (px)', 'supercraft-anim'), 'default' => 0],
        'start_rotate' => ['label' => __('Start Rotate (deg)', 'supercraft-anim'), 'default' => 0],
        'start_scale' => ['label' => __('Start Scale', 'supercraft-anim'), 'default' => 1],
        'start_opacity' => ['label' => __('Start Opacity', 'supercraft-anim'), 'default' => 1],
        'start_blur' => ['label' => __('Start Blur (px)', 'supercraft-anim'), 'default' => 0],
        'end_x' => ['label' => __('End X (px)', 'supercraft-anim'), 'default' => 0],
        'end_y' => ['label' => __('End Y (px)', 'supercraft-anim'), 'default' => 0],
        'end_rotate' => ['label' => __('End Rotate (deg)', 'supercraft-anim'), 'default' => 0],
        'end_scale' => ['label' => __('End Scale', 'supercraft-anim'), 'default' => 1],
        'end_opacity' => ['label' => __('End Opacity', 'supercraft-anim'), 'default' => 1],
        'end_blur' => ['label' => __('End Blur (px)', 'supercraft-anim'), 'default' => 0],
    ];

    foreach ($ct_fields as $key => $config) {
        $element->add_control(
            'supercraft_ct_' . $key,
            [
                'label' => $config['label'],
                'type' => \Elementor\Controls_Manager::NUMBER,
                'step' => ($key === 'start_scale' || $key === 'end_scale') ? 0.01 : 1,
                'default' => $config['default'],
                'frontend_available' => false,
                'condition' => [
                    'supercraft_anim_category' => 'scroll-transform',
                    'supercraft_scroll_preset' => 'custom',
                ],
            ]
        );
    }

    $element->add_control(
        'supercraft_ct_duration',
        [
            'label' => __('Duration (s)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'step' => 0.1,
            'default' => 1,
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_preset' => 'custom',
                'supercraft_scroll_scrub!' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_ct_delay',
            [
                'label' => __('Delay (s)', 'supercraft-anim'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'step' => 0.1,
                'frontend_available' => false,
                'condition' => [
                    'supercraft_anim_category' => 'scroll-transform',
                    'supercraft_scroll_preset' => 'custom',
                    'supercraft_scroll_scrub!' => 'yes',
                ],
            ]
    );

    $element->add_control(
        'supercraft_ct_ease',
            [
                'label' => __('Ease', 'supercraft-anim'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $ease_options,
                'default' => 'power2.out',
                'frontend_available' => false,
                'condition' => [
                    'supercraft_anim_category' => 'scroll-transform',
                    'supercraft_scroll_preset' => 'custom',
                    'supercraft_scroll_scrub!' => 'yes',
                ],
            ]
    );

    $element->add_control(
        'supercraft_ct_trigger',
            [
                'label' => __('Trigger (e.g. top 85%)', 'supercraft-anim'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'top 85%',
                'frontend_available' => false,
                'condition' => [
                    'supercraft_anim_category' => 'scroll-transform',
                    'supercraft_scroll_preset' => 'custom',
                ],
            ]
    );

    // Scroll Transform Scrub controls
    $element->add_control(
        'supercraft_scrub_start',
        [
            'label' => __('Scroll Start', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 85%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_scrub' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_scrub_end',
        [
            'label' => __('Scroll End', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 15%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_scrub' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_scrub_ease',
        [
            'label' => __('Ease', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $ease_options,
            'default' => 'none',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_scrub' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_scrub_forward',
        [
            'label' => __('Forward Only', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-transform',
                'supercraft_scroll_scrub' => 'yes',
            ],
        ]
    );

    // Split Text controls
    $element->add_control(
        'supercraft_split_mode',
        [
            'label' => __('Split Mode', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'chars' => __('Characters', 'supercraft-anim'),
                'words' => __('Words', 'supercraft-anim'),
            ],
            'default' => 'chars',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
            ],
        ]
    );

    // Variant per mode to avoid showing word-only blur on chars
    $element->add_control(
        'supercraft_split_variant_char',
        [
            'label' => __('Variant (Characters)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'fade-x' => __('Fade X', 'supercraft-anim'),
                'fade-y' => __('Fade Y', 'supercraft-anim'),
                'fade-blur' => __('Fade Blur', 'supercraft-anim'),
            ],
            'default' => 'fade-x',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
                'supercraft_split_mode' => 'chars',
            ],
        ]
    );

    $element->add_control(
        'supercraft_split_variant_word',
        [
            'label' => __('Variant (Words)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'fade-x' => __('Fade X', 'supercraft-anim'),
                'fade-y' => __('Fade Y', 'supercraft-anim'),
                'fade-blur' => __('Fade Blur', 'supercraft-anim'),
            ],
            'default' => 'fade-x',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
                'supercraft_split_mode' => 'words',
            ],
        ]
    );

    $element->add_control(
        'supercraft_split_scrub',
        [
            'label' => __('Enable Scroll Scrub', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
            ],
        ]
    );

    $element->add_control(
        'supercraft_split_scroll_start',
        [
            'label' => __('Scroll Start', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 85%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
                'supercraft_split_scrub' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_split_scroll_end',
        [
            'label' => __('Scroll End', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 40%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
                'supercraft_split_scrub' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_split_forward',
        [
            'label' => __('Forward Only', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
                'supercraft_split_scrub' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_split_preset',
        [
            'label' => __('Preset', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'custom' => __('Custom', 'supercraft-anim'),
                'light' => __('Light', 'supercraft-anim'),
                'medium' => __('Medium', 'supercraft-anim'),
                'dramatic' => __('Dramatic', 'supercraft-anim'),
            ],
            'default' => 'medium',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
            ],
        ]
    );

    $element->add_control(
        'supercraft_split_delay',
        [
            'label' => __('Delay (s)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'step' => 0.1,
            'default' => 0,
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
            ],
        ]
    );

    $split_custom = [
        'offset_x' => __('Offset X (px)', 'supercraft-anim'),
        'offset_y' => __('Offset Y (px)', 'supercraft-anim'),
        'stagger' => __('Stagger (s)', 'supercraft-anim'),
        'duration' => __('Duration (s)', 'supercraft-anim'),
        'opacity_start' => __('Opacity Start', 'supercraft-anim'),
        'blur_start' => __('Blur Start (px)', 'supercraft-anim'),
    ];
    foreach ($split_custom as $key => $label) {
        $element->add_control(
            'supercraft_split_' . $key,
            [
                'label' => $label,
                'type' => \Elementor\Controls_Manager::NUMBER,
                'step' => 0.01,
                'frontend_available' => false,
                'condition' => [
                    'supercraft_anim_category' => 'split-text',
                    'supercraft_split_preset' => 'custom',
                ],
            ]
        );
    }

    $element->add_control(
        'supercraft_split_ease',
        [
            'label' => __('Ease', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $ease_options,
            'default' => 'power2.out',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'split-text',
                'supercraft_split_preset' => 'custom',
            ],
        ]
    );

    // Image Reveal controls
    $element->add_control(
        'supercraft_image_preset',
        [
            'label' => __('Preset', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'left' => __('Left', 'supercraft-anim'),
                'right' => __('Right', 'supercraft-anim'),
                'top' => __('Top', 'supercraft-anim'),
                'bottom' => __('Bottom', 'supercraft-anim'),
                'custom' => __('Custom', 'supercraft-anim'),
            ],
            'default' => 'left',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'image-reveal',
            ],
        ]
    );

    $element->add_control(
        'supercraft_image_direction',
        [
            'label' => __('Direction', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'left' => __('Left', 'supercraft-anim'),
                'right' => __('Right', 'supercraft-anim'),
                'top' => __('Top', 'supercraft-anim'),
                'bottom' => __('Bottom', 'supercraft-anim'),
            ],
            'default' => 'left',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'image-reveal',
                'supercraft_image_preset' => 'custom',
            ],
        ]
    );

    $element->add_control(
        'supercraft_image_duration',
        [
            'label' => __('Duration (s)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'step' => 0.1,
            'default' => 1.5,
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'image-reveal',
            ],
        ]
    );

    $element->add_control(
        'supercraft_image_delay',
        [
            'label' => __('Delay (s)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'step' => 0.1,
            'default' => 0,
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'image-reveal',
            ],
        ]
    );

    $element->add_control(
        'supercraft_image_ease',
        [
            'label' => __('Ease', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $ease_options,
            'default' => 'power2.out',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'image-reveal',
            ],
        ]
    );

    $element->add_control(
        'supercraft_image_trigger',
        [
            'label' => __('Trigger', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 85%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'image-reveal',
            ],
        ]
    );

    $element->add_control(
        'supercraft_image_scale',
        [
            'label' => __('Image Scale', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'step' => 0.01,
            'default' => 1.3,
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'image-reveal',
            ],
        ]
    );

    // Container Reveal controls
    $element->add_control(
        'supercraft_container_preset',
        [
            'label' => __('Preset', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'center' => __('Center Out', 'supercraft-anim'),
                'left' => __('Left', 'supercraft-anim'),
                'right' => __('Right', 'supercraft-anim'),
                'top' => __('Top', 'supercraft-anim'),
                'bottom' => __('Bottom', 'supercraft-anim'),
                'custom' => __('Custom', 'supercraft-anim'),
            ],
            'default' => 'center',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
            ],
        ]
    );

    $element->add_control(
        'supercraft_container_scrub',
        [
            'label' => __('Enable Scroll Scrub', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
            ],
        ]
    );

    // Play button for container animations (non-scrub)
    $element->add_control(
        'supercraft_preview_play_btn_container',
        [
            'label' => __('Play in Editor', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::BUTTON,
            'text' => __('Play', 'supercraft-anim'),
            'event' => 'supercraft_preview_play',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
                'supercraft_container_scrub!' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_container_forward',
        [
            'label' => __('Forward Only', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
                'supercraft_container_scrub' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_container_direction',
        [
            'label' => __('Direction', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'center' => __('Center', 'supercraft-anim'),
                'left' => __('Left', 'supercraft-anim'),
                'right' => __('Right', 'supercraft-anim'),
                'top' => __('Top', 'supercraft-anim'),
                'bottom' => __('Bottom', 'supercraft-anim'),
            ],
            'default' => 'center',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
                'supercraft_container_preset' => 'custom',
            ],
        ]
    );

    $element->add_control(
        'supercraft_container_duration',
        [
            'label' => __('Duration (s)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'step' => 0.1,
            'default' => 1.2,
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
            ],
        ]
    );

    $element->add_control(
        'supercraft_container_delay',
        [
            'label' => __('Delay (s)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'step' => 0.1,
            'default' => 0,
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
            ],
        ]
    );

    $element->add_control(
        'supercraft_container_ease',
        [
            'label' => __('Ease', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $ease_options,
            'default' => 'power2.out',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
            ],
        ]
    );

    $element->add_control(
        'supercraft_container_trigger',
        [
            'label' => __('Trigger', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 85%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
                'supercraft_container_scrub!' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_container_scroll_start',
        [
            'label' => __('Scroll Start (for scroll variant)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 85%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
                'supercraft_container_scrub' => 'yes',
            ],
        ]
    );

    $element->add_control(
        'supercraft_container_scroll_end',
        [
            'label' => __('Scroll End (for scroll variant)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 20%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'container-reveal',
                'supercraft_container_scrub' => 'yes',
            ],
        ]
    );

    // Scroll Fill Text minimal controls
    $element->add_control(
        'supercraft_fill_start',
        [
            'label' => __('Scroll Start', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 85%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-fill-text',
            ],
        ]
    );

    $element->add_control(
        'supercraft_fill_end',
        [
            'label' => __('Scroll End', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'top 60%',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-fill-text',
            ],
        ]
    );

    $element->add_control(
        'supercraft_fill_base',
        [
            'label' => __('Base Color (unfilled)', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::COLOR,
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-fill-text',
            ],
        ]
    );

    $element->add_control(
        'supercraft_fill_line',
        [
            'label' => __('Line by Line', 'supercraft-anim'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'frontend_available' => false,
            'condition' => [
                'supercraft_anim_category' => 'scroll-fill-text',
            ],
        ]
    );

    $element->end_controls_section();
};

add_action('elementor/element/common/_section_style/after_section_end', $supercraft_controls_callback, 10, 2);
add_action('elementor/element/section/after_section_end', $supercraft_controls_callback, 10, 2);
add_action('elementor/element/column/after_section_end', $supercraft_controls_callback, 10, 2);
add_action('elementor/element/container/after_section_end', $supercraft_controls_callback, 10, 2);
# Also hook specific container sections to ensure visibility
add_action('elementor/element/container/section_layout/after_section_end', $supercraft_controls_callback, 10, 2);
add_action('elementor/element/container/section_advanced/after_section_end', $supercraft_controls_callback, 10, 2);

// Only register icon controls if validated (dev mode always allowed)
if (supercraft_is_validated()) {
add_action('elementor/editor/after_enqueue_scripts', function () {
    $icon_url = plugins_url('favicon.webp', __FILE__);
    $css = "
    .elementor-panel .elementor-control-section_supercraft_anim_section .elementor-panel-heading .elementor-panel-heading-title:before {
        content: '';
        display: inline-block;
        width: 1em;
        height: 1em;
        margin-right: 6px;
        background: url('{$icon_url}') center center / contain no-repeat;
        vertical-align: middle;
    }";
    // Attach inline style to an existing editor handle
    wp_register_style('superanimate-editor-icon', false);
    wp_enqueue_style('superanimate-editor-icon');
    wp_add_inline_style('superanimate-editor-icon', $css);
});

// Apply classes/data attributes on render
function supercraft_apply_attrs($element) {
    // Prefer raw settings; fallback to display; final fallback to element data (editor mode)
    $settings = method_exists($element, 'get_settings') ? $element->get_settings() : $element->get_settings_for_display();
    if (empty($settings) && method_exists($element, 'get_data')) {
        $data = $element->get_data();
        if (!empty($data['settings']) && is_array($data['settings'])) {
            $settings = $data['settings'];
        }
    }
    $cat = $settings['supercraft_anim_category'] ?? '';
    if (!$cat) {
        return;
    }

    $classes = [];
    $styles = [];
    $data = [];

    // Preview flags
    if (!empty($settings['supercraft_preview_play'])) {
        $data['data-supercraft-preview-play'] = 'yes';
    }
    if ($cat === 'scroll-transform' && ($settings['supercraft_scroll_preset'] ?? '') === 'custom') {
        if (!empty($settings['supercraft_preview_state'])) {
            $data['data-preview-state'] = $settings['supercraft_preview_state'];
        }
    }

    switch ($cat) {
        case 'scroll-transform':
            $isScrub = !empty($settings['supercraft_scroll_scrub']);
            $classes[] = $isScrub ? 'scroll-transform-scrub' : 'scroll-transform';
            $preset = $settings['supercraft_scroll_preset'] ?? 'fade-up';
            if ($preset && $preset !== 'custom') {
                $classes[] = $preset;
                // Ensure preset values are applied inline for reliability (scrub + non-scrub)
                $presetMap = [
                    'fade-left' => [
                        '--transform-start-x' => '-100px',
                        '--transform-end-x' => '0px',
                        '--transform-end-opacity' => '1',
                    ],
                    'fade-right' => [
                        '--transform-start-x' => '100px',
                        '--transform-end-x' => '0px',
                        '--transform-end-opacity' => '1',
                    ],
                    'fade-up' => [
                        '--transform-start-y' => '50px',
                        '--transform-end-y' => '0px',
                        '--transform-end-opacity' => '1',
                    ],
                    'fade-down' => [
                        '--transform-start-y' => '-50px',
                        '--transform-end-y' => '0px',
                        '--transform-end-opacity' => '1',
                    ],
                    'zoom-in' => [
                        '--transform-start-scale' => '0.8',
                        '--transform-end-scale' => '1',
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                    'zoom-out' => [
                        '--transform-start-scale' => '1.2',
                        '--transform-end-scale' => '1',
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                    'blur-fade' => [
                        '--transform-start-blur' => '20px',
                        '--transform-end-blur' => '0px',
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                    'blur-fade-left' => [
                        '--transform-start-x' => '-100px',
                        '--transform-end-x' => '0px',
                        '--transform-start-blur' => '20px',
                        '--transform-end-blur' => '0px',
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                    'blur-fade-right' => [
                        '--transform-start-x' => '100px',
                        '--transform-end-x' => '0px',
                        '--transform-start-blur' => '20px',
                        '--transform-end-blur' => '0px',
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                    'blur-fade-up' => [
                        '--transform-start-y' => '50px',
                        '--transform-end-y' => '0px',
                        '--transform-start-blur' => '20px',
                        '--transform-end-blur' => '0px',
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                    'blur-fade-down' => [
                        '--transform-start-y' => '-50px',
                        '--transform-end-y' => '0px',
                        '--transform-start-blur' => '20px',
                        '--transform-end-blur' => '0px',
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                    'blur-zoom-in' => [
                        '--transform-start-scale' => '0.8',
                        '--transform-end-scale' => '1',
                        '--transform-start-blur' => '15px',
                        '--transform-end-blur' => '0px',
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                    'blur-zoom-out' => [
                        '--transform-start-scale' => '1.2',
                        '--transform-end-scale' => '1',
                        '--transform-start-blur' => '15px',
                        '--transform-end-blur' => '0px',
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                    'fade' => [
                        '--transform-start-opacity' => '0',
                        '--transform-end-opacity' => '1',
                    ],
                ];
                if (isset($presetMap[$preset])) {
                    foreach ($presetMap[$preset] as $var => $val) {
                        $styles[] = $var . ':' . $val;
                    }
                }
                if ($isScrub) {
                    if (!empty($settings['supercraft_scrub_start'])) {
                        $styles[] = '--transform-scroll-start:' . esc_attr($settings['supercraft_scrub_start']);
                    }
                    if (!empty($settings['supercraft_scrub_end'])) {
                        $styles[] = '--transform-scroll-end:' . esc_attr($settings['supercraft_scrub_end']);
                    }
                    if (!empty($settings['supercraft_scrub_ease'])) {
                        $styles[] = '--transform-ease:' . esc_attr($settings['supercraft_scrub_ease']);
                    }
                    if (!empty($settings['supercraft_scrub_forward'])) {
                        $data['data-transform-forward-only'] = 'true';
                    }
                } else {
                if (!empty($settings['supercraft_trigger'])) {
                    $styles[] = '--transform-trigger:' . esc_attr($settings['supercraft_trigger']);
                }
                if ($settings['supercraft_preset_duration'] !== '' && $settings['supercraft_preset_duration'] !== null) {
                    $styles[] = '--transform-duration:' . esc_attr($settings['supercraft_preset_duration']) . 's';
                }
                if ($settings['supercraft_delay'] !== '' && $settings['supercraft_delay'] !== null) {
                    $styles[] = '--transform-delay:' . esc_attr($settings['supercraft_delay']) . 's';
                }
                if (!empty($settings['supercraft_ease'])) {
                    $styles[] = '--transform-ease:' . esc_attr($settings['supercraft_ease']);
                    }
                }
            } else {
                // custom
                $map = [
                    'supercraft_ct_start_x' => '--transform-start-x',
                    'supercraft_ct_start_y' => '--transform-start-y',
                    'supercraft_ct_start_rotate' => '--transform-start-rotate',
                    'supercraft_ct_start_scale' => '--transform-start-scale',
                    'supercraft_ct_start_opacity' => '--transform-start-opacity',
                    'supercraft_ct_start_blur' => '--transform-start-blur',
                    'supercraft_ct_end_x' => '--transform-end-x',
                    'supercraft_ct_end_y' => '--transform-end-y',
                    'supercraft_ct_end_rotate' => '--transform-end-rotate',
                    'supercraft_ct_end_scale' => '--transform-end-scale',
                    'supercraft_ct_end_opacity' => '--transform-end-opacity',
                    'supercraft_ct_end_blur' => '--transform-end-blur',
                    'supercraft_ct_duration' => '--transform-duration',
                    'supercraft_ct_delay' => '--transform-delay',
                    'supercraft_ct_ease' => '--transform-ease',
                    'supercraft_ct_trigger' => '--transform-trigger',
                ];
                foreach ($map as $key => $var) {
                    if ($settings[$key] !== '' && $settings[$key] !== null) {
                        $val = $settings[$key];
                        if (strpos($key, 'delay') !== false || strpos($key, 'duration') !== false) {
                            $val .= 's';
                        } elseif (strpos($key, 'rotate') !== false) {
                            $val .= 'deg';
                        } elseif (strpos($key, 'blur') !== false) {
                            $val .= 'px';
                        } elseif (strpos($key, '_x') !== false || strpos($key, '_y') !== false) {
                            $val .= 'px';
                        }
                        // For scrub mode, duration/delay/trigger are ignored; map only relevant vars
                        if ($isScrub) {
                            // Skip duration/delay/trigger when scrub is enabled
                            if (strpos($key, 'duration') !== false || strpos($key, 'delay') !== false || strpos($key, 'trigger') !== false) {
                                continue;
                            }
                        }
                        $styles[] = $var . ':' . esc_attr($val);
                    }
                }
                // Default start opacity to 1 for custom transforms if left blank
                if (!isset($settings['supercraft_ct_start_opacity']) || $settings['supercraft_ct_start_opacity'] === '' || $settings['supercraft_ct_start_opacity'] === null) {
                    $styles[] = '--transform-start-opacity:1';
                }
                if ($isScrub) {
                    if (!empty($settings['supercraft_scrub_start'])) {
                        $styles[] = '--transform-scroll-start:' . esc_attr($settings['supercraft_scrub_start']);
                    }
                    if (!empty($settings['supercraft_scrub_end'])) {
                        $styles[] = '--transform-scroll-end:' . esc_attr($settings['supercraft_scrub_end']);
                    }
                    if (!empty($settings['supercraft_scrub_ease'])) {
                        $styles[] = '--transform-ease:' . esc_attr($settings['supercraft_scrub_ease']);
                    }
                    if (!empty($settings['supercraft_scrub_forward'])) {
                        $data['data-transform-forward-only'] = 'true';
                    }
                }
            }
            break;

        case 'split-text':
            $mode = $settings['supercraft_split_mode'] ?? 'chars';
            // Fallback to legacy variant if present
            $variant = $mode === 'words'
                ? ($settings['supercraft_split_variant_word'] ?? ($settings['supercraft_split_variant'] ?? 'fade-x'))
                : ($settings['supercraft_split_variant_char'] ?? ($settings['supercraft_split_variant'] ?? 'fade-x'));
            $preset = $settings['supercraft_split_preset'] ?? 'medium';
            $isScrubSplit = !empty($settings['supercraft_split_scrub']);
            // Preset mappings
            if ($preset !== 'custom') {
                $isWord = ($mode === 'words');
                $offsetDefault = $preset === 'light' ? 15 : ($preset === 'dramatic' ? 50 : 30);
                $staggerDefault = $isWord
                    ? ($preset === 'light' ? 0.06 : ($preset === 'dramatic' ? 0.12 : 0.1))
                    : ($preset === 'light' ? 0.04 : ($preset === 'dramatic' ? 0.08 : 0.05));
                $durationDefault = $preset === 'light' ? 1.0 : ($preset === 'dramatic' ? 1.8 : 1.5);
                $isBlurVariant = ($variant === 'fade-blur');
                $isOffsetY = ($variant === 'fade-y' || $variant === 'fade-blur');
                $blurDefault = $isBlurVariant
                    ? ($preset === 'light' ? 10 : ($preset === 'dramatic' ? 20 : 15))
                    : null;
                $styles[] = ($isWord ? '--word-offset-x' : '--char-offset-x') . ':' . $offsetDefault . 'px';
                $styles[] = ($isWord ? '--word-offset-y' : '--char-offset-y') . ':' . ($isOffsetY ? $offsetDefault : 0) . 'px';
                $styles[] = ($isWord ? '--word-stagger' : '--char-stagger') . ':' . $staggerDefault . 's';
                $styles[] = ($isWord ? '--word-duration' : '--char-duration') . ':' . $durationDefault . 's';
                $styles[] = ($isWord ? '--word-opacity-start' : '--char-opacity-start') . ':0';
                if ($blurDefault !== null) {
                    $styles[] = ($isWord ? '--word-blur-start' : '--char-blur-start') . ':' . $blurDefault . 'px';
                }
                if ($isScrubSplit) {
                    $styles[] = ($isWord ? '--word-scroll-start' : '--char-scroll-start') . ':' . (!empty($settings['supercraft_split_scroll_start']) ? esc_attr($settings['supercraft_split_scroll_start']) : 'top 85%');
                    $styles[] = ($isWord ? '--word-scroll-end' : '--char-scroll-end') . ':' . (!empty($settings['supercraft_split_scroll_end']) ? esc_attr($settings['supercraft_split_scroll_end']) : 'top 40%');
                    if (!empty($settings['supercraft_split_forward'])) {
                        $data['data-split-forward-only'] = 'true';
                    }
                } else {
                    if (isset($settings['supercraft_split_delay']) && $settings['supercraft_split_delay'] !== '') {
                        $styles[] = '--animation-delay:' . esc_attr($settings['supercraft_split_delay']) . 's';
                    }
                }
            }
            if ($mode === 'words') {
                if ($variant === 'fade-y') {
                    $classes[] = $isScrubSplit ? 'split-text-word-fade-y-scroll' : 'split-text-word-fade-y';
                } elseif ($variant === 'fade-blur') {
                    $classes[] = $isScrubSplit ? 'split-text-word-fade-y-blur-scroll' : 'split-text-word-fade-y-blur';
                } else {
                    $classes[] = $isScrubSplit ? 'split-text-word-fade-scroll' : 'split-text-word-fade';
                }
            } else {
                if ($variant === 'fade-y') {
                    $classes[] = $isScrubSplit ? 'split-text-char-fade-y-scroll' : 'split-text-char-fade-y';
                } elseif ($variant === 'fade-blur') {
                    $classes[] = $isScrubSplit ? 'split-text-char-fade-y-blur-scroll' : 'split-text-char-fade-y-blur';
                } else {
                    $classes[] = $isScrubSplit ? 'split-text-char-fade-scroll' : 'split-text-char-fade';
                }
            }
            if ($preset === 'custom') {
                $map = [
                    'supercraft_split_offset_x' => $mode === 'words' ? '--word-offset-x' : '--char-offset-x',
                    'supercraft_split_offset_y' => $mode === 'words' ? '--word-offset-y' : '--char-offset-y',
                    'supercraft_split_stagger' => $mode === 'words' ? '--word-stagger' : '--char-stagger',
                    'supercraft_split_duration' => $mode === 'words' ? '--word-duration' : '--char-duration',
                    'supercraft_split_opacity_start' => $mode === 'words' ? '--word-opacity-start' : '--char-opacity-start',
                    'supercraft_split_blur_start' => $mode === 'words' ? '--word-blur-start' : '--char-blur-start',
                ];
                foreach ($map as $key => $var) {
                    if ($settings[$key] !== '' && $settings[$key] !== null) {
                        $val = $settings[$key];
                        if (strpos($key, 'stagger') !== false || strpos($key, 'duration') !== false) {
                            $val .= 's';
                        } elseif (strpos($key, 'offset') !== false || strpos($key, 'blur') !== false) {
                            $val .= 'px';
                        }
                        $styles[] = $var . ':' . esc_attr($val);
                    }
                }
                if (!empty($settings['supercraft_split_ease'])) {
                    $styles[] = ($mode === 'words' ? '--word-ease:' : '--char-ease:') . esc_attr($settings['supercraft_split_ease']);
                }
                if ($isScrubSplit) {
                    $styles[] = ($mode === 'words' ? '--word-scroll-start' : '--char-scroll-start') . ':' . (!empty($settings['supercraft_split_scroll_start']) ? esc_attr($settings['supercraft_split_scroll_start']) : 'top 85%');
                    $styles[] = ($mode === 'words' ? '--word-scroll-end' : '--char-scroll-end') . ':' . (!empty($settings['supercraft_split_scroll_end']) ? esc_attr($settings['supercraft_split_scroll_end']) : 'top 40%');
                    if (!empty($settings['supercraft_split_forward'])) {
                        $data['data-split-forward-only'] = 'true';
                    }
                } else {
                    if (isset($settings['supercraft_split_delay']) && $settings['supercraft_split_delay'] !== '') {
                        $styles[] = '--animation-delay:' . esc_attr($settings['supercraft_split_delay']) . 's';
                    }
                }
            }
            break;

        case 'image-reveal':
            $classes[] = 'image-reveal';
            $dir = $settings['supercraft_image_preset'] ?? 'left';
            if ($dir === 'custom') {
                $dir = $settings['supercraft_image_direction'] ?? 'left';
            }
            $classes[] = 'image-reveal-' . $dir;
            if ($settings['supercraft_image_duration'] !== '' && $settings['supercraft_image_duration'] !== null) {
                $styles[] = '--reveal-duration:' . esc_attr($settings['supercraft_image_duration']) . 's';
            }
            if ($settings['supercraft_image_delay'] !== '' && $settings['supercraft_image_delay'] !== null) {
                $styles[] = '--reveal-delay:' . esc_attr($settings['supercraft_image_delay']) . 's';
            }
            if (!empty($settings['supercraft_image_ease'])) {
                $styles[] = '--reveal-ease:' . esc_attr($settings['supercraft_image_ease']);
            }
            if (!empty($settings['supercraft_image_trigger'])) {
                $styles[] = '--reveal-trigger:' . esc_attr($settings['supercraft_image_trigger']);
            }
            if ($settings['supercraft_image_scale'] !== '' && $settings['supercraft_image_scale'] !== null) {
                $styles[] = '--reveal-image-scale:' . esc_attr($settings['supercraft_image_scale']);
            }
            break;

        case 'container-reveal':
            $isContainerScrub = !empty($settings['supercraft_container_scrub']);
            $classes[] = $isContainerScrub ? 'container-reveal-scroll' : 'container-reveal';
            $dir = $settings['supercraft_container_preset'] ?? 'center';
            if ($dir === 'custom') {
                $dir = $settings['supercraft_container_direction'] ?? 'center';
            }
            $classes[] = 'container-reveal-' . $dir;
            if ($settings['supercraft_container_duration'] !== '' && $settings['supercraft_container_duration'] !== null) {
                $styles[] = '--reveal-duration:' . esc_attr($settings['supercraft_container_duration']) . 's';
            }
            if ($settings['supercraft_container_delay'] !== '' && $settings['supercraft_container_delay'] !== null) {
                $styles[] = '--animation-delay:' . esc_attr($settings['supercraft_container_delay']) . 's';
            }
            if (!empty($settings['supercraft_container_ease'])) {
                $styles[] = '--reveal-ease:' . esc_attr($settings['supercraft_container_ease']);
            }
            if ($isContainerScrub) {
                if (!empty($settings['supercraft_container_scroll_start'])) {
                    $styles[] = '--reveal-scroll-start:' . esc_attr($settings['supercraft_container_scroll_start']);
                }
                if (!empty($settings['supercraft_container_scroll_end'])) {
                    $styles[] = '--reveal-scroll-end:' . esc_attr($settings['supercraft_container_scroll_end']);
                }
                if (!empty($settings['supercraft_container_forward'])) {
                    $data['data-reveal-forward-only'] = 'true';
                }
            } else {
                if (!empty($settings['supercraft_container_trigger'])) {
                    $styles[] = '--reveal-trigger:' . esc_attr($settings['supercraft_container_trigger']);
                }
            }
            break;

        case 'scroll-fill-text':
            $classes[] = 'scroll-fill-text';
            if (!empty($settings['supercraft_fill_start'])) {
                $styles[] = '--scroll-fill-start:' . esc_attr($settings['supercraft_fill_start']);
                $data['data-scroll-fill-start'] = esc_attr($settings['supercraft_fill_start']);
            }
            if (!empty($settings['supercraft_fill_end'])) {
                $styles[] = '--scroll-fill-end:' . esc_attr($settings['supercraft_fill_end']);
                $data['data-scroll-fill-end'] = esc_attr($settings['supercraft_fill_end']);
            }
            // Resolve base color (supports Elementor globals)
            $baseColor = '';
            if (!empty($settings['supercraft_fill_base'])) {
                $baseColor = supercraft_normalize_color($settings['supercraft_fill_base']);
            }
            if (empty($baseColor) && !empty($settings['__globals__']['supercraft_fill_base'])) {
                $baseColor = supercraft_global_css_var($settings['__globals__']['supercraft_fill_base']);
            }
            if (!empty($baseColor)) {
                $styles[] = '--scroll-fill-base:' . esc_attr($baseColor);
                $data['data-scroll-fill-base'] = esc_attr($baseColor);
            }
            if (!empty($settings['supercraft_fill_line'])) {
                $data['data-scroll-fill-line'] = 'yes';
            }
            break;
    }

    if (!empty($classes)) {
        $element->add_render_attribute('_wrapper', 'class', $classes);
    }
    if (!empty($styles)) {
        $element->add_render_attribute('_wrapper', 'style', implode(';', $styles));
    }
    foreach ($data as $k => $v) {
        $element->add_render_attribute('_wrapper', $k, $v);
    }
}
function supercraft_normalize_color($val) {
    if (is_array($val)) {
        if (!empty($val['color'])) {
            return $val['color'];
        }
        if (!empty($val['value'])) {
            return $val['value'];
        }
    }
    return $val;
}

function supercraft_global_css_var($global) {
    if (empty($global)) {
        return '';
    }
    // Elementor globals can look like "id:abcdef" or "globals/colors?id=abcdef"
    // Extract the last segment after "=" or ":" and strip unsafe chars
    $raw = preg_replace('/^.*[=:]/', '', $global);
    $raw = preg_replace('/[^a-zA-Z0-9_-]/', '', $raw);
    if (empty($raw)) {
        return '';
    }
    return 'var(--e-global-color-' . $raw . ')';
}

/**
 * ==========================================
 * SUPABASE VALIDATION SYSTEM
 * ==========================================
 */

function supercraft_get_validation_status() {
    return get_option('supercraft_validation_status', 'not_set');
}

function supercraft_get_embed_code() {
    return get_option('supercraft_embed_code', '');
}

function supercraft_get_last_validated() {
    return get_option('supercraft_last_validated', '');
}

function supercraft_validate_embed_code_standalone($embed_code) {
    if (empty($embed_code) || !defined('SUPERCRAFT_SUPABASE_URL')) {
        return false;
    }

    $code_col = defined('SUPERCRAFT_CODE_COLUMN') ? SUPERCRAFT_CODE_COLUMN : 'embed_public_key';
    $plugin_name = defined('SUPERCRAFT_PLUGIN_NAME') ? SUPERCRAFT_PLUGIN_NAME : 'supercraft-superanimation';
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

    // Step 1: Check if embed code exists in projects table
    $url = SUPERCRAFT_SUPABASE_URL . '/rest/v1/' . SUPERCRAFT_TABLE . '?select=id&' . $code_col . '=eq.' . urlencode($embed_code);

    $response = wp_remote_get($url, [
        'headers' => [
            'apikey' => SUPERCRAFT_SUPABASE_ANON_KEY,
            'Authorization' => 'Bearer ' . SUPERCRAFT_SUPABASE_ANON_KEY,
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data) || !is_array($data)) {
        return false;
    }

    $project_id = $data[0]['id'];

    // Step 2: Check if already registered to different domain
    $reg_url = SUPERCRAFT_SUPABASE_URL . '/rest/v1/project_plugin_registrations?project_id=eq.' . $project_id . '&plugin_name=eq.' . urlencode($plugin_name);

    $reg_response = wp_remote_get($reg_url, [
        'headers' => [
            'apikey' => SUPERCRAFT_SUPABASE_ANON_KEY,
            'Authorization' => 'Bearer ' . SUPERCRAFT_SUPABASE_ANON_KEY,
        ],
        'timeout' => 15,
    ]);

    $reg_body = wp_remote_retrieve_body($reg_response);
    $reg_data = json_decode($reg_body, true);

    if (!empty($reg_data) && is_array($reg_data)) {
        // Already registered - check same domain
        $existing_domain = isset($reg_data[0]['registered_domain']) ? $reg_data[0]['registered_domain'] : '';
        if (!empty($existing_domain) && $existing_domain !== $domain) {
            return false; // Already used on different domain
        }
    } else {
        // No registration yet - insert new
        $insert_response = wp_remote_post(SUPERCRAFT_SUPABASE_URL . '/rest/v1/project_plugin_registrations', [
            'headers' => [
                'apikey' => SUPERCRAFT_SUPABASE_ANON_KEY,
                'Authorization' => 'Bearer ' . SUPERCRAFT_SUPABASE_ANON_KEY,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=minimal',
            ],
            'body' => json_encode([
                'project_id' => $project_id,
                'plugin_name' => $plugin_name,
                'registered_domain' => $domain,
            ]),
        ]);
    }

    return true;
}

// Add action for save (outside validation block)
add_action('admin_post_supercraft_save_embed_code', function() {
    check_admin_referer('supercraft_save_settings');
    $code = isset($_POST['supercraft_embed_code']) ? sanitize_text_field($_POST['supercraft_embed_code']) : '';
    update_option('supercraft_embed_code', $code);
    if (!empty($code)) {
        $valid = supercraft_validate_embed_code_standalone($code);
        update_option('supercraft_validation_status', $valid ? 'valid' : 'invalid');
    } else {
        update_option('supercraft_validation_status', 'not_set');
    }
    update_option('supercraft_last_validated', current_time('mysql'));
    wp_redirect(add_query_arg('updated', 'true', wp_get_referer()));
    exit;
});

// Add action for re-validate
add_action('admin_post_supercraft_validate_now', function() {
    check_admin_referer('supercraft_validate');
    $code = get_option('supercraft_embed_code', '');
    if (!empty($code)) {
        $valid = supercraft_validate_embed_code_standalone($code);
        update_option('supercraft_validation_status', $valid ? 'valid' : 'invalid');
        update_option('supercraft_last_validated', current_time('mysql'));
    }
    wp_redirect(add_query_arg('updated', 'true', wp_get_referer()));
    exit;
});

} // End if supercraft_is_validated()
function supercraft_schedule_validation() {
    if (!wp_next_scheduled('supercraft_daily_validation')) {
        wp_schedule_event(time(), 'daily', 'supercraft_daily_validation');
    }
}
add_action('wp', 'supercraft_schedule_validation');

function supercraft_daily_validation_event() {
    $code = supercraft_get_embed_code();
    if (!empty($code)) {
        $valid = supercraft_validate_embed_code($code);
        update_option('supercraft_validation_status', $valid ? 'valid' : 'invalid');
        update_option('supercraft_last_validated', current_time('mysql'));
    }
}
add_action('supercraft_daily_validation', 'supercraft_daily_validation_event');

// Show admin notice if not validated
function supercraft_admin_notice() {
    if (!defined('SUPERCRAFT_SUPABASE_URL')) {
        return;
    }

    $status = supercraft_get_validation_status();
    if ($status === 'invalid') {
        echo '<div class="notice notice-warning is-dismissible">
            <p><strong>Supercraft Animations:</strong> Embed code is invalid. Animations are disabled. <a href="' . admin_url('admin.php?page=supercraft-animations') . '">Enter a valid embed code</a>.</p>
        </div>';
    }
}
add_action('admin_notices', 'supercraft_admin_notice');

// Admin pages (always accessible)
function supercraft_render_admin_page() {
    $status = get_option('supercraft_validation_status', 'not_set');
    $embed_code = get_option('supercraft_embed_code', '');
    $last_validated = get_option('supercraft_last_validated', '');
    $show_all_tabs = defined('SUPERCRAFT_SUPABASE_URL');

    ?>
    <div class="wrap">
        <h1>Supercraft Animations</h1>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>Settings saved.</p>
            </div>
        <?php endif; ?>

        <?php if (!$show_all_tabs): ?>
            <div class="notice notice-error">
                <p>Configuration file missing.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('supercraft_save_settings'); ?>
            <input type="hidden" name="action" value="supercraft_save_embed_code">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="supercraft_embed_code">Embed Code</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="supercraft_embed_code" 
                               name="supercraft_embed_code" 
                               class="regular-text" 
                               value="<?php echo esc_attr($embed_code); ?>"
                               placeholder="Enter your embed code">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <?php if ($status === 'valid'): ?>
                            <span style="color: green; font-weight: bold;">Valid</span>
                        <?php elseif ($status === 'invalid'): ?>
                            <span style="color: red; font-weight: bold;">Invalid</span>
                        <?php else: ?>
                            <span style="color: gray;">Not Set</span>
                        <?php endif; ?>
                        <?php if ($last_validated): ?>
                            <p class="description">Last validated: <?php echo esc_html($last_validated); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save & Validate'); ?>
        </form>
    </div>
    <?php
}

function supercraft_admin_menu() {
    add_menu_page(
        'Supercraft Settings',
        'Supercraft Animations',
        'manage_options',
        'supercraft-animations',
        'supercraft_render_admin_page',
        'dashicons-controls-play',
        80
    );
}
add_action('admin_menu', 'supercraft_admin_menu');

// Disable frontend animations when not validated
if (!supercraft_is_validated()) {
    remove_action('wp_enqueue_scripts', 'supercraft_anim_enqueue_assets');
    remove_action('elementor/frontend/after_enqueue_scripts', 'supercraft_anim_enqueue_assets');
    remove_action('elementor/preview/enqueue_scripts', 'supercraft_anim_enqueue_assets');
    remove_action('elementor/frontend/widget/before_render', 'supercraft_apply_attrs');
    remove_action('elementor/frontend/container/before_render', 'supercraft_apply_attrs');
}
