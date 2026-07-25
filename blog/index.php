<?php
/**
 * Public blog home — shows published posts only
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$page_title = 'Blog';
$page_mode = 'public';

$posts = db_all(
    $pdo,
    "SELECT p.*, t.name AS teacher_name, c.name AS category_name
     FROM posts p
     JOIN teachers t ON t.id = p.teacher_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'published'
     ORDER BY p.published_at DESC, p.created_at DESC"
);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="page-hero">
    <h1>Our Blogs</h1>
    <p>Articles and learning notes from Sabeel Us Salaam Online teachers.</p>
  </div>

  <?php if (!$posts): ?>
    <div class="card empty-state">No published posts yet. Please check back soon.</div>
  <?php else: ?>
    <div class="posts-grid">
      <?php foreach ($posts as $post): ?>
        <article class="post-card">
          <?php if (!empty($post['featured_image'])): ?>
            <img src="<?php echo e($post['featured_image']); ?>" alt="<?php echo e($post['title']); ?>">
          <?php else: ?>
            <img src="/assets/images/woman.jpg" alt="">
          <?php endif; ?>
          <div class="post-card-body">
            <div class="post-meta">
              <?php echo e($post['category_name'] ?: 'General'); ?>
              · <?php echo e(format_date($post['published_at'] ?: $post['created_at'])); ?>
            </div>
            <h2>
              <a href="post.php?slug=<?php echo e(urlencode($post['slug'])); ?>">
                <?php echo e($post['title']); ?>
              </a>
            </h2>
            <p class="excerpt">
              <?php echo e($post['short_description'] ?: substr(strip_tags($post['content']), 0, 120) . '...'); ?>
            </p>
            <div class="post-meta">By <?php echo e($post['teacher_name']); ?></div>
            <a class="btn btn-sm btn-primary read-more-btn" href="post.php?slug=<?php echo e(urlencode($post['slug'])); ?>">Read More</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
