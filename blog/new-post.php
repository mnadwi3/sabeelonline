<?php
/**
 * Create a new blog post
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_staff();

$page_title = 'New Blog';
$page_mode = 'admin';

$error = '';
$form = [
    'title' => '',
    'slug' => '',
    'content' => '',
    'tags' => '',
    'meta_title' => '',
    'meta_description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'draft';

    $form['title'] = trim($_POST['title'] ?? '');
    $form['slug'] = trim($_POST['slug'] ?? '');
    $form['content'] = trim($_POST['content'] ?? '');
    $form['tags'] = trim($_POST['tags'] ?? '');
    $form['meta_title'] = trim($_POST['meta_title'] ?? '');
    $form['meta_description'] = trim($_POST['meta_description'] ?? '');

    if ($form['title'] === '' || $form['content'] === '') {
        $error = 'Title and Content are required.';
    } else {
        try {
            $slug = $form['slug'] !== '' ? make_slug($form['slug']) : make_slug($form['title']);
            $slug = unique_post_slug($pdo, $slug);

            $imagePath = null;
            if (!empty($_FILES['featured_image']['name'])) {
                $imagePath = upload_blog_image($_FILES['featured_image']);
            }

            // Decide status from button clicked
            // Teacher cannot publish directly
            if ($action === 'publish' && is_admin()) {
                $status = 'published';
                $publishedAt = date('Y-m-d H:i:s');
            } elseif ($action === 'submit') {
                $status = 'pending_review';
                $publishedAt = null;
            } elseif ($action === 'preview') {
                $status = 'draft';
                $publishedAt = null;
            } else {
                $status = 'draft';
                $publishedAt = null;
            }

            db_run(
                $pdo,
                "INSERT INTO posts
                 (teacher_id, category_id, title, slug, featured_image, short_description,
                  content, tags, meta_title, meta_description, status, published_at)
                 VALUES (?, NULL, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)",
                [
                    current_user_id(),
                    $form['title'],
                    $slug,
                    $imagePath,
                    $form['content'],
                    $form['tags'],
                    $form['meta_title'] !== '' ? $form['meta_title'] : $form['title'],
                    $form['meta_description'],
                    $status,
                    $publishedAt,
                ]
            );

            $newId = (int) $pdo->lastInsertId();

            if ($action === 'preview') {
                header('Location: edit-post.php?id=' . $newId . '&preview=1');
                exit;
            }

            header('Location: posts.php?msg=created');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>

<form class="form-card" method="post" action="new-post.php" enctype="multipart/form-data">
  <div class="form-group">
    <label for="title">Title *</label>
    <input type="text" id="title" name="title" required value="<?php echo e($form['title']); ?>">
  </div>

  <div class="form-group">
    <label for="slug">Slug (auto generated)</label>
    <input type="text" id="slug" name="slug" value="<?php echo e($form['slug']); ?>"
           placeholder="leave blank to auto-create from title">
  </div>

  <div class="form-group">
    <label for="featured_image">Featured Image</label>
    <input type="file" id="featured_image" name="featured_image" accept=".jpg,.jpeg,.png,.webp,image/*">
  </div>

  <div class="form-group">
    <label for="content">Content *</label>
    <textarea class="content-box" id="content" name="content" required><?php echo e($form['content']); ?></textarea>
  </div>

  <div class="form-group">
    <label for="tags">Tags (comma separated)</label>
    <input type="text" id="tags" name="tags" value="<?php echo e($form['tags']); ?>"
           placeholder="quran, arabic, beginners">
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="meta_title">Meta Title</label>
      <input type="text" id="meta_title" name="meta_title" value="<?php echo e($form['meta_title']); ?>">
    </div>
    <div class="form-group">
      <label for="meta_description">Meta Description</label>
      <input type="text" id="meta_description" name="meta_description" value="<?php echo e($form['meta_description']); ?>">
    </div>
  </div>

  <div class="btn-group">
    <button type="submit" name="action" value="draft" class="btn">Save Draft</button>
    <button type="submit" name="action" value="submit" class="btn btn-warning">Submit for Review</button>
    <?php if (is_admin()): ?>
      <button type="submit" name="action" value="publish" class="btn btn-success">Publish</button>
    <?php endif; ?>
    <button type="submit" name="action" value="preview" class="btn btn-primary">Preview</button>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
