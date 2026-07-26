/**
 * Library portal configuration
 * Students sign in with their portal Student ID (same as Download Results).
 * ACCESS_CODES remain only as an emergency fallback.
 */
window.LIBRARY_CONFIG = {
  /* Legacy fallback codes only (prefer Student ID from Results Admin). */
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
