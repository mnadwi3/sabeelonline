<?php
/**
 * Edit an existing blog post
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_staff();

$page_title = 'Edit Blog';
$page_mode = 'admin';

$id = (int) ($_GET['id'] ?? 0);
$post = db_one($pdo, "SELECT * FROM posts WHERE id = ? LIMIT 1", [$id]);

if (!$post) {
    exit('Post not found.');
}

// Teacher can edit only own posts
if (is_teacher() && (int) $post['teacher_id'] !== current_user_id()) {
    header('Location: posts.php');
    exit;
}

$error = '';
$info = '';

if (isset($_GET['preview'])) {
    $info = 'Draft saved. You can keep editing below, or open the public page after it is published.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'draft';

    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDesc = trim($_POST['meta_description'] ?? '');

    if ($title === '' || $content === '') {
        $error = 'Title and Content are required.';
    } else {
        try {
            $slug = $slug !== '' ? make_slug($slug) : make_slug($title);
            $slug = unique_post_slug($pdo, $slug, $id);

            $imagePath = $post['featured_image'];
            if (!empty($_FILES['featured_image']['name'])) {
                $imagePath = upload_blog_image($_FILES['featured_image']);
            }

            $status = $post['status'];
            $publishedAt = $post['published_at'];

            if ($action === 'publish' && is_admin()) {
                $status = 'published';
                $publishedAt = $publishedAt ?: date('Y-m-d H:i:s');
            } elseif ($action === 'reject' && is_admin()) {
                $status = 'rejected';
            } elseif ($action === 'submit') {
                // Teachers cannot unpublish a live post
                if (!(is_teacher() && $post['status'] === 'published')) {
                    $status = 'pending_review';
                }
            } elseif ($action === 'draft') {
                if (!(is_teacher() && $post['status'] === 'published')) {
                    $status = 'draft';
                }
            }

            db_run(
                $pdo,
                "UPDATE posts SET
                    title = ?, slug = ?, featured_image = ?,
                    content = ?, tags = ?,
                    meta_title = ?, meta_description = ?, status = ?, published_at = ?
                 WHERE id = ?",
                [
                    $title,
                    $slug,
                    $imagePath,
                    $content,
                    $tags,
                    $metaTitle !== '' ? $metaTitle : $title,
                    $metaDesc,
                    $status,
                    $publishedAt,
                    $id,
                ]
            );

            header('Location: posts.php?msg=updated');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // refresh local values after failed save
    $post = array_merge($post, [
        'title' => $title ?? $post['title'],
        'slug' => $slug ?? $post['slug'],
        'content' => $content ?? $post['content'],
        'tags' => $tags ?? $post['tags'],
        'meta_title' => $metaTitle ?? $post['meta_title'],
        'meta_description' => $metaDesc ?? $post['meta_description'],
    ]);
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>
<?php if ($info): ?>
  <div class="alert alert-info"><?php echo e($info); ?></div>
<?php endif; ?>

<form class="form-card" method="post" action="edit-post.php?id=<?php echo $id; ?>" enctype="multipart/form-data">
  <div class="form-group">
    <label for="title">Title *</label>
    <input type="text" id="title" name="title" required value="<?php echo e($post['title']); ?>">
  </div>

  <div class="form-group">
    <label for="slug">Slug</label>
    <input type="text" id="slug" name="slug" data-manual="1" value="<?php echo e($post['slug']); ?>">
  </div>

  <div class="form-group">
    <label for="featured_image">Featured Image</label>
    <input type="file" id="featured_image" name="featured_image" accept=".jpg,.jpeg,.png,.webp,image/*">
    <?php if (!empty($post['featured_image'])): ?>
      <img class="preview-image" src="<?php echo e($post['featured_image']); ?>" alt="Current featured image">
    <?php endif; ?>
    <p class="post-meta" style="margin-top:0.6rem;">
      Current status:
      <span class="badge badge-<?php echo e($post['status']); ?>">
        <?php echo e(status_label($post['status'])); ?>
      </span>
    </p>
  </div>

  <div class="form-group">
    <label for="content">Content *</label>
    <textarea class="content-box" id="content" name="content" required><?php echo e($post['content']); ?></textarea>
  </div>

  <div class="form-group">
    <label for="tags">Tags</label>
    <input type="text" id="tags" name="tags" value="<?php echo e($post['tags']); ?>">
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="meta_title">Meta Title</label>
      <input type="text" id="meta_title" name="meta_title" value="<?php echo e($post['meta_title']); ?>">
    </div>
    <div class="form-group">
      <label for="meta_description">Meta Description</label>
      <input type="text" id="meta_description" name="meta_description" value="<?php echo e($post['meta_description']); ?>">
    </div>
  </div>

  <div class="btn-group">
    <button type="submit" name="action" value="draft" class="btn">Save Draft</button>
    <button type="submit" name="action" value="submit" class="btn btn-warning">Submit for Review</button>
    <?php if (is_admin()): ?>
      <button type="submit" name="action" value="publish" class="btn btn-success">Publish</button>
      <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
    <?php endif; ?>
    <?php if ($post['status'] === 'published'): ?>
      <a class="btn btn-primary" href="post.php?slug=<?php echo e(urlencode($post['slug'])); ?>" target="_blank">View Live</a>
    <?php endif; ?>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
