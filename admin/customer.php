<?php
/**
 * MADAM CAKES — admin customer detail, order history and account status.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin();
$customerId = (int) get('id');
$customer = $customerId > 0
    ? Database::fetch("SELECT * FROM users WHERE id = ? AND role = 'customer'", [$customerId])
    : null;

if (!$customer) {
    http_response_code(404);
    $adminTitle = 'Customer not found';
    $adminActive = 'customers';
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><h2>Customer not found</h2><a class="btn btn-primary" href="' . e(url('admin/customers.php')) . '">Back to customers</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if (is_post()) {
    require_csrf();
    $action = (string) post('action');

    if ($action === 'status') {
        $status = (string) post('status');
        if (in_array($status, ['active', 'blocked'], true)) {
            Database::execute('UPDATE users SET status = ?, closed_at = ' . ($status === 'blocked' ? 'COALESCE(closed_at, NOW())' : 'NULL') . ' WHERE id = ? AND role = ?', [$status, (int) $customer['id'], 'customer']);
            if ($status === 'blocked') {
                notify_customer((int) $customer['id'], 'Your account has been suspended', 'Please contact the studio if you believe this is a mistake.', 'account', '');
            }
            log_admin_action('customer.status', 'user', (int) $customer['id'], 'Status → ' . $status);
            flash('success', 'Account status updated to ' . $status . '.');
        }
    } elseif ($action === 'notes') {
        flash('warning', 'Customer notes are not part of this schema; use order notes for order-specific information.');
    }
    redirect('admin/customer.php?id=' . (int) $customer['id']);
}

$orders = Database::fetchAll('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 50', [(int) $customer['id']]);
$addresses = Database::fetchAll('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC', [(int) $customer['id']]);
$customRequests = Database::fetchAll('SELECT * FROM custom_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 20', [(int) $customer['id']]);
$reviewCount = (int) Database::value('SELECT COUNT(*) FROM reviews WHERE user_id = ?', [(int) $customer['id']], 0);
$paidTotal = (float) Database::value("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id = ? AND payment_status = 'paid'", [(int) $customer['id']], 0);

$adminTitle  = $customer['full_name'];
$adminActive = 'customers';
require __DIR__ . '/includes/header.php';
?>

<p class="admin-back"><a href="<?= e(url('admin/customers.php')) ?>">&larr; All customers</a></p>

<div class="admin-columns admin-columns-wide">
  <div class="admin-stack">
    <section class="admin-panel">
      <div class="panel-head">
        <h2>Customer</h2>
        <span class="pill <?= $customer['status'] === 'active' ? 'pill-success' : 'pill-muted' ?>"><?= e(ucfirst((string) $customer['status'])) ?></span>
      </div>
      <dl class="detail-list">
        <div><dt>Name</dt><dd><?= e($customer['full_name']) ?></dd></div>
        <div><dt>Email</dt><dd><a href="mailto:<?= e($customer['email']) ?>"><?= e($customer['email']) ?></a></dd></div>
        <div><dt>Phone</dt><dd><?= e((string) ($customer['phone'] ?? '—')) ?></dd></div>
        <div><dt>Joined</dt><dd><?= e(format_datetime($customer['created_at'])) ?></dd></div>
        <div><dt>Last sign in</dt><dd><?= e(format_datetime($customer['last_login_at'])) ?></dd></div>
        <div><dt>Orders</dt><dd><?= count($orders) ?></dd></div>
        <div><dt>Verified payments</dt><dd><?= e(money($paidTotal)) ?></dd></div>
        <div><dt>Reviews</dt><dd><?= $reviewCount ?></dd></div>
      </dl>
    </section>

    <section class="admin-panel">
      <div class="panel-head"><h2>Saved addresses</h2></div>
      <?php if ($addresses): ?>
        <ul class="address-list">
          <?php foreach ($addresses as $address): ?>
            <li class="address-card">
              <div class="address-head"><strong><?= e($address['label']) ?></strong><?= (int) $address['is_default'] === 1 ? '<span class="pill">Default</span>' : '' ?></div>
              <p><?= e(trim(implode(', ', array_filter([$address['street'], $address['area'], $address['city'], $address['region']])))) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="admin-empty">No addresses saved.</p>
      <?php endif; ?>
    </section>

    <section class="admin-panel">
      <div class="panel-head"><h2>Order history</h2></div>
      <?php if ($orders): ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Reference</th><th>Date</th><th>Total</th><th>Status</th><th>Payment</th></tr></thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <tr>
                  <td><a href="<?= e(url('admin/order.php?id=' . (int) $order['id'])) ?>"><?= e($order['order_reference']) ?></a></td>
                  <td><?= e(format_date($order['created_at'])) ?></td>
                  <td><?= e(money($order['total'])) ?></td>
                  <td><?= status_badge($order['status']) ?></td>
                  <td><?= payment_badge($order['payment_status']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="admin-empty">This customer has not ordered yet.</p>
      <?php endif; ?>
    </section>

    <?php if ($customRequests): ?>
      <section class="admin-panel">
        <div class="panel-head"><h2>Custom requests</h2></div>
        <ul class="admin-mini-list">
          <?php foreach ($customRequests as $request): ?>
            <li>
              <a href="<?= e(url('admin/custom-request.php?id=' . (int) $request['id'])) ?>"><?= e($request['order_reference']) ?></a>
              <span><?= e(ucfirst(str_replace('_', ' ', (string) $request['status']))) ?><?= $request['quoted_price'] !== null ? ' · ' . e(money($request['quoted_price'])) : '' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>

  <section class="admin-panel">
    <div class="panel-head"><h2>Account status</h2></div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <label class="field">
        <span>Status</span>
        <select name="status">
          <option value="active"<?= $customer['status'] === 'active' ? ' selected' : '' ?>>Active — can sign in and order</option>
          <option value="blocked"<?= $customer['status'] === 'blocked' ? ' selected' : '' ?>>Suspended — cannot sign in</option>
        </select>
      </label>
      <button type="submit" class="btn btn-primary btn-block btn-small">Update status</button>
    </form>
    <p class="admin-muted">Administrators cannot change a customer's role from this screen, and customers can never promote themselves.</p>
    <?php if ($customer['closed_at']): ?>
      <p class="note">This customer closed their account on <?= e(format_date($customer['closed_at'])) ?>. Setting the status to active reopens it.</p>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
