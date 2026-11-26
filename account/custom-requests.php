<?php
/**
 * MADAM CAKES — customer custom cake requests and quotations.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];

$customStatuses = [
    'pending'            => 'Awaiting review',
    'awaiting_quotation' => 'Awaiting quotation',
    'quoted'             => 'Quotation ready',
    'accepted'           => 'Accepted',
    'rejected'           => 'Declined',
    'converted'         => 'Converted to order',
    'cancelled'         => 'Cancelled',
];

if (is_post()) {
    require_csrf();
    $customId = (int) post('custom_id');
    $action   = (string) post('action');
    $request  = Database::fetch('SELECT * FROM custom_orders WHERE id = ? AND user_id = ?', [$customId, $userId]);

    if (!$request) {
        flash('error', 'That request was not found on your account.');
    } elseif ($request['status'] !== 'quoted') {
        flash('warning', 'This request is not awaiting your decision.');
    } elseif (!in_array($action, ['accept', 'reject'], true)) {
        flash('error', 'Unknown action.');
    } else {
        $status = $action === 'accept' ? 'accepted' : 'rejected';
        Database::execute('UPDATE custom_orders SET status = ? WHERE id = ? AND user_id = ?', [$status, $customId, $userId]);
        notify_admin(
            'Custom quotation ' . ($action === 'accept' ? 'accepted' : 'declined'),
            $request['order_reference'] . ' for ' . $request['customer_name'] . '.',
            'custom',
            'admin/custom-request.php?id=' . $customId
        );
        flash('success', $action === 'accept'
            ? 'Thank you — the studio will prepare your order and confirm payment details.'
            : 'Your request has been declined. You can submit a new design at any time.');
    }
    redirect('account/custom-requests.php');
}

$requests = Database::fetchAll(
    'SELECT * FROM custom_orders WHERE user_id = ? ORDER BY created_at DESC, id DESC',
    [$userId]
);

$accountActive   = 'custom';
$pageTitle       = 'My custom cake requests';
$pageDescription = 'Designs and quotations you have submitted to MADAM CAKES.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">Custom cake requests</h1>
      <p class="section-sub">Designs you have sent to the studio and the quotations we have prepared.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <?php if ($requests): ?>
          <ul class="request-list">
            <?php foreach ($requests as $request): ?>
              <li class="request-card">
                <div class="request-head">
                  <div>
                    <p class="eyebrow"><?= e($request['order_reference']) ?></p>
                    <h2 class="request-title"><?= e($customStatuses[$request['status']] ?? ucfirst((string) $request['status'])) ?></h2>
                    <p class="request-meta">Submitted <?= e(format_date($request['created_at'])) ?><?= $request['required_date'] ? ' · needed for ' . e(format_date($request['required_date'])) : '' ?></p>
                  </div>
                  <?php if ($request['quoted_price'] !== null): ?>
                    <p class="request-price"><?= e(money($request['quoted_price'])) ?></p>
                  <?php elseif ($request['estimated_price'] !== null): ?>
                    <p class="request-price request-price-estimate">Est. <?= e(money($request['estimated_price'])) ?></p>
                  <?php endif; ?>
                </div>

                <dl class="detail-list detail-list-inline">
                  <?php
                  $labels = [
                      'occasion_option_id'   => 'Occasion',
                      'shape_option_id'      => 'Shape',
                      'size_option_id'       => 'Size',
                      'flavour_option_id'    => 'Flavour',
                      'decoration_option_id' => 'Decoration',
                  ];
                  foreach ($labels as $field => $label):
                      $optionName = $request[$field] ? Database::value('SELECT name FROM cake_options WHERE id = ?', [(int) $request[$field]], null) : null;
                      if ($optionName): ?>
                        <div><dt><?= e($label) ?></dt><dd><?= e($optionName) ?></dd></div>
                      <?php endif;
                  endforeach; ?>
                  <?php if ($request['cream_colour']): ?><div><dt>Cream</dt><dd><?= e($request['cream_colour']) ?></dd></div><?php endif; ?>
                  <div><dt>Layers</dt><dd><?= (int) $request['layers'] ?></dd></div>
                  <?php if ($request['cake_message']): ?><div><dt>Message</dt><dd><?= e($request['cake_message']) ?></dd></div><?php endif; ?>
                </dl>

                <?php if ($request['additional_instructions']): ?>
                  <p class="request-notes"><?= nl2br(e($request['additional_instructions'])) ?></p>
                <?php endif; ?>
                <?php if ($request['reference_image']): ?>
                  <a class="request-image" href="<?= e(upload_url($request['reference_image'])) ?>" target="_blank" rel="noopener">
                    <img src="<?= e(upload_url($request['reference_image'])) ?>" alt="Reference photograph for this cake request" width="140" height="140">
                  </a>
                <?php endif; ?>

                <?php if ($request['quotation_note']): ?>
                  <p class="note"><strong>Quotation note:</strong> <?= nl2br(e($request['quotation_note'])) ?></p>
                <?php endif; ?>

                <?php if ($request['status'] === 'quoted'): ?>
                  <div class="request-actions">
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="custom_id" value="<?= (int) $request['id'] ?>">
                      <input type="hidden" name="action" value="accept">
                      <button type="submit" class="btn btn-primary btn-small">Accept quotation</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Decline this quotation?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="custom_id" value="<?= (int) $request['id'] ?>">
                      <input type="hidden" name="action" value="reject">
                      <button type="submit" class="btn btn-outline btn-small">Decline</button>
                    </form>
                  </div>
                <?php elseif ($request['converted_order_id']): ?>
                  <a class="btn btn-outline btn-small" href="<?= e(url('account/order.php?id=' . (int) $request['converted_order_id'])) ?>">View the order</a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="empty-state">
            <h2>No custom requests yet</h2>
            <p>Tell us about your dream cake and we will quote it personally.</p>
            <a class="btn btn-primary" href="<?= e(url('custom-cake.php')) ?>">Design your cake</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
