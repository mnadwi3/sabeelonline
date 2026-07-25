/**
 * Admin Hub — gate + links to every Sabeel admin panel.
 */
(function () {
  'use strict';

  const cfg = window.LIBRARY_CONFIG || {};
  const ADMIN_CODES = (cfg.ADMIN_CODES || [cfg.ADMIN_CODE || 'admin@sabeel']).map((c) => String(c).toUpperCase());
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

  gateForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = document.getElementById('adminCode').value.trim();
    if (!isAdminCode(code)) {
      if (gateError) gateError.hidden = false;
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
  });

  document.addEventListener('DOMContentLoaded', async () => {
    if (window.SabeelSiteNav) window.SabeelSiteNav.mount();
    if (localStorage.getItem(HUB_KEY) === '1' && adminCode && isAdminCode(adminCode)) {
      await apiLogin(adminCode);
      setAuthed(true);
    } else {
      setAuthed(false);
    }
  });
})();
