<?php
/**
 * MADAM CAKES — about page, built from owner-editable copy.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

$pageTitle       = 'Our story';
$pageDescription = content_body('about_title', 'The story behind MADAM CAKES.');
$activeNav       = 'about';
require __DIR__ . '/includes/header.php';

$storyTitle = content_title('about_title', 'The ' . setting('business_name', APP_NAME) . ' story');
$storyBody  = content_body('about_title', 'Every cake is baked to order and finished by hand.');
$aboutRow   = content_row('about_title');
$heroImage  = (string) ($aboutRow['image'] ?? '');
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">Baked with care</p>
      <h1 class="section-title"><?= e($storyTitle) ?></h1>
      <p class="section-sub"><?= e(excerpt($storyBody, 180)) ?></p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container about-grid">
      <div class="about-copy">
        <p><?= nl2br(e($storyBody)) ?></p>
        <?php if ($whyTitle = content_title('why_choose_title')): ?>
          <h2 class="section-title"><?= e($whyTitle) ?></h2>
        <?php endif; ?>
        <ul class="about-list">
          <li><strong>Baked to order.</strong> Nothing is made until you order it, so it reaches you fresh.</li>
          <li><strong>Owner-set prices.</strong> Every price you see comes from the studio, never from a template.</li>
          <li><strong>Honest updates.</strong> Your order status changes only when the studio updates it.</li>
          <li><strong>Personal service.</strong> Custom designs are quoted by a real person, not an automated estimate.</li>
        </ul>
        <a class="btn btn-primary" href="<?= e(url('cakes.php')) ?>">Browse the cakes</a>
      </div>
      <div class="about-media">
        <?php if ($heroImage !== ''): ?>
          <img src="<?= e(upload_url((string) $heroImage)) ?>" alt="<?= e($storyTitle) ?>" width="640" height="800">
        <?php else: ?>
          <img src="<?= e(asset('assets/img/hero-cake.jpg')) ?>" alt="A handcrafted MADAM CAKES celebration cake" width="640" height="860">
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="section-tight">
    <div class="container">
      <div class="cta-band">
        <div>
          <h2 class="section-title">Have a design in mind?</h2>
          <p>Tell us the occasion and we will quote your cake personally.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('custom-cake.php')) ?>">Design your cake</a>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
