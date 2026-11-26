<?php
/**
 * MADAM CAKES — homepage.
 *
 * Every figure on this page comes from the database. When the owner has not yet
 * added products or published reviews, the page says so instead of inventing
 * cakes, prices or testimonials.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

// ---------------------------------------------------------------------------
// Data (all owner-managed)
// ---------------------------------------------------------------------------
$categories = Database::fetchAll(
    "SELECT c.id, c.name, c.slug, c.description,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS cake_count
       FROM categories c
      WHERE c.is_active = 1
      ORDER BY c.sort_order, c.name"
);

$featured = products_search(['featured' => true, 'limit' => 8])['rows'];

$reviewStats = Database::fetch(
    'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average
       FROM reviews WHERE is_approved = 1'
) ?? ['total' => 0, 'average' => 0];

$reviewTotal = (int) $reviewStats['total'];
$reviewAverage = round((float) $reviewStats['average'], 1);

// ---------------------------------------------------------------------------
// Owner-controlled copy
// ---------------------------------------------------------------------------
$businessName = (string) (setting('business_name', APP_NAME) ?? APP_NAME);
$whatsapp     = whatsapp_number();
$heroEyebrow  = (string) (content_title('hero_eyebrow', 'Baked fresh to order') ?? '');
$heroTitle    = (string) (content_title('hero_title', APP_TAGLINE) ?? APP_TAGLINE);
$primaryCta   = (string) (content('hero_primary_cta', 'Shop cakes') ?? 'Shop cakes');
$secondaryCta = (string) (content('hero_secondary_cta', 'Design your cake') ?? 'Design your cake');

// The hero title's last word gets the magenta italic accent from the reference.
$heroWords = preg_split('/\s+/', trim($heroTitle)) ?: [$heroTitle];
$heroLast  = $heroWords ? array_pop($heroWords) : '';
$heroStart = implode(' ', $heroWords);

// The designer page arrives in a later phase; until then the same invitation
// leads to a real destination instead of a broken link.
$designerUrl = is_file(ROOT_PATH . '/custom-cake.php')
    ? url('custom-cake.php')
    : ($whatsapp !== '' ? whatsapp_url('Hello ' . $businessName . '! I would like to design a custom cake.') : '#contact');

// ---------------------------------------------------------------------------
// Contact + service facts taken from settings (never guessed)
// ---------------------------------------------------------------------------
$minDays    = (int) setting('min_advance_days', '0');
$hasDelivery = setting('delivery_enabled') === '1';
$hasPickup   = setting('pickup_enabled') === '1';

$paymentList = payment_methods();

$whyTiles = [];
if ($minDays > 0) {
    $whyTiles[] = [
        'Baked to order',
        'Cakes are baked for your date, so please order at least ' . $minDays . ' day' . ($minDays === 1 ? '' : 's') . ' ahead.',
    ];
}
if ($hasDelivery) {
    $whyTiles[] = [
        'Delivered to you',
        'Delivery can be arranged with your order. The fee is confirmed by ' . $businessName . '.',
    ];
}
if ($hasPickup) {
    $whyTiles[] = [
        'Collect from the studio',
        'Prefer to collect? Arrange pick-up when you place your order.',
    ];
}
if ($paymentList) {
    $whyTiles[] = [
        'Payments we accept',
        ucfirst(implode(', ', $paymentList)) . '.',
    ];
}
$whyTiles = array_slice($whyTiles, 0, 4);

$contactItems = [];
foreach ([
    'address'       => 'Studio',
    'phone'         => 'Call',
    'email'         => 'Email',
    'opening_hours' => 'Opening hours',
] as $key => $label) {
    $value = trim((string) setting($key, ''));
    if ($value !== '') {
        $contactItems[] = ['label' => $label, 'value' => $value, 'key' => $key];
    }
}
$mapsUrl = trim((string) setting('maps_url', ''));

$pageTitle = null;
$activeNav = 'home';
$bodyClass = 'home';

require __DIR__ . '/includes/header.php';
?>

<main id="main">

  <!-- ============================================================ hero -->
  <div class="hero-wrap">
    <div class="container">
      <section class="hero-card">
        <p class="hero-micro" aria-hidden="true">Any type of cake you need</p>

        <div class="hero-grid">
          <div class="hero-copy reveal">
            <?php if ($heroEyebrow !== ''): ?>
              <p class="hero-eyebrow"><?= e($heroEyebrow) ?></p>
            <?php endif; ?>

            <h1 class="hero-title">
              <?php if ($heroStart !== ''): ?>
                <?= e($heroStart) ?>
              <?php endif; ?>
              <em><?= e($heroLast) ?></em>
            </h1>

            <?php $heroBody = content_body('hero_title'); ?>
            <?php if ($heroBody !== null): ?>
              <p class="hero-lead"><?= e($heroBody) ?></p>
            <?php endif; ?>

            <div class="hero-actions">
              <a class="btn btn-primary" href="<?= e(url('cakes.php')) ?>"><?= e($primaryCta) ?></a>
              <a class="btn btn-outline" href="<?= e($designerUrl) ?>"<?= preg_match('~^https?://~i', $designerUrl) ? ' target="_blank" rel="noopener"' : '' ?>>
                <?= e($secondaryCta) ?>
              </a>
            </div>
          </div>

          <div class="hero-media reveal" data-delay="2">
            <div class="arch">
              <img src="<?= e(asset('assets/img/hero-cake.jpg')) ?>"
                   alt="Hand-finished layered cake topped with fresh strawberries and cream"
                   width="880" height="1184" fetchpriority="high">
            </div>

            <div class="hero-stamp" aria-hidden="true">
              <svg viewBox="0 0 120 120">
                <defs>
                  <path id="stampTextPath" d="M60,60 m-46,0 a46,46 0 1,1 92,0 a46,46 0 1,1 -92,0" />
                </defs>
                <circle cx="60" cy="60" r="57" fill="#fff" stroke="currentColor" stroke-width="1" stroke-dasharray="3 5" opacity=".85" />
                <text fill="currentColor" font-size="8" font-family="DM Sans, sans-serif" letter-spacing="2.4">
                  <textPath href="#stampTextPath" startOffset="0">
                    WE CARE ABOUT YOUR CAKE · WE CARE ABOUT YOUR CAKE ·
                  </textPath>
                </text>
                <text x="60" y="55" text-anchor="middle" fill="currentColor" font-size="15" font-family="Playfair Display, serif">M</text>
                <text x="60" y="70" text-anchor="middle" fill="currentColor" font-size="7" letter-spacing="1.6" font-family="DM Sans, sans-serif">
                  <?= e(mb_strtoupper(mb_substr($businessName, 0, 12))) ?>
                </text>
              </svg>
            </div>

            <?php if ($reviewTotal > 0): ?>
              <p class="hero-rating">
                <span class="stars" aria-hidden="true">★★★★★</span>
                <span><?= e(number_format($reviewAverage, 1)) ?></span>
                <span class="visually-hidden"><?= (int) $reviewTotal ?> published reviews</span>
              </p>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </div>
  </div>

  <!-- ====================================================== categories -->
  <section class="section" id="cakes">
    <div class="container">
      <div class="section-head reveal">
        <div>
          <span class="eyebrow">The cake menu</span>
          <h2 class="section-title">Cakes for every celebration</h2>
          <p class="section-sub">Choose a style to start from, or tell us your own idea — every cake is made to order.</p>
        </div>
      </div>

      <?php if ($categories): ?>
        <div class="category-grid">
          <?php foreach ($categories as $index => $category): ?>
            <article class="category-card tone-<?= ($index % 4) + 1 ?> reveal" data-delay="<?= $index % 4 > 2 ? 3 : $index % 4 ?>">
              <span class="category-index"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <div>
                <h3 class="category-name"><a href="<?= e(category_url((string) $category['slug'])) ?>"><?= e($category['name']) ?></a></h3>
                <?php if (trim((string) $category['description']) !== ''): ?>
                  <p class="category-desc"><?= e($category['description']) ?></p>
                <?php endif; ?>
              </div>
              <p class="category-meta">
                <?php if ((int) $category['cake_count'] > 0): ?>
                  <?= (int) $category['cake_count'] ?> cake<?= (int) $category['cake_count'] === 1 ? '' : 's' ?>
                <?php else: ?>
                  Made to order
                <?php endif; ?>
                <a class="category-link" href="<?= e(category_url((string) $category['slug'])) ?>">Browse &rarr;</a>
              </p>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <h3>No categories published yet</h3>
          <p>Cake categories added in the administration area appear here automatically.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ========================================================== feature -->
  <section class="section-tight">
    <div class="container">
      <div class="section-head reveal">
        <div>
          <span class="eyebrow">From our kitchen</span>
          <h2 class="section-title">Signature cakes</h2>
          <p class="section-sub">A small selection of the cakes currently available to order.</p>
        </div>
      </div>

      <?php if ($featured): ?>
        <div class="cake-grid">
          <?php foreach ($featured as $cake): ?>
            <?= product_card($cake) ?>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state reveal">
          <h3>The cake menu is being prepared</h3>
          <p>
            Our cakes are being photographed and priced, so nothing is listed here yet.
            Tell us what you have in mind and the team will quote it personally.
          </p>
          <div class="hero-actions">
            <a class="btn btn-primary" href="#custom">Design your cake</a>
            <?php if ($whatsapp !== ''): ?>
              <a class="btn btn-outline" href="<?= e(whatsapp_url('Hello ' . $businessName . '! I would like a quotation for a cake.')) ?>" target="_blank" rel="noopener">
                Ask on WhatsApp
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ==================================================== custom cake -->
  <section class="section" id="custom">
    <div class="container">
      <div class="custom-band reveal">
        <div class="custom-grid">
          <div>
            <span class="eyebrow">Made for you</span>
            <h2 class="section-title"><?= e((string) content_title('custom_invite_title', 'Have an idea in mind?')) ?></h2>
            <p><?= e((string) content_body('custom_invite_title', 'Design your cake step by step and send it to our team for a personal quotation.')) ?></p>

            <ol class="step-list">
              <li><span>1</span> Choose the occasion, shape and size.</li>
              <li><span>2</span> Pick a flavour, cream colour and finish.</li>
              <li><span>3</span> Add a reference photograph if you have one.</li>
              <li><span>4</span> Receive a personal quotation before anything is confirmed.</li>
            </ol>

            <div class="hero-actions">
              <a class="btn btn-primary" href="<?= e($designerUrl) ?>"<?= preg_match('~^https?://~i', $designerUrl) ? ' target="_blank" rel="noopener"' : '' ?>>
                Start designing
              </a>
            </div>
          </div>

          <div class="custom-media">
            <img src="<?= e(asset('assets/img/custom-cake.jpg')) ?>"
                 alt="Cake being finished by hand with piped buttercream detail"
                 loading="lazy" width="1184" height="880">
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ======================================================== surprise -->
  <section class="section-tight">
    <div class="container">
      <div class="about-band reveal">
        <div class="about-mark">
          <div>
            <strong>Surprise delivery</strong>
            <span><?= e($hasDelivery ? 'Available' : 'Ask our team') ?></span>
          </div>
        </div>
        <div>
          <span class="eyebrow">Send some joy</span>
          <h2 class="section-title"><?= e((string) content_title('surprise_title', 'Send a surprise')) ?></h2>
          <p class="text-muted">
            <?= e((string) content_body('surprise_title', 'Arrange a cake for someone you love, with a personal message and delivery to their door.')) ?>
          </p>
          <div class="hero-actions">
            <a class="btn btn-outline" href="#contact">Talk to us about delivery</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ======================================================== why us -->
  <?php if ($whyTiles): ?>
    <section class="section">
      <div class="container">
        <div class="section-head reveal">
          <div>
            <span class="eyebrow">Ordering with confidence</span>
            <h2 class="section-title"><?= e((string) content_title('why_choose_title', 'Why families choose us')) ?></h2>
          </div>
        </div>
        <div class="why-grid">
          <?php foreach ($whyTiles as $index => $tile): ?>
            <article class="why-card reveal" data-delay="<?= $index % 4 > 2 ? 3 : $index % 4 ?>">
              <h3><?= e($tile[0]) ?></h3>
              <p><?= e($tile[1]) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ========================================================= about -->
  <section class="section-tight" id="about">
    <div class="container">
      <div class="about-band reveal">
        <div class="about-mark">
          <div>
            <strong><?= e($businessName) ?></strong>
            <span><?= e((string) setting('tagline', APP_TAGLINE)) ?></span>
          </div>
        </div>
        <div>
          <span class="eyebrow">Our story</span>
          <h2 class="section-title"><?= e((string) content_title('about_title', 'The ' . $businessName . ' story')) ?></h2>
          <p class="text-muted"><?= e((string) content_body('about_title', '')) ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ======================================================= reviews -->
  <section class="section-tight">
    <div class="container">
      <div class="section-head reveal">
        <div>
          <span class="eyebrow">In their words</span>
          <h2 class="section-title">What our customers say</h2>
        </div>
      </div>

      <?php if ($reviewTotal > 0): ?>
        <div class="empty-state reveal">
          <h3><?= e(number_format($reviewAverage, 1)) ?> out of 5</h3>
          <p>Based on <?= (int) $reviewTotal ?> published review<?= $reviewTotal === 1 ? '' : 's' ?>.</p>
        </div>
      <?php else: ?>
        <div class="empty-state reveal">
          <h3>No reviews published yet</h3>
          <p>Customer reviews appear here once orders have been delivered and approved for publication. We do not publish testimonials we did not receive.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ======================================================= contact -->
  <section class="section" id="contact">
    <div class="container">
      <div class="contact-band reveal">
        <span class="eyebrow">Order or ask a question</span>
        <h2 class="section-title">Talk to <?= e($businessName) ?></h2>
        <p class="on-dark contact-lead">
          <?= e((string) content('contact_note', 'Send us a message and we will reply during opening hours.')) ?>
        </p>

        <?php if ($contactItems): ?>
          <div class="contact-grid">
            <?php foreach ($contactItems as $item): ?>
              <div class="contact-item">
                <strong><?= e($item['label']) ?></strong>
                <?php if ($item['key'] === 'phone'): ?>
                  <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $item['value'])) ?>"><?= e($item['value']) ?></a>
                <?php elseif ($item['key'] === 'email'): ?>
                  <a href="mailto:<?= e($item['value']) ?>"><?= e($item['value']) ?></a>
                <?php else: ?>
                  <?= e($item['value']) ?>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="on-dark">Phone, email and studio address are being added — please check back shortly.</p>
        <?php endif; ?>

        <div class="hero-actions">
          <?php if ($whatsapp !== ''): ?>
            <a class="btn btn-gold" href="<?= e(whatsapp_url('Hello ' . $businessName . '! I would like to place an order.')) ?>" target="_blank" rel="noopener">
              Message us on WhatsApp
            </a>
          <?php endif; ?>
          <?php if ($mapsUrl !== ''): ?>
            <a class="btn btn-outline btn-on-dark" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener">Find us on the map</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
