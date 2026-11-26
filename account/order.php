<?php
/**
 * MADAM CAKES — one order: items, totals, delivery details and live timeline.
 * Ownership is enforced in the query, not in the view.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];
$orderId     = (int) get('id');

$order = $orderId > 0 ? order_for_user($orderId, $userId) : null;
if (!$order) {
    http_response_code(404);
    $accountActive = 'orders';
    $pageTitle = 'Order not found';
    require __DIR__ . '/../includes/header.php';
    echo '<main id="main"><section class="section"><div class="container empty-state"><h1>Order not found</h1><p>That order does not belong to your account.</p><a class="btn btn-primary" href="' . e(url('account/orders.php')) . '">My orders</a></div></section></main>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if (is_post()) {
    require_csrf();
    if ((string) post('action') === 'cancel' && in_array($order['status'], ['pending', 'awaiting_payment'], true)) {
        order_change_status((int) $order['id'], 'cancelled', 'Cancelled by the customer.', null);
        notify_admin('Order cancelled by customer', $order['order_reference'] . ' was cancelled.', 'order', 'admin/order.php?id=' . (int) $order['id']);
        flash('success', 'Your order has been cancelled. The studio has been notified.');
    } else {
        flash('warning', 'This order can no longer be cancelled online. Please contact the studio.');
    }
    redirect('account/order.php?id=' . (int) $order['id']);
}

$items   = order_items((int) $order['id']);
$history = order_history((int) $order['id']);
$payments = order_payments((int) $order['id']);
$placed  = (string) get('placed') === '1';

$accountActive   = 'orders';
$pageTitle       = 'Order ' . $order['order_reference'];
$pageDescription = 'Details and progress for order ' . $order['order_reference'] . '.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow"><a href="<?= e(url('account/orders.php')) ?>">&larr; All orders</a></p>
      <h1 class="section-title">Order <?= e($order['order_reference']) ?></h1>
      <p class="section-sub">Placed <?= e(format_datetime($order['created_at'])) ?> · <?= e(ucfirst((string) $order['fulfilment'])) ?><?= $order['delivery_date'] ? ' for ' . e(format_date($order['delivery_date'])) : '' ?></p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <?php if ($placed): ?>
          <p class="note note-success">Thank you — your order has been received. The studio will confirm it during opening hours.</p>
        <?php endif; ?>

        <div class="order-panel">
          <div class="order-panel-head">
            <div>
              <span class="stat-label">Current status</span>
              <h2 class="section-title"><?= e(order_status_label($order['status'])) ?></h2>
            </div>
            <div class="order-panel-badges">
              <?= status_badge($order['status']) ?>
              <?= payment_badge($order['payment_status']) ?>
            </div>
          </div>

          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr><th scope="col">Cake</th><th scope="col">Size / flavour</th><th scope="col">Qty</th><th scope="col">Unit</th><th scope="col">Total</th></tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td>
                      <?php if ($item['product_id']): ?>
                        <a href="<?= e(product_url((string) Database::value('SELECT slug FROM products WHERE id = ?', [(int) $item['product_id']], ''))) ?>"><?= e($item['product_name']) ?></a>
                      <?php else: ?>
                        <?= e($item['product_name']) ?>
                      <?php endif; ?>
                    </td>
                    <td><?= e(trim(implode(' · ', array_filter([(string) $item['size_name'], (string) $item['flavour_name']])))) ?: '—' ?></td>
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
        </div>

        <div class="account-columns">
          <section class="account-block">
            <h2 class="section-title">Delivery details</h2>
            <dl class="detail-list">
              <div><dt>Method</dt><dd><?= e(ucfirst((string) $order['fulfilment'])) ?></dd></div>
              <?php if ($order['fulfilment'] === 'delivery'): ?>
                <div><dt>Address</dt><dd><?= e(trim(implode(', ', array_filter([(string) $order['street_address'], (string) $order['area'], (string) $order['city'], (string) $order['region']])))) ?></dd></div>
              <?php endif; ?>
              <div><dt>Date</dt><dd><?= e(format_date($order['delivery_date']) ?: 'To be confirmed') ?></dd></div>
              <?php if ($order['delivery_time']): ?><div><dt>Time</dt><dd><?= e($order['delivery_time']) ?></dd></div><?php endif; ?>
              <div><dt>Contact</dt><dd><?= e($order['customer_name']) ?><br><?= e($order['customer_phone']) ?></dd></div>
              <?php if ((int) $order['is_surprise'] === 1): ?>
                <div><dt>Surprise for</dt><dd><?= e((string) $order['recipient_name']) ?><?= $order['gift_message'] ? '<br><em>“' . e($order['gift_message']) . '”</em>' : '' ?></dd></div>
              <?php endif; ?>
            </dl>
            <?php if ($order['delivery_instructions'] || $order['customer_notes']): ?>
              <p class="text-muted"><?= e($order['delivery_instructions'] ?: $order['customer_notes']) ?></p>
            <?php endif; ?>
          </section>

          <section class="account-block">
            <h2 class="section-title">Payment</h2>
            <dl class="detail-list">
              <div><dt>Method</dt><dd><?= e(ucwords(str_replace('_', ' ', (string) ($order['payment_method'] ?? '—')))) ?></dd></div>
              <div><dt>Status</dt><dd><?= payment_badge($order['payment_status']) ?></dd></div>
              <?php foreach ($payments as $payment): ?>
                <div><dt><?= e(ucfirst((string) $payment['status'])) ?> payment</dt><dd><?= e(money($payment['amount'])) ?><?= $payment['paid_at'] ? ' · ' . e(format_date($payment['paid_at'])) : '' ?></dd></div>
              <?php endforeach; ?>
            </dl>
            <p class="note"><?= e((string) setting('payment_instructions', 'Payment is confirmed by the studio after verification.')) ?></p>
            <?php if (in_array($order['status'], ['pending', 'awaiting_payment'], true) && $order['payment_status'] === 'unpaid'): ?>
              <form method="post" class="cancel-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="link-button">Cancel this order</button>
              </form>
            <?php endif; ?>
          </section>
        </div>

        <section class="account-block">
          <h2 class="section-title">Order progress</h2>
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
          <p class="account-footnote">Track this order from any device with <a href="<?= e(url('track.php')) ?>">order tracking</a>.</p>
        </section>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
