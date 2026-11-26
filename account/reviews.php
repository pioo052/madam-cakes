<?php
/**
 * MADAM CAKES — customer reviews: view moderation status, edit or delete.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];

if (is_post()) {
    require_csrf();
    $action   = (string) post('action');
    $reviewId = (int) post('review_id');
    $review   = Database::fetch('SELECT * FROM reviews WHERE id = ? AND user_id = ?', [$reviewId, $userId]);

    if (!$review) {
        flash('error', 'That review was not found on your account.');
    } elseif ($action === 'delete') {
        Database::execute('DELETE FROM reviews WHERE id = ? AND user_id = ?', [$reviewId, $userId]);
        flash('success', 'Your review was deleted.');
    } elseif ($action === 'update') {
        $rating = (int) post('rating', 5);
        $title  = trim((string) post('review_title'));
        $body   = trim((string) post('review_body'));

        if ((int) $review['is_approved'] === 1) {
            flash('warning', 'Published reviews can no longer be edited. Please contact the studio.');
        } elseif ($rating < 1 || $rating > 5 || mb_strlen($body) < 10) {
            flash('error', 'Choose a rating and write at least a sentence.');
        } else {
            Database::execute(
                'UPDATE reviews SET rating = ?, title = ?, body = ? WHERE id = ? AND user_id = ?',
                [$rating, $title ?: null, $body, $reviewId, $userId]
            );
            flash('success', 'Your review was updated and is awaiting approval.');
        }
    }
    redirect('account/reviews.php');
}

$editId = (int) get('edit');
$editing = $editId > 0
    ? Database::fetch('SELECT * FROM reviews WHERE id = ? AND user_id = ? AND is_approved = 0', [$editId, $userId])
    : null;

$reviews = Database::fetchAll(
    'SELECT r.*, p.name AS product_name, p.slug AS product_slug
     FROM reviews r LEFT JOIN products p ON p.id = r.product_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC',
    [$userId]
);

$accountActive   = 'reviews';
$pageTitle       = 'My reviews';
$pageDescription = 'Reviews you have written for MADAM CAKES.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">My reviews</h1>
      <p class="section-sub">Reviews are published after the studio approves them.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <?php if ($editing): ?>
          <section class="account-block">
            <h2 class="section-title">Edit your review</h2>
            <form method="post" class="form-card form-card-flat">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="review_id" value="<?= (int) $editing['id'] ?>">
              <label class="field">
                <span>Rating</span>
                <select name="rating">
                  <?php for ($star = 5; $star >= 1; $star--): ?>
                    <option value="<?= $star ?>"<?= (int) $editing['rating'] === $star ? ' selected' : '' ?>><?= str_repeat('★', $star) ?> <?= $star ?></option>
                  <?php endfor; ?>
                </select>
              </label>
              <label class="field">
                <span>Title</span>
                <input type="text" name="review_title" value="<?= e((string) $editing['title']) ?>" maxlength="160">
              </label>
              <label class="field">
                <span>Your review</span>
                <textarea name="review_body" required minlength="10"><?= e((string) $editing['body']) ?></textarea>
              </label>
              <button type="submit" class="btn btn-primary">Save review</button>
              <a class="btn btn-ghost btn-small" href="<?= e(url('account/reviews.php')) ?>">Cancel</a>
            </form>
          </section>
        <?php endif; ?>

        <?php if ($reviews): ?>
          <ul class="review-list">
            <?php foreach ($reviews as $review): ?>
              <li class="review">
                <div class="review-head">
                  <strong><?= $review['product_name'] ? e($review['product_name']) : 'Custom cake' ?></strong>
                  <span class="review-stars"><?= str_repeat('★', (int) $review['rating']) ?></span>
                  <span class="pill <?= (int) $review['is_approved'] === 1 ? 'pill-success' : 'pill-muted' ?>">
                    <?= (int) $review['is_approved'] === 1 ? 'Published' : 'Awaiting approval' ?>
                  </span>
                  <time datetime="<?= e($review['created_at']) ?>"><?= e(format_date($review['created_at'])) ?></time>
                </div>
                <?php if ($review['title']): ?><p class="review-title"><?= e($review['title']) ?></p><?php endif; ?>
                <p class="review-body"><?= nl2br(e($review['body'])) ?></p>
                <?php if ($review['admin_reply']): ?>
                  <p class="review-reply"><strong>MADAM CAKES replied:</strong> <?= nl2br(e($review['admin_reply'])) ?></p>
                <?php endif; ?>
                <div class="review-actions">
                  <?php if ($review['product_slug']): ?>
                    <a class="link-button" href="<?= e(product_url((string) $review['product_slug'])) ?>">View cake</a>
                  <?php endif; ?>
                  <?php if ((int) $review['is_approved'] === 0): ?>
                    <a class="link-button" href="<?= e(url('account/reviews.php?edit=' . (int) $review['id'])) ?>">Edit</a>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('Delete this review?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                    <button type="submit" class="link-button link-danger">Delete</button>
                  </form>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="empty-state">
            <h2>No reviews yet</h2>
            <p>Once you have received a cake, you can review it from the cake page.</p>
            <a class="btn btn-primary" href="<?= e(url('account/orders.php')) ?>">View my orders</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
