/**
 * Homepage courses admin — single Admin Login session.
 */
(function () {
  'use strict';

  const API_URL = 'api/website-courses.php';
  const FALLBACK_URL = 'data/website-courses.json';
  const DEFAULT_IMAGE = 'assets/personal.png';

  const gateView = document.getElementById('viewAdminGate');
  const appView = document.getElementById('viewAdminApp');
  const statusEl = document.getElementById('courseStatus');
  const listEl = document.getElementById('coursesTableWrap');
  const form = document.getElementById('courseForm');
  const btnCancel = document.getElementById('btnCancelEdit');
  const btnLogout = document.getElementById('btnAdminLogout');
  const btnUploadImage = document.getElementById('btnUploadImage');
  const imageFile = document.getElementById('imageFile');
  const imagePreview = document.getElementById('imagePreview');
  const imageHint = document.getElementById('imageHint');
  const ctaHint = document.getElementById('ctaHint');
  const registrationSelect = document.getElementById('registration');

  let courses = [];
  let editingId = null;

  function showStatus(msg, ok) {
    if (!statusEl) return;
    statusEl.hidden = !msg;
    statusEl.textContent = msg || '';
    statusEl.className = 'form-status' + (ok ? ' is-ok' : ' is-error');
  }

  function setAuthed(on) {
    if (gateView) gateView.hidden = on;
    if (appView) appView.hidden = !on;
  }

  function field(id) {
    return document.getElementById(id);
  }

  function updateCtaHint() {
    if (!ctaHint || !registrationSelect) return;
    const open = registrationSelect.value === 'open';
    ctaHint.textContent = open
      ? 'Button on homepage: Enroll Now (opens admission form)'
      : 'Button on homepage: Join Waitlist (WhatsApp waitlist message is automatic)';
  }

  function setImage(path) {
    const value = path || DEFAULT_IMAGE;
    field('image').value = value;
    if (imagePreview) {
      imagePreview.src = value + (value.indexOf('?') >= 0 ? '&' : '?') + 't=' + Date.now();
    }
    if (imageHint) {
      imageHint.textContent = value === DEFAULT_IMAGE
        ? 'JPG, PNG, or WebP · max 5 MB'
        : 'Saved: ' + value;
    }
  }

  async function postAction(action, fields) {
    const body = new FormData();
    body.append('action', action);
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

  function fillForm(course) {
    editingId = course ? course.id : null;
    field('courseId').value = course?.id || '';
    field('name').value = course?.name || '';
    field('description').value = course?.description || '';
    setImage(course?.image || DEFAULT_IMAGE);
    field('registration').value = course?.registration || 'open';
    field('duration').value = course?.duration || '';
    field('classDays').value = course?.classDays || '';
    field('fee').value = course?.fee || '';
    field('sortOrder').value = course?.sortOrder ?? '';
    document.getElementById('formTitle').textContent = course ? 'Edit course' : 'Add course';
    btnCancel.hidden = !course;
    if (imageFile) imageFile.value = '';
    updateCtaHint();
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
      '<th>Order</th><th>Name</th><th>Status</th><th>CTA</th><th>Fee</th><th></th></tr></thead><tbody>' +
      sorted.map((c) => {
        const open = String(c.registration).toLowerCase() === 'open';
        return (
          '<tr data-id="' + escapeAttr(c.id) + '">' +
            '<td>' + escapeHtml(c.sortOrder ?? '') + '</td>' +
            '<td><strong>' + escapeHtml(c.name) + '</strong><br><span class="muted">' + escapeHtml(c.image || '') + '</span></td>' +
            '<td><span class="badge ' + (open ? 'badge-open' : 'badge-closed') + '">' + (open ? 'Open' : 'Closed') + '</span></td>' +
            '<td>' + (open ? 'Enroll Now' : 'Join Waitlist') + '</td>' +
            '<td>' + escapeHtml(c.fee || '—') + '</td>' +
            '<td class="admin-row-actions">' +
              '<button type="button" class="btn btn-primary btn-sm" data-edit="' + escapeAttr(c.id) + '">Edit</button>' +
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

  btnLogout?.addEventListener('click', () => {
    if (window.SabeelAdminGate) window.SabeelAdminGate.logoutAdmin();
    else window.location.href = '/pages/logout.php';
  });

  registrationSelect?.addEventListener('change', updateCtaHint);

  btnUploadImage?.addEventListener('click', () => imageFile?.click());

  imageFile?.addEventListener('change', async () => {
    const file = imageFile.files && imageFile.files[0];
    if (!file) return;
    showStatus('Uploading image…', true);
    try {
      const body = new FormData();
      body.append('action', 'upload_image');
      body.append('image', file);
      const res = await fetch(API_URL, { method: 'POST', body, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Upload failed.');
      }
      setImage(data.image || data.url);
      showStatus('Image uploaded to assets. Save the course to apply it.', true);
    } catch (err) {
      showStatus(err.message || 'Upload failed.', false);
    } finally {
      imageFile.value = '';
    }
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    showStatus('Saving…', true);
    const course = {
      id: field('courseId').value.trim(),
      name: field('name').value.trim(),
      description: field('description').value.trim(),
      image: field('image').value.trim() || DEFAULT_IMAGE,
      registration: field('registration').value,
      duration: field('duration').value.trim(),
      classDays: field('classDays').value.trim(),
      fee: field('fee').value.trim(),
      sortOrder: field('sortOrder').value === '' ? 0 : Number(field('sortOrder').value),
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
    updateCtaHint();
    setImage(DEFAULT_IMAGE);
    setAuthed(false);
    const session = await window.SabeelAdminGate.requireAdminOrRedirect('/courses-admin.html');
    if (!session) return;
    setAuthed(true);
    await loadCourses();
  });
})();
