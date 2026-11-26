<?php
/**
 * MADAM CAKES — approved customer reviews.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

$pager = paginate((int) Database::value('SELECT COUNT(*) FROM reviews WHERE is_approved = 1', [], 0), 10);
$reviews = Database::fetchAll(
    'SELECT r.*, p.name AS product_name, p.slug AS product_slug
     FROM reviews r LEFT JOIN products p ON p.id = r.product_id
     WHERE r.is_approved = 1
     ORDER BY r.is_featured DESC, r.created_at DESC
     LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset']
);

$summary = Database::fetch(
    'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average FROM reviews WHERE is_approved = 1'
);

$pageTitle       = 'Reviews';
$pageDescription = 'What customers say about their MADAM CAKES cakes.';
$activeNav       = 'cakes';
require __DIR__ . '/includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">Kind words</p>
      <h1 class="section-title">Customer reviews</h1>
      <p class="section-sub">
        <?php if ((int) ($summary['total'] ?? 0) > 0): ?>
          <?= e(number_format((float) $summary['average'], 1)) ?> out of 5 from <?= (int) $summary['total'] ?> verified customer review<?= (int) $summary['total'] === 1 ? '' : 's' ?>.
        <?php else: ?>
          Reviews appear here after customers receive their cakes. We never invent them.
        <?php endif; ?>
      </p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container">
      <?php if ($reviews): ?>
        <ul class="review-list review-list-wide">
          <?php foreach ($reviews as $review): ?>
            <li class="review">
              <div class="review-head">
                <strong><?= e($review['customer_name']) ?></strong>
                <span class="review-stars" aria-label="<?= (int) $review['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $review['rating']) ?></span>
                <time datetime="<?= e(substr((string) $review['created_at'], 0, 10)) ?>"><?= e(format_date($review['created_at'])) ?></time>
                <?php if ((int) $review['is_verified'] === 1): ?><span class="review-verified">Verified order</span><?php endif; ?>
              </div>
              <?php if ($review['product_name']): ?>
                <p class="review-product"><a href="<?= e(product_url((string) $review['product_slug'])) ?>"><?= e($review['product_name']) ?></a></p>
              <?php endif; ?>
              <?php if ($review['title']): ?><p class="review-title"><?= e($review['title']) ?></p><?php endif; ?>
              <p class="review-body"><?= nl2br(e($review['body'])) ?></p>
              <?php if ($review['admin_reply']): ?>
                <p class="review-reply"><strong>MADAM CAKES replied:</strong> <?= nl2br(e($review['admin_reply'])) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?= pagination_html($pager, 'Reviews') ?>
      <?php else: ?>
        <div class="empty-state">
          <h2>No reviews published yet</h2>
          <p>Once a customer has received a cake, they can review it from their account. Reviews are published after the studio approves them.</p>
          <a class="btn btn-primary" href="<?= e(url('cakes.php')) ?>">Browse cakes</a>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
