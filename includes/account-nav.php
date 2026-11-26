<?php
/**
 * MADAM CAKES — customer dashboard navigation.
 *
 * Expects: $accountUser (array), $accountActive (string).
 */

declare(strict_types=1);

$accountUser   = $accountUser ?? auth_user();
$accountActive = $accountActive ?? '';
$unreadCount   = $accountUser ? unread_notifications((int) $accountUser['id']) : 0;

$accountLinks = [
    'overview'  => ['label' => 'Overview', 'href' => 'account/index.php'],
    'orders'    => ['label' => 'My orders', 'href' => 'account/orders.php'],
    'custom'    => ['label' => 'Custom requests', 'href' => 'account/custom-requests.php'],
    'favourites' => ['label' => 'My favourites', 'href' => 'account/favourites.php'],
    'addresses' => ['label' => 'My addresses', 'href' => 'account/addresses.php'],
    'reviews'   => ['label' => 'My reviews', 'href' => 'account/reviews.php'],
    'profile'   => ['label' => 'My profile', 'href' => 'account/profile.php'],
    'password'  => ['label' => 'Change password', 'href' => 'account/password.php'],
    'notifications' => ['label' => 'Notifications', 'href' => 'account/notifications.php'],
];
?>
<aside class="account-sidebar">
  <div class="account-identity">
    <?php if (!empty($accountUser['avatar'])): ?>
      <img class="account-avatar" src="<?= e(upload_url($accountUser['avatar'])) ?>" alt="">
    <?php else: ?>
      <span class="account-avatar account-avatar-initial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $accountUser['full_name'], 0, 1))) ?></span>
    <?php endif; ?>
    <div>
      <p class="account-name"><?= e($accountUser['full_name']) ?></p>
      <p class="account-email"><?= e($accountUser['email']) ?></p>
    </div>
  </div>

  <nav class="account-nav" aria-label="My account">
    <ul>
      <?php foreach ($accountLinks as $key => $item): ?>
        <li>
          <a href="<?= e(url($item['href'])) ?>"<?= $accountActive === $key ? ' class="is-active" aria-current="page"' : '' ?>>
            <span><?= e($item['label']) ?></span>
            <?php if ($key === 'notifications' && $unreadCount > 0): ?>
              <span class="nav-count"><?= (int) $unreadCount ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <a class="btn btn-outline btn-block btn-small" href="<?= e(url('cakes.php')) ?>">Continue shopping</a>
  <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="account-logout">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-ghost btn-block btn-small">Sign out</button>
  </form>
</aside>
