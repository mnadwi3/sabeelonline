/**
 * Single Admin session helper for Hub / Library / Courses / Admissions.
 * Requires /pages/login.php (SABEELAUTH). No access codes.
 */
(function (global) {
  'use strict';

  var SESSION_API = '/library/api/session.php';
  var LOGIN_PATH = '/pages/login.php';
  var LOGOUT_PATH = '/pages/logout.php';

  function loginUrl(redirectPath) {
    var path = redirectPath || (global.location.pathname + global.location.search);
    if (path.charAt(0) !== '/') path = '/' + path;
    return LOGIN_PATH + '?redirect=' + encodeURIComponent(path);
  }

  function checkAdminSession() {
    return fetch(SESSION_API + '?t=' + Date.now(), {
      credentials: 'same-origin',
      cache: 'no-store',
    })
      .then(function (res) {
        return res.json().catch(function () {
          return null;
        });
      })
      .then(function (data) {
        if (
          data &&
          data.ok &&
          data.authenticated &&
          (data.is_admin || data.role === 'admin' || data.role === 'super_admin')
        ) {
          return data;
        }
        return null;
      })
      .catch(function () {
        return null;
      });
  }

  /** If not admin, redirect to the one login page. Returns session data or null. */
  function requireAdminOrRedirect(redirectPath) {
    return checkAdminSession().then(function (session) {
      if (session) return session;
      global.location.replace(loginUrl(redirectPath || global.location.pathname));
      return null;
    });
  }

  function logoutAdmin() {
    return fetch('/library/api/logout.php', {
      method: 'POST',
      credentials: 'same-origin',
    })
      .catch(function () {})
      .then(function () {
        global.location.href = LOGOUT_PATH;
      });
  }

  global.SabeelAdminGate = {
    checkAdminSession: checkAdminSession,
    requireAdminOrRedirect: requireAdminOrRedirect,
    loginUrl: loginUrl,
    logoutAdmin: logoutAdmin,
  };
})(window);
