<?php
/**
 * MADAM CAKES — administrator dashboard. Every number is a live query.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

$today = date('Y-m-d');

$stats = [
    'orders_total'   => (int) Database::value('SELECT COUNT(*) FROM orders', [], 0),
    'orders_pending' => (int) Database::value("SELECT COUNT(*) FROM orders WHERE status = 'pending'", [], 0),
    'orders_today'   => (int) Database::value('SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?', [$today], 0),
    'orders_active'  => (int) Database::value("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','preparing','ready','out_for_delivery')", [], 0),
    'revenue_paid'   => (float) Database::value("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid'", [], 0),
    'custom_pending' => (int) Database::value("SELECT COUNT(*) FROM custom_orders WHERE status IN ('pending','awaiting_quotation')", [], 0),
    'customers'      => (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'customer'", [], 0),
    'products'       => (int) Database::value('SELECT COUNT(*) FROM products WHERE is_active = 1', [], 0),
    'unpriced'       => (int) Database::value(
        "SELECT COUNT(*) FROM products p WHERE p.is_active = 1 AND p.base_price = 0
           AND NOT EXISTS (SELECT 1 FROM product_sizes ps WHERE ps.product_id = p.id AND ps.is_active = 1 AND ps.price > 0)",
        [],
        0
    ),
    'pending_payments' => (int) Database::value("SELECT COUNT(*) FROM payments WHERE status = 'pending'", [], 0),
];

$recentOrders = Database::fetchAll(
    'SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 8'
);
$recentCustom = Database::fetchAll(
    'SELECT * FROM custom_orders ORDER BY created_at DESC, id DESC LIMIT 5'
);
$recentReviews = Database::fetchAll(
    "SELECT r.*, p.name AS product_name FROM reviews r LEFT JOIN products p ON p.id = r.product_id
     WHERE r.is_approved = 0 ORDER BY r.created_at DESC LIMIT 5"
);
$recentMessages = Database::fetchAll('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5');

$adminTitle  = 'Dashboard';
$adminActive = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="stat-grid admin-stats">
  <div class="stat-card">
    <span class="stat-label">Orders</span>
    <strong class="stat-value"><?= $stats['orders_total'] ?></strong>
    <span class="stat-muted"><?= $stats['orders_today'] ?> placed today</span>
  </div>
  <div class="stat-card">
    <span class="stat-label">Waiting for confirmation</span>
    <strong class="stat-value"><?= $stats['orders_pending'] ?></strong>
    <a href="<?= e(url('admin/orders.php?status=pending')) ?>">Review orders</a>
  </div>
  <div class="stat-card">
    <span class="stat-label">In the kitchen</span>
    <strong class="stat-value"><?= $stats['orders_active'] ?></strong>
    <span class="stat-muted">Confirmed, preparing, ready or out</span>
  </div>
  <div class="stat-card">
    <span class="stat-label">Verified revenue</span>
    <strong class="stat-value stat-value-sm"><?= e(money($stats['revenue_paid'])) ?></strong>
    <span class="stat-muted">Only orders marked paid</span>
  </div>
</div>

<div class="stat-grid admin-stats">
  <div class="stat-card">
    <span class="stat-label">Custom requests waiting</span>
    <strong class="stat-value"><?= $stats['custom_pending'] ?></strong>
    <a href="<?= e(url('admin/custom-requests.php')) ?>">Open requests</a>
  </div>
  <div class="stat-card">
    <span class="stat-label">Customers</span>
    <strong class="stat-value"><?= $stats['customers'] ?></strong>
    <a href="<?= e(url('admin/customers.php')) ?>">Manage customers</a>
  </div>
  <div class="stat-card">
    <span class="stat-label">Active cakes</span>
    <strong class="stat-value"><?= $stats['products'] ?></strong>
    <a href="<?= e(url('admin/products.php')) ?>">Manage products</a>
  </div>
  <div class="stat-card<?= $stats['pending_payments'] > 0 ? ' stat-alert' : '' ?>">
    <span class="stat-label">Payments to verify</span>
    <strong class="stat-value"><?= $stats['pending_payments'] ?></strong>
    <a href="<?= e(url('admin/payments.php')) ?>">Verify payments</a>
  </div>
</div>

<?php if ($stats['unpriced'] > 0): ?>
  <p class="note note-warning">
    <?= $stats['unpriced'] ?> active cake<?= $stats['unpriced'] === 1 ? ' has' : 's have' ?> no price yet, so customers cannot order them.
    <a href="<?= e(url('admin/products.php?filter=unpriced')) ?>">Add prices</a>.
  </p>
<?php endif; ?>

<div class="admin-columns">
  <section class="admin-panel">
    <div class="panel-head">
      <h2>Recent orders</h2>
      <a href="<?= e(url('admin/orders.php')) ?>">All orders</a>
    </div>
    <?php if ($recentOrders): ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Reference</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($recentOrders as $order): ?>
              <tr>
                <td><a href="<?= e(url('admin/order.php?id=' . (int) $order['id'])) ?>"><?= e($order['order_reference']) ?></a><br><small><?= e(format_date($order['created_at'])) ?></small></td>
                <td><?= e($order['customer_name']) ?></td>
                <td><?= e(money($order['total'])) ?></td>
                <td><?= status_badge($order['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="admin-empty">No orders yet. Orders placed on the website appear here.</p>
    <?php endif; ?>
  </section>

  <section class="admin-panel">
    <div class="panel-head">
      <h2>Needs attention</h2>
    </div>
    <ul class="admin-attention">
      <li>
        <span>Unapproved reviews</span>
        <a href="<?= e(url('admin/reviews.php')) ?>"><?= count($recentReviews) > 0 ? (int) Database::value('SELECT COUNT(*) FROM reviews WHERE is_approved = 0', [], 0) : 0 ?></a>
      </li>
      <li>
        <span>Unread messages</span>
        <a href="<?= e(url('admin/messages.php')) ?>"><?= (int) Database::value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0', [], 0) ?></a>
      </li>
      <li>
        <span>Payments pending</span>
        <a href="<?= e(url('admin/payments.php')) ?>"><?= $stats['pending_payments'] ?></a>
      </li>
      <li>
        <span>Custom quotations to send</span>
        <a href="<?= e(url('admin/custom-requests.php')) ?>"><?= $stats['custom_pending'] ?></a>
      </li>
    </ul>

    <h3 class="panel-subhead">Latest custom requests</h3>
    <?php if ($recentCustom): ?>
      <ul class="admin-mini-list">
        <?php foreach ($recentCustom as $request): ?>
          <li>
            <a href="<?= e(url('admin/custom-request.php?id=' . (int) $request['id'])) ?>"><?= e($request['order_reference']) ?></a>
            <span><?= e($request['customer_name']) ?> · <?= e(ucfirst(str_replace('_', ' ', (string) $request['status']))) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="admin-empty">No custom requests yet.</p>
    <?php endif; ?>

    <h3 class="panel-subhead">Latest messages</h3>
    <?php if ($recentMessages): ?>
      <ul class="admin-mini-list">
        <?php foreach ($recentMessages as $message): ?>
          <li>
            <a href="<?= e(url('admin/messages.php')) ?>"><?= e($message['name']) ?></a>
            <span><?= e(excerpt($message['message'], 60)) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="admin-empty">No messages yet.</p>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
