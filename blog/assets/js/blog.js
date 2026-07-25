/**
 * Simple Blog CMS JavaScript
 * - Auto slug from title
 * - Confirm before delete
 */

document.addEventListener('DOMContentLoaded', function () {
  // Auto-generate slug while typing title (only if slug field is empty or synced)
  var titleInput = document.getElementById('title');
  var slugInput = document.getElementById('slug');

  if (titleInput && slugInput) {
    var slugTouched = slugInput.dataset.manual === '1';

    slugInput.addEventListener('input', function () {
      slugTouched = true;
      slugInput.dataset.manual = '1';
    });

    titleInput.addEventListener('input', function () {
      if (slugTouched && slugInput.value.trim() !== '') {
        return;
      }
      slugInput.value = makeSlug(titleInput.value);
    });
  }

  // Confirm delete links/buttons/forms
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener(el.tagName === 'FORM' ? 'submit' : 'click', function (e) {
      var message = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(message)) {
        e.preventDefault();
      }
    });
  });
});

/**
 * Convert text to a simple slug
 */
function makeSlug(text) {
  return String(text || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/[\s-]+/g, '-')
    .replace(/^-+|-+$/g, '');
}
