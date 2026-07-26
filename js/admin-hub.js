/**
 * Admin Hub — gated by unified SABEELAUTH (Library / Courses modules).
 */
(function () {
  'use strict';

  const SESSION_API = 'library/api/session.php';
  const LOGIN_URL = '/pages/login.php?redirect=' + encodeURIComponent('/admin-hub.html');
  const HUB_KEY = 'sabeel_admin_hub';

  const gateView = document.getElementById('viewHubGate');
  const appView = document.getElementById('viewHubApp');
  const gateForm = document.getElementById('hubGateForm');
  const gateError = document.getElementById('hubGateError');
  const btnLogout = document.getElementById('btnHubLogout');
  const staffLink = document.getElementById('hubStaffLogin');

  function setAuthed(on) {
    if (gateView) gateView.hidden = on;
    if (appView) appView.hidden = !on;
  }

  async function checkSession() {
    try {
      const res = await fetch(SESSION_API + '?t=' + Date.now(), {
        credentials: 'same-origin',
        cache: 'no-store',
      });
      const data = await res.json().catch(() => null);
      if (data && data.ok && data.authenticated && (data.is_admin || data.can_library || data.can_courses || data.role === 'super_admin' || data.role === 'admin')) {
        localStorage.setItem(HUB_KEY, '1');
        return true;
      }
    } catch (_) { /* ignore */ }
    return false;
  }

  gateForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    // Prefer unified login — keep code field as emergency fallback
    const code = (document.getElementById('adminCode')?.value || '').trim();
    if (!code) {
      window.location.href = LOGIN_URL;
      return;
    }
    try {
      const body = new FormData();
      body.append('admin_code', code);
      const res = await fetch('library/api/login.php', { method: 'POST', body, credentials: 'same-origin' });
      const data = await res.json().catch(() => null);
      if (res.ok && data && data.ok) {
        if (gateError) gateError.hidden = true;
        localStorage.setItem(HUB_KEY, '1');
        setAuthed(true);
        return;
      }
    } catch (_) { /* ignore */ }
    if (gateError) {
      gateError.hidden = false;
      gateError.textContent = 'Sign in with your staff account, or use a valid emergency admin code.';
    }
  });

  btnLogout?.addEventListener('click', async () => {
    localStorage.removeItem(HUB_KEY);
    try {
      await fetch('library/api/logout.php', { method: 'POST', credentials: 'same-origin' });
    } catch (_) { /* ignore */ }
    window.location.href = '/pages/logout.php';
  });

  staffLink?.addEventListener('click', (e) => {
    e.preventDefault();
    window.location.href = LOGIN_URL;
  });

  document.addEventListener('DOMContentLoaded', async () => {
    if (window.SabeelSiteNav) window.SabeelSiteNav.mount();
    if (await checkSession()) {
      setAuthed(true);
    } else {
      setAuthed(false);
    }
  });
})();
