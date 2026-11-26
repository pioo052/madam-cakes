<?php
/**
 * MADAM CAKES — admin customer list.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

$q = trim((string) get('q'));
$sql = "SELECT u.*,
          (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
          (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id = u.id AND o.payment_status = 'paid') AS paid_total,
          (SELECT COUNT(*) FROM custom_orders c WHERE c.user_id = u.id) AS custom_count
        FROM users u WHERE u.role = 'customer'";
$params = [];
if ($q !== '') {
    $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY u.created_at DESC, u.id DESC LIMIT 200';
$customers = Database::fetchAll($sql, $params);

$adminTitle  = 'Customers';
$adminActive = 'customers';
require __DIR__ . '/includes/header.php';
?>

<form class="admin-filters" method="get" action="<?= e(url('admin/customers.php')) ?>">
  <label class="visually-hidden" for="customerQ">Search customers</label>
  <input type="search" id="customerQ" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone">
  <button type="submit" class="btn btn-primary btn-small">Search</button>
  <?php if ($q !== ''): ?><a class="link-button" href="<?= e(url('admin/customers.php')) ?>">Reset</a><?php endif; ?>
</form>

<section class="admin-panel">
  <div class="panel-head"><h2><?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?></h2></div>
  <?php if ($customers): ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Orders</th><th>Paid</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($customers as $customer): ?>
            <tr>
              <td><a href="<?= e(url('admin/customer.php?id=' . (int) $customer['id'])) ?>"><?= e($customer['full_name']) ?></a></td>
              <td><?= e($customer['email']) ?></td>
              <td><?= e((string) ($customer['phone'] ?? '—')) ?></td>
              <td><?= e(format_date($customer['created_at'])) ?></td>
              <td><?= (int) $customer['order_count'] ?></td>
              <td><?= e(money($customer['paid_total'])) ?></td>
              <td>
                <span class="pill <?= $customer['status'] === 'active' ? 'pill-success' : 'pill-muted' ?>"><?= e(ucfirst((string) $customer['status'])) ?></span>
                <?php if ($customer['closed_at']): ?><br><small>closed</small><?php endif; ?>
              </td>
              <td><a class="btn btn-ghost btn-small" href="<?= e(url('admin/customer.php?id=' . (int) $customer['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="admin-empty">No customers match this search.</p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
