<?php
/**
 * Shared footer — closes the layout opened in header.php
 */

if (!isset($page_mode)) {
    $page_mode = 'public';
}
?>

<?php if ($page_mode === 'admin'): ?>
      </main><!-- .admin-content -->
    </div><!-- .admin-main -->
  </div><!-- .admin-layout -->
  <script src="assets/js/blog.js"></script>
<?php else: ?>
  </main><!-- .blog-main -->

  <!-- Same footer as main website -->
  <footer class="site-footer">
    <div class="container footer-grid">
      <div class="footer-brand">
        <a href="/#home" class="logo logo-footer">
          <img src="../assets/logo-white.png" alt="Sabeel Us-Salam" class="logo-img" width="96" height="96">
        </a>
        <p>Learn Quran. Learn Islam. Transform Your Life.</p>
        <div class="social-icons">
          <a href="https://www.facebook.com/SabeelUsSalamOnline/" aria-label="Facebook" class="social-link" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>
          </a>
          <a href="#" aria-label="Instagram" class="social-link">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><rect x="2" y="2" width="20" height="20" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2"/></svg>
          </a>
          <a href="#" aria-label="YouTube" class="social-link">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M22.5 6.5a3 3 0 00-2.1-2.1C18.6 4 12 4 12 4s-6.6 0-8.4.4A3 3 0 001.5 6.5 31 31 0 001 12a31 31 0 00.5 5.5 3 3 0 002.1 2.1C5.4 20 12 20 12 20s6.6 0 8.4-.4a3 3 0 002.1-2.1A31 31 0 0023 12a31 31 0 00-.5-5.5zM10 15.5v-7l6 3.5-6 3.5z"/></svg>
          </a>
          <a href="https://wa.me/918979983149" aria-label="WhatsApp" class="social-link" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </a>
        </div>
      </div>
      <div>
        <h3>Quick Links</h3>
        <ul class="footer-links">
          <li><a href="/#home">Home</a></li>
          <li><a href="/#courses">Courses</a></li>
          <li><a href="/#teachers">Our Team</a></li>
          <li><a href="/#about">About Us</a></li>
          <li><a href="/#why-us">Why Choose Us</a></li>
          <li><a href="/#testimonials">Testimonials</a></li>
          <li><a href="index.php">Blog</a></li>
          <li><a href="../student-portal/public/">Download Results</a></li>
          <li><a href="../library/">Download Coursebooks</a></li>
          <li><a href="/#contact">Contact Us</a></li>
        </ul>
      </div>
      <div>
        <h3>Courses</h3>
        <ul class="footer-links">
          <li><a href="/#courses">Personal Tutoring</a></li>
          <li><a href="/#courses">Basic Urdu Course</a></li>
          <li><a href="/#courses">Short Term Alimiyyat</a></li>
          <li><a href="/#courses">Advanced Arabic Diploma</a></li>
          <li><a href="/#courses">Translation of The Quran</a></li>
          <li><a href="/#courses">Elementary Course In Islamic Education</a></li>
        </ul>
      </div>
      <div>
        <h3>Legal</h3>
        <ul class="footer-links">
          <li><a href="/#contact">Privacy Policy</a></li>
          <li><a href="/#contact">Terms of Service</a></li>
          <li><a href="https://wa.me/918979983149" target="_blank" rel="noopener noreferrer">WhatsApp +91-8979983149</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>&copy; <?php echo date('Y'); ?> Sabeel Us Salaam Online. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <div class="float-actions" aria-label="Quick actions">
    <a href="https://wa.me/918979983149?text=Assalamu%20Alaikum%2C%20I%20would%20like%20to%20know%20more%20about%20Sabeel%20Us-Salam%20Online." class="float-btn float-wa" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp +91-8979983149">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    </a>
  </div>

  <button type="button" class="back-top" id="backTop" aria-label="Back to top">↑</button>

  <!-- Main site JS (menu) + blog JS -->
  <script src="../script.js?v=20260726navDrop1" defer></script>
  <script src="assets/js/blog.js" defer></script>
<?php endif; ?>
</body>
</html>
