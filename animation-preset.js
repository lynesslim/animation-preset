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

  /* ==========================================
     CORE ANIMATIONS: split-text / blur / fade
     ========================================== */
  function initCoreAnimations() {
    const elements = document.querySelectorAll(
      '.split-text-reveal-up, .anim-fade-up, .blur-reveal'
    );

    elements.forEach((el) => {
      if (el.dataset.animInit === 'true') return;

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
        tl.from(split.words, {
          y: '120%',
          opacity: 0,
          filter: 'blur(20px)',
          duration,
          delay,
          stagger: 0.08,
          ease: 'power2.out',
          onComplete: () => gsap.set(split.words, { filter: 'blur(0px)' }),
        });
        
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
            0 // Start at position 0 (concurrent with split animation)
          );
        }
      }
      // CASE 2: SPLIT ONLY (no blur, only if SplitType available)
      else if (hasSplit && !hasBlur && split) {
        tl.from(split.words, {
          y: '120%',
          opacity: 0,
          duration: 0.6,
          stagger: 0.08,
          ease: 'power2.out',
        });
        
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
            0 // Start at position 0 (concurrent with split animation)
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
        tl.fromTo(
          el,
          { y: 20, opacity: 0 },
          {
            y: 0,
            opacity: 1,
            duration: 0.6,
            ease: 'power2.out',
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
      let delay = parseFloat(styles.getPropertyValue('--transform-delay')?.trim()) || 
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
      let delay = parseFloat(styles.getPropertyValue('--reveal-delay')?.trim()) || 
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

      // Set container to visible
      tl.set(container, { 
        autoAlpha: 1,
        immediateRender: true,
      });

      // Reveal container using polygon clip-path (from left to right)
      tl.fromTo(
        container,
        {
          clipPath: 'polygon(0 0, 0 0, 0 100%, 0% 100%)',
          webkitClipPath: 'polygon(0 0, 0 0, 0 100%, 0% 100%)',
        },
        {
          clipPath: 'polygon(0 0, 100% 0, 100% 100%, 0 100%)',
          webkitClipPath: 'polygon(0 0, 100% 0, 100% 100%, 0 100%)',
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
     MASTER INIT
     ========================================== */
  function initAllAnimations() {
    initCoreAnimations();
    initScrollFillHeadings();
    initScrollTransform();
    initImageReveal();
  }

  initAllAnimations();
});

