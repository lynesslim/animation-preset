document.addEventListener('DOMContentLoaded', function () {
  const isEditor = document.body.classList.contains('elementor-editor-active');

  if (!window.gsap) {
    console.warn('GSAP not loaded.');
    return;
  }
  if (window.ScrollTrigger) {
    gsap.registerPlugin(ScrollTrigger);
  } else {
    console.warn('ScrollTrigger not loaded. Scroll-based animations may not work.');
  }

  // Sync GSAP/ScrollTrigger with Lenis smooth scrolling if Lenis is present
  (function syncLenis() {
    if (!window.ScrollTrigger) return;

    // If Lenis constructor exists but no instance yet, create one and expose it.
    if (!window.lenis && window.Lenis) {
      window.lenis = new window.Lenis({ lerp: 0.1, duration: 1.2 });
    }

    const lenis = window.lenis;
    if (!lenis) return; // nothing to sync if Lenis not present

    // Keep ScrollTrigger in sync with Lenis scroll updates
    lenis.on('scroll', ScrollTrigger.update);

    // Drive Lenis with GSAP's ticker (use performance.now() to match Lenis API across versions)
    gsap.ticker.add(() => {
      lenis.raf(performance.now());
    });
    gsap.ticker.lagSmoothing(0);

    const rootScroller = document.documentElement;

    ScrollTrigger.scrollerProxy(rootScroller, {
      scrollTop(value) {
        if (arguments.length) {
          lenis.scrollTo(value, { immediate: true });
        }
        return lenis.scroll || window.scrollY || 0;
      },
      getBoundingClientRect() {
        return {
          top: 0,
          left: 0,
          width: window.innerWidth,
          height: window.innerHeight,
        };
      },
      pinType: rootScroller.style.transform ? 'transform' : 'fixed',
    });

    ScrollTrigger.addEventListener('refresh', () => {
      if (lenis.update) lenis.update();
    });
    ScrollTrigger.refresh();
  })();

  /* ==========================================
     CORE ANIMATIONS: split-text / blur / fade
     ========================================== */
  function initCoreAnimations() {
    const elements = document.querySelectorAll(
      '.split-text-reveal-up, .anim-fade-up, .blur-reveal, .split-text-char-fade, .split-text-char-fade-y, .split-text-char-fade-scroll, .split-text-char-fade-y-scroll, .split-text-word-fade, .split-text-word-fade-y, .split-text-word-fade-scroll, .split-text-word-fade-y-scroll, .split-text-word-fade-y-blur, .split-text-word-fade-y-blur-scroll'
    );

    elements.forEach((el) => {
      if (el.dataset.animInit === 'true') return;

      const isSplitFade =
        el.classList.contains('split-text-char-fade') ||
        el.classList.contains('split-text-char-fade-y') ||
        el.classList.contains('split-text-char-fade-scroll') ||
        el.classList.contains('split-text-char-fade-y-scroll') ||
        el.classList.contains('split-text-word-fade') ||
        el.classList.contains('split-text-word-fade-y') ||
        el.classList.contains('split-text-word-fade-scroll') ||
        el.classList.contains('split-text-word-fade-y-scroll') ||
        el.classList.contains('split-text-word-fade-y-blur') ||
        el.classList.contains('split-text-word-fade-y-blur-scroll');

      if (isSplitFade) {
        if (typeof window.SplitType !== 'function') {
          console.warn('SplitType not loaded for split-text animations');
          return;
        }

        const textTarget = (() => {
          if (
            el.matches(
              '.elementor-heading-title, h1, h2, h3, h4, h5, h6, p, span'
            )
          ) {
            return el;
          }
          return (
            el.querySelector(
              '.elementor-heading-title, h1, h2, h3, h4, h5, h6, p, span'
            ) || el
          );
        })();

        const isWord =
          el.classList.contains('split-text-word-fade') ||
          el.classList.contains('split-text-word-fade-y') ||
          el.classList.contains('split-text-word-fade-scroll') ||
          el.classList.contains('split-text-word-fade-y-scroll') ||
          el.classList.contains('split-text-word-fade-y-blur') ||
          el.classList.contains('split-text-word-fade-y-blur-scroll');

        const split = new SplitType(textTarget, {
          types: isWord ? 'words' : 'words, chars',
          whitespace: 'preserve',
        });

        // Preserve spacing and wrapping for words (char mode now includes words)
        if (split.words?.length) {
          split.words.forEach((wordEl) => {
            wordEl.style.display = 'inline-block';
            wordEl.style.whiteSpace = 'normal';
            wordEl.style.lineHeight = 'inherit';
            wordEl.style.marginRight = '0';
          });
        }

        if (!isWord && split.chars?.length) {
          split.chars.forEach((charEl) => {
            charEl.style.display = 'inline-block';
            charEl.style.whiteSpace = 'pre';
            charEl.style.lineHeight = 'inherit';
          });
        }
        const styles = getComputedStyle(el);

        const offsetX =
          (isWord
            ? styles.getPropertyValue('--word-offset-x')
            : styles.getPropertyValue('--char-offset-x'))?.trim() || '0px';
        const offsetY =
          (isWord
            ? styles.getPropertyValue('--word-offset-y')
            : styles.getPropertyValue('--char-offset-y'))?.trim() || '0px';

        const durationRaw = isWord
          ? styles.getPropertyValue('--word-duration')
          : styles.getPropertyValue('--char-duration');
        const duration =
          durationRaw && !Number.isNaN(parseFloat(durationRaw))
            ? parseFloat(durationRaw)
            : 1.5;

        const staggerRaw = isWord
          ? styles.getPropertyValue('--word-stagger')
          : styles.getPropertyValue('--char-stagger');
        const stagger =
          staggerRaw && !Number.isNaN(parseFloat(staggerRaw))
            ? parseFloat(staggerRaw)
            : 0.05;

        const opacityStartRaw = isWord
          ? styles.getPropertyValue('--word-opacity-start')
          : styles.getPropertyValue('--char-opacity-start');
        const opacityStart =
          opacityStartRaw && !Number.isNaN(parseFloat(opacityStartRaw))
            ? parseFloat(opacityStartRaw)
            : 0;

        const ease =
          (isWord
            ? styles.getPropertyValue('--word-ease')
            : styles.getPropertyValue('--char-ease'))?.trim() || 'power2.out';

        // Check if this is a scroll-scrubbed version
        const isScrollScrubbed =
          el.classList.contains('split-text-char-fade-scroll') ||
          el.classList.contains('split-text-char-fade-y-scroll') ||
          el.classList.contains('split-text-char-fade-y-blur-scroll') ||
          el.classList.contains('split-text-word-fade-scroll') ||
          el.classList.contains('split-text-word-fade-y-scroll') ||
          el.classList.contains('split-text-word-fade-y-blur-scroll');

        if (isScrollScrubbed) {
          // Scroll-scrubbed version - tied to scroll position, reversible
          const scrollStart =
            (isWord
              ? styles.getPropertyValue('--word-scroll-start')
              : styles.getPropertyValue('--char-scroll-start'))?.trim() || 'top 85%';
          const scrollEnd =
            (isWord
              ? styles.getPropertyValue('--word-scroll-end')
              : styles.getPropertyValue('--char-scroll-end'))?.trim() || 'top 20%';

          // Optional per-word/char blur for scrubbed variant
          const isWordBlurYScroll = el.classList.contains('split-text-word-fade-y-blur-scroll');
          const isCharBlurYScroll = el.classList.contains('split-text-char-fade-y-blur-scroll');
          let blurStart = (styles.getPropertyValue('--word-blur-start') || '').trim();
          if ((isWord && isWordBlurYScroll) || (!isWord && isCharBlurYScroll)) {
            if (!isWord) {
              blurStart = (styles.getPropertyValue('--char-blur-start') || '').trim();
            }
            if (!blurStart) blurStart = '20px';
            if (/^-?\d+(?:\.\d+)?$/.test(blurStart)) {
              blurStart = blurStart + 'px';
            }
          }

        const fromVars = {
          x: offsetX,
          y: offsetY,
          opacity: opacityStart,
        };
          const toVars = {
            x: 0,
            y: 0,
            opacity: 1,
            duration,
            stagger,
            ease,
            scrollTrigger: {
              trigger: el,
              start: scrollStart,
              end: scrollEnd,
              scrub: true, // Ties animation to scroll position, reversible
              onUpdate: (self) => {
                const forwardOnly = el.dataset.splitForwardOnly === 'true';
                if (forwardOnly) {
                  const max = Math.max(self.progress, self._maxProgress || 0);
                  self._maxProgress = max;
                  if (self.progress < max) {
                    self.animation.progress(max);
                  }
                }
              },
            },
            // Don't revert on scroll-scrubbed versions - they need to stay split
          };

          if ((isWord && isWordBlurYScroll) || (!isWord && isCharBlurYScroll)) {
            fromVars.filter = `blur(${blurStart})`;
            toVars.filter = 'blur(0px)';
          }

          const targets = isWord ? split.words : split.chars;
          // Ensure initial state is applied before scrub
          gsap.set(targets, fromVars);
          gsap.fromTo(targets, fromVars, {
            ...toVars,
            immediateRender: false,
          });
        } else {
          // One-time trigger version (supports optional blur on words/chars)
          const isWordBlurY = el.classList.contains('split-text-word-fade-y-blur');
          const isCharBlurY = el.classList.contains('split-text-char-fade-y-blur');
          let blurStart = (styles.getPropertyValue(isWord ? '--word-blur-start' : '--char-blur-start') || '').trim();
          if (!blurStart) blurStart = '20px';
          if (/^-?\d+(?:\.\d+)?$/.test(blurStart)) {
            blurStart = blurStart + 'px';
          }

          // Optional overall delay for non-scrubbed variants
          const delayRawStd = styles.getPropertyValue('--animation-delay');
          const delayRawLegacy = isWord
            ? styles.getPropertyValue('--word-delay')
            : styles.getPropertyValue('--char-delay');
          const delaySource = (delayRawStd && !Number.isNaN(parseFloat(delayRawStd))) ? delayRawStd : delayRawLegacy;
          const delayAmt =
            delaySource && !Number.isNaN(parseFloat(delaySource))
              ? parseFloat(delaySource)
              : 0;

          const fromVars = {
            x: offsetX,
            y: offsetY,
            opacity: opacityStart,
          };
          const toVars = {
            x: 0,
            y: 0,
            opacity: 1,
            duration,
          stagger,
          ease,
          immediateRender: false, // don't hide text until the trigger actually fires
          delay: delayAmt,
          scrollTrigger: {
              trigger: el,
              start: 'top 85%',
            },
          };

          if (isWordBlurY || isCharBlurY) {
            fromVars.filter = `blur(${blurStart})`;
            toVars.filter = 'blur(0px)';
          }

          const targets = isWord ? split.words : split.chars;
          gsap.fromTo(targets, fromVars, {
            ...toVars,
            onComplete: () => {
              // Revert to original DOM to keep final spacing identical to pre-split
              split.revert();
            },
          });
          gsap.set(targets, fromVars);
        }

        el.dataset.animInit = 'true';
        return;
      }

      const hasSplit = el.classList.contains('split-text-reveal-up');
      const hasBlur  = el.classList.contains('blur-reveal');
      const hasFade  = el.classList.contains('anim-fade-up');

      let split = null;

      // --- SplitType prep if needed ---
      if (hasSplit) {
        if (typeof window.SplitType !== 'function') {
          console.warn('SplitType not loaded for split-text-reveal-up');
        } else {
          split = new SplitType(el, { types: 'lines, words' });

          el.querySelectorAll('.line').forEach((line) => {
            const wrapper = document.createElement('div');
            wrapper.classList.add('text-reveal-line-wrapper');
            line.parentNode.insertBefore(wrapper, line);
            wrapper.appendChild(line);
          });
        }
      }

      // --- Blur vars from CSS custom props ---
      let duration = 1.5;
      let delay = 0;
      let move = '-20px';

      if (hasBlur) {
        const styles = getComputedStyle(el);
        duration =
          parseFloat(styles.getPropertyValue('--animation-duration')) || 1.5;
        delay =
          parseFloat(styles.getPropertyValue('--animation-delay')) || 0;
        move = styles.getPropertyValue('--move-distance') || '-20px';
      }

      const tl = gsap.timeline({
        scrollTrigger: {
          trigger: el,
          start: 'top 85%',
        },
      });

      // CASE 1: SPLIT + BLUR (blur on words, runs concurrently)
      if (hasSplit && hasBlur && split) {
        tl.from(
          split.words,
          {
            y: '120%',
            opacity: 0,
            filter: 'blur(20px)',
            duration,
            stagger: 0.08,
            ease: 'power2.out',
            onComplete: () => gsap.set(split.words, { filter: 'blur(0px)' }),
          },
          delay // launch the whole group after a trigger delay
        );
        
        // If fade-up is also present, add it to the element concurrently
        if (hasFade) {
          tl.fromTo(
            el,
            { y: 20, opacity: 0 },
            {
              y: 0,
              opacity: 1,
              duration: 0.6,
              ease: 'power2.out',
            },
            delay // start at the same trigger delay so both run together
          );
        }
      }
      // CASE 2: SPLIT ONLY (no blur, only if SplitType available)
      else if (hasSplit && !hasBlur && split) {
        const styles = getComputedStyle(el);
        const splitDelay =
          parseFloat(styles.getPropertyValue('--animation-delay')) ||
          parseFloat(styles.getPropertyValue('--split-delay')) || 0;

        tl.from(
          split.words,
          {
            y: '120%',
            opacity: 0,
            duration: 0.6,
            stagger: 0.08,
            ease: 'power2.out',
          },
          splitDelay // delay the whole split animation launch
        );
        
        // If fade-up is also present, add it to the element concurrently
        if (hasFade) {
          tl.fromTo(
            el,
            { y: 20, opacity: 0 },
            {
              y: 0,
              opacity: 1,
              duration: 0.6,
              ease: 'power2.out',
            },
            splitDelay // align start with split animation
          );
        }
      }
      // CASE 3: BLUR + FADE-UP (both on element, run concurrently)
      else if (hasBlur && hasFade) {
        tl.fromTo(
          el,
          {
            opacity: 0,
            filter: 'blur(20px)',
            y: move,
            scale: 1.1,
          },
          {
            opacity: 1,
            filter: 'blur(0px)',
            y: 0,
            scale: 1,
            duration,
            delay,
            ease: 'power2.out',
          }
        );
        // Fade-up properties are already included in the blur animation
        // (opacity and y are handled together)
      }
      // CASE 4: BLUR ONLY (no split, no fade)
      else if (hasBlur && !hasFade) {
        tl.fromTo(
          el,
          {
            opacity: 0,
            filter: 'blur(20px)',
            y: move,
            scale: 1.1,
          },
          {
            opacity: 1,
            filter: 'blur(0px)',
            y: 0,
            scale: 1,
            duration,
            delay,
            ease: 'power2.out',
          }
        );
      }
      // CASE 5: FADE-UP ONLY (no blur, no split)
      else if (hasFade && !hasBlur && !hasSplit) {
        const styles = getComputedStyle(el);
        const fadeDelay =
          parseFloat(styles.getPropertyValue('--animation-delay')) || 0;

        tl.fromTo(
          el,
          { y: 20, opacity: 0 },
          {
            y: 0,
            opacity: 1,
            duration: 0.6,
            ease: 'power2.out',
            delay: fadeDelay,
          }
        );
      }

      el.dataset.animInit = 'true';
    });
  }

  /* ==========================================
     SCROLL TRANSFORM ANIMATION
     Controlled via CSS custom properties or data attributes
     ========================================== */
  function initScrollTransform() {
    const elements = gsap.utils.toArray('.scroll-transform');

    elements.forEach((el) => {
      // Skip if already initialized
      if (el.dataset.scrollTransformInit === 'true') return;

      const styles = getComputedStyle(el);

      // Prefer data-* overrides first, then CSS vars, then fallbacks
      const pickValue = (cssVarName, dataKey, fallback) => {
        const dataVal = el.dataset[dataKey];
        if (dataVal !== undefined && `${dataVal}`.trim() !== '') {
          return `${dataVal}`.trim();
        }
        const cssVal = styles.getPropertyValue(cssVarName);
        if (cssVal && cssVal.trim() !== '') {
          return cssVal.trim();
        }
        return fallback;
      };

      const pickNumber = (cssVarName, dataKey, fallback) => {
        const raw = pickValue(cssVarName, dataKey, '');
        const num = parseFloat(raw);
        return Number.isNaN(num) ? fallback : num;
      };

      // Get initial values from CSS custom properties or data attributes
      let startX = pickValue('--transform-start-x', 'transformStartX', '0px');
      let startY = pickValue('--transform-start-y', 'transformStartY', '0px');
      let startRotate = pickValue('--transform-start-rotate', 'transformStartRotate', '0deg');
      let startScale = pickNumber('--transform-start-scale', 'transformStartScale', 1);
      let startOpacity = pickNumber('--transform-start-opacity', 'transformStartOpacity', 0);
      let startBlur = pickValue('--transform-start-blur', 'transformStartBlur', '0px');

      // Get end values from CSS custom properties or data attributes
      let endX = pickValue('--transform-end-x', 'transformEndX', '0px');
      let endY = pickValue('--transform-end-y', 'transformEndY', '0px');
      let endRotate = pickValue('--transform-end-rotate', 'transformEndRotate', '0deg');
      let endScale = pickNumber('--transform-end-scale', 'transformEndScale', 1);
      let endOpacity = pickNumber('--transform-end-opacity', 'transformEndOpacity', 1);
      let endBlur = pickValue('--transform-end-blur', 'transformEndBlur', '0px');

      // Get animation settings
      let duration = pickNumber('--transform-duration', 'transformDuration', 1);
      let delay =
        pickNumber('--animation-delay', 'animationDelay', 0) ||
        pickNumber('--transform-delay', 'transformDelay', 0);
      let ease = pickValue('--transform-ease', 'transformEase', 'power2.out');
      let startTrigger = pickValue('--transform-trigger', 'transformTrigger', 'top 85%');

      // Ensure values have units if they're just numbers
      const ensureUnit = (val, defaultUnit = 'px') => {
        if (typeof val === 'string') {
          val = val.trim();
          // If it's just a number, add default unit
          if (/^-?\d+\.?\d*$/.test(val)) {
            return val + defaultUnit;
          }
          return val;
        }
        return val || '0' + defaultUnit;
      };

      // Ensure rotate values have 'deg' unit
      const ensureRotateUnit = (val) => {
        if (typeof val === 'string') {
          val = val.trim();
          if (/^-?\d+\.?\d*$/.test(val)) {
            return val + 'deg';
          }
          return val;
        }
        return val || '0deg';
      };

      // Ensure blur values have 'px' unit
      const ensureBlurUnit = (val) => {
        if (typeof val === 'string') {
          val = val.trim();
          if (/^-?\d+\.?\d*$/.test(val)) {
            return val + 'px';
          }
          return val;
        }
        return val || '0px';
      };

      // Clear any CSS transitions that might interfere with GSAP
      // This is important for containers that might have CSS transitions set
      el.style.transition = 'none';
      el.style.willChange = 'transform, opacity, filter';

      // Set initial state (GSAP accepts string values with units)
      gsap.set(el, {
        x: ensureUnit(startX, 'px'),
        y: ensureUnit(startY, 'px'),
        rotation: ensureRotateUnit(startRotate),
        scale: startScale,
        opacity: startOpacity,
        filter: `blur(${ensureBlurUnit(startBlur)})`,
        force3D: true, // Force hardware acceleration for better performance
        immediateRender: true, // Apply immediately
      });

      // Editor preview override: show start or end state statically if requested
      const previewState = el.dataset.previewState;
      // In editor, default to showing end state for visibility unless explicitly set to "start"
      if (isEditor) {
        const stateToUse = previewState || 'end';
        if (stateToUse === 'end') {
          gsap.set(el, {
            x: ensureUnit(endX, 'px'),
            y: ensureUnit(endY, 'px'),
            rotation: ensureRotateUnit(endRotate),
            scale: endScale,
            opacity: endOpacity,
            filter: `blur(${ensureBlurUnit(endBlur)})`,
          });
        }
        // if stateToUse === 'start', the initial gsap.set above already left it at start
        el.dataset.scrollTransformInit = 'true';
        return;
      }

      // Create timeline for better control over duration
      const tl = gsap.timeline({
        scrollTrigger: {
          trigger: el,
          start: startTrigger,
          toggleActions: 'play none none none', // Play once when entering viewport
        },
      });

      // Animate to end state when element enters viewport (entrance animation)
      // Add delay to timeline position if needed
      tl.to(el, {
        x: ensureUnit(endX, 'px'),
        y: ensureUnit(endY, 'px'),
        rotation: ensureRotateUnit(endRotate),
        scale: endScale,
        opacity: endOpacity,
        filter: `blur(${ensureBlurUnit(endBlur)})`,
        duration: duration,
        ease: ease,
        force3D: true, // Force hardware acceleration
      }, delay); // Position delay at timeline position

      el.dataset.scrollTransformInit = 'true';
    });
  }

  /* ==========================================
     SCROLL FILL HEADINGS
     ========================================== */
  function initScrollFillHeadings() {
    // "scroll-fill-text" is on the widget or any wrapper
    const wrappers = gsap.utils.toArray('.scroll-fill-text');

    wrappers.forEach((wrapper) => {
      // Kill any previous trigger so we can rebuild with updated settings
      if (wrapper._scrollFillTrigger) {
        wrapper._scrollFillTrigger.kill();
        wrapper._scrollFillTrigger = null;
      }
      // Also kill any ScrollTrigger whose trigger matches this wrapper (defensive)
      if (window.ScrollTrigger) {
        ScrollTrigger.getAll().forEach((st) => {
          if (st.trigger === wrapper) {
            st.kill();
          }
        });
      }

      // Try to find an inner text element (prioritize actual text elements, not containers)
      let el = wrapper.querySelector(
        '.elementor-heading-title, h1, h2, h3, h4, h5, h6, p, span, .elementor-icon-box-title, .elementor-icon-box-description'
      );
      
      // If no text element found, check if wrapper itself is a text element
      if (!el) {
        const tagName = wrapper.tagName?.toLowerCase();
        if (['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span'].includes(tagName)) {
          el = wrapper;
        } else {
          // Last resort: use wrapper but try to find text inside
          el = wrapper.querySelector('.elementor-widget-container') || wrapper;
        }
      }

      const wrapperStyles = getComputedStyle(wrapper);
      const baseOverride =
        (wrapperStyles.getPropertyValue('--scroll-fill-base') || '').trim() ||
        (wrapper.dataset.scrollFillBase || '').trim();
      const originalColorStr = getComputedStyle(el).color;

      const hexToRgba = (hex) => {
        const clean = hex.replace('#', '');
        const full = clean.length === 3
          ? clean.split('').map((c) => c + c).join('')
          : clean;
        if (full.length !== 6) return null;
        const intVal = parseInt(full, 16);
        const r = (intVal >> 16) & 255;
        const g = (intVal >> 8) & 255;
        const b = intVal & 255;
        return { r, g, b, a: 1 };
      };

      const parseColor = (str) => {
        if (!str) return null;
        let m = str.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/);
        if (m) {
          return {
            r: parseInt(m[1], 10),
            g: parseInt(m[2], 10),
            b: parseInt(m[3], 10),
            a: m[4] !== undefined ? parseFloat(m[4]) : 1,
          };
        }
        if (str.startsWith('#')) return hexToRgba(str);
        return null;
      };

      const originalParsed = parseColor(originalColorStr) || { r: 0, g: 0, b: 0, a: 1 };
      const baseParsed = baseOverride ? parseColor(baseOverride) : null;

      // Unfilled/base color: use provided base (force alpha to 1) or dimmed original
      const dimColor = baseParsed
        ? `rgba(${baseParsed.r}, ${baseParsed.g}, ${baseParsed.b}, 1)`
        : `rgba(${originalParsed.r}, ${originalParsed.g}, ${originalParsed.b}, 0.2)`;

      // Filled color: always solid original (alpha forced to 1)
      const fullColor = `rgba(${originalParsed.r}, ${originalParsed.g}, ${originalParsed.b}, 1)`;

      // Two-layer background: base stays visible, fill overlays as it grows
      el.style.backgroundImage = `linear-gradient(to right, ${fullColor}, ${fullColor}), linear-gradient(${dimColor}, ${dimColor})`;
      el.style.backgroundRepeat = 'no-repeat, no-repeat';
      el.style.backgroundSize = '0% 100%, 100% 100%';
      el.style.webkitBackgroundClip = 'text';
      el.style.backgroundClip = 'text';
      el.style.webkitTextFillColor = 'transparent';
      el.style.display = 'inline-block'; // Required for backgroundClip to work properly

// Get scroll trigger values - prefer data attributes, then CSS vars, then fallback
const getScrollValue = (dataKey, cssVar, fallback) => {
  // Check data attribute first
  const dataVal = wrapper.dataset[dataKey];
  if (dataVal !== undefined && dataVal.trim() !== '') {
    return dataVal.trim();
  }
  // Check CSS custom property
  const cssVal = wrapperStyles.getPropertyValue(cssVar);
  if (cssVal && cssVal.trim() !== '') {
    return cssVal.trim();
  }
  // Return fallback
  return fallback;
};

const scrollStart = getScrollValue('scrollFillStart', '--scroll-fill-start', 'top 85%');
const scrollEnd = getScrollValue('scrollFillEnd', '--scroll-fill-end', 'top 60%');

      // Animate both the background-size and text-fill-color on scroll
      const anim = gsap.to(el, {
        backgroundSize: '100% 100%, 100% 100%',
        ease: 'none',
        scrollTrigger: {
          trigger: wrapper, // Use wrapper as trigger for better detection
          start: scrollStart,
          end: scrollEnd,
          scrub: true,
        },
      });
      wrapper._scrollFillTrigger = anim && anim.scrollTrigger ? anim.scrollTrigger : null;
      // Expose applied values for debugging/inspection
      wrapper.dataset.scrollFillStartApplied = scrollStart;
      wrapper.dataset.scrollFillEndApplied = scrollEnd;
    });

    if (window.ScrollTrigger) {
      ScrollTrigger.refresh();
    }
  }

  /* ==========================================
     IMAGE REVEAL ANIMATION
     Smooth mask reveal with zoom effect for images
     ========================================== */
  function initImageReveal() {
    const containers = gsap.utils.toArray('.image-reveal');

    containers.forEach((container) => {
      // Skip if already initialized
      if (container.dataset.imageRevealInit === 'true') return;

      // Find the image inside the container
      let image = container.querySelector('img');
      if (!image) {
        console.warn('image-reveal: No img element found in container');
        return;
      }

      const styles = getComputedStyle(container);

      // Get animation settings from CSS custom properties or data attributes
      let duration = parseFloat(styles.getPropertyValue('--reveal-duration')?.trim()) || 
                     parseFloat(container.dataset.revealDuration) || 1.5;
      let delay = parseFloat(styles.getPropertyValue('--animation-delay')?.trim()) ||
                  parseFloat(styles.getPropertyValue('--reveal-delay')?.trim()) || 
                  parseFloat(container.dataset.revealDelay) || 0;
      let ease = styles.getPropertyValue('--reveal-ease')?.trim() || 
                 container.dataset.revealEase || 'power2.out';
      let startTrigger = styles.getPropertyValue('--reveal-trigger')?.trim() || 
                         container.dataset.revealTrigger || 'top 85%';
      let imageScale = parseFloat(styles.getPropertyValue('--reveal-image-scale')?.trim()) || 
                       parseFloat(container.dataset.revealImageScale) || 1.3;

      // Clear any CSS transitions
      container.style.transition = 'none';
      container.style.willChange = 'clip-path';
      image.style.willChange = 'transform';

      // Create timeline with ScrollTrigger
      const tl = gsap.timeline({
        scrollTrigger: {
          trigger: container,
          start: startTrigger,
          toggleActions: 'play none none none', // Play once when entering viewport
        },
      });

      // Determine reveal direction (left, right, top, bottom)
      // Prefer data-attribute, then CSS var; always let helper classes override
      const directionAttr = (container.dataset.revealDirection || '').trim().toLowerCase();
      const directionCss = (styles.getPropertyValue('--reveal-direction') || '').trim().toLowerCase();
      let direction = directionAttr || directionCss;

      // Class helpers override everything for reliability
      if (container.classList.contains('image-reveal-right')) direction = 'right';
      else if (container.classList.contains('image-reveal-top')) direction = 'top';
      else if (container.classList.contains('image-reveal-bottom')) direction = 'bottom';
      else if (container.classList.contains('image-reveal-left')) direction = 'left';

      if (!direction) direction = 'left';
      const fullClip = 'inset(0% 0% 0% 0%)';
      let startClip;
      switch (direction) {
        case 'right':
          startClip = 'inset(0% 100% 0% 0%)'; // hide everything by pulling right edge in
          break;
        case 'top':
          startClip = 'inset(100% 0% 0% 0%)';
          break;
        case 'bottom':
          startClip = 'inset(0% 0% 100% 0%)';
          break;
        default: // 'left'
          startClip = 'inset(0% 0% 0% 100%)';
      }

      // Set container to visible
      tl.set(container, { 
        autoAlpha: 1,
        immediateRender: true,
      });

      // Reveal container using polygon clip-path
      tl.fromTo(
        container,
        {
          clipPath: startClip,
          webkitClipPath: startClip,
        },
        {
          clipPath: fullClip,
          webkitClipPath: fullClip,
          duration: duration,
          ease: ease,
        },
        delay
      );

      // Scale image simultaneously (zoom out effect)
      tl.from(
        image,
        {
          scale: imageScale,
          duration: duration,
          ease: ease,
        },
        delay // Start at same time as clip-path reveal
      );

      container.dataset.imageRevealInit = 'true';
    });
  }

  /* ==========================================
     CONTAINER REVEAL ANIMATION
     Mask reveal for any container (center-out or directional)
     ========================================== */
  function initContainerReveal() {
    const containers = gsap.utils.toArray('.container-reveal');

    containers.forEach((container) => {
      if (container.dataset.containerRevealInit === 'true') return;

      const styles = getComputedStyle(container);

      const duration = parseFloat(styles.getPropertyValue('--reveal-duration')?.trim()) || 
                       parseFloat(container.dataset.revealDuration) || 1.2;
      const delayRaw = styles.getPropertyValue('--animation-delay');
      const delay = (delayRaw && !Number.isNaN(parseFloat(delayRaw)))
        ? parseFloat(delayRaw)
        : 0;
      const ease = styles.getPropertyValue('--reveal-ease')?.trim() || 
                   container.dataset.revealEase || 'power2.out';
      const startTrigger = styles.getPropertyValue('--reveal-trigger')?.trim() || 
                           container.dataset.revealTrigger || 'top 85%';

      const directionAttr = (container.dataset.revealDirection || '').trim().toLowerCase();
      const directionCss = (styles.getPropertyValue('--reveal-direction') || '').trim().toLowerCase();
      let direction = directionAttr || directionCss || 'center';

      if (container.classList.contains('container-reveal-right')) direction = 'right';
      else if (container.classList.contains('container-reveal-top')) direction = 'top';
      else if (container.classList.contains('container-reveal-bottom')) direction = 'bottom';
      else if (container.classList.contains('container-reveal-left')) direction = 'left';
      else if (container.classList.contains('container-reveal-center')) direction = 'center';

      const fullClip = 'inset(0% 0% 0% 0%)';
      let startClip;
      switch (direction) {
        case 'right':
          startClip = 'inset(0% 100% 0% 0%)';
          break;
        case 'left':
          startClip = 'inset(0% 0% 0% 100%)';
          break;
        case 'top':
          startClip = 'inset(100% 0% 0% 0%)';
          break;
        case 'bottom':
          startClip = 'inset(0% 0% 100% 0%)';
          break;
        default: // center-out (both vertical sides)
          startClip = 'inset(50% 0% 50% 0%)';
      }

      // In the Elementor editor, show the end state for visibility without needing to play
      if (isEditor) {
        gsap.set(container, {
          autoAlpha: 1,
          clipPath: fullClip,
          webkitClipPath: fullClip,
          visibility: 'visible',
        });
        container.dataset.containerRevealInit = 'true';
        return;
      }

      const isScrollScrub = container.classList.contains('container-reveal-scroll');
      const scrollStart = styles.getPropertyValue('--reveal-scroll-start')?.trim() ||
                          container.dataset.revealScrollStart || 'top 85%';
      const scrollEnd = styles.getPropertyValue('--reveal-scroll-end')?.trim() ||
                        container.dataset.revealScrollEnd || 'top 20%';
      const forwardOnly = (container.dataset.revealForwardOnly || '').trim().toLowerCase() === 'true';

      // Ensure no interfering transitions and force initial masked state
      container.style.transition = 'none';
      gsap.set(container, {
        autoAlpha: 1,
        clipPath: startClip,
        webkitClipPath: startClip,
        visibility: 'visible',
      });

      if (isScrollScrub) {
        gsap.fromTo(
          container,
          {
            autoAlpha: 1,
            clipPath: startClip,
            webkitClipPath: startClip,
            visibility: 'visible',
          },
          {
            clipPath: fullClip,
            webkitClipPath: fullClip,
            ease,
            immediateRender: false,
            scrollTrigger: {
              trigger: container,
              start: scrollStart,
              end: scrollEnd,
              scrub: true,
              onUpdate: (self) => {
                if (forwardOnly) {
                  const max = Math.max(self.progress, self._maxProgress || 0);
                  self._maxProgress = max;
                  if (self.progress < max) {
                    self.animation.progress(max);
                  }
                }
              },
            },
          }
        );
      } else {
        const tl = gsap.timeline({
          scrollTrigger: {
            trigger: container,
            start: startTrigger,
            toggleActions: 'play none none none',
          },
        });

        tl.fromTo(
          container,
          {
            autoAlpha: 1,
            clipPath: startClip,
            webkitClipPath: startClip,
            immediateRender: false,
          },
          {
            clipPath: fullClip,
            webkitClipPath: fullClip,
            duration: duration,
            ease,
          },
          delay
        );
      }

      container.dataset.containerRevealInit = 'true';
    });
    if (window.ScrollTrigger) {
      ScrollTrigger.refresh();
    }
  }

  /* ==========================================
     SCROLL TRANSFORM SCRUB (scroll-linked, reversible optional)
     Uses same vars/data-* as scroll-transform plus:
     --transform-scroll-start / --transform-scroll-end
     data-transform-reversible="true" to allow reverse on scroll-back
     ========================================== */
  function initScrollTransformScrub() {
    const elements = gsap.utils.toArray('.scroll-transform-scrub');

    elements.forEach((el) => {
      if (el.dataset.scrollTransformScrubInit === 'true') return;

      const styles = getComputedStyle(el);

      const parseNum = (val, fallback = 0) => {
        const num = parseFloat((val || '').trim());
        return Number.isNaN(num) ? fallback : num;
      };
      const withUnit = (val, unit = 'px') => {
        if (!val) return '0' + unit;
        const t = val.trim();
        return /^-?\d+(\.\d+)?$/.test(t) ? t + unit : t;
      };
      const withDeg = (val) => {
        if (!val) return '0deg';
        const t = val.trim();
        return /^-?\d+(\.\d+)?$/.test(t) ? t + 'deg' : t;
      };
      const withPx = (val) => {
        if (!val) return '0px';
        const t = val.trim();
        return /^-?\d+(\.\d+)?$/.test(t) ? t + 'px' : t;
      };

      const startX = withUnit(styles.getPropertyValue('--transform-start-x') || el.dataset.transformStartX);
      const startY = withUnit(styles.getPropertyValue('--transform-start-y') || el.dataset.transformStartY);
      const startR = withDeg(styles.getPropertyValue('--transform-start-rotate') || el.dataset.transformStartRotate);
      const startS = parseNum(styles.getPropertyValue('--transform-start-scale') || el.dataset.transformStartScale, 1);
      const startO = parseNum(styles.getPropertyValue('--transform-start-opacity') || el.dataset.transformStartOpacity, 0);
      const startB = withPx(styles.getPropertyValue('--transform-start-blur') || el.dataset.transformStartBlur);

      const endX = withUnit(styles.getPropertyValue('--transform-end-x') || el.dataset.transformEndX);
      const endY = withUnit(styles.getPropertyValue('--transform-end-y') || el.dataset.transformEndY);
      const endR = withDeg(styles.getPropertyValue('--transform-end-rotate') || el.dataset.transformEndRotate);
      const endS = parseNum(styles.getPropertyValue('--transform-end-scale') || el.dataset.transformEndScale, 1);
      const endO = parseNum(styles.getPropertyValue('--transform-end-opacity') || el.dataset.transformEndOpacity, 1);
      const endB = withPx(styles.getPropertyValue('--transform-end-blur') || el.dataset.transformEndBlur);

      const ease = (styles.getPropertyValue('--transform-ease') || el.dataset.transformEase || 'none').trim();
      const startTrigger = (styles.getPropertyValue('--transform-scroll-start') || el.dataset.transformScrollStart || 'top 85%').trim();
      const endTrigger = (styles.getPropertyValue('--transform-scroll-end') || el.dataset.transformScrollEnd || 'top 15%').trim();
      const parseBool = (val) => {
        const t = (val || '').trim().toLowerCase();
        return t === 'true' || t === '1' || t === 'yes';
      };
      const lockForward = parseBool(styles.getPropertyValue('--transform-forward-only') || el.dataset.transformForwardOnly || 'false'); // scrub but never reverse progress

      // Initial state
      gsap.set(el, {
        x: startX,
        y: startY,
        rotation: startR,
        scale: startS,
        opacity: startO,
        filter: `blur(${startB})`,
        force3D: true,
        willChange: 'transform, opacity, filter',
      });

      gsap.to(el, {
        x: endX,
        y: endY,
        rotation: endR,
        scale: endS,
        opacity: endO,
        filter: `blur(${endB})`,
        ease,
        scrollTrigger: {
          trigger: el,
          start: startTrigger,
          end: endTrigger,
          scrub: true,
          onUpdate: (self) => {
            if (lockForward) {
              const max = Math.max(self.progress, self._maxProgress || 0);
              self._maxProgress = max;
              if (self.progress < max) {
                self.animation.progress(max);
              }
            }
          },
        },
      });

      el.dataset.scrollTransformScrubInit = 'true';
    });
  }

  /* ==========================================
     MASTER INIT
     ========================================== */
  function initAllAnimations() {
    initCoreAnimations();
    initScrollFillHeadings();
    initScrollTransform();
    initScrollTransformScrub();
    initImageReveal();
    initContainerReveal();
  }

  initAllAnimations();
  // Expose for editor preview tools
  window.initAllAnimations = initAllAnimations;
  window.initCoreAnimations = initCoreAnimations;
  window.initScrollFillHeadings = initScrollFillHeadings;
  window.initScrollTransform = initScrollTransform;
  window.initScrollTransformScrub = initScrollTransformScrub;
  window.initImageReveal = initImageReveal;
  window.initContainerReveal = initContainerReveal;
});
