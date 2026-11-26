<?php
/**
 * MADAM CAKES — admin custom request: quotation, decision, conversion.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();
$requestId = (int) get('id');
$request = $requestId > 0 ? Database::fetch('SELECT * FROM custom_orders WHERE id = ?', [$requestId]) : null;

if (!$request) {
    http_response_code(404);
    $adminTitle = 'Request not found';
    $adminActive = 'custom';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h2>Custom request not found</h2><a class="btn btn-primary" href="' . e(url('admin/custom-requests.php')) . '">Back to requests</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if (is_post()) {
    require_csrf();
    $action = (string) post('action');

    if ($action === 'quote') {
        $price = (float) post('quoted_price');
        $note  = trim((string) post('quotation_note'));
        if ($price <= 0) {
            flash('error', 'Enter the quotation price.');
        } else {
            Database::execute(
                "UPDATE custom_orders SET quoted_price = ?, quotation_note = ?, quoted_at = NOW(), status = 'quoted' WHERE id = ?",
                [$price, $note ?: null, (int) $request['id']]
            );
            if ($request['user_id'] !== null) {
                notify_customer(
                    (int) $request['user_id'],
                    'Your quotation is ready — ' . $request['order_reference'],
                    'The studio quoted ' . money($price) . '. Review and accept it from your account.',
                    'custom',
                    'account/custom-requests.php'
                );
            }
            log_admin_action('custom.quote', 'custom_order', (int) $request['id'], 'Quoted ' . money($price));
            flash('success', 'Quotation saved and the customer notified.');
        }
    } elseif ($action === 'status') {
        $status = (string) post('status');
        if (in_array($status, ['pending', 'awaiting_quotation', 'quoted', 'accepted', 'rejected', 'cancelled'], true)) {
            Database::execute('UPDATE custom_orders SET status = ? WHERE id = ?', [$status, (int) $request['id']]);
            if ($request['user_id'] !== null) {
                notify_customer((int) $request['user_id'], 'Custom request ' . $request['order_reference'] . ' updated', 'The studio marked your request as ' . strtolower(str_replace('_', ' ', $status)) . '.', 'custom', 'account/custom-requests.php');
            }
            log_admin_action('custom.status', 'custom_order', (int) $request['id'], 'Status → ' . $status);
            flash('success', 'Request status updated.');
        }
    } elseif ($action === 'convert') {
        $result = convert_custom_request($request, trim((string) post('conversion_note')) ?: null);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        if ($result['ok']) {
            log_admin_action('custom.convert', 'custom_order', (int) $request['id'], 'Created order ' . $result['order_id']);
            redirect('admin/order.php?id=' . (int) $result['order_id']);
        }
    } elseif ($action === 'notes') {
        Database::execute('UPDATE custom_orders SET admin_notes = ? WHERE id = ?', [trim((string) post('admin_notes')) ?: null, (int) $request['id']]);
        flash('success', 'Internal notes saved.');
    }

    redirect('admin/custom-request.php?id=' . (int) $request['id']);
}

$optionLabels = [
    'occasion_option_id'   => 'Occasion',
    'shape_option_id'      => 'Shape',
    'size_option_id'       => 'Size',
    'flavour_option_id'    => 'Flavour',
    'decoration_option_id' => 'Decoration',
];
$breakdown = json_decode((string) ($request['estimate_breakdown'] ?? ''), true);

$adminTitle  = 'Request ' . $request['order_reference'];
$adminActive = 'custom';
require __DIR__ . '/includes/header.php';
?>

<p class="admin-back"><a href="<?= e(url('admin/custom-requests.php')) ?>">&larr; All custom requests</a></p>

<div class="admin-columns admin-columns-wide">
  <section class="admin-panel">
    <div class="panel-head">
      <h2>Design brief</h2>
      <span class="pill"><?= e(ucfirst(str_replace('_', ' ', (string) $request['status']))) ?></span>
    </div>

    <dl class="detail-list">
      <div><dt>Reference</dt><dd><?= e($request['order_reference']) ?></dd></div>
      <div><dt>Received</dt><dd><?= e(format_datetime($request['created_at'])) ?></dd></div>
      <div><dt>Customer</dt><dd><?= e($request['customer_name']) ?><br><?= e($request['customer_phone']) ?><?= $request['customer_email'] ? '<br>' . e($request['customer_email']) : '' ?></dd></div>
      <?php if ($request['user_id']): ?><div><dt>Account</dt><dd><a href="<?= e(url('admin/customer.php?id=' . (int) $request['user_id'])) ?>">View customer</a></dd></div><?php endif; ?>
      <div><dt>Needed for</dt><dd><?= e(format_date($request['required_date'])) ?></dd></div>
      <div><dt>Layers</dt><dd><?= (int) $request['layers'] ?></dd></div>
      <?php foreach ($optionLabels as $field => $label): ?>
        <?php $name = $request[$field] ? Database::value('SELECT name FROM cake_options WHERE id = ?', [(int) $request[$field]], null) : null; ?>
        <?php if ($name): ?><div><dt><?= e($label) ?></dt><dd><?= e($name) ?></dd></div><?php endif; ?>
      <?php endforeach; ?>
      <?php if ($request['cream_colour']): ?><div><dt>Cream colour</dt><dd><?= e($request['cream_colour']) ?></dd></div><?php endif; ?>
      <?php if ($request['cake_message']): ?><div><dt>Message on cake</dt><dd><?= e($request['cake_message']) ?></dd></div><?php endif; ?>
    </dl>

    <?php if ($request['additional_instructions']): ?>
      <div class="admin-notes"><h3>Customer notes</h3><p><?= nl2br(e($request['additional_instructions'])) ?></p></div>
    <?php endif; ?>

    <?php if ($request['reference_image']): ?>
      <a class="request-image" href="<?= e(upload_url($request['reference_image'])) ?>" target="_blank" rel="noopener">
        <img src="<?= e(upload_url($request['reference_image'])) ?>" alt="Customer reference photograph" width="180" height="180">
      </a>
    <?php endif; ?>

    <?php if ($breakdown): ?>
      <h3 class="panel-subhead">Customer-facing estimate</h3>
      <ul class="plain-list">
        <?php foreach ($breakdown as $label => $value): ?>
          <li><?= e((string) $label) ?> — <?= e(money($value)) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="admin-muted">Estimate from option prices: <?= e(money((float) ($request['estimated_price'] ?? 0))) ?>. The quotation you set is the price that applies.</p>
    <?php endif; ?>
  </section>

  <div class="admin-stack">
    <section class="admin-panel">
      <div class="panel-head"><h2>Quotation</h2></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="quote">
        <label class="field">
          <span>Quoted price (TZS)</span>
          <input type="number" name="quoted_price" step="0.01" min="0" value="<?= e((string) ($request['quoted_price'] ?? '')) ?>" required>
        </label>
        <label class="field">
          <span>Note to the customer</span>
          <textarea name="quotation_note" maxlength="2000"><?= e((string) ($request['quotation_note'] ?? '')) ?></textarea>
        </label>
        <button type="submit" class="btn btn-primary btn-block">Send quotation</button>
        <?php if ($request['quoted_at']): ?><p class="admin-muted">Last quoted <?= e(format_datetime($request['quoted_at'])) ?>.</p><?php endif; ?>
      </form>
    </section>

    <section class="admin-panel">
      <div class="panel-head"><h2>Decision</h2></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <label class="field">
          <span>Status</span>
          <select name="status">
            <?php foreach (['pending', 'awaiting_quotation', 'quoted', 'accepted', 'rejected', 'cancelled'] as $key): ?>
              <option value="<?= e($key) ?>"<?= $request['status'] === $key ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $key))) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="btn btn-outline btn-block btn-small">Update status</button>
      </form>

      <?php if ($request['converted_order_id']): ?>
        <p class="note note-success">Converted to <a href="<?= e(url('admin/order.php?id=' . (int) $request['converted_order_id'])) ?>">order #<?= (int) $request['converted_order_id'] ?></a>.</p>
      <?php elseif ($request['quoted_price'] !== null && in_array($request['status'], ['accepted', 'quoted'], true)): ?>
        <h3 class="panel-subhead">Convert to an order</h3>
        <p class="admin-muted">Creates a confirmed pickup order for the quoted amount.</p>
        <form method="post" onsubmit="return confirm('Create an order from this quotation?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="convert">
          <label class="field"><span>Note (optional)</span><input type="text" name="conversion_note" maxlength="200"></label>
          <button type="submit" class="btn btn-primary btn-block btn-small">Create order</button>
        </form>
      <?php else: ?>
        <p class="note">Send a quotation before converting this request into an order.</p>
      <?php endif; ?>
    </section>

    <section class="admin-panel">
      <div class="panel-head"><h2>Internal notes</h2></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="notes">
        <label class="field">
          <span>Administrators only</span>
          <textarea name="admin_notes" maxlength="2000"><?= e((string) $request['admin_notes']) ?></textarea>
        </label>
        <button type="submit" class="btn btn-ghost btn-small">Save notes</button>
      </form>
    </section>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
