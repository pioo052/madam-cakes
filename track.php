<?php
/**
 * MADAM CAKES — public order tracking.
 * A reference alone is not enough: the customer must also confirm the email
 * or phone on the order.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

$reference = '';
$contact   = '';
$order     = null;
$history   = [];
$items     = [];
$error     = '';

if (is_post()) {
    require_csrf();
    $reference = strtoupper(trim((string) post('reference')));
    $contact   = strtolower(trim((string) post('contact')));

    if ($reference === '' || $contact === '') {
        $error = 'Enter your order reference and the email or phone used for the order.';
    } else {
        $candidate = order_by_reference($reference);
        if (!$candidate) {
            $error = 'We could not find an order with that reference.';
        } else {
            $email = strtolower(trim((string) ($candidate['customer_email'] ?? '')));
            $phone = preg_replace('/\D+/', '', (string) $candidate['customer_phone']) ?? '';
            $given = preg_replace('/\D+/', '', $contact) ?? '';
            $matches = ($email !== '' && $email === $contact)
                || ($phone !== '' && $given !== '' && (str_ends_with($phone, $given) || $phone === $given));

            if (!$matches) {
                $error = 'The contact detail does not match that order.';
            } else {
                $order   = $candidate;
                $items   = order_items((int) $order['id']);
                $history = order_history((int) $order['id']);
            }
        }
    }
}

$pageTitle       = 'Track your order';
$pageDescription = 'Follow the progress of your MADAM CAKES order.';
$activeNav       = 'cakes';
require __DIR__ . '/includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">Order tracking</p>
      <h1 class="section-title">Where is my cake?</h1>
      <p class="section-sub">Enter your reference and the contact detail you used when ordering.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container tracking-wrap">
      <form method="post" class="form-card">
        <?= csrf_field() ?>
        <?php if ($error !== ''): ?><p class="note note-error"><?= e($error) ?></p><?php endif; ?>
        <label class="field">
          <span>Order reference</span>
          <input type="text" name="reference" value="<?= e($reference) ?>" placeholder="MC-YYMMDD-XXXXXX" required maxlength="24">
        </label>
        <label class="field">
          <span>Email or phone on the order</span>
          <input type="text" name="contact" value="<?= e($contact) ?>" required maxlength="190">
        </label>
        <button type="submit" class="btn btn-primary btn-block">Track order</button>
      </form>

      <?php if ($order): ?>
        <section class="order-panel">
          <div class="order-panel-head">
            <div>
              <p class="eyebrow"><?= e($order['order_reference']) ?></p>
              <h2 class="section-title"><?= e(order_status_label($order['status'])) ?></h2>
              <p class="section-sub">Placed <?= e(format_date($order['created_at'])) ?><?= $order['delivery_date'] ? ' · needed for ' . e(format_date($order['delivery_date'])) : '' ?></p>
            </div>
            <?= payment_badge($order['payment_status']) ?>
          </div>

          <ul class="order-items">
            <?php foreach ($items as $item): ?>
              <li>
                <span><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?><?= $item['size_name'] ? ' <small>' . e($item['size_name']) . '</small>' : '' ?></span>
                <span><?= e(money($item['line_total'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>

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

          <p><a class="btn btn-outline" href="<?= e(url('auth/login.php')) ?>">Sign in for full order details</a></p>
        </section>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
