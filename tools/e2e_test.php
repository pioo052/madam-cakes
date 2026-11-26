<?php
/**
 * MADAM CAKES — end-to-end HTTP test (temporary verification script).
 *
 * Drives the real application over HTTP against Apache + MySQL: administrator
 * setup and login, product creation, customer registration, cart, checkout,
 * payment verification, custom quotation, review and account features.
 * All test records are removed at the end.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$base = APP_URL;
$jarAdmin = sys_get_temp_dir() . '/mc_admin_' . getmypid() . '.txt';
$jarCust  = sys_get_temp_dir() . '/mc_cust_' . getmypid() . '.txt';
$pass = 0;
$fail = 0;
$failures = [];

function http(string $method, string $url, array $data = [], ?string $jar = null, array $files = []): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
    ];
    if ($jar !== null) {
        $opts[CURLOPT_COOKIEJAR] = $jar;
        $opts[CURLOPT_COOKIEFILE] = $jar;
    }
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $files === [] ? http_build_query($data) : array_merge($data, $files);
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
        $location = trim($m[1]);
    }
    return ['status' => $code, 'body' => $body, 'location' => $location];
}

function token(string $jar, string $base, string $path): string
{
    $r = http('GET', $base . '/' . ltrim($path, '/'), [], $jar);
    if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $r['body'], $m)) {
        return $m[1];
    }
    return '';
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "PASS  $label\n";
    } else {
        $fail++;
        $failures[] = $label . ($detail !== '' ? " ($detail)" : '');
        echo "FAIL  $label" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

$adminEmail = 'e2e.admin@example.test';
$custEmail  = 'e2e.customer@example.test';
$custPass   = 'TestCustomer123!';
$newPass    = 'TestCustomer456!';
$slug       = 'e2e-celebration-cake';
$phone      = '0712345678';
$origSettings = Database::fetchAll('SELECT setting_key, setting_value, group_name FROM business_settings');

try {
    // ---------------------------------------------------------------- admin
    // A live site already has its owner administrator, so first-time setup is
    // correctly closed. Provision the test administrator directly in that case.
    $adminCount = (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin'", [], 0);
    if ($adminCount === 0) {
        $t = token($jarAdmin, $base, 'admin/setup.php');
        check('admin setup form offers a CSRF token', $t !== '');
        $r = http('POST', $base . '/admin/setup.php', [
            'csrf_token' => $t,
            'full_name'  => 'E2E Administrator',
            'email'      => $adminEmail,
            'phone'      => '0700000000',
            'password'   => 'TestAdmin123!',
            'password_confirm' => 'TestAdmin123!',
        ], $jarAdmin);
        check('administrator created', $r['status'] === 200 && str_contains($r['body'], 'Administrator account created'), 'status ' . $r['status']);
    } else {
        Database::execute(
            "INSERT INTO users (role, full_name, email, phone, password_hash, status)
             VALUES ('admin', ?, ?, ?, ?, 'active')
             ON DUPLICATE KEY UPDATE role = 'admin', status = 'active', password_hash = VALUES(password_hash)",
            ['E2E Administrator', $adminEmail, '0700000000', password_hash('TestAdmin123!', PASSWORD_DEFAULT)]
        );
        check('test administrator provisioned (first-time setup is closed on a live site)', (int) Database::value("SELECT COUNT(*) FROM users WHERE email = ? AND role = 'admin'", [$adminEmail], 0) === 1);
        check('first-time setup is refused while an administrator exists', http('GET', $base . '/admin/setup.php')['status'] === 403);
    }

    $t = token($jarAdmin, $base, 'admin/login.php');
    $r = http('POST', $base . '/admin/login.php', ['csrf_token' => $t, 'email' => $adminEmail, 'password' => 'TestAdmin123!'], $jarAdmin);
    check('administrator can sign in', $r['status'] === 302 && str_contains($r['location'], 'admin/index.php'), 'status ' . $r['status']);

    $r = http('GET', $base . '/admin/index.php', [], $jarAdmin);
    check('admin dashboard loads', $r['status'] === 200 && str_contains($r['body'], 'Dashboard'));

    // -------------------------------------------------------------- product
    $t = token($jarAdmin, $base, 'admin/product-edit.php');
    $r = http('POST', $base . '/admin/product-edit.php', [
        'csrf_token'        => $t,
        'action'            => 'save',
        'name'              => 'E2E Celebration Cake',
        'category_id'       => '1',
        'short_description' => 'A test cake created by the automated verification run.',
        'description'       => 'This cake exists only for the end-to-end test and is removed afterwards.',
        'base_price'        => '45000',
        'preparation_days'  => '2',
        'is_active'         => '1',
        'is_featured'       => '1',
    ], $jarAdmin);
    check('product created', $r['status'] === 302 && preg_match('/id=(\d+)/', $r['location'], $m) === 1, 'status ' . $r['status']);
    $productId = isset($m[1]) ? (int) $m[1] : 0;

    $t = token($jarAdmin, $base, 'admin/product-edit.php?id=' . $productId);
    $r = http('POST', $base . '/admin/product-edit.php?id=' . $productId, [
        'csrf_token' => $t,
        'action'     => 'add_size',
        'size_name'  => '8 inch (serves 12)',
        'size_price' => '55000',
        'size_sort'  => '1',
    ], $jarAdmin);
    check('product size added', $r['status'] === 302, 'status ' . $r['status']);
    $sizeId = (int) Database::value('SELECT id FROM product_sizes WHERE product_id = ? ORDER BY id DESC LIMIT 1', [$productId], 0);
    check('size stored with the owner price', (float) Database::value('SELECT price FROM product_sizes WHERE id = ?', [$sizeId], 0) === 55000.0);

    // ------------------------------------------------------------- upload
    $png = sys_get_temp_dir() . '/mc_test_image.png';
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    $t = token($jarAdmin, $base, 'admin/product-edit.php?id=' . $productId);
    $r = http('POST', $base . '/admin/product-edit.php?id=' . $productId, [
        'csrf_token' => $t, 'action' => 'upload_image', 'alt_text' => 'Uploaded test image', 'sort_order' => '0',
    ], $jarAdmin, ['image' => new CURLFile($png, 'image/png', 'test.png')]);
    $imageRow = Database::fetch('SELECT * FROM product_images WHERE product_id = ? ORDER BY id DESC LIMIT 1', [$productId]);
    check('admin can upload a product photograph', $r['status'] === 302 && $imageRow !== null);
    check('uploaded photograph exists on disk and is the primary image', $imageRow && is_file(ROOT_PATH . '/' . $imageRow['image_path']) && (int) $imageRow['is_primary'] === 1);

    $bad = sys_get_temp_dir() . '/mc_bad.php';
    file_put_contents($bad, '<?php echo "pwned";');
    $t = token($jarAdmin, $base, 'admin/product-edit.php?id=' . $productId);
    http('POST', $base . '/admin/product-edit.php?id=' . $productId, [
        'csrf_token' => $t, 'action' => 'upload_image', 'alt_text' => 'bad', 'sort_order' => '0',
    ], $jarAdmin, ['image' => new CURLFile($bad, 'image/png', 'bad.png')]);
    check('a PHP file disguised as an image is rejected', (int) Database::value('SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$productId], 0) === 1);
    @unlink($bad);

    if ($imageRow) {
        $t = token($jarAdmin, $base, 'admin/product-edit.php?id=' . $productId);
        $r = http('POST', $base . '/admin/product-edit.php?id=' . $productId, [
            'csrf_token' => $t, 'action' => 'delete_image', 'image_id' => (string) $imageRow['id'],
        ], $jarAdmin);
        check('admin can delete a product photograph and its file', $r['status'] === 302 && !is_file(ROOT_PATH . '/' . $imageRow['image_path']) && (int) Database::value('SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$productId], 0) === 0);
    }
    @unlink($png);

    // ------------------------------------------------------------ catalogue
    $r = http('GET', $base . '/cakes.php');
    check('catalogue lists the new cake', $r['status'] === 200 && str_contains($r['body'], 'E2E Celebration Cake'));
    check('catalogue shows the owner price', str_contains($r['body'], '55,000'));

    $r = http('GET', $base . '/cake.php?slug=' . $slug);
    check('cake detail page loads', $r['status'] === 200 && str_contains($r['body'], 'E2E Celebration Cake'));
    check('cake detail shows the size price', str_contains($r['body'], '55,000'));

    // ----------------------------------------------------------- customer
    $t = token($jarCust, $base, 'auth/register.php');
    $r = http('POST', $base . '/auth/register.php', [
        'csrf_token' => $t,
        'full_name'  => 'E2E Customer',
        'email'      => $custEmail,
        'phone'      => $phone,
        'password'   => $custPass,
        'password_confirm' => $custPass,
    ], $jarCust);
    check('customer registration succeeds', $r['status'] === 302 && str_contains($r['location'], 'account/index.php'), 'status ' . $r['status']);
    $custId = (int) Database::value('SELECT id FROM users WHERE email = ?', [$custEmail], 0);
    $storedRole = (string) Database::value('SELECT role FROM users WHERE id = ?', [$custId], '');
    $storedHash = (string) Database::value('SELECT password_hash FROM users WHERE id = ?', [$custId], '');
    check('registration stores the account', $custId > 0);
    check('public registration always creates a customer', $storedRole === 'customer');
    check('password is stored hashed, not plaintext', $storedHash !== '' && $storedHash !== $custPass && password_verify($custPass, $storedHash));

    $r = http('GET', $base . '/account/index.php', [], $jarCust);
    check('customer dashboard loads', $r['status'] === 200 && str_contains($r['body'], 'E2E Customer'));

    // ---------------------------------------------------------------- cart
    $t = token($jarCust, $base, 'cake.php?slug=' . $slug);
    $r = http('POST', $base . '/cake.php?slug=' . $slug, [
        'csrf_token' => $t,
        'action'     => 'add',
        'size_id'    => (string) $sizeId,
        'quantity'   => '2',
    ], $jarCust);
    check('add to cart redirects to the cart', $r['status'] === 302 && str_contains($r['location'], 'cart.php'), 'status ' . $r['status']);

    $r = http('GET', $base . '/cart.php', [], $jarCust);
    check('cart shows the line', $r['status'] === 200 && str_contains($r['body'], 'E2E Celebration Cake'));
    check('cart totals are calculated server-side', str_contains($r['body'], '110,000'), 'expected 110,000');

    // ------------------------------------------------------------ checkout
    $t = token($jarCust, $base, 'checkout.php');
    $date = date('Y-m-d', strtotime('+5 days'));
    $r = http('POST', $base . '/checkout.php', [
        'csrf_token'      => $t,
        'customer_name'   => 'E2E Customer',
        'customer_phone'  => $phone,
        'customer_email'  => $custEmail,
        'fulfilment'      => 'pickup',
        'delivery_date'   => $date,
        'delivery_time'   => '3:00 PM',
        'payment_method'  => 'mpesa',
        'customer_notes'  => 'Created by the automated verification run.',
        'confirm_terms'   => '1',
    ], $jarCust);
    check('order is placed', $r['status'] === 302 && preg_match('/account\/order\.php\?id=(\d+)/', $r['location'], $m) === 1, 'status ' . $r['status'] . ' ' . $r['location']);
    $orderId = isset($m[1]) ? (int) $m[1] : 0;

    $order = $orderId ? Database::fetch('SELECT * FROM orders WHERE id = ?', [$orderId]) : null;
    check('order stored in MySQL', $order !== null);
    check('order total recalculated on the server', $order && (float) $order['total'] === 110000.0, $order ? (string) $order['total'] : 'missing');
    check('order belongs to the customer', $order && (int) $order['user_id'] === $custId);
    check('order starts as pending and unpaid', $order && $order['status'] === 'pending' && $order['payment_status'] === 'unpaid');
    check('order items stored', (int) Database::value('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$orderId], 0) === 1);
    check('initial payment row created', (int) Database::value("SELECT COUNT(*) FROM payments WHERE order_id = ? AND status = 'pending'", [$orderId], 0) === 1);
    check('cart emptied after checkout', (int) Database::value('SELECT COUNT(*) FROM cart_items WHERE cart_id = (SELECT id FROM carts WHERE user_id = ?)', [$custId], 0) === 0);
    check('customer notification created', (int) Database::value('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$custId], 0) >= 2);

    $r = http('GET', $base . '/account/order.php?id=' . $orderId, [], $jarCust);
    check('customer sees the order and reference', $r['status'] === 200 && $order && str_contains($r['body'], $order['order_reference']));

    // --------------------------------------------------------- order track
    $t = token($jarCust, $base, 'track.php');
    $r = http('POST', $base . '/track.php', ['csrf_token' => $t, 'reference' => $order['order_reference'], 'contact' => $phone], $jarCust);
    check('public tracking finds the order with matching contact', $r['status'] === 200 && str_contains($r['body'], $order['order_reference']));

    $r = http('POST', $base . '/track.php', ['csrf_token' => $t, 'reference' => $order['order_reference'], 'contact' => '0799999999'], null);
    check('public tracking rejects a wrong contact', !str_contains($r['body'], 'Payment') || str_contains($r['body'], 'does not match'));

    // -------------------------------------------------------- admin update
    $t = token($jarAdmin, $base, 'admin/order.php?id=' . $orderId);
    $r = http('POST', $base . '/admin/order.php?id=' . $orderId, [
        'csrf_token' => $t, 'action' => 'status', 'status' => 'preparing', 'status_note' => 'In the kitchen.',
    ], $jarAdmin);
    check('admin updates the order status', $r['status'] === 302);
    check('status saved to MySQL', (string) Database::value('SELECT status FROM orders WHERE id = ?', [$orderId], '') === 'preparing');
    check('status history appended', (int) Database::value('SELECT COUNT(*) FROM order_status_history WHERE order_id = ?', [$orderId], 0) >= 2);

    $r = http('GET', $base . '/account/order.php?id=' . $orderId, [], $jarCust);
    check('customer sees the updated status', str_contains($r['body'], 'Preparing'));

    $paymentId = (int) Database::value('SELECT id FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$orderId], 0);
    $t = token($jarAdmin, $base, 'admin/order.php?id=' . $orderId);
    $r = http('POST', $base . '/admin/order.php?id=' . $orderId, [
        'csrf_token' => $t, 'action' => 'verify_payment', 'payment_id' => (string) $paymentId,
    ], $jarAdmin);
    check('admin verifies the payment', $r['status'] === 302);
    check('order marked paid in MySQL', (string) Database::value('SELECT payment_status FROM orders WHERE id = ?', [$orderId], '') === 'paid');
    check('payment row verified', (string) Database::value('SELECT status FROM payments WHERE id = ?', [$paymentId], '') === 'verified');

    // ------------------------------------------------------- custom request
    $option = (int) Database::value("SELECT id FROM cake_options WHERE group_key = 'occasion' AND is_active = 1 LIMIT 1", [], 0);
    $layer = (int) Database::value('SELECT layers FROM cake_layer_prices WHERE is_active = 1 ORDER BY layers LIMIT 1', [], 0);
    $t = token($jarCust, $base, 'custom-cake.php');
    $r = http('POST', $base . '/custom-cake.php', [
        'csrf_token' => $t,
        'customer_name' => 'E2E Customer', 'customer_phone' => $phone, 'customer_email' => $custEmail,
        'occasion_option_id' => (string) $option,
        'layers' => (string) $layer,
        'cream_colour' => 'White',
        'cake_message' => 'Happy testing',
        'additional_instructions' => 'Automated verification request.',
        'required_date' => $date,
    ], $jarCust);
    check('custom request submitted', $r['status'] === 200 && str_contains($r['body'], 'Request received'));
    $customId = (int) Database::value('SELECT id FROM custom_orders WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$custId], 0);
    check('custom request stored with a reference', $customId > 0 && Database::value('SELECT order_reference FROM custom_orders WHERE id = ?', [$customId]) !== null);

    $t = token($jarAdmin, $base, 'admin/custom-request.php?id=' . $customId);
    $r = http('POST', $base . '/admin/custom-request.php?id=' . $customId, [
        'csrf_token' => $t, 'action' => 'quote', 'quoted_price' => '90000', 'quotation_note' => 'Quoted by the verification run.',
    ], $jarAdmin);
    check('admin sends a quotation', $r['status'] === 302 && (string) Database::value('SELECT status FROM custom_orders WHERE id = ?', [$customId], '') === 'quoted');

    $t = token($jarCust, $base, 'account/custom-requests.php');
    $r = http('POST', $base . '/account/custom-requests.php', ['csrf_token' => $t, 'custom_id' => (string) $customId, 'action' => 'accept'], $jarCust);
    check('customer accepts the quotation', $r['status'] === 302 && (string) Database::value('SELECT status FROM custom_orders WHERE id = ?', [$customId], '') === 'accepted');

    $t = token($jarAdmin, $base, 'admin/custom-request.php?id=' . $customId);
    $r = http('POST', $base . '/admin/custom-request.php?id=' . $customId, ['csrf_token' => $t, 'action' => 'convert', 'conversion_note' => 'Verified conversion.'], $jarAdmin);
    check('admin converts the quotation to an order', $r['status'] === 302 && str_contains($r['location'], 'admin/order.php'));
    check('custom request linked to a real order', (int) Database::value('SELECT converted_order_id FROM custom_orders WHERE id = ?', [$customId], 0) > 0);

    // ------------------------------------------------------------ account
    $t = token($jarCust, $base, 'account/addresses.php');
    $r = http('POST', $base . '/account/addresses.php', [
        'csrf_token' => $t, 'action' => 'save', 'address_id' => '0', 'label' => 'Home',
        'region' => 'Dar es Salaam', 'city' => 'Kinondoni', 'area' => 'Mikocheni', 'street' => 'Test Street 1', 'is_default' => '1',
    ], $jarCust);
    check('customer can add an address', (int) Database::value('SELECT COUNT(*) FROM user_addresses WHERE user_id = ?', [$custId], 0) === 1);

    $t = token($jarCust, $base, 'account/profile.php');
    $r = http('POST', $base . '/account/profile.php', [
        'csrf_token' => $t, 'full_name' => 'E2E Customer Updated', 'email' => $custEmail, 'phone' => $phone,
    ], $jarCust);
    check('customer can update profile', $r['status'] === 302 && (string) Database::value('SELECT full_name FROM users WHERE id = ?', [$custId], '') === 'E2E Customer Updated');

    $t = token($jarCust, $base, 'account/password.php');
    $r = http('POST', $base . '/account/password.php', [
        'csrf_token' => $t, 'current_password' => $custPass, 'new_password' => $newPass, 'new_password_confirm' => $newPass,
    ], $jarCust);
    check('customer can change password', $r['status'] === 302 && password_verify($newPass, (string) Database::value('SELECT password_hash FROM users WHERE id = ?', [$custId], '')));

    $jarCust2 = sys_get_temp_dir() . '/mc_cust2_' . getmypid() . '.txt';
    $t = token($jarCust2, $base, 'auth/login.php');
    $r = http('POST', $base . '/auth/login.php', ['csrf_token' => $t, 'email' => $custEmail, 'password' => $newPass], $jarCust2);
    check('customer signs in with the new password', $r['status'] === 302 && str_contains($r['location'], 'account/index.php'));

    $t = token($jarCust2, $base, 'cake.php?slug=' . $slug);
    $r = http('POST', $base . '/cake.php?slug=' . $slug, ['csrf_token' => $t, 'action' => 'favourite'], $jarCust2);
    check('customer can favourite a cake', $r['status'] === 302 && (int) Database::value('SELECT COUNT(*) FROM favourites WHERE user_id = ? AND product_id = ?', [$custId, $productId], 0) === 1);

    $r = http('GET', $base . '/account/favourites.php', [], $jarCust2);
    check('favourites page lists the cake', str_contains($r['body'], 'E2E Celebration Cake'));

    // ------------------------------------------------------------- review
    $t = token($jarAdmin, $base, 'admin/order.php?id=' . $orderId);
    http('POST', $base . '/admin/order.php?id=' . $orderId, ['csrf_token' => $t, 'action' => 'status', 'status' => 'delivered', 'status_note' => 'Delivered.'], $jarAdmin);

    $t = token($jarCust2, $base, 'cake.php?slug=' . $slug);
    $r = http('POST', $base . '/cake.php?slug=' . $slug, [
        'csrf_token' => $t, 'action' => 'review', 'rating' => '5', 'review_title' => 'Lovely cake', 'review_body' => 'The automated verification cake tasted wonderful.',
    ], $jarCust2);
    check('delivered customer can submit a review', $r['status'] === 302 && (int) Database::value('SELECT COUNT(*) FROM reviews WHERE user_id = ? AND product_id = ?', [$custId, $productId], 0) === 1);
    $reviewId = (int) Database::value('SELECT id FROM reviews WHERE user_id = ? AND product_id = ?', [$custId, $productId], 0);
    check('review awaits approval', (int) Database::value('SELECT is_approved FROM reviews WHERE id = ?', [$reviewId], 1) === 0);

    $t = token($jarAdmin, $base, 'admin/reviews.php');
    $r = http('POST', $base . '/admin/reviews.php', ['csrf_token' => $t, 'review_id' => (string) $reviewId, 'action' => 'approve'], $jarAdmin);
    check('admin approves the review', $r['status'] === 302 && (int) Database::value('SELECT is_approved FROM reviews WHERE id = ?', [$reviewId], 0) === 1);

    $r = http('GET', $base . '/cake.php?slug=' . $slug);
    check('approved review appears publicly', str_contains($r['body'], 'Lovely cake'));

    // ------------------------------------------------------------ contact
    $t = token($jarCust2, $base, 'contact.php');
    $r = http('POST', $base . '/contact.php', ['csrf_token' => $t, 'name' => 'E2E Customer', 'email' => $custEmail, 'phone' => $phone, 'subject' => 'Test', 'message' => 'Automated verification message.'], $jarCust2);
    check('contact form stores a message', $r['status'] === 200 && (int) Database::value('SELECT COUNT(*) FROM contact_messages WHERE email = ?', [$custEmail], 0) === 1);

    // --------------------------------------- coupon, delivery fee, cancel
    $t = token($jarAdmin, $base, 'admin/delivery-zones.php');
    $r = http('POST', $base . '/admin/delivery-zones.php', [
        'csrf_token' => $t, 'action' => 'save', 'zone_id' => '0', 'name' => 'E2E Zone',
        'region' => 'Dar es Salaam', 'city' => 'Kinondoni', 'fee' => '5000', 'free_over' => '', 'is_active' => '1',
    ], $jarAdmin);
    $zoneId = (int) Database::value("SELECT id FROM delivery_zones WHERE name = 'E2E Zone'", [], 0);
    check('admin can create a delivery zone', $r['status'] === 302 && $zoneId > 0);

    $t = token($jarAdmin, $base, 'admin/coupons.php');
    $r = http('POST', $base . '/admin/coupons.php', [
        'csrf_token' => $t, 'action' => 'save', 'coupon_id' => '0', 'code' => 'E2E10', 'description' => 'Test coupon',
        'discount_type' => 'percent', 'discount_value' => '10', 'min_order_value' => '0',
        'starts_at' => '', 'expires_at' => '', 'usage_limit' => '', 'per_user_limit' => '1', 'is_active' => '1',
    ], $jarAdmin);
    check('admin can create a coupon', $r['status'] === 302 && Database::value("SELECT id FROM coupons WHERE code = 'E2E10'") !== null);

    $t = token($jarCust2, $base, 'cake.php?slug=' . $slug);
    http('POST', $base . '/cake.php?slug=' . $slug, ['csrf_token' => $t, 'action' => 'add', 'size_id' => (string) $sizeId, 'quantity' => '2'], $jarCust2);
    $t = token($jarCust2, $base, 'cart.php');
    $r = http('POST', $base . '/cart.php', ['csrf_token' => $t, 'action' => 'coupon', 'coupon_code' => 'E2E10'], $jarCust2);
    $r = http('GET', $base . '/cart.php', [], $jarCust2);
    check('coupon discount is applied to the cart', str_contains($r['body'], 'E2E10') && str_contains($r['body'], '11,000'), 'expected 11,000 discount');

    $t = token($jarCust2, $base, 'checkout.php');
    $r = http('POST', $base . '/checkout.php', [
        'csrf_token' => $t, 'customer_name' => 'E2E Customer Updated', 'customer_phone' => $phone, 'customer_email' => $custEmail,
        'fulfilment' => 'delivery', 'delivery_zone_id' => (string) $zoneId, 'street_address' => 'Test Street 1',
        'region' => 'Dar es Salaam', 'city' => 'Kinondoni', 'area' => 'Mikocheni',
        'delivery_date' => $date, 'payment_method' => 'cash', 'confirm_terms' => '1',
    ], $jarCust2);
    check('second order placed with delivery', $r['status'] === 302 && preg_match('/account\/order\.php\?id=(\d+)/', $r['location'], $m2) === 1, 'status ' . $r['status'] . ' ' . $r['location']);
    $order2 = isset($m2[1]) ? Database::fetch('SELECT * FROM orders WHERE id = ?', [(int) $m2[1]]) : null;
    check('discount stored on the order', $order2 && (float) $order2['discount_total'] === 11000.0, $order2 ? (string) $order2['discount_total'] : 'missing');
    check('delivery fee taken from the zone', $order2 && (float) $order2['delivery_fee'] === 5000.0, $order2 ? (string) $order2['delivery_fee'] : 'missing');
    check('final total recalculated', $order2 && (float) $order2['total'] === 104000.0, $order2 ? (string) $order2['total'] : 'missing');
    check('coupon usage recorded', $order2 && (int) Database::value('SELECT COUNT(*) FROM coupon_usage WHERE order_id = ?', [(int) $order2['id']], 0) === 1);

    $t = token($jarCust2, $base, 'account/order.php?id=' . ($order2['id'] ?? 0));
    $r = http('POST', $base . '/account/order.php?id=' . ($order2['id'] ?? 0), ['csrf_token' => $t, 'action' => 'cancel'], $jarCust2);
    check('customer can cancel a pending order', $order2 && (string) Database::value('SELECT status FROM orders WHERE id = ?', [(int) $order2['id']], '') === 'cancelled');

    // ---------------------------- free-delivery threshold and surprise
    $t = token($jarAdmin, $base, 'admin/delivery-zones.php');
    $r = http('POST', $base . '/admin/delivery-zones.php', [
        'csrf_token' => $t, 'action' => 'save', 'zone_id' => (string) $zoneId, 'name' => 'E2E Zone',
        'region' => 'Dar es Salaam', 'city' => 'Kinondoni', 'fee' => '5000', 'free_over' => '100000', 'is_active' => '1',
    ], $jarAdmin);
    check('admin can set a free-delivery threshold', $r['status'] === 302 && (float) Database::value('SELECT free_over FROM delivery_zones WHERE id = ?', [$zoneId], 0) === 100000.0);

    $t = token($jarCust2, $base, 'cake.php?slug=' . $slug);
    http('POST', $base . '/cake.php?slug=' . $slug, ['csrf_token' => $t, 'action' => 'add', 'size_id' => (string) $sizeId, 'quantity' => '2'], $jarCust2);
    $t = token($jarCust2, $base, 'checkout.php');
    $r = http('POST', $base . '/checkout.php', [
        'csrf_token' => $t, 'customer_name' => 'E2E Customer Updated', 'customer_phone' => $phone, 'customer_email' => $custEmail,
        'fulfilment' => 'delivery', 'delivery_zone_id' => (string) $zoneId, 'street_address' => 'Test Street 1',
        'region' => 'Dar es Salaam', 'city' => 'Kinondoni', 'area' => 'Mikocheni',
        'delivery_date' => $date, 'payment_method' => 'cash', 'confirm_terms' => '1',
        'is_surprise' => '1', 'recipient_name' => 'E2E Recipient', 'recipient_phone' => '0755555555',
        'recipient_location' => 'Mikocheni, Dar es Salaam', 'gift_message' => 'Surprise gift from the verification run.',
    ], $jarCust2);
    check('surprise delivery order is placed', $r['status'] === 302 && str_contains($r['location'], 'account/order.php?id='), 'status ' . $r['status'] . ' ' . $r['location']);
    $order3 = Database::fetch('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$custId]);
    check('delivery fee waived above the free-delivery threshold', $order3 && (float) $order3['delivery_fee'] === 0.0, $order3 ? (string) $order3['delivery_fee'] : 'missing');
    check('waived order total carries no delivery charge', $order3 && (float) $order3['total'] === 110000.0, $order3 ? (string) $order3['total'] : 'missing');
    check('surprise delivery fields stored on the order', $order3 && (int) $order3['is_surprise'] === 1
        && $order3['recipient_name'] === 'E2E Recipient'
        && $order3['recipient_phone'] === '0755555555'
        && $order3['recipient_location'] === 'Mikocheni, Dar es Salaam'
        && $order3['gift_message'] === 'Surprise gift from the verification run.');

    // ------------------------------------------------------- category CRUD
    $t = token($jarAdmin, $base, 'admin/categories.php');
    $r = http('POST', $base . '/admin/categories.php', [
        'csrf_token' => $t, 'action' => 'save', 'category_id' => '0', 'name' => 'E2E Category',
        'description' => 'Created by the verification run.', 'sort_order' => '9', 'is_active' => '1',
    ], $jarAdmin);
    $catId = (int) Database::value("SELECT id FROM categories WHERE slug = 'e2e-category'", [], 0);
    check('admin can create a category', $r['status'] === 302 && $catId > 0);
    check('new category keeps its visibility and order', (int) Database::value('SELECT is_active FROM categories WHERE id = ?', [$catId], 0) === 1
        && (int) Database::value('SELECT sort_order FROM categories WHERE id = ?', [$catId], 0) === 9);

    $t = token($jarAdmin, $base, 'admin/categories.php?edit=' . $catId);
    $r = http('POST', $base . '/admin/categories.php?edit=' . $catId, [
        'csrf_token' => $t, 'action' => 'save', 'category_id' => (string) $catId, 'name' => 'E2E Category Renamed',
        'description' => 'Edited by the verification run.', 'sort_order' => '8', 'is_active' => '1',
    ], $jarAdmin);
    check('admin can edit a category', $r['status'] === 302
        && (string) Database::value('SELECT name FROM categories WHERE id = ?', [$catId], '') === 'E2E Category Renamed');
    check('category slug follows the new name', (string) Database::value('SELECT slug FROM categories WHERE id = ?', [$catId], '') === 'e2e-category-renamed');

    $t = token($jarAdmin, $base, 'admin/categories.php');
    $r = http('POST', $base . '/admin/categories.php', ['csrf_token' => $t, 'action' => 'delete', 'category_id' => (string) $catId], $jarAdmin);
    check('admin can delete an empty category', $r['status'] === 302
        && (int) Database::value('SELECT COUNT(*) FROM categories WHERE id = ?', [$catId], 0) === 0);

    // -------------------------------------------------------- gallery CRUD
    $galleryPng = sys_get_temp_dir() . '/mc_gallery.png';
    file_put_contents($galleryPng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    $t = token($jarAdmin, $base, 'admin/gallery.php');
    $r = http('POST', $base . '/admin/gallery.php', [
        'csrf_token' => $t, 'action' => 'save', 'gallery_id' => '0', 'title' => 'E2E Gallery Cake',
        'caption' => 'Uploaded by the verification run.', 'category' => 'wedding', 'sort_order' => '5',
        'is_featured' => '1', 'is_active' => '1',
    ], $jarAdmin, ['image' => new CURLFile($galleryPng, 'image/png', 'gallery.png')]);
    $gallery = Database::fetch("SELECT * FROM gallery WHERE title = 'E2E Gallery Cake'");
    check('admin can add a gallery photograph', $r['status'] === 302 && $gallery !== null && is_file(ROOT_PATH . '/' . $gallery['image']));
    check('gallery photograph stores its category and flag', $gallery && $gallery['category'] === 'wedding' && (int) $gallery['is_featured'] === 1);

    if ($gallery) {
        $galleryPath = (string) $gallery['image'];
        $t = token($jarAdmin, $base, 'admin/gallery.php?edit=' . (int) $gallery['id']);
        $r = http('POST', $base . '/admin/gallery.php?edit=' . (int) $gallery['id'], [
            'csrf_token' => $t, 'action' => 'save', 'gallery_id' => (string) $gallery['id'], 'title' => 'E2E Gallery Cake Renamed',
            'caption' => 'Edited by the verification run.', 'category' => 'birthday', 'sort_order' => '4', 'is_active' => '1',
        ], $jarAdmin);
        check('admin can edit a gallery photograph', $r['status'] === 302
            && (string) Database::value('SELECT title FROM gallery WHERE id = ?', [(int) $gallery['id']], '') === 'E2E Gallery Cake Renamed');
        check('editing keeps the existing photograph file', is_file(ROOT_PATH . '/' . $galleryPath));

        $t = token($jarAdmin, $base, 'admin/gallery.php');
        $r = http('POST', $base . '/admin/gallery.php', ['csrf_token' => $t, 'action' => 'delete', 'gallery_id' => (string) $gallery['id']], $jarAdmin);
        check('admin can delete a gallery photograph', $r['status'] === 302
            && (int) Database::value('SELECT COUNT(*) FROM gallery WHERE id = ?', [(int) $gallery['id']], 0) === 0);
        clearstatcache(true);
        check('deleting a photograph removes its file', !is_file(ROOT_PATH . '/' . $galleryPath));
    }
    @unlink($galleryPng);

    // ------------------------------------------------------------- CSRF
    $r = http('POST', $base . '/admin/categories.php', ['action' => 'save', 'name' => 'No Token Category'], $jarAdmin);
    check('an admin form post without a CSRF token is rejected', $r['status'] === 403, 'status ' . $r['status']);
    check('the rejected post changed nothing', (int) Database::value("SELECT COUNT(*) FROM categories WHERE name = 'No Token Category'", [], 0) === 0);
    $r = http('POST', $base . '/cart.php', ['action' => 'remove'], $jarCust2);
    check('a customer form post without a CSRF token is rejected', $r['status'] === 403, 'status ' . $r['status']);

    // ------------------------------------------------------ settings save
    $settingsPost = ['csrf_token' => token($jarAdmin, $base, 'admin/settings.php')];
    foreach ($origSettings as $row) {
        $settingsPost[$row['group_name']][$row['setting_key']] = (string) $row['setting_value'];
    }
    $settingsPost['contact']['whatsapp_number'] = '255 712 345 678';
    $r = http('POST', $base . '/admin/settings.php', $settingsPost, $jarAdmin);
    check('admin can save business settings', $r['status'] === 302
        && (string) Database::value("SELECT setting_value FROM business_settings WHERE setting_key = 'whatsapp_number'", [], '') === '255 712 345 678');

    $r = http('GET', $base . '/index.php');
    check('the saved WhatsApp number renders as a wa.me link', $r['status'] === 200 && str_contains($r['body'], 'https://wa.me/255712345678'));

    // -------------------------------------------------- admin page smoke
    $adminPages = [
        'admin/index.php',
        'admin/orders.php',
        'admin/order.php?id=' . $orderId,
        'admin/custom-requests.php',
        'admin/custom-request.php?id=' . $customId,
        'admin/payments.php',
        'admin/products.php',
        'admin/product-edit.php?id=' . $productId,
        'admin/categories.php',
        'admin/gallery.php',
        'admin/reviews.php',
        'admin/customers.php',
        'admin/customer.php?id=' . $custId,
        'admin/messages.php',
        'admin/coupons.php',
        'admin/delivery-zones.php',
        'admin/reports.php',
        'admin/content.php',
        'admin/settings.php',
    ];
    foreach ($adminPages as $page) {
        $r = http('GET', $base . '/' . $page, [], $jarAdmin);
        check('admin page loads clean: ' . $page, $r['status'] === 200 && !preg_match('/Fatal error|Warning:|Uncaught/i', $r['body']), 'status ' . $r['status']);
    }

    // ------------------------------------------------------- account closure
    $jarClose = sys_get_temp_dir() . '/mc_close_' . getmypid() . '.txt';
    $closedEmail = 'e2e.closed@example.test';
    $t = token($jarClose, $base, 'auth/register.php');
    http('POST', $base . '/auth/register.php', [
        'csrf_token' => $t, 'full_name' => 'E2E Closing Customer', 'email' => $closedEmail,
        'phone' => '0711111111', 'password' => $custPass, 'password_confirm' => $custPass,
    ], $jarClose);
    $closedId = (int) Database::value('SELECT id FROM users WHERE email = ?', [$closedEmail], 0);
    check('second customer can register', $closedId > 0);
    $t = token($jarClose, $base, 'account/delete-account.php');
    $r = http('POST', $base . '/account/delete-account.php', ['csrf_token' => $t, 'password' => $custPass, 'confirm' => 'close'], $jarClose);
    $closedRow = Database::fetch('SELECT status, closed_at FROM users WHERE id = ?', [$closedId]);
    check('customer can close their account', $r['status'] === 302 && $closedRow && $closedRow['status'] === 'blocked' && $closedRow['closed_at'] !== null);
    $t = token($jarClose, $base, 'auth/login.php');
    $r = http('POST', $base . '/auth/login.php', ['csrf_token' => $t, 'email' => $closedEmail, 'password' => $custPass], $jarClose);
    check('closed account cannot sign in', $r['status'] === 200 && str_contains($r['body'], 'did not match an active account'));
    @unlink($jarClose);

    // ---------------------------------------------------------- security
    $r = http('GET', $base . '/account/orders.php', [], null);
    check('signed-out visitor is redirected from the account', $r['status'] === 302 && str_contains($r['location'], 'auth/login.php'));

    $r = http('GET', $base . '/admin/orders.php', [], $jarCust2);
    check('customer is forbidden from admin pages', $r['status'] === 403 || ($r['status'] === 302 && str_contains($r['location'], 'admin/login.php')));

    $other = http('GET', $base . '/account/order.php?id=' . $orderId, [], $jarAdmin);
    check('an administrator session cannot open another user\'s account order', $other['status'] === 404 && !str_contains($other['body'], 'Order progress'));
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . "\n";
    $fail++;
    $failures[] = 'exception: ' . $e->getMessage();
} finally {
    // ------------------------------------------------------------- cleanup
    try {
        $ids = Database::fetchAll('SELECT id FROM users WHERE email IN (?, ?, ?)', [$adminEmail, $custEmail, 'e2e.closed@example.test']);
        $userIds = array_map(static fn (array $r): int => (int) $r['id'], $ids);
        if ($userIds !== []) {
            $in = implode(',', array_fill(0, count($userIds), '?'));
            $orders = Database::fetchAll("SELECT id FROM orders WHERE user_id IN ($in)", $userIds);
            $orderIds = array_map(static fn (array $r): int => (int) $r['id'], $orders);
            if ($orderIds !== []) {
                $oin = implode(',', array_fill(0, count($orderIds), '?'));
                Database::execute("DELETE FROM coupon_usage WHERE order_id IN ($oin)", $orderIds);
                Database::execute("DELETE FROM notifications WHERE link LIKE '%order.php%'");
                Database::execute("DELETE FROM payments WHERE order_id IN ($oin)", $orderIds);
                Database::execute("DELETE FROM order_status_history WHERE order_id IN ($oin)", $orderIds);
                Database::execute("DELETE FROM order_items WHERE order_id IN ($oin)", $orderIds);
                Database::execute("DELETE FROM reviews WHERE order_id IN ($oin)", $orderIds);
                Database::execute("DELETE FROM custom_orders WHERE converted_order_id IN ($oin)", $orderIds);
                Database::execute("DELETE FROM orders WHERE id IN ($oin)", $orderIds);
            }
            Database::execute("DELETE FROM custom_orders WHERE user_id IN ($in)", $userIds);
            Database::execute("DELETE FROM favourites WHERE user_id IN ($in)", $userIds);
            Database::execute("DELETE FROM user_addresses WHERE user_id IN ($in)", $userIds);
            Database::execute("DELETE FROM reviews WHERE user_id IN ($in)", $userIds);
            Database::execute("DELETE FROM notifications WHERE user_id IN ($in)", $userIds);
            Database::execute("DELETE FROM carts WHERE user_id IN ($in)", $userIds);
            Database::execute("DELETE FROM users WHERE id IN ($in)", $userIds);
        }
        Database::execute('DELETE FROM contact_messages WHERE email = ?', [$custEmail]);
        Database::execute('DELETE FROM products WHERE slug = ?', [$slug]);
        Database::execute("DELETE FROM coupons WHERE code = 'E2E10'");
        Database::execute("DELETE FROM delivery_zones WHERE name = 'E2E Zone'");
        Database::execute("DELETE FROM notifications WHERE audience = 'admin' AND title LIKE '%E2E%'");
        Database::execute("DELETE FROM notifications WHERE audience = 'admin' AND message LIKE '%E2E%'");
        Database::execute("DELETE FROM admin_activity_logs WHERE admin_id IS NULL AND details LIKE '%E2E%'");
        Database::execute("DELETE FROM gallery WHERE title LIKE 'E2E %'");
        Database::execute("DELETE FROM categories WHERE slug LIKE 'e2e-%'");
        foreach ($origSettings as $row) {
            Database::execute('UPDATE business_settings SET setting_value = ? WHERE setting_key = ?', [$row['setting_value'], $row['setting_key']]);
        }
        @unlink($jarAdmin);
        @unlink($jarCust);
        @unlink($jarCust2 ?? '');
        echo "\nTest records removed.\n";
    } catch (Throwable $e) {
        echo 'Cleanup warning: ' . $e->getMessage() . "\n";
    }

    echo "\n----------------------------------------\n";
    echo "PASSED: $pass   FAILED: $fail\n";
    if ($failures) {
        echo "Failures:\n - " . implode("\n - ", $failures) . "\n";
    }
    exit($fail === 0 ? 0 : 1);
}
