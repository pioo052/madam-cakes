<?php
/**
 * MADAM CAKES — gallery of cakes the studio has actually photographed.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

$categories = ['birthday', 'wedding', 'graduation', 'anniversary', 'baby_shower', 'custom', 'other'];
$active     = (string) get('category');
if ($active !== '' && !in_array($active, $categories, true)) {
    $active = '';
}

$sql = 'SELECT * FROM gallery WHERE is_active = 1';
$params = [];
if ($active !== '') {
    $sql .= ' AND category = ?';
    $params[] = $active;
}
$sql .= ' ORDER BY is_featured DESC, sort_order, created_at DESC';
$items = Database::fetchAll($sql, $params);

$pageTitle       = 'Gallery';
$pageDescription = 'Photographs of cakes made by MADAM CAKES.';
$activeNav       = 'gallery';
require __DIR__ . '/includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">From the studio</p>
      <h1 class="section-title">Cake gallery</h1>
      <p class="section-sub">Every photograph here is a cake we have made. When the studio adds new work, it appears here.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container">
      <?php if ($items): ?>
        <nav class="filter-chips" aria-label="Gallery categories">
          <a class="filter-chip<?= $active === '' ? ' is-active' : '' ?>" href="<?= e(url('gallery.php')) ?>">All</a>
          <?php foreach ($categories as $category): ?>
            <a class="filter-chip<?= $active === $category ? ' is-active' : '' ?>" href="<?= e(url('gallery.php?category=' . $category)) ?>"><?= e(ucwords(str_replace('_', ' ', $category))) ?></a>
          <?php endforeach; ?>
        </nav>

        <ul class="gallery-grid">
          <?php foreach ($items as $item): ?>
            <li class="gallery-item reveal">
              <figure>
                <img src="<?= e(upload_url($item['image'])) ?>" alt="<?= e($item['title']) ?>" loading="lazy" width="640" height="640">
                <figcaption>
                  <span class="gallery-title"><?= e($item['title']) ?></span>
                  <?php if ($item['caption']): ?><span class="gallery-caption"><?= e($item['caption']) ?></span><?php endif; ?>
                </figcaption>
              </figure>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state">
          <h2>The gallery is being prepared</h2>
          <p>The studio has not published photographs yet. In the meantime, the cake menu shows what is available to order.</p>
          <a class="btn btn-primary" href="<?= e(url('cakes.php')) ?>">Browse cakes</a>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
