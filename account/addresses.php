<?php
/**
 * MADAM CAKES — customer delivery addresses.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/store.php';

$accountUser = require_login('auth/login.php');
$userId      = (int) $accountUser['id'];

$editId  = (int) get('edit');
$editing = null;
if ($editId > 0) {
    $editing = Database::fetch('SELECT * FROM user_addresses WHERE id = ? AND user_id = ?', [$editId, $userId]);
}

if (is_post()) {
    require_csrf();
    $action = (string) post('action');

    if ($action === 'save') {
        $addressId = (int) post('address_id');
        $label     = trim((string) post('label')) ?: 'Home';
        $region    = trim((string) post('region'));
        $city      = trim((string) post('city'));
        $area      = trim((string) post('area'));
        $street    = trim((string) post('street'));
        $isDefault = !empty(post('is_default'));

        if (mb_strlen($region) < 2 || mb_strlen($city) < 2) {
            flash('error', 'Enter the region and city for this address.');
            redirect('account/addresses.php' . ($addressId > 0 ? '?edit=' . $addressId : ''));
        }

        try {
            Database::begin();
            if ($addressId > 0) {
                $owned = Database::value('SELECT id FROM user_addresses WHERE id = ? AND user_id = ?', [$addressId, $userId]);
                if (!$owned) {
                    throw new RuntimeException('Address not found.');
                }
                Database::execute(
                    'UPDATE user_addresses SET label = ?, region = ?, city = ?, area = ?, street = ?, is_default = ? WHERE id = ? AND user_id = ?',
                    [$label, $region, $city, $area, $street, $isDefault ? 1 : 0, $addressId, $userId]
                );
            } else {
                $isFirst = !Database::value('SELECT id FROM user_addresses WHERE user_id = ? LIMIT 1', [$userId]);
                $addressId = Database::insert(
                    'INSERT INTO user_addresses (user_id, label, region, city, area, street, is_default) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$userId, $label, $region, $city, $area, $street, ($isDefault || $isFirst) ? 1 : 0]
                );
            }

            if ($isDefault) {
                Database::execute('UPDATE user_addresses SET is_default = 0 WHERE user_id = ? AND id <> ?', [$userId, $addressId]);
            }
            Database::commit();
            flash('success', 'Address saved.');
            redirect('account/addresses.php');
        } catch (Throwable $e) {
            Database::rollback();
            error_log('Address save failed: ' . $e->getMessage());
            flash('error', 'That address could not be saved.');
            redirect('account/addresses.php');
        }
    }

    if ($action === 'delete') {
        Database::execute('DELETE FROM user_addresses WHERE id = ? AND user_id = ?', [(int) post('address_id'), $userId]);
        flash('success', 'Address removed.');
        redirect('account/addresses.php');
    }

    if ($action === 'default') {
        $addressId = (int) post('address_id');
        $owned = Database::value('SELECT id FROM user_addresses WHERE id = ? AND user_id = ?', [$addressId, $userId]);
        if ($owned) {
            Database::begin();
            Database::execute('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?', [$userId]);
            Database::execute('UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?', [$addressId, $userId]);
            Database::commit();
            flash('success', 'Default address updated.');
        }
        redirect('account/addresses.php');
    }
}

$addresses = Database::fetchAll(
    'SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC',
    [$userId]
);

$accountActive   = 'addresses';
$pageTitle       = 'My addresses';
$pageDescription = 'Delivery addresses saved to your MADAM CAKES account.';
require __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="page-band">
    <div class="container">
      <p class="eyebrow">My account</p>
      <h1 class="section-title">My addresses</h1>
      <p class="section-sub">Save the places you send cakes to most often.</p>
    </div>
  </section>

  <section class="section-tight">
    <div class="container account-layout">
      <?php require __DIR__ . '/../includes/account-nav.php'; ?>

      <div class="account-main">
        <div class="account-columns">
          <section class="account-block">
            <h2 class="section-title">Saved addresses</h2>
            <?php if ($addresses): ?>
              <ul class="address-list">
                <?php foreach ($addresses as $address): ?>
                  <li class="address-card<?= (int) $address['is_default'] === 1 ? ' is-default' : '' ?>">
                    <div class="address-head">
                      <strong><?= e($address['label']) ?></strong>
                      <?php if ((int) $address['is_default'] === 1): ?><span class="pill">Default</span><?php endif; ?>
                    </div>
                    <p><?= e(trim(implode(', ', array_filter([$address['street'], $address['area'], $address['city'], $address['region']])))) ?></p>
                    <div class="address-actions">
                      <a class="link-button" href="<?= e(url('account/addresses.php?edit=' . (int) $address['id'])) ?>">Edit</a>
                      <?php if ((int) $address['is_default'] !== 1): ?>
                        <form method="post">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="default">
                          <input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>">
                          <button type="submit" class="link-button">Make default</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" onsubmit="return confirm('Remove this address?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>">
                        <button type="submit" class="link-button link-danger">Delete</button>
                      </form>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="note">No addresses saved yet. Add one below and it will be ready at checkout.</p>
            <?php endif; ?>
          </section>

          <section class="account-block">
            <h2 class="section-title"><?= $editing ? 'Edit address' : 'Add an address' ?></h2>
            <form method="post" class="form-card form-card-flat">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="address_id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">
              <label class="field">
                <span>Label</span>
                <input type="text" name="label" value="<?= e($editing['label'] ?? 'Home') ?>" maxlength="60" placeholder="Home, Office, Mum's house">
              </label>
              <div class="field-row">
                <label class="field">
                  <span>Region</span>
                  <input type="text" name="region" value="<?= e($editing['region'] ?? '') ?>" required maxlength="80">
                </label>
                <label class="field">
                  <span>City / district</span>
                  <input type="text" name="city" value="<?= e($editing['city'] ?? '') ?>" required maxlength="80">
                </label>
              </div>
              <label class="field">
                <span>Area / ward</span>
                <input type="text" name="area" value="<?= e($editing['area'] ?? '') ?>" maxlength="120">
              </label>
              <label class="field">
                <span>Street, house or landmark</span>
                <input type="text" name="street" value="<?= e($editing['street'] ?? '') ?>" maxlength="255">
              </label>
              <label class="checkbox-line">
                <input type="checkbox" name="is_default" value="1"<?= $editing && (int) $editing['is_default'] === 1 ? ' checked' : '' ?>>
                <span>Use as my default delivery address</span>
              </label>
              <button type="submit" class="btn btn-primary"><?= $editing ? 'Save address' : 'Add address' ?></button>
              <?php if ($editing): ?>
                <a class="btn btn-ghost btn-small" href="<?= e(url('account/addresses.php')) ?>">Cancel</a>
              <?php endif; ?>
            </form>
          </section>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
