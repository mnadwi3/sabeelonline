/**
 * Load homepage course cards from API / JSON catalogue.
 */
(function () {
  'use strict';

  const WHATSAPP = '918979983149';
  const API_URL = 'api/website-courses.php';
  const FALLBACK_URL = 'data/website-courses.json';

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function waLink(text) {
    return 'https://wa.me/' + WHATSAPP + '?text=' + encodeURIComponent(text || '');
  }

  function isRegistrationOpen(course) {
    return String(course.registration || '').toLowerCase() === 'open';
  }

  function sortCourses(list) {
    return list.slice().sort((a, b) => {
      const openA = isRegistrationOpen(a) ? 0 : 1;
      const openB = isRegistrationOpen(b) ? 0 : 1;
      if (openA !== openB) return openA - openB;
      return (Number(a.sortOrder) || 0) - (Number(b.sortOrder) || 0);
    });
  }

  function cardHtml(course, index) {
    const open = isRegistrationOpen(course);
    const delay = index % 3 === 1 ? ' delay-1' : index % 3 === 2 ? ' delay-2' : '';
    const closedClass = open ? '' : ' is-closed';
    const badgeClass = open ? 'is-open' : 'is-closed';
    const badgeText = open ? 'Registration Open' : 'Registration Closed';
    const btnClass = open ? 'btn btn-primary' : 'btn btn-secondary';
    const ctaLabel = open ? 'Enroll Now' : 'Join Waitlist';
    const waText = course.whatsappWaitlistText
      || ('Assalamu Alaikum, please notify me when ' + course.name + ' registration opens.');
    const name = String(course.name || '').trim();
    const image = String(course.image || 'assets/personal.png').trim() || 'assets/personal.png';
    const cta = open
      ? '<button type="button" class="' + btnClass + '" data-enroll-course="' + escapeHtml(name) + '">' + ctaLabel + '</button>'
      : '<a href="' + escapeHtml(waLink(waText)) + '" class="' + btnClass + '" target="_blank" rel="noopener noreferrer">' + ctaLabel + '</a>';

    return (
      '<article class="course-card' + closedClass + ' reveal' + delay + '" data-name="' + escapeHtml(name) + '" data-registration="' + (open ? 'open' : 'closed') + '">' +
        '<div class="course-media">' +
          '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(name) + '" loading="lazy" decoding="async" width="600" height="360">' +
          '<span class="reg-badge ' + badgeClass + '">' + badgeText + '</span>' +
        '</div>' +
        '<div class="course-body">' +
          '<h3>' + escapeHtml(name) + '</h3>' +
          '<p>' + escapeHtml(course.description || '') + '</p>' +
          '<ul class="course-details">' +
            '<li><span>Duration</span><strong>' + escapeHtml(course.duration || '—') + '</strong></li>' +
            '<li><span>Class Days</span><strong>' + escapeHtml(course.classDays || '—') + '</strong></li>' +
            '<li><span>Fee</span><strong>' + escapeHtml(course.fee || '—') + '</strong></li>' +
          '</ul>' +
          cta +
        '</div>' +
      '</article>'
    );
  }

  function fillContactSelect(courses) {
    const select = document.getElementById('course');
    if (!select) return;
    const names = courses.map((c) => String(c.name || '').trim()).filter(Boolean);
    const current = select.value;
    select.innerHTML = '<option value="">Select a course</option>' +
      names.map((name) => '<option value="' + escapeHtml(name) + '">' + escapeHtml(name) + '</option>').join('');
    if (names.includes(current)) {
      select.value = current;
    }
    if (window.SabeelForm && typeof window.SabeelForm.setAllowedCourses === 'function') {
      window.SabeelForm.setAllowedCourses(names);
    }
  }

  async function fetchCourses() {
    try {
      const res = await fetch(API_URL, { cache: 'no-store' });
      if (res.ok) {
        const data = await res.json();
        if (data && data.ok && Array.isArray(data.courses)) {
          return data.courses;
        }
      }
    } catch (_) { /* fall through */ }

    const res = await fetch(FALLBACK_URL, { cache: 'no-store' });
    if (!res.ok) throw new Error('Could not load courses.');
    const data = await res.json();
    return Array.isArray(data.courses) ? data.courses : [];
  }

  async function initWebsiteCourses() {
    const grid = document.getElementById('coursesGrid');
    if (!grid) return;

    try {
      const courses = sortCourses(await fetchCourses());
      if (!courses.length) {
        grid.innerHTML = '<p class="muted">Courses will appear here soon.</p>';
        return;
      }
      grid.innerHTML = courses.map(cardHtml).join('');
      fillContactSelect(courses);
      if (window.SabeelUI && typeof window.SabeelUI.observeReveal === 'function') {
        window.SabeelUI.observeReveal(grid);
      } else {
        grid.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
      }
    } catch (err) {
      grid.innerHTML = '<p class="muted">Unable to load courses right now.</p>';
      console.warn(err);
    }
  }

  document.addEventListener('DOMContentLoaded', initWebsiteCourses);
})();
