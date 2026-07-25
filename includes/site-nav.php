<?php
/**
 * Shared primary navigation (root-relative links).
 * Optional: $navActive = 'home'|'courses'|'student-services'|'blog'|'about'|'contact'
 */
$navActive = $navActive ?? '';
$is = static function (string $key) use ($navActive): string {
    return $navActive === $key ? ' active' : '';
};
$isLib = static function (string $key) use ($navActive): string {
    return $navActive === $key ? ' is-active-lib' : '';
};
?>
<nav class="main-nav" id="mainNav" aria-label="Primary">
  <ul class="nav-list">
    <li><a href="/#home" class="nav-link<?= $is('home') ?>">Home</a></li>
    <li><a href="/#courses" class="nav-link<?= $is('courses') ?>">Courses</a></li>
    <li class="has-sub">
      <a href="/#student-services" class="nav-link nav-parent<?= $is('student-services') ?>" aria-haspopup="true" aria-expanded="false">Student Services</a>
      <ul class="nav-sub" aria-label="Student Services submenu">
        <li><a href="/student-portal/" class="nav-link<?= $isLib('portal') ?>">Student Portal</a></li>
        <li><a href="/student-portal/public/" class="nav-link<?= $isLib('results') ?>">Results</a></li>
        <li><a href="/library/" class="nav-link<?= $isLib('library') ?>">Digital Library</a></li>
      </ul>
    </li>
    <li><a href="/blog/" class="nav-link<?= $is('blog') ?>">Blog</a></li>
    <li class="has-sub">
      <a href="/#about" class="nav-link nav-parent<?= $is('about') ?>" aria-haspopup="true" aria-expanded="false">About</a>
      <ul class="nav-sub" aria-label="About submenu">
        <li><a href="/#about" class="nav-link">About Us</a></li>
        <li><a href="/#teachers" class="nav-link">Our Team</a></li>
        <li><a href="/#testimonials" class="nav-link">Testimonials</a></li>
      </ul>
    </li>
    <li><a href="/#contact" class="nav-link<?= $is('contact') ?>">Contact</a></li>
  </ul>
</nav>
