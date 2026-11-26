<?php
/**
 * MADAM CAKES — shared site header.
 *
 * Every page includes this before its content and includes/footer.php after it.
 * Optional variables a page may set first:
 *   $pageTitle        string  extra title segment, e.g. 'Cakes'
 *   $pageDescription  string  meta description
 *   $activeNav        string  home|cakes|about|contact
 *   $bodyClass        string  extra <body> classes
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$businessName = (string) (setting('business_name', APP_NAME) ?? APP_NAME);
$tagline      = (string) (setting('tagline', APP_TAGLINE) ?? APP_TAGLINE);
$logoPath     = trim((string) setting('logo_path', ''));

$pageTitle       = $pageTitle ?? null;
$pageDescription = $pageDescription ?? (content('hero_title') ?? $tagline);
$activeNav       = $activeNav ?? 'home';
$bodyClass       = trim('site-body ' . ($bodyClass ?? ''));

$homeUrl = url('');

$cartCount = cart_count();
$cartUrl   = url('cart.php');
$searchUrl = url('cakes.php');

$currentUser = auth_user();
$accountUrl  = $currentUser ? url('account/index.php') : url('auth/login.php');

$navItems = [
    ['key' => 'home',    'label' => 'Home',    'href' => $homeUrl],
    ['key' => 'cakes',   'label' => 'Cakes',   'href' => url('cakes.php')],
    ['key' => 'gallery', 'label' => 'Gallery', 'href' => url('gallery.php')],
    ['key' => 'about',   'label' => 'About',   'href' => url('about.php')],
    ['key' => 'contact', 'label' => 'Contact', 'href' => url('contact.php')],
];

$socials = social_links();

$whatsapp = whatsapp_number();
$promoActive = setting('promo_banner_active') === '1';
$promoText   = trim((string) setting('promo_banner_text', ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ? $pageTitle . ' · ' . $businessName : $businessName . ' · ' . $tagline) ?></title>
<meta name="description" content="<?= e((string) $pageDescription) ?>">
<meta name="theme-color" content="#E0436F">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($businessName) ?>">
<meta property="og:title" content="<?= e($pageTitle ? $pageTitle . ' · ' . $businessName : $businessName) ?>">
<meta property="og:description" content="<?= e((string) $pageDescription) ?>">
<meta property="og:image" content="<?= e(asset('assets/img/hero-cake.jpg')) ?>">
<meta name="twitter:card" content="summary_large_image">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/theme.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/store.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<?php if ($promoActive && $promoText !== ''): ?>
  <div class="promo-bar">
    <div class="container promo-bar-inner">
      <span><?= e($promoText) ?></span>
    </div>
  </div>
<?php endif; ?>

<header class="site-header" id="siteHeader">
  <div class="container header-inner">
    <a class="brand" href="<?= e($homeUrl) ?>" aria-label="<?= e($businessName) ?> — home">
      <?php if ($logoPath !== ''): ?>
        <img class="brand-logo" src="<?= e(upload_url($logoPath)) ?>" alt="<?= e($businessName) ?>">
      <?php else: ?>
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 26h22"/><path d="M7 26v-7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7"/>
            <path d="M7 21c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 2-2"/>
            <path d="M16 13V9"/><path d="M16 6.2c.9.8 1.9.5 1.9-.6 0-1-1-1.6-1.9-2.6-.9 1-1.9 1.6-1.9 2.6 0 1.1 1 1.4 1.9.6z"/>
          </svg>
        </span>
      <?php endif; ?>
      <?php $brandWords = preg_split('/\s+/', trim($businessName)) ?: [$businessName]; ?>
      <span class="brand-text">
        <span class="brand-line brand-line-strong"><?= e(array_shift($brandWords)) ?></span>
        <?php if ($brandWords): ?>
          <span class="brand-line"><?= e(implode(' ', $brandWords)) ?></span>
        <?php endif; ?>
      </span>
    </a>

    <nav class="primary-nav" aria-label="Primary">
      <ul>
        <?php foreach ($navItems as $item): ?>
          <li>
            <a href="<?= e($item['href']) ?>"<?= $activeNav === $item['key'] ? ' class="is-active" aria-current="page"' : '' ?>>
              <?= e($item['label']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="header-actions">
      <a class="icon-btn" href="<?= e($searchUrl) ?>" aria-label="Search cakes">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
          <circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>
        </svg>
      </a>

      <a class="icon-btn" href="<?= e($accountUrl) ?>" aria-label="<?= $currentUser ? 'My account' : 'Sign in' ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="8" r="3.6"/><path d="M4.8 20a7.2 7.2 0 0 1 14.4 0"/>
        </svg>
      </a>

      <?php
      $cartInner = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
          . '<path d="M6 8h12l-1.2 10.2a2 2 0 0 1-2 1.8H9.2a2 2 0 0 1-2-1.8L6 8z"/>'
          . '<path d="M9.5 8V6.8a2.5 2.5 0 0 1 5 0V8"/></svg>'
          . '<span class="cart-count" data-cart-count>' . (int) $cartCount . '</span>';
      ?>
      <a class="icon-btn cart-btn" href="<?= e($cartUrl) ?>" aria-label="Cart, <?= (int) $cartCount ?> item<?= $cartCount === 1 ? '' : 's' ?>" data-cart-link>
        <?= $cartInner ?>
      </a>

      <button class="nav-toggle" type="button" id="navToggle" aria-controls="mobileDrawer" aria-expanded="false" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>

<div class="nav-scrim" id="navScrim" hidden></div>

<aside class="mobile-drawer" id="mobileDrawer" aria-label="Site menu" aria-hidden="true">
  <div class="drawer-head">
    <span class="drawer-title"><?= e($businessName) ?></span>
    <button class="drawer-close" type="button" id="drawerClose" aria-label="Close menu">&times;</button>
  </div>

  <nav class="drawer-nav" aria-label="Mobile">
    <ul>
      <?php foreach ($navItems as $item): ?>
        <li>
          <a href="<?= e($item['href']) ?>"<?= $activeNav === $item['key'] ? ' class="is-active"' : '' ?>>
            <?= e($item['label']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <div class="drawer-actions">
    <a class="btn btn-primary btn-block" href="<?= e(url('cakes.php')) ?>">Browse cakes</a>
    <a class="btn btn-outline btn-block" href="<?= e($accountUrl) ?>"><?= $currentUser ? 'My account' : 'Sign in / Register' ?></a>
    <?php if ($whatsapp !== ''): ?>
      <a class="btn btn-outline btn-block" href="<?= e(whatsapp_url('Hello ' . $businessName . '! I would like to ask about a cake.')) ?>" target="_blank" rel="noopener">
        Chat on WhatsApp
      </a>
    <?php endif; ?>
  </div>

  <div class="drawer-contact">
    <?php
    $drawerLines = [
        'phone'         => (string) setting('phone', ''),
        'email'         => (string) setting('email', ''),
        'address'       => (string) setting('address', ''),
        'opening_hours' => (string) setting('opening_hours', ''),
    ];
    ?>
    <?php foreach ($drawerLines as $line): ?>
      <?php if (trim($line) !== ''): ?>
        <p><?= e($line) ?></p>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($socials): ?>
    <div class="drawer-socials">
      <?php foreach ($socials as $social): ?>
        <a href="<?= e($social['href']) ?>" target="_blank" rel="noopener noreferrer"><?= e($social['label']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</aside>

<?php $messages = flashes(); ?>
<?php if ($messages): ?>
  <div class="container flash-stack" role="status" aria-live="polite">
    <?php foreach ($messages as $message): ?>
      <?php $tone = in_array($message['type'], ['error', 'success', 'warning'], true) ? $message['type'] : 'info'; ?>
      <div class="note<?= $tone === 'info' ? '' : ' note-' . e($tone) ?>">
        <?= e($message['message']) ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
