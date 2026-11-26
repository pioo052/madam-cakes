<?php
/**
 * MADAM CAKES — admin single order: status workflow, payments, notes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();
$orderId = (int) get('id');
$order = $orderId > 0 ? Database::fetch('SELECT * FROM orders WHERE id = ?', [$orderId]) : null;

if (!$order) {
    http_response_code(404);
    $adminTitle = 'Order not found';
    $adminActive = 'orders';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h2>Order not found</h2><a class="btn btn-primary" href="' . e(url('admin/orders.php')) . '">Back to orders</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$paymentMethods = ['cash', 'mpesa', 'airtel_money', 'mixx_by_yas', 'bank_transfer', 'other'];

if (is_post()) {
    require_csrf();
    $action = (string) post('action');

    if ($action === 'status') {
        $newStatus = (string) post('status');
        $note      = trim((string) post('status_note'));
        if (array_key_exists($newStatus, order_statuses())) {
            order_change_status((int) $order['id'], $newStatus, $note ?: null, (int) $admin['id']);
            log_admin_action('order.status', 'order', (int) $order['id'], $order['order_reference'] . ' → ' . $newStatus);
            flash('success', 'Order status updated to ' . order_status_label($newStatus) . '. The customer has been notified.');
        } else {
            flash('error', 'Unknown order status.');
        }
    } elseif ($action === 'verify_payment') {
        $paymentId = (int) post('payment_id');
        $payment = Database::fetch('SELECT * FROM payments WHERE id = ? AND order_id = ?', [$paymentId, (int) $order['id']]);
        if ($payment && $payment['status'] !== 'verified') {
            Database::begin();
            Database::execute(
                "UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW(), paid_at = COALESCE(paid_at, NOW()) WHERE id = ?",
                [(int) $admin['id'], $paymentId]
            );
            Database::execute("UPDATE orders SET payment_status = 'paid' WHERE id = ?", [(int) $order['id']]);
            Database::commit();

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
            log_admin_action('payment.verify', 'payment', $paymentId, 'Verified ' . money($payment['amount']));
            flash('success', 'Payment verified. The order is marked paid.');
        } else {
            flash('error', 'That payment could not be verified.');
        }
    } elseif ($action === 'reject_payment') {
        $paymentId = (int) post('payment_id');
        Database::execute(
            "UPDATE payments SET status = 'rejected', verified_by = ?, verified_at = NOW(), notes = ? WHERE id = ? AND order_id = ?",
            [(int) $admin['id'], 'Rejected by the studio.', $paymentId, (int) $order['id']]
        );
        log_admin_action('payment.reject', 'payment', $paymentId, 'Rejected a payment.');
        flash('success', 'Payment marked as rejected.');
    } elseif ($action === 'add_payment') {
        $amount = (float) post('amount');
        $method = (string) post('method');
        $ref    = trim((string) post('provider_reference'));
        $verified = !empty(post('verified'));

        if ($amount <= 0) {
            flash('error', 'Enter the amount received.');
        } elseif (!in_array($method, $paymentMethods, true)) {
            flash('error', 'Choose a payment method.');
        } else {
            Database::begin();
            Database::insert(
                'INSERT INTO payments (order_id, method, amount, provider_reference, status, paid_at, verified_by, verified_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) $order['id'], $method, $amount, $ref ?: null,
                    $verified ? 'verified' : 'pending',
                    trim((string) post('paid_at')) ?: null,
                    $verified ? (int) $admin['id'] : null,
                    $verified ? date('Y-m-d H:i:s') : null,
                ]
            );
            if ($verified) {
                Database::execute("UPDATE orders SET payment_status = 'paid' WHERE id = ?", [(int) $order['id']]);
            }
            Database::commit();
            log_admin_action('payment.create', 'order', (int) $order['id'], 'Recorded ' . money($amount) . ' via ' . $method);
            flash('success', 'Payment recorded.');
        }
    } elseif ($action === 'notes') {
        Database::execute('UPDATE orders SET admin_notes = ? WHERE id = ?', [trim((string) post('admin_notes')) ?: null, (int) $order['id']]);
        flash('success', 'Internal notes saved.');
    }

    redirect('admin/order.php?id=' . (int) $order['id']);
}

$items    = order_items((int) $order['id']);
$history  = order_history((int) $order['id']);
$payments = order_payments((int) $order['id']);

$adminTitle  = 'Order ' . $order['order_reference'];
$adminActive = 'orders';
require __DIR__ . '/includes/header.php';
?>

<p class="admin-back"><a href="<?= e(url('admin/orders.php')) ?>">&larr; All orders</a></p>

<div class="admin-columns admin-columns-wide">
  <section class="admin-panel">
    <div class="panel-head">
      <h2>Order details</h2>
      <?= status_badge($order['status']) ?>
    </div>

    <dl class="detail-list">
      <div><dt>Reference</dt><dd><?= e($order['order_reference']) ?></dd></div>
      <div><dt>Placed</dt><dd><?= e(format_datetime($order['created_at'])) ?></dd></div>
      <div><dt>Customer</dt><dd><?= e($order['customer_name']) ?><br><?= e($order['customer_phone']) ?><?= $order['customer_email'] ? '<br>' . e($order['customer_email']) : '' ?></dd></div>
      <?php if ($order['user_id']): ?>
        <div><dt>Account</dt><dd><a href="<?= e(url('admin/customer.php?id=' . (int) $order['user_id'])) ?>">View customer</a></dd></div>
      <?php else: ?>
        <div><dt>Account</dt><dd>Guest order</dd></div>
      <?php endif; ?>
      <div><dt>Fulfilment</dt><dd><?= e(ucfirst((string) $order['fulfilment'])) ?><?= $order['fulfilment'] === 'delivery' ? ' — ' . e(trim(implode(', ', array_filter([(string) $order['street_address'], (string) $order['area'], (string) $order['city'], (string) $order['region']])))) : '' ?></dd></div>
      <div><dt>Needed for</dt><dd><?= e(format_date($order['delivery_date'])) ?><?= $order['delivery_time'] ? ' · ' . e($order['delivery_time']) : '' ?></dd></div>
      <div><dt>Payment method</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) ($order['payment_method'] ?? '—')))) ?> · <?= payment_badge($order['payment_status']) ?></dd></div>
      <?php if ((int) $order['is_surprise'] === 1): ?>
        <div><dt>Surprise</dt><dd><?= e((string) $order['recipient_name']) ?> · <?= e((string) $order['recipient_phone']) ?><?= $order['gift_message'] ? '<br><em>' . e($order['gift_message']) . '</em>' : '' ?></dd></div>
      <?php endif; ?>
    </dl>

    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Cake</th><th>Options</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['product_name']) ?></td>
              <td><?= e(trim(implode(' · ', array_filter([(string) $item['size_name'], (string) $item['flavour_name'], (string) $item['addons_json']])))) ?: '—' ?></td>
              <td><?= (int) $item['quantity'] ?></td>
              <td><?= e(money($item['unit_price'])) ?></td>
              <td><?= e(money($item['line_total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <dl class="summary-list">
      <div><dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd></div>
      <?php if ((float) $order['discount_total'] > 0): ?>
        <div class="summary-discount"><dt>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></dt><dd>−<?= e(money($order['discount_total'])) ?></dd></div>
      <?php endif; ?>
      <div><dt>Delivery</dt><dd><?= e(money($order['delivery_fee'])) ?></dd></div>
      <div class="summary-total"><dt>Total</dt><dd><?= e(money($order['total'])) ?></dd></div>
    </dl>

    <?php if ($order['customer_notes'] || $order['delivery_instructions']): ?>
      <div class="admin-notes">
        <h3>Customer notes</h3>
        <?php if ($order['customer_notes']): ?><p><?= nl2br(e($order['customer_notes'])) ?></p><?php endif; ?>
        <?php if ($order['delivery_instructions']): ?><p><?= nl2br(e($order['delivery_instructions'])) ?></p><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="admin-stack">
    <section class="admin-panel">
      <div class="panel-head"><h2>Update status</h2></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <label class="field">
          <span>Status</span>
          <select name="status">
            <?php foreach (order_statuses() as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= $order['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">
          <span>Note for the customer (optional)</span>
          <textarea name="status_note" maxlength="255"></textarea>
        </label>
        <button type="submit" class="btn btn-primary btn-block">Save status</button>
      </form>
    </section>

    <section class="admin-panel">
      <div class="panel-head"><h2>Payments</h2></div>
      <?php if ($payments): ?>
        <ul class="payment-list">
          <?php foreach ($payments as $payment): ?>
            <li>
              <div>
                <strong><?= e(money($payment['amount'])) ?> · <?= e(ucwords(str_replace('_', ' ', (string) $payment['method']))) ?></strong>
                <span><?= payment_badge($payment['status']) ?> <?= $payment['provider_reference'] ? '· ' . e($payment['provider_reference']) : '' ?></span>
                <small><?= e(format_datetime($payment['created_at'])) ?></small>
              </div>
              <?php if ($payment['status'] === 'pending'): ?>
                <div class="payment-actions">
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify_payment">
                    <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-small">Verify</button>
                  </form>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject_payment">
                    <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                    <button type="submit" class="link-button link-danger">Reject</button>
                  </form>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="admin-empty">No payments recorded.</p>
      <?php endif; ?>

      <h3 class="panel-subhead">Record a payment</h3>
      <form method="post" class="compact-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_payment">
        <label class="field"><span>Amount</span><input type="number" name="amount" step="0.01" min="0" required value="<?= e((string) $order['total']) ?>"></label>
        <label class="field">
          <span>Method</span>
          <select name="method">
            <?php foreach ($paymentMethods as $method): ?>
              <option value="<?= e($method) ?>"><?= e(ucwords(str_replace('_', ' ', $method))) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field"><span>Provider reference</span><input type="text" name="provider_reference" maxlength="120"></label>
        <label class="field"><span>Paid at</span><input type="date" name="paid_at" value="<?= e(date('Y-m-d')) ?>"></label>
        <label class="checkbox-line"><input type="checkbox" name="verified" value="1"><span>Mark as verified immediately</span></label>
        <button type="submit" class="btn btn-outline btn-block btn-small">Record payment</button>
      </form>
    </section>

    <section class="admin-panel">
      <div class="panel-head"><h2>Internal notes</h2></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="notes">
        <label class="field">
          <span>Visible to administrators only</span>
          <textarea name="admin_notes" maxlength="2000"><?= e((string) $order['admin_notes']) ?></textarea>
        </label>
        <button type="submit" class="btn btn-ghost btn-small">Save notes</button>
      </form>
    </section>
  </div>
</div>

<section class="admin-panel">
  <div class="panel-head"><h2>History</h2></div>
  <ol class="timeline">
    <?php foreach ($history as $step): ?>
      <li class="timeline-step">
        <span class="timeline-dot" aria-hidden="true"></span>
        <div>
          <strong><?= e(order_status_label($step['status'])) ?></strong>
          <time datetime="<?= e($step['created_at']) ?>"><?= e(format_datetime($step['created_at'])) ?></time>
          <?php if ($step['note']): ?><p><?= e($step['note']) ?></p><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
