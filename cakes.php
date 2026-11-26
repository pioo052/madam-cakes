<?php
/**
 * MADAM CAKES — cake catalogue: category filter, search, sort and pagination.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/store.php';

$categories   = categories_active();
$categorySlug = trim((string) get('category'));
$q            = trim((string) get('q'));
$sort         = (string) get('sort', 'newest');
if (!in_array($sort, ['newest', 'price_asc', 'price_desc', 'name', 'popular'], true)) {
    $sort = 'newest';
}

$category = $categorySlug !== '' ? category_by_slug($categorySlug) : null;
if ($categorySlug !== '' && $category === null) {
    http_response_code(404);
}

$result = products_search([
    'category' => $category ? $category['slug'] : '',
    'q'        => $q,
    'sort'     => $sort,
    'limit'    => 12,
    'page'     => current_page(),
]);
$products = $result['rows'];
$pager    = $result['pager'];

$totalProducts = (int) Database::value('SELECT COUNT(*) FROM products WHERE is_active = 1', [], 0);

$pageTitle       = $category ? (string) $category['name'] : 'Our cakes';
$pageDescription = $category && $category['description']
    ? (string) $category['description']
    : 'Browse handcrafted birthday, wedding, graduation and celebration cakes from MADAM CAKES.';
$activeNav       = 'cakes';
require __DIR__ . '/includes/header.php';

/** Keep the other filters when switching category. */
$chipUrl = static function (string $slug) use ($q, $sort): string {
    $params = array_filter(['category' => $slug, 'q' => $q, 'sort' => $sort !== 'newest' ? $sort : '']);
    return url('cakes.php' . ($params ? '?' . http_build_query($params) : ''));
};
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">The cake menu</p>
      <h1 class="section-title"><?= e($category ? $category['name'] : 'Cakes for every celebration') ?></h1>
      <p class="section-sub"><?= e($category && $category['description'] ? $category['description'] : 'Every cake is baked to order. Choose a size and flavour, and we will take care of the rest.') ?></p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container">
      <div class="catalogue-toolbar">
        <form class="catalogue-search" method="get" action="<?= e(url('cakes.php')) ?>" role="search">
          <?php if ($category): ?>
            <input type="hidden" name="category" value="<?= e((string) $category['slug']) ?>">
          <?php endif; ?>
          <label class="visually-hidden" for="catalogueQ">Search cakes</label>
          <input type="search" id="catalogueQ" name="q" value="<?= e($q) ?>" placeholder="Search cakes…">
          <label class="visually-hidden" for="catalogueSort">Sort cakes</label>
          <select id="catalogueSort" name="sort">
            <option value="newest"<?= $sort === 'newest' ? ' selected' : '' ?>>Newest first</option>
            <option value="popular"<?= $sort === 'popular' ? ' selected' : '' ?>>Most loved</option>
            <option value="price_asc"<?= $sort === 'price_asc' ? ' selected' : '' ?>>Price: low to high</option>
            <option value="price_desc"<?= $sort === 'price_desc' ? ' selected' : '' ?>>Price: high to low</option>
            <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Name A–Z</option>
          </select>
          <button type="submit" class="btn btn-primary btn-small">Search</button>
        </form>
      </div>

      <?php if ($categories): ?>
        <nav class="filter-chips" aria-label="Cake categories">
          <a class="filter-chip<?= $category === null ? ' is-active' : '' ?>" href="<?= e($chipUrl('')) ?>">All cakes</a>
          <?php foreach ($categories as $item): ?>
            <a class="filter-chip<?= $category && $category['id'] === $item['id'] ? ' is-active' : '' ?>" href="<?= e($chipUrl((string) $item['slug'])) ?>"><?= e($item['name']) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>

      <?php if ($q !== '' || $category): ?>
        <p class="results-note">
          <?= (int) $pager['total'] ?> result<?= (int) $pager['total'] === 1 ? '' : 's' ?>
          <?php if ($q !== ''): ?> for “<?= e($q) ?>”<?php endif; ?>
          <a class="results-clear" href="<?= e(url('cakes.php')) ?>">Clear filters</a>
        </p>
      <?php endif; ?>

      <?php if ($products): ?>
        <div class="cake-grid">
          <?php foreach ($products as $product): ?>
            <?= product_card($product) ?>
          <?php endforeach; ?>
        </div>
        <?= pagination_html($pager, 'Cake catalogue') ?>
      <?php else: ?>
        <div class="empty-state">
          <?php if ($totalProducts === 0): ?>
            <h2>Our cake menu is being prepared</h2>
            <p>The studio is adding this season’s cakes. Tell us what you are celebrating and we will design something for you.</p>
            <div class="empty-actions">
              <a class="btn btn-primary" href="<?= e(url('custom-cake.php')) ?>">Design your cake</a>
              <?php if (whatsapp_number() !== ''): ?>
                <a class="btn btn-outline" href="<?= e(whatsapp_url('Hello! I would like to ask about a cake.')) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <h2>No cakes match those filters</h2>
            <p>Try a different search, or browse every cake we have available.</p>
            <a class="btn btn-outline" href="<?= e(url('cakes.php')) ?>">View all cakes</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
