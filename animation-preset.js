document.addEventListener('DOMContentLoaded', function () {
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
          types: isWord ? 'words' : 'chars',
          whitespace: 'preserve',
        });

        // Preserve spacing when splitting into characters so words don't collapse together
        if (!isWord && split.chars?.length) {
          split.chars.forEach((charEl) => {
            // Treat any whitespace (space, newline, tabs) as a non-breaking space
            if (/^[\s\n\r\t]+$/.test(charEl.textContent)) {
              charEl.innerHTML = '&nbsp;';
            }
            charEl.style.display = 'inline-block';
            charEl.style.whiteSpace = 'pre';
            charEl.style.lineHeight = 'inherit';
          });
        }

        // Preserve spacing and line-height when splitting into words (handles inline spans).
        // If SplitType drops an inter-word space (common around inline spans), insert a breakable space node.
        if (isWord && split.words?.length) {
          const ordered = Array.from(
            textTarget.querySelectorAll('.word, .whitespace')
          );

          ordered.forEach((node, idx) => {
            if (node.classList.contains('word')) {
              node.style.display = 'inline-block';
              node.style.whiteSpace = 'normal';
              node.style.lineHeight = 'inherit';
              node.style.marginRight = '0';

              const next = ordered[idx + 1];
              if (next && next.classList.contains('word')) {
                const spacer = document.createElement('span');
                spacer.className = 'whitespace';
                spacer.textContent = ' ';
                spacer.style.display = 'inline';
                spacer.style.whiteSpace = 'normal';
                spacer.style.lineHeight = 'inherit';
                spacer.style.fontSize = 'inherit';
                node.insertAdjacentElement('afterend', spacer);
              }
            } else if (node.classList.contains('whitespace')) {
              node.textContent = ' ';
              node.style.display = 'inline';
              node.style.whiteSpace = 'normal';
              node.style.lineHeight = 'inherit';
              node.style.fontSize = 'inherit';
            }
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

          // Optional per-word blur for scrubbed variant
          const isWordBlurYScroll = el.classList.contains('split-text-word-fade-y-blur-scroll');
          let blurStart = (styles.getPropertyValue('--word-blur-start') || '').trim();
          if (isWord && isWordBlurYScroll) {
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
            },
            // Don't revert on scroll-scrubbed versions - they need to stay split
          };

          if (isWord && isWordBlurYScroll) {
            fromVars.filter = `blur(${blurStart})`;
            toVars.filter = 'blur(0px)';
          }

          gsap.fromTo(
            isWord ? split.words : split.chars,
            fromVars,
            toVars
          );
        } else {
          // One-time trigger version (supports optional blur on words)
          const isWordBlurY = el.classList.contains('split-text-word-fade-y-blur');
          let blurStart = (styles.getPropertyValue('--word-blur-start') || '').trim();
          if (isWordBlurY) {
            if (!blurStart) blurStart = '20px';
            // Ensure px unit
            if (/^-?\d+(?:\.\d+)?$/.test(blurStart)) {
              blurStart = blurStart + 'px';
            }
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
            onComplete: () => split.revert(),
          };

          if (isWord && isWordBlurY) {
            fromVars.filter = `blur(${blurStart})`;
            toVars.filter = 'blur(0px)';
          }

          gsap.fromTo(
            isWord ? split.words : split.chars,
            fromVars,
            toVars
          );
          // Ensure elements start in their "from" state before scroll triggers
          gsap.set(isWord ? split.words : split.chars, fromVars);
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

      // Get initial values from CSS custom properties or data attributes
      let startX = styles.getPropertyValue('--transform-start-x')?.trim() || 
                   el.dataset.transformStartX || '0px';
      let startY = styles.getPropertyValue('--transform-start-y')?.trim() || 
                   el.dataset.transformStartY || '0px';
      let startRotate = styles.getPropertyValue('--transform-start-rotate')?.trim() || 
                        el.dataset.transformStartRotate || '0deg';
      let startScale = parseFloat(styles.getPropertyValue('--transform-start-scale')?.trim()) || 
                       parseFloat(el.dataset.transformStartScale) || 1;
      let startOpacity = parseFloat(styles.getPropertyValue('--transform-start-opacity')?.trim()) || 
                         parseFloat(el.dataset.transformStartOpacity) || 0;
      let startBlur = styles.getPropertyValue('--transform-start-blur')?.trim() || 
                      el.dataset.transformStartBlur || '0px';

      // Get end values from CSS custom properties or data attributes
      let endX = styles.getPropertyValue('--transform-end-x')?.trim() || 
                 el.dataset.transformEndX || '0px';
      let endY = styles.getPropertyValue('--transform-end-y')?.trim() || 
                 el.dataset.transformEndY || '0px';
      let endRotate = styles.getPropertyValue('--transform-end-rotate')?.trim() || 
                      el.dataset.transformEndRotate || '0deg';
      let endScale = parseFloat(styles.getPropertyValue('--transform-end-scale')?.trim()) || 
                     parseFloat(el.dataset.transformEndScale) || 1;
      let endOpacity = parseFloat(styles.getPropertyValue('--transform-end-opacity')?.trim()) || 
                       parseFloat(el.dataset.transformEndOpacity) || 1;
      let endBlur = styles.getPropertyValue('--transform-end-blur')?.trim() || 
                    el.dataset.transformEndBlur || '0px';

      // Get animation settings
      let duration = parseFloat(styles.getPropertyValue('--transform-duration')?.trim()) || 
                     parseFloat(el.dataset.transformDuration) || 1;
      let delay = parseFloat(styles.getPropertyValue('--animation-delay')?.trim()) ||
                  parseFloat(styles.getPropertyValue('--transform-delay')?.trim()) || 
                  parseFloat(el.dataset.transformDelay) || 0;
      let ease = styles.getPropertyValue('--transform-ease')?.trim() || 
                 el.dataset.transformEase || 'power2.out';
      let startTrigger = styles.getPropertyValue('--transform-trigger')?.trim() || 
                         el.dataset.transformTrigger || 'top 85%';

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
      // Skip if already initialized
      if (wrapper.dataset.scrollFillInit === 'true') return;

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

      // Get its current text color (from Elementor Style tab)
      const computedColor = getComputedStyle(el).color;

      const match = computedColor.match(
        /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/
      );

      let r = 0,
        g = 0,
        b = 0,
        a = 1;
      if (match) {
        r = parseInt(match[1], 10);
        g = parseInt(match[2], 10);
        b = parseInt(match[3], 10);
        a = match[4] !== undefined ? parseFloat(match[4]) : 1;
      } else {
        // Fallback: try to get color from wrapper or use black
        const wrapperColor = getComputedStyle(wrapper).color;
        const wrapperMatch = wrapperColor.match(
          /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/
        );
        if (wrapperMatch) {
          r = parseInt(wrapperMatch[1], 10);
          g = parseInt(wrapperMatch[2], 10);
          b = parseInt(wrapperMatch[3], 10);
          a = wrapperMatch[4] !== undefined ? parseFloat(wrapperMatch[4]) : 1;
        }
      }

      const dimColor = `rgba(${r}, ${g}, ${b}, 0.2)`; // dim (for initial state)
      const fullColor = `rgba(${r}, ${g}, ${b}, ${a})`; // full (for gradient)

      // Apply styles directly to the text element
      // Start with dimmed text visible, then gradient fills over it
      el.style.color = dimColor; // Initial dimmed text (visible at 0.2 opacity)
      el.style.backgroundImage = `linear-gradient(to right, ${fullColor}, ${fullColor})`;
      el.style.backgroundRepeat = 'no-repeat';
      el.style.backgroundSize = '0% 100%';
      el.style.webkitBackgroundClip = 'text';
      el.style.backgroundClip = 'text';
      el.style.webkitTextFillColor = dimColor; // Start with dimmed color visible
      el.style.display = 'inline-block'; // Required for backgroundClip to work properly

      // Animate both the background-size and text-fill-color on scroll
      gsap.to(el, {
        backgroundSize: '100% 100%',
        webkitTextFillColor: 'transparent', // Fade text to transparent as gradient fills
        ease: 'none',
        scrollTrigger: {
          trigger: wrapper, // Use wrapper as trigger for better detection
          start: 'top 85%',
          end: 'top 20%',
          scrub: true,
        },
      });

      wrapper.dataset.scrollFillInit = 'true';
    });
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
                       parseFloat(container.dataset.revealDuration) || 1;
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

      const isScrollScrub = container.classList.contains('container-reveal-scroll');
      const scrollStart = styles.getPropertyValue('--reveal-scroll-start')?.trim() ||
                          container.dataset.revealScrollStart || 'top 85%';
      const scrollEnd = styles.getPropertyValue('--reveal-scroll-end')?.trim() ||
                        container.dataset.revealScrollEnd || 'top 20%';

      // Ensure no interfering transitions and force initial masked state
      container.style.transition = 'none';
      gsap.set(container, {
        autoAlpha: 1,
        clipPath: startClip,
        webkitClipPath: startClip,
        visibility: 'visible',
      });

      const tl = gsap.timeline({
        scrollTrigger: isScrollScrub
          ? {
              trigger: container,
              start: scrollStart,
              end: scrollEnd,
              scrub: true,
            }
          : {
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
          duration: isScrollScrub ? 1 : duration,
          ease,
        },
        delay
      );

      container.dataset.containerRevealInit = 'true';
    });
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
});
