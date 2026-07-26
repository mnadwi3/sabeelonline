/**
 * Access-control helpers for the Student Digital Library.
 * Prefers unified SABEELAUTH (/pages/login.php); legacy codes remain as fallback.
 */
(function (global) {
  'use strict';

  var cfg = global.LIBRARY_CONFIG;
  var SESSION_API = 'api/session.php';
  var cachedUnified = null;

  function normalizeCode(code) {
    return String(code || '').trim().toUpperCase();
  }

  function getValidCodes() {
    if (!cfg || !cfg.ACCESS_CODES) return [];
    return cfg.ACCESS_CODES
      .map(normalizeCode)
      .filter(function (code) { return !!code; });
  }

  function staffLoginUrl(redirectPath) {
    var path = redirectPath || '/library/';
    return '/pages/login.php?redirect=' + encodeURIComponent(path);
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
    if (cachedUnified && cachedUnified.authenticated && cachedUnified.can_library) {
      return true;
    }
    try {
      var raw = localStorage.getItem(cfg.SESSION_KEY);
      if (!raw) return false;
      var session = JSON.parse(raw);
      return !!(session && session.authenticated === true && session.code);
    } catch (e) {
      return false;
    }
  }

  function getSession() {
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

  function verifyAccessCode(code) {
    var normalized = normalizeCode(code);
    if (!normalized) return false;
    return getValidCodes().indexOf(normalized) !== -1;
  }

  function login(code) {
    var normalized = normalizeCode(code);
    if (!verifyAccessCode(normalized)) return false;
    var session = {
      authenticated: true,
      code: normalized,
      loggedInAt: new Date().toISOString()
    };
    localStorage.setItem(cfg.SESSION_KEY, JSON.stringify(session));
    return true;
  }

  function logout() {
    localStorage.removeItem(cfg.SESSION_KEY);
    cachedUnified = null;
  }

  function isAdminAuthenticated() {
    if (cachedUnified && cachedUnified.authenticated && (cachedUnified.can_library || cachedUnified.can_courses)) {
      return true;
    }
    try {
      var raw = localStorage.getItem(cfg.ADMIN_SESSION_KEY);
      if (!raw) return false;
      var session = JSON.parse(raw);
      return !!(session && session.authenticated === true);
    } catch (e) {
      return false;
    }
  }

  function verifyAdminCode(code) {
    var normalized = normalizeCode(code);
    if (!normalized || !cfg) return false;
    var list = [];
    if (cfg.ADMIN_CODES && cfg.ADMIN_CODES.length) {
      list = cfg.ADMIN_CODES;
    } else if (cfg.ADMIN_CODE) {
      list = [cfg.ADMIN_CODE];
    }
    for (var i = 0; i < list.length; i++) {
      if (normalizeCode(list[i]) === normalized) return true;
    }
    return false;
  }

  function loginAdmin(code) {
    if (!verifyAdminCode(code)) return false;
    localStorage.setItem(cfg.ADMIN_SESSION_KEY, JSON.stringify({
      authenticated: true,
      loggedInAt: new Date().toISOString()
    }));
    return true;
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
    verifyAccessCode: verifyAccessCode,
    login: login,
    logout: logout,
    isAdminAuthenticated: isAdminAuthenticated,
    verifyAdminCode: verifyAdminCode,
    loginAdmin: loginAdmin,
    logoutAdmin: logoutAdmin,
    whatsAppUrl: whatsAppUrl,
    fetchUnifiedSession: fetchUnifiedSession,
    staffLoginUrl: staffLoginUrl,
    getUnified: function () { return cachedUnified; }
  };
})(window);
