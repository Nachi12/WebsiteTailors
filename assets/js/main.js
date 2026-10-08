/**
 * Website Tailors — Centralized 60fps Motion Engine
 * Pure Vanilla JavaScript (No Frameworks, No Libraries)
 */

console.log("[Website Tailors Motion] main.js loaded");

document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  /* =========================================================
     0. MOTION PREFERENCES & SETUP
  ========================================================= */
  document.documentElement.classList.add("force-motion");

  /* =========================================================
     1. PRELOADER & HERO ENTRANCE COORDINATION (PHASE 2)
  ========================================================= */
  const loader = document.getElementById("loader");
  let isPageReady = false;

  function triggerPageReady() {
    if (isPageReady) return;
    isPageReady = true;
    document.body.classList.add("page-ready");
    document.body.classList.add("js-loaded");

    // Once hero entrance sequence finishes settling (~1400ms),
    // mark settled so mouse parallax on floating cards runs without CSS transition delay
    setTimeout(() => {
      document.body.classList.add("hero-settled");
    }, 1400);
  }

  if (loader) {
    // Elegant entrance: dismiss preloader after 280ms so hero sequence reveals smoothly
    setTimeout(() => {
      loader.classList.add("hide");
      setTimeout(triggerPageReady, 100);
    }, 280);
  } else {
    triggerPageReady();
  }

  /* =========================================================
     2. GLOBAL MOTION STATE VARIABLES (CENTRALIZED ARCHITECTURE)
  ========================================================= */
  const isFinePointer = window.matchMedia("(pointer: fine)").matches && window.matchMedia("(hover: hover)").matches;
  const isDesktop = window.innerWidth > 900 && isFinePointer;

  let targetMouseX = -100, targetMouseY = -100;
  let currentMouseX = -100, currentMouseY = -100;
  let ringX = -100, ringY = -100;
  let isMouseActive = false;

  let targetScroll = window.scrollY;
  let currentScroll = window.scrollY;

  let targetTiltX = 0, targetTiltY = 0;
  let currentTiltX = 0, currentTiltY = 0;

  let targetProgress = 0;
  let currentProgress = 0;

  /* =========================================================
     3. DOM ELEMENTS
  ========================================================= */
  const cursor = document.querySelector(".cursor");
  const ring = document.querySelector(".cursor-ring");
  const navbar = document.getElementById("navbar");
  const floatingSystem = document.getElementById("floatingSystem");
  const projectCards = document.querySelectorAll(".projects .project");
  const processSection = document.querySelector(".process");
  const processProgress = document.getElementById("processProgress");
  const processSteps = document.querySelectorAll(".process-step");
  const magneticElements = document.querySelectorAll(".magnetic");

  /* =========================================================
     4. EVENT LISTENERS FOR STATE
  ========================================================= */
  if (isDesktop) {
    window.addEventListener("mousemove", (e) => {
      targetMouseX = e.clientX;
      targetMouseY = e.clientY;

      if (!isMouseActive) {
        isMouseActive = true;
        currentMouseX = targetMouseX;
        currentMouseY = targetMouseY;
        ringX = targetMouseX;
        ringY = targetMouseY;
        if (cursor) cursor.style.opacity = "1";
        if (ring) ring.style.opacity = "1";
      }

      // Automatic global high-contrast background detection for custom cursor
      let isDarkSection = false;
      if (document.body.classList.contains("modal-open")) {
        isDarkSection = true;
      } else if (e.target && e.target !== document.body && e.target !== document.documentElement) {
        // Fast path: known dark containers & sections
        const darkParent = e.target.closest(
          ".statement, .cta, footer, .questionnaire-modal, .qn-modal, .dark-bg, .bg-dark, .dark-section, [data-theme='dark'], .nav-overlay, .menu-overlay, .project-modal"
        );
        if (darkParent) {
          isDarkSection = true;
        } else {
          // Dynamic path: inspect computed background color of target or up to 3 ancestors
          let curr = e.target;
          let depth = 0;
          while (curr && curr !== document.body && depth < 3) {
            try {
              const bg = window.getComputedStyle(curr).backgroundColor;
              if (bg && bg !== "transparent" && bg !== "rgba(0, 0, 0, 0)") {
                const match = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
                if (match) {
                  const r = parseInt(match[1], 10);
                  const g = parseInt(match[2], 10);
                  const b = parseInt(match[3], 10);
                  const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                  if (brightness < 128) {
                    isDarkSection = true;
                  }
                  break;
                }
              }
            } catch (err) {
              // Ignore detached element errors
            }
            curr = curr.parentElement;
            depth++;
          }
        }
      }

      if (isDarkSection) {
        document.body.classList.add("cursor-dark-bg");
      } else {
        document.body.classList.remove("cursor-dark-bg");
      }

      // Hero subtle micro-tilt targets (Section 8: max displacement 10-20px with smooth inertia)
      const heroOrbitalWrapper = document.getElementById("heroOrbitalWrapper");
      if ((heroOrbitalWrapper || floatingSystem) && e.clientY < window.innerHeight * 1.05) {
        const normX = (e.clientX / window.innerWidth) - 0.5;
        const normY = (e.clientY / window.innerHeight) - 0.5;
        targetTiltX = normX * 28.0; // [-14.0px, +14.0px]
        targetTiltY = normY * 22.0; // [-11.0px, +11.0px]
      } else {
        targetTiltX = 0;
        targetTiltY = 0;
      }
    }, { passive: true });

    document.addEventListener("mouseleave", () => {
      if (cursor) cursor.style.opacity = "0";
      if (ring) ring.style.opacity = "0";
      isMouseActive = false;
      targetTiltX = 0;
      targetTiltY = 0;
    });

    document.addEventListener("mouseenter", () => {
      isMouseActive = true;
      if (cursor) cursor.style.opacity = "1";
      if (ring) ring.style.opacity = "1";
    });

    document.addEventListener("mousedown", () => {
      document.body.classList.add("cursor-active");
    });
    document.addEventListener("mouseup", () => {
      document.body.classList.remove("cursor-active");
    });

    const hoverSelectors = "a, button, input, textarea, select, .service, .principle, .project, .testimonial-card, .menu, .magnetic, [role='button'], .qn-option-card, .qn-next-btn, .qn-back-btn, .qn-modal-close, .btn, .cta-btn, .nav-link, .vortex-card";

    // Event delegation for cursor-hover state across static & dynamic DOM elements
    document.addEventListener("mouseover", (e) => {
      if (e.target && e.target.closest(hoverSelectors)) {
        document.body.classList.add("cursor-hover");
      }
    }, { passive: true });

    document.addEventListener("mouseout", (e) => {
      if (e.target && e.target.closest(hoverSelectors)) {
        if (!e.relatedTarget || !e.relatedTarget.closest(hoverSelectors)) {
          document.body.classList.remove("cursor-hover");
        }
      }
    }, { passive: true });

    /* =========================================================
       HERO VORTEX CARD ENGINE (PHASE 2 — CONTINUOUS VORTEX MOTION)
       12 cards continuously travel through 12 authored position
       states, forming a flowing vortex ring. Each card smoothly
       interpolates x, y, scale, rotation, depth between positions.

       Architecture:
         • Card i starts at position P_i
         • All cards advance at the same rate around the ring
         • Per-segment cubic-bezier easing creates a natural wave
         • P11 → P0 wraps seamlessly (adjacent at top of ring)
         • Hover pauses the vortex; mouse parallax preserved
         • Full cycle: ~13.8 seconds (12 segments × 1150ms)
    ========================================================= */
    const orbitalWrapper = document.getElementById("heroOrbitalWrapper");
    const vortexCards = document.querySelectorAll(".vortex-card");

    if (vortexCards.length > 0) {
      // Touch device detection
      const isTouchDevice = window.matchMedia("(pointer: coarse)").matches || ('ontouchstart' in window);

      /* ----------------------------------------------------------
         12 AUTHORED POSITION STATES (Phase 4 Reference Matching)
         Refined against Velara Vortex:
         - Expanded radius & spacing to reduce overlap
         - Elliptical vortexed layout with open center
         - Clear individual card readability at all 12 stages
         - Camera perspective scaling (0.72 deep rear -> 1.03 front)
      ---------------------------------------------------------- */
      const VORTEX_POSITIONS = [
        // P0  — 12 o'clock (top center, deepest back, elevated)
        { x:    0,  y: -190, scale: 0.72, zIndex: 1,  rotation: -0.8, depth: 0.04 },
        // P1  — ~1 o'clock (top-right, back)
        { x:  155,  y: -155, scale: 0.75, zIndex: 2,  rotation:  1.8, depth: 0.12 },
        // P2  — ~2:15 (upper-right, mid-back)
        { x:  255,  y:  -78, scale: 0.82, zIndex: 4,  rotation:  2.8, depth: 0.28 },
        // P3  — ~3:30 (right, mid-front)
        { x:  285,  y:   25, scale: 0.89, zIndex: 6,  rotation:  2.4, depth: 0.50 },
        // P4  — ~4:30 (lower-right, front)
        { x:  240,  y:  125, scale: 0.96, zIndex: 9,  rotation:  1.2, depth: 0.74 },
        // P5  — ~5:30 (bottom-right, front)
        { x:  135,  y:  180, scale: 1.01, zIndex: 11, rotation: -0.6, depth: 0.92 },
        // P6  — 6 o'clock (bottom center, closest to viewer)
        { x:   -5,  y:  192, scale: 1.03, zIndex: 12, rotation: -1.6, depth: 1.00 },
        // P7  — ~7 o'clock (bottom-left, front)
        { x: -150,  y:  172, scale: 1.00, zIndex: 10, rotation: -2.4, depth: 0.88 },
        // P8  — ~8:15 (lower-left, mid-front)
        { x: -250,  y:  102, scale: 0.93, zIndex: 7,  rotation: -3.2, depth: 0.64 },
        // P9  — ~9:30 (left, mid-back)
        { x: -285,  y:  -10, scale: 0.85, zIndex: 5,  rotation: -3.4, depth: 0.38 },
        // P10 — ~10:30 (upper-left, back)
        { x: -250,  y: -115, scale: 0.78, zIndex: 3,  rotation: -2.0, depth: 0.18 },
        // P11 — ~11:15 (top-left, deep back)
        { x: -145,  y: -168, scale: 0.73, zIndex: 1,  rotation:  0.6, depth: 0.07 }
      ];

      const NUM_POS = VORTEX_POSITIONS.length; // 12

      /* ----------------------------------------------------------
         CUBIC BEZIER EASING — cubic-bezier(0.22, 1, 0.36, 1)
         Aggressive ease-out: fast departure, gentle arrival.
         Newton-Raphson solver for precise curve evaluation.
      ---------------------------------------------------------- */
      function buildCubicBezier(x1, y1, x2, y2) {
        const cx = 3 * x1, bx = 3 * (x2 - x1) - cx, ax = 1 - cx - bx;
        const cy = 3 * y1, by = 3 * (y2 - y1) - cy, ay = 1 - cy - by;
        function sampleX(t) { return ((ax * t + bx) * t + cx) * t; }
        function sampleY(t) { return ((ay * t + by) * t + cy) * t; }
        function solveCurveX(x) {
          let t2 = x;
          for (let i = 0; i < 8; i++) {
            const err = sampleX(t2) - x;
            if (Math.abs(err) < 1e-6) return t2;
            const d = (3 * ax * t2 + 2 * bx) * t2 + cx;
            if (Math.abs(d) < 1e-6) break;
            t2 -= err / d;
          }
          return t2;
        }
        return function(x) { return sampleY(solveCurveX(x)); };
      }

      const vortexEase = buildCubicBezier(0.22, 1, 0.36, 1);

      /* ----------------------------------------------------------
         TIMING CONSTANTS (Phase 4: 15.0s stately studio cycle)
      ---------------------------------------------------------- */
      const SEGMENT_MS = 1250;              // ms per position-to-position transition
      const CYCLE_MS   = SEGMENT_MS * NUM_POS; // 15,000ms full cycle

      /* ----------------------------------------------------------
         ANIMATION STATE
      ---------------------------------------------------------- */
      let vortexStartTs   = null;   // first rAF timestamp
      let isHoveringVortex = false;
      let totalPausedMs   = 0;      // accumulated pause duration
      let pauseBeganTs    = null;   // timestamp when current pause started

      /* ----------------------------------------------------------
         RESPONSIVE SCALE FACTOR & CARD COUNT (Phase 5 QA)
         Breakpoints:
         - Desktop: 1440, 1366, 1280 (12 cards)
         - Tablet:  1024 (10 cards), 768 (9 cards) [target: 8-10 cards]
         - Mobile:  430, 412, 390 (7 cards), 375, 360, 320 (6 cards) [target: 6-8 cards]
      ---------------------------------------------------------- */
      function getResponsiveScale() {
        const w = window.innerWidth;
        if (w <= 360)  return { factor: 0.32, visibleCount: 6  }; // 320, 360: 6 cards
        if (w <= 430)  return { factor: 0.38, visibleCount: 7  }; // 375, 390, 412, 430: 7 cards
        if (w <= 600)  return { factor: 0.46, visibleCount: 8  }; // 480-600: 8 cards
        if (w <= 768)  return { factor: 0.56, visibleCount: 9  }; // 768: 9 cards
        if (w <= 1024) return { factor: 0.72, visibleCount: 10 }; // 1024: 10 cards
        if (w <= 1280) return { factor: 0.88, visibleCount: 12 }; // 1280: 12 cards
        return { factor: 1.0, visibleCount: 12 };                 // 1366, 1440+: 12 cards
      }

      /* ----------------------------------------------------------
         LERP UTILITY
      ---------------------------------------------------------- */
      function lerp(a, b, t) { return a + (b - a) * t; }

      /* ----------------------------------------------------------
         WRAPPER GEOMETRY CACHE
         Cached on resize and scroll to prevent layout thrashing in rAF.
      ---------------------------------------------------------- */
      let wrapperCenter = { x: window.innerWidth * 0.72, y: window.innerHeight * 0.45 };
      function updateWrapperCenter() {
        if (orbitalWrapper) {
          const r = orbitalWrapper.getBoundingClientRect();
          wrapperCenter.x = r.left + r.width / 2;
          wrapperCenter.y = r.top + r.height / 2;
        }
      }
      updateWrapperCenter();
      window.addEventListener("resize", updateWrapperCenter, { passive: true });
      window.addEventListener("scroll", updateWrapperCenter, { passive: true });

      /* ----------------------------------------------------------
         RENDER FRAME
         Computes each card's interpolated position based on
         elapsed time. Applies Phase 3 subtle pointer engagement:
         - Collective displacement (10-20px max)
         - Proximity depth response (3-5% scale/brightness/shadow)
         - Micro-rotation (±2deg max)
      ---------------------------------------------------------- */
      function renderVortex(elapsedMs, tiltX, tiltY) {
        const { factor, visibleCount } = getResponsiveScale();

        // 1. Whole-system pointer displacement (10-20px max, disabled on touch/reduced motion)
        const shiftX = (isTouchDevice || prefersReducedMotion) ? 0 : (tiltX || 0);
        const shiftY = (isTouchDevice || prefersReducedMotion) ? 0 : (tiltY || 0);

        // Pointer proximity relative to vortex center
        const pointerRelX = currentMouseX - wrapperCenter.x;
        const pointerRelY = currentMouseY - wrapperCenter.y;
        const hasPointerFocus = isMouseActive && !isTouchDevice && !prefersReducedMotion && (currentMouseY < window.innerHeight * 1.12);
        const INFLUENCE_RADIUS = 210 * factor;

        vortexCards.forEach((card, i) => {
          // Hide excess cards on small viewports
          if (i >= visibleCount) {
            card.style.display = "none";
            return;
          }
          card.style.display = "block";

          // Uniformly distribute visible cards around the full cycle
          const cardIntervalMs = CYCLE_MS / visibleCount;
          const basePhaseMs    = i * cardIntervalMs;
          const progressMs     = ((elapsedMs + basePhaseMs) % CYCLE_MS + CYCLE_MS) % CYCLE_MS;

          // Which two position states are we interpolating between?
          const segmentFloat = progressMs / SEGMENT_MS;
          const fromIdx      = Math.floor(segmentFloat) % NUM_POS;
          const toIdx        = (fromIdx + 1) % NUM_POS;
          const rawT         = segmentFloat - Math.floor(segmentFloat);
          const t            = vortexEase(rawT);

          const from = VORTEX_POSITIONS[fromIdx];
          const to   = VORTEX_POSITIONS[toIdx];

          // Interpolate all authored properties
          let x        = lerp(from.x,        to.x,        t) * factor;
          let y        = lerp(from.y,        to.y,        t) * factor;
          const scale  = lerp(from.scale,    to.scale,    t);
          const rot    = lerp(from.rotation, to.rotation, t);
          const depth  = lerp(from.depth,    to.depth,    t);

          // Collective pointer displacement (entire system shifts together, no individual chasing)
          x += shiftX;
          y += shiftY;

          // Depth Response: Proximity to pointer (scale, brightness, shadow change)
          let prox = 0;
          let deltaRot = 0;
          if (hasPointerFocus) {
            const dx = pointerRelX - x;
            const dy = pointerRelY - y;
            const dist = Math.hypot(dx, dy);
            if (dist < INFLUENCE_RADIUS) {
              const rawP = 1 - (dist / INFLUENCE_RADIUS);
              prox = rawP * rawP * (3 - 2 * rawP); // smooth Hermite curve
            }

            // Pointer interaction adds ±2deg maximum rotation (NO spinning)
            const tiltComponent = (shiftX / 14.0) * 1.2;
            const proxTiltComponent = prox > 0 ? -(dx / INFLUENCE_RADIUS) * 0.8 : 0;
            deltaRot = Math.max(-2.0, Math.min(2.0, tiltComponent + proxTiltComponent));
          }

          // Depth response scale increase: subtle 3-5% range (+3.5% at peak proximity)
          const proxScale = 1 + (prox * 0.035);
          const finalScale = scale * proxScale;

          // Depth response brightness increase: subtle (+4.0% at peak proximity)
          const brightness = 1 + (prox * 0.040);

          // Card rotation with subtle pointer interaction (±2deg max)
          const finalRot = rot + deltaRot;

          // Depth-driven z-index (granular to avoid z-fighting)
          const zIndex = Math.round(2 + depth * 100);

          // Depth-driven opacity: 0.70 (deep rear) → 1.00 (closest front)
          const opacity = (0.70 + depth * 0.30).toFixed(2);

          // Depth-driven shadow matching Velara reference (soft, multi-tiered physical depth)
          const shadowY     = Math.round(5 + depth * 18 + prox * 4);
          const shadowBlur  = Math.round(14 + depth * 32 + prox * 8);
          const shadowAlpha = (0.22 + depth * 0.44 + prox * 0.06).toFixed(2);
          const borderAlpha = (0.06 + depth * 0.14 + prox * 0.08).toFixed(2);

          // Apply transform (no CSS transition — driven by rAF)
          card.style.transform   = `translate3d(${x.toFixed(1)}px, ${y.toFixed(1)}px, 0) scale(${finalScale.toFixed(3)}) rotate(${finalRot.toFixed(1)}deg)`;
          card.style.zIndex      = zIndex;
          card.style.opacity     = opacity;
          card.style.borderColor = `rgba(255, 255, 255, ${borderAlpha})`;
          card.style.boxShadow   = `0 ${shadowY}px ${shadowBlur}px -8px rgba(0, 0, 0, ${shadowAlpha}), 0 3px 10px -2px rgba(0, 0, 0, 0.30)`;
          card.style.filter      = prox > 0.01 ? `brightness(${brightness.toFixed(3)})` : "none";
        });
      }

      /* ----------------------------------------------------------
         ANIMATION LOOP
         Runs via requestAnimationFrame. Pauses on hover.
         Computes effective elapsed time excluding paused periods.
      ---------------------------------------------------------- */
      function tickVortex(timestamp) {
        if (!vortexStartTs) vortexStartTs = timestamp;

        // Compute elapsed time excluding any paused periods
        let elapsedMs;
        if (isHoveringVortex) {
          // While paused, freeze at the moment pause began
          if (!pauseBeganTs) pauseBeganTs = timestamp;
          elapsedMs = pauseBeganTs - vortexStartTs - totalPausedMs;
        } else {
          // If resuming from pause, accumulate paused duration
          if (pauseBeganTs) {
            totalPausedMs += timestamp - pauseBeganTs;
            pauseBeganTs = null;
          }
          elapsedMs = timestamp - vortexStartTs - totalPausedMs;
        }

        renderVortex(elapsedMs, currentTiltX, currentTiltY);
        requestAnimationFrame(tickVortex);
      }

      /* ----------------------------------------------------------
         START & REDUCED MOTION SUPPORT
      ---------------------------------------------------------- */
      const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      if (!prefersReducedMotion) {
        requestAnimationFrame(tickVortex);
      } else {
        // Reduced motion: render stable static Phase 1 positions with zero pointer motion
        renderVortex(0, 0, 0);
      }

      // Dynamic media query change listener
      if (window.matchMedia) {
        window.matchMedia("(prefers-reduced-motion: reduce)").addEventListener("change", (e) => {
          if (e.matches) {
            renderVortex(0, 0, 0);
          }
        });
      }

      // Hover pause / resume
      if (orbitalWrapper) {
        orbitalWrapper.addEventListener("mouseenter", () => { isHoveringVortex = true; });
        orbitalWrapper.addEventListener("mouseleave", () => { isHoveringVortex = false; });
      }

      // Expose parallax hook for global mouse tracking
      window._updateHeroOrbitalParallax = function(tX, tY) {
        // In reduced-motion mode, keep a completely stable card arrangement
        if (prefersReducedMotion) {
          return;
        }
      };
    }

    /* =========================================================
       SURREAL HERO EXPERIMENT ENGINE (PHASE 1 — HERO ONLY)
       Subtle 3-Tier Spatial Depth Parallax:
       - Layer 1 (Background):  2–4px
       - Layer 2 (Monolith):    8–12px + subtle micro-tilt
       - Layer 3 (Foreground): 12–18px
       Fully respects touch devices and prefers-reduced-motion.
    ========================================================= */
    const surrealMonolith = document.getElementById("surrealMonolith");
    if (surrealMonolith) {
      const surrealBgLayer = document.getElementById("surrealBgLayer");
      const surrealSlab = document.querySelector(".surreal-slab-recessed");
      const surrealChip1 = document.getElementById("surrealChip1");
      const surrealChip2 = document.getElementById("surrealChip2");
      const surrealChip3 = document.getElementById("surrealChip3");
      const heroWork = document.querySelector(".hero-surreal-work");

      const isTouch = window.matchMedia("(pointer: coarse)").matches || ('ontouchstart' in window);
      const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

      let currentX = 0, currentY = 0;
      let targetX = 0, targetY = 0;

      if (!isTouch && !reducedMotion) {
        window.addEventListener("mousemove", (e) => {
          if (e.clientY < window.innerHeight * 1.15) {
            const normX = (e.clientX / window.innerWidth) - 0.5;
            const normY = (e.clientY / window.innerHeight) - 0.5;
            targetX = normX;
            targetY = normY;
          } else {
            targetX = 0;
            targetY = 0;
          }
        }, { passive: true });

        document.addEventListener("mouseleave", () => {
          targetX = 0;
          targetY = 0;
        });
      }

      function updateSurrealHeroParallax() {
        if (reducedMotion || isTouch) return;

        // Smooth damping (lerp 0.08)
        currentX += (targetX - currentX) * 0.08;
        currentY += (targetY - currentY) * 0.08;

        // 1. Background Layer Parallax (2–4px)
        if (surrealBgLayer) {
          const bgX = (currentX * 3.5).toFixed(2);
          const bgY = (currentY * 3.0).toFixed(2);
          surrealBgLayer.style.transform = `translate3d(${bgX}px, ${bgY}px, 0)`;
        }

        // 2. Midground Monolith (8–12px) + Micro-tilt (±2.5deg)
        if (surrealMonolith) {
          const monoX = (currentX * 11.0).toFixed(2);
          const monoY = (currentY * 9.0).toFixed(2);
          const rotY = (-11 + currentX * 4.5).toFixed(2);
          const rotX = (6 - currentY * 3.5).toFixed(2);
          const rotZ = (-1.5 + currentX * 1.0).toFixed(2);
          surrealMonolith.style.transform = `translate3d(${monoX}px, ${monoY}px, 0) rotateY(${rotY}deg) rotateX(${rotX}deg) rotateZ(${rotZ}deg)`;
        }

        // Midground Recessed Slab
        if (surrealSlab) {
          const slabX = (currentX * 6.0).toFixed(2);
          const slabY = (currentY * 5.0).toFixed(2);
          surrealSlab.style.transform = `translate3d(calc(-20px + ${slabX}px), calc(30px + ${slabY}px), -50px) rotate(-4.5deg)`;
        }

        // 3. Foreground Elements (12–18px)
        if (surrealChip1) {
          const c1X = (currentX * 16.0).toFixed(2);
          const c1Y = (currentY * 13.0).toFixed(2);
          surrealChip1.style.transform = `translate3d(${c1X}px, ${c1Y}px, 0) rotate(-1.5deg)`;
        }

        if (surrealChip2) {
          const c2X = (currentX * -14.0).toFixed(2);
          const c2Y = (currentY * -12.0).toFixed(2);
          surrealChip2.style.transform = `translate3d(${c2X}px, ${c2Y}px, 0) rotate(1deg)`;
        }

        if (surrealChip3) {
          const c3X = (currentX * 12.0).toFixed(2);
          const c3Y = (currentY * 10.0).toFixed(2);
          surrealChip3.style.transform = `translate3d(${c3X}px, ${c3Y}px, 0)`;
        }

        // Typographic focal point subtle reaction (2-3px)
        if (heroWork) {
          const wX = (currentX * 3.0).toFixed(2);
          const wY = (currentY * 2.0).toFixed(2);
          heroWork.style.transform = `translate3d(${wX}px, ${wY}px, 0)`;
        }
      }

      // Attach to centralized animation loop
      window._updateSurrealHero = updateSurrealHeroParallax;
    }

    // Magnetic buttons setup (Phase 13: 4-8px max pull, smooth interpolation)
    magneticElements.forEach((element) => {
      let rect = null;
      let btnTargetX = 0, btnTargetY = 0;
      let btnCurrentX = 0, btnCurrentY = 0;
      let isHovered = false;

      element.addEventListener("mouseenter", () => {
        rect = element.getBoundingClientRect();
        isHovered = true;
        element.style.transition = "none";
      });

      element.addEventListener("mousemove", (e) => {
        if (!rect) rect = element.getBoundingClientRect();
        const x = e.clientX - rect.left - rect.width / 2;
        const y = e.clientY - rect.top - rect.height / 2;
        const maxDist = 7;
        btnTargetX = Math.max(-maxDist, Math.min(maxDist, x * 0.16));
        btnTargetY = Math.max(-maxDist, Math.min(maxDist, y * 0.16));
      }, { passive: true });

      element.addEventListener("mouseleave", () => {
        isHovered = false;
        btnTargetX = 0;
        btnTargetY = 0;
        element.style.transition = "transform 0.45s var(--ease-main)";
        element.style.transform = "translate3d(0, 0, 0)";
        rect = null;
      });

      function updateMagnetic() {
        if (isHovered) {
          btnCurrentX += (btnTargetX - btnCurrentX) * 0.18;
          btnCurrentY += (btnTargetY - btnCurrentY) * 0.18;
          element.style.transform = `translate3d(${btnCurrentX.toFixed(2)}px, ${btnCurrentY.toFixed(2)}px, 0)`;
        }
      }
      element._updateMagnetic = updateMagnetic;
    });
  } else {
    if (cursor) cursor.style.display = "none";
    if (ring) ring.style.display = "none";
  }

  // Scroll listener updates targetScroll
  window.addEventListener("scroll", () => {
    targetScroll = window.scrollY;
  }, { passive: true });

  /* =========================================================
     5. CENTRALIZED REQUESTANIMATIONFRAME LOOP (PHASE 22)
  ========================================================= */
  let isNavScrolled = false;

  function animationLoop() {
    // 1. UPDATE CURSOR (Phase 12: dot: 0.25, ring: 0.12)
    if (isDesktop && cursor && ring && isMouseActive) {
      currentMouseX += (targetMouseX - currentMouseX) * 0.25;
      currentMouseY += (targetMouseY - currentMouseY) * 0.25;
      ringX += (targetMouseX - ringX) * 0.12;
      ringY += (targetMouseY - ringY) * 0.12;

      cursor.style.transform = `translate3d(${currentMouseX.toFixed(2)}px, ${currentMouseY.toFixed(2)}px, 0) translate(-50%, -50%)`;
      ring.style.transform = `translate3d(${ringX.toFixed(2)}px, ${ringY.toFixed(2)}px, 0) translate(-50%, -50%)`;
    }

    // 2. UPDATE HERO PARALLAX & SCROLL INTERACTION
    if (isDesktop) {
      currentTiltX += (targetTiltX - currentTiltX) * 0.06;
      currentTiltY += (targetTiltY - currentTiltY) * 0.06;

      if (typeof window._updateHeroOrbitalParallax === "function") {
        window._updateHeroOrbitalParallax(currentTiltX, currentTiltY);
      }
      if (typeof window._updateSurrealHero === "function") {
        window._updateSurrealHero();
      }
    }

    // Scroll interaction for hero: subtle fade & translate without layout shift
    const heroContent = document.querySelector(".hero-content");
    if (heroContent && currentScroll < window.innerHeight * 1.2) {
      const scrollProgress = Math.min(1, Math.max(0, currentScroll / (window.innerHeight * 0.85)));
      const heroFade = (1 - (scrollProgress * 0.32)).toFixed(3);
      const heroTranslateY = (currentScroll * 0.09).toFixed(2);
      heroContent.style.setProperty("--hero-fade", heroFade);
      heroContent.style.setProperty("--hero-scroll-y", `-${heroTranslateY}px`);
    }

    // 3. INTERPOLATE SCROLL
    currentScroll += (targetScroll - currentScroll) * 0.12;

    // 4. UPDATE NAVBAR (Phase 14)
    if (navbar) {
      const shouldBeScrolled = targetScroll > 40;
      if (shouldBeScrolled !== isNavScrolled) {
        isNavScrolled = shouldBeScrolled;
        if (shouldBeScrolled) {
          navbar.classList.add("scrolled");
        } else {
          navbar.classList.remove("scrolled");
        }
      }
    }

    // 5. UPDATE PROJECT STACKING (Desktop + Mobile Responsive Touch & Mouse Stacking)
    if (projectCards.length > 0) {
      const isMobile = window.innerWidth <= 767;
      const baseTop = isMobile ? 75 : 90;
      const step = isMobile ? 12 : 16;
      const range = isMobile ? 320 : 420;
      const maxScaleDrop = isMobile ? 0.025 : 0.03;
      const maxTranslateY = isMobile ? 6 : 10;

      const projectsContainer = document.querySelector(".projects");
      const scrollY = window.scrollY || window.pageYOffset || 0;
      const containerDocTop = projectsContainer
        ? projectsContainer.getBoundingClientRect().top + scrollY
        : 0;

      const len = projectCards.length;
      for (let i = 0; i < len; i++) {
        const card = projectCards[i];
        const topThreshold = baseTop + i * step;
        const cardDocTop = projectsContainer
          ? containerDocTop + card.offsetTop
          : (card.getBoundingClientRect().top + scrollY);

        // Distance scrolled past the point where the card reaches its sticky position
        const distance = Math.max(0, (scrollY + topThreshold) - cardDocTop);

        if (distance > 0) {
          const progress = Math.min(1, distance / range);
          const scale = 1 - (progress * maxScaleDrop);
          const translateY = -(progress * maxTranslateY);
          card.style.transform = `scale(${scale.toFixed(4)}) translateY(${translateY.toFixed(1)}px)`;
        } else {
          card.style.transform = "scale(1) translateY(0px)";
        }
      }
    }

    // 6. UPDATE PROCESS PROGRESS (Phase 10: currentProgress += (targetProgress - currentProgress) * 0.12)
    if (processSection && processProgress) {
      const rect = processSection.getBoundingClientRect();
      const vh = window.innerHeight;
      if (rect.top <= vh && rect.bottom >= 0) {
        const totalDistance = rect.height + vh * 0.2;
        const progress = Math.max(0, Math.min(1, (vh - rect.top) / totalDistance));
        targetProgress = progress;
      }

      currentProgress += (targetProgress - currentProgress) * 0.12;
      const isMobile = window.innerWidth <= 768;
      if (isMobile) {
        processProgress.style.transform = `scaleY(${currentProgress.toFixed(4)})`;
      } else {
        processProgress.style.transform = `scaleX(${currentProgress.toFixed(4)})`;
      }

      if (processSteps.length > 0) {
        processSteps.forEach((step, index) => {
          const stepThreshold = index / processSteps.length;
          if (currentProgress >= stepThreshold) {
            step.classList.add("active");
          } else {
            step.classList.remove("active");
          }
        });
      }
    }

    // 7. UPDATE MAGNETIC BUTTONS
    if (isDesktop) {
      magneticElements.forEach((el) => {
        if (el._updateMagnetic) el._updateMagnetic();
      });
    }

    requestAnimationFrame(animationLoop);
  }

  requestAnimationFrame(animationLoop);
  console.log("[Website Tailors Motion] initialized");

  /* =========================================================
     6. SCROLL REVEAL (INTERSECTION OBSERVER - PHASE 5 & 6)
  ========================================================= */
  const revealElements = document.querySelectorAll(
    ".reveal, .reveal-stagger, .statement, .cta, footer"
  );

  if ("IntersectionObserver" in window && revealElements.length > 0) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("visible");
            observer.unobserve(entry.target);
          }
        });
      },
      {
        threshold: 0.12,
        rootMargin: "0px 0px -40px 0px"
      }
    );

    revealElements.forEach((el) => observer.observe(el));
  } else {
    revealElements.forEach((el) => el.classList.add("visible"));
  }

  /* =========================================================
     7. MOBILE MENU
  ========================================================= */
  const menuButton = document.getElementById("menuButton");
  const mobileMenu = document.getElementById("mobileMenu");

  if (menuButton && mobileMenu) {
    const syncMenuState = () => {
      if (window.innerWidth > 900) {
        mobileMenu.classList.remove("active");
        menuButton.textContent = "☰";
        menuButton.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      }
    };

    // Clean initial state on desktop load
    syncMenuState();

    menuButton.addEventListener("click", () => {
      if (window.innerWidth <= 900) {
        const isOpen = mobileMenu.classList.toggle("active");
        menuButton.textContent = isOpen ? "✕" : "☰";
        menuButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
        document.body.style.overflow = isOpen ? "hidden" : "";
      } else {
        syncMenuState();
      }
    });

    mobileMenu.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        mobileMenu.classList.remove("active");
        menuButton.textContent = "☰";
        menuButton.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      });
    });

    window.addEventListener("resize", syncMenuState);
  }

  /* =========================================================
     8. SMOOTH ANCHOR LINK NAVIGATION & CTA TRIGGER WIRING
  ========================================================= */
  const modal = document.getElementById("questionnaireModal");
  const modalClose = document.getElementById("qnModalClose");
  const modalBackdrop = document.getElementById("qnModalBackdrop");
  const modalContainer = document.getElementById("qnModalContainer");
  const mainQuestionnaire = document.getElementById("projectQuestionnaire");

  function openQuestionnaireModal() {
    if (!modal) return;
    if (mainQuestionnaire && modalContainer && !modalContainer.contains(mainQuestionnaire)) {
      modalContainer.appendChild(mainQuestionnaire);
    }
    modal.classList.add("active");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
  }

  function closeQuestionnaireModal() {
    if (!modal) return;
    modal.classList.remove("active");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
  }

  if (modalClose) modalClose.addEventListener("click", closeQuestionnaireModal);
  if (modalBackdrop) modalBackdrop.addEventListener("click", closeQuestionnaireModal);

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal && modal.classList.contains("active")) {
      closeQuestionnaireModal();
    }
  });

  // Intercept all CTAs ("Let's Talk", "Start a Project", "Get in Touch")
  document.querySelectorAll('a[href="#contact"], .nav-cta, .hero-buttons a[href="#contact"]').forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      openQuestionnaireModal();
    });
  });

  /* =========================================================
     9. INTERACTIVE PROJECT QUESTIONNAIRE CONTROLLER
  ========================================================= */
  const qnWrapper = document.getElementById("projectQuestionnaire");
  if (qnWrapper) {
    // Micro-interaction: Specular liquid glass light reflection on pointer movement
    document.addEventListener("mousemove", (e) => {
      const cards = document.querySelectorAll(".qn-option-card");
      cards.forEach((card) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        if (x >= -40 && x <= rect.width + 40 && y >= -40 && y <= rect.height + 40) {
          card.style.setProperty("--mouse-x", `${(x / rect.width) * 100}%`);
          card.style.setProperty("--mouse-y", `${(y / rect.height) * 100}%`);
        }
      });
    });

    let currentStep = 1;
    const totalSteps = 3;

    const stepBadge = document.getElementById("qnStepBadge");
    const backBtn = document.getElementById("qnBackBtn");
    const progressBar = document.getElementById("qnProgressBar");
    const form = document.getElementById("contactForm") || document.getElementById("questionnaireForm");
    const feedbackMsg = document.getElementById("qnFeedback");
    const successScreen = document.getElementById("qnSuccessScreen");
    const resetBtn = document.getElementById("qnResetBtn");

    const inputService = document.getElementById("qnInputService");
    const inputCompany = document.getElementById("qnInputCompany");
    const businessInput = document.getElementById("qnBusinessInput");

    // Single Questionnaire State Object
    const qnState = {
      name: "",
      company: inputCompany ? inputCompany.value.trim() : "",
      service: inputService ? inputService.value.trim() : "",
      phone: "",
      email: "",
      projectDetails: ""
    };

    // Inline Step Validation Message Helpers
    function showStepError(stepEl, message) {
      if (!stepEl) return;
      let errEl = stepEl.querySelector(".qn-step-error");
      if (!errEl) {
        errEl = document.createElement("div");
        errEl.className = "qn-step-error";
        errEl.style.color = "#ff6b6b";
        errEl.style.fontSize = "13px";
        errEl.style.marginTop = "12px";
        errEl.style.marginBottom = "8px";
        errEl.style.fontFamily = "var(--font-heading, sans-serif)";
        errEl.style.letterSpacing = "0.02em";
        const actions = stepEl.querySelector(".qn-actions");
        if (actions) {
          stepEl.insertBefore(errEl, actions);
        } else {
          stepEl.appendChild(errEl);
        }
      }
      errEl.textContent = message;
      errEl.style.display = "block";
    }

    function clearStepError(stepEl) {
      if (!stepEl) return;
      const errEl = stepEl.querySelector(".qn-step-error");
      if (errEl) {
        errEl.style.display = "none";
        errEl.textContent = "";
      }
    }

    // Guarantee clean initial step state (only Step 1 active)
    qnWrapper.querySelectorAll(".qn-step").forEach((el) => {
      el.classList.toggle("active", el.getAttribute("data-step") === "1");
    });

    function goToStep(targetStep) {
      if (targetStep < 1 || targetStep > totalSteps) return;

      const currentStepEl = qnWrapper.querySelector(`.qn-step[data-step="${currentStep}"]`);
      const nextStepEl = qnWrapper.querySelector(`.qn-step[data-step="${targetStep}"]`);

      if (currentStepEl && nextStepEl && currentStep !== targetStep) {
        currentStepEl.classList.remove("active");
        nextStepEl.classList.add("active");
        currentStep = targetStep;

        if (stepBadge) stepBadge.textContent = `STEP ${currentStep} OF ${totalSteps}`;
        if (progressBar) progressBar.style.width = `${(currentStep / totalSteps) * 100}%`;
        if (backBtn) backBtn.style.display = currentStep > 1 ? "inline-block" : "none";

        const firstInput = nextStepEl.querySelector("input, textarea, button.qn-option-card");
        if (firstInput) {
          setTimeout(() => firstInput.focus(), 150);
        }
      }
    }

    // Validate specific step choice/input
    function validateStep(stepNumber) {
      const stepEl = qnWrapper.querySelector(`.qn-step[data-step="${stepNumber}"]`);
      clearStepError(stepEl);

      if (stepNumber === 1) {
        const nameInput = document.getElementById("qnNameInput");
        const val = nameInput ? nameInput.value.trim() : "";
        if (!val || val.length < 2) {
          showStepError(stepEl, "Please enter your name (minimum 2 characters).");
          if (nameInput) {
            nameInput.focus();
            nameInput.style.borderColor = "#ff6b6b";
          }
          return false;
        }
        if (businessInput && inputCompany) {
          inputCompany.value = businessInput.value.trim();
        }
      } else if (stepNumber === 2) {
        const val = inputService ? inputService.value.trim() : qnState.service;
        if (!val) {
          showStepError(stepEl, "Please select an option to continue.");
          return false;
        }
      }
      return true;
    }

    // Option Cards (Step 2)
    qnWrapper.querySelectorAll(".qn-option-card").forEach((card) => {
      card.addEventListener("click", () => {
        const stepEl = card.closest(".qn-step");
        const val = card.getAttribute("data-value");

        clearStepError(stepEl);

        stepEl.querySelectorAll(".qn-option-card").forEach((c) => c.classList.remove("selected"));
        card.classList.add("selected");

        qnState.service = val;
        if (inputService) inputService.value = val;

        setTimeout(() => {
          if (currentStep < totalSteps) {
            goToStep(currentStep + 1);
          }
        }, 220);
      });
    });

    // Next Buttons
    qnWrapper.querySelectorAll(".qn-next-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        const nextNum = parseInt(btn.getAttribute("data-next"), 10);
        if (!validateStep(currentStep)) return;
        goToStep(nextNum);
      });
    });

    // Back Button
    if (backBtn) {
      backBtn.addEventListener("click", () => {
        if (currentStep > 1) {
          goToStep(currentStep - 1);
        }
      });
    }

    // Form Submission
    if (form) {
      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        if (feedbackMsg) {
          feedbackMsg.style.display = "none";
          feedbackMsg.className = "qn-feedback-msg";
        }

        if (!validateStep(1)) { goToStep(1); return; }
        if (!validateStep(2)) { goToStep(2); return; }

        const nameVal = document.getElementById("qnNameInput")?.value.trim() || "";
        const phoneVal = document.getElementById("qnPhoneInput")?.value.trim() || "";
        const emailVal = document.getElementById("qnEmailInput")?.value.trim() || "";

        if (!nameVal || nameVal.length < 2) {
          goToStep(1);
          return;
        }

        if (!phoneVal && !emailVal) {
          if (feedbackMsg) {
            feedbackMsg.textContent = "Please provide your phone/WhatsApp number or email address.";
            feedbackMsg.classList.add("error");
            feedbackMsg.style.display = "block";
          }
          document.getElementById("qnPhoneInput")?.focus();
          return;
        }

        if (inputService) inputService.value = qnState.service;
        if (inputCompany) inputCompany.value = businessInput ? businessInput.value.trim() : "";

        const submitBtn = document.getElementById("qnSubmitBtn");
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = "<span>SENDING... ↗</span>";
        }

        const formData = new FormData(form);

        try {
          const response = await fetch("api/contact.php", {
            method: "POST",
            headers: {
              "X-Requested-With": "XMLHttpRequest"
            },
            body: formData
          });

          const data = await response.json();

          if (data.success) {
            if (form) form.style.display = "none";
            if (successScreen) successScreen.style.display = "block";
          } else {
            if (feedbackMsg) {
              feedbackMsg.textContent = data.error || "We couldn't send your enquiry. Please try again.";
              feedbackMsg.classList.add("error");
              feedbackMsg.style.display = "block";
            }
          }
        } catch (err) {
          if (feedbackMsg) {
            feedbackMsg.textContent = "We couldn't send your enquiry. Please check your connection and try again.";
            feedbackMsg.classList.add("error");
            feedbackMsg.style.display = "block";
          }
        } finally {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = "<span>GET YOUR FREE QUOTE ↗</span>";
          }
        }
      });
    }

    // Reset Button
    if (resetBtn) {
      resetBtn.addEventListener("click", () => {
        if (form) {
          form.reset();
          form.style.display = "block";
        }
        qnState.service = "";
        qnState.company = "";
        qnState.name = "";
        qnState.phone = "";
        qnState.email = "";
        qnState.projectDetails = "";

        if (inputService) inputService.value = "";
        if (inputCompany) inputCompany.value = "";
        if (businessInput) businessInput.value = "";

        qnWrapper.querySelectorAll(".qn-option-card").forEach((card) => card.classList.remove("selected"));
        qnWrapper.querySelectorAll(".qn-step").forEach((stepEl) => clearStepError(stepEl));

        if (feedbackMsg) {
          feedbackMsg.style.display = "none";
          feedbackMsg.textContent = "";
          feedbackMsg.className = "qn-feedback-msg";
        }

        if (successScreen) successScreen.style.display = "none";
        goToStep(1);

        if (modal && modal.classList.contains("active")) {
          closeQuestionnaireModal();
        }
      });
    }
  }

  /* =========================================================
     FAQ ACCORDION CONTROLLER (PHASE 9)
  ========================================================= */
  const faqQuestions = document.querySelectorAll(".faq-question");
  faqQuestions.forEach((btn) => {
    btn.addEventListener("click", () => {
      const expanded = btn.getAttribute("aria-expanded") === "true";
      const targetId = btn.getAttribute("aria-controls");
      const targetAns = document.getElementById(targetId);

      faqQuestions.forEach((otherBtn) => {
        if (otherBtn !== btn) {
          otherBtn.setAttribute("aria-expanded", "false");
          const otherId = otherBtn.getAttribute("aria-controls");
          const otherAns = document.getElementById(otherId);
          if (otherAns) otherAns.hidden = true;
        }
      });

      btn.setAttribute("aria-expanded", expanded ? "false" : "true");
      if (targetAns) {
        targetAns.hidden = expanded;
      }
    });
  });

  /* =========================================================
     AUDIENCE SECTION ("BUILT FOR") INTERACTION COORDINATION
  ========================================================= */
  const audItems = document.querySelectorAll(".aud-item");
  const audNodes = document.querySelectorAll(".aud-node");

  if (audItems.length && audNodes.length) {
    audItems.forEach((item) => {
      item.addEventListener("mouseenter", () => {
        const key = item.getAttribute("data-aud");
        audNodes.forEach((node) => {
          const matchKeys = (node.getAttribute("data-node") || "").split(" ");
          if (matchKeys.includes(key)) {
            node.classList.add("is-active");
          } else {
            node.classList.remove("is-active");
          }
        });
      });

      item.addEventListener("mouseleave", () => {
        audNodes.forEach((node) => node.classList.remove("is-active"));
      });
    });

    audNodes.forEach((node) => {
      node.addEventListener("mouseenter", () => {
        const matchKeys = (node.getAttribute("data-node") || "").split(" ");
        audItems.forEach((item) => {
          const key = item.getAttribute("data-aud");
          if (matchKeys.includes(key)) {
            item.classList.add("is-active");
          } else {
            item.classList.remove("is-active");
          }
        });
      });

      node.addEventListener("mouseleave", () => {
        audItems.forEach((item) => item.classList.remove("is-active"));
      });
    });
  }

  /* =========================================================
     INTERACTIVE BEFORE/AFTER REDESIGN SLIDER & TILT ENGINE
  ========================================================= */
  const baContainer = document.getElementById("baSliderContainer");
  const baAfterView = document.getElementById("baAfterView");
  const baHandle = document.getElementById("baHandle");
  const heroVisualStage = document.getElementById("heroVisualStage");
  const scrollCue = document.getElementById("scrollCue");

  if (baContainer && baAfterView && baHandle) {
    let isDraggingBA = false;
    let currentPosPercentage = 50;

    function updateBASliderPosition(percentage) {
      currentPosPercentage = Math.max(0, Math.min(100, percentage));
      baContainer.style.setProperty("--ba-pos", `${currentPosPercentage}%`);
      baHandle.setAttribute("aria-valuenow", Math.round(currentPosPercentage));
    }

    function calculatePosFromEvent(e) {
      const rect = baContainer.getBoundingClientRect();
      const clientX = e.touches ? e.touches[0].clientX : e.clientX;
      const x = clientX - rect.left;
      return (x / rect.width) * 100;
    }

    // Mouse Events
    baHandle.addEventListener("mousedown", (e) => {
      isDraggingBA = true;
      document.body.style.userSelect = "none";
      e.preventDefault();
    });

    baContainer.addEventListener("mousedown", (e) => {
      if (e.target !== baHandle && !baHandle.contains(e.target)) {
        updateBASliderPosition(calculatePosFromEvent(e));
      }
    });

    window.addEventListener("mousemove", (e) => {
      if (!isDraggingBA) return;
      updateBASliderPosition(calculatePosFromEvent(e));
    });

    window.addEventListener("mouseup", () => {
      if (isDraggingBA) {
        isDraggingBA = false;
        document.body.style.userSelect = "";
      }
    });

    // Touch Events
    baHandle.addEventListener("touchstart", () => {
      isDraggingBA = true;
    }, { passive: true });

    window.addEventListener("touchmove", (e) => {
      if (!isDraggingBA) return;
      updateBASliderPosition(calculatePosFromEvent(e));
    }, { passive: true });

    window.addEventListener("touchend", () => {
      isDraggingBA = false;
    });

    // Keyboard Accessibility
    baHandle.addEventListener("keydown", (e) => {
      if (e.key === "ArrowLeft") {
        updateBASliderPosition(currentPosPercentage - 5);
        e.preventDefault();
      } else if (e.key === "ArrowRight") {
        updateBASliderPosition(currentPosPercentage + 5);
        e.preventDefault();
      } else if (e.key === "Home") {
        updateBASliderPosition(0);
        e.preventDefault();
      } else if (e.key === "End") {
        updateBASliderPosition(100);
        e.preventDefault();
      }
    });

    // One-time auto-sweep animation on load (sweeps left-to-right once to draw the eye)
    if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      setTimeout(() => {
        let startTime = null;
        const duration = 1400;

        function animateSweep(timestamp) {
          if (!startTime) startTime = timestamp;
          const elapsed = timestamp - startTime;
          const progress = Math.min(1, elapsed / duration);

          // Ease out sine wave sweep: 50% -> 82% -> 25% -> 50%
          const sweepVal = 50 + Math.sin(progress * Math.PI * 2) * 32 * (1 - progress);
          updateBASliderPosition(sweepVal);

          if (progress < 1 && !isDraggingBA) {
            requestAnimationFrame(animateSweep);
          } else if (!isDraggingBA) {
            updateBASliderPosition(50);
          }
        }

        requestAnimationFrame(animateSweep);
      }, 1000);
    }
  }

  /* 3D Tilt on Browser Frame (Disabled on touch devices, max 6deg) */
  if (heroVisualStage && isDesktop && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    heroVisualStage.addEventListener("mousemove", (e) => {
      const rect = heroVisualStage.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      const centerX = rect.width / 2;
      const centerY = rect.height / 2;

      const tiltX = ((y - centerY) / centerY) * -6;
      const tiltY = ((x - centerX) / centerX) * 6;

      heroVisualStage.style.transform = `perspective(1000px) rotateX(${tiltX.toFixed(2)}deg) rotateY(${tiltY.toFixed(2)}deg)`;
    });

    heroVisualStage.addEventListener("mouseleave", () => {
      heroVisualStage.style.transform = "perspective(1000px) rotateX(0deg) rotateY(0deg)";
      heroVisualStage.style.transition = "transform 0.5s ease-out";
    });

    heroVisualStage.addEventListener("mouseenter", () => {
      heroVisualStage.style.transition = "none";
    });
  }

  /* Scroll Cue Fade Out */
  if (scrollCue) {
    window.addEventListener("scroll", () => {
      if (window.scrollY > 40) {
        scrollCue.style.opacity = "0";
      } else {
        scrollCue.style.opacity = "0.8";
      }
    }, { passive: true });
  }
});
