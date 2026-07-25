/**
 * Homepage courses admin — same admin codes as Library.
 */
(function () {
  'use strict';

  const cfg = window.LIBRARY_CONFIG || {};
  const API_URL = 'api/website-courses.php';
  const FALLBACK_URL = 'data/website-courses.json';
  const ADMIN_CODES = (cfg.ADMIN_CODES || [cfg.ADMIN_CODE || 'admin@sabeel']).map((c) => String(c).toUpperCase());
  const ADMIN_KEY = 'sabeel_website_courses_admin';
  const CODE_KEY = 'sabeel_lib_admin_code';

  const gateView = document.getElementById('viewAdminGate');
  const appView = document.getElementById('viewAdminApp');
  const gateForm = document.getElementById('adminGateForm');
  const gateError = document.getElementById('adminGateError');
  const statusEl = document.getElementById('courseStatus');
  const listEl = document.getElementById('coursesTableWrap');
  const form = document.getElementById('courseForm');
  const btnCancel = document.getElementById('btnCancelEdit');
  const btnLogout = document.getElementById('btnAdminLogout');

  let courses = [];
  let editingId = null;
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

  async function apiLogin(code) {
    try {
      const body = new FormData();
      body.append('admin_code', code);
      await fetch('library/api/login.php', { method: 'POST', body, credentials: 'same-origin' });
    } catch (_) { /* optional */ }
  }

  async function postAction(action, fields) {
    const body = new FormData();
    body.append('action', action);
    body.append('admin_code', adminCode);
    Object.entries(fields || {}).forEach(([k, v]) => body.append(k, v));
    const res = await fetch(API_URL, { method: 'POST', body, credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) {
      throw new Error(data.error || 'Request failed.');
    }
    return data;
  }

  async function loadCourses() {
    try {
      const res = await fetch(API_URL + '?t=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' });
      if (res.ok) {
        const data = await res.json();
        if (data && data.ok && Array.isArray(data.courses)) {
          courses = data.courses;
          renderList();
          return;
        }
      }
    } catch (_) { /* fall through */ }

    const res = await fetch(FALLBACK_URL + '?t=' + Date.now(), { cache: 'no-store' });
    const data = await res.json();
    courses = Array.isArray(data.courses) ? data.courses : [];
    renderList();
  }

  function field(id) {
    return document.getElementById(id);
  }

  function fillForm(course) {
    editingId = course ? course.id : null;
    field('courseId').value = course?.id || '';
    field('name').value = course?.name || '';
    field('description').value = course?.description || '';
    field('image').value = course?.image || 'assets/personal.png';
    field('registration').value = course?.registration || 'open';
    field('duration').value = course?.duration || '';
    field('classDays').value = course?.classDays || '';
    field('fee').value = course?.fee || '';
    field('sortOrder').value = course?.sortOrder ?? '';
    field('whatsappEnrollText').value = course?.whatsappEnrollText || '';
    field('whatsappWaitlistText').value = course?.whatsappWaitlistText || '';
    document.getElementById('formTitle').textContent = course ? 'Edit course' : 'Add course';
    btnCancel.hidden = !course;
  }

  function renderList() {
    if (!listEl) return;
    const sorted = courses.slice().sort((a, b) => (Number(a.sortOrder) || 0) - (Number(b.sortOrder) || 0));
    if (!sorted.length) {
      listEl.innerHTML = '<p class="muted">No courses yet. Add the first one above.</p>';
      return;
    }
    listEl.innerHTML =
      '<div class="table-wrap"><table class="data admin-courses-table"><thead><tr>' +
      '<th>Order</th><th>Name</th><th>Status</th><th>Fee</th><th></th></tr></thead><tbody>' +
      sorted.map((c) => {
        const open = String(c.registration).toLowerCase() === 'open';
        return (
          '<tr data-id="' + escapeAttr(c.id) + '">' +
            '<td>' + escapeHtml(c.sortOrder ?? '') + '</td>' +
            '<td><strong>' + escapeHtml(c.name) + '</strong><br><span class="muted">' + escapeHtml(c.image || '') + '</span></td>' +
            '<td><span class="badge ' + (open ? 'badge-open' : 'badge-closed') + '">' + (open ? 'Open' : 'Closed') + '</span></td>' +
            '<td>' + escapeHtml(c.fee || '—') + '</td>' +
            '<td class="admin-row-actions">' +
              '<button type="button" class="btn btn-outline btn-sm" data-edit="' + escapeAttr(c.id) + '">Edit</button> ' +
              '<button type="button" class="btn btn-danger btn-sm" data-del="' + escapeAttr(c.id) + '">Delete</button>' +
            '</td>' +
          '</tr>'
        );
      }).join('') +
      '</tbody></table></div>';
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/'/g, '&#39;');
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
    await loadCourses();
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

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    showStatus('Saving…', true);
    const course = {
      id: field('courseId').value.trim(),
      name: field('name').value.trim(),
      description: field('description').value.trim(),
      image: field('image').value.trim(),
      registration: field('registration').value,
      duration: field('duration').value.trim(),
      classDays: field('classDays').value.trim(),
      fee: field('fee').value.trim(),
      sortOrder: field('sortOrder').value === '' ? 0 : Number(field('sortOrder').value),
      whatsappEnrollText: field('whatsappEnrollText').value.trim(),
      whatsappWaitlistText: field('whatsappWaitlistText').value.trim(),
    };
    try {
      const data = await postAction('save', { course: JSON.stringify(course) });
      courses = data.courses || [];
      renderList();
      fillForm(null);
      showStatus('Course saved. Homepage will show it after refresh.', true);
    } catch (err) {
      showStatus(err.message || 'Save failed.', false);
    }
  });

  btnCancel?.addEventListener('click', () => {
    fillForm(null);
    showStatus('', true);
  });

  listEl?.addEventListener('click', async (e) => {
    const editId = e.target.getAttribute('data-edit');
    const delId = e.target.getAttribute('data-del');
    if (editId) {
      const course = courses.find((c) => c.id === editId);
      if (course) {
        fillForm(course);
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      return;
    }
    if (delId) {
      if (!confirm('Delete this course from the homepage?')) return;
      try {
        const data = await postAction('delete', { id: delId });
        courses = data.courses || [];
        renderList();
        if (editingId === delId) fillForm(null);
        showStatus('Course deleted.', true);
      } catch (err) {
        showStatus(err.message || 'Delete failed.', false);
      }
    }
  });

  document.addEventListener('DOMContentLoaded', async () => {
    if (localStorage.getItem(ADMIN_KEY) === '1' && adminCode && isAdminCode(adminCode)) {
      await apiLogin(adminCode);
      setAuthed(true);
      await loadCourses();
    } else {
      setAuthed(false);
    }
  });
})();
