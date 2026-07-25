<?php
/**
 * Dashboard — stats + recent blogs
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_staff();

$page_title = 'Dashboard';
$page_mode = 'admin';

$userId = current_user_id();

// Build counts (admin sees all, teacher sees only own posts)
if (is_admin()) {
    $total = db_one($pdo, "SELECT COUNT(*) AS c FROM posts")['c'] ?? 0;
    $published = db_one($pdo, "SELECT COUNT(*) AS c FROM posts WHERE status = 'published'")['c'] ?? 0;
    $pending = db_one($pdo, "SELECT COUNT(*) AS c FROM posts WHERE status = 'pending_review'")['c'] ?? 0;
    $drafts = db_one($pdo, "SELECT COUNT(*) AS c FROM posts WHERE status = 'draft'")['c'] ?? 0;

    $recent = db_all(
        $pdo,
        "SELECT p.*, t.name AS teacher_name
         FROM posts p
         JOIN teachers t ON t.id = p.teacher_id
         ORDER BY p.created_at DESC
         LIMIT 8"
    );
} else {
    $total = db_one($pdo, "SELECT COUNT(*) AS c FROM posts WHERE teacher_id = ?", [$userId])['c'] ?? 0;
    $published = db_one($pdo, "SELECT COUNT(*) AS c FROM posts WHERE teacher_id = ? AND status = 'published'", [$userId])['c'] ?? 0;
    $pending = db_one($pdo, "SELECT COUNT(*) AS c FROM posts WHERE teacher_id = ? AND status = 'pending_review'", [$userId])['c'] ?? 0;
    $drafts = db_one($pdo, "SELECT COUNT(*) AS c FROM posts WHERE teacher_id = ? AND status = 'draft'", [$userId])['c'] ?? 0;

    $recent = db_all(
        $pdo,
        "SELECT p.*, t.name AS teacher_name
         FROM posts p
         JOIN teachers t ON t.id = p.teacher_id
         WHERE p.teacher_id = ?
         ORDER BY p.created_at DESC
         LIMIT 8",
        [$userId]
    );
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card">
    <h3>Total Blogs</h3>
    <p><?php echo (int) $total; ?></p>
  </div>
  <div class="stat-card">
    <h3>Published</h3>
    <p><?php echo (int) $published; ?></p>
  </div>
  <div class="stat-card">
    <h3>Pending</h3>
    <p><?php echo (int) $pending; ?></p>
  </div>
  <div class="stat-card">
    <h3>Drafts</h3>
    <p><?php echo (int) $drafts; ?></p>
  </div>
</div>

<div class="card" style="margin-bottom:1rem;">
  <div class="btn-group">
    <a class="btn btn-primary" href="new-post.php">+ New Blog</a>
    <a class="btn" href="posts.php">View All Blogs</a>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Title</th>
        <?php if (is_admin()): ?><th>Teacher</th><?php endif; ?>
        <th>Status</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$recent): ?>
        <tr>
          <td colspan="<?php echo is_admin() ? 5 : 4; ?>" class="empty-state">No blogs yet. Create your first post!</td>
        </tr>
      <?php else: ?>
        <?php foreach ($recent as $post): ?>
          <tr>
            <td><?php echo e($post['title']); ?></td>
            <?php if (is_admin()): ?>
              <td><?php echo e($post['teacher_name']); ?></td>
            <?php endif; ?>
            <td>
              <span class="badge badge-<?php echo e($post['status']); ?>">
                <?php echo e(status_label($post['status'])); ?>
              </span>
            </td>
            <td><?php echo e(format_date($post['created_at'])); ?></td>
            <td class="btn-group">
              <a class="btn btn-sm btn-primary" href="edit-post.php?id=<?php echo (int) $post['id']; ?>">Edit</a>
              <form method="post" action="posts.php" style="display:inline;" data-confirm="Delete this blog post?">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
                <button class="btn btn-sm btn-danger" type="submit" name="action" value="delete">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
