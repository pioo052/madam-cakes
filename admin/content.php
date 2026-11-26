<?php
/**
 * MADAM CAKES — editable website copy (site_content).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

if (is_post()) {
    require_csrf();
    $action = (string) post('action');
    $key    = trim((string) post('content_key'));

    if ($action === 'save' && $key !== '') {
        $title = trim((string) post('title'));
        $body  = trim((string) post('body'));
        $existing = Database::fetch('SELECT * FROM site_content WHERE content_key = ?', [$key]);
        $image = $existing['image'] ?? null;

        if (!empty($_FILES['image']['name'])) {
            try {
                $image = upload_image($_FILES['image'], 'content');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect('admin/content.php');
            }
        }
        if (!empty(post('remove_image'))) {
            $image = null;
        }

        if ($existing) {
            Database::execute('UPDATE site_content SET title = ?, body = ?, image = ? WHERE content_key = ?', [$title ?: null, $body ?: null, $image, $key]);
            flash('success', 'Content updated.');
        } else {
            Database::insert('INSERT INTO site_content (content_key, title, body, image) VALUES (?, ?, ?, ?)', [$key, $title ?: null, $body ?: null, $image]);
            flash('success', 'Content block created.');
        }
    } elseif ($action === 'delete' && $key !== '') {
        Database::execute('DELETE FROM site_content WHERE content_key = ?', [$key]);
        flash('success', 'Content block deleted.');
    }
    redirect('admin/content.php' . ((string) get('edit') !== '' ? '?edit=' . rawurlencode((string) get('edit')) : ''));
}

$rows = Database::fetchAll('SELECT * FROM site_content ORDER BY content_key');
$editKey = (string) get('edit');
$editing = null;
foreach ($rows as $row) {
    if ($row['content_key'] === $editKey) {
        $editing = $row;
    }
}

$adminTitle  = 'Website content';
$adminActive = 'content';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-columns admin-columns-wide">
  <section class="admin-panel">
    <div class="panel-head"><h2><?= count($rows) ?> content blocks</h2></div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Key</th><th>Title</th><th>Body</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><code><?= e($row['content_key']) ?></code></td>
              <td><?= e(excerpt((string) $row['title'], 50)) ?></td>
              <td><?= e(excerpt((string) $row['body'], 60)) ?></td>
              <td><a class="link-button" href="<?= e(url('admin/content.php?edit=' . rawurlencode((string) $row['content_key']))) ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="admin-panel">
    <div class="panel-head"><h2><?= $editing ? 'Edit: ' . e($editing['content_key']) : 'Add a content block' ?></h2></div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <label class="field">
        <span>Key</span>
        <input type="text" name="content_key" value="<?= e($editing['content_key'] ?? '') ?>" required maxlength="64"<?= $editing ? ' readonly' : '' ?>>
      </label>
      <label class="field"><span>Title</span><input type="text" name="title" value="<?= e((string) ($editing['title'] ?? '')) ?>" maxlength="190"></label>
      <label class="field"><span>Body</span><textarea name="body" maxlength="8000"><?= e((string) ($editing['body'] ?? '')) ?></textarea></label>
      <label class="field"><span>Image (optional)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"></label>
      <?php if (!empty($editing['image'])): ?>
        <img class="content-preview" src="<?= e(upload_url($editing['image'])) ?>" alt="" width="200">
        <label class="checkbox-line"><input type="checkbox" name="remove_image" value="1"><span>Remove this image</span></label>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary btn-block">Save content</button>
      <?php if ($editing): ?><a class="btn btn-ghost btn-small btn-block" href="<?= e(url('admin/content.php')) ?>">Cancel edit</a><?php endif; ?>
    </form>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
