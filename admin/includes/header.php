<?php
/**
 * MADAM CAKES — administrator layout header.
 *
 * Expects: $admin (from require_admin()), $adminTitle, $adminActive.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/store.php';
require_once __DIR__ . '/../../includes/auth.php';

$admin       = $admin ?? require_admin();
$adminTitle  = $adminTitle ?? 'Dashboard';
$adminActive = $adminActive ?? 'dashboard';

$adminNav = [
    'dashboard' => ['label' => 'Dashboard', 'href' => 'admin/index.php'],
    'orders'    => ['label' => 'Orders', 'href' => 'admin/orders.php'],
    'custom'    => ['label' => 'Custom requests', 'href' => 'admin/custom-requests.php'],
    'payments'  => ['label' => 'Payments', 'href' => 'admin/payments.php'],
    'products'  => ['label' => 'Products', 'href' => 'admin/products.php'],
    'categories' => ['label' => 'Categories', 'href' => 'admin/categories.php'],
    'gallery'   => ['label' => 'Gallery', 'href' => 'admin/gallery.php'],
    'reviews'   => ['label' => 'Reviews', 'href' => 'admin/reviews.php'],
    'customers' => ['label' => 'Customers', 'href' => 'admin/customers.php'],
    'messages'  => ['label' => 'Messages', 'href' => 'admin/messages.php'],
    'coupons'   => ['label' => 'Coupons', 'href' => 'admin/coupons.php'],
    'zones'     => ['label' => 'Delivery zones', 'href' => 'admin/delivery-zones.php'],
    'reports'   => ['label' => 'Reports', 'href' => 'admin/reports.php'],
    'content'   => ['label' => 'Website content', 'href' => 'admin/content.php'],
    'settings'  => ['label' => 'Settings', 'href' => 'admin/settings.php'],
];

$adminUnread = (int) Database::value(
    "SELECT COUNT(*) FROM notifications WHERE audience = 'admin' AND is_read = 0",
    [],
    0
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($adminTitle) ?> · <?= e(APP_NAME) ?> Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/theme.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-sidebar" id="adminSidebar">
    <a class="admin-brand" href="<?= e(url('admin/index.php')) ?>">
      <span class="admin-brand-mark" aria-hidden="true">MC</span>
      <span>
        <strong><?= e(setting('business_name', APP_NAME)) ?></strong>
        <small>Studio admin</small>
      </span>
    </a>

    <nav class="admin-nav" aria-label="Administration">
      <ul>
        <?php foreach ($adminNav as $key => $item): ?>
          <li>
            <a href="<?= e(url($item['href'])) ?>"<?= $adminActive === $key ? ' class="is-active" aria-current="page"' : '' ?>>
              <span><?= e($item['label']) ?></span>
              <?php if ($key === 'orders'): ?>
                <?php $newOrders = (int) Database::value("SELECT COUNT(*) FROM orders WHERE status = 'pending'", [], 0); ?>
                <?php if ($newOrders > 0): ?><span class="admin-count"><?= $newOrders ?></span><?php endif; ?>
              <?php elseif ($key === 'custom'): ?>
                <?php $newCustom = (int) Database::value("SELECT COUNT(*) FROM custom_orders WHERE status = 'pending'", [], 0); ?>
                <?php if ($newCustom > 0): ?><span class="admin-count"><?= $newCustom ?></span><?php endif; ?>
              <?php elseif ($key === 'messages'): ?>
                <?php $newMessages = (int) Database::value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0', [], 0); ?>
                <?php if ($newMessages > 0): ?><span class="admin-count"><?= $newMessages ?></span><?php endif; ?>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="admin-sidebar-foot">
      <a href="<?= e(url('')) ?>" target="_blank" rel="noopener">View website ↗</a>
    </div>
  </aside>

  <div class="admin-main">
    <header class="admin-topbar">
      <div>
        <h1 class="admin-title"><?= e($adminTitle) ?></h1>
        <p class="admin-subtitle">Signed in as <?= e($admin['full_name']) ?></p>
      </div>
      <div class="admin-topbar-actions">
        <?php if ($adminUnread > 0): ?>
          <span class="admin-bell" title="<?= $adminUnread ?> unread notifications"><?= $adminUnread ?> alerts</span>
        <?php endif; ?>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline btn-small">Sign out</button>
        </form>
      </div>
    </header>

    <main class="admin-content">
      <?php foreach (flashes() as $message): ?>
        <?php $tone = in_array($message['type'], ['error', 'success', 'warning'], true) ? ' note-' . $message['type'] : ''; ?>
        <p class="note<?= $tone ?>"><?= e($message['message']) ?></p>
      <?php endforeach; ?>
