<?php
/**
 * MADAM CAKES — single cake page: photos, sizes, flavours, extras, reviews.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

$slug = trim((string) get('slug'));
$product = $slug !== '' ? product_by_slug($slug) : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Cake not found';
    $activeNav = 'cakes';
    require __DIR__ . '/includes/header.php';
    echo '<main id="main"><section class="section"><div class="container empty-state"><h1>Cake not found</h1><p>This cake is no longer on the menu.</p><a class="btn btn-primary" href="' . e(url('cakes.php')) . '">Browse cakes</a></div></section></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$productId = (int) $product['id'];
$sizes     = product_sizes($productId);
$flavours  = product_flavours($productId);
$addons    = product_addons($productId);
$gallery   = product_gallery($productId);
$user      = auth_user();
$errors    = [];

if (is_post()) {
    require_csrf();
    $action = (string) post('action');

    if ($action === 'add') {
        $sizeId    = (int) post('size_id');
        $flavourId = (int) post('flavour_id');
        $quantity  = (int) post('quantity', 1);
        $addonIds  = array_map('intval', (array) post('addons', []));

        $result = cart_add(
            $productId,
            $sizeId > 0 ? $sizeId : null,
            $flavourId > 0 ? $flavourId : null,
            $quantity,
            $addonIds
        );

        if ($result['ok']) {
            flash('success', $result['message']);
            redirect('cart.php');
        }
        $errors[] = $result['message'];
    }

    if ($action === 'favourite') {
        if (!$user) {
            flash('warning', 'Sign in to save cakes to your favourites.');
            redirect('auth/login.php');
        }
        $state = favourite_toggle((int) $user['id'], $productId);
        flash('success', $state ? 'Saved to your favourites.' : 'Removed from your favourites.');
        redirect('cake.php?slug=' . rawurlencode((string) $product['slug']));
    }

    if ($action === 'review') {
        if (!$user) {
            flash('warning', 'Sign in to write a review.');
            redirect('auth/login.php');
        }
        $rating = (int) post('rating', 5);
        $title  = trim((string) post('review_title'));
        $body   = trim((string) post('review_body'));

        if (!customer_can_review((int) $user['id'], $productId)) {
            $errors[] = 'Reviews are open to customers who have received this cake.';
        } elseif ($rating < 1 || $rating > 5) {
            $errors[] = 'Choose a rating between 1 and 5 stars.';
        } elseif (mb_strlen($body) < 10) {
            $errors[] = 'Please write at least a sentence about your cake.';
        } else {
            Database::insert(
                'INSERT INTO reviews (product_id, user_id, customer_name, rating, title, body, is_verified, is_approved)
                 VALUES (?, ?, ?, ?, ?, ?, 1, 0)',
                [$productId, (int) $user['id'], $user['full_name'], $rating, $title ?: null, $body]
            );
            notify_admin('New review awaiting approval', $user['full_name'] . ' reviewed ' . $product['name'] . '.', 'review', 'admin/reviews.php');
            flash('success', 'Thank you — your review will appear once the studio has approved it.');
            redirect('cake.php?slug=' . rawurlencode((string) $product['slug']));
        }
    }
}

$price    = product_price($product);
$summary  = review_summary($productId);
$reviews  = reviews_for_product($productId, 12);
$isFav    = $user ? in_array($productId, favourite_ids((int) $user['id']), true) : false;
$canReview = $user ? customer_can_review((int) $user['id'], $productId) : false;

$related = [];
if (!empty($product['category_id'])) {
    $related = Database::fetchAll(
        product_card_select() . ' WHERE p.is_active = 1 AND p.id <> ? AND p.category_id = ? ORDER BY p.is_featured DESC, p.created_at DESC LIMIT 3',
        [$productId, (int) $product['category_id']]
    );
}

$pageTitle       = (string) $product['name'];
$pageDescription = (string) ($product['short_description'] ?? $product['name']);
$activeNav       = 'cakes';
require __DIR__ . '/includes/header.php';
?>

<main id="main">
  <section class="section-tight">
    <div class="container">
      <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url('')) ?>">Home</a><span>/</span>
        <a href="<?= e(url('cakes.php')) ?>">Cakes</a><span>/</span>
        <span aria-current="page"><?= e($product['name']) ?></span>
      </nav>

      <?php foreach ($errors as $error): ?>
        <p class="note note-error"><?= e($error) ?></p>
      <?php endforeach; ?>

      <div class="product-layout">
        <div class="product-media">
          <?php if ($gallery): ?>
            <figure class="product-main">
              <img src="<?= e(upload_url($gallery[0]['image_path'])) ?>" alt="<?= e($gallery[0]['alt_text'] ?: $product['name']) ?>" width="800" height="800">
            </figure>
            <?php if (count($gallery) > 1): ?>
              <ul class="product-thumbs">
                <?php foreach ($gallery as $image): ?>
                  <li><img src="<?= e(upload_url($image['image_path'])) ?>" alt="<?= e($image['alt_text'] ?: $product['name']) ?>" width="120" height="120" loading="lazy"></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          <?php else: ?>
            <figure class="product-main product-main-empty"><span>Photograph coming soon</span></figure>
          <?php endif; ?>
        </div>

        <div class="product-info">
          <?php if (!empty($product['category_name'])): ?>
            <p class="eyebrow"><a href="<?= e(category_url((string) $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a></p>
          <?php endif; ?>
          <h1 class="product-title"><?= e($product['name']) ?></h1>

          <div class="product-meta">
            <span class="product-price"><?= e(product_price_label($product)) ?></span>
            <?php if ($summary['total'] > 0): ?>
              <a class="product-rating" href="#reviews">&#9733; <?= e((string) $summary['average']) ?> · <?= (int) $summary['total'] ?> review<?= $summary['total'] === 1 ? '' : 's' ?></a>
            <?php else: ?>
              <span class="product-rating is-muted">No reviews yet</span>
            <?php endif; ?>
          </div>

          <?php if (!empty($product['short_description'])): ?>
            <p class="product-lead"><?= e($product['short_description']) ?></p>
          <?php endif; ?>

          <form class="product-form" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <?php if ($sizes): ?>
              <fieldset class="option-group">
                <legend>Choose a size</legend>
                <div class="option-list">
                  <?php foreach ($sizes as $index => $size): ?>
                    <label class="option">
                      <input type="radio" name="size_id" value="<?= (int) $size['id'] ?>"<?= $index === 0 ? ' checked' : '' ?> required>
                      <span class="option-body">
                        <span class="option-name"><?= e($size['name']) ?></span>
                        <span class="option-price"><?= (float) $size['price'] > 0 ? e(money($size['price'])) : 'Price on enquiry' ?></span>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>
            <?php elseif ($price['on_request']): ?>
              <p class="note note-warning">The studio has not published a price for this cake yet. Send an enquiry and we will quote you personally.</p>
            <?php endif; ?>

            <?php if ($flavours): ?>
              <label class="field">
                <span>Flavour</span>
                <select name="flavour_id">
                  <?php foreach ($flavours as $flavour): ?>
                    <option value="<?= (int) $flavour['id'] ?>">
                      <?= e($flavour['name']) ?><?= (float) $flavour['surcharge'] > 0 ? ' (+' . money($flavour['surcharge']) . ')' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
            <?php endif; ?>

            <?php if ($addons): ?>
              <fieldset class="option-group">
                <legend>Add something extra</legend>
                <div class="option-list">
                  <?php foreach ($addons as $addon): ?>
                    <label class="option option-check">
                      <input type="checkbox" name="addons[]" value="<?= (int) $addon['id'] ?>">
                      <span class="option-body">
                        <span class="option-name"><?= e($addon['name']) ?></span>
                        <span class="option-price"><?= (float) $addon['price'] > 0 ? '+' . e(money($addon['price'])) : 'Included' ?></span>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>
            <?php endif; ?>

            <div class="product-actions">
              <label class="field field-qty">
                <span>Quantity</span>
                <input type="number" name="quantity" value="1" min="1" max="20" inputmode="numeric">
              </label>
              <?php if ($price['on_request'] && !$sizes): ?>
                <a class="btn btn-primary" href="<?= e(url('custom-cake.php')) ?>">Request a quotation</a>
              <?php else: ?>
                <button type="submit" class="btn btn-primary">Add to cart</button>
              <?php endif; ?>
            </div>
          </form>

          <div class="product-save">
            <?php if ($user): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="favourite">
                <button type="submit" class="btn btn-ghost btn-small"><?= $isFav ? '♥ Saved to favourites' : '♡ Save to favourites' ?></button>
              </form>
            <?php else: ?>
              <a class="link-button" href="<?= e(url('auth/login.php')) ?>">Sign in to save this cake</a>
            <?php endif; ?>
          </div>

          <?php if (setting('delivery_enabled') === '1' || setting('pickup_enabled') === '1'): ?>
            <ul class="product-facts">
              <?php $minDays = (int) setting('min_advance_days', '0'); ?>
              <?php if ($minDays > 0): ?><li>Order at least <?= $minDays ?> day<?= $minDays === 1 ? '' : 's' ?> ahead.</li><?php endif; ?>
              <?php if (setting('pickup_enabled') === '1'): ?><li>Collection from the studio.</li><?php endif; ?>
              <?php if (setting('delivery_enabled') === '1'): ?><li>Delivery within our partner zones.</li><?php endif; ?>
              <?php if (!empty($product['preparation_days'])): ?><li>Baked to order in about <?= (int) $product['preparation_days'] ?> day<?= (int) $product['preparation_days'] === 1 ? '' : 's' ?>.</li><?php endif; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($product['description'])): ?>
        <div class="product-description">
          <h2 class="section-title">About this cake</h2>
          <p><?= nl2br(e($product['description'])) ?></p>
        </div>
      <?php endif; ?>

      <section class="reviews-block" id="reviews">
        <div class="section-head">
          <div>
            <h2 class="section-title">Reviews</h2>
            <p class="section-sub"><?= $summary['total'] > 0 ? 'Rated ' . e((string) $summary['average']) . ' out of 5 by ' . (int) $summary['total'] . ' customer' . ($summary['total'] === 1 ? '' : 's') . '.' : 'No reviews for this cake yet.' ?></p>
          </div>
        </div>

        <?php if ($canReview): ?>
          <form class="form-card review-form" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review">
            <h3>Write a review</h3>
            <label class="field">
              <span>Rating</span>
              <select name="rating">
                <?php for ($star = 5; $star >= 1; $star--): ?>
                  <option value="<?= $star ?>"><?= str_repeat('★', $star) ?> <?= $star ?></option>
                <?php endfor; ?>
              </select>
            </label>
            <label class="field">
              <span>Title (optional)</span>
              <input type="text" name="review_title" maxlength="160">
            </label>
            <label class="field">
              <span>Your review</span>
              <textarea name="review_body" required minlength="10" maxlength="2000"></textarea>
            </label>
            <button type="submit" class="btn btn-primary">Submit review</button>
          </form>
        <?php elseif ($user): ?>
          <p class="note">Reviews can be written once you have received this cake in a delivered order.</p>
        <?php else: ?>
          <p class="note"><a href="<?= e(url('auth/login.php')) ?>">Sign in</a> to review a cake you have ordered.</p>
        <?php endif; ?>

        <?php if ($reviews): ?>
          <ul class="review-list">
            <?php foreach ($reviews as $review): ?>
              <li class="review">
                <div class="review-head">
                  <strong><?= e($review['customer_name']) ?></strong>
                  <span class="review-stars" aria-label="<?= (int) $review['rating'] ?> out of 5 stars"><?= str_repeat('★', (int) $review['rating']) ?></span>
                  <time datetime="<?= e(substr((string) $review['created_at'], 0, 10)) ?>"><?= e(format_date($review['created_at'])) ?></time>
                  <?php if ((int) $review['is_verified'] === 1): ?><span class="review-verified">Verified order</span><?php endif; ?>
                </div>
                <?php if (!empty($review['title'])): ?><p class="review-title"><?= e($review['title']) ?></p><?php endif; ?>
                <p class="review-body"><?= nl2br(e($review['body'])) ?></p>
                <?php if (!empty($review['admin_reply'])): ?>
                  <p class="review-reply"><strong>MADAM CAKES replied:</strong> <?= nl2br(e($review['admin_reply'])) ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <?php if ($related): ?>
        <section class="section-tight">
          <h2 class="section-title">You may also love</h2>
          <div class="cake-grid">
            <?php foreach ($related as $item): ?>
              <?= product_card($item) ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
