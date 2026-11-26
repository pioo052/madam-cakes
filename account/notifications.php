<?php
/**
 * MADAM CAKES — account notifications.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];

if (is_post()) {
    require_csrf();
    if ((string) post('action') === 'mark_all') {
        Database::execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND audience = ?', [$userId, 'customer']);
        flash('success', 'All notifications marked as read.');
    } elseif ((string) post('action') === 'mark_one') {
        Database::execute(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND audience = ?',
            [(int) post('notification_id'), $userId, 'customer']
        );
    }
    redirect('account/notifications.php');
}

$notifications = Database::fetchAll(
    'SELECT * FROM notifications WHERE user_id = ? AND audience = ? ORDER BY created_at DESC, id DESC LIMIT 100',
    [$userId, 'customer']
);
$unread = unread_notifications($userId);

$accountActive   = 'notifications';
$pageTitle       = 'Notifications';
$pageDescription = 'Order and account notifications from MADAM CAKES.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">Notifications</h1>
      <p class="section-sub"><?= $unread > 0 ? $unread . ' unread' : 'You are all caught up' ?>.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <?php if ($notifications): ?>
          <div class="block-head">
            <h2 class="section-title">Recent activity</h2>
            <?php if ($unread > 0): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all">
                <button type="submit" class="link-button">Mark all as read</button>
              </form>
            <?php endif; ?>
          </div>

          <ul class="notification-list">
            <?php foreach ($notifications as $note): ?>
              <li class="notification<?= (int) $note['is_read'] === 0 ? ' is-unread' : '' ?>">
                <div>
                  <strong><?= e($note['title']) ?></strong>
                  <?php if ($note['message']): ?><p><?= e($note['message']) ?></p><?php endif; ?>
                  <time datetime="<?= e($note['created_at']) ?>"><?= e(format_datetime($note['created_at'])) ?></time>
                </div>
                <div class="notification-actions">
                  <?php if ($note['link']): ?>
                    <a class="btn btn-ghost btn-small" href="<?= e(url((string) $note['link'])) ?>">Open</a>
                  <?php endif; ?>
                  <?php if ((int) $note['is_read'] === 0): ?>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="mark_one">
                      <input type="hidden" name="notification_id" value="<?= (int) $note['id'] ?>">
                      <button type="submit" class="link-button">Mark read</button>
                    </form>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="empty-state">
            <h2>No notifications yet</h2>
            <p>Order updates, quotation news and account messages will appear here.</p>
            <a class="btn btn-primary" href="<?= e(url('cakes.php')) ?>">Browse cakes</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
