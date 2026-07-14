<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/app.php';

requireAdmin();
ensureProductsImageColumn($conn);
ensureCategoriesTable($conn);
ensureProductExtraColumns($conn);
ensureCategorySampleProducts($conn);
ensureCategorySampleProductDetails($conn);
ensureOrdersTables($conn);
ensureCartTable($conn);
ensureUserStatusColumn($conn);
ensureSettingsTable($conn);

$section = (string)($_GET['section'] ?? 'dashboard');
$allowedSections = ['dashboard', 'orders', 'users', 'products', 'categories', 'analytics', 'settings', 'support'];
if (!in_array($section, $allowedSections, true)) {
    $section = 'dashboard';
}

$flash = (string)($_GET['msg'] ?? '');
$editId = (int)($_GET['edit_id'] ?? 0);
$currentAdminId = (int)($_SESSION['user_id'] ?? 0);

function redirectToUsers(string $message): void
{
    header('Location: dashboard.php?section=users&msg=' . urlencode($message));
    exit;
}

function redirectToCategories(string $message): void
{
    header('Location: dashboard.php?section=categories&msg=' . urlencode($message));
    exit;
}

function redirectToSettings(string $message): void
{
    header('Location: dashboard.php?section=settings&msg=' . urlencode($message));
    exit;
}

function redirectToProducts(string $message): void
{
    header('Location: dashboard.php?section=products&msg=' . urlencode($message));
    exit;
}

function redirectToOrders(string $message): void
{
    header('Location: dashboard.php?section=orders&msg=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_product') {
        $name = trim((string)($_POST['product_name'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $compareAtPriceRaw = trim((string)($_POST['compare_at_price'] ?? ''));
        $compareAtPrice = $compareAtPriceRaw !== '' ? (float)$compareAtPriceRaw : null;
        $qty = (int)($_POST['quantity'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $imageUrl = trim((string)($_POST['product_image_url'] ?? ''));
        $imageValue = ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl) === 1)
            ? $imageUrl
            : resolveProductImageFilename($_POST['product_image'] ?? null);

        if ($name !== '') {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO products (product_name, price, compare_at_price, quantity, image, category_id) VALUES (?, ?, ?, ?, ?, ?)'
            );
            if ($stmt) {
                $categoryParam = $categoryId > 0 ? $categoryId : null;
                mysqli_stmt_bind_param($stmt, 'sddisi', $name, $price, $compareAtPrice, $qty, $imageValue, $categoryParam);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        redirectToProducts('Product added');
    }

    if ($action === 'update_product') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['product_name'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $compareAtPriceRaw = trim((string)($_POST['compare_at_price'] ?? ''));
        $compareAtPrice = $compareAtPriceRaw !== '' ? (float)$compareAtPriceRaw : null;
        $qty = (int)($_POST['quantity'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $imageUrl = trim((string)($_POST['product_image_url'] ?? ''));
        $imageValue = ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl) === 1)
            ? $imageUrl
            : resolveProductImageFilename($_POST['product_image'] ?? null);

        if ($id > 0 && $name !== '') {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE products SET product_name = ?, price = ?, compare_at_price = ?, quantity = ?, image = ?, category_id = ? WHERE id = ?'
            );
            if ($stmt) {
                $categoryParam = $categoryId > 0 ? $categoryId : null;
                mysqli_stmt_bind_param($stmt, 'sddisii', $name, $price, $compareAtPrice, $qty, $imageValue, $categoryParam, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        redirectToProducts('Product updated');
    }

    if ($action === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, 'DELETE FROM products WHERE id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        redirectToProducts('Product deleted');
    }

    if ($action === 'update_order_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = (string)($_POST['status'] ?? '');
        $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

        if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            redirectToOrders('Invalid order update request.');
        }

        $curStmt = mysqli_prepare($conn, 'SELECT status FROM orders WHERE id = ? LIMIT 1');
        $currentStatus = null;
        if ($curStmt) {
            mysqli_stmt_bind_param($curStmt, 'i', $orderId);
            mysqli_stmt_execute($curStmt);
            $curRes = mysqli_stmt_get_result($curStmt);
            $curRow = $curRes ? mysqli_fetch_assoc($curRes) : null;
            mysqli_stmt_close($curStmt);
            $currentStatus = $curRow ? (string)$curRow['status'] : null;
        }

        if ($currentStatus === null) {
            redirectToOrders('Order not found.');
        }

        // Only deduct stock the first time an order moves into "shipped".
        $shouldDeductStock = $newStatus === 'shipped' && !in_array($currentStatus, ['shipped', 'delivered'], true);

        // Only credit sales the first time an order moves into "delivered".
        $shouldCreditSales = $newStatus === 'delivered' && $currentStatus !== 'delivered';

        mysqli_begin_transaction($conn);
        $failure = null;

        if ($shouldDeductStock) {
            $itemsStmt = mysqli_prepare($conn, 'SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            if (!$itemsStmt) {
                $failure = 'Could not read order items.';
            } else {
                mysqli_stmt_bind_param($itemsStmt, 'i', $orderId);
                mysqli_stmt_execute($itemsStmt);
                $itemsRes = mysqli_stmt_get_result($itemsStmt);
                $orderProducts = [];
                while ($itemsRes && ($itemRow = mysqli_fetch_assoc($itemsRes))) {
                    $orderProducts[] = $itemRow;
                }
                mysqli_stmt_close($itemsStmt);

                foreach ($orderProducts as $itemRow) {
                    $pid = (int)$itemRow['product_id'];
                    $qty = (int)$itemRow['quantity'];

                    // Clamp at zero rather than fail, since the admin has already
                    // committed to shipping this order.
                    $stockStmt = mysqli_prepare($conn, 'UPDATE products SET quantity = GREATEST(quantity - ?, 0) WHERE id = ?');
                    if (!$stockStmt) {
                        $failure = 'Could not update stock levels.';
                        break;
                    }
                    mysqli_stmt_bind_param($stockStmt, 'ii', $qty, $pid);
                    mysqli_stmt_execute($stockStmt);
                    mysqli_stmt_close($stockStmt);
                }
            }
        }

        if (!$failure && $shouldCreditSales) {
            $itemsStmt = mysqli_prepare($conn, 'SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            if (!$itemsStmt) {
                $failure = 'Could not read order items.';
            } else {
                mysqli_stmt_bind_param($itemsStmt, 'i', $orderId);
                mysqli_stmt_execute($itemsStmt);
                $itemsRes = mysqli_stmt_get_result($itemsStmt);
                $deliveredProducts = [];
                while ($itemsRes && ($itemRow = mysqli_fetch_assoc($itemsRes))) {
                    $deliveredProducts[] = $itemRow;
                }
                mysqli_stmt_close($itemsStmt);

                foreach ($deliveredProducts as $itemRow) {
                    $pid = (int)$itemRow['product_id'];
                    $qty = (int)$itemRow['quantity'];

                    $salesStmt = mysqli_prepare($conn, 'UPDATE products SET sold_count = sold_count + ? WHERE id = ?');
                    if (!$salesStmt) {
                        $failure = 'Could not update sales counts.';
                        break;
                    }
                    mysqli_stmt_bind_param($salesStmt, 'ii', $qty, $pid);
                    mysqli_stmt_execute($salesStmt);
                    mysqli_stmt_close($salesStmt);
                }
            }
        }

        if (!$failure) {
            $updStmt = mysqli_prepare($conn, 'UPDATE orders SET status = ? WHERE id = ?');
            if (!$updStmt) {
                $failure = 'Could not update order status.';
            } else {
                mysqli_stmt_bind_param($updStmt, 'si', $newStatus, $orderId);
                if (!mysqli_stmt_execute($updStmt)) {
                    $failure = 'Could not update order status.';
                }
                mysqli_stmt_close($updStmt);
            }
        }

        if ($failure) {
            mysqli_rollback($conn);
            redirectToOrders($failure);
        }

        mysqli_commit($conn);
        $extra = [];
        if ($shouldDeductStock) {
            $extra[] = 'stock deducted';
        }
        if ($shouldCreditSales) {
            $extra[] = 'sales credited';
        }
        redirectToOrders(
            'Order #' . $orderId . ' updated to "' . ucfirst($newStatus) . '"'
            . ($extra ? ' and ' . implode(' and ', $extra) . '.' : '.')
        );
    }

    if ($action === 'update_payment_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newPaymentStatus = (string)($_POST['payment_status'] ?? '');
        $allowedPaymentStatuses = ['unpaid', 'paid', 'refunded'];

        if ($orderId <= 0 || !in_array($newPaymentStatus, $allowedPaymentStatuses, true)) {
            redirectToOrders('Invalid payment update request.');
        }

        $updStmt = mysqli_prepare($conn, 'UPDATE orders SET payment_status = ? WHERE id = ?');
        if ($updStmt) {
            mysqli_stmt_bind_param($updStmt, 'si', $newPaymentStatus, $orderId);
            mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);
        }

        redirectToOrders('Order #' . $orderId . ' payment marked "' . ucfirst($newPaymentStatus) . '".');
    }

    if ($action === 'create_category') {
        $name = trim((string)($_POST['category_name'] ?? ''));
        if ($name === '') {
            redirectToCategories('Category name is required.');
        }
        $stmt = mysqli_prepare($conn, 'INSERT INTO categories (category_name) VALUES (?)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $name);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirectToCategories('A category with that name already exists.');
            }
            mysqli_stmt_close($stmt);
        }
        redirectToCategories('Category "' . $name . '" created.');
    }

    if ($action === 'update_category') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['category_name'] ?? ''));
        if ($id > 0 && $name !== '') {
            $stmt = mysqli_prepare($conn, 'UPDATE categories SET category_name = ? WHERE id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $name, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        redirectToCategories('Category updated.');
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM products WHERE category_id = ?');
            $productCount = 0;
            if ($countStmt) {
                mysqli_stmt_bind_param($countStmt, 'i', $id);
                mysqli_stmt_execute($countStmt);
                $cRes = mysqli_stmt_get_result($countStmt);
                $productCount = (int)(($cRes ? mysqli_fetch_assoc($cRes) : [])['c'] ?? 0);
                mysqli_stmt_close($countStmt);
            }

            if ($productCount > 0) {
                redirectToCategories('Cannot delete: this category still has ' . $productCount . ' product(s).');
            }

            $stmt = mysqli_prepare($conn, 'DELETE FROM categories WHERE id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        redirectToCategories('Category deleted.');
    }

    if ($action === 'update_user') {
        $id = (int)($_POST['id'] ?? 0);
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($id > 0 && $fullName !== '' && $email !== '') {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET full_name = ?, email = ? WHERE id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssi', $fullName, $email, $id);
                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    redirectToUsers('Could not update user — email may already be in use.');
                }
                mysqli_stmt_close($stmt);
            }
        }
        redirectToUsers('User profile updated.');
    }

    if ($action === 'change_user_role') {
        $id = (int)($_POST['id'] ?? 0);
        $role = (string)($_POST['role'] ?? 'user');
        $role = in_array($role, ['admin', 'user'], true) ? $role : 'user';

        if ($id > 0 && $id === $currentAdminId && $role !== 'admin') {
            redirectToUsers('You cannot remove your own admin role.');
        }

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET role = ? WHERE id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $role, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        redirectToUsers('User role updated to "' . ucfirst($role) . '".');
    }

    if ($action === 'set_user_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'active');
        $status = in_array($status, ['active', 'inactive'], true) ? $status : 'active';

        if ($id > 0 && $id === $currentAdminId && $status === 'inactive') {
            redirectToUsers('You cannot deactivate your own account.');
        }

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET status = ? WHERE id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $status, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        redirectToUsers($status === 'active' ? 'Account reactivated.' : 'Account deactivated.');
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0 && $id === $currentAdminId) {
            redirectToUsers('You cannot delete your own account.');
        }

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        redirectToUsers('User account deleted.');
    }

    if ($action === 'save_settings') {
        $fields = [
            'store_name', 'store_email', 'store_phone', 'store_address',
            'currency', 'tax_rate', 'shipping_fee', 'homepage_banner', 'footer_text', 'logo',
        ];

        $stmt = mysqli_prepare($conn, 'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        if ($stmt) {
            foreach ($fields as $field) {
                $value = trim((string)($_POST[$field] ?? ''));
                mysqli_stmt_bind_param($stmt, 'ss', $field, $value);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
        redirectToSettings('Settings saved.');
    }
}

$productSearch = trim((string)($_GET['q'] ?? ''));
$productCategoryFilter = (int)($_GET['filter_category'] ?? 0);
$productStockFilter = (string)($_GET['filter_stock'] ?? '');

$productWhere = [];
$productParams = [];
$productTypes = '';

if ($productSearch !== '') {
    $productWhere[] = 'p.product_name LIKE ?';
    $productParams[] = '%' . $productSearch . '%';
    $productTypes .= 's';
}
if ($productCategoryFilter > 0) {
    $productWhere[] = 'p.category_id = ?';
    $productParams[] = $productCategoryFilter;
    $productTypes .= 'i';
}
if ($productStockFilter === 'low') {
    $productWhere[] = 'p.quantity > 0 AND p.quantity < 10';
} elseif ($productStockFilter === 'out') {
    $productWhere[] = 'p.quantity = 0';
}

$productWhereSql = $productWhere ? ('WHERE ' . implode(' AND ', $productWhere)) : '';

$products = [];
$productSql = "SELECT p.id, p.product_name, p.price, p.compare_at_price, p.quantity, p.image, p.category_id, c.category_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 $productWhereSql
                ORDER BY p.id DESC";
if ($productTypes !== '') {
    $prodStmt = mysqli_prepare($conn, $productSql);
    if ($prodStmt) {
        mysqli_stmt_bind_param($prodStmt, $productTypes, ...$productParams);
        mysqli_stmt_execute($prodStmt);
        $res = mysqli_stmt_get_result($prodStmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $products[] = $row;
        }
        mysqli_stmt_close($prodStmt);
    }
} else {
    $res = mysqli_query($conn, $productSql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $products[] = $row;
        }
    }
}

$editProduct = null;
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, product_name, price, compare_at_price, quantity, image, category_id FROM products WHERE id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $editId);
        mysqli_stmt_execute($stmt);
        $editRes = mysqli_stmt_get_result($stmt);
        $editProduct = $editRes ? mysqli_fetch_assoc($editRes) : null;
        mysqli_stmt_close($stmt);
    }
}

function productEmoji(int $id): string
{
    $emojis = ['🎧', '⌚', '💡', '🥽', '📷', '🔊', '💻', '🛍️', '📦', '🧠', '📱'];
    return $emojis[$id % count($emojis)];
}

$orders = [];
$ordersRes = mysqli_query(
    $conn,
    'SELECT o.id, o.user_id, o.status, o.total_amount, o.shipping_name, o.shipping_phone,
            o.shipping_address, o.shipping_city, o.payment_method, o.payment_status, o.created_at,
            u.username, u.email,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
       FROM orders o
       LEFT JOIN users u ON u.id = o.user_id
      ORDER BY o.created_at DESC'
);
if ($ordersRes) {
    while ($row = mysqli_fetch_assoc($ordersRes)) {
        $orders[] = $row;
    }
}

$orderItemsByOrder = [];
if ($orders) {
    $orderIds = array_map(static fn($o) => (int)$o['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('i', count($orderIds));

    $itemsSql = "SELECT oi.order_id, oi.quantity, oi.unit_price, p.product_name
                   FROM order_items oi
                   JOIN products p ON p.id = oi.product_id
                  WHERE oi.order_id IN ($placeholders)
                  ORDER BY oi.id ASC";

    $itemsStmt = mysqli_prepare($conn, $itemsSql);
    if ($itemsStmt) {
        mysqli_stmt_bind_param($itemsStmt, $types, ...$orderIds);
        mysqli_stmt_execute($itemsStmt);
        $itemsRes = mysqli_stmt_get_result($itemsStmt);
        while ($itemsRes && ($row = mysqli_fetch_assoc($itemsRes))) {
            $orderItemsByOrder[(int)$row['order_id']][] = $row;
        }
        mysqli_stmt_close($itemsStmt);
    }
}

function orderStatusBadgeClass(string $status): string
{
    return match ($status) {
        'pending' => 'badge-amber',
        'processing' => 'badge-blue',
        'shipped' => 'badge-blue',
        'delivered' => 'badge-green',
        'cancelled' => 'badge-red',
        default => 'badge-blue',
    };
}

function paymentStatusBadgeClass(string $status): string
{
    return match ($status) {
        'paid' => 'badge-green',
        'unpaid' => 'badge-amber',
        'refunded' => 'badge-red',
        default => 'badge-blue',
    };
}

// ── Dashboard overview stats (real DB data) ──────────────────────
$dashStats = adminDashboardStats($conn);

// ── Users module data ─────────────────────────────────────────────
$userSearch = trim((string)($_GET['q'] ?? ''));
$userPage = max(1, (int)($_GET['page'] ?? 1));
$usersPerPage = 8;
$usersResult = fetchUsersPaged($conn, $userSearch, $userPage, $usersPerPage);
$usersList = $usersResult['users'];
$usersTotal = $usersResult['total'];
$usersTotalPages = max(1, (int)ceil($usersTotal / $usersPerPage));
$editUserId = (int)($_GET['edit_user'] ?? 0);
$editUser = null;
if ($editUserId > 0) {
    $euStmt = mysqli_prepare($conn, 'SELECT id, username, full_name, email, role, status FROM users WHERE id = ?');
    if ($euStmt) {
        mysqli_stmt_bind_param($euStmt, 'i', $editUserId);
        mysqli_stmt_execute($euStmt);
        $euRes = mysqli_stmt_get_result($euStmt);
        $editUser = $euRes ? mysqli_fetch_assoc($euRes) : null;
        mysqli_stmt_close($euStmt);
    }
}

// ── Categories module data ─────────────────────────────────────────
$categoriesList = fetchCategoriesWithCounts($conn);
$editCategoryId = (int)($_GET['edit_category'] ?? 0);
$editCategory = null;
if ($editCategoryId > 0) {
    $ecStmt = mysqli_prepare($conn, 'SELECT id, category_name FROM categories WHERE id = ?');
    if ($ecStmt) {
        mysqli_stmt_bind_param($ecStmt, 'i', $editCategoryId);
        mysqli_stmt_execute($ecStmt);
        $ecRes = mysqli_stmt_get_result($ecStmt);
        $editCategory = $ecRes ? mysqli_fetch_assoc($ecRes) : null;
        mysqli_stmt_close($ecStmt);
    }
}

// ── Analytics module data ─────────────────────────────────────────
$analytics = analyticsData($conn);
$maxRevenueChart = max(1.0, ...array_column($analytics['revenue_chart'], 'value'));
$maxOrdersChart = max(1, ...array_column($analytics['orders_chart'], 'value'));
$maxMonthlyChart = max(1.0, ...array_column($analytics['monthly_sales'], 'value'));

// ── Settings module data ─────────────────────────────────────────
$settingsData = fetchSettings($conn);
function setting(array $settings, string $key, string $default = ''): string
{
    return (string)($settings[$key] ?? $default);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — SmartShop</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#2563EB;--blue-dark:#1d4ed8;--gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-300:#cbd5e1;--gray-500:#64748b;--gray-700:#334155;--gray-900:#0f172a;--green:#16a34a;--red:#dc2626;--amber:#d97706}
body{font-family:'Segoe UI',system-ui,sans-serif;color:var(--gray-900);background:var(--gray-50);font-size:14px;line-height:1.5}
a{text-decoration:none}
.topbar{display:flex;align-items:center;gap:16px;padding:0 24px;height:56px;border-bottom:1px solid var(--gray-200);background:#fff;position:sticky;top:0;z-index:50}
.brand{font-size:18px;font-weight:800;color:var(--blue)}
.topnav{display:flex;gap:12px;align-items:center;font-size:13px}
.topnav a{color:var(--gray-700)}
.topnav a:hover{color:var(--blue)}
.topnav .active{font-weight:700;color:var(--blue)}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.profile-menu{position:relative}
.profile-trigger{display:inline-flex;align-items:center;gap:8px;padding:4px 8px 4px 4px;border:1px solid var(--gray-200);border-radius:999px;background:#fff;cursor:pointer;font:inherit;color:inherit}
.profile-trigger:hover{background:var(--gray-50)}
.profile-trigger .avatar{width:32px;height:32px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:#fff}
.profile-trigger .meta{text-align:left;line-height:1.2}
.profile-trigger .meta-name{font-size:13px;font-weight:700;color:var(--gray-700)}
.profile-trigger .meta-role{font-size:10px;color:var(--gray-500)}
.profile-trigger .chevron{font-size:10px;color:var(--gray-500);margin-left:2px}
.profile-dropdown{display:none;position:absolute;right:0;top:calc(100% + 8px);min-width:210px;background:#fff;border:1px solid var(--gray-200);border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.12);padding:6px 0;z-index:120}
.profile-dropdown.open{display:block}
.profile-dropdown-head{padding:10px 14px;border-bottom:1px solid var(--gray-100);font-size:12px;color:var(--gray-500)}
.profile-dropdown-head strong{display:block;font-size:13px;color:var(--gray-900);margin-bottom:2px}
.profile-dropdown a,.profile-dropdown button{display:block;width:100%;text-align:left;padding:10px 14px;font-size:13px;color:var(--gray-700);background:transparent;border:none;cursor:pointer;font:inherit}
.profile-dropdown a:hover,.profile-dropdown button:hover{background:var(--gray-50)}
.profile-dropdown .menu-danger{color:var(--red);border-top:1px solid var(--gray-100);margin-top:4px;padding-top:12px}
.product-thumb{width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid var(--gray-200);background:var(--gray-100)}
.product-image-preview{width:120px;height:120px;border-radius:10px;object-fit:cover;border:1px solid var(--gray-200);background:var(--gray-100);margin-bottom:10px;display:block}
.image-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px}
.pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.pill-admin{background:#dbeafe;color:#1e40af}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:all .15s}
.btn-primary{background:var(--blue);color:#fff}.btn-primary:hover{background:var(--blue-dark)}
.btn-outline{background:#fff;color:var(--gray-700);border:1px solid var(--gray-200)}.btn-outline:hover{background:var(--gray-50)}
.layout{display:grid;grid-template-columns:220px 1fr;min-height:calc(100vh - 56px)}
.sidebar{background:var(--gray-900);color:#fff;padding:18px 0;display:flex;flex-direction:column}
.sidebar-head{padding:0 16px 16px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:12px}
.avatar{width:42px;height:42px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;margin-bottom:8px}
.name{font-size:13px;font-weight:700}
.role{font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.5px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 16px;font-size:13px;color:rgba(255,255,255,.68);border-left:2px solid transparent}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:rgba(255,255,255,.08);color:#fff;border-left-color:var(--blue)}
.sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,.1)}
.sidebar-footer small{display:block;color:rgba(255,255,255,.45);line-height:1.6}
.main{padding:24px;overflow:auto}
.page{display:none}.page.active{display:block}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.title{font-size:22px;font-weight:800}
.subtitle{font-size:12px;color:var(--gray-500);margin-top:4px}
.notice{background:#fff;border:1px solid var(--gray-200);border-left:4px solid var(--blue);border-radius:10px;padding:12px 14px;margin-bottom:12px;font-size:12px;color:var(--gray-700)}
.metric-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px}
.metric-card,.card{background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:16px}
.metric-label{font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.metric-value{font-size:24px;font-weight:800}
.metric-change{font-size:11px;margin-top:4px;font-weight:600}.metric-up{color:var(--green)}.metric-down{color:var(--red)}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.card-title{font-size:15px;font-weight:800;margin-bottom:14px}
.chart-bars{display:flex;align-items:flex-end;gap:4px;height:110px}
.chart-bar{flex:1;border-radius:4px 4px 0 0;background:var(--blue);opacity:.5;min-height:8px}.chart-bar.highlight{opacity:1}
.chart-labels{display:flex;gap:4px;margin-top:4px}.chart-day{flex:1;text-align:center;font-size:9px;color:var(--gray-500)}
.order-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--gray-100);font-size:12px}.order-row:last-child{border:none}
.order-id{font-weight:700;color:var(--gray-700)}.order-name{color:var(--gray-500);font-size:11px;margin-top:1px}
.inventory-circle{width:90px;height:90px;border-radius:50%;background:conic-gradient(var(--green) 0% 92%,var(--gray-200) 92% 100%);display:flex;align-items:center;justify-content:center;margin:8px auto}
.inventory-inner{width:68px;height:68px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:var(--green)}
.inv-stats{display:flex;justify-content:space-around;margin-top:14px;text-align:center}.inv-value{font-size:16px;font-weight:800}.inv-label{font-size:10px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.3px}
.table{width:100%;border-collapse:collapse;overflow:hidden}
.table th{background:var(--gray-900);color:#fff;text-align:left;padding:10px;font-size:12px}
.table td{padding:10px;border-bottom:1px solid var(--gray-100);font-size:12px}
.table tr:last-child td{border-bottom:none}
.small-input{width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;outline:none}.small-input:focus{border-color:var(--blue)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-group{margin-bottom:12px}
.form-label{font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:block}
.inline-actions{display:flex;gap:8px;align-items:center}
.link-btn{background:transparent;border:none;color:var(--blue);cursor:pointer;font-weight:700;font-size:12px;padding:0}.link-btn.danger{color:var(--red)}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}.badge-blue{background:#dbeafe;color:#1e40af}.badge-green{background:#dcfce7;color:#15803d}.badge-red{background:#fee2e2;color:#b91c1c}.badge-amber{background:#fef3c7;color:#92400e}
.section-actions{display:flex;gap:8px;flex-wrap:wrap}
.pagination{display:flex;gap:6px;margin-top:14px;flex-wrap:wrap}
.page-link{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 8px;border-radius:6px;border:1px solid var(--gray-200);color:var(--gray-700);font-size:12px;font-weight:700}
.page-link:hover{background:var(--gray-50)}
.page-link.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.link-btn:disabled{color:var(--gray-300);cursor:not-allowed}
@media(max-width:900px){.layout{grid-template-columns:1fr}.sidebar{display:none}}
@media(max-width:768px){.metric-grid,.grid-2,.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>

<header class="topbar">
  <a class="brand" href="dashboard.php">SmartShop Dashboard</a>
  <nav class="topnav">
    <a href="index.php">Home</a>
    <a href="index.php?p=shop">Shop</a>
    <a class="active" href="dashboard.php">Dashboard</a>
  </nav>
  <div class="topbar-right">
    <div class="profile-menu" id="admin-profile-menu">
      <button class="profile-trigger" type="button" id="admin-profile-trigger" aria-haspopup="true" aria-expanded="false">
        <span class="avatar"><?= strtoupper(substr(currentUsername(), 0, 2)) ?></span>
        <span class="meta">
          <span class="meta-name"><?= h(currentFullName()) ?></span>
          <span class="meta-role"><?= h(currentRoleLabel()) ?></span>
        </span>
        <span class="chevron">▾</span>
      </button>
      <div class="profile-dropdown" id="admin-profile-dropdown">
        <div class="profile-dropdown-head">
          <strong><?= h(currentFullName()) ?></strong>
          <?= h(currentRoleLabel()) ?>
        </div>
        <a href="index.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="dashboard.php">Dashboard</a>
        <a class="menu-danger" href="logout.php">Logout</a>
      </div>
    </div>
  </div>
</header>

<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-head">
      <div class="avatar"><?= strtoupper(substr(currentUsername(), 0, 2)) ?></div>
      <div class="name"><?= h(currentFullName()) ?></div>
      <div class="role">System Admin</div>
    </div>

    <a class="nav-item<?= $section === 'dashboard' ? ' active' : '' ?>" href="dashboard.php?section=dashboard">📊 Dashboard</a>
    <a class="nav-item<?= $section === 'orders' ? ' active' : '' ?>" href="dashboard.php?section=orders">📦 Orders</a>
    <a class="nav-item<?= $section === 'users' ? ' active' : '' ?>" href="dashboard.php?section=users">👥 Users</a>
    <a class="nav-item<?= $section === 'products' ? ' active' : '' ?>" href="dashboard.php?section=products">🏷️ Products</a>
    <a class="nav-item<?= $section === 'categories' ? ' active' : '' ?>" href="dashboard.php?section=categories">🗂️ Categories</a>
    <a class="nav-item<?= $section === 'analytics' ? ' active' : '' ?>" href="dashboard.php?section=analytics">📈 Analytics</a>
    <a class="nav-item<?= $section === 'settings' ? ' active' : '' ?>" href="dashboard.php?section=settings">⚙️ Settings</a>
    <a class="nav-item<?= $section === 'support' ? ' active' : '' ?>" href="dashboard.php?section=support">❓ Support</a>

    <div class="sidebar-footer">
      <small>Quick access for product management, reporting, and support workflows.</small>
    </div>
  </aside>

  <main class="main">
    <?php if ($flash !== ''): ?>
      <div class="notice" id="flash-banner"><?= h($flash) ?></div>
      <script>
        setTimeout(function () {
          var banner = document.getElementById('flash-banner');
          if (banner) {
            banner.style.opacity = '0';
            banner.style.transition = 'opacity .5s ease';
            setTimeout(function () { banner.style.display = 'none'; }, 500);
          }
        }, 3500);
      </script>
    <?php endif; ?>

    <section class="page<?= $section === 'dashboard' ? ' active' : '' ?>" id="section-dashboard">
      <div class="header">
        <div>
          <div class="title">Dashboard Overview</div>
          <div class="subtitle">Management portal</div>
        </div>
        <div class="section-actions">
          <a class="btn btn-outline" href="dashboard.php?section=dashboard">Weekly</a>
          <a class="btn btn-primary" href="dashboard.php?section=products">View Products</a>
        </div>
      </div>

      <div class="metric-grid">
        <div class="metric-card"><div class="metric-label">Revenue Today</div><div class="metric-value"><?= h(money($conn, $dashStats['revenue_today'])) ?></div><div class="metric-change metric-up">Paid or delivered only</div></div>
        <div class="metric-card"><div class="metric-label">Revenue This Month</div><div class="metric-value"><?= h(money($conn, $dashStats['revenue_month'])) ?></div><div class="metric-change metric-up">Across <?= (int)$dashStats['delivered_orders'] ?> delivered orders</div></div>
        <div class="metric-card"><div class="metric-label">Pending Order Value</div><div class="metric-value"><?= h(money($conn, $dashStats['pending_value'])) ?></div><div class="metric-change metric-down">Not yet counted as revenue</div></div>
        <div class="metric-card"><div class="metric-label">Total Orders</div><div class="metric-value"><?= (int)$dashStats['total_orders'] ?></div><div class="metric-change metric-up"><?= (int)$dashStats['pending_orders'] ?> pending</div></div>
        <div class="metric-card"><div class="metric-label">Pending Orders</div><div class="metric-value"><?= (int)$dashStats['pending_orders'] ?></div></div>
        <div class="metric-card"><div class="metric-label">Processing Orders</div><div class="metric-value"><?= (int)$dashStats['processing_orders'] ?></div></div>
        <div class="metric-card"><div class="metric-label">Delivered Orders</div><div class="metric-value"><?= (int)$dashStats['delivered_orders'] ?></div></div>
        <div class="metric-card"><div class="metric-label">Cancelled Orders</div><div class="metric-value"><?= (int)$dashStats['cancelled_orders'] ?></div></div>
        <div class="metric-card"><div class="metric-label">Total Customers</div><div class="metric-value"><?= (int)$dashStats['total_customers'] ?></div><div class="metric-change metric-up">Excludes admins</div></div>
        <div class="metric-card"><div class="metric-label">Total Products</div><div class="metric-value"><?= (int)$dashStats['total_products'] ?></div></div>
        <div class="metric-card"><div class="metric-label">Low Stock Products</div><div class="metric-value"><?= (int)$dashStats['low_stock'] ?></div><div class="metric-change <?= $dashStats['low_stock'] > 0 ? 'metric-down' : 'metric-up' ?>">Below 10 units</div></div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-title">Order Status Breakdown</div>
          <div class="inv-stats" style="justify-content:space-between">
            <div><div class="inv-value" style="color:var(--amber)"><?= (int)$dashStats['pending_orders'] ?></div><div class="inv-label">Pending</div></div>
            <div><div class="inv-value" style="color:var(--blue)"><?= (int)$dashStats['processing_orders'] ?></div><div class="inv-label">Processing</div></div>
            <div><div class="inv-value" style="color:var(--green)"><?= (int)$dashStats['delivered_orders'] ?></div><div class="inv-label">Delivered</div></div>
            <div><div class="inv-value" style="color:var(--red)"><?= (int)$dashStats['cancelled_orders'] ?></div><div class="inv-label">Cancelled</div></div>
            <div><div class="inv-value"><?= (int)$dashStats['total_orders'] ?></div><div class="inv-label">Total</div></div>
          </div>
        </div>

        <div class="card">
          <div class="card-title">Inventory Health</div>
          <?php
            $inStockCount = max(0, $dashStats['total_products'] - $dashStats['low_stock']);
            $healthPct = $dashStats['total_products'] > 0 ? (int)round(($inStockCount / $dashStats['total_products']) * 100) : 100;
          ?>
          <div class="inventory-circle" style="background:conic-gradient(var(--green) 0% <?= $healthPct ?>%,var(--gray-200) <?= $healthPct ?>% 100%)"><div class="inventory-inner"><?= $healthPct ?>%</div></div>
          <div style="text-align:center;margin-top:6px"><span class="badge <?= $healthPct >= 80 ? 'badge-green' : 'badge-amber' ?>"><?= $healthPct >= 80 ? 'Optimal' : 'Needs Attention' ?></span></div>
          <div class="inv-stats">
            <div><div class="inv-value"><?= $inStockCount ?></div><div class="inv-label">Healthy Stock</div></div>
            <div style="width:1px;background:var(--gray-200)"></div>
            <div><div class="inv-value" style="color:var(--amber)"><?= (int)$dashStats['low_stock'] ?></div><div class="inv-label">Low Stock</div></div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="card-title">Latest Orders</div>
        <?php if (!$dashStats['latest_orders']): ?>
          <div style="color:var(--gray-500);font-size:12px">No orders yet.</div>
        <?php endif; ?>
        <?php foreach ($dashStats['latest_orders'] as $lo): ?>
          <div class="order-row">
            <div><div class="order-id">#ORD-<?= (int)$lo['id'] ?></div><div class="order-name"><?= h((string)$lo['shipping_name']) ?></div></div>
            <div style="font-weight:700"><?= h(money($conn, (float)$lo['total_amount'])) ?></div>
            <span class="badge <?= orderStatusBadgeClass((string)$lo['status']) ?>"><?= h(strtoupper((string)$lo['status'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="card">
        <div class="card-title">Recent Users</div>
        <?php if (!$dashStats['recent_users']): ?>
          <div style="color:var(--gray-500);font-size:12px">No users yet.</div>
        <?php endif; ?>
        <?php foreach ($dashStats['recent_users'] as $ru): ?>
          <div class="order-row">
            <div><div class="order-id"><?= h((string)($ru['full_name'] ?: $ru['username'])) ?></div><div class="order-name"><?= h((string)$ru['email']) ?></div></div>
            <span class="pill <?= normalizeRole($ru['role']) === 'admin' ? 'pill-admin' : '' ?>" style="<?= normalizeRole($ru['role']) === 'admin' ? '' : 'background:var(--gray-100);color:var(--gray-700)' ?>"><?= h(ucfirst(normalizeRole($ru['role']))) ?></span>
            <span style="font-size:11px;color:var(--gray-500)"><?= h(date('M j, Y', strtotime((string)$ru['created_at']))) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="page<?= $section === 'products' ? ' active' : '' ?>" id="section-products">
      <div class="header">
        <div>
          <div class="title">Products</div>
          <div class="subtitle">Create, update, and delete products</div>
        </div>
        <div class="section-actions">
          <a class="btn btn-outline" href="read.php">Open Classic List</a>
          <a class="btn btn-outline" href="create.php">Classic Add</a>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="card-title">Add Product</div>
        <form method="POST" action="dashboard.php?section=products">
          <input type="hidden" name="action" value="create_product">
          <div class="form-row">
            <div class="form-group" style="margin:0"><label class="form-label">Product Name</label><input class="small-input" name="product_name" placeholder="Product name" required></div>
            <div class="form-group" style="margin:0"><label class="form-label">Price</label><input class="small-input" type="number" step="0.01" min="0" name="price" placeholder="0.00" required></div>
            <div class="form-group" style="margin:0"><label class="form-label">Compare-at Price <span style="font-weight:400;color:var(--gray-500)">(optional)</span></label><input class="small-input" type="number" step="0.01" min="0" name="compare_at_price" placeholder="Leave blank for no discount"></div>
          </div>
          <div class="form-row" style="margin-top:12px">
            <div class="form-group" style="margin:0"><label class="form-label">Quantity</label><input class="small-input" type="number" min="0" name="quantity" placeholder="0" required></div>
            <div class="form-group" style="margin:0"><label class="form-label">Category</label>
              <select class="small-input" name="category_id">
                <option value="0">Uncategorized</option>
                <?php foreach ($categoriesList as $cat): ?>
                  <option value="<?= (int)$cat['id'] ?>"><?= h((string)$cat['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group" style="margin-top:12px;margin-bottom:0">
            <label class="form-label">Product Image</label>
            <div style="display:flex;gap:14px;font-size:11px;margin-bottom:6px">
              <label><input type="radio" name="image_source" value="local" checked onchange="toggleImageSource(this,'add')"> Local Image</label>
              <label><input type="radio" name="image_source" value="url" onchange="toggleImageSource(this,'add')"> Image URL</label>
            </div>
            <div id="add-image-local"><?= productImageDropdown(PRODUCT_IMAGE_DEFAULT_FILE, 'product_image', 'small-input') ?></div>
            <input id="add-image-url" class="small-input" style="display:none" type="url" name="product_image_url" placeholder="https://example.com/image.jpg">
          </div>
          <div class="form-group" style="margin-top:12px;margin-bottom:0"><button class="btn btn-primary" type="submit">Add Product</button></div>
        </form>
      </div>

      <?php if ($editProduct):
        $editIsUrl = preg_match('#^https?://#i', (string)($editProduct['image'] ?? '')) === 1;
      ?>
        <div class="card" style="margin-bottom:16px">
          <div class="card-title">Edit Product #<?= h((string)$editProduct['id']) ?></div>
          <form method="POST" action="dashboard.php?section=products">
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="id" value="<?= h((string)$editProduct['id']) ?>">
            <img class="product-image-preview" src="<?= h(productImageUrl($editProduct['image'] ?? '')) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$editProduct['product_name']) ?>">
            <div class="form-row">
              <div class="form-group" style="margin:0"><label class="form-label">Product Name</label><input class="small-input" name="product_name" value="<?= h((string)$editProduct['product_name']) ?>" required></div>
              <div class="form-group" style="margin:0"><label class="form-label">Price</label><input class="small-input" type="number" step="0.01" min="0" name="price" value="<?= h((string)$editProduct['price']) ?>" required></div>
              <div class="form-group" style="margin:0"><label class="form-label">Compare-at Price <span style="font-weight:400;color:var(--gray-500)">(optional)</span></label><input class="small-input" type="number" step="0.01" min="0" name="compare_at_price" value="<?= h($editProduct['compare_at_price'] !== null ? (string)$editProduct['compare_at_price'] : '') ?>" placeholder="Leave blank for no discount"></div>
            </div>
            <div class="form-row" style="margin-top:12px">
              <div class="form-group" style="margin:0"><label class="form-label">Quantity</label><input class="small-input" type="number" min="0" name="quantity" value="<?= h((string)$editProduct['quantity']) ?>" required></div>
              <div class="form-group" style="margin:0"><label class="form-label">Category</label>
                <select class="small-input" name="category_id">
                  <option value="0">Uncategorized</option>
                  <?php foreach ($categoriesList as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$editProduct['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>><?= h((string)$cat['category_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-group" style="margin-top:12px;margin-bottom:0">
              <label class="form-label">Product Image</label>
              <div style="display:flex;gap:14px;font-size:11px;margin-bottom:6px">
                <label><input type="radio" name="image_source" value="local" <?= $editIsUrl ? '' : 'checked' ?> onchange="toggleImageSource(this,'edit')"> Local Image</label>
                <label><input type="radio" name="image_source" value="url" <?= $editIsUrl ? 'checked' : '' ?> onchange="toggleImageSource(this,'edit')"> Image URL</label>
              </div>
              <div id="edit-image-local" style="<?= $editIsUrl ? 'display:none' : '' ?>"><?= productImageDropdown($editProduct['image'] ?? '', 'product_image', 'small-input') ?></div>
              <input id="edit-image-url" class="small-input" style="<?= $editIsUrl ? '' : 'display:none' ?>" type="url" name="product_image_url" placeholder="https://example.com/image.jpg" value="<?= $editIsUrl ? h((string)$editProduct['image']) : '' ?>">
            </div>
            <div class="image-actions">
              <button class="btn btn-primary" type="submit">Save Changes</button>
              <a class="btn btn-outline" href="dashboard.php?section=products">Cancel</a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-title">Product List</div>
        <form method="GET" action="dashboard.php" class="section-actions" style="margin-bottom:14px">
          <input type="hidden" name="section" value="products">
          <input class="small-input" style="width:200px" type="text" name="q" placeholder="Search products" value="<?= h($productSearch) ?>">
          <select class="small-input" name="filter_category">
            <option value="0">All Categories</option>
            <?php foreach ($categoriesList as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>" <?= $productCategoryFilter === (int)$cat['id'] ? 'selected' : '' ?>><?= h((string)$cat['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="small-input" name="filter_stock">
            <option value="">All Stock</option>
            <option value="low" <?= $productStockFilter === 'low' ? 'selected' : '' ?>>Low Stock (&lt;10)</option>
            <option value="out" <?= $productStockFilter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
          </select>
          <button class="btn btn-primary" type="submit">Filter</button>
          <?php if ($productSearch !== '' || $productCategoryFilter > 0 || $productStockFilter !== ''): ?>
            <a class="btn btn-outline" href="dashboard.php?section=products">Clear</a>
          <?php endif; ?>
        </form>
        <table class="table">
          <thead>
            <tr>
              <th style="width:70px">ID</th>
              <th style="width:60px">Image</th>
              <th>Product</th>
              <th style="width:120px">Category</th>
              <th style="width:110px">Price</th>
              <th style="width:110px">Stock</th>
              <th style="width:170px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($products) === 0): ?>
              <tr><td colspan="7" style="color:var(--gray-500)">No products match your filters.</td></tr>
            <?php endif; ?>

            <?php foreach ($products as $product):
              $stock = (int)$product['quantity'];
              $rowDiscountPct = productDiscountPercent((float)$product['price'], isset($product['compare_at_price']) ? (float)$product['compare_at_price'] : null);
            ?>
              <tr>
                <td><?= h((string)$product['id']) ?></td>
                <td><img class="product-thumb" src="<?= h(productImageUrl($product['image'] ?? '')) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$product['product_name']) ?>"></td>
                <td><?= h((string)$product['product_name']) ?></td>
                <td><?= h((string)($product['category_name'] ?? 'Uncategorized')) ?></td>
                <td>
                  <?= h(money($conn, (float)$product['price'])) ?>
                  <?php if ($rowDiscountPct > 0): ?>
                    <span class="badge badge-red">-<?= $rowDiscountPct ?>%</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= (int)$stock ?>
                  <?php if ($stock === 0): ?>
                    <span class="badge badge-red">OUT</span>
                  <?php elseif ($stock < 10): ?>
                    <span class="badge badge-amber">LOW</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="inline-actions">
                    <a class="link-btn" href="dashboard.php?section=products&edit_id=<?= h((string)$product['id']) ?>">Edit</a>
                    <form method="POST" action="dashboard.php?section=products" onsubmit="return confirm('Delete this product?');" style="display:inline">
                      <input type="hidden" name="action" value="delete_product">
                      <input type="hidden" name="id" value="<?= h((string)$product['id']) ?>">
                      <button class="link-btn danger" type="submit">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <script>
    function toggleImageSource(radio, prefix) {
      const useUrl = radio.value === 'url';
      document.getElementById(prefix + '-image-local').style.display = useUrl ? 'none' : '';
      document.getElementById(prefix + '-image-url').style.display = useUrl ? '' : 'none';
    }
    </script>

    <section class="page<?= $section === 'orders' ? ' active' : '' ?>" id="section-orders">
      <div class="header">
        <div>
          <div class="title">Orders</div>
          <div class="subtitle">Track fulfillment and update order status</div>
        </div>
      </div>


      <div class="card">
        <div class="card-title">All Orders</div>
        <table class="table">
          <thead>
            <tr>
              <th style="width:60px">ID</th>
              <th>Customer</th>
              <th style="width:60px">Items</th>
              <th style="width:100px">Total</th>
              <th style="width:120px">Payment</th>
              <th style="width:180px">Status</th>
              <th style="width:100px">Placed</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($orders) === 0): ?>
              <tr><td colspan="7" style="color:var(--gray-500)">No orders yet.</td></tr>
            <?php endif; ?>

            <?php foreach ($orders as $order):
              $orderId = (int)$order['id'];
              $items = $orderItemsByOrder[$orderId] ?? [];
            ?>
              <tr>
                <td>#<?= $orderId ?></td>
                <td>
                  <div style="font-weight:700"><?= h((string)$order['shipping_name']) ?></div>
                  <div style="font-size:11px;color:var(--gray-500)"><?= h((string)$order['shipping_phone']) ?> &middot; <?= h((string)$order['shipping_city']) ?></div>
                  <?php if (!empty($order['username'])): ?>
                    <div style="font-size:11px;color:var(--gray-500)">@<?= h((string)$order['username']) ?> &middot; <?= h((string)$order['email']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($order['shipping_address'])): ?>
                    <div style="font-size:11px;color:var(--gray-500)"><?= h((string)$order['shipping_address']) ?></div>
                  <?php endif; ?>
                  <?php if ($items): ?>
                    <details style="margin-top:4px">
                      <summary style="cursor:pointer;font-size:11px;color:var(--blue)">View items</summary>
                      <ul style="margin:6px 0 0 16px;padding:0;font-size:11px;color:var(--gray-700)">
                        <?php foreach ($items as $it): ?>
                          <li><?= h((string)$it['product_name']) ?> &times; <?= (int)$it['quantity'] ?> (<?= h(money($conn, (float)$it['unit_price'])) ?>)</li>
                        <?php endforeach; ?>
                      </ul>
                    </details>
                  <?php endif; ?>
                </td>
                <td><?= (int)$order['item_count'] ?></td>
                <td><?= h(money($conn, (float)$order['total_amount'])) ?></td>
                <td>
                  <form method="POST" action="dashboard.php?section=orders" style="display:flex;gap:6px;align-items:center;margin-bottom:4px">
                    <input type="hidden" name="action" value="update_payment_status">
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                    <select name="payment_status" class="small-input" style="padding:4px 6px;font-size:11px">
                      <?php foreach (['unpaid', 'paid', 'refunded'] as $payOption): ?>
                        <option value="<?= $payOption ?>" <?= (string)$order['payment_status'] === $payOption ? 'selected' : '' ?>><?= ucfirst($payOption) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="link-btn" type="submit">Save</button>
                  </form>
                  <span class="badge <?= paymentStatusBadgeClass((string)$order['payment_status']) ?>"><?= h(strtoupper((string)$order['payment_status'])) ?></span>
                  <div style="font-size:10px;color:var(--gray-500);margin-top:2px"><?= h(strtoupper((string)$order['payment_method'])) ?></div>
                </td>
                <td>
                  <form method="POST" action="dashboard.php?section=orders" style="display:flex;gap:6px;align-items:center;margin-bottom:4px">
                    <input type="hidden" name="action" value="update_order_status">
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                    <select name="status" class="small-input" style="padding:4px 6px;font-size:11px">
                      <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $statusOption): ?>
                        <option value="<?= $statusOption ?>" <?= (string)$order['status'] === $statusOption ? 'selected' : '' ?>><?= ucfirst($statusOption) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="link-btn" type="submit">Save</button>
                  </form>
                  <span class="badge <?= orderStatusBadgeClass((string)$order['status']) ?>"><?= h(strtoupper((string)$order['status'])) ?></span>
                </td>
                <td style="font-size:11px;color:var(--gray-500)"><?= h(date('M j, Y', strtotime((string)$order['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <section class="page<?= $section === 'users' ? ' active' : '' ?>" id="section-users">
      <div class="header">
        <div>
          <div class="title">Users</div>
          <div class="subtitle"><?= (int)$usersTotal ?> registered account<?= $usersTotal === 1 ? '' : 's' ?></div>
        </div>
        <form class="section-actions" method="GET" action="dashboard.php">
          <input type="hidden" name="section" value="users">
          <input class="small-input" style="width:220px" type="text" name="q" placeholder="Search name, email, username" value="<?= h($userSearch) ?>">
          <button class="btn btn-primary" type="submit">Search</button>
          <?php if ($userSearch !== ''): ?><a class="btn btn-outline" href="dashboard.php?section=users">Clear</a><?php endif; ?>
        </form>
      </div>

      <?php if ($editUser): ?>
        <div class="card" style="margin-bottom:16px">
          <div class="card-title">Edit User — <?= h((string)$editUser['username']) ?></div>
          <form method="POST" action="dashboard.php?section=users">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id" value="<?= h((string)$editUser['id']) ?>">
            <div class="form-row">
              <div class="form-group" style="margin:0"><label class="form-label">Full Name</label><input class="small-input" name="full_name" value="<?= h((string)$editUser['full_name']) ?>" required></div>
              <div class="form-group" style="margin:0"><label class="form-label">Email</label><input class="small-input" type="email" name="email" value="<?= h((string)$editUser['email']) ?>" required></div>
            </div>
            <div class="image-actions">
              <button class="btn btn-primary" type="submit">Save Changes</button>
              <a class="btn btn-outline" href="dashboard.php?section=users">Cancel</a>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-title">All Users</div>
        <table class="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th style="width:90px">Role</th>
              <th style="width:100px">Joined</th>
              <th style="width:70px">Orders</th>
              <th style="width:90px">Status</th>
              <th style="width:260px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($usersList) === 0): ?>
              <tr><td colspan="7" style="color:var(--gray-500)">No users found.</td></tr>
            <?php endif; ?>
            <?php foreach ($usersList as $u):
              $uId = (int)$u['id'];
              $uRole = normalizeRole($u['role']);
              $uStatus = (string)($u['status'] ?? 'active');
              $isSelf = $uId === $currentAdminId;
            ?>
              <tr>
                <td><?= h((string)($u['full_name'] ?: $u['username'])) ?><?= $isSelf ? ' <span style="color:var(--gray-500);font-size:11px">(you)</span>' : '' ?></td>
                <td><?= h((string)$u['email']) ?></td>
                <td><span class="pill <?= $uRole === 'admin' ? 'pill-admin' : '' ?>" style="<?= $uRole === 'admin' ? '' : 'background:var(--gray-100);color:var(--gray-700)' ?>"><?= h(ucfirst($uRole)) ?></span></td>
                <td style="font-size:11px;color:var(--gray-500)"><?= h(date('M j, Y', strtotime((string)$u['created_at']))) ?></td>
                <td><?= (int)$u['order_count'] ?></td>
                <td><span class="badge <?= $uStatus === 'active' ? 'badge-green' : 'badge-red' ?>"><?= h(strtoupper($uStatus)) ?></span></td>
                <td>
                  <div class="inline-actions" style="flex-wrap:wrap;gap:6px">
                    <a class="link-btn" href="dashboard.php?section=users&edit_user=<?= $uId ?>">Edit</a>

                    <form method="POST" action="dashboard.php?section=users" style="display:inline">
                      <input type="hidden" name="action" value="change_user_role">
                      <input type="hidden" name="id" value="<?= $uId ?>">
                      <input type="hidden" name="role" value="<?= $uRole === 'admin' ? 'user' : 'admin' ?>">
                      <button class="link-btn" type="submit" <?= $isSelf && $uRole === 'admin' ? 'disabled title="Cannot change your own role"' : '' ?>><?= $uRole === 'admin' ? 'Make User' : 'Make Admin' ?></button>
                    </form>

                    <form method="POST" action="dashboard.php?section=users" style="display:inline">
                      <input type="hidden" name="action" value="set_user_status">
                      <input type="hidden" name="id" value="<?= $uId ?>">
                      <input type="hidden" name="status" value="<?= $uStatus === 'active' ? 'inactive' : 'active' ?>">
                      <button class="link-btn" type="submit" <?= $isSelf ? 'disabled title="Cannot deactivate your own account"' : '' ?>><?= $uStatus === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                    </form>

                    <form method="POST" action="dashboard.php?section=users" onsubmit="return confirm('Permanently delete this account?');" style="display:inline">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="id" value="<?= $uId ?>">
                      <button class="link-btn danger" type="submit" <?= $isSelf ? 'disabled title="Cannot delete your own account"' : '' ?>>Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($usersTotalPages > 1): ?>
          <div class="pagination">
            <?php for ($p = 1; $p <= $usersTotalPages; $p++): ?>
              <a class="page-link<?= $p === $userPage ? ' active' : '' ?>" href="dashboard.php?section=users&page=<?= $p ?><?= $userSearch !== '' ? '&q=' . urlencode($userSearch) : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="page<?= $section === 'categories' ? ' active' : '' ?>" id="section-categories">
      <div class="header">
        <div>
          <div class="title">Categories</div>
          <div class="subtitle">Organize products into shoppable categories</div>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="card-title">Add Category</div>
        <form method="POST" action="dashboard.php?section=categories">
          <input type="hidden" name="action" value="create_category">
          <div class="form-row">
            <div class="form-group" style="margin:0"><label class="form-label">Category Name</label><input class="small-input" name="category_name" placeholder="e.g. Sports & Outdoors" required></div>
            <div class="form-group" style="margin:0;display:flex;align-items:flex-end"><button class="btn btn-primary" type="submit">Add Category</button></div>
          </div>
        </form>
      </div>

      <?php if ($editCategory): ?>
        <div class="card" style="margin-bottom:16px">
          <div class="card-title">Edit Category</div>
          <form method="POST" action="dashboard.php?section=categories">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="id" value="<?= h((string)$editCategory['id']) ?>">
            <div class="form-row">
              <div class="form-group" style="margin:0"><label class="form-label">Category Name</label><input class="small-input" name="category_name" value="<?= h((string)$editCategory['category_name']) ?>" required></div>
              <div class="form-group" style="margin:0;display:flex;align-items:flex-end;gap:8px">
                <button class="btn btn-primary" type="submit">Save Changes</button>
                <a class="btn btn-outline" href="dashboard.php?section=categories">Cancel</a>
              </div>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-title">All Categories</div>
        <table class="table">
          <thead>
            <tr>
              <th>Category</th>
              <th style="width:140px">Products</th>
              <th style="width:170px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($categoriesList) === 0): ?>
              <tr><td colspan="3" style="color:var(--gray-500)">No categories yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($categoriesList as $cat): $catCount = (int)$cat['product_count']; ?>
              <tr>
                <td><?= h((string)$cat['category_name']) ?></td>
                <td><span class="badge badge-blue"><?= $catCount ?> product<?= $catCount === 1 ? '' : 's' ?></span></td>
                <td>
                  <div class="inline-actions">
                    <a class="link-btn" href="dashboard.php?section=categories&edit_category=<?= (int)$cat['id'] ?>">Edit</a>
                    <form method="POST" action="dashboard.php?section=categories" onsubmit="return confirm('Delete this category?');" style="display:inline">
                      <input type="hidden" name="action" value="delete_category">
                      <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                      <button class="link-btn danger" type="submit" <?= $catCount > 0 ? 'disabled title="Move or delete its products first"' : '' ?>>Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="page<?= $section === 'analytics' ? ' active' : '' ?>" id="section-analytics">
      <div class="header">
        <div>
          <div class="title">Analytics</div>
          <div class="subtitle">Live sales and performance data</div>
        </div>
      </div>

      <div class="metric-grid">
        <div class="metric-card"><div class="metric-label">Sales Today</div><div class="metric-value"><?= h(money($conn, $analytics['sales_today'])) ?></div><div class="metric-change metric-up">Paid or delivered only</div></div>
        <div class="metric-card"><div class="metric-label">Sales This Month</div><div class="metric-value"><?= h(money($conn, $analytics['sales_month'])) ?></div><div class="metric-change metric-up">Paid or delivered only</div></div>
        <div class="metric-card"><div class="metric-label">Pending Order Value</div><div class="metric-value"><?= h(money($conn, $analytics['pending_value'])) ?></div><div class="metric-change metric-down">Not yet counted as revenue</div></div>
        <div class="metric-card"><div class="metric-label">Top Category</div><div class="metric-value" style="font-size:16px"><?= h((string)($analytics['top_categories'][0]['category_name'] ?? '—')) ?></div></div>
        <div class="metric-card"><div class="metric-label">Low Stock Items</div><div class="metric-value"><?= count($analytics['low_stock']) ?></div></div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-title">Revenue — Last 7 Days</div>
          <div class="chart-bars">
            <?php foreach ($analytics['revenue_chart'] as $point): ?>
              <div class="chart-bar<?= $point['value'] > 0 ? ' highlight' : '' ?>" style="height:<?= max(4, (int)round(($point['value'] / $maxRevenueChart) * 100)) ?>%" title="<?= h(money($conn, $point['value'])) ?>"></div>
            <?php endforeach; ?>
          </div>
          <div class="chart-labels">
            <?php foreach ($analytics['revenue_chart'] as $point): ?><div class="chart-day"><?= h(strtoupper($point['label'])) ?></div><?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-title">Orders — Last 7 Days</div>
          <div class="chart-bars">
            <?php foreach ($analytics['orders_chart'] as $point): ?>
              <div class="chart-bar<?= $point['value'] > 0 ? ' highlight' : '' ?>" style="height:<?= max(4, (int)round(($point['value'] / $maxOrdersChart) * 100)) ?>%" title="<?= (int)$point['value'] ?> orders"></div>
            <?php endforeach; ?>
          </div>
          <div class="chart-labels">
            <?php foreach ($analytics['orders_chart'] as $point): ?><div class="chart-day"><?= h(strtoupper($point['label'])) ?></div><?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-title">Monthly Sales — Last 6 Months</div>
          <div class="chart-bars">
            <?php foreach ($analytics['monthly_sales'] as $point): ?>
              <div class="chart-bar<?= $point['value'] > 0 ? ' highlight' : '' ?>" style="height:<?= max(4, (int)round(($point['value'] / $maxMonthlyChart) * 100)) ?>%" title="<?= h(money($conn, $point['value'])) ?>"></div>
            <?php endforeach; ?>
          </div>
          <div class="chart-labels">
            <?php foreach ($analytics['monthly_sales'] as $point): ?><div class="chart-day"><?= h(strtoupper($point['label'])) ?></div><?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-title">Best Selling Products</div>
          <?php if (!$analytics['top_products']): ?>
            <div style="color:var(--gray-500);font-size:12px">No sales yet.</div>
          <?php endif; ?>
          <?php foreach ($analytics['top_products'] as $tp): ?>
            <div class="order-row">
              <div style="display:flex;align-items:center;gap:8px">
                <img class="product-thumb" src="<?= h(productImageUrl($tp['image'] ?? '')) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$tp['product_name']) ?>">
                <div class="order-name"><?= h((string)$tp['product_name']) ?></div>
              </div>
              <div style="font-weight:700"><?= (int)$tp['sold_count'] ?> sold</div>
              <span class="badge badge-green"><?= h(money($conn, (float)$tp['revenue'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="card">
          <div class="card-title">Top Categories by Revenue</div>
          <?php if (!$analytics['top_categories']): ?>
            <div style="color:var(--gray-500);font-size:12px">No category sales yet.</div>
          <?php endif; ?>
          <?php foreach ($analytics['top_categories'] as $tc): ?>
            <div class="order-row">
              <div class="order-name"><?= h((string)$tc['category_name']) ?></div>
              <span class="badge badge-blue"><?= h(money($conn, (float)$tc['revenue'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="card-title">Customer Registrations — Last 7 Days</div>
        <div class="chart-bars">
          <?php $maxReg = max(1, ...array_column($analytics['registrations_chart'], 'value')); ?>
          <?php foreach ($analytics['registrations_chart'] as $point): ?>
            <div class="chart-bar<?= $point['value'] > 0 ? ' highlight' : '' ?>" style="height:<?= max(4, (int)round(($point['value'] / $maxReg) * 100)) ?>%" title="<?= (int)$point['value'] ?> new users"></div>
          <?php endforeach; ?>
        </div>
        <div class="chart-labels">
          <?php foreach ($analytics['registrations_chart'] as $point): ?><div class="chart-day"><?= h(strtoupper($point['label'])) ?></div><?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Low Stock Report</div>
        <table class="table">
          <thead><tr><th style="width:60px">Image</th><th>Product</th><th style="width:120px">Quantity</th></tr></thead>
          <tbody>
            <?php if (!$analytics['low_stock']): ?>
              <tr><td colspan="3" style="color:var(--gray-500)">All products are well stocked.</td></tr>
            <?php endif; ?>
            <?php foreach ($analytics['low_stock'] as $ls): ?>
              <tr>
                <td><img class="product-thumb" src="<?= h(productImageUrl($ls['image'] ?? '')) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt=""></td>
                <td><?= h((string)$ls['product_name']) ?></td>
                <td><span class="badge <?= (int)$ls['quantity'] === 0 ? 'badge-red' : 'badge-amber' ?>"><?= (int)$ls['quantity'] ?> left</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="page<?= $section === 'settings' ? ' active' : '' ?>" id="section-settings">
      <div class="header">
        <div>
          <div class="title">Settings</div>
          <div class="subtitle">Store configuration, saved to the database</div>
        </div>
      </div>

      <form method="POST" action="dashboard.php?section=settings">
        <input type="hidden" name="action" value="save_settings">

        <div class="card" style="margin-bottom:16px">
          <div class="card-title">Store Information</div>
          <div class="form-row">
            <div class="form-group" style="margin:0"><label class="form-label">Store Name</label><input class="small-input" name="store_name" value="<?= h(setting($settingsData, 'store_name')) ?>"></div>
            <div class="form-group" style="margin:0"><label class="form-label">Store Email</label><input class="small-input" type="email" name="store_email" value="<?= h(setting($settingsData, 'store_email')) ?>"></div>
          </div>
          <div class="form-row" style="margin-top:12px">
            <div class="form-group" style="margin:0"><label class="form-label">Phone</label><input class="small-input" name="store_phone" value="<?= h(setting($settingsData, 'store_phone')) ?>"></div>
            <div class="form-group" style="margin:0"><label class="form-label">Address</label><input class="small-input" name="store_address" value="<?= h(setting($settingsData, 'store_address')) ?>"></div>
          </div>
        </div>

        <div class="card" style="margin-bottom:16px">
          <div class="card-title">Commerce Settings</div>
          <div class="form-row">
            <div class="form-group" style="margin:0"><label class="form-label">Currency</label><input class="small-input" name="currency" value="<?= h(setting($settingsData, 'currency')) ?>"></div>
            <div class="form-group" style="margin:0"><label class="form-label">Tax Rate (%)</label><input class="small-input" type="number" step="0.01" min="0" name="tax_rate" value="<?= h(setting($settingsData, 'tax_rate')) ?>"></div>
          </div>
          <div class="form-row" style="margin-top:12px">
            <div class="form-group" style="margin:0"><label class="form-label">Shipping Fee</label><input class="small-input" type="number" step="0.01" min="0" name="shipping_fee" value="<?= h(setting($settingsData, 'shipping_fee')) ?>"></div>
            <div class="form-group" style="margin:0"><label class="form-label">Logo</label><?= productImageDropdown(setting($settingsData, 'logo', PRODUCT_IMAGE_DEFAULT_FILE), 'logo', 'small-input') ?></div>
          </div>
        </div>

        <div class="card" style="margin-bottom:16px">
          <div class="card-title">Homepage &amp; Footer</div>
          <div class="form-group"><label class="form-label">Homepage Banner Text</label><input class="small-input" name="homepage_banner" value="<?= h(setting($settingsData, 'homepage_banner')) ?>"></div>
          <div class="form-group" style="margin-bottom:0"><label class="form-label">Footer Text</label><input class="small-input" name="footer_text" value="<?= h(setting($settingsData, 'footer_text')) ?>"></div>
        </div>

        <button class="btn btn-primary" type="submit">Save Settings</button>
      </form>
    </section>

    <section class="page<?= $section === 'support' ? ' active' : '' ?>" id="section-support">
      <div class="header">
        <div>
          <div class="title">Support / Help Center</div>
          <div class="subtitle">Internal reference for the admin team</div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-title">About SmartShop</div>
          <p style="font-size:12px;color:var(--gray-700);line-height:1.7">SmartShop is a lightweight PHP + MySQL e-commerce platform covering the full purchase flow — browsing, cart, checkout, order tracking — alongside an admin dashboard for products, orders, users, categories, and analytics.</p>
        </div>
        <div class="card">
          <div class="card-title">System Information</div>
          <div class="order-row"><div class="order-name">Version</div><span class="badge badge-blue">1.0.0</span></div>
          <div class="order-row"><div class="order-name">PHP</div><span class="badge badge-blue"><?= h(PHP_VERSION) ?></span></div>
          <div class="order-row"><div class="order-name">Environment</div><span class="badge badge-green">Production</span></div>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px">
        <div class="card-title">Frequently Asked Questions</div>
        <details style="margin-bottom:10px"><summary style="cursor:pointer;font-weight:700;font-size:13px">How do I add a new product?</summary><p style="font-size:12px;color:var(--gray-700);margin-top:6px">Go to Products → fill in the "Add Product" form → choose an image → Add Product.</p></details>
        <details style="margin-bottom:10px"><summary style="cursor:pointer;font-weight:700;font-size:13px">Why can't I delete a category?</summary><p style="font-size:12px;color:var(--gray-700);margin-top:6px">Categories that still contain products are protected. Move or delete those products first, then delete the category.</p></details>
        <details style="margin-bottom:10px"><summary style="cursor:pointer;font-weight:700;font-size:13px">When is stock deducted from an order?</summary><p style="font-size:12px;color:var(--gray-700);margin-top:6px">Stock is only deducted the first time an order's status is changed to "Shipped", so pending orders never tie up inventory.</p></details>
        <details><summary style="cursor:pointer;font-weight:700;font-size:13px">How do I promote a user to admin?</summary><p style="font-size:12px;color:var(--gray-700);margin-top:6px">Open Users, find the account, and click "Make Admin" in the Actions column.</p></details>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-title">Admin Guide</div>
          <ul style="font-size:12px;color:var(--gray-700);line-height:2;padding-left:18px">
            <li><strong>Dashboard</strong> — real-time store overview.</li>
            <li><strong>Orders</strong> — update fulfillment status; stock deducts on "Shipped".</li>
            <li><strong>Users</strong> — search, edit, promote, deactivate, or delete accounts.</li>
            <li><strong>Categories</strong> — organize the catalog; deletion is blocked while products remain assigned.</li>
            <li><strong>Analytics</strong> — sales, top products/categories, and stock health.</li>
            <li><strong>Settings</strong> — store details, currency, tax, shipping fee, and homepage copy.</li>
          </ul>
        </div>
        <div class="card">
          <div class="card-title">Contact Support</div>
          <p style="font-size:12px;color:var(--gray-700);margin-bottom:10px">Reach the store's configured support address for anything not covered here.</p>
          <a class="btn btn-primary" href="mailto:<?= h(setting($settingsData, 'store_email', 'support@smartshop.test')) ?>">Email Support</a>
        </div>
      </div>
    </section>
  </main>
</div>

<script>
(function () {
  var menu = document.getElementById('admin-profile-menu');
  var trigger = document.getElementById('admin-profile-trigger');
  var dropdown = document.getElementById('admin-profile-dropdown');
  if (!menu || !trigger || !dropdown) {
    return;
  }

  trigger.addEventListener('click', function (event) {
    event.stopPropagation();
    var isOpen = dropdown.classList.toggle('open');
    trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  document.addEventListener('click', function () {
    dropdown.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
  });

  dropdown.addEventListener('click', function (event) {
    event.stopPropagation();
  });
})();
</script>

</body>
</html>