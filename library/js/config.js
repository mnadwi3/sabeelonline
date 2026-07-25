/**
 * Library portal configuration
 * Change ACCESS_CODES before going live. Client-side checks deter casual access
 * only — for production-grade security, verify codes on a server.
 */
window.LIBRARY_CONFIG = {
  /* Student access codes (case-insensitive). Any one works. */
  ACCESS_CODES: [
    'student@sabeel',
    'SABEEL2026',
    'STUDENT001'
  ],

  /* Admin codes (any one works). Keep in sync with library/api/bootstrap.php */
  ADMIN_CODE: 'admin@sabeel',
  ADMIN_CODES: ['admin@sabeel', 'ADMIN-SABEEL'],

  /* LocalStorage keys */
  SESSION_KEY: 'sabeel_library_session',
  ADMIN_SESSION_KEY: 'sabeel_library_admin',
  RESOURCES_KEY: 'sabeel_library_custom_resources',

  /* Contact */
  WHATSAPP: '918979983149',
  WHATSAPP_LIBRARY_MSG: 'Assalamu Alaikum, I am an enrolled student and need a Library Access Code for Sabeel Us Salaam Online.',

  SITE_NAME: 'Sabeel Us Salaam Online'
};
