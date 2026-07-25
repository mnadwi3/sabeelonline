<?php
/**
 * Admin only — manage blog categories
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_role('admin');

$page_title = 'Categories';
$page_mode = 'admin';

$error = '';

// Create / update / delete (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        exit('Invalid request. Please go back and try again.');
    }

    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db_run($pdo, "DELETE FROM categories WHERE id = ?", [$id]);
        header('Location: categories.php?msg=deleted');
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');

    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        $slug = $slug !== '' ? make_slug($slug) : make_slug($name);

        if ($id > 0) {
            $exists = db_one(
                $pdo,
                "SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1",
                [$slug, $id]
            );
            if ($exists) {
                $error = 'That slug already exists. Choose another name/slug.';
            } else {
                db_run($pdo, "UPDATE categories SET name = ?, slug = ? WHERE id = ?", [$name, $slug, $id]);
                header('Location: categories.php?msg=updated');
                exit;
            }
        } else {

            $exists = db_one($pdo, "SELECT id FROM categories WHERE slug = ? LIMIT 1", [$slug]);
            if ($exists) {
                $error = 'That slug already exists. Choose another name/slug.';
            } else {
                db_run($pdo, "INSERT INTO categories (name, slug) VALUES (?, ?)", [$name, $slug]);
                header('Location: categories.php?msg=created');
                exit;
            }
        }
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$editCat = $editId ? db_one($pdo, "SELECT * FROM categories WHERE id = ?", [$editId]) : null;
$categories = db_all($pdo, "SELECT * FROM categories ORDER BY name ASC");

$messages = [
    'created' => 'Category created.',
    'updated' => 'Category updated.',
    'deleted' => 'Category deleted.',
];
$msg = $_GET['msg'] ?? '';

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-error"><?php echo e($error); ?></div>
<?php endif; ?>
<?php if (isset($messages[$msg])): ?>
  <div class="alert alert-success"><?php echo e($messages[$msg]); ?></div>
<?php endif; ?>

<form class="form-card" method="post" action="categories.php" style="margin-bottom:1.25rem; max-width:720px;">
  <h2 style="margin-bottom:1rem;"><?php echo $editCat ? 'Edit Category' : 'Add Category'; ?></h2>
  <?php echo csrf_field(); ?>
  <input type="hidden" name="id" value="<?php echo $editCat ? (int) $editCat['id'] : 0; ?>">

  <div class="form-row">
    <div class="form-group">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required value="<?php echo e($editCat['name'] ?? ''); ?>">
    </div>
    <div class="form-group">
      <label for="slug">Slug</label>
      <input type="text" id="slug" name="slug" value="<?php echo e($editCat['slug'] ?? ''); ?>"
             placeholder="auto from name if blank">
    </div>
  </div>

  <div class="btn-group">
    <button class="btn btn-primary" type="submit"><?php echo $editCat ? 'Update' : 'Add Category'; ?></button>
    <?php if ($editCat): ?>
      <a class="btn" href="categories.php">Cancel</a>
    <?php endif; ?>
  </div>
</form>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Slug</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$categories): ?>
        <tr><td colspan="3" class="empty-state">No categories yet.</td></tr>
      <?php else: ?>
        <?php foreach ($categories as $cat): ?>
          <tr>
            <td><?php echo e($cat['name']); ?></td>
            <td><?php echo e($cat['slug']); ?></td>
            <td class="btn-group">
              <a class="btn btn-sm btn-primary" href="categories.php?edit=<?php echo (int) $cat['id']; ?>">Edit</a>
              <form method="post" action="categories.php" style="display:inline;" data-confirm="Delete this category?">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
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
