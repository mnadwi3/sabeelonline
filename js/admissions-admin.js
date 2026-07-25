/**
 * Admin viewer for Enroll Now admission applications.
 */
(function () {
  'use strict';

  const cfg = window.LIBRARY_CONFIG || {};
  const API_URL = 'api/admissions.php';
  const ADMIN_CODES = (cfg.ADMIN_CODES || [cfg.ADMIN_CODE || 'admin@sabeel']).map((c) => String(c).toUpperCase());
  const ADMIN_KEY = 'sabeel_admissions_admin';
  const CODE_KEY = 'sabeel_lib_admin_code';

  const gateView = document.getElementById('viewAdminGate');
  const appView = document.getElementById('viewAdminApp');
  const gateForm = document.getElementById('adminGateForm');
  const gateError = document.getElementById('adminGateError');
  const statusEl = document.getElementById('appStatus');
  const listEl = document.getElementById('appsTableWrap');
  const btnLogout = document.getElementById('btnAdminLogout');
  const btnRefresh = document.getElementById('btnRefresh');

  let apps = [];
  let adminCode = sessionStorage.getItem(CODE_KEY) || '';

  function showStatus(msg, ok) {
    if (!statusEl) return;
    statusEl.hidden = !msg;
    statusEl.textContent = msg || '';
    statusEl.className = 'form-status' + (ok ? ' is-ok' : ' is-error');
  }

  function isAdminCode(code) {
    return ADMIN_CODES.includes(String(code || '').trim().toUpperCase());
  }

  function setAuthed(on) {
    if (gateView) gateView.hidden = on;
    if (appView) appView.hidden = !on;
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return escapeHtml(iso);
    return d.toLocaleString();
  }

  async function apiLogin(code) {
    try {
      const body = new FormData();
      body.append('admin_code', code);
      await fetch('library/api/login.php', { method: 'POST', body, credentials: 'same-origin' });
    } catch (_) { /* optional */ }
  }

  async function apiGet(url) {
    const res = await fetch(url, {
      cache: 'no-store',
      credentials: 'same-origin',
      headers: { 'X-Admin-Code': adminCode },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.error || 'Request failed.');
    return data;
  }

  async function apiPost(fields) {
    const body = new FormData();
    body.append('admin_code', adminCode);
    Object.entries(fields || {}).forEach(([k, v]) => body.append(k, v));
    const res = await fetch(API_URL, { method: 'POST', body, credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.error || 'Request failed.');
    return data;
  }

  async function loadApps() {
    const data = await apiGet(API_URL + '?action=list&t=' + Date.now());
    apps = Array.isArray(data.applications) ? data.applications : [];
    renderList();
  }

  function badgeClass(status) {
    const s = String(status || 'new').toLowerCase();
    if (s === 'accepted') return 'badge-accepted';
    if (s === 'rejected') return 'badge-rejected';
    if (s === 'reviewed') return 'badge-reviewed';
    return 'badge-new';
  }

  function renderList() {
    if (!listEl) return;
    if (!apps.length) {
      listEl.innerHTML = '<p class="muted">No admission applications yet. When a student clicks Enroll Now and submits the form, it will appear here.</p>';
      return;
    }

    listEl.innerHTML =
      '<div class="table-wrap"><table class="admin-apps-table"><thead><tr>' +
      '<th>Date</th><th>Student</th><th>Course</th><th>ID Proof</th><th>Status</th><th></th>' +
      '</tr></thead><tbody>' +
      apps.map((a) => {
        const status = String(a.status || 'new').toLowerCase();
        return (
          '<tr data-id="' + escapeHtml(a.id) + '">' +
            '<td>' + formatDate(a.created_at) + '</td>' +
            '<td><strong>' + escapeHtml(a.full_name) + '</strong><br>' +
              '<span class="muted">Father: ' + escapeHtml(a.father_name) + '</span><br>' +
              '<span class="muted">DOB: ' + escapeHtml(a.dob) + '</span><br>' +
              '<span class="muted detail-address">' + escapeHtml(a.address) + '</span></td>' +
            '<td>' + escapeHtml(a.course) + '</td>' +
            '<td>' + escapeHtml(a.id_type) + '<br>' +
              '<button type="button" class="btn btn-outline btn-sm" data-file="' + escapeHtml(a.id) + '">View file</button></td>' +
            '<td><span class="badge ' + badgeClass(status) + '">' + escapeHtml(status) + '</span><br>' +
              '<select class="status-select" data-status="' + escapeHtml(a.id) + '">' +
                ['new', 'reviewed', 'accepted', 'rejected'].map((s) =>
                  '<option value="' + s + '"' + (status === s ? ' selected' : '') + '>' + s + '</option>'
                ).join('') +
              '</select></td>' +
            '<td class="admin-row-actions">' +
              '<button type="button" class="btn btn-danger btn-sm" data-del="' + escapeHtml(a.id) + '">Delete</button>' +
            '</td>' +
          '</tr>'
        );
      }).join('') +
      '</tbody></table></div>';
  }

  async function openProof(id) {
    showStatus('Opening identity proof…', true);
    try {
      const res = await fetch(API_URL + '?action=file&id=' + encodeURIComponent(id) + '&t=' + Date.now(), {
        credentials: 'same-origin',
        headers: { 'X-Admin-Code': adminCode },
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error || 'Could not open file.');
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      window.open(url, '_blank', 'noopener');
      setTimeout(() => URL.revokeObjectURL(url), 60000);
      showStatus('', true);
    } catch (err) {
      showStatus(err.message || 'Could not open file.', false);
    }
  }

  gateForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = document.getElementById('adminCode').value.trim();
    if (!isAdminCode(code)) {
      gateError.hidden = false;
      return;
    }
    gateError.hidden = true;
    adminCode = code;
    sessionStorage.setItem(CODE_KEY, code);
    localStorage.setItem(ADMIN_KEY, '1');
    await apiLogin(code);
    setAuthed(true);
    try {
      await loadApps();
      showStatus(apps.length ? '' : 'No applications yet.', true);
    } catch (err) {
      showStatus(err.message || 'Could not load applications.', false);
    }
  });

  btnLogout?.addEventListener('click', async () => {
    localStorage.removeItem(ADMIN_KEY);
    sessionStorage.removeItem(CODE_KEY);
    adminCode = '';
    try {
      await fetch('library/api/logout.php', { method: 'POST', credentials: 'same-origin' });
    } catch (_) { /* ignore */ }
    setAuthed(false);
  });

  btnRefresh?.addEventListener('click', async () => {
    try {
      await loadApps();
      showStatus('Updated.', true);
    } catch (err) {
      showStatus(err.message || 'Refresh failed.', false);
    }
  });

  listEl?.addEventListener('click', async (e) => {
    const fileId = e.target.getAttribute('data-file');
    const delId = e.target.getAttribute('data-del');
    if (fileId) {
      await openProof(fileId);
      return;
    }
    if (delId) {
      if (!confirm('Delete this admission application and its identity proof?')) return;
      try {
        const data = await apiPost({ action: 'delete', id: delId });
        apps = data.applications || [];
        renderList();
        showStatus('Application deleted.', true);
      } catch (err) {
        showStatus(err.message || 'Delete failed.', false);
      }
    }
  });

  listEl?.addEventListener('change', async (e) => {
    const id = e.target.getAttribute('data-status');
    if (!id || e.target.tagName !== 'SELECT') return;
    try {
      const data = await apiPost({ action: 'set_status', id, status: e.target.value });
      apps = data.applications || [];
      renderList();
      showStatus('Status updated.', true);
    } catch (err) {
      showStatus(err.message || 'Could not update status.', false);
      await loadApps().catch(() => {});
    }
  });

  document.addEventListener('DOMContentLoaded', async () => {
    if (window.SabeelSiteNav) window.SabeelSiteNav.mount();
    if (localStorage.getItem(ADMIN_KEY) === '1' && adminCode && isAdminCode(adminCode)) {
      await apiLogin(adminCode);
      setAuthed(true);
      try {
        await loadApps();
      } catch (err) {
        showStatus(err.message || 'Could not load applications.', false);
      }
    } else {
      setAuthed(false);
    }
  });
})();
