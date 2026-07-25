/* ============================================
   Sabeel Us Salaam Online — Interactions
   ============================================ */

(function () {
  'use strict';

  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  /* ---------- Page Loader ---------- */
  function initLoader() {
    const loader = $('#pageLoader');
    if (!loader) return;

    let done = false;
    const hide = () => {
      if (done) return;
      done = true;
      loader.classList.add('is-done');
      loader.setAttribute('aria-hidden', 'true');
    };

    // Prefer full load, but never block the UI if images hang
    if (document.readyState === 'complete') {
      setTimeout(hide, 350);
    } else {
      window.addEventListener('load', () => setTimeout(hide, 350));
      setTimeout(hide, 2200);
    }
  }

  /* ---------- Scroll Progress ---------- */
  function initScrollProgress() {
    const bar = $('#scrollProgress');
    if (!bar) return;

    const update = () => {
      const scrollTop = window.scrollY || document.documentElement.scrollTop;
      const height = document.documentElement.scrollHeight - window.innerHeight;
      const pct = height > 0 ? (scrollTop / height) * 100 : 0;
      bar.style.width = pct + '%';
    };

    window.addEventListener('scroll', update, { passive: true });
    update();
  }

  /* ---------- Sticky Header ---------- */
  function initHeader() {
    const header = $('#siteHeader');
    if (!header) return;

    const onScroll = () => {
      header.classList.toggle('is-scrolled', window.scrollY > 20);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Mobile Menu ---------- */
  function initMobileMenu() {
    const toggle = $('#menuToggle');
    const nav = $('#mainNav');
    const backdrop = $('#navBackdrop');
    if (!toggle || !nav) return;

    const closeSubs = () => {
      $$('.has-sub.is-open', nav).forEach((item) => {
        item.classList.remove('is-open');
        const btn = item.querySelector('.nav-parent');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      });
    };

    const close = () => {
      toggle.classList.remove('is-open');
      nav.classList.remove('is-open');
      backdrop?.classList.remove('is-open');
      if (backdrop) backdrop.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Open menu');
      document.body.classList.remove('nav-open');
      closeSubs();
    };

    const open = () => {
      toggle.classList.add('is-open');
      nav.classList.add('is-open');
      if (backdrop) {
        backdrop.hidden = false;
        backdrop.classList.add('is-open');
      }
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Close menu');
      document.body.classList.add('nav-open');
    };

    toggle.addEventListener('click', () => {
      if (nav.classList.contains('is-open')) close();
      else open();
    });

    backdrop?.addEventListener('click', close);

    // Submenu toggles (About / Student Services)
    $$('.has-sub > .nav-parent', nav).forEach((btn) => {
      btn.addEventListener('click', (e) => {
        const item = btn.closest('.has-sub');
        // Mobile / tablet: toggle submenu instead of navigating away
        if (window.innerWidth <= 960) {
          e.preventDefault();
          e.stopPropagation();
          const willOpen = !item.classList.contains('is-open');
          $$('.has-sub.is-open', nav).forEach((other) => {
            if (other !== item) {
              other.classList.remove('is-open');
              const ob = other.querySelector('.nav-parent');
              if (ob) ob.setAttribute('aria-expanded', 'false');
            }
          });
          item.classList.toggle('is-open', willOpen);
          btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
          return;
        }
        // Desktop: keep open briefly so submenu stays clickable if needed
        item.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
      });
    });

    // Close mobile menu for destination links (not parents)
    $$('a.nav-link:not(.nav-parent)', nav).forEach((link) => {
      link.addEventListener('click', () => {
        // allow navigation; close drawer on mobile
        if (window.innerWidth <= 960) close();
      });
    });

    // Keep submenu open while pointer is over parent or panel
    $$('.has-sub', nav).forEach((item) => {
      item.addEventListener('mouseenter', () => {
        if (window.innerWidth <= 960) return;
        item.classList.add('is-open');
        const btn = item.querySelector('.nav-parent');
        if (btn) btn.setAttribute('aria-expanded', 'true');
      });
      item.addEventListener('mouseleave', () => {
        if (window.innerWidth <= 960) return;
        item.classList.remove('is-open');
        const btn = item.querySelector('.nav-parent');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      });
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') close();
    });

    document.addEventListener('click', (e) => {
      if (window.innerWidth > 960 && !nav.contains(e.target)) closeSubs();
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 960) close();
    });
  }

  /* ---------- Active Nav Link ---------- */
  function initActiveNav() {
    const links = $$('.nav-link');
    const sections = links
      .map((link) => {
        const id = link.getAttribute('href');
        return id && id.startsWith('#') ? $(id) : null;
      })
      .filter(Boolean);

    if (!sections.length) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const id = '#' + entry.target.id;
          links.forEach((link) => {
            link.classList.toggle('active', link.getAttribute('href') === id);
          });
        });
      },
      { rootMargin: '-40% 0px -50% 0px', threshold: 0 }
    );

    sections.forEach((sec) => observer.observe(sec));
  }

  /* ---------- Reveal on Scroll ---------- */
  let revealObserver = null;

  function observeReveal(root = document) {
    const scope = root && root.querySelectorAll ? root : document;
    const els = scope === document
      ? $$('.reveal:not(.is-visible)')
      : Array.from(scope.querySelectorAll('.reveal:not(.is-visible)'));
    if (!els.length) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      els.forEach((el) => el.classList.add('is-visible'));
      return;
    }

    if (!revealObserver) {
      revealObserver = new IntersectionObserver(
        (entries, obs) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            obs.unobserve(entry.target);
          });
        },
        { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
      );
    }

    els.forEach((el) => revealObserver.observe(el));
  }

  function initReveal() {
    observeReveal(document);
  }

  window.SabeelUI = Object.assign(window.SabeelUI || {}, { observeReveal });

  /* ---------- Counter Animation ---------- */
  function initCounters() {
    const nums = $$('.stat-num[data-target]');
    if (!nums.length) return;

    const animate = (el) => {
      const target = parseInt(el.dataset.target, 10) || 0;
      const suffix = el.dataset.suffix || '';
      const duration = 1600;
      const start = performance.now();

      const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(target * eased) + suffix;
        if (progress < 1) requestAnimationFrame(tick);
        else el.textContent = target + suffix;
      };

      requestAnimationFrame(tick);
    };

    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          animate(entry.target);
          obs.unobserve(entry.target);
        });
      },
      { threshold: 0.5 }
    );

    nums.forEach((n) => observer.observe(n));
  }

  /* ---------- Contact Form → Google Sheets (Apps Script Web App) ---------- */
  /**
   * CONFIG — paste values here after you deploy:
   * 1) Spreadsheet ID  → google-apps-script/Code.gs → SPREADSHEET_ID
   * 2) Web App URL     → FORM_CONFIG.GOOGLE_SCRIPT_URL below
   *
   * Spreadsheet ID example from:
   * https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit
   *
   * Web App URL example:
   * https://script.google.com/macros/s/XXXX/exec
   */
  const FORM_CONFIG = {
    GOOGLE_SCRIPT_URL: 'https://script.google.com/macros/s/AKfycbyfMzUUQRTYSotZ38Wcopwl6NXS2X0LJ9TOGg3tSRidvFnb6PInNGPFi9xCyxrfa9Vf/exec',
  };

  let ALLOWED_COURSES = [
    'Personal Tutoring',
    'Basic Urdu Course',
    'Short Term Alimiyyat',
    'Advanced Arabic Diploma',
    'Translation of The Quran',
    'Elementary Course In Islamic Education',
  ];

  window.SabeelForm = Object.assign(window.SabeelForm || {}, {
    setAllowedCourses(names) {
      if (!Array.isArray(names)) return;
      ALLOWED_COURSES = names.map((n) => String(n || '').trim()).filter(Boolean);
    },
  });

  const FORM_RULES = {
    name: {
      min: 2,
      max: 80,
      pattern: /^[A-Za-z\u0600-\u06FF][A-Za-z\u0600-\u06FF\s.'-]{1,79}$/,
      message: 'Enter a valid name (letters only, 2–80 characters).',
    },
    country: {
      min: 2,
      max: 56,
      pattern: /^[A-Za-z\u0600-\u06FF][A-Za-z\u0600-\u06FF\s.-]{1,55}$/,
      message: 'Enter a valid country name.',
    },
    phone: {
      min: 8,
      max: 20,
      pattern: /^\+?[0-9][0-9\s\-()]{7,18}$/,
      digits: { min: 8, max: 15 },
      message: 'Enter a valid phone number (8–15 digits).',
    },
    email: {
      max: 100,
      pattern: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
      message: 'Enter a valid email address.',
    },
    course: {
      message: 'Please select a valid course from the list.',
    },
    message: {
      min: 10,
      max: 1000,
      message: 'Message must be 10–1000 characters.',
    },
  };

  function cleanText(value) {
    return String(value || '')
      .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function validateField(id, rawValue) {
    const value = cleanText(rawValue);
    const rule = FORM_RULES[id];
    if (!rule) return { ok: false, value, message: 'Invalid field.' };

    if (!value) {
      return { ok: false, value, message: 'This field is required.' };
    }

    if (id === 'course') {
      return ALLOWED_COURSES.includes(value)
        ? { ok: true, value }
        : { ok: false, value, message: rule.message };
    }

    if (rule.min && value.length < rule.min) {
      return { ok: false, value, message: rule.message };
    }

    if (rule.max && value.length > rule.max) {
      return { ok: false, value, message: rule.message };
    }

    if (rule.pattern && !rule.pattern.test(value)) {
      return { ok: false, value, message: rule.message };
    }

    if (id === 'phone' && rule.digits) {
      const digits = value.replace(/\D/g, '');
      if (digits.length < rule.digits.min || digits.length > rule.digits.max) {
        return { ok: false, value, message: rule.message };
      }
    }

    if (id === 'email') {
      return { ok: true, value: value.toLowerCase() };
    }

    return { ok: true, value };
  }

  function showFormPopup(type, title, message) {
    const popup = $('#formPopup');
    if (!popup) {
      console.error('[ContactForm] Popup element #formPopup not found.');
      return;
    }

    const titleEl = $('#formPopupTitle', popup);
    const msgEl = $('#formPopupMessage', popup);
    const iconEl = $('#formPopupIcon', popup);

    popup.dataset.type = type;
    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = message;
    if (iconEl) iconEl.textContent = type === 'success' ? '✓' : '!';

    popup.hidden = false;
    popup.setAttribute('aria-hidden', 'false');
    document.body.classList.add('form-popup-open');

    const closeBtn = $('#formPopupClose', popup);
    if (closeBtn) closeBtn.focus();
  }

  function hideFormPopup() {
    const popup = $('#formPopup');
    if (!popup) return;
    popup.hidden = true;
    popup.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('form-popup-open');
  }

  function initFormPopup() {
    const popup = $('#formPopup');
    if (!popup) return;

    const close = () => hideFormPopup();
    $('#formPopupClose', popup)?.addEventListener('click', close);
    $('#formPopupOk', popup)?.addEventListener('click', close);
    $('.form-popup-backdrop', popup)?.addEventListener('click', close);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !popup.hidden) close();
    });
  }

  function initForm() {
    const form = $('#contactForm');
    const status = $('#formStatus');
    if (!form) return;

    const fields = ['name', 'country', 'phone', 'email', 'course', 'message'];
    const scriptUrl = String(FORM_CONFIG.GOOGLE_SCRIPT_URL || '').trim();

    if (!scriptUrl) {
      console.warn(
        '[ContactForm] GOOGLE_SCRIPT_URL is missing.\n' +
          '1) Create a Google Sheet and copy its Spreadsheet ID into google-apps-script/Code.gs → SPREADSHEET_ID\n' +
          '2) Deploy Code.gs as a Web App (Execute as: Me, Access: Anyone)\n' +
          '3) Paste the Web App URL into script.js → FORM_CONFIG.GOOGLE_SCRIPT_URL'
      );
    }

    const setStatus = (message, type) => {
      if (!status) return;
      status.textContent = message;
      status.className = 'form-status' + (type ? ' ' + type : '');
    };

    const markField = (el, ok) => {
      if (!el) return;
      el.classList.toggle('is-invalid', !ok);
      el.setAttribute('aria-invalid', ok ? 'false' : 'true');
    };

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const cleaned = {};
      let valid = true;
      let firstError = '';

      fields.forEach((id) => {
        const el = $('#' + id);
        if (!el) return;
        const result = validateField(id, el.value);
        markField(el, result.ok);
        if (result.ok) {
          cleaned[id] = result.value;
          el.value = result.value;
        } else {
          valid = false;
          if (!firstError) firstError = result.message;
        }
      });

      if (!valid) {
        setStatus(firstError || 'Please fill in all required fields correctly.', 'error');
        showFormPopup('error', 'Invalid details', firstError || 'Please fill in all required fields correctly.');
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) firstInvalid.focus();
        return;
      }

      if (!scriptUrl) {
        console.error(
          '[ContactForm] Submission blocked: FORM_CONFIG.GOOGLE_SCRIPT_URL is empty. ' +
            'Deploy google-apps-script/Code.gs and paste the Web App URL.'
        );
        showFormPopup(
          'error',
          'Form unavailable',
          'Unable to submit right now. Please try again later or WhatsApp +91-8979983149.'
        );
        return;
      }

      // Send both PascalCase and lowercase keys (Apps Script versions differ)
      const payload = {
        Timestamp: new Date().toISOString(),
        Name: cleaned.name,
        Country: cleaned.country,
        Phone: cleaned.phone,
        Email: cleaned.email,
        Course: cleaned.course,
        Message: cleaned.message,
        Status: 'New Lead',
        timestamp: new Date().toISOString(),
        name: cleaned.name,
        country: cleaned.country,
        phone: cleaned.phone,
        email: cleaned.email,
        course: cleaned.course,
        message: cleaned.message,
        status: 'New Lead',
      };

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn ? submitBtn.textContent : 'Send Message';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('is-loading');
        submitBtn.textContent = 'Sending…';
      }
      setStatus('Submitting your inquiry…', '');
      form.classList.add('is-submitting');

      console.info('[ContactForm] Submitting payload:', payload);

      try {
        // text/plain avoids CORS preflight issues with Google Apps Script
        const res = await fetch(scriptUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'text/plain;charset=utf-8' },
          body: JSON.stringify(payload),
          redirect: 'follow',
        });

        const raw = await res.text();
        console.info('[ContactForm] HTTP status:', res.status, 'raw response:', raw);

        // Restricted deployments return Google HTML ("You need access") instead of JSON.
        if (/you need (access|permission)/i.test(raw) || /accounts\.google\.com/i.test(raw)) {
          throw new Error(
            'Apps Script Web App is not public. Redeploy with Access: Anyone (not Only myself).'
          );
        }

        let result = null;
        try {
          result = raw ? JSON.parse(raw) : null;
        } catch (parseErr) {
          console.error('[ContactForm] Non-JSON response from Apps Script:', raw);
          throw new Error('Unexpected response from spreadsheet service.');
        }

        console.info('[ContactForm] Apps Script response:', result);

        // Accept both { ok: true } and { success: true } response shapes
        const isSuccess =
          !!result &&
          (result.ok === true || result.success === true) &&
          result.ok !== false &&
          result.success !== false;

        if (!res.ok || !isSuccess) {
          throw new Error(
            (result && (result.error || result.message)) ||
              'Request failed with status ' + res.status
          );
        }

        setStatus('Thank you! Your inquiry has been saved.', 'success');
        form.reset();
        fields.forEach((id) => markField($('#' + id), true));
        showFormPopup(
          'success',
          'Message sent',
          'JazakAllah Khair. Your inquiry was saved successfully. Our team will contact you soon.'
        );
      } catch (err) {
        console.error('[ContactForm] Submission failed:', err);
        setStatus('Submission failed. Please try again.', 'error');
        showFormPopup(
          'error',
          'Submission failed',
          'We could not save your inquiry. Please try again or WhatsApp +91-8979983149.'
        );
      } finally {
        form.classList.remove('is-submitting');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('is-loading');
          submitBtn.textContent = originalBtnText;
        }
      }
    });

    $$('input, select, textarea', form).forEach((el) => {
      el.addEventListener('input', () => {
        markField(el, true);
        if (status && status.classList.contains('error')) setStatus('', '');
      });
      el.addEventListener('blur', () => {
        const result = validateField(el.id, el.value);
        if (el.value.trim()) markField(el, result.ok);
      });
    });
  }

  /* ---------- Back to Top ---------- */
  function initBackTop() {
    const btn = $('#backTop');
    if (!btn) return;

    window.addEventListener(
      'scroll',
      () => {
        btn.classList.toggle('is-visible', window.scrollY > 500);
      },
      { passive: true }
    );

    btn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /* ---------- Footer Year ---------- */
  function initYear() {
    const year = $('#year');
    if (year) year.textContent = String(new Date().getFullYear());
  }

  /* ---------- Clean /index.html out of the address bar ---------- */
  function initCleanUrl() {
    if (!/\/index\.html$/i.test(location.pathname)) return;
    const cleanPath = location.pathname.replace(/\/index\.html$/i, '/') || '/';
    const next = cleanPath + location.search + location.hash;
    history.replaceState(null, '', next);
  }

  /* ---------- Section hash scrolling ---------- */
  function isHomePath(pathname) {
    return pathname === '/' || pathname === '' || /\/index\.html$/i.test(pathname);
  }

  function hashFromHref(href) {
    if (!href || href === '#') return '';
    if (href.startsWith('#')) return href;
    try {
      const url = new URL(href, location.href);
      return url.hash || '';
    } catch (_) {
      return '';
    }
  }

  function scrollToHash(hash, behavior) {
    if (!hash || hash === '#') return false;
    const target = document.getElementById(decodeURIComponent(hash.slice(1)));
    if (!target) return false;
    target.scrollIntoView({ behavior: behavior || 'smooth', block: 'start' });
    return true;
  }

  /**
   * Re-apply hash scroll after late layout shifts (course cards, images).
   * Without this, /#contact often lands on Latest Blog ("Insights from Our Teachers").
   */
  function applyHashScroll(behavior) {
    if (!location.hash) return;
    scrollToHash(location.hash, behavior || 'auto');
  }

  function initHashScroll() {
    applyHashScroll('auto');

    const retry = () => applyHashScroll('auto');
    window.addEventListener('load', () => {
      retry();
      setTimeout(retry, 200);
      setTimeout(retry, 700);
      setTimeout(retry, 1400);
    });

    const grid = document.getElementById('coursesGrid');
    if (grid && 'MutationObserver' in window) {
      const mo = new MutationObserver(() => {
        if (location.hash) retry();
      });
      mo.observe(grid, { childList: true, subtree: true });
      setTimeout(() => mo.disconnect(), 8000);
    }

    window.addEventListener('hashchange', () => applyHashScroll('smooth'));
  }

  function initSmoothAnchors() {
    document.addEventListener('click', (e) => {
      const link = e.target.closest('a[href]');
      if (!link || link.target === '_blank' || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return;
      }

      const href = link.getAttribute('href');
      const hash = hashFromHref(href);
      if (!hash) return;

      let url;
      try {
        url = new URL(href, location.href);
      } catch (_) {
        return;
      }

      /* Another page (e.g. Blog → /#contact): let the browser navigate; initHashScroll fixes landing. */
      if (!isHomePath(url.pathname) || !isHomePath(location.pathname)) {
        return;
      }

      const target = document.getElementById(decodeURIComponent(hash.slice(1)));
      if (!target) return;

      e.preventDefault();
      scrollToHash(hash, 'smooth');
      history.pushState(null, '', hash === '#home' ? '/' : hash);

      /* Courses/images may still expand — nudge again shortly after */
      setTimeout(() => scrollToHash(hash, 'auto'), 350);
      setTimeout(() => scrollToHash(hash, 'auto'), 900);
    });
  }

  /* ---------- Init ---------- */
  document.addEventListener('DOMContentLoaded', () => {
    initCleanUrl();
    initLoader();
    initScrollProgress();
    initHeader();
    initMobileMenu();
    initActiveNav();
    initReveal();
    initCounters();
    initFormPopup();
    initForm();
    initBackTop();
    initYear();
    initSmoothAnchors();
    initHashScroll();
  });
})();
