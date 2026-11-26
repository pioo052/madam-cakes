<?php
/**
 * MADAM CAKES — admin payments: verify or reject money received.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

if (is_post()) {
    require_csrf();
    $paymentId = (int) post('payment_id');
    $action    = (string) post('action');
    $payment   = Database::fetch('SELECT * FROM payments WHERE id = ?', [$paymentId]);

    if (!$payment) {
        flash('error', 'Payment not found.');
    } elseif (!in_array($payment['status'], ['pending'], true)) {
        flash('warning', 'Only pending payments can be changed.');
    } elseif ($action === 'verify') {
        Database::begin();
        Database::execute(
            "UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW(), paid_at = COALESCE(paid_at, NOW()) WHERE id = ?",
            [(int) $admin['id'], $paymentId]
        );
        Database::execute("UPDATE orders SET payment_status = 'paid' WHERE id = ?", [(int) $payment['order_id']]);
        Database::commit();

        $order = Database::fetch('SELECT * FROM orders WHERE id = ?', [(int) $payment['order_id']]);
        if ($order) {
            if ($order['status'] === 'pending') {
                order_change_status((int) $order['id'], 'confirmed', 'Payment verified by the studio.', (int) $admin['id']);
            } else {
                notify_customer(
                    $order['user_id'] !== null ? (int) $order['user_id'] : null,
                    'Payment verified for ' . $order['order_reference'],
                    'We have verified your payment. Thank you!',
                    'payment',
                    'account/order.php?id=' . (int) $order['id']
                );
            }
        }
        log_admin_action('payment.verify', 'payment', $paymentId, 'Verified ' . money($payment['amount']));
        flash('success', 'Payment verified.');
    } elseif ($action === 'reject') {
        Database::execute("UPDATE payments SET status = 'rejected', verified_by = ?, verified_at = NOW() WHERE id = ?", [(int) $admin['id'], $paymentId]);
        log_admin_action('payment.reject', 'payment', $paymentId, 'Rejected a payment.');
        flash('success', 'Payment rejected.');
    }
    redirect('admin/payments.php');
}

$status = (string) get('status');
if (!in_array($status, ['pending', 'verified', 'rejected', 'refunded'], true)) {
    $status = '';
}

$sql = 'SELECT p.*, o.order_reference, o.customer_name, o.total AS order_total
        FROM payments p JOIN orders o ON o.id = p.order_id';
$params = [];
if ($status !== '') {
    $sql .= ' WHERE p.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT 200';
$payments = Database::fetchAll($sql, $params);

$adminTitle  = 'Payments';
$adminActive = 'payments';
require __DIR__ . '/includes/header.php';
?>

<nav class="filter-chips" aria-label="Filter payments">
  <a class="filter-chip<?= $status === '' ? ' is-active' : '' ?>" href="<?= e(url('admin/payments.php')) ?>">All</a>
  <?php foreach (['pending', 'verified', 'rejected', 'refunded'] as $key): ?>
    <a class="filter-chip<?= $status === $key ? ' is-active' : '' ?>" href="<?= e(url('admin/payments.php?status=' . $key)) ?>"><?= e(ucfirst($key)) ?></a>
  <?php endforeach; ?>
</nav>

<section class="admin-panel">
  <div class="panel-head"><h2><?= count($payments) ?> payment<?= count($payments) === 1 ? '' : 's' ?></h2></div>
  <?php if ($payments): ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Created</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <tr>
              <td><a href="<?= e(url('admin/order.php?id=' . (int) $payment['order_id'])) ?>"><?= e($payment['order_reference']) ?></a></td>
              <td><?= e($payment['customer_name']) ?></td>
              <td><?= e(money($payment['amount'])) ?><br><small>of <?= e(money($payment['order_total'])) ?></small></td>
              <td><?= e(ucwords(str_replace('_', ' ', (string) $payment['method']))) ?></td>
              <td><?= e((string) ($payment['provider_reference'] ?? '—')) ?></td>
              <td><?= payment_badge($payment['status']) ?></td>
              <td><?= e(format_datetime($payment['created_at'])) ?></td>
              <td>
                <?php if ($payment['status'] === 'pending'): ?>
                  <div class="payment-actions">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><button type="submit" class="btn btn-primary btn-small">Verify</button></form>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><button type="submit" class="link-button link-danger">Reject</button></form>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="admin-empty">No payments in this view.</p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
