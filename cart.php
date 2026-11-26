<?php
/**
 * MADAM CAKES — shopping cart. Every line is repriced from the database.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

$user = auth_user();

if (is_post()) {
    require_csrf();
    $action = (string) post('action');

    if ($action === 'update') {
        cart_set_quantity((int) post('item_id'), (int) post('quantity', 1));
        flash('success', 'Cart updated.');
    } elseif ($action === 'remove') {
        cart_remove((int) post('item_id'));
        flash('success', 'Item removed from your cart.');
    } elseif ($action === 'coupon') {
        $code = strtoupper(trim((string) post('coupon_code')));
        $cart = cart_lines();
        $check = coupon_validate($code, $cart['subtotal'], $user ? (int) $user['id'] : null);
        if ($check['ok']) {
            $_SESSION['coupon'] = $code;
            flash('success', $check['message']);
        } else {
            unset($_SESSION['coupon']);
            flash('error', $check['message']);
        }
    } elseif ($action === 'remove_coupon') {
        unset($_SESSION['coupon']);
        flash('success', 'Coupon removed.');
    }

    redirect('cart.php');
}

$totals = cart_totals($user ? (int) $user['id'] : null);
$lines  = $totals['cart']['lines'];

$pageTitle       = 'Your cart';
$pageDescription = 'Review the cakes in your MADAM CAKES cart before checkout.';
$activeNav       = 'cakes';
require __DIR__ . '/includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">Almost there</p>
      <h1 class="section-title">Your cart</h1>
      <p class="section-sub"><?= $totals['cart']['count'] ?> item<?= $totals['cart']['count'] === 1 ? '' : 's' ?> ready for celebration.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container">
      <?php if ($totals['message'] !== ''): ?>
        <p class="note note-warning"><?= e($totals['message']) ?></p>
      <?php endif; ?>

      <?php if ($lines === []): ?>
        <div class="empty-state">
          <h2>Your cart is empty</h2>
          <p>Browse the cake menu and add something sweet to begin.</p>
          <a class="btn btn-primary" href="<?= e(url('cakes.php')) ?>">Browse cakes</a>
        </div>
      <?php else: ?>
        <div class="cart-layout">
          <div class="cart-lines">
            <?php foreach ($lines as $line): ?>
              <article class="cart-line<?= $line['available'] ? '' : ' is-unavailable' ?>">
                <a class="cart-thumb" href="<?= e(product_url($line['slug'])) ?>">
                  <?php if ($line['image'] !== ''): ?>
                    <img src="<?= e(upload_url($line['image'])) ?>" alt="" width="120" height="120">
                  <?php else: ?>
                    <span class="cake-media-empty">No photo</span>
                  <?php endif; ?>
                </a>
                <div class="cart-line-body">
                  <h2 class="cart-line-name"><a href="<?= e(product_url($line['slug'])) ?>"><?= e($line['name']) ?></a></h2>
                  <p class="cart-line-meta">
                    <?php if ($line['size_name']): ?><?= e($line['size_name']) ?><?php endif; ?>
                    <?php if ($line['flavour_name']): ?> · <?= e($line['flavour_name']) ?><?php endif; ?>
                  </p>
                  <?php if ($line['addons']): ?>
                    <p class="cart-line-meta">Extras: <?= e(implode(', ', array_column($line['addons'], 'name'))) ?></p>
                  <?php endif; ?>
                  <p class="cart-line-price"><?= e(money($line['unit_price'])) ?> each</p>
                  <?php if (!$line['available']): ?>
                    <p class="note note-warning">This cake or one of its options is no longer available. Remove it to continue.</p>
                  <?php endif; ?>
                </div>
                <div class="cart-line-actions">
                  <?php if ($line['available']): ?>
                    <form method="post" class="qty-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="item_id" value="<?= (int) $line['id'] ?>">
                      <label class="visually-hidden" for="qty-<?= (int) $line['id'] ?>">Quantity</label>
                      <input id="qty-<?= (int) $line['id'] ?>" type="number" name="quantity" value="<?= (int) $line['quantity'] ?>" min="1" max="20" inputmode="numeric">
                      <button type="submit" class="btn btn-ghost btn-small">Update</button>
                    </form>
                  <?php endif; ?>
                  <p class="cart-line-total"><?= e(money($line['line_total'])) ?></p>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="item_id" value="<?= (int) $line['id'] ?>">
                    <button type="submit" class="link-button">Remove</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>

            <div class="cart-actions">
              <a class="btn btn-outline" href="<?= e(url('cakes.php')) ?>">Continue shopping</a>
            </div>
          </div>

          <aside class="cart-summary">
            <h2>Order summary</h2>
            <dl class="summary-list">
              <div><dt>Subtotal</dt><dd><?= e(money($totals['cart']['subtotal'])) ?></dd></div>
              <?php if ($totals['coupon']): ?>
                <div class="summary-discount">
                  <dt>Coupon <?= e($totals['coupon']['code']) ?></dt>
                  <dd>−<?= e(money($totals['discount'])) ?></dd>
                </div>
              <?php endif; ?>
              <div class="summary-total"><dt>Total</dt><dd><?= e(money($totals['total'])) ?></dd></div>
            </dl>
            <p class="summary-note">Delivery is calculated at checkout based on your zone.</p>

            <?php if ($totals['coupon']): ?>
              <form method="post" class="coupon-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_coupon">
                <button type="submit" class="link-button">Remove coupon</button>
              </form>
            <?php else: ?>
              <form method="post" class="coupon-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="coupon">
                <label class="field">
                  <span>Coupon code</span>
                  <input type="text" name="coupon_code" maxlength="40" placeholder="Enter code">
                </label>
                <button type="submit" class="btn btn-ghost btn-small">Apply coupon</button>
              </form>
            <?php endif; ?>

            <?php if ($totals['cart']['unavailable']): ?>
              <p class="note note-warning">Remove unavailable items before checking out.</p>
            <?php else: ?>
              <a class="btn btn-primary btn-block" href="<?= e(url('checkout.php')) ?>">
                <?= $user ? 'Proceed to checkout' : 'Sign in and check out' ?>
              </a>
            <?php endif; ?>
          </aside>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
