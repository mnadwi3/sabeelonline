/**
 * Admin Hub — one Admin Login only (no access codes).
 */
(function () {
  'use strict';

  const gateView = document.getElementById('viewHubGate');
  const appView = document.getElementById('viewHubApp');
  const btnLogout = document.getElementById('btnHubLogout');

  function setAuthed(on) {
    if (gateView) gateView.hidden = on;
    if (appView) appView.hidden = !on;
  }

  btnLogout?.addEventListener('click', () => {
    if (window.SabeelAdminGate) {
      window.SabeelAdminGate.logoutAdmin();
    } else {
      window.location.href = '/pages/logout.php';
    }
  });

  document.addEventListener('DOMContentLoaded', async () => {
    if (window.SabeelSiteNav) window.SabeelSiteNav.mount();
    setAuthed(false);
    const session = await window.SabeelAdminGate.requireAdminOrRedirect('/admin-hub.html');
    if (session) setAuthed(true);
  });
})();
