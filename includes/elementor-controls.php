<?php
if (!defined('ABSPATH')) {
    exit;
}

$supercraft_controls_callback = function ($element, $section_id) {
    if (!supercraft_is_validated()) {
        return;
    }

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
            'icon' => 'eicon-animation',
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
add_action('elementor/element/container/section_layout/after_section_end', $supercraft_controls_callback, 10, 2);
add_action('elementor/element/container/section_advanced/after_section_end', $supercraft_controls_callback, 10, 2);

add_action('elementor/editor/after_enqueue_scripts', function () {
    if (!supercraft_is_validated()) {
        return;
    }
    $icon_url = plugins_url('favicon.webp', dirname(__FILE__) . '/../supercraft-animations.php');
    $css = '
.elementor-panel .elementor-control-section_supercraft_anim_section .elementor-panel-heading .elementor-panel-heading-title:before {
    content: "";
    display: inline-block;
    width: 1em;
    height: 1em;
    margin-right: 6px;
    background: url(' . $icon_url . ') center center / contain no-repeat;
    vertical-align: middle;
}';
    wp_register_style('superanimate-editor-icon', false);
    wp_enqueue_style('superanimate-editor-icon');
    wp_add_inline_style('superanimate-editor-icon', $css);
});