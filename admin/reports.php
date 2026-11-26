<?php
/**
 * MADAM CAKES — reports. Every figure is a live aggregate; no sample data.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

$from = (string) get('from', date('Y-m-01'));
$to   = (string) get('to', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}
$start = $from . ' 00:00:00';
$end   = $to . ' 23:59:59';

$orders = Database::fetch(
    'SELECT COUNT(*) AS orders,
            COALESCE(SUM(total), 0) AS ordered_value,
            COALESCE(SUM(CASE WHEN payment_status = \'paid\' THEN total ELSE 0 END), 0) AS paid_value,
            COALESCE(AVG(total), 0) AS average_value
     FROM orders WHERE created_at BETWEEN ? AND ?',
    [$start, $end]
);

$statusRows = Database::fetchAll(
    'SELECT status, COUNT(*) AS total, COALESCE(SUM(total),0) AS value
     FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY status ORDER BY total DESC',
    [$start, $end]
);

$topProducts = Database::fetchAll(
    'SELECT oi.product_name, SUM(oi.quantity) AS units, SUM(oi.line_total) AS value
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE o.created_at BETWEEN ? AND ?
     GROUP BY oi.product_name ORDER BY units DESC LIMIT 10',
    [$start, $end]
);

$newCustomers = (int) Database::value(
    "SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at BETWEEN ? AND ?",
    [$start, $end],
    0
);

$customRequests = Database::fetch(
    'SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status IN (\'accepted\',\'converted\') THEN 1 ELSE 0 END),0) AS accepted
     FROM custom_orders WHERE created_at BETWEEN ? AND ?',
    [$start, $end]
);

$deliveryMix = Database::fetchAll(
    'SELECT fulfilment, COUNT(*) AS total FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY fulfilment',
    [$start, $end]
);

$adminTitle  = 'Reports';
$adminActive = 'reports';
require __DIR__ . '/includes/header.php';
?>

<form class="admin-filters" method="get" action="<?= e(url('admin/reports.php')) ?>">
  <label class="field field-inline"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="field field-inline"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
  <button type="submit" class="btn btn-primary btn-small">Run report</button>
</form>

<p class="admin-muted">Showing records created between <?= e(format_date($from)) ?> and <?= e(format_date($to)) ?>.</p>

<div class="stat-grid admin-stats">
  <div class="stat-card"><span class="stat-label">Orders</span><strong class="stat-value"><?= (int) $orders['orders'] ?></strong></div>
  <div class="stat-card"><span class="stat-label">Ordered value</span><strong class="stat-value stat-value-sm"><?= e(money($orders['ordered_value'])) ?></strong></div>
  <div class="stat-card"><span class="stat-label">Verified payments</span><strong class="stat-value stat-value-sm"><?= e(money($orders['paid_value'])) ?></strong></div>
  <div class="stat-card"><span class="stat-label">Average order</span><strong class="stat-value stat-value-sm"><?= e(money($orders['average_value'])) ?></strong></div>
  <div class="stat-card"><span class="stat-label">New customers</span><strong class="stat-value"><?= $newCustomers ?></strong></div>
  <div class="stat-card"><span class="stat-label">Custom requests</span><strong class="stat-value"><?= (int) $customRequests['total'] ?></strong><span class="stat-muted"><?= (int) $customRequests['accepted'] ?> accepted</span></div>
</div>

<div class="admin-columns">
  <section class="admin-panel">
    <div class="panel-head"><h2>Orders by status</h2></div>
    <?php if ($statusRows): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Status</th><th>Orders</th><th>Value</th></tr></thead>
          <tbody>
            <?php foreach ($statusRows as $row): ?>
              <tr><td><?= status_badge($row['status']) ?></td><td><?= (int) $row['total'] ?></td><td><?= e(money($row['value'])) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="admin-empty">No orders in this period.</p>
    <?php endif; ?>
  </section>

  <section class="admin-panel">
    <div class="panel-head"><h2>Best-selling cakes</h2></div>
    <?php if ($topProducts): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Cake</th><th>Units</th><th>Value</th></tr></thead>
          <tbody>
            <?php foreach ($topProducts as $row): ?>
              <tr><td><?= e($row['product_name']) ?></td><td><?= (int) $row['units'] ?></td><td><?= e(money($row['value'])) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="admin-empty">No items sold in this period.</p>
    <?php endif; ?>
  </section>
</div>

<section class="admin-panel">
  <div class="panel-head"><h2>Fulfilment mix</h2></div>
  <?php if ($deliveryMix): ?>
    <ul class="plain-list">
      <?php foreach ($deliveryMix as $row): ?>
        <li><?= e(ucfirst((string) $row['fulfilment'])) ?>: <?= (int) $row['total'] ?> order<?= (int) $row['total'] === 1 ? '' : 's' ?></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="admin-empty">No orders in this period.</p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
