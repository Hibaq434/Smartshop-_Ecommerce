<?php
declare(strict_types=1);

// ── Session ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Core dependencies ────────────────────────────────────────
require_once __DIR__ . '/../dbconnect.php';
require_once __DIR__ . '/../session_helper.php';
require_once __DIR__ . '/product_images.php';

// ── HTML escaping helper ─────────────────────────────────────
function h(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Cart count helper ────────────────────────────────────────
function getCartCount(mysqli $conn): int
{
    if (!isset($_SESSION['user_id'])) {
        return 0;
    }

    $userId = (int)$_SESSION['user_id'];

    $stmt = mysqli_prepare($conn,
        'SELECT COALESCE(SUM(quantity), 0) AS total
           FROM cart
          WHERE user_id = ?'
    );

    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $row    = $result ? mysqli_fetch_assoc($result) : null;

    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0);
}

// ── Categories helper ────────────────────────────────────────
function fetchCategories(mysqli $conn): array
{
    $result = mysqli_query(
        $conn,
        'SELECT id, category_name
           FROM categories
          ORDER BY category_name'
    );

    if (!$result) {
        return [];
    }

    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }

    return $categories;
}

// ── Sort option resolver ─────────────────────────────────────
function resolveSortOption(string $sort): array
{
    return match ($sort) {
        'best_selling' => ['p.sold_count DESC',   'Best Selling'],
        'price_asc'    => ['p.price ASC',          'Price: Low to High'],
        'price_desc'   => ['p.price DESC',         'Price: High to Low'],
        default        => ['p.created_at DESC',    'Newest'],
    };
}

// ── Ensure newsletter_subscribers table exists ─────────────────
function ensureNewsletterTable(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_newsletter_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// ── Ensure wishlist table exists ────────────────────────────────
function ensureWishlistTable(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS wishlist (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_wishlist_user_product (user_id, product_id),
            KEY idx_wishlist_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// ── Ensure extra profile columns exist on users ─────────────────
function ensureUserProfileColumns(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [
        'phone'   => "ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT NULL AFTER full_name",
        'city'    => "ALTER TABLE users ADD COLUMN city VARCHAR(120) DEFAULT NULL AFTER phone",
        'address' => "ALTER TABLE users ADD COLUMN address VARCHAR(255) DEFAULT NULL AFTER city",
    ];

    foreach ($columns as $column => $alterSql) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE '" . $column . "'");
        if ($result && mysqli_num_rows($result) === 0) {
            mysqli_query($conn, $alterSql);
        }
    }
}

// ── Categories with live product counts ─────────────────────────
function fetchCategoriesWithCounts(mysqli $conn): array
{
    $result = mysqli_query(
        $conn,
        'SELECT c.id, c.category_name, COUNT(p.id) AS product_count
           FROM categories c
           LEFT JOIN products p ON p.category_id = c.id
          GROUP BY c.id, c.category_name
          ORDER BY c.category_name'
    );

    if (!$result) {
        return [];
    }

    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }

    return $categories;
}

// ── Wishlist product ids for a user (for quick lookups) ─────────
function fetchWishlistProductIds(mysqli $conn, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $stmt = mysqli_prepare($conn, 'SELECT product_id FROM wishlist WHERE user_id = ?');
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $ids = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $ids[] = (int)$row['product_id'];
    }
    mysqli_stmt_close($stmt);

    return $ids;
}

// ── Ensure categories table exists (seeded with core categories) ─
function ensureCategoriesTable(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_name VARCHAR(120) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_categories_name (category_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $countRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM categories');
    $count = $countRes ? (int)(mysqli_fetch_assoc($countRes)['c'] ?? 0) : 0;

    if ($count === 0) {
        mysqli_query(
            $conn,
            "INSERT INTO categories (category_name) VALUES ('Electronics'), ('Fashion'), ('Home'), ('Beauty')"
        );
    }
}

// ── Ensure extra product columns used across the storefront/admin ─
function ensureProductExtraColumns(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [
        'category_id'       => "ALTER TABLE products ADD COLUMN category_id INT UNSIGNED DEFAULT NULL AFTER image",
        'description'       => "ALTER TABLE products ADD COLUMN description TEXT DEFAULT NULL AFTER category_id",
        'rating'            => "ALTER TABLE products ADD COLUMN rating DECIMAL(2,1) NOT NULL DEFAULT 0.0 AFTER description",
        'sold_count'        => "ALTER TABLE products ADD COLUMN sold_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER rating",
        'created_at'        => "ALTER TABLE products ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER sold_count",
        'compare_at_price'  => "ALTER TABLE products ADD COLUMN compare_at_price DECIMAL(10,2) DEFAULT NULL AFTER price",
    ];

    foreach ($columns as $column => $alterSql) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE '" . $column . "'");
        if ($result && mysqli_num_rows($result) === 0) {
            mysqli_query($conn, $alterSql);
        }
    }
}

// ── Assign existing products to Electronics + seed the other
//    categories with realistic products so counts are never empty ─
function ensureCategorySampleProducts(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $catByName = [];
    $catRes = mysqli_query($conn, 'SELECT id, category_name FROM categories');
    if ($catRes) {
        while ($row = mysqli_fetch_assoc($catRes)) {
            $catByName[$row['category_name']] = (int)$row['id'];
        }
    }

    // Any product created before category_id existed defaults to Electronics.
    if (isset($catByName['Electronics'])) {
        mysqli_query(
            $conn,
            'UPDATE products SET category_id = ' . (int)$catByName['Electronics'] . ' WHERE category_id IS NULL'
        );
    }

    $samples = [
        'Fashion' => [
            ['Classic Denim Jacket', 54.99, 18, 4.5, 128, 'Timeless mid-wash denim jacket with a button closure and classic collar — a versatile layering piece for any season.'],
            ['Leather Crossbody Bag', 39.50, 25, 4.6, 94, 'Compact genuine-leather crossbody bag with an adjustable strap and secure zip closure. Fits daily essentials with room to spare.'],
            ['Slim Fit Chino Trousers', 32.00, 30, 4.3, 156, 'Tailored slim-fit chinos in stretch cotton twill — smart enough for the office, comfortable enough for the weekend.'],
            ['Cotton Graphic T-Shirt', 14.99, 60, 4.4, 212, 'Soft 100% cotton tee with a bold graphic print. Pre-shrunk fabric holds its shape wash after wash.'],
            ['Everyday Running Sneakers', 64.99, 22, 4.7, 189, 'Lightweight running shoes with a breathable mesh upper and cushioned sole for all-day comfort on any run.'],
        ],
        'Home' => [
            ['Non-Stick Cookware Set', 89.99, 14, 4.5, 143, 'Durable non-stick cookware set that heats evenly and cleans up in seconds — everything you need to get cooking.'],
            ['Memory Foam Pillow', 22.50, 40, 4.2, 87, 'Contoured memory foam pillow that cradles the neck and shoulders for deeper, more restful sleep.'],
            ['Ceramic Dinnerware Set (16pc)', 45.00, 16, 4.4, 76, 'A 16-piece ceramic dinnerware set with a clean, modern glaze — dishwasher and microwave safe.'],
            ['LED Desk Lamp', 19.99, 35, 4.3, 102, 'Adjustable LED desk lamp with multiple brightness settings and a flexible arm for focused, flicker-free light.'],
            ['Cotton Bedsheet Set', 29.99, 28, 4.3, 118, 'Breathable 100% cotton bedsheet set with a soft hand-feel that gets softer with every wash.'],
        ],
        'Beauty' => [
            ['Vitamin C Facial Serum', 24.99, 45, 4.6, 231, 'Vitamin C face serum that brightens skin tone and softens the look of fine lines with daily use.'],
            ['Matte Liquid Lipstick Set', 18.50, 50, 4.5, 264, 'Long-wearing matte lipstick with rich pigment in one swipe and a comfortable, non-drying finish.'],
            ['Argan Oil Hair Treatment', 16.99, 38, 4.3, 176, 'Lightweight argan oil treatment that tames frizz and adds shine without weighing hair down.'],
            ['Hydrating Face Sunscreen SPF50', 15.00, 42, 4.4, 97, 'Broad-spectrum SPF50 sunscreen with a hydrating, non-greasy finish that layers well under makeup.'],
            ['Natural Bristle Makeup Brush Set', 21.99, 30, 4.5, 149, 'A complete natural-bristle brush set for flawless foundation, powder, and blush application.'],
        ],
    ];

    foreach ($samples as $catName => $productList) {
        if (!isset($catByName[$catName])) {
            continue;
        }
        $catId = $catByName[$catName];

        $countRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM products WHERE category_id = ' . $catId);
        $existing = $countRes ? (int)(mysqli_fetch_assoc($countRes)['c'] ?? 0) : 0;
        if ($existing > 0) {
            continue;
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO products (product_name, price, quantity, image, category_id, rating, sold_count, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            continue;
        }

        foreach ($productList as [$name, $price, $qty, $rating, $sold, $description]) {
            $img = PRODUCT_IMAGE_DEFAULT_FILE;
            mysqli_stmt_bind_param($stmt, 'sdisidis', $name, $price, $qty, $img, $catId, $rating, $sold, $description);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }
}

// ── Backfill rating/sold_count/description for sample products that
//    were already inserted by an earlier version of this function
//    (before those fields existed here). Only touches rows that still
//    have the untouched defaults (rating 0, sold_count 0), so any real
//    admin edits are never overwritten. Safe to run on every request. ─
function ensureCategorySampleProductDetails(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $details = [
        'Classic Denim Jacket'              => [4.5, 128, 'Timeless mid-wash denim jacket with a button closure and classic collar — a versatile layering piece for any season.'],
        'Leather Crossbody Bag'             => [4.6, 94, 'Compact genuine-leather crossbody bag with an adjustable strap and secure zip closure. Fits daily essentials with room to spare.'],
        'Slim Fit Chino Trousers'           => [4.3, 156, 'Tailored slim-fit chinos in stretch cotton twill — smart enough for the office, comfortable enough for the weekend.'],
        'Cotton Graphic T-Shirt'            => [4.4, 212, 'Soft 100% cotton tee with a bold graphic print. Pre-shrunk fabric holds its shape wash after wash.'],
        'Everyday Running Sneakers'         => [4.7, 189, 'Lightweight running shoes with a breathable mesh upper and cushioned sole for all-day comfort on any run.'],
        'Running Shoes'                     => [4.7, 189, 'Lightweight running shoes with a breathable mesh upper and cushioned sole for all-day comfort on any run.'],
        'Non-Stick Cookware Set'            => [4.5, 143, 'Durable non-stick cookware set that heats evenly and cleans up in seconds — everything you need to get cooking.'],
        'Memory Foam Pillow'                => [4.2, 87, 'Contoured memory foam pillow that cradles the neck and shoulders for deeper, more restful sleep.'],
        'Ceramic Dinnerware Set (16pc)'     => [4.4, 76, 'A 16-piece ceramic dinnerware set with a clean, modern glaze — dishwasher and microwave safe.'],
        'LED Desk Lamp'                     => [4.3, 102, 'Adjustable LED desk lamp with multiple brightness settings and a flexible arm for focused, flicker-free light.'],
        'Cotton Bedsheet Set'               => [4.3, 118, 'Breathable 100% cotton bedsheet set with a soft hand-feel that gets softer with every wash.'],
        'Vitamin C Facial Serum'            => [4.6, 231, 'Vitamin C face serum that brightens skin tone and softens the look of fine lines with daily use.'],
        'Matte Liquid Lipstick Set'         => [4.5, 264, 'Long-wearing matte lipstick with rich pigment in one swipe and a comfortable, non-drying finish.'],
        'Argan Oil Hair Treatment'          => [4.3, 176, 'Lightweight argan oil treatment that tames frizz and adds shine without weighing hair down.'],
        'Hydrating Face Sunscreen SPF50'    => [4.4, 97, 'Broad-spectrum SPF50 sunscreen with a hydrating, non-greasy finish that layers well under makeup.'],
        'Natural Bristle Makeup Brush Set'  => [4.5, 149, 'A complete natural-bristle brush set for flawless foundation, powder, and blush application.'],
    ];

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE products SET rating = ?, sold_count = ?, description = ?
          WHERE product_name = ? AND rating = 0 AND sold_count = 0'
    );
    if (!$stmt) {
        return;
    }

    foreach ($details as $name => [$rating, $sold, $description]) {
        mysqli_stmt_bind_param($stmt, 'diss', $rating, $sold, $description, $name);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
}

// ── Ensure orders / order_items tables exist ─────────────────────
function ensureOrdersTables(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            status ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            shipping_name VARCHAR(255) NOT NULL,
            shipping_phone VARCHAR(30) NOT NULL,
            shipping_address VARCHAR(255) DEFAULT NULL,
            shipping_city VARCHAR(120) NOT NULL,
            payment_method VARCHAR(30) NOT NULL,
            payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_orders_user (user_id),
            KEY idx_orders_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS order_items (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY idx_order_items_order (order_id),
            KEY idx_order_items_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// ── Ensure cart table exists ──────────────────────────────────────
function ensureCartTable(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS cart (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_cart_user_product (user_id, product_id),
            KEY idx_cart_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

// ── Ensure users.status column exists (active/inactive) ──────────
function ensureUserStatusColumn(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'status'");
    if ($result && mysqli_num_rows($result) === 0) {
        mysqli_query(
            $conn,
            "ALTER TABLE users ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER role"
        );
    }
}

// ── Ensure settings table exists + seed defaults ─────────────────
function ensureSettingsTable(mysqli $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT DEFAULT NULL,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $defaults = [
        'store_name'      => 'SmartShop',
        'store_email'     => 'support@smartshop.test',
        'store_phone'     => '+254 700 000000',
        'store_address'   => 'Nairobi, Kenya',
        'currency'        => 'USD',
        'tax_rate'        => '0',
        'shipping_fee'    => '0',
        'homepage_banner' => 'Welcome to SmartShop, quality products, fast delivery.',
        'footer_text'     => '(c) ' . date('Y') . ' SmartShop. All rights reserved.',
        'logo'            => PRODUCT_IMAGE_DEFAULT_FILE,
    ];

    foreach ($defaults as $key => $value) {
        $stmt = mysqli_prepare($conn, 'INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

function fetchSettings(mysqli $conn): array
{
    ensureSettingsTable($conn);

    $settings = [];
    $res = mysqli_query($conn, 'SELECT setting_key, setting_value FROM settings');
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $settings;
}

function getSetting(mysqli $conn, string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = fetchSettings($conn);
    }

    return $cache[$key] ?? $default;
}

// ── Global currency helpers ───────────────────────────────────────
function currencySymbol(mysqli $conn): string
{
    $currency = strtoupper(trim((string)getSetting($conn, 'currency', 'USD')));

    return match ($currency) {
        'KSH' => 'KSh',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        default => '$',
    };
}

function money(mysqli $conn, float $amount): string
{
    $symbol = currencySymbol($conn);
    $spaced = !in_array($symbol, ['$', '€', '£'], true);

    return $symbol . ($spaced ? ' ' : '') . number_format($amount, 2);
}

// ── Discount badge helper: returns 0 when there's nothing to show.
//    A product only has a discount when an admin has set a
//    compare_at_price higher than the current selling price. ─────
function productDiscountPercent(float $price, ?float $compareAtPrice): int
{
    if ($compareAtPrice === null || $compareAtPrice <= $price || $compareAtPrice <= 0) {
        return 0;
    }

    return (int)round((($compareAtPrice - $price) / $compareAtPrice) * 100);
}

// ── SQL fragment for "actually sold" orders: paid or delivered,
//    and never cancelled. Used everywhere revenue is calculated so
//    pending/processing/unpaid/cancelled orders never count as sales. ─
function completedOrderSql(): string
{
    // This project has no literal 'completed' status — 'delivered' is the
    // equivalent (an order that has actually been fulfilled). Revenue is
    // tied to that single condition only, so Pending/Processing/Cancelled
    // orders never contribute, even if payment_status is independently
    // marked 'paid' before delivery.
    return "status = 'delivered'";
}

// ── Admin dashboard overview stats ────────────────────────────────
function adminDashboardStats(mysqli $conn): array
{
    $completed = completedOrderSql();

    $stats = [
        'revenue_today'      => 0.0,
        'revenue_month'      => 0.0,
        'pending_value'      => 0.0,
        'pending_orders'     => 0,
        'processing_orders'  => 0,
        'delivered_orders'   => 0,
        'cancelled_orders'   => 0,
        'total_orders'       => 0,
        'total_customers'    => 0,
        'total_users'        => 0,
        'total_products'     => 0,
        'low_stock'          => 0,
        'latest_orders'      => [],
        'recent_users'       => [],
        // Kept for backward compatibility with any older callers.
        'revenue'            => 0.0,
        'completed_orders'   => 0,
    ];

    if ($res = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) AS r FROM orders WHERE $completed AND DATE(created_at) = CURDATE()")) {
        $stats['revenue_today'] = (float)(mysqli_fetch_assoc($res)['r'] ?? 0);
    }
    if ($res = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) AS r FROM orders WHERE $completed AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")) {
        $stats['revenue_month'] = (float)(mysqli_fetch_assoc($res)['r'] ?? 0);
    }
    if ($res = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) AS r FROM orders WHERE status = 'pending'")) {
        $stats['pending_value'] = (float)(mysqli_fetch_assoc($res)['r'] ?? 0);
    }
    if ($res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'")) {
        $stats['pending_orders'] = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    }
    if ($res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'processing'")) {
        $stats['processing_orders'] = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    }
    if ($res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'delivered'")) {
        $stats['delivered_orders'] = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
        $stats['completed_orders'] = $stats['delivered_orders'];
    }
    if ($res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'cancelled'")) {
        $stats['cancelled_orders'] = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    }
    if ($res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM orders')) {
        $stats['total_orders'] = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    }
    if ($res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'user'")) {
        $stats['total_customers'] = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    }
    if ($res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM users')) {
        $stats['total_users'] = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    }
    if ($res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM products')) {
        $stats['total_products'] = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    }
    if ($res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM products WHERE quantity < 10')) {
        $stats['low_stock'] = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    }

    // Backward-compatible aggregate revenue (all completed orders, all time).
    if ($res = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) AS r FROM orders WHERE $completed")) {
        $stats['revenue'] = (float)(mysqli_fetch_assoc($res)['r'] ?? 0);
    }

    $res = mysqli_query(
        $conn,
        'SELECT id, shipping_name, total_amount, status, created_at FROM orders ORDER BY created_at DESC LIMIT 5'
    );
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $stats['latest_orders'][] = $row;
        }
    }

    $res = mysqli_query(
        $conn,
        'SELECT id, username, full_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5'
    );
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $stats['recent_users'][] = $row;
        }
    }

    return $stats;
}

// ── Paginated / searchable users list for the admin Users module ──
function fetchUsersPaged(mysqli $conn, string $search, int $page, int $perPage): array
{
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $where = '';
    $params = [];
    $types = '';
    if ($search !== '') {
        $where = 'WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?';
        $like = '%' . $search . '%';
        $params = [$like, $like, $like];
        $types = 'sss';
    }

    $total = 0;
    $countSql = "SELECT COUNT(*) AS c FROM users $where";
    if ($types !== '') {
        $stmt = mysqli_prepare($conn, $countSql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $total = (int)(($res ? mysqli_fetch_assoc($res) : [])['c'] ?? 0);
            mysqli_stmt_close($stmt);
        }
    } else {
        $res = mysqli_query($conn, $countSql);
        $total = $res ? (int)(mysqli_fetch_assoc($res)['c'] ?? 0) : 0;
    }

    $sql = "SELECT u.id, u.username, u.full_name, u.email, u.role, u.status, u.created_at,
                   (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count
              FROM users u
              $where
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?";

    $users = [];
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if ($types !== '') {
            $allTypes = $types . 'ii';
            $allParams = array_merge($params, [$perPage, $offset]);
            mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
        } else {
            mysqli_stmt_bind_param($stmt, 'ii', $perPage, $offset);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $users[] = $row;
        }
        mysqli_stmt_close($stmt);
    }

    return ['users' => $users, 'total' => $total];
}

// ── Analytics module data ─────────────────────────────────────────
function analyticsData(mysqli $conn): array
{
    $completed = completedOrderSql();

    $data = [
        'sales_today'         => 0.0,
        'sales_month'         => 0.0,
        'pending_value'       => 0.0,
        'top_products'        => [],
        'top_categories'      => [],
        'revenue_chart'       => [],
        'orders_chart'        => [],
        'registrations_chart' => [],
        'monthly_sales'       => [],
        'low_stock'           => [],
    ];

    // Revenue = paid or delivered orders only. Pending/processing/unpaid/
    // cancelled orders never contribute, no matter how large their total.
    if ($res = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) AS s FROM orders WHERE $completed AND DATE(created_at) = CURDATE()")) {
        $data['sales_today'] = (float)(mysqli_fetch_assoc($res)['s'] ?? 0);
    }
    if ($res = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) AS s FROM orders WHERE $completed AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")) {
        $data['sales_month'] = (float)(mysqli_fetch_assoc($res)['s'] ?? 0);
    }
    if ($res = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) AS s FROM orders WHERE status = 'pending'")) {
        $data['pending_value'] = (float)(mysqli_fetch_assoc($res)['s'] ?? 0);
    }

    // Top selling products now comes straight from products.sold_count,
    // which is incremented whenever an order is marked "Delivered".
    $res = mysqli_query(
        $conn,
        "SELECT p.id, p.product_name, p.image, p.sold_count,
                COALESCE((SELECT SUM(oi.quantity * oi.unit_price)
                            FROM order_items oi
                            JOIN orders o ON o.id = oi.order_id
                           WHERE oi.product_id = p.id AND $completed), 0) AS revenue
           FROM products p
          WHERE p.sold_count > 0
          ORDER BY p.sold_count DESC, revenue DESC
          LIMIT 5"
    );
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $data['top_products'][] = $row;
        }
    }

    $res = mysqli_query(
        $conn,
        "SELECT c.category_name,
                COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS revenue
           FROM categories c
           LEFT JOIN products p ON p.category_id = c.id
           LEFT JOIN order_items oi ON oi.product_id = p.id
           LEFT JOIN orders o ON o.id = oi.order_id AND $completed
          GROUP BY c.id, c.category_name
          ORDER BY revenue DESC
          LIMIT 5"
    );
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $data['top_categories'][] = $row;
        }
    }

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $label = date('D', strtotime($date));

        $rev = 0.0;
        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(total_amount),0) AS r FROM orders WHERE $completed AND DATE(created_at) = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $date);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $rev = (float)(($r ? mysqli_fetch_assoc($r) : [])['r'] ?? 0);
            mysqli_stmt_close($stmt);
        }
        $data['revenue_chart'][] = ['label' => $label, 'value' => $rev];

        $ordCount = 0;
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at) = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $date);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $ordCount = (int)(($r ? mysqli_fetch_assoc($r) : [])['c'] ?? 0);
            mysqli_stmt_close($stmt);
        }
        $data['orders_chart'][] = ['label' => $label, 'value' => $ordCount];

        $regCount = 0;
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM users WHERE DATE(created_at) = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $date);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $regCount = (int)(($r ? mysqli_fetch_assoc($r) : [])['c'] ?? 0);
            mysqli_stmt_close($stmt);
        }
        $data['registrations_chart'][] = ['label' => $label, 'value' => $regCount];
    }

    // Monthly Sales — last 6 calendar months, completed orders only.
    for ($m = 5; $m >= 0; $m--) {
        $monthStart = date('Y-m-01', strtotime("-$m months"));
        $label = date('M', strtotime($monthStart));

        $monthRevenue = 0.0;
        $stmt = mysqli_prepare(
            $conn,
            "SELECT COALESCE(SUM(total_amount),0) AS r FROM orders
              WHERE $completed AND YEAR(created_at) = YEAR(?) AND MONTH(created_at) = MONTH(?)"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $monthStart, $monthStart);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $monthRevenue = (float)(($r ? mysqli_fetch_assoc($r) : [])['r'] ?? 0);
            mysqli_stmt_close($stmt);
        }
        $data['monthly_sales'][] = ['label' => $label, 'value' => $monthRevenue];
    }

    $res = mysqli_query($conn, 'SELECT id, product_name, quantity, image FROM products WHERE quantity < 10 ORDER BY quantity ASC LIMIT 10');
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $data['low_stock'][] = $row;
        }
    }

    return $data;
}

// ── Single entry point that guarantees every required table/column
//    exists: users, products, categories, cart, wishlist, orders,
//    order_items, newsletter_subscribers, settings. Previously only
//    dashboard.php called these individually, so a customer hitting
//    cart.php/checkout.php/login.php on a brand-new install (before
//    any admin had ever opened the dashboard) could hit a fatal
//    "table doesn't exist" error. Every page that requires app.php
//    should call this once, right after. Cheap to call repeatedly —
//    every underlying ensure*() function is itself guarded by a
//    static flag, so this is a no-op after the first call per request. ─
function ensureCoreSchema(mysqli $conn): void
{
    ensureNewsletterTable($conn);
    ensureWishlistTable($conn);
    ensureUserProfileColumns($conn);
    ensureUserStatusColumn($conn);
    ensureCategoriesTable($conn);
    ensureProductsImageColumn($conn);
    ensureProductExtraColumns($conn);
    ensureCategorySampleProducts($conn);
    ensureCategorySampleProductDetails($conn);
    ensureCartTable($conn);
    ensureOrdersTables($conn);
    ensureSettingsTable($conn);
}