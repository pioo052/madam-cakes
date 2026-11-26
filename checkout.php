<?php
/**
 * MADAM CAKES — checkout. Requires an account so every order has an owner.
 * Totals are recalculated on the server inside a transaction.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login('auth/login.php');

$totals = cart_totals((int) $user['id']);
if ($totals['cart']['lines'] === []) {
    flash('warning', 'Your cart is empty.');
    redirect('cart.php');
}
if ($totals['cart']['unavailable']) {
    flash('warning', 'Please remove unavailable cakes before checking out.');
    redirect('cart.php');
}

$zones = delivery_zones_active();
$defaultAddress = Database::fetch(
    'SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1',
    [(int) $user['id']]
);

$values = [
    'customer_name'     => (string) $user['full_name'],
    'customer_phone'    => (string) ($user['phone'] ?? ''),
    'customer_email'    => (string) $user['email'],
    'fulfilment'        => setting('delivery_enabled') === '0' ? 'pickup' : 'delivery',
    'delivery_zone_id'  => $defaultAddress ? '' : '',
    'region'            => (string) ($defaultAddress['region'] ?? ''),
    'city'              => (string) ($defaultAddress['city'] ?? ''),
    'area'              => (string) ($defaultAddress['area'] ?? ''),
    'street_address'    => (string) ($defaultAddress['street'] ?? ''),
    'delivery_date'     => '',
    'delivery_time'     => '',
    'delivery_instructions' => '',
    'customer_notes'    => '',
    'payment_method'    => array_key_first(payment_options()) ?? '',
    'is_surprise'       => '',
    'recipient_name'    => '',
    'recipient_phone'   => '',
    'recipient_location' => '',
    'gift_message'      => '',
    'gift_wrapping'     => '',
    'anonymous_sender'  => '',
];

$errors = [];

if (is_post()) {
    require_csrf();
    foreach ($values as $key => $default) {
        if (isset($_POST[$key])) {
            $values[$key] = trim((string) $_POST[$key]);
        }
    }
    $values['is_surprise']     = !empty($_POST['is_surprise']) ? '1' : '';
    $values['gift_wrapping']   = !empty($_POST['gift_wrapping']) ? '1' : '';
    $values['anonymous_sender'] = !empty($_POST['anonymous_sender']) ? '1' : '';

    $input = $_POST;
    $input['coupon_code'] = (string) ($_SESSION['coupon'] ?? '');
    $result = place_order($input, $user);

    if ($result['ok']) {
        flash('success', 'Thank you — order ' . $result['reference'] . ' has been received.');
        redirect('account/order.php?id=' . (int) $result['order_id'] . '&placed=1');
    }
    $errors[] = $result['message'];
}

$minDate = date('Y-m-d', strtotime('+' . (int) setting('min_advance_days', '0') . ' days'));
$options = payment_options();

$pageTitle       = 'Checkout';
$pageDescription = 'Complete your MADAM CAKES order.';
$activeNav       = 'cakes';
require __DIR__ . '/includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">Checkout</p>
      <h1 class="section-title">Where should the cake go?</h1>
      <p class="section-sub">Signed in as <?= e($user['full_name']) ?>. We only confirm payment once we have verified it.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container checkout-layout">
      <div>
        <?php foreach ($errors as $error): ?>
          <p class="note note-error"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form method="post" class="checkout-form">
          <?= csrf_field() ?>

          <fieldset class="form-section">
            <legend>Contact details</legend>
            <div class="field-row">
              <label class="field">
                <span>Full name</span>
                <input type="text" name="customer_name" value="<?= e($values['customer_name']) ?>" required maxlength="120" autocomplete="name">
              </label>
              <label class="field">
                <span>Phone number</span>
                <input type="tel" name="customer_phone" value="<?= e($values['customer_phone']) ?>" required maxlength="30" autocomplete="tel">
              </label>
            </div>
            <label class="field">
              <span>Email address</span>
              <input type="email" name="customer_email" value="<?= e($values['customer_email']) ?>" maxlength="190" autocomplete="email">
            </label>
          </fieldset>

          <fieldset class="form-section">
            <legend>Delivery or collection</legend>
            <div class="option-list option-list-row">
              <?php if (setting('delivery_enabled') !== '0'): ?>
                <label class="option">
                  <input type="radio" name="fulfilment" value="delivery"<?= $values['fulfilment'] === 'delivery' ? ' checked' : '' ?> required>
                  <span class="option-body"><span class="option-name">Deliver to me</span><span class="option-price">Fee depends on your zone</span></span>
                </label>
              <?php endif; ?>
              <?php if (setting('pickup_enabled') !== '0'): ?>
                <label class="option">
                  <input type="radio" name="fulfilment" value="pickup"<?= $values['fulfilment'] === 'pickup' ? ' checked' : '' ?> required>
                  <span class="option-body"><span class="option-name">I will collect</span><span class="option-price">No delivery fee</span></span>
                </label>
              <?php endif; ?>
            </div>

            <?php if ($zones): ?>
              <label class="field">
                <span>Delivery zone</span>
                <select name="delivery_zone_id">
                  <option value="">Choose a zone (or enter your address below)</option>
                  <?php foreach ($zones as $zone): ?>
                    <option value="<?= (int) $zone['id'] ?>"<?= (string) $values['delivery_zone_id'] === (string) $zone['id'] ? ' selected' : '' ?>>
                      <?= e($zone['name'] . ' — ' . $zone['region'] . ($zone['city'] ? ', ' . $zone['city'] : '') . ' · ' . money($zone['fee'])) ?>
                      <?= $zone['free_over'] !== null && (float) $zone['free_over'] > 0 ? ' (free over ' . money($zone['free_over']) . ')' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
            <?php endif; ?>

            <div class="field-row">
              <label class="field">
                <span>Region</span>
                <input type="text" name="region" value="<?= e($values['region']) ?>" maxlength="80" autocomplete="address-level1">
              </label>
              <label class="field">
                <span>City / district</span>
                <input type="text" name="city" value="<?= e($values['city']) ?>" maxlength="80" autocomplete="address-level2">
              </label>
            </div>
            <div class="field-row">
              <label class="field">
                <span>Area / ward</span>
                <input type="text" name="area" value="<?= e($values['area']) ?>" maxlength="120" autocomplete="address-level3">
              </label>
              <label class="field">
                <span>Street, house or landmark</span>
                <input type="text" name="street_address" value="<?= e($values['street_address']) ?>" maxlength="255" autocomplete="street-address">
              </label>
            </div>

            <div class="field-row">
              <label class="field">
                <span>Preferred date</span>
                <input type="date" name="delivery_date" value="<?= e($values['delivery_date']) ?>" min="<?= e($minDate) ?>">
              </label>
              <label class="field">
                <span>Preferred time</span>
                <input type="text" name="delivery_time" value="<?= e($values['delivery_time']) ?>" maxlength="40" placeholder="e.g. 3:00 PM">
              </label>
            </div>

            <label class="field">
              <span>Delivery instructions (optional)</span>
              <textarea name="delivery_instructions" maxlength="1000"><?= e($values['delivery_instructions']) ?></textarea>
            </label>
          </fieldset>

          <?php if (setting('surprise_delivery_enabled') === '1'): ?>
            <fieldset class="form-section">
              <legend>Surprise delivery</legend>
              <label class="checkbox-line">
                <input type="checkbox" name="is_surprise" value="1"<?= $values['is_surprise'] === '1' ? ' checked' : '' ?>>
                <span>This cake is a surprise for someone else</span>
              </label>
              <div class="field-row">
                <label class="field">
                  <span>Recipient name</span>
                  <input type="text" name="recipient_name" value="<?= e($values['recipient_name']) ?>" maxlength="120">
                </label>
                <label class="field">
                  <span>Recipient phone</span>
                  <input type="tel" name="recipient_phone" value="<?= e($values['recipient_phone']) ?>" maxlength="30">
                </label>
              </div>
              <label class="field">
                <span>Recipient location (if different)</span>
                <input type="text" name="recipient_location" value="<?= e($values['recipient_location']) ?>" maxlength="255">
              </label>
              <label class="field">
                <span>Gift message</span>
                <textarea name="gift_message" maxlength="500"><?= e($values['gift_message']) ?></textarea>
              </label>
              <label class="checkbox-line"><input type="checkbox" name="gift_wrapping" value="1"<?= $values['gift_wrapping'] === '1' ? ' checked' : '' ?>><span>Add gift wrapping</span></label>
              <label class="checkbox-line"><input type="checkbox" name="anonymous_sender" value="1"<?= $values['anonymous_sender'] === '1' ? ' checked' : '' ?>><span>Keep my name off the gift card</span></label>
            </fieldset>
          <?php endif; ?>

          <fieldset class="form-section">
            <legend>Payment</legend>
            <?php if ($options): ?>
              <div class="option-list">
                <?php foreach ($options as $code => $option): ?>
                  <label class="option option-check">
                    <input type="radio" name="payment_method" value="<?= e($code) ?>"<?= $values['payment_method'] === $code ? ' checked' : '' ?> required>
                    <span class="option-body"><span class="option-name"><?= e($option['label']) ?></span></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <p class="note"><?= e((string) setting('payment_instructions', 'Payment is confirmed by the studio after verification. No order is marked paid automatically.')) ?></p>
          </fieldset>

          <fieldset class="form-section">
            <legend>Notes</legend>
            <label class="field">
              <span>Anything we should know? (optional)</span>
              <textarea name="customer_notes" maxlength="1000"><?= e($values['customer_notes']) ?></textarea>
            </label>
            <label class="checkbox-line">
              <input type="checkbox" name="confirm_terms" value="1" required>
              <span>I understand my order is confirmed once MADAM CAKES verifies it.</span>
            </label>
          </fieldset>

          <button type="submit" class="btn btn-primary btn-block">Place order</button>
        </form>
      </div>

      <aside class="cart-summary checkout-summary">
        <h2>Your order</h2>
        <ul class="checkout-lines">
          <?php foreach ($totals['cart']['lines'] as $line): ?>
            <li>
              <span class="checkout-line-name"><?= (int) $line['quantity'] ?> × <?= e($line['name']) ?>
                <?php if ($line['size_name']): ?><small><?= e($line['size_name']) ?><?= $line['flavour_name'] ? ' · ' . e($line['flavour_name']) : '' ?></small><?php endif; ?>
              </span>
              <span><?= e(money($line['line_total'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <dl class="summary-list">
          <div><dt>Subtotal</dt><dd><?= e(money($totals['cart']['subtotal'])) ?></dd></div>
          <?php if ($totals['coupon']): ?>
            <div class="summary-discount"><dt>Coupon <?= e($totals['coupon']['code']) ?></dt><dd>−<?= e(money($totals['discount'])) ?></dd></div>
          <?php endif; ?>
          <div><dt>Delivery</dt><dd>Calculated from your zone</dd></div>
          <div class="summary-total"><dt>Total before delivery</dt><dd><?= e(money($totals['total'])) ?></dd></div>
        </dl>
        <p class="summary-note">The exact total, including any delivery fee, is confirmed with your order.</p>
        <a class="btn btn-ghost btn-small btn-block" href="<?= e(url('cart.php')) ?>">Back to cart</a>
      </aside>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
