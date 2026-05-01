// Shared helpers to track the currently selected widget across editor/preview contexts
const getActiveView = () => window.__supercraftActiveView || null;
const setActiveView = (view) => { window.__supercraftActiveView = view; };

// Track timeouts that reapply start-state after play, so we can cancel on deselect
const startResetTimers = new Map();

function clearStartResetTimer(el) {
  if (!el) return;
  const timer = startResetTimers.get(el);
  if (timer) {
    clearTimeout(timer);
    startResetTimers.delete(el);
  }
}

// Apply start state to an element using its current CSS custom properties (used for play reset)
function applyStartStateFromStyles(el) {
  if (!el) return;
  const styles = getComputedStyle(el);
  const getVal = (varName, fallback) => {
    const v = (styles.getPropertyValue(varName) || '').trim();
    return v || fallback;
  };
  const startX = getVal('--transform-start-x', '0px');
  const startY = getVal('--transform-start-y', '0px');
  const startR = getVal('--transform-start-rotate', '0deg');
  const startS = parseFloat(getVal('--transform-start-scale', '1')) || 1;
  const startO = parseFloat(getVal('--transform-start-opacity', '1'));
  const startB = getVal('--transform-start-blur', '0px');

  el.style.transform = `translateX(${startX}) translateY(${startY}) rotate(${startR}) scale(${startS})`;
  el.style.opacity = isNaN(startO) ? 1 : startO;
  el.style.filter = `blur(${startB})`;
}

(function () {
  // Wait for Elementor to be ready
  if (typeof elementor === 'undefined') {
    return;
  }

  const ANIM_CLASSES = [
    'scroll-transform',
    'scroll-transform-scrub',
    'split-text-char-fade',
    'split-text-char-fade-y',
    'split-text-char-fade-scroll',
    'split-text-char-fade-y-scroll',
    'split-text-word-fade',
    'split-text-word-fade-y',
    'split-text-word-fade-scroll',
    'split-text-word-fade-y-scroll',
    'split-text-word-fade-y-blur',
    'split-text-word-fade-y-blur-scroll',
    'split-text-char-fade-y-blur-scroll',
    'image-reveal',
    'image-reveal-left',
    'image-reveal-right',
    'image-reveal-top',
    'image-reveal-bottom',
    'container-reveal',
    'container-reveal-scroll',
    'container-reveal-center',
    'container-reveal-left',
    'container-reveal-right',
    'container-reveal-top',
    'container-reveal-bottom',
    'scroll-fill-text',
    'fade-left',
    'fade-right',
    'fade-up',
    'fade-down',
    'zoom-in',
    'zoom-out',
    'blur-fade',
    'blur-fade-left',
    'blur-fade-right',
    'blur-fade-up',
    'blur-fade-down',
    'blur-zoom-in',
    'blur-zoom-out',
    'fade'
  ];

  function hasSupercraftDecorations($el) {
    if (!$el || !$el.hasClass) return false;
    return $el.attr('data-supercraft-applied') === 'true';
  }

  function getSettingsAttributes(model) {
    if (!model || typeof model.get !== 'function') return null;
    const settingsModel = model.get('settings');
    if (!settingsModel) return null;
    if (settingsModel.attributes && typeof settingsModel.attributes === 'object') {
      return settingsModel.attributes;
    }
    if (typeof settingsModel.toJSON === 'function') {
      const json = settingsModel.toJSON();
      return json && typeof json === 'object' ? json : null;
    }
    return typeof settingsModel === 'object' ? settingsModel : null;
  }

  function hasSupercraftSettings(settings) {
    if (!settings || typeof settings !== 'object') return false;
    if (Object.prototype.hasOwnProperty.call(settings, 'supercraft_anim_category')) return true;
    return Object.keys(settings).some((key) => key.indexOf('supercraft_') === 0);
  }

  function stripAnimationClasses($el, force = false) {
    if (!$el || !$el.removeClass) return;
    // Never mutate non-Supercraft elements unless explicitly forced.
    if (!force && !hasSupercraftDecorations($el)) return;
    $el.removeClass(ANIM_CLASSES.join(' '));

    // Remove custom prop styles we add; keep unrelated inline styles
    const currentStyle = $el.attr('style') || '';
    const newStyle = currentStyle
      .split(';')
      .map((s) => s.trim())
      .filter(
        (s) =>
          s &&
          !s.startsWith('--transform-') &&
          !s.startsWith('--word-') &&
          !s.startsWith('--char-') &&
          !s.startsWith('--reveal-') &&
          !s.startsWith('--scroll-fill-') &&
          !s.startsWith('--animation-')
      )
      .join(';');
    if (newStyle) {
      $el.attr('style', newStyle);
    } else {
      $el.removeAttr('style');
    }

    // Drop data flags we set
    [
      'data-preview-state',
      'data-transform-forward-only',
      'data-split-forward-only',
      'data-reveal-forward-only',
      'data-scroll-fill-base'
    ].forEach((attr) => $el.removeAttr(attr));
    $el.removeAttr('data-supercraft-applied');
  }

  // Apply animation classes AND inline styles based on widget settings in the editor
  function applyAnimationClasses(view) {
    if (!view || !view.$el) return;

    const model = view.model;
    const $el = view.$el;
    const settings = getSettingsAttributes(model) || {};

    const cat = settings.supercraft_anim_category || '';
    const hasDecor = hasSupercraftDecorations($el); // only true when we previously applied
    if (!cat && !hasDecor) return; // Not ours; do nothing
    if (!cat && hasDecor) {
      stripAnimationClasses($el); // User cleared the setting; clean up our traces
      return;
    }

    stripAnimationClasses($el);
    $el.attr('data-supercraft-applied', 'true');

    const styles = [];

    // Apply classes and styles based on category
    switch (cat) {
      case 'scroll-transform':
        const isScrub = settings.supercraft_scroll_scrub === 'yes';
        $el.addClass(isScrub ? 'scroll-transform-scrub' : 'scroll-transform');
        
        const preset = settings.supercraft_scroll_preset || 'fade-up';
        if (preset && preset !== 'custom') {
          $el.addClass(preset);
          
          // Apply preset styles (matching PHP logic)
          const presetMap = {
            'fade-left': {
              '--transform-start-x': '-100px',
              '--transform-end-x': '0px',
              '--transform-end-opacity': '1'
            },
            'fade-right': {
              '--transform-start-x': '100px',
              '--transform-end-x': '0px',
              '--transform-end-opacity': '1'
            },
            'fade-up': {
              '--transform-start-y': '50px',
              '--transform-end-y': '0px',
              '--transform-end-opacity': '1'
            },
            'fade-down': {
              '--transform-start-y': '-50px',
              '--transform-end-y': '0px',
              '--transform-end-opacity': '1'
            },
            'zoom-in': {
              '--transform-start-scale': '0.8',
              '--transform-end-scale': '1',
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            },
            'zoom-out': {
              '--transform-start-scale': '1.2',
              '--transform-end-scale': '1',
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            },
            'blur-fade': {
              '--transform-start-blur': '20px',
              '--transform-end-blur': '0px',
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            },
            'blur-fade-left': {
              '--transform-start-x': '-100px',
              '--transform-end-x': '0px',
              '--transform-start-blur': '20px',
              '--transform-end-blur': '0px',
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            },
            'blur-fade-right': {
              '--transform-start-x': '100px',
              '--transform-end-x': '0px',
              '--transform-start-blur': '20px',
              '--transform-end-blur': '0px',
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            },
            'blur-fade-up': {
              '--transform-start-y': '50px',
              '--transform-end-y': '0px',
              '--transform-start-blur': '20px',
              '--transform-end-blur': '0px',
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            },
            'blur-fade-down': {
              '--transform-start-y': '-50px',
              '--transform-end-y': '0px',
              '--transform-start-blur': '20px',
              '--transform-end-blur': '0px',
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            },
            'blur-zoom-in': {
              '--transform-start-scale': '0.8',
              '--transform-end-scale': '1',
              '--transform-start-blur': '15px',
              '--transform-end-blur': '0px',
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            },
            'blur-zoom-out': {
              '--transform-start-scale': '1.2',
              '--transform-end-scale': '1',
              '--transform-start-blur': '15px',
              '--transform-end-blur': '0px',
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            },
            'fade': {
              '--transform-start-opacity': '0',
              '--transform-end-opacity': '1'
            }
          };
          
          if (presetMap[preset]) {
            Object.entries(presetMap[preset]).forEach(([key, val]) => {
              styles.push(`${key}:${val}`);
            });
          }
          
          if (isScrub) {
            if (settings.supercraft_scrub_start) {
              styles.push(`--transform-scroll-start:${settings.supercraft_scrub_start}`);
            }
            if (settings.supercraft_scrub_end) {
              styles.push(`--transform-scroll-end:${settings.supercraft_scrub_end}`);
            }
            if (settings.supercraft_scrub_ease) {
              styles.push(`--transform-ease:${settings.supercraft_scrub_ease}`);
            }
            if (settings.supercraft_scrub_forward === 'yes') {
              $el.attr('data-transform-forward-only', 'true');
            }
          } else {
            if (settings.supercraft_trigger) {
              styles.push(`--transform-trigger:${settings.supercraft_trigger}`);
            }
            if (settings.supercraft_preset_duration !== '' && settings.supercraft_preset_duration !== null) {
              styles.push(`--transform-duration:${settings.supercraft_preset_duration}s`);
            }
            if (settings.supercraft_delay !== '' && settings.supercraft_delay !== null) {
              styles.push(`--transform-delay:${settings.supercraft_delay}s`);
            }
            if (settings.supercraft_ease) {
              styles.push(`--transform-ease:${settings.supercraft_ease}`);
            }
          }
        } else {
          // Custom preset
          const map = {
            'supercraft_ct_start_x': ['--transform-start-x', 'px'],
            'supercraft_ct_start_y': ['--transform-start-y', 'px'],
            'supercraft_ct_start_rotate': ['--transform-start-rotate', 'deg'],
            'supercraft_ct_start_scale': ['--transform-start-scale', ''],
            'supercraft_ct_start_opacity': ['--transform-start-opacity', ''],
            'supercraft_ct_start_blur': ['--transform-start-blur', 'px'],
            'supercraft_ct_end_x': ['--transform-end-x', 'px'],
            'supercraft_ct_end_y': ['--transform-end-y', 'px'],
            'supercraft_ct_end_rotate': ['--transform-end-rotate', 'deg'],
            'supercraft_ct_end_scale': ['--transform-end-scale', ''],
            'supercraft_ct_end_opacity': ['--transform-end-opacity', ''],
            'supercraft_ct_end_blur': ['--transform-end-blur', 'px'],
            'supercraft_ct_duration': ['--transform-duration', 's'],
            'supercraft_ct_delay': ['--transform-delay', 's'],
            'supercraft_ct_ease': ['--transform-ease', ''],
            'supercraft_ct_trigger': ['--transform-trigger', '']
          };
          
          Object.entries(map).forEach(([key, [cssVar, unit]]) => {
            if (settings[key] !== '' && settings[key] !== null && settings[key] !== undefined) {
              // Skip duration/delay/trigger when scrub is enabled
              if (isScrub && (key.includes('duration') || key.includes('delay') || key.includes('trigger'))) {
                return;
              }
              styles.push(`${cssVar}:${settings[key]}${unit}`);
            }
          });
          
          // Default start opacity to 1 if not set, so custom transforms stay visible on live/preset render
          if (settings.supercraft_ct_start_opacity === '' || settings.supercraft_ct_start_opacity === null || settings.supercraft_ct_start_opacity === undefined) {
            styles.push('--transform-start-opacity:1');
          }
          
          if (isScrub) {
            if (settings.supercraft_scrub_start) {
              styles.push(`--transform-scroll-start:${settings.supercraft_scrub_start}`);
            }
            if (settings.supercraft_scrub_end) {
              styles.push(`--transform-scroll-end:${settings.supercraft_scrub_end}`);
            }
            if (settings.supercraft_scrub_ease) {
              styles.push(`--transform-ease:${settings.supercraft_scrub_ease}`);
            }
            if (settings.supercraft_scrub_forward === 'yes') {
              $el.attr('data-transform-forward-only', 'true');
            }
          }
        }
        
        // Preview state: default to end for editor visibility; honor toggle only for custom, non-scrub
        const previewState = (!isScrub && preset === 'custom')
          ? (settings.supercraft_preview_state || 'end')
          : 'end';

        if (!isScrub) {
          $el.attr('data-preview-state', previewState);
          applyStaticPreviewState(view, settings, previewState, isScrub, preset);
        } else {
          $el.removeAttr('data-preview-state');
          applyStaticPreviewState(view, settings, 'end', isScrub, preset);
        }
        break;

      case 'split-text':
        const mode = settings.supercraft_split_mode || 'chars';
        const variant = mode === 'words'
          ? (settings.supercraft_split_variant_word || 'fade-x')
          : (settings.supercraft_split_variant_char || 'fade-x');
        const isScrubSplit = settings.supercraft_split_scrub === 'yes';
        const splitPreset = settings.supercraft_split_preset || 'medium';

        if (mode === 'words') {
          if (variant === 'fade-y') {
            $el.addClass(isScrubSplit ? 'split-text-word-fade-y-scroll' : 'split-text-word-fade-y');
          } else if (variant === 'fade-blur') {
            $el.addClass(isScrubSplit ? 'split-text-word-fade-y-blur-scroll' : 'split-text-word-fade-y-blur');
          } else {
            $el.addClass(isScrubSplit ? 'split-text-word-fade-scroll' : 'split-text-word-fade');
          }
        } else {
          if (variant === 'fade-y') {
            $el.addClass(isScrubSplit ? 'split-text-char-fade-y-scroll' : 'split-text-char-fade-y');
          } else if (variant === 'fade-blur') {
            $el.addClass(isScrubSplit ? 'split-text-char-fade-y-blur-scroll' : 'split-text-char-fade-y-blur');
          } else {
            $el.addClass(isScrubSplit ? 'split-text-char-fade-scroll' : 'split-text-char-fade');
          }
        }
        
        // Apply preset styles
        if (splitPreset !== 'custom') {
          const isWord = (mode === 'words');
          const offsetDefault = splitPreset === 'light' ? 15 : (splitPreset === 'dramatic' ? 50 : 30);
          const staggerDefault = isWord
            ? (splitPreset === 'light' ? 0.06 : (splitPreset === 'dramatic' ? 0.12 : 0.1))
            : (splitPreset === 'light' ? 0.04 : (splitPreset === 'dramatic' ? 0.08 : 0.05));
          const durationDefault = splitPreset === 'light' ? 1.0 : (splitPreset === 'dramatic' ? 1.8 : 1.5);
          const isBlurVariant = (variant === 'fade-blur');
          const isOffsetY = (variant === 'fade-y' || variant === 'fade-blur');
          const blurDefault = isBlurVariant
            ? (splitPreset === 'light' ? 10 : (splitPreset === 'dramatic' ? 20 : 15))
            : null;
          
          const prefix = isWord ? '--word-' : '--char-';
          styles.push(`${prefix}offset-x:${offsetDefault}px`);
          styles.push(`${prefix}offset-y:${isOffsetY ? offsetDefault : 0}px`);
          styles.push(`${prefix}stagger:${staggerDefault}s`);
          styles.push(`${prefix}duration:${durationDefault}s`);
          styles.push(`${prefix}opacity-start:0`);
          if (blurDefault !== null) {
            styles.push(`${prefix}blur-start:${blurDefault}px`);
          }
          
          if (isScrubSplit) {
            const scrollStart = settings.supercraft_split_scroll_start || 'top 85%';
            const scrollEnd = settings.supercraft_split_scroll_end || 'top 40%';
            styles.push(`${prefix}scroll-start:${scrollStart}`);
            styles.push(`${prefix}scroll-end:${scrollEnd}`);
            if (settings.supercraft_split_forward === 'yes') {
              $el.attr('data-split-forward-only', 'true');
            }
          } else {
            if (settings.supercraft_split_delay !== '' && settings.supercraft_split_delay !== null && settings.supercraft_split_delay !== undefined) {
              styles.push(`--animation-delay:${settings.supercraft_split_delay}s`);
            }
          }
        }
        break;

      case 'image-reveal':
        $el.addClass('image-reveal');
        let imgDir = settings.supercraft_image_preset || 'left';
        if (imgDir === 'custom') {
          imgDir = settings.supercraft_image_direction || 'left';
        }
        $el.addClass('image-reveal-' + imgDir);
        
        if (settings.supercraft_image_duration) {
          styles.push(`--reveal-duration:${settings.supercraft_image_duration}s`);
        }
        if (settings.supercraft_image_delay !== '' && settings.supercraft_image_delay !== null) {
          styles.push(`--reveal-delay:${settings.supercraft_image_delay}s`);
        }
        if (settings.supercraft_image_ease) {
          styles.push(`--reveal-ease:${settings.supercraft_image_ease}`);
        }
        if (settings.supercraft_image_trigger) {
          styles.push(`--reveal-trigger:${settings.supercraft_image_trigger}`);
        }
        if (settings.supercraft_image_scale) {
          styles.push(`--reveal-image-scale:${settings.supercraft_image_scale}`);
        }
        break;

      case 'container-reveal':
        const isContainerScrub = settings.supercraft_container_scrub === 'yes';
        $el.addClass(isContainerScrub ? 'container-reveal-scroll' : 'container-reveal');
        
        let contDir = settings.supercraft_container_preset || 'center';
        if (contDir === 'custom') {
          contDir = settings.supercraft_container_direction || 'center';
        }
        $el.addClass('container-reveal-' + contDir);
        
        if (settings.supercraft_container_duration) {
          styles.push(`--reveal-duration:${settings.supercraft_container_duration}s`);
        }
        if (settings.supercraft_container_delay !== '' && settings.supercraft_container_delay !== null) {
          styles.push(`--animation-delay:${settings.supercraft_container_delay}s`);
        }
        if (settings.supercraft_container_ease) {
          styles.push(`--reveal-ease:${settings.supercraft_container_ease}`);
        }
        
        if (isContainerScrub) {
          if (settings.supercraft_container_scroll_start) {
            styles.push(`--reveal-scroll-start:${settings.supercraft_container_scroll_start}`);
          }
          if (settings.supercraft_container_scroll_end) {
            styles.push(`--reveal-scroll-end:${settings.supercraft_container_scroll_end}`);
          }
          if (settings.supercraft_container_forward === 'yes') {
            $el.attr('data-reveal-forward-only', 'true');
          }
        } else {
          if (settings.supercraft_container_trigger) {
            styles.push(`--reveal-trigger:${settings.supercraft_container_trigger}`);
          }
        }
        break;

      case 'scroll-fill-text':
        $el.addClass('scroll-fill-text');
        const normalizeColor = (val, globalKey) => {
          if (!val && !globalKey) return '';
          if (typeof val === 'string') return val;
          if (typeof val === 'object') {
            if (val.color) return val.color;
            if (val.value) return val.value;
          }
          if (globalKey) {
            const cleaned = globalKey
              .toString()
              .replace(/^.*[=:]/, '')
              .replace(/[^a-zA-Z0-9_-]/g, '');
            if (cleaned) {
              return `var(--e-global-color-${cleaned})`;
            }
          }
          return '';
        };
        if (settings.supercraft_fill_start) {
          styles.push(`--scroll-fill-start:${settings.supercraft_fill_start}`);
          $el.attr('data-scroll-fill-start', settings.supercraft_fill_start);
        }
        if (settings.supercraft_fill_end) {
          styles.push(`--scroll-fill-end:${settings.supercraft_fill_end}`);
          $el.attr('data-scroll-fill-end', settings.supercraft_fill_end);
        }
        const globalKey = settings.__globals__ ? settings.__globals__.supercraft_fill_base : '';
        const fillBase = normalizeColor(settings.supercraft_fill_base, globalKey);
        if (fillBase) {
          styles.push(`--scroll-fill-base:${fillBase}`);
          $el.attr('data-scroll-fill-base', fillBase);
        }
        if (settings.supercraft_fill_line === 'yes') {
          $el.attr('data-scroll-fill-line', 'yes');
        }
        break;
    }

    // Apply inline styles
    if (styles.length > 0) {
      const existingStyle = $el.attr('style') || '';
      const combinedStyle = existingStyle + (existingStyle ? ';' : '') + styles.join(';');
      $el.attr('style', combinedStyle);
    }

    console.log('Applied classes and styles:', $el.attr('class'), $el.attr('style'));
  }

  // Apply static start/end state for custom non-scrub scroll transforms (preview/reset helper)
  function applyStaticPreviewState(view, settings, state, isScrub, preset) {
    if (!view || !view.$el) return;
    if (isScrub) return;

    const $el = view.$el;
    const compStyles = getComputedStyle($el[0]);
    const getVal = (key, fallback = 0, unit = '') => {
      const v = settings[key];
      let val = v;
      if (val === '' || val === null || val === undefined) {
        // Fallback to CSS custom property if present
        const cssVar = '--' + key.replace(/^supercraft_/, '').replace(/_/g, '-');
        const cssVal = compStyles.getPropertyValue(cssVar);
        if (cssVal && cssVal.trim() !== '') {
          val = cssVal.trim();
        }
      }
      if (val === '' || val === null || val === undefined) return fallback;
      if (unit && typeof val === 'number') return `${val}${unit}`;
      if (unit && typeof val === 'string' && /^-?\d+(\.\d+)?$/.test(val)) return `${val}${unit}`;
      return val;
    };

    const startX = getVal('supercraft_ct_start_x', 0, 'px');
    const startY = getVal('supercraft_ct_start_y', 0, 'px');
    const startR = getVal('supercraft_ct_start_rotate', 0, 'deg');
    const startS = getVal('supercraft_ct_start_scale', 1, '');
    const startO = getVal('supercraft_ct_start_opacity', 1, '');
    const startB = getVal('supercraft_ct_start_blur', 0, 'px');

    const endX = getVal('supercraft_ct_end_x', 0, 'px');
    const endY = getVal('supercraft_ct_end_y', 0, 'px');
    const endR = getVal('supercraft_ct_end_rotate', 0, 'deg');
    const endS = getVal('supercraft_ct_end_scale', 1, '');
    const endO = getVal('supercraft_ct_end_opacity', 1, '');
    const endB = getVal('supercraft_ct_end_blur', 0, 'px');

    const applyStatic = (x, y, r, s, o, b) => {
      const transforms = [];
      transforms.push(`translateX(${x})`);
      transforms.push(`translateY(${y})`);
      transforms.push(`rotate(${r})`);
      transforms.push(`scale(${s})`);
      $el.css({
        transform: transforms.join(' '),
        opacity: o,
        filter: `blur(${b})`,
      });
    };

    if (state === 'start') {
      applyStatic(startX, startY, startR, startS, startO, startB);
    } else if (state === 'end') {
      applyStatic(endX, endY, endR, endS, endO, endB);
    }
  }

  // Apply start state to an element using its current CSS custom properties (fallback for play reset)
  function applyStartStateFromStyles(el) {
    if (!el) return;
    const styles = getComputedStyle(el);
    const getVal = (varName, fallback) => {
      const v = (styles.getPropertyValue(varName) || '').trim();
      return v || fallback;
    };
    const startX = getVal('--transform-start-x', '0px');
    const startY = getVal('--transform-start-y', '0px');
    const startR = getVal('--transform-start-rotate', '0deg');
    const startS = parseFloat(getVal('--transform-start-scale', '1')) || 1;
    const startO = parseFloat(getVal('--transform-start-opacity', '1'));
    const startB = getVal('--transform-start-blur', '0px');

    el.style.transform = `translateX(${startX}) translateY(${startY}) rotate(${startR}) scale(${startS})`;
    el.style.opacity = isNaN(startO) ? 1 : startO;
    el.style.filter = `blur(${startB})`;
  }

  function resetPreviewState(view) {
    if (!view) return;
    const model = view.model;
    const modelSettings = getSettingsAttributes(model) || {};
    if (model && model.get && modelSettings.supercraft_preview_state === 'start') {
      model.set('supercraft_preview_state', 'end', { silent: true });
    }
    if (view.$el) {
      // Apply end state so element isn't left in start pose when deselected
      const settings = getSettingsAttributes(view.model) || {};
      const preset = settings.supercraft_scroll_preset || '';
      const isScrub = settings.supercraft_scroll_scrub === 'yes';
      // Clear any pending start-state reset timer for this element
      clearStartResetTimer(view.$el[0]);
      applyStaticPreviewState(view, settings, 'end', isScrub, preset);
      view.$el.attr('data-preview-state', 'end');
    }
  }

  function applyHandler(panel, model, view) {
    if (!view || !view.$el || !model) return;
    const initialSettings = getSettingsAttributes(model) || {};
    const isSupercraftView = hasSupercraftSettings(initialSettings) || hasSupercraftDecorations(view.$el);
    if (!isSupercraftView) return;

    // Clear previous widget decorations
    const activeView = getActiveView();
    if (activeView && activeView !== view) {
      resetPreviewState(activeView);
      if (hasSupercraftDecorations(activeView.$el)) {
        stripAnimationClasses(activeView.$el);
      }
    }
    setActiveView(view);

    const applyForView = () => applyAnimationClasses(view);

    // Apply immediately when panel opens
    setTimeout(applyForView, 50);
    // Also apply every time Elementor re-renders the widget
    if (view && view.on) {
      view.on('render', applyForView);
    }

    // Listen for setting changes
    const onSettingChange = () => {
      const currentSettings = getSettingsAttributes(model) || {};
      const currentCat = currentSettings.supercraft_anim_category || '';
      const currentHasDecor = hasSupercraftDecorations(view.$el);

      // Ignore non-Supercraft widgets entirely to avoid interfering with editor widgets (e.g. Tabs).
      if (!currentCat && !currentHasDecor) {
        return;
      }

      // Re-apply immediately and again shortly after Elementor updates the DOM
      applyForView();
      if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(applyForView);
      } else {
        setTimeout(applyForView, 0);
      }
      setTimeout(applyForView, 100);

      // Re-initialize animations after class change
      setTimeout(() => {
        if (window.initAllAnimations) {
          // Kill existing triggers first
          if (window.ScrollTrigger) {
            ScrollTrigger.getAll().forEach(st => st.kill());
          }
          
          // Remove init flags
          document.querySelectorAll(
            '[data-scroll-transform-init], [data-scroll-transform-scrub-init], [data-image-reveal-init], [data-container-reveal-init], [data-scroll-fill-init], [data-anim-init]'
          ).forEach(el => {
            delete el.dataset.scrollTransformInit;
            delete el.dataset.scrollTransformScrubInit;
            delete el.dataset.imageRevealInit;
            delete el.dataset.containerRevealInit;
            delete el.dataset.scrollFillInit;
            delete el.dataset.animInit;
          });
          
          window.initAllAnimations();
          
          if (window.ScrollTrigger) {
            ScrollTrigger.refresh();
          }
        }
      }, 100);
    };

    // Watch for changes to animation settings
    model.on('change', onSettingChange);

    // Clean up when widget is destroyed or a different widget is opened
    view.once('destroy', () => {
      model.off('change', onSettingChange);
      if (view && view.off) {
        view.off('render', applyForView);
      }
      resetPreviewState(view);
      if (hasSupercraftDecorations(view.$el)) {
        stripAnimationClasses(view.$el);
      }
      const activeView = getActiveView();
      if (activeView === view) {
        setActiveView(null);
      }
    });
  }

  // Apply classes when element is rendered or settings change
  elementor.hooks.addAction('panel/open_editor/widget', applyHandler);
  elementor.hooks.addAction('panel/open_editor/section', applyHandler);
  elementor.hooks.addAction('panel/open_editor/column', applyHandler);
  elementor.hooks.addAction('panel/open_editor/container', applyHandler);

  // Also apply on initial load for all widgets
  elementor.on('preview:loaded', function () {
    setTimeout(() => {
      const activeView = getActiveView();
      if (activeView) applyAnimationClasses(activeView);

      // Initialize all animations
      if (window.initAllAnimations) {
        window.initAllAnimations();
      }
    }, 500);
  });

  console.log('Supercraft editor class applier loaded');
})();

  // Play button handler: replay non-scrub animations in preview
  (function () {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-event="supercraft_preview_play"]');
      if (!btn) return;
    const iframe = document.getElementById('elementor-preview-iframe');
    if (!iframe || !iframe.contentWindow) {
      console.warn('supercraft play: preview iframe not found, skipping play');
      return;
    }
    console.log('supercraft play: panel button click -> posting to preview');
    iframe.contentWindow.postMessage({ type: 'supercraft_preview_play' }, '*');
  });

  window.addEventListener('message', (e) => {
    if (!e || !e.data || e.data.type !== 'supercraft_preview_play') return;
    // Only handle in the preview frame, not in the panel
    if (window === window.top) return;
    console.log('supercraft play: postMessage received, replaying');

    // Reapply latest classes/styles for the active widget before replaying
    const activeView = getActiveView();
    if (activeView) {
      const settings = activeView.model?.get('settings')?.attributes || {};
      // Temporarily clear preview-state so animation can play even if preview is set to "start"
      if (settings.supercraft_preview_state === 'start' && activeView.$el) {
        activeView.$el.removeAttr('data-preview-state');
      }
      applyAnimationClasses(activeView);
    }
    if (window.ScrollTrigger) {
      ScrollTrigger.getAll().forEach((st) => st.kill());
    }
    document.querySelectorAll('[data-scroll-transform-init], [data-scroll-transform-scrub-init], [data-image-reveal-init], [data-container-reveal-init], [data-scroll-fill-init], [data-anim-init]').forEach((el) => {
      delete el.dataset.scrollTransformInit;
      delete el.dataset.scrollTransformScrubInit;
      delete el.dataset.imageRevealInit;
      delete el.dataset.containerRevealInit;
      delete el.dataset.scrollFillInit;
      delete el.dataset.animInit;
    });
    if (window.initAllAnimations) {
      window.initAllAnimations();
    }
    // Refresh triggers only; manual replay was causing a second run (auto-play + manual).
    setTimeout(() => {
      if (window.ScrollTrigger) {
        ScrollTrigger.refresh();
      }
    }, 150);

    // If preview state is "start" for custom non-scrub scroll transforms, re-apply the static start state after play
    const targets = document.querySelectorAll('[data-preview-state="start"].scroll-transform:not(.scroll-transform-scrub)');
    targets.forEach((el) => {
      const styles = getComputedStyle(el);
      const duration = parseFloat((styles.getPropertyValue('--transform-duration') || '').trim()) || 1;
      const delay = parseFloat((styles.getPropertyValue('--transform-delay') || '').trim()) || 0;
      const total = Math.max(0, (duration + delay) * 1000 + 150);
      // Clear any existing timer for this element before scheduling
      clearStartResetTimer(el);
      const timerId = setTimeout(() => {
        applyStartStateFromStyles(el);
        startResetTimers.delete(el);
      }, total);
      startResetTimers.set(el, timerId);
    });
  });
})();
