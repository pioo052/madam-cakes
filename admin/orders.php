<?php
/**
 * MADAM CAKES — admin order list with filters and search.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

$status = (string) get('status');
if ($status !== '' && !array_key_exists($status, order_statuses())) {
    $status = '';
}
$q = trim((string) get('q'));

$where = ['1 = 1'];
$params = [];
if ($status !== '') {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(o.order_reference LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$pager = paginate((int) Database::value('SELECT COUNT(*) FROM orders o WHERE ' . $whereSql, $params, 0), 20);
$orders = Database::fetchAll(
    'SELECT o.*, (SELECT COALESCE(SUM(quantity),0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
     FROM orders o WHERE ' . $whereSql . '
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset'],
    $params
);

$adminTitle  = 'Orders';
$adminActive = 'orders';
require __DIR__ . '/includes/header.php';
?>

<form class="admin-filters" method="get" action="<?= e(url('admin/orders.php')) ?>">
  <label class="visually-hidden" for="orderQ">Search orders</label>
  <input type="search" id="orderQ" name="q" value="<?= e($q) ?>" placeholder="Reference, name, phone or email">
  <label class="visually-hidden" for="orderStatus">Status</label>
  <select id="orderStatus" name="status">
    <option value="">All statuses</option>
    <?php foreach (order_statuses() as $key => $label): ?>
      <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-small">Filter</button>
  <?php if ($q !== '' || $status !== ''): ?><a class="link-button" href="<?= e(url('admin/orders.php')) ?>">Reset</a><?php endif; ?>
</form>

<section class="admin-panel">
  <div class="panel-head">
    <h2><?= (int) $pager['total'] ?> order<?= (int) $pager['total'] === 1 ? '' : 's' ?></h2>
    <span class="admin-muted">Newest first</span>
  </div>

  <?php if ($orders): ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Reference</th>
            <th scope="col">Placed</th>
            <th scope="col">Customer</th>
            <th scope="col">Items</th>
            <th scope="col">Total</th>
            <th scope="col">Status</th>
            <th scope="col">Payment</th>
            <th scope="col"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><a href="<?= e(url('admin/order.php?id=' . (int) $order['id'])) ?>"><?= e($order['order_reference']) ?></a></td>
              <td><?= e(format_date($order['created_at'])) ?><br><small><?= e(format_date($order['created_at'], 'H:i')) ?></small></td>
              <td><?= e($order['customer_name']) ?><br><small><?= e($order['customer_phone']) ?></small></td>
              <td><?= (int) $order['item_count'] ?></td>
              <td><?= e(money($order['total'])) ?></td>
              <td><?= status_badge($order['status']) ?></td>
              <td><?= payment_badge($order['payment_status']) ?></td>
              <td><a class="btn btn-ghost btn-small" href="<?= e(url('admin/order.php?id=' . (int) $order['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination_html($pager, 'Orders') ?>
  <?php else: ?>
    <p class="admin-empty">No orders match this view.</p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
