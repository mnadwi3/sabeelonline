/**
 * Homepage Latest Blog — show two newest published posts.
 */
(function () {
  'use strict';

  const API_URL = 'api/latest-blog.php';
  const grid = document.getElementById('latestBlogGrid');
  if (!grid) return;

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function cardHtml(post, index) {
    const delay = index === 1 ? ' delay-1' : '';
    const meta = [post.category, post.date].filter(Boolean).join(' · ');
    return (
      '<article class="home-blog-card reveal' + delay + '">' +
        '<a class="home-blog-media" href="' + escapeHtml(post.url) + '">' +
          '<img src="' + escapeHtml(post.image) + '" alt="' + escapeHtml(post.title) + '" loading="lazy" decoding="async" width="640" height="360">' +
        '</a>' +
        '<div class="home-blog-body">' +
          (meta ? '<p class="home-blog-meta">' + escapeHtml(meta) + '</p>' : '') +
          '<h3><a href="' + escapeHtml(post.url) + '">' + escapeHtml(post.title) + '</a></h3>' +
          (post.excerpt ? '<p class="home-blog-excerpt">' + escapeHtml(post.excerpt) + '</p>' : '') +
          (post.author ? '<p class="home-blog-author">By ' + escapeHtml(post.author) + '</p>' : '') +
          '<a class="btn btn-outline btn-sm" href="' + escapeHtml(post.url) + '">Read More</a>' +
        '</div>' +
      '</article>'
    );
  }

  function finish() {
    if (window.SabeelUI && typeof window.SabeelUI.observeReveal === 'function') {
      window.SabeelUI.observeReveal(grid);
    } else {
      grid.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
    }
  }

  async function load() {
    try {
      const res = await fetch(API_URL + '?t=' + Date.now(), { cache: 'no-store' });
      const data = await res.json().catch(() => ({}));
      const posts = data && data.ok && Array.isArray(data.posts) ? data.posts.slice(0, 2) : [];
      if (!posts.length) {
        grid.innerHTML = '<p class="home-blog-empty">New articles will appear here soon.</p>';
        finish();
        return;
      }
      grid.innerHTML = posts.map(cardHtml).join('');
      finish();
    } catch (_) {
      grid.innerHTML = '<p class="home-blog-empty">Visit the blog to read the latest articles.</p>';
      finish();
    }
  }

  document.addEventListener('DOMContentLoaded', load);
})();
