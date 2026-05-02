<?php
if (!defined('ABSPATH')) {
    exit;
}

function supercraft_apply_attrs($element) {
    if (!supercraft_is_validated()) {
        return;
    }
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
    $data_attrs = [];

    if (!empty($settings['supercraft_preview_play'])) {
        $data_attrs['data-supercraft-preview-play'] = 'yes';
    }
    if ($cat === 'scroll-transform' && ($settings['supercraft_scroll_preset'] ?? '') === 'custom') {
        if (!empty($settings['supercraft_preview_state'])) {
            $data_attrs['data-preview-state'] = $settings['supercraft_preview_state'];
        }
    }

    switch ($cat) {
        case 'scroll-transform':
            $isScrub = !empty($settings['supercraft_scroll_scrub']);
            $classes[] = $isScrub ? 'scroll-transform-scrub' : 'scroll-transform';
            $preset = $settings['supercraft_scroll_preset'] ?? 'fade-up';
            if ($preset && $preset !== 'custom') {
                $classes[] = $preset;
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
                        $data_attrs['data-transform-forward-only'] = 'true';
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
                        if ($isScrub) {
                            if (strpos($key, 'duration') !== false || strpos($key, 'delay') !== false || strpos($key, 'trigger') !== false) {
                                continue;
                            }
                        }
                        $styles[] = $var . ':' . esc_attr($val);
                    }
                }
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
                        $data_attrs['data-transform-forward-only'] = 'true';
                    }
                }
            }
            break;

        case 'split-text':
            $mode = $settings['supercraft_split_mode'] ?? 'chars';
            $variant = $mode === 'words'
                ? ($settings['supercraft_split_variant_word'] ?? ($settings['supercraft_split_variant'] ?? 'fade-x'))
                : ($settings['supercraft_split_variant_char'] ?? ($settings['supercraft_split_variant'] ?? 'fade-x'));
            $preset = $settings['supercraft_split_preset'] ?? 'medium';
            $isScrubSplit = !empty($settings['supercraft_split_scrub']);
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
                        $data_attrs['data-split-forward-only'] = 'true';
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
                        $data_attrs['data-split-forward-only'] = 'true';
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
                    $data_attrs['data-reveal-forward-only'] = 'true';
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
                $data_attrs['data-scroll-fill-start'] = esc_attr($settings['supercraft_fill_start']);
            }
            if (!empty($settings['supercraft_fill_end'])) {
                $styles[] = '--scroll-fill-end:' . esc_attr($settings['supercraft_fill_end']);
                $data_attrs['data-scroll-fill-end'] = esc_attr($settings['supercraft_fill_end']);
            }
            $baseColor = '';
            if (!empty($settings['supercraft_fill_base'])) {
                $baseColor = supercraft_normalize_color($settings['supercraft_fill_base']);
            }
            if (empty($baseColor) && !empty($settings['__globals__']['supercraft_fill_base'])) {
                $baseColor = supercraft_global_css_var($settings['__globals__']['supercraft_fill_base']);
            }
            if (!empty($baseColor)) {
                $styles[] = '--scroll-fill-base:' . esc_attr($baseColor);
                $data_attrs['data-scroll-fill-base'] = esc_attr($baseColor);
            }
            if (!empty($settings['supercraft_fill_line'])) {
                $data_attrs['data-scroll-fill-line'] = 'yes';
            }
            if (!empty($settings['supercraft_fill_forward'])) {
                $data_attrs['data-scroll-fill-forward-only'] = 'true';
            }
            break;
    }

    if (!empty($classes)) {
        $element->add_render_attribute('_wrapper', 'class', $classes);
    }
    if (!empty($styles)) {
        $element->add_render_attribute('_wrapper', 'style', implode(';', $styles));
    }
    foreach ($data_attrs as $k => $v) {
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
    $raw = preg_replace('/^.*[=:]/', '', $global);
    $raw = preg_replace('/[^a-zA-Z0-9_-]/', '', $raw);
    if (empty($raw)) {
        return '';
    }
    return 'var(--e-global-color-' . $raw . ')';
}