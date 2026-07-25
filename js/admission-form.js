/**
 * Course enrollment admission modal.
 */
(function () {
  'use strict';

  const API_URL = 'api/admissions.php';
  const modal = document.getElementById('admissionModal');
  const form = document.getElementById('admissionForm');
  const courseInput = document.getElementById('admissionCourse');
  const courseLabel = document.getElementById('admissionCourseLabel');
  const statusEl = document.getElementById('admissionStatus');
  const submitBtn = document.getElementById('admissionSubmit');

  if (!modal || !form) return;

  function setStatus(message, type) {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.className = 'form-status' + (type ? ' ' + type : '');
  }

  function mark(el, ok) {
    if (!el) return;
    el.classList.toggle('is-invalid', !ok);
    el.setAttribute('aria-invalid', ok ? 'false' : 'true');
  }

  function openAdmission(courseName) {
    const name = String(courseName || '').trim();
    if (!name) return;
    courseInput.value = name;
    if (courseLabel) courseLabel.textContent = name;
    form.reset();
    courseInput.value = name;
    setStatus('', '');
    ['admFullName', 'admFatherName', 'admDob', 'admAddress', 'admIdType', 'admIdProof'].forEach((id) => {
      mark(document.getElementById(id), true);
    });
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('admission-open');
    document.getElementById('admFullName')?.focus();
  }

  function closeAdmission() {
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('admission-open');
  }

  window.SabeelAdmission = { open: openAdmission, close: closeAdmission };

  document.getElementById('admissionClose')?.addEventListener('click', closeAdmission);
  document.getElementById('admissionCancel')?.addEventListener('click', closeAdmission);
  document.getElementById('admissionBackdrop')?.addEventListener('click', closeAdmission);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) closeAdmission();
  });

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-enroll-course]');
    if (!btn) return;
    e.preventDefault();
    openAdmission(btn.getAttribute('data-enroll-course'));
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    setStatus('', '');

    const fullName = document.getElementById('admFullName');
    const fatherName = document.getElementById('admFatherName');
    const dob = document.getElementById('admDob');
    const address = document.getElementById('admAddress');
    const idType = document.getElementById('admIdType');
    const idProof = document.getElementById('admIdProof');

    let ok = true;
    if (!fullName.value.trim() || fullName.value.trim().length < 2) { mark(fullName, false); ok = false; } else mark(fullName, true);
    if (!fatherName.value.trim()) { mark(fatherName, false); ok = false; } else mark(fatherName, true);
    if (!dob.value) { mark(dob, false); ok = false; } else mark(dob, true);
    if (!address.value.trim() || address.value.trim().length < 8) { mark(address, false); ok = false; } else mark(address, true);
    if (!idType.value) { mark(idType, false); ok = false; } else mark(idType, true);
    if (!idProof.files || !idProof.files[0]) { mark(idProof, false); ok = false; } else mark(idProof, true);

    if (!ok) {
      setStatus('Please fill all required fields and upload identity proof.', 'error');
      return;
    }

    const file = idProof.files[0];
    if (file.size > 5 * 1024 * 1024) {
      mark(idProof, false);
      setStatus('Identity proof must be 5 MB or smaller.', 'error');
      return;
    }

    const body = new FormData(form);
    submitBtn.disabled = true;
    setStatus('Submitting application…', '');

    try {
      const res = await fetch(API_URL, { method: 'POST', body });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Submission failed. Please try again.');
      }
      setStatus(data.message || 'Application submitted successfully.', 'success');
      form.reset();
      courseInput.value = '';
      setTimeout(closeAdmission, 1600);
      if (window.showFormPopup || document.getElementById('formPopup')) {
        // Reuse contact success popup if available via custom event
      }
      const popupTitle = document.getElementById('formPopupTitle');
      const popupMsg = document.getElementById('formPopupMessage');
      const popup = document.getElementById('formPopup');
      if (popup && popupTitle && popupMsg) {
        popup.dataset.type = 'success';
        popupTitle.textContent = 'Application received';
        popupMsg.textContent = data.message || 'Your admission form was submitted successfully.';
        const icon = document.getElementById('formPopupIcon');
        if (icon) icon.textContent = '✓';
        popup.hidden = false;
        popup.setAttribute('aria-hidden', 'false');
        document.body.classList.add('form-popup-open');
      }
    } catch (err) {
      setStatus(err.message || 'Submission failed.', 'error');
    } finally {
      submitBtn.disabled = false;
    }
  });
})();
