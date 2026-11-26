<?php
/**
 * MADAM CAKES — review moderation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();

if (is_post()) {
    require_csrf();
    $reviewId = (int) post('review_id');
    $action   = (string) post('action');
    $review   = Database::fetch('SELECT * FROM reviews WHERE id = ?', [$reviewId]);

    if (!$review) {
        flash('error', 'Review not found.');
    } elseif ($action === 'approve') {
        Database::execute('UPDATE reviews SET is_approved = 1 WHERE id = ?', [$reviewId]);
        if ($review['user_id'] !== null && (int) $review['is_approved'] === 0) {
            notify_customer((int) $review['user_id'], 'Your review is published', 'Thank you for sharing your cake story.', 'review', 'account/reviews.php');
        }
        log_admin_action('review.approve', 'review', $reviewId, 'Approved a review.');
        flash('success', 'Review approved and published.');
    } elseif ($action === 'unapprove') {
        Database::execute('UPDATE reviews SET is_approved = 0 WHERE id = ?', [$reviewId]);
        flash('success', 'Review hidden from the website.');
    } elseif ($action === 'feature') {
        Database::execute('UPDATE reviews SET is_featured = 1 - is_featured WHERE id = ?', [$reviewId]);
        flash('success', 'Featured state updated.');
    } elseif ($action === 'reply') {
        Database::execute('UPDATE reviews SET admin_reply = ? WHERE id = ?', [trim((string) post('admin_reply')) ?: null, $reviewId]);
        flash('success', 'Reply saved.');
    } elseif ($action === 'delete') {
        Database::execute('DELETE FROM reviews WHERE id = ?', [$reviewId]);
        log_admin_action('review.delete', 'review', $reviewId, 'Deleted a review.');
        flash('success', 'Review deleted.');
    }
    redirect('admin/reviews.php' . ((string) get('filter') !== '' ? '?filter=' . rawurlencode((string) get('filter')) : ''));
}

$filter = (string) get('filter');
$where = '1 = 1';
if ($filter === 'pending') {
    $where = 'r.is_approved = 0';
} elseif ($filter === 'approved') {
    $where = 'r.is_approved = 1';
}

$reviews = Database::fetchAll(
    'SELECT r.*, p.name AS product_name FROM reviews r
     LEFT JOIN products p ON p.id = r.product_id
     WHERE ' . $where . '
     ORDER BY r.is_approved ASC, r.created_at DESC LIMIT 200'
);

$adminTitle  = 'Reviews';
$adminActive = 'reviews';
require __DIR__ . '/includes/header.php';
?>

<nav class="filter-chips" aria-label="Filter reviews">
  <a class="filter-chip<?= $filter === '' ? ' is-active' : '' ?>" href="<?= e(url('admin/reviews.php')) ?>">All</a>
  <a class="filter-chip<?= $filter === 'pending' ? ' is-active' : '' ?>" href="<?= e(url('admin/reviews.php?filter=pending')) ?>">Awaiting approval</a>
  <a class="filter-chip<?= $filter === 'approved' ? ' is-active' : '' ?>" href="<?= e(url('admin/reviews.php?filter=approved')) ?>">Published</a>
</nav>

<?php if ($reviews): ?>
  <ul class="review-list review-list-wide">
    <?php foreach ($reviews as $review): ?>
      <li class="review admin-review">
        <div class="review-head">
          <strong><?= e($review['customer_name']) ?></strong>
          <span class="review-stars"><?= str_repeat('★', (int) $review['rating']) ?></span>
          <span class="pill <?= (int) $review['is_approved'] === 1 ? 'pill-success' : 'pill-muted' ?>"><?= (int) $review['is_approved'] === 1 ? 'Published' : 'Awaiting approval' ?></span>
          <?php if ((int) $review['is_featured'] === 1): ?><span class="pill">Featured</span><?php endif; ?>
          <time datetime="<?= e($review['created_at']) ?>"><?= e(format_datetime($review['created_at'])) ?></time>
        </div>
        <p class="review-product"><?= $review['product_name'] ? e($review['product_name']) : 'Custom cake' ?></p>
        <?php if ($review['title']): ?><p class="review-title"><?= e($review['title']) ?></p><?php endif; ?>
        <p class="review-body"><?= nl2br(e($review['body'])) ?></p>
        <?php if ($review['photo']): ?><img class="review-photo" src="<?= e(upload_url($review['photo'])) ?>" alt="Customer review photograph" width="160" height="160"><?php endif; ?>

        <div class="review-actions">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
            <input type="hidden" name="action" value="<?= (int) $review['is_approved'] === 1 ? 'unapprove' : 'approve' ?>">
            <button type="submit" class="btn btn-primary btn-small"><?= (int) $review['is_approved'] === 1 ? 'Hide' : 'Approve' ?></button>
          </form>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
            <input type="hidden" name="action" value="feature">
            <button type="submit" class="btn btn-outline btn-small"><?= (int) $review['is_featured'] === 1 ? 'Unfeature' : 'Feature' ?></button>
          </form>
          <form method="post" onsubmit="return confirm('Delete this review permanently?');">
            <?= csrf_field() ?>
            <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="link-button link-danger">Delete</button>
          </form>
        </div>

        <form method="post" class="compact-form">
          <?= csrf_field() ?>
          <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
          <input type="hidden" name="action" value="reply">
          <label class="field"><span>Public reply</span><textarea name="admin_reply" maxlength="2000"><?= e((string) $review['admin_reply']) ?></textarea></label>
          <button type="submit" class="btn btn-ghost btn-small">Save reply</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <div class="admin-panel"><p class="admin-empty">No reviews in this view.</p></div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
