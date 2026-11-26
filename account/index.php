<?php
/**
 * MADAM CAKES — customer account overview.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];

$orders        = customer_orders($userId, 6);
$orderCount    = (int) Database::value('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$userId], 0);
$activeCount   = (int) Database::value(
    "SELECT COUNT(*) FROM orders WHERE user_id = ? AND status NOT IN ('delivered','cancelled','rejected')",
    [$userId],
    0
);
$customCount   = (int) Database::value('SELECT COUNT(*) FROM custom_orders WHERE user_id = ?', [$userId], 0);
$favourites    = (int) Database::value('SELECT COUNT(*) FROM favourites WHERE user_id = ?', [$userId], 0);
$unread        = unread_notifications($userId);
$notifications = Database::fetchAll(
    'SELECT * FROM notifications WHERE user_id = ? AND audience = ? ORDER BY created_at DESC, id DESC LIMIT 5',
    [$userId, 'customer']
);
$defaultAddress = Database::fetch(
    'SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1',
    [$userId]
);

$accountActive   = 'overview';
$pageTitle       = 'My account';
$pageDescription = 'Your MADAM CAKES account overview.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">Hello, <?= e(explode(' ', trim((string) $accountUser['full_name']))[0]) ?></h1>
      <p class="section-sub">Member since <?= e(format_date($accountUser['created_at'], 'F Y')) ?>. Everything about your cakes lives here.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <div class="stat-grid">
          <div class="stat-card">
            <span class="stat-label">Orders placed</span>
            <strong class="stat-value"><?= $orderCount ?></strong>
            <a href="<?= e(url('account/orders.php')) ?>">View orders</a>
          </div>
          <div class="stat-card">
            <span class="stat-label">In progress</span>
            <strong class="stat-value"><?= $activeCount ?></strong>
            <span class="stat-muted">We will update each step for you.</span>
          </div>
          <div class="stat-card">
            <span class="stat-label">Custom requests</span>
            <strong class="stat-value"><?= $customCount ?></strong>
            <a href="<?= e(url('account/custom-requests.php')) ?>">View requests</a>
          </div>
          <div class="stat-card">
            <span class="stat-label">Favourites</span>
            <strong class="stat-value"><?= $favourites ?></strong>
            <a href="<?= e(url('account/favourites.php')) ?>">Saved cakes</a>
          </div>
        </div>

        <div class="account-columns">
          <section class="account-block">
            <div class="block-head">
              <h2 class="section-title">Recent orders</h2>
              <a href="<?= e(url('account/orders.php')) ?>">See all</a>
            </div>
            <?php if ($orders): ?>
              <ul class="order-mini-list">
                <?php foreach ($orders as $order): ?>
                  <li>
                    <a href="<?= e(url('account/order.php?id=' . (int) $order['id'])) ?>">
                      <span class="order-mini-ref"><?= e($order['order_reference']) ?></span>
                      <span class="order-mini-date"><?= e(format_date($order['created_at'])) ?></span>
                      <?= status_badge($order['status']) ?>
                      <span class="order-mini-total"><?= e(money($order['total'])) ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="note">No orders yet. <a href="<?= e(url('cakes.php')) ?>">Browse the cake menu</a> to start.</p>
            <?php endif; ?>
          </section>

          <section class="account-block">
            <div class="block-head">
              <h2 class="section-title">Account details</h2>
              <a href="<?= e(url('account/profile.php')) ?>">Edit</a>
            </div>
            <dl class="detail-list">
              <div><dt>Name</dt><dd><?= e($accountUser['full_name']) ?></dd></div>
              <div><dt>Email</dt><dd><?= e($accountUser['email']) ?></dd></div>
              <div><dt>Phone</dt><dd><?= $accountUser['phone'] ? e($accountUser['phone']) : '<span class="text-muted">Not added</span>' ?></dd></div>
              <div><dt>Delivery address</dt><dd>
                <?php if ($defaultAddress): ?>
                  <?= e(trim(implode(', ', array_filter([$defaultAddress['street'], $defaultAddress['area'], $defaultAddress['city'], $defaultAddress['region']]))) ) ?>
                <?php else: ?>
                  <span class="text-muted">Not added — <a href="<?= e(url('account/addresses.php')) ?>">add one</a></span>
                <?php endif; ?>
              </dd></div>
            </dl>
          </section>
        </div>

        <section class="account-block">
          <div class="block-head">
            <h2 class="section-title">Notifications<?= $unread > 0 ? ' (' . $unread . ' new)' : '' ?></h2>
            <a href="<?= e(url('account/notifications.php')) ?>">All notifications</a>
          </div>
          <?php if ($notifications): ?>
            <ul class="notification-list">
              <?php foreach ($notifications as $note): ?>
                <li class="notification<?= (int) $note['is_read'] === 0 ? ' is-unread' : '' ?>">
                  <div>
                    <strong><?= e($note['title']) ?></strong>
                    <?php if ($note['message']): ?><p><?= e($note['message']) ?></p><?php endif; ?>
                    <time datetime="<?= e($note['created_at']) ?>"><?= e(format_datetime($note['created_at'])) ?></time>
                  </div>
                  <?php if ($note['link']): ?>
                    <a class="btn btn-ghost btn-small" href="<?= e(url((string) $note['link'])) ?>">Open</a>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="note">No notifications yet. Order updates will appear here.</p>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
