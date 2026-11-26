<?php
/**
 * MADAM CAKES — admin gallery.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

$categories = ['birthday', 'wedding', 'graduation', 'anniversary', 'baby_shower', 'custom', 'other'];

if (is_post()) {
    require_csrf();
    $action = (string) post('action');
    $galleryId = (int) post('gallery_id');

    if ($action === 'save') {
        $title    = trim((string) post('title'));
        $caption  = trim((string) post('caption'));
        $category = (string) post('category');
        $sort     = (int) post('sort_order');
        $featured = !empty(post('is_featured'));
        $active   = !empty(post('is_active'));
        $existing = $galleryId > 0 ? Database::fetch('SELECT * FROM gallery WHERE id = ?', [$galleryId]) : null;

        if (!in_array($category, $categories, true)) {
            $category = 'other';
        }

        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            try {
                $imagePath = upload_image($_FILES['image'], 'gallery');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect('admin/gallery.php' . ($galleryId ? '?edit=' . $galleryId : ''));
            }
        }

        if (mb_strlen($title) < 2) {
            flash('error', 'Enter a title for this photograph.');
        } elseif ($galleryId > 0 && $existing) {
            Database::execute(
                'UPDATE gallery SET title = ?, caption = ?, category = ?, sort_order = ?, is_featured = ?, is_active = ?, image = ? WHERE id = ?',
                [$title, $caption ?: null, $category, $sort, $featured ? 1 : 0, $active ? 1 : 0, $imagePath ?: $existing['image'], $galleryId]
            );
            flash('success', 'Gallery item saved.');
        } elseif ($imagePath === null) {
            flash('error', 'Choose a photograph to upload.');
        } else {
            Database::insert(
                'INSERT INTO gallery (title, caption, image, category, sort_order, is_featured, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$title, $caption ?: null, $imagePath, $category, $sort, $featured ? 1 : 0, $active ? 1 : 0]
            );
            flash('success', 'Photograph added to the gallery.');
        }
    } elseif ($action === 'delete') {
        $item = Database::fetch('SELECT * FROM gallery WHERE id = ?', [$galleryId]);
        if ($item) {
            Database::execute('DELETE FROM gallery WHERE id = ?', [$galleryId]);
            $file = ROOT_PATH . '/' . $item['image'];
            if (is_file($file) && str_starts_with((string) $item['image'], 'assets/uploads/')) {
                @unlink($file);
            }
            flash('success', 'Gallery item deleted.');
        }
    }
    redirect('admin/gallery.php');
}

$editId = (int) get('edit');
$editing = $editId > 0 ? Database::fetch('SELECT * FROM gallery WHERE id = ?', [$editId]) : null;
$items = Database::fetchAll('SELECT * FROM gallery ORDER BY is_featured DESC, sort_order, created_at DESC');

$adminTitle  = 'Gallery';
$adminActive = 'gallery';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-columns admin-columns-wide">
  <section class="admin-panel">
    <div class="panel-head"><h2><?= count($items) ?> photographs</h2></div>
    <?php if ($items): ?>
      <ul class="image-admin-grid">
        <?php foreach ($items as $item): ?>
          <li>
            <img src="<?= e(upload_url($item['image'])) ?>" alt="<?= e($item['title']) ?>" width="160" height="160">
            <strong class="image-admin-title"><?= e($item['title']) ?></strong>
            <small><?= e(ucwords(str_replace('_', ' ', (string) $item['category']))) ?> · order <?= (int) $item['sort_order'] ?></small>
            <div class="image-admin-actions">
              <a class="link-button" href="<?= e(url('admin/gallery.php?edit=' . (int) $item['id'])) ?>">Edit</a>
              <form method="post" onsubmit="return confirm('Delete this photograph?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="gallery_id" value="<?= (int) $item['id'] ?>">
                <button type="submit" class="link-button link-danger">Delete</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="admin-empty">The gallery is empty. Add photographs of cakes you have made.</p>
    <?php endif; ?>
  </section>

  <section class="admin-panel">
    <div class="panel-head"><h2><?= $editing ? 'Edit photograph' : 'Add a photograph' ?></h2></div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="gallery_id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">
      <label class="field"><span>Title</span><input type="text" name="title" value="<?= e($editing['title'] ?? '') ?>" required maxlength="160"></label>
      <label class="field"><span>Caption</span><textarea name="caption" maxlength="300"><?= e((string) ($editing['caption'] ?? '')) ?></textarea></label>
      <label class="field">
        <span>Category</span>
        <select name="category">
          <?php foreach ($categories as $category): ?>
            <option value="<?= e($category) ?>"<?= $editing && $editing['category'] === $category ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $category))) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span>Photograph<?= $editing ? ' (leave empty to keep current)' : '' ?></span><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"<?= $editing ? '' : ' required' ?>></label>
      <label class="field"><span>Sort order</span><input type="number" name="sort_order" value="<?= (int) ($editing['sort_order'] ?? 0) ?>"></label>
      <label class="checkbox-line"><input type="checkbox" name="is_featured" value="1"<?= $editing && (int) $editing['is_featured'] === 1 ? ' checked' : '' ?>><span>Feature at the top</span></label>
      <label class="checkbox-line"><input type="checkbox" name="is_active" value="1"<?= !$editing || (int) $editing['is_active'] === 1 ? ' checked' : '' ?>><span>Visible on the website</span></label>
      <button type="submit" class="btn btn-primary btn-block"><?= $editing ? 'Save photograph' : 'Add photograph' ?></button>
      <?php if ($editing): ?><a class="btn btn-ghost btn-small btn-block" href="<?= e(url('admin/gallery.php')) ?>">Cancel edit</a><?php endif; ?>
    </form>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
