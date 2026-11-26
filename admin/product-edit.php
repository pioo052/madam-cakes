<?php
/**
 * MADAM CAKES — create/edit a cake, its sizes, flavours, extras and photos.
 * Prices are owner-defined; nothing here is pre-filled with invented amounts.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();
$productId = (int) get('id');

$product = $productId > 0
    ? Database::fetch('SELECT * FROM products WHERE id = ?', [$productId])
    : null;

if ($productId > 0 && !$product) {
    http_response_code(404);
    $adminTitle = 'Product not found';
    $adminActive = 'products';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h2>Product not found</h2><a class="btn btn-primary" href="' . e(url('admin/products.php')) . '">Back to products</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$categories = Database::fetchAll('SELECT id, name FROM categories ORDER BY sort_order, name');

if (is_post()) {
    require_csrf();
    $action = (string) post('action');

    if ($action === 'save') {
        $name        = trim((string) post('name'));
        $categoryId  = (int) post('category_id');
        $short       = trim((string) post('short_description'));
        $description = trim((string) post('description'));
        $basePrice   = (float) post('base_price');
        $prepDays    = (int) post('preparation_days');
        $isActive    = !empty(post('is_active'));
        $isFeatured  = !empty(post('is_featured'));
        $isBestseller = !empty(post('is_bestseller'));

        if (mb_strlen($name) < 2) {
            flash('error', 'Enter the cake name.');
        } elseif ($basePrice < 0) {
            flash('error', 'The base price cannot be negative.');
        } else {
            if ($categoryId > 0 && !Database::value('SELECT id FROM categories WHERE id = ?', [$categoryId])) {
                $categoryId = 0;
            }

            if ($product) {
                $slug = unique_slug('products', 'slug', $name, (int) $product['id']);
                Database::execute(
                    'UPDATE products SET category_id = ?, name = ?, slug = ?, short_description = ?, description = ?,
                       base_price = ?, preparation_days = ?, is_active = ?, is_featured = ?, is_bestseller = ?
                     WHERE id = ?',
                    [
                        $categoryId ?: null, $name, $slug, $short ?: null, $description ?: null,
                        $basePrice, max(0, $prepDays), $isActive ? 1 : 0, $isFeatured ? 1 : 0, $isBestseller ? 1 : 0,
                        (int) $product['id'],
                    ]
                );
                log_admin_action('product.update', 'product', (int) $product['id'], 'Updated ' . $name);
                flash('success', 'Cake saved.');
                redirect('admin/product-edit.php?id=' . (int) $product['id']);
            }

            $slug = unique_slug('products', 'slug', $name);
            $newId = Database::insert(
                'INSERT INTO products (category_id, name, slug, short_description, description, base_price,
                   preparation_days, is_active, is_featured, is_bestseller)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $categoryId ?: null, $name, $slug, $short ?: null, $description ?: null,
                    $basePrice, max(0, $prepDays), $isActive ? 1 : 0, $isFeatured ? 1 : 0, $isBestseller ? 1 : 0,
                ]
            );
            log_admin_action('product.create', 'product', $newId, 'Created ' . $name);
            flash('success', 'Cake created. Add sizes, flavours and photographs below.');
            redirect('admin/product-edit.php?id=' . $newId);
        }
        redirect($product ? 'admin/product-edit.php?id=' . (int) $product['id'] : 'admin/product-edit.php');
    }

    if (!$product) {
        flash('error', 'Save the cake before editing its options.');
        redirect('admin/product-edit.php');
    }

    $pid = (int) $product['id'];

    if ($action === 'add_size') {
        $sizeName = trim((string) post('size_name'));
        $price    = (float) post('size_price');
        if ($sizeName === '') {
            flash('error', 'Enter the size name.');
        } else {
            Database::insert(
                'INSERT INTO product_sizes (product_id, name, price, sort_order, is_active) VALUES (?, ?, ?, ?, 1)',
                [$pid, $sizeName, max(0, $price), (int) post('size_sort')]
            );
            flash('success', 'Size added.');
        }
    } elseif ($action === 'update_sizes') {
        foreach ((array) post('sizes', []) as $sizeId => $fields) {
            $owned = Database::value('SELECT id FROM product_sizes WHERE id = ? AND product_id = ?', [(int) $sizeId, $pid]);
            if ($owned) {
                Database::execute(
                    'UPDATE product_sizes SET name = ?, price = ?, sort_order = ?, is_active = ? WHERE id = ?',
                    [
                        trim((string) ($fields['name'] ?? '')),
                        max(0, (float) ($fields['price'] ?? 0)),
                        (int) ($fields['sort_order'] ?? 0),
                        !empty($fields['is_active']) ? 1 : 0,
                        (int) $sizeId,
                    ]
                );
            }
        }
        flash('success', 'Sizes updated.');
    } elseif ($action === 'delete_size') {
        Database::execute('DELETE FROM product_sizes WHERE id = ? AND product_id = ?', [(int) post('size_id'), $pid]);
        flash('success', 'Size removed.');
    } elseif ($action === 'add_flavour') {
        $flavourName = trim((string) post('flavour_name'));
        if ($flavourName === '') {
            flash('error', 'Enter the flavour name.');
        } else {
            Database::insert(
                'INSERT INTO product_flavours (product_id, name, surcharge, is_active) VALUES (?, ?, ?, 1)',
                [$pid, $flavourName, max(0, (float) post('flavour_surcharge'))]
            );
            flash('success', 'Flavour added.');
        }
    } elseif ($action === 'delete_flavour') {
        Database::execute('DELETE FROM product_flavours WHERE id = ? AND product_id = ?', [(int) post('flavour_id'), $pid]);
        flash('success', 'Flavour removed.');
    } elseif ($action === 'add_addon') {
        $addonName = trim((string) post('addon_name'));
        if ($addonName === '') {
            flash('error', 'Enter the extra name.');
        } else {
            Database::insert(
                'INSERT INTO product_addons (product_id, name, price, is_active) VALUES (?, ?, ?, 1)',
                [$pid, $addonName, max(0, (float) post('addon_price'))]
            );
            flash('success', 'Extra added.');
        }
    } elseif ($action === 'delete_addon') {
        Database::execute('DELETE FROM product_addons WHERE id = ? AND product_id = ?', [(int) post('addon_id'), $pid]);
        flash('success', 'Extra removed.');
    } elseif ($action === 'upload_image') {
        if (empty($_FILES['image']['name'])) {
            flash('error', 'Choose an image to upload.');
        } else {
            try {
                $path = upload_image($_FILES['image'], 'products');
                $hasPrimary = Database::value('SELECT id FROM product_images WHERE product_id = ? AND is_primary = 1', [$pid]);
                Database::insert(
                    'INSERT INTO product_images (product_id, image_path, alt_text, sort_order, is_primary) VALUES (?, ?, ?, ?, ?)',
                    [$pid, $path, trim((string) post('alt_text')) ?: null, (int) post('sort_order'), $hasPrimary ? 0 : 1]
                );
                log_admin_action('product.image', 'product', $pid, 'Uploaded ' . $path);
                flash('success', 'Photograph uploaded.');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
            }
        }
    } elseif ($action === 'delete_image') {
        $imageId = (int) post('image_id');
        $image = Database::fetch('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $pid]);
        if ($image) {
            Database::execute('DELETE FROM product_images WHERE id = ?', [$imageId]);
            $file = ROOT_PATH . '/' . $image['image_path'];
            if (is_file($file) && str_starts_with((string) $image['image_path'], 'assets/uploads/')) {
                @unlink($file);
            }
            flash('success', 'Photograph removed.');
        }
    } elseif ($action === 'primary_image') {
        $imageId = (int) post('image_id');
        if (Database::value('SELECT id FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $pid])) {
            Database::execute('UPDATE product_images SET is_primary = 0 WHERE product_id = ?', [$pid]);
            Database::execute('UPDATE product_images SET is_primary = 1 WHERE id = ?', [$imageId]);
            flash('success', 'Main photograph updated.');
        }
    }

    redirect('admin/product-edit.php?id=' . $pid);
}

$sizes    = $product ? Database::fetchAll('SELECT * FROM product_sizes WHERE product_id = ? ORDER BY sort_order, id', [(int) $product['id']]) : [];
$flavours = $product ? Database::fetchAll('SELECT * FROM product_flavours WHERE product_id = ? ORDER BY name', [(int) $product['id']]) : [];
$addons   = $product ? Database::fetchAll('SELECT * FROM product_addons WHERE product_id = ? ORDER BY name', [(int) $product['id']]) : [];
$images   = $product ? product_gallery((int) $product['id']) : [];

$adminTitle  = $product ? 'Edit: ' . $product['name'] : 'Add a cake';
$adminActive = 'products';
require __DIR__ . '/includes/header.php';
?>

<p class="admin-back"><a href="<?= e(url('admin/products.php')) ?>">&larr; All products</a></p>

<section class="admin-panel">
  <div class="panel-head">
    <h2><?= $product ? 'Cake details' : 'New cake' ?></h2>
    <?php if ($product): ?>
      <a class="btn btn-ghost btn-small" href="<?= e(product_url((string) $product['slug'])) ?>" target="_blank" rel="noopener">View public page ↗</a>
    <?php endif; ?>
  </div>

  <form method="post" class="admin-form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <label class="field">
      <span>Name</span>
      <input type="text" name="name" value="<?= e($product['name'] ?? '') ?>" required maxlength="160">
    </label>
    <label class="field">
      <span>Category</span>
      <select name="category_id">
        <option value="">No category</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>"<?= $product && (int) $product['category_id'] === (int) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="field field-span">
      <span>Short description (shown on cards)</span>
      <input type="text" name="short_description" value="<?= e((string) ($product['short_description'] ?? '')) ?>" maxlength="300">
    </label>

    <label class="field field-span">
      <span>Full description</span>
      <textarea name="description" maxlength="8000"><?= e((string) ($product['description'] ?? '')) ?></textarea>
    </label>

    <label class="field">
      <span>Base price (TZS, 0 = not priced)</span>
      <input type="number" name="base_price" step="0.01" min="0" value="<?= e((string) ($product['base_price'] ?? '0.00')) ?>">
    </label>
    <label class="field">
      <span>Preparation days</span>
      <input type="number" name="preparation_days" min="0" max="60" value="<?= e((string) ($product['preparation_days'] ?? '2')) ?>">
    </label>

    <div class="field field-span">
      <label class="checkbox-line"><input type="checkbox" name="is_active" value="1"<?= !$product || (int) $product['is_active'] === 1 ? ' checked' : '' ?>><span>Visible on the website</span></label>
      <label class="checkbox-line"><input type="checkbox" name="is_featured" value="1"<?= $product && (int) $product['is_featured'] === 1 ? ' checked' : '' ?>><span>Feature on the homepage</span></label>
      <label class="checkbox-line"><input type="checkbox" name="is_bestseller" value="1"<?= $product && (int) $product['is_bestseller'] === 1 ? ' checked' : '' ?>><span>Mark as a customer favourite</span></label>
    </div>

    <div class="field field-span">
      <button type="submit" class="btn btn-primary"><?= $product ? 'Save cake' : 'Create cake' ?></button>
    </div>
  </form>
</section>

<?php if ($product): ?>
  <div class="admin-columns">
    <section class="admin-panel">
      <div class="panel-head"><h2>Sizes and prices</h2></div>
      <p class="admin-muted">A size price replaces the base price. Leave the price at 0 until you are ready to sell that size.</p>

      <?php if ($sizes): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_sizes">
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Name</th><th>Price</th><th>Order</th><th>Active</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($sizes as $size): ?>
                  <tr>
                    <td><input type="text" name="sizes[<?= (int) $size['id'] ?>][name]" value="<?= e($size['name']) ?>" required></td>
                    <td><input type="number" step="0.01" min="0" name="sizes[<?= (int) $size['id'] ?>][price]" value="<?= e($size['price']) ?>"></td>
                    <td><input type="number" name="sizes[<?= (int) $size['id'] ?>][sort_order]" value="<?= (int) $size['sort_order'] ?>"></td>
                    <td><input type="checkbox" name="sizes[<?= (int) $size['id'] ?>][is_active]" value="1"<?= (int) $size['is_active'] === 1 ? ' checked' : '' ?>></td>
                    <td>
                      <button type="submit" form="delete-size-<?= (int) $size['id'] ?>" class="link-button link-danger">Delete</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button type="submit" class="btn btn-primary btn-small">Save sizes</button>
        </form>
        <?php foreach ($sizes as $size): ?>
          <form id="delete-size-<?= (int) $size['id'] ?>" method="post" onsubmit="return confirm('Remove this size?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_size">
            <input type="hidden" name="size_id" value="<?= (int) $size['id'] ?>">
          </form>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="admin-empty">No sizes yet — the base price will be used.</p>
      <?php endif; ?>

      <h3 class="panel-subhead">Add a size</h3>
      <form method="post" class="compact-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_size">
        <label class="field"><span>Name</span><input type="text" name="size_name" required maxlength="80" placeholder="8 inch (serves 12)"></label>
        <label class="field"><span>Price</span><input type="number" name="size_price" step="0.01" min="0" value="0"></label>
        <label class="field"><span>Sort order</span><input type="number" name="size_sort" value="0"></label>
        <button type="submit" class="btn btn-outline btn-block btn-small">Add size</button>
      </form>
    </section>

    <div class="admin-stack">
      <section class="admin-panel">
        <div class="panel-head"><h2>Flavours</h2></div>
        <?php if ($flavours): ?>
          <ul class="option-admin-list">
            <?php foreach ($flavours as $flavour): ?>
              <li>
                <span><?= e($flavour['name']) ?><?= (float) $flavour['surcharge'] > 0 ? ' · +' . e(money($flavour['surcharge'])) : '' ?></span>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_flavour"><input type="hidden" name="flavour_id" value="<?= (int) $flavour['id'] ?>"><button type="submit" class="link-button link-danger">Remove</button></form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="admin-empty">No flavours yet.</p>
        <?php endif; ?>
        <form method="post" class="compact-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_flavour">
          <label class="field"><span>Flavour</span><input type="text" name="flavour_name" required maxlength="80"></label>
          <label class="field"><span>Extra charge</span><input type="number" name="flavour_surcharge" step="0.01" min="0" value="0"></label>
          <button type="submit" class="btn btn-outline btn-block btn-small">Add flavour</button>
        </form>
      </section>

      <section class="admin-panel">
        <div class="panel-head"><h2>Extras</h2></div>
        <?php if ($addons): ?>
          <ul class="option-admin-list">
            <?php foreach ($addons as $addon): ?>
              <li>
                <span><?= e($addon['name']) ?> · <?= e(money($addon['price'])) ?></span>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_addon"><input type="hidden" name="addon_id" value="<?= (int) $addon['id'] ?>"><button type="submit" class="link-button link-danger">Remove</button></form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="admin-empty">No extras yet.</p>
        <?php endif; ?>
        <form method="post" class="compact-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_addon">
          <label class="field"><span>Extra</span><input type="text" name="addon_name" required maxlength="120" placeholder="Cake topper"></label>
          <label class="field"><span>Price</span><input type="number" name="addon_price" step="0.01" min="0" value="0"></label>
          <button type="submit" class="btn btn-outline btn-block btn-small">Add extra</button>
        </form>
      </section>
    </div>
  </div>

  <section class="admin-panel">
    <div class="panel-head"><h2>Photographs</h2></div>
    <?php if ($images): ?>
      <ul class="image-admin-grid">
        <?php foreach ($images as $image): ?>
          <li>
            <img src="<?= e(upload_url($image['image_path'])) ?>" alt="<?= e((string) ($image['alt_text'] ?? '')) ?>" width="160" height="160">
            <div class="image-admin-actions">
              <?php if ((int) $image['is_primary'] === 1): ?>
                <span class="pill pill-success">Main photo</span>
              <?php else: ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="primary_image"><input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>"><button type="submit" class="link-button">Make main</button></form>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Delete this photograph?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_image"><input type="hidden" name="image_id" value="<?= (int) $image['id'] ?>"><button type="submit" class="link-button link-danger">Delete</button></form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="admin-empty">No photographs yet.</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="compact-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_image">
      <label class="field"><span>Photograph (max 5 MB)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required></label>
      <label class="field"><span>Alt text</span><input type="text" name="alt_text" maxlength="190"></label>
      <label class="field"><span>Sort order</span><input type="number" name="sort_order" value="0"></label>
      <button type="submit" class="btn btn-primary btn-small">Upload photograph</button>
    </form>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
