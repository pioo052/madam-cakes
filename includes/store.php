<?php
/**
 * MADAM CAKES — catalogue, cart, checkout, orders, reviews and favourites.
 *
 * Prices and totals are ALWAYS recalculated here from owner-managed database
 * rows. Nothing sent by the browser is trusted as a price.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// ---------------------------------------------------------------------------
// Catalogue
// ---------------------------------------------------------------------------

function categories_active(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = Database::fetchAll(
            'SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, name'
        );
    }
    return $rows;
}

function category_by_slug(string $slug): ?array
{
    return Database::fetch('SELECT * FROM categories WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]);
}

function category_url(string $slug): string
{
    return url('cakes.php?category=' . rawurlencode($slug));
}

function product_url(string $slug): string
{
    return url('cake.php?slug=' . rawurlencode($slug));
}

/** A product card row: primary photo, cheapest size and approved rating. */
function product_card_select(): string
{
    return "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id
              ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS primary_image,
            (SELECT MIN(ps.price) FROM product_sizes ps
              WHERE ps.product_id = p.id AND ps.is_active = 1 AND ps.price > 0) AS min_size_price,
            (SELECT COUNT(*) FROM product_sizes ps
              WHERE ps.product_id = p.id AND ps.is_active = 1 AND ps.price > 0) AS priced_sizes,
            (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1) AS review_count,
            (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1) AS avg_rating
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id";
}

/**
 * Filtered, sorted, paginated products.
 *
 * @param array{category?:string,q?:string,sort?:string,featured?:bool,limit?:int,page?:int} $opts
 */
function products_search(array $opts = []): array
{
    $where = ['p.is_active = 1'];
    $params = [];

    if (!empty($opts['category'])) {
        $where[] = 'c.slug = ?';
        $params[] = $opts['category'];
    }
    if (!empty($opts['q'])) {
        $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)';
        $like = '%' . $opts['q'] . '%';
        array_push($params, $like, $like, $like);
    }
    if (!empty($opts['featured'])) {
        $where[] = 'p.is_featured = 1';
    }

    $sort = $opts['sort'] ?? 'newest';
    $orderBy = match ($sort) {
        'price_asc'  => 'COALESCE(min_size_price, p.base_price) IS NULL, COALESCE(min_size_price, p.base_price) ASC, p.name',
        'price_desc' => 'COALESCE(min_size_price, p.base_price) DESC, p.name',
        'name'       => 'p.name ASC',
        'popular'    => 'p.is_bestseller DESC, p.is_featured DESC, p.created_at DESC',
        default      => 'p.is_featured DESC, p.created_at DESC, p.id DESC',
    };

    $sqlWhere = ' WHERE ' . implode(' AND ', $where);
    $total = (int) Database::value(
        'SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id' . $sqlWhere,
        $params,
        0
    );

    $limit = max(1, (int) ($opts['limit'] ?? 12));
    $pager = paginate($total, $limit);
    if (isset($opts['page'])) {
        $page = max(1, (int) $opts['page']);
        $offset = ($page - 1) * $limit;
        $pager = ['page' => $page, 'per_page' => $limit, 'pages' => max(1, (int) ceil($total / $limit)), 'total' => $total, 'offset' => $offset];
    }

    $rows = Database::fetchAll(
        product_card_select() . $sqlWhere . ' ORDER BY ' . $orderBy . ' LIMIT ' . $limit . ' OFFSET ' . (int) $pager['offset'],
        $params
    );

    return ['rows' => $rows, 'pager' => $pager];
}

function product_by_slug(string $slug): ?array
{
    return Database::fetch(
        product_card_select() . ' WHERE p.slug = ? AND p.is_active = 1 LIMIT 1',
        [$slug]
    );
}

function product_gallery(int $productId): array
{
    return Database::fetchAll(
        'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id',
        [$productId]
    );
}

function product_primary_image(array $product): string
{
    $path = trim((string) ($product['primary_image'] ?? ''));
    if ($path !== '') {
        return upload_url($path);
    }
    $path = trim((string) ($product['image'] ?? ''));
    return $path === '' ? '' : upload_url($path);
}

function product_sizes(int $productId): array
{
    return Database::fetchAll(
        'SELECT * FROM product_sizes WHERE product_id = ? AND is_active = 1 ORDER BY sort_order, id',
        [$productId]
    );
}

function product_flavours(int $productId): array
{
    return Database::fetchAll(
        'SELECT * FROM product_flavours WHERE product_id = ? AND is_active = 1 ORDER BY name',
        [$productId]
    );
}

function product_addons(int $productId): array
{
    return Database::fetchAll(
        'SELECT * FROM product_addons WHERE product_id = ? AND is_active = 1 ORDER BY name',
        [$productId]
    );
}

/**
 * The amount to advertise for a product.
 *
 * @return array{amount:float,from:bool,on_request:bool}
 */
function product_price(array $product): array
{
    $base = (float) ($product['base_price'] ?? 0);
    $min  = isset($product['min_size_price']) && $product['min_size_price'] !== null
        ? (float) $product['min_size_price'] : null;

    if ($min !== null && $min > 0) {
        return ['amount' => $min, 'from' => (int) ($product['priced_sizes'] ?? 1) > 1 || $base > 0, 'on_request' => false];
    }
    if ($base > 0) {
        return ['amount' => $base, 'from' => false, 'on_request' => false];
    }
    return ['amount' => 0.0, 'from' => false, 'on_request' => true];
}

function product_price_label(array $product): string
{
    $price = product_price($product);
    if ($price['on_request']) {
        return 'Price confirmed on enquiry';
    }
    return ($price['from'] ? 'From ' : '') . money($price['amount']);
}

/** Shared storefront cake card. */
function product_card(array $product): string
{
    $image = product_primary_image($product);
    $href  = product_url((string) $product['slug']);
    $price = product_price($product);
    $rating = review_summary((int) $product['id']);

    $html  = '<article class="cake-card reveal">';
    $html .= '<a class="cake-media" href="' . e($href) . '" aria-hidden="true" tabindex="-1">';
    if ($image !== '') {
        $html .= '<img src="' . e($image) . '" alt="" loading="lazy" width="640" height="480">';
    } else {
        $html .= '<span class="cake-media-empty">Photograph coming soon</span>';
    }
    $html .= '</a>';
    $html .= '<div class="cake-body">';
    if (!empty($product['category_name'])) {
        $html .= '<p class="cake-cat">' . e($product['category_name']) . '</p>';
    }
    $html .= '<h3 class="cake-name"><a href="' . e($href) . '">' . e($product['name']) . '</a></h3>';
    if (!empty($product['short_description'])) {
        $html .= '<p class="cake-desc">' . e(excerpt($product['short_description'], 92)) . '</p>';
    }
    $html .= '<div class="cake-foot"><span class="cake-price">' . e($price['on_request'] ? 'Price on enquiry' : ($price['from'] ? 'From ' : '') . money($price['amount'])) . '</span>';
    if ($rating['total'] > 0) {
        $html .= '<span class="cake-rating" title="' . (int) $rating['total'] . ' review(s)">&#9733; ' . e((string) $rating['average']) . ' (' . (int) $rating['total'] . ')</span>';
    }
    $html .= '</div></div></article>';
    return $html;
}

/**
 * Cart subtotal, coupon discount and derived totals.
 *
 * @return array{cart:array,coupon:?array,discount:float,delivery:float,total:float,message:string}
 */
function cart_totals(?int $userId = null): array
{
    $cart = cart_lines();
    $coupon = null;
    $discount = 0.0;
    $message = '';

    $code = (string) ($_SESSION['coupon'] ?? '');
    if ($code !== '' && $cart['subtotal'] > 0) {
        $check = coupon_validate($code, $cart['subtotal'], $userId);
        if ($check['ok']) {
            $coupon = $check['coupon'];
            $discount = $check['discount'];
        } else {
            unset($_SESSION['coupon']);
            $message = $check['message'];
        }
    }

    return [
        'cart'     => $cart,
        'coupon'   => $coupon,
        'discount' => $discount,
        'delivery' => 0.0,
        'total'    => round(max(0, $cart['subtotal'] - $discount), 2),
        'message'  => $message,
    ];
}

// ---------------------------------------------------------------------------
// Cart
// ---------------------------------------------------------------------------

/** Read the cart from the database and recompute every line price. */
function cart_lines(): array
{
    $cartId = current_cart_id();
    $empty = ['lines' => [], 'subtotal' => 0.0, 'count' => 0, 'unavailable' => false];
    if ($cartId === null) {
        return $empty;
    }

    $rows = Database::fetchAll(
        "SELECT ci.*, p.name, p.slug, p.base_price, p.is_active AS product_active, p.preparation_days,
                ps.name AS size_name, ps.price AS size_price, ps.is_active AS size_active,
                pf.name AS flavour_name, pf.surcharge AS flavour_surcharge, pf.is_active AS flavour_active,
                (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id
                  ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         LEFT JOIN product_sizes ps ON ps.id = ci.size_id
         LEFT JOIN product_flavours pf ON pf.id = ci.flavour_id
         WHERE ci.cart_id = ?
         ORDER BY ci.id",
        [$cartId]
    );

    $lines = [];
    $subtotal = 0.0;
    $count = 0;
    $unavailable = false;

    foreach ($rows as $row) {
        $addonIds = json_decode((string) ($row['addons_json'] ?? '[]'), true);
        $addonIds = is_array($addonIds) ? array_map('intval', $addonIds) : [];

        $addons = [];
        if ($addonIds !== []) {
            $in = implode(',', array_fill(0, count($addonIds), '?'));
            $addons = Database::fetchAll(
                "SELECT * FROM product_addons WHERE product_id = ? AND is_active = 1 AND id IN ($in) ORDER BY name",
                array_merge([(int) $row['product_id']], $addonIds)
            );
        }

        $available = (int) $row['product_active'] === 1;
        if ($row['size_id'] !== null && (int) $row['size_active'] !== 1) {
            $available = false;
        }
        if ($row['flavour_id'] !== null && (int) $row['flavour_active'] !== 1) {
            $available = false;
        }

        $unit = (int) $row['size_id'] !== 0 && $row['size_id'] !== null && $row['size_price'] !== null
            ? (float) $row['size_price']
            : (float) $row['base_price'];
        $unit += (float) ($row['flavour_surcharge'] ?? 0);
        foreach ($addons as $addon) {
            $unit += (float) $addon['price'];
        }

        if ($unit <= 0) {
            $available = false;
        }

        $qty = max(1, (int) $row['quantity']);
        $lineTotal = $unit * $qty;
        if ($available) {
            $subtotal += $lineTotal;
            $count += $qty;
        } else {
            $unavailable = true;
        }

        $lines[] = [
            'id'            => (int) $row['id'],
            'product_id'    => (int) $row['product_id'],
            'name'          => (string) $row['name'],
            'slug'          => (string) $row['slug'],
            'image'         => (string) ($row['image'] ?? ''),
            'size_name'     => $row['size_name'] !== null ? (string) $row['size_name'] : null,
            'flavour_name'  => $row['flavour_name'] !== null ? (string) $row['flavour_name'] : null,
            'addons'        => array_map(static fn (array $a): array => ['name' => $a['name'], 'price' => (float) $a['price']], $addons),
            'quantity'      => $qty,
            'unit_price'    => $unit,
            'line_total'    => $lineTotal,
            'available'     => $available,
            'preparation_days' => (int) $row['preparation_days'],
        ];
    }

    return ['lines' => $lines, 'subtotal' => round($subtotal, 2), 'count' => $count, 'unavailable' => $unavailable];
}

/** Add a line to the cart, merging an identical configuration. */
function cart_add(int $productId, ?int $sizeId, ?int $flavourId, int $quantity, array $addonIds = []): array
{
    $product = Database::fetch('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1', [$productId]);
    if (!$product) {
        return ['ok' => false, 'message' => 'That cake is no longer available.'];
    }

    $quantity = max(1, min(20, $quantity));

    if ($sizeId !== null) {
        $size = Database::fetch(
            'SELECT * FROM product_sizes WHERE id = ? AND product_id = ? AND is_active = 1 LIMIT 1',
            [$sizeId, $productId]
        );
        if (!$size) {
            $sizeId = null;
        }
    }
    if ($flavourId !== null) {
        $flavour = Database::fetch(
            'SELECT * FROM product_flavours WHERE id = ? AND product_id = ? AND is_active = 1 LIMIT 1',
            [$flavourId, $productId]
        );
        if (!$flavour) {
            $flavourId = null;
        }
    }

    $addonIds = array_values(array_unique(array_map('intval', $addonIds)));
    if ($addonIds !== []) {
        $in = implode(',', array_fill(0, count($addonIds), '?'));
        $valid = Database::fetchAll(
            "SELECT id FROM product_addons WHERE product_id = ? AND is_active = 1 AND id IN ($in)",
            array_merge([$productId], $addonIds)
        );
        $addonIds = array_map(static fn (array $r): int => (int) $r['id'], $valid);
        sort($addonIds);
    }

    $cartId = current_cart_id(true);
    if ($cartId === null) {
        return ['ok' => false, 'message' => 'Your cart could not be started. Please try again.'];
    }

    $addonsJson = $addonIds === [] ? null : json_encode($addonIds);

    $existing = Database::fetch(
        'SELECT * FROM cart_items WHERE cart_id = ? AND product_id = ?
           AND (size_id <=> ?) AND (flavour_id <=> ?) AND (addons_json <=> ?) LIMIT 1',
        [$cartId, $productId, $sizeId, $flavourId, $addonsJson]
    );

    if ($existing) {
        Database::execute(
            'UPDATE cart_items SET quantity = LEAST(20, quantity + ?) WHERE id = ?',
            [$quantity, (int) $existing['id']]
        );
    } else {
        Database::insert(
            'INSERT INTO cart_items (cart_id, product_id, size_id, flavour_id, quantity, unit_price, addons_json)
             VALUES (?, ?, ?, ?, ?, 0, ?)',
            [$cartId, $productId, $sizeId, $flavourId, $quantity, $addonsJson]
        );
    }

    return ['ok' => true, 'message' => $product['name'] . ' was added to your cart.'];
}

function cart_remove(int $itemId): void
{
    $cartId = current_cart_id();
    if ($cartId === null) {
        return;
    }
    Database::execute('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cartId]);
}

function cart_set_quantity(int $itemId, int $quantity): void
{
    $cartId = current_cart_id();
    if ($cartId === null) {
        return;
    }
    if ($quantity <= 0) {
        cart_remove($itemId);
        return;
    }
    Database::execute(
        'UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?',
        [max(1, min(20, $quantity)), $itemId, $cartId]
    );
}

function cart_empty(): void
{
    $cartId = current_cart_id();
    if ($cartId !== null) {
        Database::execute('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
    }
    unset($_SESSION['coupon']);
}

// ---------------------------------------------------------------------------
// Delivery and coupons
// ---------------------------------------------------------------------------

function delivery_zones_active(): array
{
    return Database::fetchAll('SELECT * FROM delivery_zones WHERE is_active = 1 ORDER BY region, name');
}

function delivery_zone(int $id): ?array
{
    return Database::fetch('SELECT * FROM delivery_zones WHERE id = ? AND is_active = 1 LIMIT 1', [$id]);
}

function delivery_fee_for(?array $zone, float $subtotal): float
{
    if ($zone === null) {
        return 0.0;
    }
    $fee = (float) $zone['fee'];
    if ($zone['free_over'] !== null && (float) $zone['free_over'] > 0 && $subtotal >= (float) $zone['free_over']) {
        return 0.0;
    }
    return $fee;
}

function coupon_find(string $code): ?array
{
    $code = strtoupper(trim($code));
    return $code === '' ? null : Database::fetch('SELECT * FROM coupons WHERE code = ? LIMIT 1', [$code]);
}

/**
 * Validate a coupon against the current subtotal and customer.
 *
 * @return array{ok:bool,message:string,discount:float,coupon:?array}
 */
function coupon_validate(string $code, float $subtotal, ?int $userId): array
{
    $coupon = coupon_find($code);
    if (!$coupon) {
        return ['ok' => false, 'message' => 'That coupon code was not recognised.', 'discount' => 0.0, 'coupon' => null];
    }
    if ((int) $coupon['is_active'] !== 1) {
        return ['ok' => false, 'message' => 'That coupon is no longer active.', 'discount' => 0.0, 'coupon' => null];
    }
    if ($coupon['starts_at'] !== null && strtotime((string) $coupon['starts_at']) > time()) {
        return ['ok' => false, 'message' => 'That coupon is not active yet.', 'discount' => 0.0, 'coupon' => null];
    }
    if ($coupon['expires_at'] !== null && strtotime((string) $coupon['expires_at']) < time()) {
        return ['ok' => false, 'message' => 'That coupon has expired.', 'discount' => 0.0, 'coupon' => null];
    }
    if ($coupon['usage_limit'] !== null && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) {
        return ['ok' => false, 'message' => 'That coupon has reached its usage limit.', 'discount' => 0.0, 'coupon' => null];
    }
    if ($subtotal < (float) $coupon['min_order_value']) {
        return ['ok' => false, 'message' => 'This coupon needs a minimum order of ' . money($coupon['min_order_value']) . '.', 'discount' => 0.0, 'coupon' => null];
    }
    if ($userId && $coupon['per_user_limit'] !== null) {
        $used = (int) Database::value(
            'SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ? AND user_id = ?',
            [(int) $coupon['id'], $userId],
            0
        );
        if ($used >= (int) $coupon['per_user_limit']) {
            return ['ok' => false, 'message' => 'You have already used this coupon.', 'discount' => 0.0, 'coupon' => null];
        }
    }

    $discount = $coupon['discount_type'] === 'percent'
        ? $subtotal * ((float) $coupon['discount_value'] / 100)
        : (float) $coupon['discount_value'];
    $discount = round(min($discount, $subtotal), 2);

    return ['ok' => true, 'message' => 'Coupon applied.', 'discount' => $discount, 'coupon' => $coupon];
}

// ---------------------------------------------------------------------------
// Notifications, favourites, reviews
// ---------------------------------------------------------------------------

function unread_notifications(int $userId): int
{
    return (int) Database::value(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND audience = ? AND is_read = 0',
        [$userId, 'customer'],
        0
    );
}

function favourites_for(int $userId): array
{
    return Database::fetchAll(
        product_card_select() . ' JOIN favourites f ON f.product_id = p.id
          WHERE f.user_id = ? AND p.is_active = 1 ORDER BY f.created_at DESC',
        [$userId]
    );
}

function favourite_ids(int $userId): array
{
    $rows = Database::fetchAll('SELECT product_id FROM favourites WHERE user_id = ?', [$userId]);
    return array_map(static fn (array $r): int => (int) $r['product_id'], $rows);
}

/** Toggle and return the new state. */
function favourite_toggle(int $userId, int $productId): bool
{
    $exists = Database::value('SELECT id FROM favourites WHERE user_id = ? AND product_id = ?', [$userId, $productId]);
    if ($exists) {
        Database::execute('DELETE FROM favourites WHERE id = ?', [(int) $exists]);
        return false;
    }
    Database::insert('INSERT INTO favourites (user_id, product_id) VALUES (?, ?)', [$userId, $productId]);
    return true;
}

function review_summary(int $productId): array
{
    $row = Database::fetch(
        'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average
         FROM reviews WHERE product_id = ? AND is_approved = 1',
        [$productId]
    );
    return [
        'total'   => (int) ($row['total'] ?? 0),
        'average' => round((float) ($row['average'] ?? 0), 1),
    ];
}

function reviews_for_product(int $productId, int $limit = 20): array
{
    return Database::fetchAll(
        'SELECT r.*, u.full_name AS reviewer
         FROM reviews r LEFT JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ? AND r.is_approved = 1
         ORDER BY r.is_featured DESC, r.created_at DESC LIMIT ' . max(1, $limit),
        [$productId]
    );
}

/** Has this customer received an order containing the product? */
function customer_bought_product(int $userId, int $productId): bool
{
    return (bool) Database::value(
        "SELECT oi.id FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
         LIMIT 1",
        [$userId, $productId]
    );
}

function customer_can_review(int $userId, int $productId): bool
{
    if (!customer_bought_product($userId, $productId)) {
        return false;
    }
    return !(bool) Database::value(
        'SELECT id FROM reviews WHERE user_id = ? AND product_id = ? LIMIT 1',
        [$userId, $productId]
    );
}

// ---------------------------------------------------------------------------
// Orders
// ---------------------------------------------------------------------------

function order_reference(): string
{
    do {
        $reference = 'MC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $exists = Database::value('SELECT id FROM orders WHERE order_reference = ?', [$reference]);
    } while ($exists);
    return $reference;
}

function custom_reference(): string
{
    do {
        $reference = 'CC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $exists = Database::value('SELECT id FROM custom_orders WHERE order_reference = ?', [$reference]);
    } while ($exists);
    return $reference;
}

/** Payment option codes the owner accepts, mapped to the payments enum. */
function payment_options(): array
{
    $labels = [
        'cash'         => ['label' => 'Cash on collection', 'enum' => 'cash'],
        'mpesa'        => ['label' => 'M-Pesa', 'enum' => 'mpesa'],
        'airtel_money' => ['label' => 'Airtel Money', 'enum' => 'airtel_money'],
        'mixx_by_yas'  => ['label' => 'Mixx by Yas', 'enum' => 'mixx_by_yas'],
        'tigo_pesa'    => ['label' => 'Tigo Pesa', 'enum' => 'other'],
        'halopesa'     => ['label' => 'HaloPesa', 'enum' => 'other'],
        'bank'         => ['label' => 'Bank transfer', 'enum' => 'bank_transfer'],
        'card'         => ['label' => 'Card', 'enum' => 'other'],
    ];

    $options = [];
    foreach (array_filter(array_map('trim', explode(',', (string) setting('accepted_payments', '')))) as $code) {
        $options[$code] = $labels[$code] ?? ['label' => ucwords(str_replace('_', ' ', $code)), 'enum' => 'other'];
    }
    return $options;
}

function order_items(int $orderId): array
{
    return Database::fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
}

function order_history(int $orderId): array
{
    return Database::fetchAll('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at, id', [$orderId]);
}

function order_payments(int $orderId): array
{
    return Database::fetchAll('SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC', [$orderId]);
}

function order_by_reference(string $reference): ?array
{
    return Database::fetch('SELECT * FROM orders WHERE order_reference = ? LIMIT 1', [strtoupper(trim($reference))]);
}

function order_for_user(int $orderId, int $userId): ?array
{
    return Database::fetch('SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1', [$orderId, $userId]);
}

function customer_orders(int $userId, int $limit = 50): array
{
    return Database::fetchAll(
        'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit),
        [$userId]
    );
}

/** Record a status change, notify the customer and keep an audit trail. */
function order_change_status(int $orderId, string $status, ?string $note = null, ?int $adminId = null): void
{
    if (!array_key_exists($status, order_statuses())) {
        return;
    }
    Database::execute(
        'UPDATE orders SET status = ? WHERE id = ?',
        [$status, $orderId]
    );
    Database::insert(
        'INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)',
        [$orderId, $status, $note, $adminId]
    );

    $order = Database::fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if ($order) {
        notify_customer(
            $order['user_id'] !== null ? (int) $order['user_id'] : null,
            'Order ' . $order['order_reference'] . ' is now ' . strtolower(order_status_label($status)),
            $note ?: '',
            'order',
            'account/order.php?id=' . $orderId
        );
    }
}

/**
 * Validate and persist an order. All money is recomputed here.
 *
 * @return array{ok:bool,message:string,order_id?:int,reference?:string}
 */
function place_order(array $input, array $user): array
{
    $cart = cart_lines();
    if ($cart['lines'] === []) {
        return ['ok' => false, 'message' => 'Your cart is empty.'];
    }
    if ($cart['unavailable']) {
        return ['ok' => false, 'message' => 'One of the cakes in your cart is no longer available. Please review your cart.'];
    }

    $fulfilment = (string) ($input['fulfilment'] ?? 'delivery');
    if (!in_array($fulfilment, ['delivery', 'pickup'], true)) {
        return ['ok' => false, 'message' => 'Choose delivery or collection.'];
    }
    if ($fulfilment === 'delivery' && setting('delivery_enabled') === '0') {
        return ['ok' => false, 'message' => 'Delivery is currently unavailable.'];
    }
    if ($fulfilment === 'pickup' && setting('pickup_enabled') === '0') {
        return ['ok' => false, 'message' => 'Collection is currently unavailable.'];
    }

    $name  = trim((string) ($input['customer_name'] ?? ''));
    $phone = trim((string) ($input['customer_phone'] ?? ''));
    $email = trim((string) ($input['customer_email'] ?? ''));

    if (mb_strlen($name) < 3) {
        return ['ok' => false, 'message' => 'Enter the name for this order.'];
    }
    if (mb_strlen(preg_replace('/\D+/', '', $phone) ?? '') < 9) {
        return ['ok' => false, 'message' => 'Enter a phone number we can reach you on.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }

    $date = trim((string) ($input['delivery_date'] ?? ''));
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return ['ok' => false, 'message' => 'Enter a valid date.'];
    }
    $minDays = (int) setting('min_advance_days', '0');
    if ($date !== '' && strtotime($date) < strtotime('today +' . $minDays . ' days')) {
        return ['ok' => false, 'message' => 'Cakes need at least ' . $minDays . ' day(s) notice. Please choose a later date.'];
    }

    $zone = null;
    $region = trim((string) ($input['region'] ?? ''));
    $city   = trim((string) ($input['city'] ?? ''));
    $area   = trim((string) ($input['area'] ?? ''));
    $street = trim((string) ($input['street_address'] ?? ''));

    if ($fulfilment === 'delivery') {
        $zoneId = (int) ($input['delivery_zone_id'] ?? 0);
        if ($zoneId > 0) {
            $zone = delivery_zone($zoneId);
            if (!$zone) {
                return ['ok' => false, 'message' => 'That delivery zone is not available.'];
            }
            $region = (string) $zone['region'];
            $city   = (string) $zone['city'];
        }
        if ($street === '' || $region === '') {
            return ['ok' => false, 'message' => 'Enter the delivery address.'];
        }
    }

    $payCode = (string) ($input['payment_method'] ?? '');
    $options = payment_options();
    if (!isset($options[$payCode])) {
        return ['ok' => false, 'message' => 'Choose a payment method.'];
    }

    $coupon = null;
    $discount = 0.0;
    $couponCode = strtoupper(trim((string) ($input['coupon_code'] ?? '')));
    if ($couponCode !== '') {
        $check = coupon_validate($couponCode, $cart['subtotal'], (int) $user['id']);
        if (!$check['ok']) {
            return ['ok' => false, 'message' => $check['message']];
        }
        $coupon = $check['coupon'];
        $discount = $check['discount'];
    }

    $deliveryFee = $fulfilment === 'delivery' ? delivery_fee_for($zone, $cart['subtotal']) : 0.0;
    $total = round(max(0, $cart['subtotal'] - $discount + $deliveryFee), 2);

    $isSurprise = $fulfilment === 'delivery' && !empty($input['is_surprise']);
    if ($isSurprise && trim((string) ($input['recipient_name'] ?? '')) === '') {
        return ['ok' => false, 'message' => 'Enter the name of the person receiving the surprise.'];
    }

    try {
        Database::begin();

        $reference = order_reference();
        $orderId = Database::insert(
            'INSERT INTO orders
              (order_reference, user_id, customer_name, customer_phone, customer_email,
               fulfilment, delivery_zone_id, region, city, area, street_address,
               delivery_date, delivery_time, delivery_instructions,
               is_surprise, recipient_name, recipient_phone, recipient_location, gift_message,
               gift_wrapping, anonymous_sender,
               subtotal, discount_total, delivery_fee, total, coupon_id, coupon_code,
               status, payment_status, payment_method, customer_notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $reference, (int) $user['id'], $name, $phone, $email ?: null,
                $fulfilment, $zone ? (int) $zone['id'] : null, $region ?: null, $city ?: null, $area ?: null, $street ?: null,
                $date ?: null, trim((string) ($input['delivery_time'] ?? '')) ?: null,
                trim((string) ($input['delivery_instructions'] ?? '')) ?: null,
                $isSurprise ? 1 : 0,
                $isSurprise ? trim((string) ($input['recipient_name'] ?? '')) : null,
                $isSurprise ? trim((string) ($input['recipient_phone'] ?? '')) : null,
                $isSurprise ? trim((string) ($input['recipient_location'] ?? '')) : null,
                $isSurprise ? trim((string) ($input['gift_message'] ?? '')) : null,
                !empty($input['gift_wrapping']) ? 1 : 0,
                !empty($input['anonymous_sender']) ? 1 : 0,
                $cart['subtotal'], $discount, $deliveryFee, $total,
                $coupon ? (int) $coupon['id'] : null, $coupon ? (string) $coupon['code'] : null,
                'pending', 'unpaid', $payCode,
                trim((string) ($input['customer_notes'] ?? '')) ?: null,
            ]
        );

        foreach ($cart['lines'] as $line) {
            Database::insert(
                'INSERT INTO order_items
                  (order_id, item_type, product_id, product_name, size_name, flavour_name, addons_json,
                   unit_price, quantity, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orderId, 'product', $line['product_id'], $line['name'],
                    $line['size_name'], $line['flavour_name'],
                    $line['addons'] === [] ? null : json_encode(array_column($line['addons'], 'name')),
                    $line['unit_price'], $line['quantity'], $line['line_total'],
                ]
            );
        }

        Database::insert(
            'INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)',
            [$orderId, 'pending', 'Order placed through the website.']
        );

        Database::insert(
            'INSERT INTO payments (order_id, method, amount, status) VALUES (?, ?, ?, ?)',
            [$orderId, $options[$payCode]['enum'], $total, 'pending']
        );

        if ($coupon) {
            Database::execute('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [(int) $coupon['id']]);
            Database::insert(
                'INSERT INTO coupon_usage (coupon_id, order_id, user_id, discount_amount) VALUES (?, ?, ?, ?)',
                [(int) $coupon['id'], $orderId, (int) $user['id'], $discount]
            );
        }

        Database::commit();
    } catch (Throwable $e) {
        Database::rollback();
        error_log('Order placement failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The order could not be saved. Please try again.'];
    }

    cart_empty();
    unset($_SESSION['coupon']);

    notify_customer(
        (int) $user['id'],
        'Order received — ' . $reference,
        'We have received your order and will confirm it during opening hours.',
        'order',
        'account/order.php?id=' . $orderId
    );
    notify_admin(
        'New order ' . $reference,
        $name . ' placed an order for ' . money($total) . '.',
        'order',
        'admin/order.php?id=' . $orderId
    );

    return ['ok' => true, 'message' => 'Your order was placed.', 'order_id' => $orderId, 'reference' => $reference];
}

/** Create an order from an accepted custom cake quotation (admin action). */
function convert_custom_request(array $request, ?string $note = null): array
{
    if ($request['quoted_price'] === null || (float) $request['quoted_price'] <= 0) {
        return ['ok' => false, 'message' => 'Set a quotation price before converting.'];
    }
    if ($request['converted_order_id'] !== null) {
        return ['ok' => false, 'message' => 'This request has already been converted.'];
    }

    $total = round((float) $request['quoted_price'], 2);

    try {
        Database::begin();
        $reference = order_reference();
        $orderId = Database::insert(
            "INSERT INTO orders
              (order_reference, user_id, customer_name, customer_phone, customer_email,
               fulfilment, delivery_date, customer_notes, subtotal, discount_total, delivery_fee, total,
               status, payment_status, payment_method)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 'confirmed', 'unpaid', NULL)",
            [
                $reference,
                $request['user_id'] !== null ? (int) $request['user_id'] : null,
                $request['customer_name'], $request['customer_phone'], $request['customer_email'],
                'pickup',
                $request['required_date'],
                'Custom cake ' . $request['order_reference'] . ($note ? ' — ' . $note : ''),
                $total, $total,
            ]
        );

        Database::insert(
            'INSERT INTO order_items
              (order_id, item_type, custom_order_id, product_name, quantity, unit_price, line_total)
             VALUES (?, ?, ?, ?, 1, ?, ?)',
            [$orderId, 'custom', (int) $request['id'], 'Custom cake ' . $request['order_reference'], $total, $total]
        );

        Database::insert(
            'INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)',
            [$orderId, 'confirmed', 'Converted from custom cake request ' . $request['order_reference'] . '.']
        );

        Database::execute(
            "UPDATE custom_orders SET status = 'converted', converted_order_id = ? WHERE id = ?",
            [$orderId, (int) $request['id']]
        );

        Database::commit();
    } catch (Throwable $e) {
        Database::rollback();
        error_log('Custom conversion failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The order could not be created.'];
    }

    if ($request['user_id'] !== null) {
        notify_customer(
            (int) $request['user_id'],
            'Your custom cake is now an order — ' . $reference,
            'We have prepared your order from quotation ' . $request['order_reference'] . '.',
            'order',
            'account/order.php?id=' . $orderId
        );
    }
    return ['ok' => true, 'message' => 'Order ' . $reference . ' created.', 'order_id' => $orderId];
}

// ---------------------------------------------------------------------------
// Form helpers
// ---------------------------------------------------------------------------

/** Page links that keep the current filters. */
function pagination_html(array $pager, string $label = 'Cakes'): string
{
    if (($pager['pages'] ?? 1) <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="' . e($label) . ' pages">';
    if ($pager['page'] > 1) {
        $html .= '<a class="page-link" href="' . e(page_url($pager['page'] - 1)) . '" rel="prev">&larr; Previous</a>';
    }
    $html .= '<span class="page-status">Page ' . (int) $pager['page'] . ' of ' . (int) $pager['pages'] . '</span>';
    if ($pager['page'] < $pager['pages']) {
        $html .= '<a class="page-link" href="' . e(page_url($pager['page'] + 1)) . '" rel="next">Next &rarr;</a>';
    }
    return $html . '</nav>';
}

function status_badge(?string $status): string
{
    $class = preg_replace('/[^a-z_]/', '', (string) $status);
    return '<span class="status-badge status-' . e($class) . '">' . e(order_status_label($status)) . '</span>';
}

function payment_badge(?string $status): string
{
    return '<span class="status-badge payment-' . e(preg_replace('/[^a-z_]/', '', (string) $status)) . '">' . e(ucfirst((string) $status)) . '</span>';
}
