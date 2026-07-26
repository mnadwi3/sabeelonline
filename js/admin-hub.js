/**
 * Admin Hub — quick access-code gate (as before) + optional Admin Login session.
 */
(function () {
  'use strict';

  const cfg = window.LIBRARY_CONFIG || {};
  const ADMIN_CODES = (cfg.ADMIN_CODES || [cfg.ADMIN_CODE || 'admin@sabeel']).map((c) =>
    String(c).toUpperCase()
  );
  const SESSION_API = 'library/api/session.php';
  const LOGIN_URL = '/pages/login.php?redirect=' + encodeURIComponent('/admin-hub.html');
  const HUB_KEY = 'sabeel_admin_hub';
  const CODE_KEY = 'sabeel_lib_admin_code';

  const gateView = document.getElementById('viewHubGate');
  const appView = document.getElementById('viewHubApp');
  const gateForm = document.getElementById('hubGateForm');
  const gateError = document.getElementById('hubGateError');
  const btnLogout = document.getElementById('btnHubLogout');

  let adminCode = sessionStorage.getItem(CODE_KEY) || '';

  function isAdminCode(code) {
    return ADMIN_CODES.includes(String(code || '').trim().toUpperCase());
  }

  function setAuthed(on) {
    if (gateView) gateView.hidden = on;
    if (appView) appView.hidden = !on;
  }

  async function apiLogin(code) {
    try {
      const body = new FormData();
      body.append('admin_code', code);
      await fetch('library/api/login.php', { method: 'POST', body, credentials: 'same-origin' });
    } catch (_) { /* optional */ }
  }

  async function checkUnifiedSession() {
    try {
      const res = await fetch(SESSION_API + '?t=' + Date.now(), {
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const data = await res.json().catch(() => null);
      if (
        data &&
        data.ok &&
        data.authenticated &&
        (data.is_admin || data.can_library || data.can_courses || data.role === 'admin' || data.role === 'super_admin')
      ) {
        localStorage.setItem(HUB_KEY, '1');
        return true;
      }
    } catch (_) { /* ignore */ }
    return false;
  }

  gateForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = (document.getElementById('adminCode')?.value || '').trim();
    if (!isAdminCode(code)) {
      if (gateError) {
        gateError.hidden = false;
        gateError.textContent = 'Invalid admin code.';
      }
      return;
    }
    if (gateError) gateError.hidden = true;
    adminCode = code;
    sessionStorage.setItem(CODE_KEY, code);
    localStorage.setItem(HUB_KEY, '1');
    await apiLogin(code);
    setAuthed(true);
  });

  btnLogout?.addEventListener('click', async () => {
    localStorage.removeItem(HUB_KEY);
    sessionStorage.removeItem(CODE_KEY);
    adminCode = '';
    try {
      await fetch('library/api/logout.php', { method: 'POST', credentials: 'same-origin' });
    } catch (_) { /* ignore */ }
    setAuthed(false);
    const input = document.getElementById('adminCode');
    if (input) input.value = '';
  });

  document.addEventListener('DOMContentLoaded', async () => {
    if (window.SabeelSiteNav) window.SabeelSiteNav.mount();

    // 1) Already signed in via Admin Login
    if (await checkUnifiedSession()) {
      setAuthed(true);
      return;
    }

    // 2) Previous hub access-code session
    if (localStorage.getItem(HUB_KEY) === '1' && adminCode && isAdminCode(adminCode)) {
      await apiLogin(adminCode);
      setAuthed(true);
      return;
    }

    setAuthed(false);
  });
})();
