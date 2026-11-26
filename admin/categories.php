<?php
/**
 * MADAM CAKES — admin cake categories.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

if (is_post()) {
    require_csrf();
    $action     = (string) post('action');
    $categoryId = (int) post('category_id');

    if ($action === 'save') {
        $name        = trim((string) post('name'));
        $description = trim((string) post('description'));
        $sortOrder   = (int) post('sort_order');
        $isActive    = !empty(post('is_active'));
        $imagePath   = null;

        if (!empty($_FILES['image']['name'])) {
            try {
                $imagePath = upload_image($_FILES['image'], 'categories');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect('admin/categories.php');
            }
        }

        if (mb_strlen($name) < 2) {
            flash('error', 'Enter the category name.');
        } elseif ($categoryId > 0) {
            $existing = Database::fetch('SELECT * FROM categories WHERE id = ?', [$categoryId]);
            if ($existing) {
                $slug = unique_slug('categories', 'slug', $name, $categoryId);
                Database::execute(
                    'UPDATE categories SET name = ?, slug = ?, description = ?, sort_order = ?, is_active = ?, image = ? WHERE id = ?',
                    [$name, $slug, $description ?: null, $sortOrder, $isActive ? 1 : 0, $imagePath ?: $existing['image'], $categoryId]
                );
                log_admin_action('category.update', 'category', $categoryId, 'Updated ' . $name);
                flash('success', 'Category saved.');
            }
        } else {
            $slug = unique_slug('categories', 'slug', $name);
            $newId = Database::insert(
                'INSERT INTO categories (name, slug, description, sort_order, is_active, image) VALUES (?, ?, ?, ?, ?, ?)',
                [$name, $slug, $description ?: null, $sortOrder, $isActive ? 1 : 0, $imagePath]
            );
            log_admin_action('category.create', 'category', $newId, 'Created ' . $name);
            flash('success', 'Category created.');
        }
    } elseif ($action === 'delete') {
        $productCount = (int) Database::value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$categoryId], 0);
        if ($productCount > 0) {
            flash('warning', 'Move or delete the ' . $productCount . ' cake(s) in this category first.');
        } else {
            Database::execute('DELETE FROM categories WHERE id = ?', [$categoryId]);
            log_admin_action('category.delete', 'category', $categoryId, 'Deleted a category.');
            flash('success', 'Category deleted.');
        }
    }
    redirect('admin/categories.php');
}

$editId = (int) get('edit');
$editing = $editId > 0 ? Database::fetch('SELECT * FROM categories WHERE id = ?', [$editId]) : null;

$categories = Database::fetchAll(
    'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c ORDER BY c.sort_order, c.name'
);

$adminTitle  = 'Categories';
$adminActive = 'categories';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-columns admin-columns-wide">
  <section class="admin-panel">
    <div class="panel-head"><h2><?= count($categories) ?> categories</h2></div>
    <?php if ($categories): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Slug</th><th>Cakes</th><th>Order</th><th>Visible</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($categories as $category): ?>
              <tr>
                <td><a href="<?= e(url('admin/categories.php?edit=' . (int) $category['id'])) ?>"><?= e($category['name']) ?></a></td>
                <td><small><?= e($category['slug']) ?></small></td>
                <td><?= (int) $category['product_count'] ?></td>
                <td><?= (int) $category['sort_order'] ?></td>
                <td><span class="pill <?= (int) $category['is_active'] === 1 ? 'pill-success' : 'pill-muted' ?>"><?= (int) $category['is_active'] === 1 ? 'Visible' : 'Hidden' ?></span></td>
                <td class="table-actions">
                  <a class="link-button" href="<?= e(url('admin/categories.php?edit=' . (int) $category['id'])) ?>">Edit</a>
                  <form method="post" onsubmit="return confirm('Delete this category?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                    <button type="submit" class="link-button link-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="admin-empty">No categories yet.</p>
    <?php endif; ?>
  </section>

  <section class="admin-panel">
    <div class="panel-head"><h2><?= $editing ? 'Edit category' : 'Add a category' ?></h2></div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="category_id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">
      <label class="field">
        <span>Name</span>
        <input type="text" name="name" value="<?= e($editing['name'] ?? '') ?>" required maxlength="120">
      </label>
      <label class="field">
        <span>Description</span>
        <textarea name="description" maxlength="1000"><?= e((string) ($editing['description'] ?? '')) ?></textarea>
      </label>
      <label class="field">
        <span>Sort order</span>
        <input type="number" name="sort_order" value="<?= (int) ($editing['sort_order'] ?? 0) ?>">
      </label>
      <label class="field">
        <span>Image (optional)</span>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
      </label>
      <label class="checkbox-line">
        <input type="checkbox" name="is_active" value="1"<?= !$editing || (int) $editing['is_active'] === 1 ? ' checked' : '' ?>>
        <span>Visible on the website</span>
      </label>
      <button type="submit" class="btn btn-primary btn-block"><?= $editing ? 'Save category' : 'Create category' ?></button>
      <?php if ($editing): ?>
        <a class="btn btn-ghost btn-small btn-block" href="<?= e(url('admin/categories.php')) ?>">Cancel edit</a>
      <?php endif; ?>
    </form>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
