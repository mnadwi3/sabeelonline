/**
 * Student Library access — Student ID only (same as Results).
 * Admin panels use /pages/login.php via SabeelAdminGate.
 */
(function (global) {
  'use strict';

  var cfg = global.LIBRARY_CONFIG;
  var STUDENT_API = '/api/student-access.php';
  var SESSION_API = 'api/session.php';
  var cachedUnified = null;
  var cachedStudent = null;

  function staffLoginUrl(redirectPath) {
    return '/pages/login.php?redirect=' + encodeURIComponent(redirectPath || '/library/');
  }

  function fetchStudentSession() {
    return fetch(STUDENT_API + '?t=' + Date.now(), {
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (res) { return res.json().catch(function () { return null; }); })
      .then(function (data) {
        cachedStudent = data && data.ok && data.authenticated ? data : null;
        return cachedStudent;
      })
      .catch(function () {
        cachedStudent = null;
        return null;
      });
  }

  function loginWithStudentId(studentId) {
    var body = new FormData();
    body.append('action', 'login');
    body.append('student_id', String(studentId || '').trim());
    return fetch(STUDENT_API, {
      method: 'POST',
      body: body,
      credentials: 'same-origin'
    })
      .then(function (res) { return res.json().then(function (data) { return { res: res, data: data }; }); })
      .then(function (pack) {
        if (pack.res.ok && pack.data && pack.data.ok && pack.data.authenticated) {
          cachedStudent = pack.data;
          localStorage.setItem(cfg.SESSION_KEY, JSON.stringify({
            authenticated: true,
            via: 'student_id',
            studentId: pack.data.student_id,
            name: pack.data.name || '',
            loggedInAt: new Date().toISOString()
          }));
          return { ok: true, data: pack.data };
        }
        return {
          ok: false,
          error: (pack.data && pack.data.error) || 'Invalid Student ID.'
        };
      })
      .catch(function () {
        return { ok: false, error: 'Could not verify Student ID. Try again.' };
      });
  }

  function fetchUnifiedSession() {
    return fetch(SESSION_API + '?t=' + Date.now(), {
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function (res) { return res.json().catch(function () { return null; }); })
      .then(function (data) {
        cachedUnified = data && data.ok ? data : null;
        return cachedUnified;
      })
      .catch(function () {
        cachedUnified = null;
        return null;
      });
  }

  function isAuthenticated() {
    if (cachedStudent && cachedStudent.authenticated) return true;
    if (cachedUnified && cachedUnified.authenticated && (cachedUnified.can_library || cachedUnified.is_admin)) {
      return true;
    }
    return false;
  }

  function getSession() {
    if (cachedStudent && cachedStudent.authenticated) {
      return {
        authenticated: true,
        via: 'student_id',
        studentId: cachedStudent.student_id,
        name: cachedStudent.name || ''
      };
    }
    if (cachedUnified && cachedUnified.authenticated) {
      return {
        authenticated: true,
        via: 'unified',
        name: cachedUnified.name || cachedUnified.username || ''
      };
    }
    try {
      return JSON.parse(localStorage.getItem(cfg.SESSION_KEY) || 'null');
    } catch (e) {
      return null;
    }
  }

  function logout() {
    localStorage.removeItem(cfg.SESSION_KEY);
    cachedStudent = null;
    var body = new FormData();
    body.append('action', 'logout');
    fetch(STUDENT_API, { method: 'POST', body: body, credentials: 'same-origin' }).catch(function () {});
  }

  function isAdminAuthenticated() {
    return !!(cachedUnified && cachedUnified.authenticated && (
      cachedUnified.is_admin ||
      cachedUnified.role === 'admin' ||
      cachedUnified.role === 'super_admin'
    ));
  }

  function logoutAdmin() {
    localStorage.removeItem(cfg.ADMIN_SESSION_KEY);
    cachedUnified = null;
  }

  function whatsAppUrl(message) {
    var text = encodeURIComponent(message || cfg.WHATSAPP_LIBRARY_MSG);
    return 'https://wa.me/' + cfg.WHATSAPP + '?text=' + text;
  }

  global.LibraryAuth = {
    isAuthenticated: isAuthenticated,
    getSession: getSession,
    loginWithStudentId: loginWithStudentId,
    logout: logout,
    isAdminAuthenticated: isAdminAuthenticated,
    logoutAdmin: logoutAdmin,
    whatsAppUrl: whatsAppUrl,
    fetchUnifiedSession: fetchUnifiedSession,
    fetchStudentSession: fetchStudentSession,
    staffLoginUrl: staffLoginUrl,
    getUnified: function () { return cachedUnified; },
    getStudent: function () { return cachedStudent; }
  };
})(window);
