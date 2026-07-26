/**
 * Library Admin — full manual control: courses, subjects, book folders, PDFs.
 */
(function () {
  'use strict';

  var cfg = window.LIBRARY_CONFIG;
  var auth = window.LibraryAuth;
  var API = 'api';

  var els = {};
  var useServer = true;
  var serverList = [];
  var serverFolders = [];
  var liveCourses = [];

  var COLORS = ['#0B5ED7', '#198754', '#0A3D91', '#146c43', '#084298', '#D4AF37', '#b45309'];

  function $(id) { return document.getElementById(id); }

  function getLocalCustom() {
    try { return JSON.parse(localStorage.getItem(cfg.RESOURCES_KEY) || '[]'); }
    catch (e) { return []; }
  }
  function saveLocalCustom(list) { localStorage.setItem(cfg.RESOURCES_KEY, JSON.stringify(list)); }

  function getLocalFolders() {
    try { return JSON.parse(localStorage.getItem('sabeel_lib_folders') || '[]'); }
    catch (e) { return []; }
  }
  function saveLocalFolders(list) { localStorage.setItem('sabeel_lib_folders', JSON.stringify(list)); }

  function getLocalStructure() {
    try {
      localStorage.removeItem('sabeel_lib_structure');
      return JSON.parse(localStorage.getItem('sabeel_lib_structure_v3') || '{"courses":[]}');
    } catch (e) {
      return { courses: [] };
    }
  }
  function saveLocalStructure(courses) {
    try {
      localStorage.removeItem('sabeel_lib_structure');
      localStorage.setItem('sabeel_lib_structure_v3', JSON.stringify({ courses: courses }));
    } catch (e) { /* ignore */ }
  }

  function showAdmin(loggedIn) {
    els.viewAdminGate.hidden = loggedIn;
    els.viewAdminApp.hidden = !loggedIn;
  }

  function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function escapeAttr(str) { return escapeHtml(str).replace(/'/g, '&#39;'); }

  function slugify(text) {
    return String(text || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'item';
  }

  function getCourse(courseId) {
    for (var i = 0; i < liveCourses.length; i++) {
      if (liveCourses[i].id === courseId) return liveCourses[i];
    }
    return null;
  }

  function courseLabel(courseId) {
    var c = getCourse(courseId);
    return c ? c.name : (courseId || '—');
  }

  function subjectLabel(courseId, subjectId) {
    var c = getCourse(courseId);
    if (!c || !c.subjects) return subjectId || '—';
    for (var i = 0; i < c.subjects.length; i++) {
      if (c.subjects[i].id === subjectId) return c.subjects[i].name;
    }
    return subjectId || '—';
  }

  function folderLabel(folderId) {
    if (!folderId) return '—';
    var list = currentFolders();
    for (var i = 0; i < list.length; i++) {
      if (list[i].id === folderId) return list[i].name;
    }
    return folderId;
  }

  function currentFolders() { return useServer ? serverFolders.slice() : getLocalFolders(); }
  function currentList() { return useServer ? serverList.slice() : getLocalCustom(); }

  function setStatus(el, ok, message) {
    if (!el) return;
    el.hidden = false;
    el.className = 'form-status ' + (ok ? 'is-success' : 'is-error');
    el.textContent = message;
  }

  function fillCourseSelect(selectEl, includeEmpty) {
    if (!selectEl) return;
    var html = includeEmpty ? '<option value="">Select course</option>' : '';
    if (!liveCourses.length) {
      html = '<option value="">No courses yet — create one above</option>';
    } else {
      html += liveCourses.map(function (c) {
        return '<option value="' + escapeAttr(c.id) + '">' + escapeHtml(c.name) + '</option>';
      }).join('');
    }
    selectEl.innerHTML = html;
  }

  function fillSubjectSelect(courseSelect, subjectSelect) {
    if (!subjectSelect) return;
    var course = getCourse(courseSelect && courseSelect.value);
    var subjects = (course && course.subjects) ? course.subjects : [];
    if (!subjects.length) {
      subjectSelect.innerHTML = '<option value="">No subjects yet</option>';
      return;
    }
    subjectSelect.innerHTML = subjects.map(function (s) {
      return '<option value="' + escapeAttr(s.id) + '">' + escapeHtml(s.name) + '</option>';
    }).join('');
  }

  function populateFolderSelect() {
    if (!els.fieldFolder) return;
    var courseId = els.fieldCourse.value;
    var subjectId = els.fieldSubject.value;
    var options = '<option value="">None — single PDF</option>';
    currentFolders().forEach(function (f) {
      if (f.courseId === courseId && f.subjectId === subjectId) {
        options += '<option value="' + escapeAttr(f.id) + '">' + escapeHtml(f.name) + '</option>';
      }
    });
    els.fieldFolder.innerHTML = options;
  }

  function refreshAllSelects() {
    fillCourseSelect(els.fieldCourse);
    fillCourseSelect(els.folderCourse);
    fillCourseSelect(els.subjectCourse);
    fillSubjectSelect(els.fieldCourse, els.fieldSubject);
    fillSubjectSelect(els.folderCourse, els.folderSubject);
    populateFolderSelect();
    renderCoursesTable();
    renderSubjectsTable();
  }

  function renderCoursesTable() {
    if (!els.coursesTableWrap) return;
    if (!liveCourses.length) {
      els.coursesTableWrap.innerHTML = '<p class="admin-empty">No courses yet. Create your first course above.</p>';
      return;
    }
    els.coursesTableWrap.innerHTML =
      '<table class="admin-table"><thead><tr><th>Course</th><th>Subjects</th><th></th></tr></thead><tbody>' +
      liveCourses.map(function (c) {
        var n = (c.subjects && c.subjects.length) || 0;
        return '<tr>' +
          '<td><span class="admin-swatch" style="background:' + escapeAttr(c.color || '#0B5ED7') + '"></span> ' + escapeHtml(c.name) + '</td>' +
          '<td>' + n + '</td>' +
          '<td><button type="button" class="btn btn-ghost btn-sm" data-delete-course="' + escapeAttr(c.id) + '">Delete</button></td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';
  }

  function renderSubjectsTable() {
    if (!els.subjectsTableWrap) return;
    var courseId = els.subjectCourse ? els.subjectCourse.value : '';
    var course = getCourse(courseId);
    if (!course) {
      els.subjectsTableWrap.innerHTML = '<p class="admin-empty">Select a course to manage subjects.</p>';
      return;
    }
    var subjects = course.subjects || [];
    if (!subjects.length) {
      els.subjectsTableWrap.innerHTML = '<p class="admin-empty">No subjects in this course yet.</p>';
      return;
    }
    els.subjectsTableWrap.innerHTML =
      '<table class="admin-table"><thead><tr><th>Subject</th><th></th></tr></thead><tbody>' +
      subjects.map(function (s) {
        return '<tr><td>' + escapeHtml(s.name) + '</td>' +
          '<td><button type="button" class="btn btn-ghost btn-sm" data-delete-subject="' + escapeAttr(s.id) + '" data-course="' + escapeAttr(courseId) + '">Delete</button></td></tr>';
      }).join('') +
      '</tbody></table>';
  }

  function renderFoldersTable() {
    if (!els.foldersTableWrap) return;
    var list = currentFolders().slice().reverse();
    if (!list.length) {
      els.foldersTableWrap.innerHTML = '<p class="admin-empty">No book folders yet.</p>';
      return;
    }
    els.foldersTableWrap.innerHTML =
      '<table class="admin-table"><thead><tr><th>Folder</th><th>Course</th><th>Subject</th><th></th></tr></thead><tbody>' +
      list.map(function (f) {
        return '<tr>' +
          '<td>' + escapeHtml(f.name) + '</td>' +
          '<td>' + escapeHtml(courseLabel(f.courseId)) + '</td>' +
          '<td>' + escapeHtml(subjectLabel(f.courseId, f.subjectId)) + '</td>' +
          '<td><button type="button" class="btn btn-ghost btn-sm" data-delete-folder="' + escapeAttr(f.id) + '">Delete</button></td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';
  }

  function renderTable() {
    var list = currentList().slice().reverse();
    if (!list.length) {
      els.adminTableWrap.innerHTML = '<p class="admin-empty">No published books yet.</p>';
      return;
    }
    els.adminTableWrap.innerHTML =
      '<table class="admin-table"><thead><tr><th>Title</th><th>Course</th><th>Subject</th><th>Book folder</th><th>Size</th><th></th></tr></thead><tbody>' +
      list.map(function (r) {
        return '<tr>' +
          '<td>' + escapeHtml(r.title) + '</td>' +
          '<td>' + escapeHtml(courseLabel(r.courseId)) + '</td>' +
          '<td>' + escapeHtml(subjectLabel(r.courseId, r.subjectId)) + '</td>' +
          '<td>' + escapeHtml(folderLabel(r.folderId)) + '</td>' +
          '<td>' + escapeHtml(r.fileSize) + '</td>' +
          '<td><button type="button" class="btn btn-ghost btn-sm" data-delete="' + escapeAttr(r.id) + '">Delete</button></td>' +
          '</tr>';
      }).join('') +
      '</tbody></table>';
  }

  function resetUploadForm() {
    els.uploadForm.reset();
    fillSubjectSelect(els.fieldCourse, els.fieldSubject);
    populateFolderSelect();
    els.formStatus.hidden = true;
    if (els.coverPreview) {
      els.coverPreview.hidden = true;
      els.coverPreviewImg.removeAttribute('src');
    }
  }

  async function apiLogin() {
    var res = await fetch(API + '/login.php', { method: 'POST', body: new FormData(), credentials: 'same-origin' });
    var json = await res.json().catch(function () { return null; });
    return !!(res.ok && json && json.ok);
  }

  async function loadStructure() {
    try {
      var res = await fetch(API + '/structure.php?t=' + Date.now(), {
        credentials: 'same-origin',
        cache: 'no-store'
      });
      var json = await res.json();
      if (res.ok && json && json.ok && Array.isArray(json.courses)) {
        liveCourses = json.courses;
        saveLocalStructure(liveCourses);
        return true;
      }
    } catch (e) { /* local */ }
    liveCourses = getLocalStructure().courses || [];
    return false;
  }

  async function loadServerFolders() {
    try {
      var res = await fetch(API + '/folders.php?t=' + Date.now(), {
        credentials: 'same-origin',
        cache: 'no-store'
      });
      var json = await res.json();
      if (res.ok && json && json.ok && Array.isArray(json.folders)) {
        serverFolders = json.folders;
        return true;
      }
    } catch (e) { /* local */ }
    serverFolders = getLocalFolders();
    return false;
  }

  async function loadServerList() {
    var apiOk = false;
    try {
      var res = await fetch(API + '/resources.php?t=' + Date.now(), {
        credentials: 'same-origin',
        cache: 'no-store'
      });
      var json = await res.json();
      if (res.ok && json && json.ok && Array.isArray(json.resources)) {
        serverList = json.resources;
        apiOk = true;
      }
    } catch (e) {
      serverList = [];
    }

    var structureOk = await loadStructure();
    await loadServerFolders();
    useServer = apiOk || structureOk;
    if (!apiOk) serverList = getLocalCustom();
    return useServer;
  }

  function fileToDataUrl(file) {
    return new Promise(function (resolve, reject) {
      if (!file) return resolve('');
      if (file.size > 4.5 * 1024 * 1024) {
        reject(new Error('This file is too large for local browser testing. Upload the Library folder to Hostinger (PHP), then publish again — server accepts PDFs up to 40 MB.'));
        return;
      }
      var reader = new FileReader();
      reader.onload = function () { resolve(reader.result); };
      reader.onerror = function () { reject(new Error('Could not read file.')); };
      reader.readAsDataURL(file);
    });
  }

  function titleFromFileName(name) {
    return String(name || '').replace(/\.pdf$/i, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim() || 'Volume';
  }

  async function postAction(url, fields) {
    var body = new FormData();
    Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
    var res = await fetch(API + '/' + url, { method: 'POST', body: body, credentials: 'same-origin' });
    var json = await res.json().catch(function () { return null; });
    if (!res.ok || !json || !json.ok) {
      throw new Error((json && json.error) || 'Request failed.');
    }
    return json;
  }

  async function createCourseLocal(name, color) {
    var course = {
      id: 'crs-local-' + Date.now(),
      name: name,
      short: name,
      color: color || COLORS[liveCourses.length % COLORS.length],
      description: '',
      subjects: []
    };
    liveCourses.push(course);
    saveLocalStructure(liveCourses);
    return course;
  }

  async function createSubjectLocal(courseId, name) {
    var course = getCourse(courseId);
    if (!course) throw new Error('Course not found.');
    if (!course.subjects) course.subjects = [];
    var subject = { id: 'sub-local-' + Date.now() + '-' + slugify(name).slice(0, 20), name: name };
    course.subjects.push(subject);
    saveLocalStructure(liveCourses);
    return subject;
  }

  async function publishToServer(pdfFile, coverFile, title, folderId) {
    var body = new FormData();
    body.append('title', title);
    body.append('courseId', els.fieldCourse.value);
    body.append('subjectId', els.fieldSubject.value);
    body.append('description', els.fieldDescription.value.trim());
    if (folderId) body.append('folderId', folderId);
    body.append('pdf', pdfFile);
    if (coverFile) body.append('cover', coverFile);
    var res = await fetch(API + '/upload.php', { method: 'POST', body: body, credentials: 'same-origin' });
    var json = await res.json().catch(function () { return null; });
    if (!res.ok || !json || !json.ok) throw new Error((json && json.error) || 'Server upload failed.');
    return json.resource;
  }

  async function publishLocal(pdfFile, coverFile, title, folderId) {
    var resource = {
      id: 'custom-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6),
      title: title,
      courseId: els.fieldCourse.value,
      subjectId: els.fieldSubject.value,
      folderId: folderId || null,
      description: els.fieldDescription.value.trim(),
      fileSize: formatBytes(pdfFile.size),
      updatedAt: new Date().toISOString().slice(0, 10),
      cover: coverFile ? await fileToDataUrl(coverFile) : '',
      fileUrl: await fileToDataUrl(pdfFile),
      type: 'pdf'
    };
    var list = getLocalCustom();
    list.push(resource);
    saveLocalCustom(list);
    return resource;
  }

  function bind() {
    if (els.fieldCover) {
      els.fieldCover.addEventListener('change', function () {
        var file = els.fieldCover.files[0];
        if (!file || !els.coverPreview) return;
        els.coverPreviewImg.src = URL.createObjectURL(file);
        els.coverPreview.hidden = false;
      });
    }

    function onCourseChange() {
      fillSubjectSelect(els.fieldCourse, els.fieldSubject);
      populateFolderSelect();
    }
    if (els.fieldCourse) els.fieldCourse.addEventListener('change', onCourseChange);
    if (els.fieldSubject) els.fieldSubject.addEventListener('change', populateFolderSelect);
    if (els.folderCourse) {
      els.folderCourse.addEventListener('change', function () {
        fillSubjectSelect(els.folderCourse, els.folderSubject);
      });
    }
    if (els.subjectCourse) {
      els.subjectCourse.addEventListener('change', renderSubjectsTable);
    }

    if (els.btnAdminLogout) {
      els.btnAdminLogout.addEventListener('click', function () {
        if (window.SabeelAdminGate) {
          window.SabeelAdminGate.logoutAdmin();
        } else {
          window.location.href = '/pages/logout.php';
        }
      });
    }

    els.courseForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      els.btnCreateCourse.disabled = true;
      try {
        var name = els.courseName.value.trim();
        var color = els.courseColor.value || '#0B5ED7';
        if (!name) throw new Error('Course name is required.');
        if (useServer) {
          await postAction('structure.php', { action: 'create_course', name: name, color: color });
          await loadStructure();
        } else {
          await createCourseLocal(name, color);
        }
        els.courseForm.reset();
        els.courseColor.value = COLORS[liveCourses.length % COLORS.length];
        refreshAllSelects();
        setStatus(els.courseStatus, true, 'Course created.');
      } catch (err) {
        setStatus(els.courseStatus, false, err.message || 'Could not create course.');
      } finally {
        els.btnCreateCourse.disabled = false;
      }
    });

    els.subjectForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      els.btnCreateSubject.disabled = true;
      try {
        var courseId = els.subjectCourse.value;
        var name = els.subjectName.value.trim();
        if (!courseId) throw new Error('Create a course first, then select it.');
        if (!name) throw new Error('Subject name is required.');
        if (useServer) {
          await postAction('structure.php', { action: 'create_subject', courseId: courseId, name: name });
          await loadStructure();
        } else {
          await createSubjectLocal(courseId, name);
        }
        els.subjectName.value = '';
        refreshAllSelects();
        els.subjectCourse.value = courseId;
        renderSubjectsTable();
        setStatus(els.subjectStatus, true, 'Subject created.');
      } catch (err) {
        setStatus(els.subjectStatus, false, err.message || 'Could not create subject.');
      } finally {
        els.btnCreateSubject.disabled = false;
      }
    });

    els.folderForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      els.btnCreateFolder.disabled = true;
      try {
        var name = els.folderName.value.trim();
        var courseId = els.folderCourse.value;
        var subjectId = els.folderSubject.value;
        if (!name) throw new Error('Folder name is required.');
        if (!courseId || !subjectId) throw new Error('Create a course and subject first.');
        if (useServer) {
          await postAction('folders.php', { name: name, courseId: courseId, subjectId: subjectId });
          await loadServerFolders();
        } else {
          var folder = {
            id: 'fld-local-' + Date.now(),
            name: name,
            courseId: courseId,
            subjectId: subjectId,
            createdAt: new Date().toISOString().slice(0, 10)
          };
          var list = getLocalFolders();
          list.push(folder);
          saveLocalFolders(list);
          serverFolders = list;
        }
        els.folderForm.reset();
        fillSubjectSelect(els.folderCourse, els.folderSubject);
        populateFolderSelect();
        renderFoldersTable();
        setStatus(els.folderStatus, true, 'Book folder created.');
      } catch (err) {
        setStatus(els.folderStatus, false, err.message || 'Could not create folder.');
      } finally {
        els.btnCreateFolder.disabled = false;
      }
    });

    els.uploadForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      els.formStatus.hidden = true;
      els.btnPublish.disabled = true;
      els.btnPublish.textContent = 'Publishing…';
      try {
        var files = els.fieldPdf.files;
        if (!files || !files.length) throw new Error('Please choose at least one PDF file.');
        if (!els.fieldCourse.value || !els.fieldSubject.value) {
          throw new Error('Create and select a course and subject first.');
        }
        var folderId = els.fieldFolder.value || '';
        var baseTitle = els.fieldTitle.value.trim();
        var coverFile = els.fieldCover.files[0] || null;
        if (files.length > 1 && !folderId) {
          throw new Error('Multiple PDFs need one book folder. Create a folder in step 3, then select it.');
        }
        if (files.length === 1 && !folderId && !baseTitle) throw new Error('Title is required.');

        var published = 0;
        for (var i = 0; i < files.length; i++) {
          var pdfFile = files[i];
          var title = files.length === 1
            ? (baseTitle || titleFromFileName(pdfFile.name))
            : (baseTitle ? (baseTitle + ' — ' + titleFromFileName(pdfFile.name)) : titleFromFileName(pdfFile.name));
          var cover = i === 0 ? coverFile : null;
          if (useServer) {
            try { await publishToServer(pdfFile, cover, title, folderId); }
            catch (serverErr) {
              if (files.length > 1 || pdfFile.size > 4.5 * 1024 * 1024) throw serverErr;
              useServer = false;
              await publishLocal(pdfFile, cover, title, folderId);
            }
          } else {
            await publishLocal(pdfFile, cover, title, folderId);
          }
          published++;
        }
        if (useServer) await loadServerList();
        refreshAllSelects();
        renderFoldersTable();
        renderTable();
        resetUploadForm();
        setStatus(els.formStatus, true, published === 1 ? 'Published.' : published + ' volumes published.');
      } catch (err) {
        setStatus(els.formStatus, false, err.message || 'Could not publish.');
      } finally {
        els.btnPublish.disabled = false;
        els.btnPublish.textContent = 'Publish';
      }
    });

    els.coursesTableWrap.addEventListener('click', async function (e) {
      var btn = e.target.closest('[data-delete-course]');
      if (!btn) return;
      var id = btn.getAttribute('data-delete-course');
      if (!confirm('Delete this course? Subjects inside it will also be removed (PDFs/folders must be deleted first).')) return;
      try {
        if (useServer) {
          await postAction('structure.php', { action: 'delete_course', courseId: id });
          await loadStructure();
        } else {
          liveCourses = liveCourses.filter(function (c) { return c.id !== id; });
          saveLocalStructure(liveCourses);
        }
        refreshAllSelects();
      } catch (err) {
        alert(err.message || 'Could not delete course.');
      }
    });

    els.subjectsTableWrap.addEventListener('click', async function (e) {
      var btn = e.target.closest('[data-delete-subject]');
      if (!btn) return;
      var subjectId = btn.getAttribute('data-delete-subject');
      var courseId = btn.getAttribute('data-course');
      if (!confirm('Delete this subject?')) return;
      try {
        if (useServer) {
          await postAction('structure.php', { action: 'delete_subject', courseId: courseId, subjectId: subjectId });
          await loadStructure();
        } else {
          var course = getCourse(courseId);
          if (course) {
            course.subjects = (course.subjects || []).filter(function (s) { return s.id !== subjectId; });
            saveLocalStructure(liveCourses);
          }
        }
        refreshAllSelects();
        if (els.subjectCourse) els.subjectCourse.value = courseId;
        renderSubjectsTable();
      } catch (err) {
        alert(err.message || 'Could not delete subject.');
      }
    });

    els.foldersTableWrap.addEventListener('click', async function (e) {
      var btn = e.target.closest('[data-delete-folder]');
      if (!btn) return;
      var id = btn.getAttribute('data-delete-folder');
      if (!confirm('Delete this book folder? (It must be empty.)')) return;
      try {
        if (useServer && String(id).indexOf('fld-') === 0) {
          await postAction('folders.php', { _method: 'DELETE', id: id });
          await loadServerFolders();
        } else {
          saveLocalFolders(getLocalFolders().filter(function (f) { return f.id !== id; }));
          serverFolders = getLocalFolders();
        }
        populateFolderSelect();
        renderFoldersTable();
      } catch (err) {
        alert(err.message || 'Could not delete folder.');
      }
    });

    els.adminTableWrap.addEventListener('click', async function (e) {
      var btn = e.target.closest('[data-delete]');
      if (!btn) return;
      var id = btn.getAttribute('data-delete');
      if (!confirm('Delete this resource?')) return;
      try {
        if (useServer && String(id).indexOf('srv-') === 0) {
          await postAction('resources.php', { _method: 'DELETE', id: id });
          await loadServerList();
        } else {
          saveLocalCustom(getLocalCustom().filter(function (r) { return r.id !== id; }));
        }
        renderTable();
      } catch (err) {
        alert(err.message || 'Could not delete resource.');
      }
    });

    els.btnExport.addEventListener('click', function () {
      var blob = new Blob([JSON.stringify({
        courses: liveCourses,
        folders: currentFolders(),
        resources: currentList()
      }, null, 2)], { type: 'application/json' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'sabeel-library-export.json';
      a.click();
      URL.revokeObjectURL(a.href);
    });

    els.importFile.addEventListener('change', function () {
      var file = els.importFile.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function () {
        try {
          var parsed = JSON.parse(reader.result);
          var resources = Array.isArray(parsed) ? parsed : (parsed.resources || []);
          var folders = Array.isArray(parsed) ? [] : (parsed.folders || []);
          var courses = Array.isArray(parsed) ? [] : (parsed.courses || []);
          if (!Array.isArray(resources)) throw new Error('Invalid export file.');
          saveLocalCustom(resources);
          if (folders.length) saveLocalFolders(folders);
          if (courses.length) {
            liveCourses = courses;
            saveLocalStructure(courses);
          }
          useServer = false;
          serverFolders = getLocalFolders();
          refreshAllSelects();
          renderFoldersTable();
          renderTable();
          alert('Imported into this browser.');
        } catch (err) {
          alert(err.message || 'Invalid JSON file.');
        }
        els.importFile.value = '';
      };
      reader.readAsText(file);
    });

    var year = document.getElementById('year');
    if (year) year.textContent = String(new Date().getFullYear());
  }

  async function init() {
    [
      'viewAdminGate', 'viewAdminApp', 'btnAdminLogout',
      'courseForm', 'courseName', 'courseColor', 'btnCreateCourse', 'courseStatus', 'coursesTableWrap',
      'subjectForm', 'subjectCourse', 'subjectName', 'btnCreateSubject', 'subjectStatus', 'subjectsTableWrap',
      'folderForm', 'folderName', 'folderCourse', 'folderSubject', 'btnCreateFolder', 'folderStatus', 'foldersTableWrap',
      'uploadForm', 'fieldTitle', 'fieldCourse', 'fieldSubject', 'fieldFolder', 'fieldDescription',
      'fieldPdf', 'fieldCover', 'coverPreview', 'coverPreviewImg', 'btnPublish', 'formStatus',
      'adminTableWrap', 'btnExport', 'importFile', 'storageNote'
    ].forEach(function (id) { els[id] = $(id); });

    bind();
    showAdmin(false);

    if (window.SabeelAdminGate) {
      var session = await window.SabeelAdminGate.requireAdminOrRedirect('/library/admin.html');
      if (!session) return;
    } else if (auth.fetchUnifiedSession) {
      try { await auth.fetchUnifiedSession(); } catch (err) { /* ignore */ }
      if (!auth.isAdminAuthenticated()) {
        window.location.replace('/pages/login.php?redirect=/library/admin.html');
        return;
      }
    }

    try { await apiLogin(); } catch (err) { /* session cookie authorizes */ }
    showAdmin(true);
    await loadServerList();
    refreshAllSelects();
    renderFoldersTable();
    renderTable();
    if (els.storageNote) {
      els.storageNote.textContent = useServer
        ? 'Connected to server. Create courses → subjects → book folders → publish PDFs (up to 40 MB).'
        : 'Local preview only. Upload Library to Hostinger for full server storage.';
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
