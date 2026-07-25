<?php
/**
 * Blog list page
 * - Teacher: own blogs
 * - Admin: all blogs + publish / reject / delete
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_staff();

$page_title = is_admin() ? 'All Blogs' : 'My Blogs';
$page_mode = 'admin';

// Handle actions via POST + CSRF (safer than GET links)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        exit('Invalid request. Please go back and try again.');
    }

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $post = $id > 0 ? db_one($pdo, "SELECT * FROM posts WHERE id = ? LIMIT 1", [$id]) : null;

    if ($post) {
        $canManage = is_admin() || (is_teacher() && (int) $post['teacher_id'] === current_user_id());

        if ($action === 'delete' && $canManage) {
            if (!empty($post['featured_image'])) {
                $path = __DIR__ . '/' . $post['featured_image'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            db_run($pdo, "DELETE FROM posts WHERE id = ?", [$id]);
            header('Location: posts.php?msg=deleted');
            exit;
        }

        if (is_admin() && $action === 'publish') {
            db_run(
                $pdo,
                "UPDATE posts SET status = 'published', published_at = COALESCE(published_at, NOW()) WHERE id = ?",
                [$id]
            );
            header('Location: posts.php?msg=published');
            exit;
        }

        if (is_admin() && $action === 'reject') {
            db_run($pdo, "UPDATE posts SET status = 'rejected' WHERE id = ?", [$id]);
            header('Location: posts.php?msg=rejected');
            exit;
        }
    }

    header('Location: posts.php');
    exit;
}

if (is_admin()) {
    $posts = db_all(
        $pdo,
        "SELECT p.*, t.name AS teacher_name, c.name AS category_name
         FROM posts p
         JOIN teachers t ON t.id = p.teacher_id
         LEFT JOIN categories c ON c.id = p.category_id
         ORDER BY p.created_at DESC"
    );
} else {
    $posts = db_all(
        $pdo,
        "SELECT p.*, t.name AS teacher_name, c.name AS category_name
         FROM posts p
         JOIN teachers t ON t.id = p.teacher_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.teacher_id = ?
         ORDER BY p.created_at DESC",
        [current_user_id()]
    );
}

$messages = [
    'created' => 'Blog created successfully.',
    'updated' => 'Blog updated successfully.',
    'deleted' => 'Blog deleted.',
    'published' => 'Blog published.',
    'rejected' => 'Blog rejected.',
];
$msg = $_GET['msg'] ?? '';
$colspan = is_admin() ? 6 : 5;

require_once __DIR__ . '/includes/header.php';
?>

<?php if (isset($messages[$msg])): ?>
  <div class="alert alert-success"><?php echo e($messages[$msg]); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:1rem;">
  <a class="btn btn-primary" href="new-post.php">+ New Blog</a>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Title</th>
        <?php if (is_admin()): ?><th>Teacher</th><?php endif; ?>
        <th>Category</th>
        <th>Status</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$posts): ?>
        <tr><td colspan="<?php echo $colspan; ?>" class="empty-state">No blog posts found.</td></tr>
      <?php else: ?>
        <?php foreach ($posts as $post): ?>
          <tr>
            <td><?php echo e($post['title']); ?></td>
            <?php if (is_admin()): ?>
              <td><?php echo e(post_author_name($post)); ?></td>
            <?php endif; ?>
            <td><?php echo e($post['category_name'] ?: '-'); ?></td>
            <td>
              <span class="badge badge-<?php echo e($post['status']); ?>">
                <?php echo e(status_label($post['status'])); ?>
              </span>
            </td>
            <td><?php echo e(format_date($post['created_at'])); ?></td>
            <td>
              <div class="btn-group">
                <a class="btn btn-sm btn-primary" href="edit-post.php?id=<?php echo (int) $post['id']; ?>">Edit</a>

                <?php if (is_admin() && $post['status'] === 'pending_review'): ?>
                  <form method="post" action="posts.php" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
                    <button class="btn btn-sm btn-success" type="submit" name="action" value="publish">Publish</button>
                  </form>
                  <form method="post" action="posts.php" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
                    <button class="btn btn-sm btn-warning" type="submit" name="action" value="reject">Reject</button>
                  </form>
                <?php endif; ?>

                <form method="post" action="posts.php" style="display:inline;" data-confirm="Delete this blog post?">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
                  <button class="btn btn-sm btn-danger" type="submit" name="action" value="delete">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
