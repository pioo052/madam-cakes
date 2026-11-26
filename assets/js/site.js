/* ==========================================================================
   MADAM CAKES — storefront behaviour
   Progressive enhancement only: without JS every section stays visible.
   ========================================================================== */

(function () {
  'use strict';

  var root = document.documentElement;
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* ------------------------------------------------------------------ header */
  var header = document.getElementById('siteHeader');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ------------------------------------------------------------------ drawer */
  var drawer = document.getElementById('mobileDrawer');
  var scrim = document.getElementById('navScrim');
  var toggle = document.getElementById('navToggle');
  var closeBtn = document.getElementById('drawerClose');

  function drawerOpen() {
    if (!drawer) return;
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    if (scrim) {
      scrim.hidden = false;
      requestAnimationFrame(function () { scrim.classList.add('is-open'); });
    }
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    document.body.classList.add('drawer-open');
    var first = drawer.querySelector('a, button');
    if (first) first.focus({ preventScroll: true });
  }

  function drawerClose(returnFocus) {
    if (!drawer) return;
    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    if (scrim) {
      scrim.classList.remove('is-open');
      window.setTimeout(function () { scrim.hidden = true; }, 320);
    }
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
      if (returnFocus) toggle.focus({ preventScroll: true });
    }
    document.body.classList.remove('drawer-open');
  }

  if (toggle) {
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') === 'true';
      if (open) drawerClose(true); else drawerOpen();
    });
  }
  if (closeBtn) closeBtn.addEventListener('click', function () { drawerClose(true); });
  if (scrim) scrim.addEventListener('click', function () { drawerClose(true); });

  if (drawer) {
    drawer.addEventListener('click', function (event) {
      if (event.target.closest('a')) drawerClose(false);
    });
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && drawer && drawer.classList.contains('is-open')) {
      drawerClose(true);
    }
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 1024 && drawer && drawer.classList.contains('is-open')) {
      drawerClose(false);
    }
  });

  /* ----------------------------------------------------------------- reveal */
  var revealItems = Array.prototype.slice.call(document.querySelectorAll('.reveal'));

  function revealAll() {
    revealItems.forEach(function (item) { item.classList.add('is-visible'); });
  }

  if (revealItems.length && !reduceMotion.matches && 'IntersectionObserver' in window) {
    try {
      var observerFired = false;
      var observer = new IntersectionObserver(function (entries) {
        observerFired = true;
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

      // The hidden state is applied only after the observer is live, and a
      // safety net reveals everything if the observer never reports at all.
      root.classList.add('js-ready');
      revealItems.forEach(function (item) { observer.observe(item); });
      window.setTimeout(function () {
        if (!observerFired) revealAll();
      }, 2500);
    } catch (error) {
      revealAll();
    }
  } else {
    revealAll();
  }

  if (reduceMotion.addEventListener) {
    reduceMotion.addEventListener('change', function (event) {
      if (event.matches) revealAll();
    });
  }

  /* -------------------------------------------- hero parallax (GSAP, optional) */
  // Additive only: content is never hidden by these animations, so a blocked CDN
  // or reduced-motion preference simply leaves the layout untouched.
  function initParallax() {
    if (!window.gsap || !window.ScrollTrigger || reduceMotion.matches) return;
    var image = document.querySelector('.hero-media .arch img');
    if (!image) return;

    gsap.registerPlugin(ScrollTrigger);
    gsap.to(image, {
      yPercent: 6,
      ease: 'none',
      scrollTrigger: {
        trigger: '.hero-card',
        start: 'top top',
        end: 'bottom top',
        scrub: true
      }
    });
  }

  if (document.readyState === 'complete') {
    initParallax();
  } else {
    window.addEventListener('load', initParallax, { once: true });
  }
})();
