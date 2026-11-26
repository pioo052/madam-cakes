<?php
/**
 * MADAM CAKES — all orders belonging to the signed-in customer.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];

$status = (string) get('status');
if ($status !== '' && !array_key_exists($status, order_statuses())) {
    $status = '';
}

$sql = 'SELECT * FROM orders WHERE user_id = ?';
$params = [$userId];
if ($status !== '') {
    $sql .= ' AND status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY created_at DESC, id DESC';
$orders = Database::fetchAll($sql, $params);

$accountActive   = 'orders';
$pageTitle       = 'My orders';
$pageDescription = 'Every MADAM CAKES order on your account.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">My orders</h1>
      <p class="section-sub">Follow every cake from the studio to your door.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <nav class="filter-chips" aria-label="Filter orders">
          <a class="filter-chip<?= $status === '' ? ' is-active' : '' ?>" href="<?= e(url('account/orders.php')) ?>">All</a>
          <?php foreach (['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'cancelled'] as $key): ?>
            <a class="filter-chip<?= $status === $key ? ' is-active' : '' ?>" href="<?= e(url('account/orders.php?status=' . $key)) ?>"><?= e(order_status_label($key)) ?></a>
          <?php endforeach; ?>
        </nav>

        <?php if ($orders): ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th scope="col">Reference</th>
                  <th scope="col">Date</th>
                  <th scope="col">Items</th>
                  <th scope="col">Total</th>
                  <th scope="col">Status</th>
                  <th scope="col">Payment</th>
                  <th scope="col"><span class="visually-hidden">Open</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $order): ?>
                  <?php $itemCount = (int) Database::value('SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE order_id = ?', [(int) $order['id']], 0); ?>
                  <tr>
                    <td><a href="<?= e(url('account/order.php?id=' . (int) $order['id'])) ?>"><?= e($order['order_reference']) ?></a></td>
                    <td><?= e(format_date($order['created_at'])) ?></td>
                    <td><?= $itemCount ?></td>
                    <td><?= e(money($order['total'])) ?></td>
                    <td><?= status_badge($order['status']) ?></td>
                    <td><?= payment_badge($order['payment_status']) ?></td>
                    <td><a class="btn btn-ghost btn-small" href="<?= e(url('account/order.php?id=' . (int) $order['id'])) ?>">View</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <h2><?= $status === '' ? 'No orders yet' : 'No orders with that status' ?></h2>
            <p><?= $status === '' ? 'When you place your first order it will appear here with live progress.' : 'Try a different filter to see your other orders.' ?></p>
            <a class="btn btn-primary" href="<?= e(url('cakes.php')) ?>">Browse cakes</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
