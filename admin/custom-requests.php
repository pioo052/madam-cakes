<?php
/**
 * MADAM CAKES — admin custom cake requests.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

$customStatuses = [
    'pending'            => 'Awaiting review',
    'awaiting_quotation' => 'Awaiting quotation',
    'quoted'             => 'Quotation ready',
    'accepted'           => 'Accepted',
    'rejected'           => 'Declined',
    'converted'          => 'Converted',
    'cancelled'          => 'Cancelled',
];

$status = (string) get('status');
if ($status !== '' && !isset($customStatuses[$status])) {
    $status = '';
}

$sql = 'SELECT * FROM custom_orders';
$params = [];
if ($status !== '') {
    $sql .= ' WHERE status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY created_at DESC, id DESC';
$requests = Database::fetchAll($sql, $params);

$adminTitle  = 'Custom cake requests';
$adminActive = 'custom';
require __DIR__ . '/includes/header.php';
?>

<nav class="filter-chips" aria-label="Filter custom requests">
  <a class="filter-chip<?= $status === '' ? ' is-active' : '' ?>" href="<?= e(url('admin/custom-requests.php')) ?>">All</a>
  <?php foreach ($customStatuses as $key => $label): ?>
    <a class="filter-chip<?= $status === $key ? ' is-active' : '' ?>" href="<?= e(url('admin/custom-requests.php?status=' . $key)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<section class="admin-panel">
  <div class="panel-head">
    <h2><?= count($requests) ?> request<?= count($requests) === 1 ? '' : 's' ?></h2>
  </div>

  <?php if ($requests): ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Reference</th><th>Received</th><th>Customer</th><th>Needed for</th><th>Estimate</th><th>Quote</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($requests as $request): ?>
            <tr>
              <td><a href="<?= e(url('admin/custom-request.php?id=' . (int) $request['id'])) ?>"><?= e($request['order_reference']) ?></a></td>
              <td><?= e(format_date($request['created_at'])) ?></td>
              <td><?= e($request['customer_name']) ?><br><small><?= e($request['customer_phone']) ?></small></td>
              <td><?= e(format_date($request['required_date'])) ?></td>
              <td><?= $request['estimated_price'] !== null ? e(money($request['estimated_price'])) : '—' ?></td>
              <td><?= $request['quoted_price'] !== null ? e(money($request['quoted_price'])) : '—' ?></td>
              <td><span class="pill<?= $request['status'] === 'pending' ? ' pill-muted' : '' ?>"><?= e($customStatuses[$request['status']] ?? $request['status']) ?></span></td>
              <td><a class="btn btn-ghost btn-small" href="<?= e(url('admin/custom-request.php?id=' . (int) $request['id'])) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="admin-empty">No custom requests in this view.</p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
