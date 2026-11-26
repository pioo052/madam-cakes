<?php
/**
 * MADAM CAKES — shared site footer.
 * Closes <main>-level layout and the document opened by includes/header.php.
 */

declare(strict_types=1);

$businessName = (string) (setting('business_name', APP_NAME) ?? APP_NAME);
$homeUrl      = url('');
$whatsapp     = whatsapp_number();
$footerAbout  = content('footer_about');

$footerContact = [];
foreach ([
    'address'       => (string) setting('address', ''),
    'phone'         => (string) setting('phone', ''),
    'email'         => (string) setting('email', ''),
    'opening_hours' => (string) setting('opening_hours', ''),
] as $key => $value) {
    if (trim($value) !== '') {
        $footerContact[$key] = trim($value);
    }
}

$footerSocials = social_links();
$payments      = payment_methods();
?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="brand brand-footer" href="<?= e($homeUrl) ?>">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 26h22"/><path d="M7 26v-7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7"/>
            <path d="M7 21c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 2-2"/>
            <path d="M16 13V9"/><path d="M16 6.2c.9.8 1.9.5 1.9-.6 0-1-1-1.6-1.9-2.6-.9 1-1.9 1.6-1.9 2.6 0 1.1 1 1.4 1.9.6z"/>
          </svg>
        </span>
        <span class="brand-text">
          <span class="brand-line brand-line-strong"><?= e($businessName) ?></span>
        </span>
      </a>
      <?php if ($footerAbout): ?>
        <p class="footer-about"><?= e($footerAbout) ?></p>
      <?php endif; ?>
      <?php if ($footerSocials): ?>
        <ul class="footer-socials">
          <?php foreach ($footerSocials as $social): ?>
            <li><a href="<?= e($social['href']) ?>" target="_blank" rel="noopener noreferrer"><?= e($social['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="footer-col">
      <h3 class="footer-heading">Explore</h3>
      <ul class="footer-links">
        <li><a href="<?= e($homeUrl) ?>">Home</a></li>
        <li><a href="<?= e(url('cakes.php')) ?>">All cakes</a></li>
        <li><a href="<?= e(url('custom-cake.php')) ?>">Design your cake</a></li>
        <li><a href="<?= e(url('gallery.php')) ?>">Gallery</a></li>
        <li><a href="<?= e(url('reviews.php')) ?>">Reviews</a></li>
        <li><a href="<?= e(url('about.php')) ?>">Our story</a></li>
        <li><a href="<?= e(url('contact.php')) ?>">Contact</a></li>
        <li><a href="<?= e(url('track.php')) ?>">Track an order</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h3 class="footer-heading">Visit &amp; contact</h3>
      <?php if ($footerContact): ?>
        <ul class="footer-contact">
          <?php if (isset($footerContact['address'])): ?>
            <li><?= e($footerContact['address']) ?></li>
          <?php endif; ?>
          <?php if (isset($footerContact['phone'])): ?>
            <li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $footerContact['phone'])) ?>"><?= e($footerContact['phone']) ?></a></li>
          <?php endif; ?>
          <?php if (isset($footerContact['email'])): ?>
            <li><a href="mailto:<?= e($footerContact['email']) ?>"><?= e($footerContact['email']) ?></a></li>
          <?php endif; ?>
          <?php if (isset($footerContact['opening_hours'])): ?>
            <li><?= e($footerContact['opening_hours']) ?></li>
          <?php endif; ?>
        </ul>
      <?php else: ?>
        <p class="footer-muted">Our address and phone number will be published here soon.</p>
      <?php endif; ?>
      <?php if ($whatsapp !== ''): ?>
        <a class="btn btn-outline btn-small" href="<?= e(whatsapp_url('Hello ' . $businessName . '!')) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a>
      <?php endif; ?>
    </div>

    <div class="footer-col">
      <h3 class="footer-heading">Ordering</h3>
      <ul class="footer-contact">
        <?php $minDays = (int) setting('min_advance_days', '0'); ?>
        <?php if ($minDays > 0): ?>
          <li>Please order at least <?= (int) $minDays ?> day<?= $minDays === 1 ? '' : 's' ?> in advance.</li>
        <?php endif; ?>
        <?php if (setting('delivery_enabled') === '1'): ?>
          <li>Delivery available.</li>
        <?php endif; ?>
        <?php if (setting('pickup_enabled') === '1'): ?>
          <li>Collection from the studio.</li>
        <?php endif; ?>
        <?php if ($payments): ?>
          <li>Payments: <?= e(implode(', ', $payments)) ?>.</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="container footer-bottom">
    <p>&copy; <?= date('Y') ?> <?= e($businessName) ?>. All rights reserved.</p>
    <p class="footer-note">Prices and availability are confirmed by <?= e($businessName) ?>.</p>
  </div>
</footer>

<?php if ($whatsapp !== ''): ?>
  <a class="whatsapp-float" href="<?= e(whatsapp_url('Hello ' . $businessName . '! I would like to order a cake.')) ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
      <path d="M12.04 2C6.6 2 2.2 6.4 2.2 11.84c0 1.74.46 3.43 1.32 4.92L2 22l5.37-1.4a9.84 9.84 0 0 0 4.67 1.19h.01c5.43 0 9.84-4.4 9.84-9.84C21.89 6.4 17.48 2 12.04 2zm5.76 14c-.24.68-1.4 1.3-1.93 1.35-.5.05-1.1.07-1.77-.11-2.02-.67-3.98-2.62-4.4-3.2-.42-.58-1.14-1.55-1.14-2.55 0-1 .53-1.5.72-1.7.19-.2.42-.25.56-.25h.4c.13 0 .3-.05.47.36.17.42.6 1.45.65 1.55.05.1.08.22.02.36-.07.14-.1.22-.2.35-.1.12-.22.28-.31.37-.1.1-.2.21-.09.4.11.2.5.86 1.08 1.39.74.68 1.37.9 1.56.99.2.1.31.08.43-.05.11-.13.49-.57.62-.76.13-.2.26-.16.44-.1.18.07 1.16.55 1.36.65.2.1.33.15.38.23.05.09.05.5-.19 1.18z"/>
    </svg>
  </a>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js" defer></script>
<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
</body>
</html>
