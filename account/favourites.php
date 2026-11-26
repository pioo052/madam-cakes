<?php
/**
 * MADAM CAKES — customer favourites.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];

if (is_post()) {
    require_csrf();
    if ((string) post('action') === 'remove') {
        Database::execute(
            'DELETE FROM favourites WHERE user_id = ? AND product_id = ?',
            [$userId, (int) post('product_id')]
        );
        flash('success', 'Removed from your favourites.');
    }
    redirect('account/favourites.php');
}

$favourites = favourites_for($userId);

$accountActive   = 'favourites';
$pageTitle       = 'My favourites';
$pageDescription = 'Cakes saved to your MADAM CAKES account.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">My favourites</h1>
      <p class="section-sub">The cakes you keep coming back to.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <?php if ($favourites): ?>
          <div class="cake-grid">
            <?php foreach ($favourites as $product): ?>
              <article class="cake-card">
                <a class="cake-media" href="<?= e(product_url((string) $product['slug'])) ?>" aria-hidden="true" tabindex="-1">
                  <?php $image = product_primary_image($product); ?>
                  <?php if ($image !== ''): ?>
                    <img src="<?= e($image) ?>" alt="" loading="lazy" width="640" height="480">
                  <?php else: ?>
                    <span class="cake-media-empty">Photograph coming soon</span>
                  <?php endif; ?>
                </a>
                <div class="cake-body">
                  <h2 class="cake-name"><a href="<?= e(product_url((string) $product['slug'])) ?>"><?= e($product['name']) ?></a></h2>
                  <p class="cake-price"><?= e(product_price_label($product)) ?></p>
                  <div class="cake-actions">
                    <a class="btn btn-outline btn-small" href="<?= e(product_url((string) $product['slug'])) ?>">Choose options</a>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                      <button type="submit" class="link-button">Remove</button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <h2>No favourites yet</h2>
            <p>Open any cake and choose “Save to favourites” to keep it here.</p>
            <a class="btn btn-primary" href="<?= e(url('cakes.php')) ?>">Browse cakes</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
