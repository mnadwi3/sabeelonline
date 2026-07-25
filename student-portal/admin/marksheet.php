<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/marksheet.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
try {
    $bundle = load_result_bundle(db(), $id, false);
} catch (Throwable $e) {
    flash('error', 'Could not load marksheet. Please try again.');
    redirect('admin/results.php');
}
if (!$bundle) {
    flash('error', 'Result not found.');
    redirect('admin/results.php');
}

$pageTitle = 'Marksheet';
$active = 'results';
require __DIR__ . '/../includes/admin_header.php';
render_marksheet($bundle, true);
require __DIR__ . '/../includes/admin_footer.php';
