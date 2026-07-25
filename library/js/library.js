/**
 * Student Digital Library — folder-based navigation
 * Library → Course → Subject → Book folder → Volumes / PDFs
 */
(function () {
  'use strict';

  var cfg = window.LIBRARY_CONFIG;
  var data = window.LIBRARY_DATA;
  var auth = window.LibraryAuth;

  var state = {
    view: 'access',
    level: 'root', /* root | course | subject | book | search */
    courseId: null,
    subjectId: null,
    folderId: null,
    query: ''
  };

  var serverResources = [];
  var serverFolders = [];
  var liveCourses = [];
  var els = {};

  function $(id) { return document.getElementById(id); }

  function cacheEls() {
    els.viewAccess = $('viewAccess');
    els.viewDenied = $('viewDenied');
    els.viewDashboard = $('viewDashboard');
    els.accessForm = $('accessForm');
    els.accessCode = $('accessCode');
    els.accessError = $('accessError');
    els.btnContactAdmin = $('btnContactAdmin');
    els.btnDeniedWhatsApp = $('btnDeniedWhatsApp');
    els.btnLogout = $('btnLogout');
    els.searchInput = $('librarySearch');
    els.btnBack = $('btnFolderBack');
    els.breadcrumb = $('libBreadcrumb');
    els.folderTitle = $('folderTitle');
    els.folderSubtitle = $('folderSubtitle');
    els.folderGrid = $('folderGrid');
    els.resourcesGrid = $('resourcesGrid');
    els.emptyState = $('emptyState');
    els.toast = $('libraryToast');
    els.resourceCount = $('resourceCount');
    els.folderPane = $('folderPane');
    els.filesPane = $('filesPane');
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeAttr(str) {
    return escapeHtml(str).replace(/'/g, '&#39;');
  }

  function formatDate(iso) {
    if (!iso) return '—';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function getLocalCustomResources() {
    try {
      return JSON.parse(localStorage.getItem(cfg.RESOURCES_KEY) || '[]');
    } catch (e) {
      return [];
    }
  }

  function normalizeResource(r) {
    var copy = Object.assign({}, r);
    /* Backward compatibility: old "category" → subjectId */
    if (!copy.subjectId && copy.category) {
      copy.subjectId = String(copy.category)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
    }
    return copy;
  }

  function getAllResources() {
    var map = {};
    (data.resources || []).forEach(function (r) { map[r.id] = normalizeResource(r); });
    getLocalCustomResources().forEach(function (r) { map[r.id] = normalizeResource(r); });
    serverResources.forEach(function (r) { map[r.id] = normalizeResource(r); });
    return Object.keys(map).map(function (k) { return map[k]; });
  }

  function getLocalFolders() {
    try {
      return JSON.parse(localStorage.getItem('sabeel_lib_folders') || '[]');
    } catch (e) {
      return [];
    }
  }

  function getAllFolders() {
    var map = {};
    getLocalFolders().forEach(function (f) { map[f.id] = f; });
    serverFolders.forEach(function (f) { map[f.id] = f; });
    return Object.keys(map).map(function (k) { return map[k]; });
  }

  function getFolder(folderId) {
    var list = getAllFolders();
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === folderId) return list[i];
    }
    return null;
  }

  function foldersInSubject(courseId, subjectId) {
    return getAllFolders().filter(function (f) {
      return f.courseId === courseId && f.subjectId === subjectId;
    });
  }

  function resourcesInFolder(folderId) {
    return getAllResources().filter(function (r) { return r.folderId === folderId; });
  }

  function looseResourcesInSubject(courseId, subjectId) {
    return resourcesInSubject(courseId, subjectId).filter(function (r) { return !r.folderId; });
  }

  function loadLocalStructureCourses() {
    try {
      /* Drop legacy browser cache that still held the old hardcoded courses */
      localStorage.removeItem('sabeel_lib_structure');
      var local = JSON.parse(localStorage.getItem('sabeel_lib_structure_v3') || '{"courses":[]}');
      return Array.isArray(local.courses) ? local.courses : [];
    } catch (e) {
      return [];
    }
  }

  function rememberStructure(courses) {
    liveCourses = Array.isArray(courses) ? courses : [];
    try {
      localStorage.removeItem('sabeel_lib_structure');
      localStorage.setItem('sabeel_lib_structure_v3', JSON.stringify({ courses: liveCourses }));
    } catch (e) { /* ignore */ }
  }

  function fetchJson(url) {
    return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      });
  }

  /** Load only Admin-managed courses (empty until you create them). */
  function loadStructure() {
    var bust = '?t=' + Date.now();
    return fetchJson('api/structure.php' + bust)
      .then(function (json) {
        if (json && json.ok && Array.isArray(json.courses)) {
          rememberStructure(json.courses);
          return 'api';
        }
        throw new Error('Invalid structure API response');
      })
      .catch(function () {
        return fetchJson('data/structure.json' + bust)
          .then(function (json) {
            var courses = (json && Array.isArray(json.courses)) ? json.courses : [];
            rememberStructure(courses);
            return 'file';
          });
      })
      .catch(function () {
        rememberStructure(loadLocalStructureCourses());
        return 'local';
      });
  }

  function loadServerResources() {
    var bust = '?t=' + Date.now();
    return Promise.all([
      fetchJson('api/resources.php' + bust)
        .then(function (json) {
          if (json && json.ok && Array.isArray(json.resources)) {
            serverResources = json.resources;
          }
        })
        .catch(function () { serverResources = []; }),
      fetchJson('api/folders.php' + bust)
        .then(function (json) {
          if (json && json.ok && Array.isArray(json.folders)) {
            serverFolders = json.folders;
          }
        })
        .catch(function () { serverFolders = []; }),
      loadStructure()
    ]);
  }

  function getCourse(courseId) {
    for (var i = 0; i < liveCourses.length; i++) {
      if (liveCourses[i].id === courseId) return liveCourses[i];
    }
    return null;
  }

  function getSubjects(course) {
    if (!course) return [];
    if (course.subjects && course.subjects.length) return course.subjects;
    /* Old data.js without subject folders → one default folder */
    return [{ id: 'general', name: 'General Resources' }];
  }

  function getSubject(course, subjectId) {
    var subjects = getSubjects(course);
    for (var i = 0; i < subjects.length; i++) {
      if (subjects[i].id === subjectId) return subjects[i];
    }
    return null;
  }

  function countInCourse(courseId) {
    return getAllResources().filter(function (r) { return r.courseId === courseId; }).length;
  }

  function resourcesInSubject(courseId, subjectId) {
    return getAllResources().filter(function (r) {
      if (r.courseId !== courseId) return false;
      /* Fallback folder for old data without subject mapping */
      if (subjectId === 'general') return true;
      return r.subjectId === subjectId;
    });
  }

  function countInSubject(courseId, subjectId) {
    return resourcesInSubject(courseId, subjectId).length;
  }

  function showView(name) {
    state.view = name;
    els.viewAccess.hidden = name !== 'access';
    els.viewDenied.hidden = name !== 'denied';
    els.viewDashboard.hidden = name !== 'dashboard';
  }

  function showToast(message) {
    if (!els.toast) return;
    els.toast.textContent = message;
    els.toast.classList.add('is-visible');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () {
      els.toast.classList.remove('is-visible');
    }, 2800);
  }

  function folderIconSvg() {
    return (
      '<span class="folder-icon" aria-hidden="true">' +
      '<svg viewBox="0 0 64 64" width="56" height="56">' +
      '<path fill="#F6C945" d="M8 18c0-3.3 2.7-6 6-6h14l4 5h22c3.3 0 6 2.7 6 6v28c0 3.3-2.7 6-6 6H14c-3.3 0-6-2.7-6-6V18z"/>' +
      '<path fill="#E8B42E" d="M8 28h48v23c0 3.3-2.7 6-6 6H14c-3.3 0-6-2.7-6-6V28z" opacity=".35"/>' +
      '</svg></span>'
    );
  }

  function pdfIconSvg() {
    return (
      '<span class="res-pdf-icon" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8">' +
      '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>' +
      '<path d="M14 2v6h6"/><path d="M9 13h6M9 17h4"/></svg></span>'
    );
  }

  function coverHtml(resource) {
    if (resource.cover) {
      return (
        '<img class="res-cover" src="' + escapeAttr(resource.cover) +
        '" alt="' + escapeAttr(resource.title || 'Book cover') +
        '" loading="lazy">'
      );
    }
    return (
      '<div class="res-cover res-cover-fallback" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5">' +
      '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>' +
      '</svg></div>'
    );
  }

  function renderBreadcrumb() {
    var parts = [
      { label: 'Home', href: '/', type: 'link' },
      { label: 'Library', action: 'root' }
    ];

    if (state.level === 'course' || state.level === 'subject' || state.level === 'book' || (state.level === 'search' && state.courseId)) {
      var course = getCourse(state.courseId);
      if (course) parts.push({ label: course.name, action: 'course', courseId: course.id });
    }

    if (state.level === 'subject' || state.level === 'book') {
      var c = getCourse(state.courseId);
      var s = getSubject(c, state.subjectId);
      if (s) parts.push({ label: s.name, action: 'subject', courseId: state.courseId, subjectId: state.subjectId });
    }

    if (state.level === 'book') {
      var folder = getFolder(state.folderId);
      if (folder) parts.push({ label: folder.name, action: 'book' });
    }

    if (state.level === 'search') {
      parts.push({ label: 'Search results', action: 'search' });
    }

    els.breadcrumb.innerHTML = parts.map(function (p, i) {
      var sep = i === 0 ? '' : '<span class="crumb-sep" aria-hidden="true">›</span>';
      if (p.href) {
        return sep + '<a class="crumb" href="' + escapeAttr(p.href) + '">' + escapeHtml(p.label) + '</a>';
      }
      if (i === parts.length - 1) {
        return sep + '<span class="crumb is-current" aria-current="page">' + escapeHtml(p.label) + '</span>';
      }
      return sep +
        '<button type="button" class="crumb crumb-btn" data-crumb="' + escapeAttr(p.action) + '"' +
        (p.courseId ? ' data-course="' + escapeAttr(p.courseId) + '"' : '') +
        (p.subjectId ? ' data-subject="' + escapeAttr(p.subjectId) + '"' : '') +
        '>' + escapeHtml(p.label) + '</button>';
    }).join('');
  }

  function showSubjectContents(title, subtitle, folderHtml, looseList) {
    els.folderPane.hidden = false;
    els.filesPane.hidden = !looseList.length;
    els.folderTitle.textContent = title;
    els.folderSubtitle.textContent = subtitle;
    els.folderGrid.innerHTML = folderHtml || '';
    els.btnBack.hidden = false;
    renderBreadcrumb();

    if (looseList.length) {
      els.emptyState.hidden = true;
      els.resourcesGrid.innerHTML = looseList.map(renderResourceCard).join('');
      els.filesPane.hidden = false;
    } else {
      els.resourcesGrid.innerHTML = '';
      els.emptyState.hidden = !!(folderHtml && folderHtml.indexOf('folder-card') !== -1);
      if (!folderHtml || folderHtml.indexOf('folder-card') === -1) {
        els.emptyState.hidden = false;
        els.folderPane.hidden = true;
        els.filesPane.hidden = false;
      }
    }
    animatePane(els.folderPane);
    if (!els.filesPane.hidden) animatePane(els.filesPane);
  }

  function animatePane(el) {
    if (!el) return;
    el.classList.remove('folder-anim');
    void el.offsetWidth;
    el.classList.add('folder-anim');
  }

  function renderFolderCard(opts) {
    var badges = [];
    if (typeof opts.folderCount === 'number') {
      badges.push('<span class="folder-badge">' + opts.folderCount + ' folder' + (opts.folderCount === 1 ? '' : 's') + '</span>');
    }
    if (typeof opts.fileCount === 'number') {
      badges.push('<span class="folder-badge folder-badge-soft">' + opts.fileCount + ' file' + (opts.fileCount === 1 ? '' : 's') + '</span>');
    }
    if (!badges.length && opts.meta) {
      badges.push('<span class="folder-badge folder-badge-soft">' + escapeHtml(opts.meta) + '</span>');
    }

    return (
      '<button type="button" class="folder-card" data-open="' + escapeAttr(opts.open) + '"' +
        (opts.courseId ? ' data-course="' + escapeAttr(opts.courseId) + '"' : '') +
        (opts.subjectId ? ' data-subject="' + escapeAttr(opts.subjectId) + '"' : '') +
        (opts.folderId ? ' data-folder="' + escapeAttr(opts.folderId) + '"' : '') +
        ' style="--folder-accent:' + escapeAttr(opts.color || '#0B5ED7') + '">' +
        '<span class="folder-card-top">' +
          folderIconSvg() +
          '<span class="folder-card-open">Open</span>' +
        '</span>' +
        '<span class="folder-card-body">' +
          '<span class="folder-card-title">' + escapeHtml(opts.title) + '</span>' +
          '<span class="folder-card-badges">' + badges.join('') + '</span>' +
        '</span>' +
      '</button>'
    );
  }

  function renderResourceCard(resource) {
    return (
      '<article class="resource-card folder-anim-item">' +
        '<div class="resource-card-media">' +
          coverHtml(resource) +
          pdfIconSvg() +
        '</div>' +
        '<div class="resource-card-body">' +
          '<h3 class="resource-title">' + escapeHtml(resource.title) + '</h3>' +
          '<p class="resource-desc">' + escapeHtml(resource.description || 'PDF document') + '</p>' +
          '<p class="resource-sub">Uploaded ' + escapeHtml(formatDate(resource.updatedAt)) +
            ' · ' + escapeHtml(resource.fileSize || '—') + '</p>' +
          '<button type="button" class="btn btn-primary btn-sm btn-download" data-download="' + escapeAttr(resource.id) + '">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14"/></svg>' +
            ' Download' +
          '</button>' +
        '</div>' +
      '</article>'
    );
  }

  function showFolders(title, subtitle, html) {
    els.folderPane.hidden = false;
    els.filesPane.hidden = true;
    els.folderTitle.textContent = title;
    els.folderSubtitle.textContent = subtitle;
    els.folderGrid.innerHTML = html;
    els.emptyState.hidden = true;
    els.btnBack.hidden = state.level === 'root';
    renderBreadcrumb();
    animatePane(els.folderPane);
  }

  function showFiles(title, subtitle, list) {
    els.folderPane.hidden = true;
    els.filesPane.hidden = false;
    els.folderTitle.textContent = title;
    els.folderSubtitle.textContent = subtitle;
    els.btnBack.hidden = false;
    renderBreadcrumb();

    if (!list.length) {
      els.resourcesGrid.innerHTML = '';
      els.emptyState.hidden = false;
    } else {
      els.emptyState.hidden = true;
      els.resourcesGrid.innerHTML = list.map(renderResourceCard).join('');
    }
    animatePane(els.filesPane);
  }

  function goRoot() {
    state.level = 'root';
    state.courseId = null;
    state.subjectId = null;
    state.folderId = null;
    if (els.searchInput) els.searchInput.value = '';
    state.query = '';

    function paint() {
      if (!liveCourses.length) {
        showFolders(
          'Library',
          'No course folders yet. Create them in Library Admin.',
          '<p class="admin-empty">Library is empty. Open Admin → create a course, then subjects and books.</p>'
        );
        updateCount();
        return;
      }

      var html = liveCourses.map(function (c) {
        var subjects = getSubjects(c);
        var n = countInCourse(c.id);
        return renderFolderCard({
          open: 'course',
          courseId: c.id,
          title: c.name,
          folderCount: subjects.length,
          fileCount: n,
          color: c.color || '#0B5ED7'
        });
      }).join('');

      showFolders(
        'Library',
        'Open a course folder, then a subject folder, then books.',
        html
      );
      updateCount();
    }

    /* Refresh from Admin structure every time we land on the root */
    loadStructure().then(paint).catch(paint);
  }

  function goCourse(courseId) {
    var course = getCourse(courseId);
    if (!course) return;
    state.level = 'course';
    state.courseId = courseId;
    state.subjectId = null;
    state.folderId = null;

    var html = getSubjects(course).map(function (s) {
      var n = countInSubject(courseId, s.id);
      var bookFolders = foldersInSubject(courseId, s.id).length;
      return renderFolderCard({
        open: 'subject',
        courseId: courseId,
        subjectId: s.id,
        title: s.name,
        folderCount: bookFolders > 0 ? bookFolders : undefined,
        fileCount: n,
        color: course.color || '#0B5ED7',
        meta: bookFolders === 0 && n === 0 ? 'Empty' : undefined
      });
    }).join('');

    showFolders(
      course.name,
      'Open a subject folder to view book folders and PDFs.',
      html || '<p class="admin-empty">No subject folders yet.</p>'
    );
  }

  function goSubject(courseId, subjectId) {
    var course = getCourse(courseId);
    var subject = getSubject(course, subjectId);
    if (!course || !subject) return;

    state.level = 'subject';
    state.courseId = courseId;
    state.subjectId = subjectId;
    state.folderId = null;

    var bookFolders = foldersInSubject(courseId, subjectId);
    var loose = looseResourcesInSubject(courseId, subjectId);
    var totalFiles = resourcesInSubject(courseId, subjectId).length;

    var folderHtml = bookFolders.map(function (f) {
      return renderFolderCard({
        open: 'book',
        courseId: courseId,
        subjectId: subjectId,
        folderId: f.id,
        title: f.name,
        fileCount: resourcesInFolder(f.id).length,
        color: course.color || '#0B5ED7'
      });
    }).join('');

    if (!bookFolders.length && !loose.length) {
      showFiles(subject.name, 'No books in this subject yet.', []);
      return;
    }

    if (!bookFolders.length) {
      showFiles(
        subject.name,
        loose.length + ' PDF' + (loose.length === 1 ? '' : 's') + ' in this subject',
        loose
      );
      return;
    }

    showSubjectContents(
      subject.name,
      (bookFolders.length ? bookFolders.length + ' book folder' + (bookFolders.length === 1 ? '' : 's') : '') +
        (loose.length ? (bookFolders.length ? ' · ' : '') + loose.length + ' other PDF' + (loose.length === 1 ? '' : 's') : '') +
        (!loose.length && bookFolders.length ? ' · ' + totalFiles + ' volume' + (totalFiles === 1 ? '' : 's') : ''),
      folderHtml,
      loose
    );
  }

  function goBookFolder(courseId, subjectId, folderId) {
    var course = getCourse(courseId);
    var subject = getSubject(course, subjectId);
    var folder = getFolder(folderId);
    if (!course || !subject || !folder) return;

    state.level = 'book';
    state.courseId = courseId;
    state.subjectId = subjectId;
    state.folderId = folderId;

    var list = resourcesInFolder(folderId);
    showFiles(
      folder.name,
      list.length + ' volume' + (list.length === 1 ? '' : 's') + ' in this set',
      list
    );
  }

  function runSearch(query) {
    state.query = query.trim();
    if (!state.query) {
      goRoot();
      return;
    }

    state.level = 'search';
    var q = state.query.toLowerCase();
    var list = getAllResources().filter(function (r) {
      var course = getCourse(r.courseId);
      var subject = course ? getSubject(course, r.subjectId) : null;
      var folder = r.folderId ? getFolder(r.folderId) : null;
      var hay = [r.title, r.description, r.fileSize, course && course.name, subject && subject.name, folder && folder.name]
        .join(' ')
        .toLowerCase();
      return hay.indexOf(q) !== -1;
    });

    showFiles(
      'Search results',
      list.length + ' match' + (list.length === 1 ? '' : 'es') + ' for “' + state.query + '”',
      list
    );
  }

  function goBack() {
    if (state.level === 'book') {
      goSubject(state.courseId, state.subjectId);
      return;
    }
    if (state.level === 'subject' || state.level === 'search') {
      if (state.courseId && state.level === 'subject') {
        goCourse(state.courseId);
      } else {
        goRoot();
      }
      return;
    }
    if (state.level === 'course') {
      goRoot();
    }
  }

  function updateCount() {
    if (els.resourceCount) {
      els.resourceCount.textContent = String(getAllResources().length);
    }
  }

  function downloadResource(id) {
    var list = getAllResources();
    var resource = null;
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === id) { resource = list[i]; break; }
    }
    if (!resource) return;

    if (resource.fileUrl && resource.fileUrl !== '#') {
      var a = document.createElement('a');
      a.href = resource.fileUrl;
      a.download = '';
      a.target = '_blank';
      a.rel = 'noopener';
      document.body.appendChild(a);
      a.click();
      a.remove();
      showToast('Download started: ' + resource.title);
      return;
    }
    showToast('File not uploaded yet. Please contact administration.');
  }

  function bindEvents() {
    if (els.accessForm) {
      els.accessForm.addEventListener('submit', function (e) {
        e.preventDefault();
        els.accessError.hidden = true;
        try {
          if (auth.login(els.accessCode.value)) {
            enterDashboard();
          } else {
            showView('denied');
          }
        } catch (err) {
          console.error(err);
          showView('denied');
        }
      });
    }

    if (els.btnContactAdmin) els.btnContactAdmin.href = auth.whatsAppUrl();
    if (els.btnDeniedWhatsApp) els.btnDeniedWhatsApp.href = auth.whatsAppUrl();

    document.querySelectorAll('[data-back-access]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        els.accessCode.value = '';
        showView('access');
        els.accessCode.focus();
      });
    });

    if (els.btnLogout) {
      els.btnLogout.addEventListener('click', function () {
        auth.logout();
        showView('access');
        els.accessCode.value = '';
      });
    }

    if (els.btnBack) {
      els.btnBack.addEventListener('click', goBack);
    }

    if (els.breadcrumb) {
      els.breadcrumb.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-crumb]');
        if (!btn) return;
        var action = btn.getAttribute('data-crumb');
        if (action === 'root') goRoot();
        else if (action === 'course') goCourse(btn.getAttribute('data-course') || state.courseId);
        else if (action === 'subject') {
          goSubject(
            btn.getAttribute('data-course') || state.courseId,
            btn.getAttribute('data-subject') || state.subjectId
          );
        }
      });
    }

    if (els.folderGrid) {
      els.folderGrid.addEventListener('click', function (e) {
        var card = e.target.closest('[data-open]');
        if (!card) return;
        var open = card.getAttribute('data-open');
        if (open === 'course') goCourse(card.getAttribute('data-course'));
        if (open === 'subject') {
          goSubject(card.getAttribute('data-course'), card.getAttribute('data-subject'));
        }
        if (open === 'book') {
          goBookFolder(
            card.getAttribute('data-course'),
            card.getAttribute('data-subject'),
            card.getAttribute('data-folder')
          );
        }
      });
    }

    if (els.resourcesGrid) {
      els.resourcesGrid.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-download]');
        if (!btn) return;
        downloadResource(btn.getAttribute('data-download'));
      });
    }

    if (els.searchInput) {
      var searchTimer;
      els.searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
          runSearch(els.searchInput.value);
        }, 220);
      });
    }

    var toggle = document.getElementById('menuToggle');
    var nav = document.getElementById('mainNav');
    var backdrop = document.getElementById('navBackdrop');
    if (toggle && nav) {
      toggle.addEventListener('click', function () {
        var open = !nav.classList.contains('is-open');
        nav.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (backdrop) backdrop.hidden = !open;
        document.body.classList.toggle('nav-open', open);
      });
      if (backdrop) {
        backdrop.addEventListener('click', function () {
          nav.classList.remove('is-open');
          toggle.setAttribute('aria-expanded', 'false');
          backdrop.hidden = true;
          document.body.classList.remove('nav-open');
        });
      }
    }

    var year = document.getElementById('year');
    if (year) year.textContent = String(new Date().getFullYear());
  }

  function enterDashboard() {
    showView('dashboard');
    if (els.folderGrid) {
      els.folderGrid.innerHTML = '<p class="admin-empty">Loading library folders…</p>';
    }
    loadServerResources().then(function () {
      try {
        goRoot();
      } catch (err) {
        console.error('Library dashboard error:', err);
        showToast('Could not load library folders. Upload the latest library files to Hostinger.');
        if (els.folderGrid) {
          els.folderGrid.innerHTML = '<p class="admin-empty">Could not open folders. Please refresh or contact administration.</p>';
        }
      }
    });
  }

  function init() {
    cacheEls();
    bindEvents();

    if (!cfg || !auth || !data) {
      console.error('Library scripts failed to load (config/auth/data).');
      showView('access');
      return;
    }

    if (auth.isAuthenticated()) enterDashboard();
    else showView('access');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
