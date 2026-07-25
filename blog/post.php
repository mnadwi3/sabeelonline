<?php
/**
 * Public single blog post page
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$slug = trim($_GET['slug'] ?? '');

$post = db_one(
    $pdo,
    "SELECT p.*, t.name AS teacher_name, c.name AS category_name
     FROM posts p
     JOIN teachers t ON t.id = p.teacher_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.status = 'published'
     LIMIT 1",
    [$slug]
);

if (!$post) {
    http_response_code(404);
    $page_title = 'Post Not Found';
    $page_mode = 'public';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="card empty-state">This post was not found or is not published.</div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = $post['meta_title'] ?: $post['title'];
$meta_description = $post['meta_description'] ?: ($post['short_description'] ?: '');
$page_mode = 'public';

// Related posts: prefer same category, otherwise recent published posts
$related = [];
if (!empty($post['category_id'])) {
    $related = db_all(
        $pdo,
        "SELECT id, title, slug, featured_image, short_description, published_at
         FROM posts
         WHERE status = 'published'
           AND category_id = ?
           AND id != ?
         ORDER BY published_at DESC
         LIMIT 3",
        [(int) $post['category_id'], (int) $post['id']]
    );
}
if (!$related) {
    $related = db_all(
        $pdo,
        "SELECT id, title, slug, featured_image, short_description, published_at
         FROM posts
         WHERE status = 'published'
           AND id != ?
         ORDER BY published_at DESC
         LIMIT 3",
        [(int) $post['id']]
    );
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <article class="article">
    <?php if (!empty($post['featured_image'])): ?>
      <img class="article-image" src="<?php echo e($post['featured_image']); ?>" alt="<?php echo e($post['title']); ?>">
    <?php endif; ?>

    <div class="article-body">
      <h1><?php echo e($post['title']); ?></h1>

      <div class="post-meta">
        By <?php echo e(post_author_name($post)); ?>
        · <?php echo e(format_date($post['published_at'] ?: $post['created_at'])); ?>
        · <?php echo e($post['category_name'] ?: 'General'); ?>
        · <?php echo e(reading_time($post['content'])); ?>
      </div>

      <?php if (!empty($post['short_description'])): ?>
        <p style="margin-top:1rem; color:#4b5563;"><?php echo e($post['short_description']); ?></p>
      <?php endif; ?>

      <div class="article-content"><?php echo format_post_content($post['content']); ?></div>

      <?php if (!empty($post['tags'])): ?>
        <p class="post-meta" style="margin-top:1.5rem;">Tags: <?php echo e($post['tags']); ?></p>
      <?php endif; ?>
    </div>
  </article>

  <?php if ($related): ?>
    <section class="related-section">
      <h2>Related Posts</h2>
      <div class="posts-grid">
        <?php foreach ($related as $item): ?>
          <article class="post-card">
            <?php if (!empty($item['featured_image'])): ?>
              <img src="<?php echo e($item['featured_image']); ?>" alt="<?php echo e($item['title']); ?>">
            <?php else: ?>
              <img src="uploads/blog-images/woman.jpg" alt="">
            <?php endif; ?>
            <div class="post-card-body">
              <h2>
                <a href="post.php?slug=<?php echo e(urlencode($item['slug'])); ?>">
                  <?php echo e($item['title']); ?>
                </a>
              </h2>
              <p class="excerpt"><?php echo e($item['short_description']); ?></p>
              <a class="btn btn-sm btn-primary" href="post.php?slug=<?php echo e(urlencode($item['slug'])); ?>">Read More</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
