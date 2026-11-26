<?php
/**
 * MADAM CAKES — contact messages from the website.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

if (is_post()) {
    require_csrf();
    $messageId = (int) post('message_id');
    $action    = (string) post('action');

    if ($action === 'read') {
        Database::execute('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$messageId]);
    } elseif ($action === 'unread') {
        Database::execute('UPDATE contact_messages SET is_read = 0 WHERE id = ?', [$messageId]);
    } elseif ($action === 'delete') {
        Database::execute('DELETE FROM contact_messages WHERE id = ?', [$messageId]);
        flash('success', 'Message deleted.');
    }
    redirect('admin/messages.php');
}

$messages = Database::fetchAll('SELECT * FROM contact_messages ORDER BY is_read ASC, created_at DESC LIMIT 200');

$adminTitle  = 'Messages';
$adminActive = 'messages';
require __DIR__ . '/includes/header.php';
?>

<section class="admin-panel">
  <div class="panel-head">
    <h2><?= count($messages) ?> message<?= count($messages) === 1 ? '' : 's' ?></h2>
    <span class="admin-muted">Unread messages are stored in the database — no email is sent automatically.</span>
  </div>

  <?php if ($messages): ?>
    <ul class="message-list">
      <?php foreach ($messages as $message): ?>
        <li class="message-card<?= (int) $message['is_read'] === 0 ? ' is-unread' : '' ?>">
          <div class="message-head">
            <div>
              <strong><?= e($message['name']) ?></strong>
              <span class="admin-muted"><?= e($message['email']) ?><?= $message['phone'] ? ' · ' . e($message['phone']) : '' ?></span>
            </div>
            <span class="admin-muted"><?= e(format_datetime($message['created_at'])) ?></span>
          </div>
          <?php if ($message['subject']): ?><p class="message-subject"><?= e($message['subject']) ?></p><?php endif; ?>
          <p class="message-body"><?= nl2br(e($message['message'])) ?></p>
          <div class="message-actions">
            <a class="btn btn-outline btn-small" href="mailto:<?= e($message['email']) ?>?subject=<?= e(rawurlencode('Re: ' . ($message['subject'] ?: 'Your message to MADAM CAKES'))) ?>">Reply by email</a>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>">
              <input type="hidden" name="action" value="<?= (int) $message['is_read'] === 1 ? 'unread' : 'read' ?>">
              <button type="submit" class="link-button"><?= (int) $message['is_read'] === 1 ? 'Mark unread' : 'Mark read' ?></button>
            </form>
            <form method="post" onsubmit="return confirm('Delete this message?');">
              <?= csrf_field() ?>
              <input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="link-button link-danger">Delete</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="admin-empty">No messages yet. Messages sent through the contact form appear here.</p>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
