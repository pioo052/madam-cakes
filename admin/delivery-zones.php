<?php
/**
 * MADAM CAKES — delivery zones and fees.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

if (is_post()) {
    require_csrf();
    $action = (string) post('action');
    $zoneId = (int) post('zone_id');

    if ($action === 'save') {
        $name     = trim((string) post('name'));
        $region   = trim((string) post('region'));
        $city     = trim((string) post('city'));
        $fee      = (float) post('fee');
        $freeOver = trim((string) post('free_over'));
        $notes    = trim((string) post('notes'));
        $isActive = !empty(post('is_active'));

        if (mb_strlen($name) < 2 || mb_strlen($region) < 2) {
            flash('error', 'Enter a zone name and region.');
        } elseif ($fee < 0) {
            flash('error', 'The delivery fee cannot be negative.');
        } else {
            $values = [$name, $region, $city ?: null, $fee, $freeOver !== '' ? (float) $freeOver : null, $notes ?: null, $isActive ? 1 : 0];
            if ($zoneId > 0) {
                $values[] = $zoneId;
                Database::execute(
                    'UPDATE delivery_zones SET name = ?, region = ?, city = ?, fee = ?, free_over = ?, notes = ?, is_active = ? WHERE id = ?',
                    $values
                );
                log_admin_action('zone.update', 'delivery_zone', $zoneId, 'Updated ' . $name);
                flash('success', 'Delivery zone saved.');
            } else {
                $newId = Database::insert(
                    'INSERT INTO delivery_zones (name, region, city, fee, free_over, notes, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    $values
                );
                log_admin_action('zone.create', 'delivery_zone', $newId, 'Created ' . $name);
                flash('success', 'Delivery zone created.');
            }
        }
    } elseif ($action === 'delete') {
        $used = (int) Database::value('SELECT COUNT(*) FROM orders WHERE delivery_zone_id = ?', [$zoneId], 0);
        if ($used > 0) {
            flash('warning', 'This zone is used by ' . $used . ' order(s). Deactivate it instead of deleting.');
        } else {
            Database::execute('DELETE FROM delivery_zones WHERE id = ?', [$zoneId]);
            flash('success', 'Delivery zone deleted.');
        }
    }
    redirect('admin/delivery-zones.php');
}

$editId = (int) get('edit');
$editing = $editId > 0 ? Database::fetch('SELECT * FROM delivery_zones WHERE id = ?', [$editId]) : null;
$zones = Database::fetchAll('SELECT * FROM delivery_zones ORDER BY region, name');

$adminTitle  = 'Delivery zones';
$adminActive = 'zones';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-columns admin-columns-wide">
  <section class="admin-panel">
    <div class="panel-head"><h2><?= count($zones) ?> zone<?= count($zones) === 1 ? '' : 's' ?></h2></div>
    <?php if ($zones): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Zone</th><th>Region / city</th><th>Fee</th><th>Free over</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($zones as $zone): ?>
              <tr>
                <td><a href="<?= e(url('admin/delivery-zones.php?edit=' . (int) $zone['id'])) ?>"><?= e($zone['name']) ?></a></td>
                <td><?= e($zone['region']) ?><?= $zone['city'] ? ', ' . e($zone['city']) : '' ?></td>
                <td><?= e(money($zone['fee'])) ?></td>
                <td><?= $zone['free_over'] !== null ? e(money($zone['free_over'])) : '—' ?></td>
                <td><span class="pill <?= (int) $zone['is_active'] === 1 ? 'pill-success' : 'pill-muted' ?>"><?= (int) $zone['is_active'] === 1 ? 'Active' : 'Hidden' ?></span></td>
                <td class="table-actions">
                  <a class="link-button" href="<?= e(url('admin/delivery-zones.php?edit=' . (int) $zone['id'])) ?>">Edit</a>
                  <form method="post" onsubmit="return confirm('Delete this zone?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="zone_id" value="<?= (int) $zone['id'] ?>">
                    <button type="submit" class="link-button link-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="admin-empty">No delivery zones yet. Customers can still choose collection or enter their address.</p>
    <?php endif; ?>
  </section>

  <section class="admin-panel">
    <div class="panel-head"><h2><?= $editing ? 'Edit zone' : 'Add a zone' ?></h2></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="zone_id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">
      <label class="field"><span>Zone name</span><input type="text" name="name" value="<?= e($editing['name'] ?? '') ?>" required maxlength="120" placeholder="Kinondoni"></label>
      <div class="field-row">
        <label class="field"><span>Region</span><input type="text" name="region" value="<?= e($editing['region'] ?? '') ?>" required maxlength="80"></label>
        <label class="field"><span>City / district</span><input type="text" name="city" value="<?= e((string) ($editing['city'] ?? '')) ?>" maxlength="80"></label>
      </div>
      <div class="field-row">
        <label class="field"><span>Delivery fee</span><input type="number" name="fee" step="0.01" min="0" value="<?= e((string) ($editing['fee'] ?? '0')) ?>"></label>
        <label class="field"><span>Free over (blank = never)</span><input type="number" name="free_over" step="0.01" min="0" value="<?= e((string) ($editing['free_over'] ?? '')) ?>"></label>
      </div>
      <label class="field"><span>Notes</span><input type="text" name="notes" value="<?= e((string) ($editing['notes'] ?? '')) ?>" maxlength="255" placeholder="Delivery days or restrictions"></label>
      <label class="checkbox-line"><input type="checkbox" name="is_active" value="1"<?= !$editing || (int) $editing['is_active'] === 1 ? ' checked' : '' ?>><span>Available at checkout</span></label>
      <button type="submit" class="btn btn-primary btn-block"><?= $editing ? 'Save zone' : 'Create zone' ?></button>
      <?php if ($editing): ?><a class="btn btn-ghost btn-small btn-block" href="<?= e(url('admin/delivery-zones.php')) ?>">Cancel edit</a><?php endif; ?>
    </form>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
