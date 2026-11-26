<?php
/**
 * MADAM CAKES — discount coupons. The owner defines every value.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

if (is_post()) {
    require_csrf();
    $action   = (string) post('action');
    $couponId = (int) post('coupon_id');

    if ($action === 'save') {
        $code      = strtoupper(trim((string) post('code')));
        $type      = (string) post('discount_type');
        $value     = (float) post('discount_value');
        $minOrder  = (float) post('min_order_value');
        $startsAt  = trim((string) post('starts_at'));
        $expiresAt = trim((string) post('expires_at'));
        $usageLimit = trim((string) post('usage_limit'));
        $perUser   = trim((string) post('per_user_limit'));
        $isActive  = !empty(post('is_active'));
        $description = trim((string) post('description'));

        if (!preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
            flash('error', 'Use 3–40 letters, numbers, dashes or underscores for the code.');
        } elseif (!in_array($type, ['percent', 'fixed'], true)) {
            flash('error', 'Choose a discount type.');
        } elseif ($value <= 0) {
            flash('error', 'Enter a discount value greater than zero.');
        } elseif ($type === 'percent' && $value > 100) {
            flash('error', 'A percentage discount cannot exceed 100.');
        } elseif (Database::value('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$code, $couponId])) {
            flash('error', 'That coupon code already exists.');
        } else {
            $params = [
                $code, $description ?: null, $type, $value, $minOrder,
                $startsAt !== '' ? $startsAt . ' 00:00:00' : null,
                $expiresAt !== '' ? $expiresAt . ' 23:59:59' : null,
                $usageLimit !== '' ? (int) $usageLimit : null,
                $perUser !== '' ? (int) $perUser : null,
                $isActive ? 1 : 0,
            ];

            if ($couponId > 0) {
                $params[] = $couponId;
                Database::execute(
                    'UPDATE coupons SET code = ?, description = ?, discount_type = ?, discount_value = ?, min_order_value = ?,
                       starts_at = ?, expires_at = ?, usage_limit = ?, per_user_limit = ?, is_active = ? WHERE id = ?',
                    $params
                );
                log_admin_action('coupon.update', 'coupon', $couponId, 'Updated ' . $code);
                flash('success', 'Coupon saved.');
            } else {
                $newId = Database::insert(
                    'INSERT INTO coupons (code, description, discount_type, discount_value, min_order_value,
                       starts_at, expires_at, usage_limit, per_user_limit, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    $params
                );
                log_admin_action('coupon.create', 'coupon', $newId, 'Created ' . $code);
                flash('success', 'Coupon created.');
            }
        }
    } elseif ($action === 'delete') {
        Database::execute('DELETE FROM coupons WHERE id = ?', [$couponId]);
        log_admin_action('coupon.delete', 'coupon', $couponId, 'Deleted a coupon.');
        flash('success', 'Coupon deleted.');
    } elseif ($action === 'toggle') {
        Database::execute('UPDATE coupons SET is_active = 1 - is_active WHERE id = ?', [$couponId]);
        flash('success', 'Coupon availability updated.');
    }
    redirect('admin/coupons.php');
}

$editId = (int) get('edit');
$editing = $editId > 0 ? Database::fetch('SELECT * FROM coupons WHERE id = ?', [$editId]) : null;
$coupons = Database::fetchAll(
    'SELECT c.*, (SELECT COUNT(*) FROM coupon_usage cu WHERE cu.coupon_id = c.id) AS usage_count
     FROM coupons c ORDER BY c.created_at DESC'
);

$adminTitle  = 'Coupons';
$adminActive = 'coupons';
require __DIR__ . '/includes/header.php';
?>

<div class="admin-columns admin-columns-wide">
  <section class="admin-panel">
    <div class="panel-head"><h2><?= count($coupons) ?> coupon<?= count($coupons) === 1 ? '' : 's' ?></h2></div>
    <?php if ($coupons): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Code</th><th>Discount</th><th>Minimum</th><th>Window</th><th>Used</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($coupons as $coupon): ?>
              <tr>
                <td><a href="<?= e(url('admin/coupons.php?edit=' . (int) $coupon['id'])) ?>"><?= e($coupon['code']) ?></a></td>
                <td><?= $coupon['discount_type'] === 'percent' ? e(amount($coupon['discount_value'], 1) . '%') : e(money($coupon['discount_value'])) ?></td>
                <td><?= e(money($coupon['min_order_value'])) ?></td>
                <td><small><?= e(format_date($coupon['starts_at'])) ?> → <?= e(format_date($coupon['expires_at'], 'd M Y')) ?></small></td>
                <td><?= (int) $coupon['usage_count'] ?><?= $coupon['usage_limit'] ? ' / ' . (int) $coupon['usage_limit'] : '' ?></td>
                <td>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="coupon_id" value="<?= (int) $coupon['id'] ?>">
                    <button type="submit" class="pill <?= (int) $coupon['is_active'] === 1 ? 'pill-success' : 'pill-muted' ?>"><?= (int) $coupon['is_active'] === 1 ? 'Active' : 'Paused' ?></button>
                  </form>
                </td>
                <td class="table-actions">
                  <a class="link-button" href="<?= e(url('admin/coupons.php?edit=' . (int) $coupon['id'])) ?>">Edit</a>
                  <form method="post" onsubmit="return confirm('Delete this coupon?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="coupon_id" value="<?= (int) $coupon['id'] ?>">
                    <button type="submit" class="link-button link-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="admin-empty">No coupons yet.</p>
    <?php endif; ?>
  </section>

  <section class="admin-panel">
    <div class="panel-head"><h2><?= $editing ? 'Edit coupon' : 'Create a coupon' ?></h2></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="coupon_id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">
      <label class="field"><span>Code</span><input type="text" name="code" value="<?= e($editing['code'] ?? '') ?>" required maxlength="40" placeholder="SWEET10"></label>
      <label class="field"><span>Description (internal)</span><input type="text" name="description" value="<?= e((string) ($editing['description'] ?? '')) ?>" maxlength="190"></label>
      <div class="field-row">
        <label class="field">
          <span>Type</span>
          <select name="discount_type">
            <option value="percent"<?= $editing && $editing['discount_type'] === 'percent' ? ' selected' : '' ?>>Percentage</option>
            <option value="fixed"<?= $editing && $editing['discount_type'] === 'fixed' ? ' selected' : '' ?>>Fixed amount</option>
          </select>
        </label>
        <label class="field"><span>Value</span><input type="number" name="discount_value" step="0.01" min="0" value="<?= e((string) ($editing['discount_value'] ?? '0')) ?>" required></label>
      </div>
      <label class="field"><span>Minimum order value</span><input type="number" name="min_order_value" step="0.01" min="0" value="<?= e((string) ($editing['min_order_value'] ?? '0')) ?>"></label>
      <div class="field-row">
        <label class="field"><span>Starts</span><input type="date" name="starts_at" value="<?= e($editing && $editing['starts_at'] ? substr((string) $editing['starts_at'], 0, 10) : '') ?>"></label>
        <label class="field"><span>Expires</span><input type="date" name="expires_at" value="<?= e($editing && $editing['expires_at'] ? substr((string) $editing['expires_at'], 0, 10) : '') ?>"></label>
      </div>
      <div class="field-row">
        <label class="field"><span>Total uses allowed (blank = unlimited)</span><input type="number" name="usage_limit" min="1" value="<?= e((string) ($editing['usage_limit'] ?? '')) ?>"></label>
        <label class="field"><span>Uses per customer</span><input type="number" name="per_user_limit" min="1" value="<?= e((string) ($editing['per_user_limit'] ?? '1')) ?>"></label>
      </div>
      <label class="checkbox-line"><input type="checkbox" name="is_active" value="1"<?= !$editing || (int) $editing['is_active'] === 1 ? ' checked' : '' ?>><span>Active</span></label>
      <button type="submit" class="btn btn-primary btn-block"><?= $editing ? 'Save coupon' : 'Create coupon' ?></button>
      <?php if ($editing): ?><a class="btn btn-ghost btn-small btn-block" href="<?= e(url('admin/coupons.php')) ?>">Cancel edit</a><?php endif; ?>
    </form>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
