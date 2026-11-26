<?php
/**
 * MADAM CAKES — admin product list.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

if (is_post()) {
    require_csrf();
    $productId = (int) post('product_id');
    $action    = (string) post('action');

    if ($action === 'toggle') {
        Database::execute('UPDATE products SET is_active = 1 - is_active WHERE id = ?', [$productId]);
        log_admin_action('product.toggle', 'product', $productId, 'Changed availability.');
        flash('success', 'Availability updated.');
    } elseif ($action === 'delete') {
        Database::execute('DELETE FROM products WHERE id = ?', [$productId]);
        log_admin_action('product.delete', 'product', $productId, 'Deleted a product.');
        flash('success', 'Product deleted.');
    }
    redirect('admin/products.php');
}

$filter = (string) get('filter');
$where = '1 = 1';
if ($filter === 'active') {
    $where .= ' AND p.is_active = 1';
} elseif ($filter === 'inactive') {
    $where .= ' AND p.is_active = 0';
} elseif ($filter === 'unpriced') {
    $where .= " AND p.base_price = 0 AND NOT EXISTS (SELECT 1 FROM product_sizes ps2 WHERE ps2.product_id = p.id AND ps2.is_active = 1 AND ps2.price > 0)";
}

$products = Database::fetchAll(
    'SELECT p.*, c.name AS category_name,
            (SELECT MIN(ps.price) FROM product_sizes ps WHERE ps.product_id = p.id AND ps.is_active = 1 AND ps.price > 0) AS min_size_price,
            (SELECT COUNT(*) FROM product_images pi WHERE pi.product_id = p.id) AS image_count
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE ' . $where . '
     ORDER BY p.is_active DESC, p.created_at DESC, p.id DESC'
);

$adminTitle  = 'Products';
$adminActive = 'products';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-toolbar">
  <nav class="filter-chips" aria-label="Filter products">
    <a class="filter-chip<?= $filter === '' ? ' is-active' : '' ?>" href="<?= e(url('admin/products.php')) ?>">All</a>
    <a class="filter-chip<?= $filter === 'active' ? ' is-active' : '' ?>" href="<?= e(url('admin/products.php?filter=active')) ?>">Active</a>
    <a class="filter-chip<?= $filter === 'inactive' ? ' is-active' : '' ?>" href="<?= e(url('admin/products.php?filter=inactive')) ?>">Hidden</a>
    <a class="filter-chip<?= $filter === 'unpriced' ? ' is-active' : '' ?>" href="<?= e(url('admin/products.php?filter=unpriced')) ?>">Without a price</a>
  </nav>
  <a class="btn btn-primary btn-small" href="<?= e(url('admin/product-edit.php')) ?>">Add a cake</a>
</div>

<section class="admin-panel">
  <div class="panel-head"><h2><?= count($products) ?> cake<?= count($products) === 1 ? '' : 's' ?></h2></div>

  <?php if ($products): ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Cake</th><th>Category</th><th>Price</th><th>Photos</th><th>Visible</th><th>Featured</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($products as $product): ?>
            <tr>
              <td>
                <a href="<?= e(url('admin/product-edit.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a>
                <?php if ((float) $product['base_price'] <= 0 && $product['min_size_price'] === null): ?>
                  <br><small class="text-danger">No price set</small>
                <?php endif; ?>
              </td>
              <td><?= e((string) ($product['category_name'] ?? '—')) ?></td>
              <td>
                <?php if ($product['min_size_price'] !== null): ?>
                  From <?= e(money($product['min_size_price'])) ?>
                <?php elseif ((float) $product['base_price'] > 0): ?>
                  <?= e(money($product['base_price'])) ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td><?= (int) $product['image_count'] ?></td>
              <td>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                  <button type="submit" class="pill <?= (int) $product['is_active'] === 1 ? 'pill-success' : 'pill-muted' ?>">
                    <?= (int) $product['is_active'] === 1 ? 'Live' : 'Hidden' ?>
                  </button>
                </form>
              </td>
              <td><?= (int) $product['is_featured'] === 1 ? 'Yes' : '—' ?></td>
              <td class="table-actions">
                <a class="btn btn-ghost btn-small" href="<?= e(url('admin/product-edit.php?id=' . (int) $product['id'])) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this cake? Existing orders keep their records.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                  <button type="submit" class="link-button link-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="admin-empty">No cakes here yet. <a href="<?= e(url('admin/product-edit.php')) ?>">Add the first cake</a>.</p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
