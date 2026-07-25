/**
 * Shared site navigation injector for static HTML pages.
 * Place an empty <nav class="main-nav" id="mainNav" data-site-nav="library|portal|..."></nav>
 * or call window.SabeelSiteNav.render(activeKey).
 */
(function () {
  'use strict';

  function link(href, label, extraClass) {
    return '<a href="' + href + '" class="nav-link' + (extraClass || '') + '">' + label + '</a>';
  }

  function render(active) {
    active = active || '';
    var a = function (key) { return active === key ? ' active' : ''; };
    var lib = function (key) { return active === key ? ' is-active-lib' : ''; };

    return (
      '<ul class="nav-list">' +
        '<li>' + link('/#home', 'Home', a('home')) + '</li>' +
        '<li>' + link('/#courses', 'Courses', a('courses')) + '</li>' +
        '<li class="has-sub">' +
          '<a href="/student-portal/public/" class="nav-link nav-parent' + a('student-services') + '" aria-haspopup="true" aria-expanded="false">Student Services</a>' +
          '<ul class="nav-sub" aria-label="Student Services submenu">' +
            '<li>' + link('/student-portal/public/', 'Download Results', lib('results')) + '</li>' +
            '<li>' + link('/library/', 'Download Coursebooks', lib('library')) + '</li>' +
          '</ul>' +
        '</li>' +
        '<li>' + link('/blog/', 'Blog', a('blog')) + '</li>' +
        '<li class="has-sub">' +
          '<a href="/#about" class="nav-link nav-parent' + a('about') + '" aria-haspopup="true" aria-expanded="false">About</a>' +
          '<ul class="nav-sub" aria-label="About submenu">' +
            '<li>' + link('/#about', 'About Us') + '</li>' +
            '<li>' + link('/#teachers', 'Our Team') + '</li>' +
            '<li>' + link('/#testimonials', 'Testimonials') + '</li>' +
          '</ul>' +
        '</li>' +
        '<li>' + link('/#contact', 'Contact', a('contact')) + '</li>' +
      '</ul>'
    );
  }

  function mount() {
    var nav = document.getElementById('mainNav');
    if (!nav) return;
    var active = nav.getAttribute('data-site-nav') || '';
    nav.innerHTML = render(active);
    nav.setAttribute('aria-label', 'Primary');
  }

  window.SabeelSiteNav = { render: render, mount: mount };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();
