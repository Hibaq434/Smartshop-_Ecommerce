<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/session_helper.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// --- State routing (keeps the right tab open after POST/redirect)
$page = (string)($_GET['p'] ?? 'home');
$adminSection = (string)($_GET['section'] ?? 'dashboard');

$flash = (string)($_GET['msg'] ?? '');

// --- Guard: non-admins cannot access the admin page
if ($page === 'admin' && !isAdmin()) {
    header('Location: login.php?error=' . urlencode('Admin access required. Please login as admin.'));
    exit;
}

// --- CRUD actions (Admin > Products — admin role required)
$action = (string)($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (in_array($action, ['create_product','update_product','delete_product'], true) && !isAdmin()) {
        header('Location: login.php?error=' . urlencode('Admin access required'));
        exit;
    }
    if ($action === 'create_product') {
        $name = trim((string)($_POST['product_name'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);

        if ($name !== '') {
            $stmt = mysqli_prepare($conn, 'INSERT INTO products (product_name, price, quantity) VALUES (?, ?, ?)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sdi', $name, $price, $qty);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: index.php?p=admin&section=products&msg=' . urlencode('Product added'));
        exit;
    }

    if ($action === 'update_product') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['product_name'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);

        if ($id > 0 && $name !== '') {
            $stmt = mysqli_prepare($conn, 'UPDATE products SET product_name = ?, price = ?, quantity = ? WHERE id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sdii', $name, $price, $qty, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: index.php?p=admin&section=products&msg=' . urlencode('Product updated'));
        exit;
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
        header('Location: index.php?p=admin&section=products&msg=' . urlencode('Product deleted'));
        exit;
    }
}

// --- Data: Products for Shop + Admin
$products = [];
$res = mysqli_query($conn, 'SELECT id, product_name, price, quantity FROM products ORDER BY id DESC');
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $products[] = $row;
    }
}

$shopProducts = array_slice($products, 0, 6);
$editId = (int)($_GET['edit_id'] ?? 0);
$editProduct = null;
if ($editId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, product_name, price, quantity FROM products WHERE id = ?');
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SmartShop — E-Commerce Platform</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#2563EB;--blue-dark:#1d4ed8;--gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-300:#cbd5e1;--gray-500:#64748b;--gray-700:#334155;--gray-900:#0f172a;--green:#16a34a;--red:#dc2626;--amber:#d97706}
body{font-family:'Segoe UI',system-ui,sans-serif;color:var(--gray-900);background:#fff;font-size:14px;line-height:1.5}
.nav{display:flex;align-items:center;gap:24px;padding:0 24px;height:52px;border-bottom:1px solid var(--gray-200);background:#fff;position:sticky;top:0;z-index:100}
.nav-logo{font-size:18px;font-weight:700;color:var(--blue);letter-spacing:-0.5px;margin-right:8px}
.nav-link{font-size:13px;color:var(--gray-700);cursor:pointer;padding:4px 8px;border-radius:4px}
.nav-link:hover,.nav-link.active{color:var(--blue)}
.nav-link.active{font-weight:600}
.nav-right{margin-left:auto;display:flex;align-items:center;gap:12px}
.nav-search{display:flex;align-items:center;gap:6px;background:var(--gray-100);border-radius:20px;padding:5px 12px;font-size:12px;color:var(--gray-500);width:180px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all 0.15s}
.btn-primary{background:var(--blue);color:#fff}.btn-primary:hover{background:var(--blue-dark)}
.btn-outline{background:#fff;color:var(--gray-700);border:1px solid var(--gray-200)}.btn-outline:hover{background:var(--gray-50)}
.badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600}
.badge-blue{background:#dbeafe;color:#1e40af}
.badge-green{background:#dcfce7;color:#15803d}
.badge-red{background:#fee2e2;color:#b91c1c}
.badge-amber{background:#fef3c7;color:#92400e}
.page{display:none}.page.active{display:block}

/* ── HOMEPAGE ── */
.hero{background:linear-gradient(135deg,#f0f7ff 0%,#e8f4fd 100%);padding:48px 24px;display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center}
.hero-label{background:#dbeafe;color:#1e40af;font-size:11px;font-weight:700;padding:3px 10px;border-radius:12px;display:inline-block;margin-bottom:12px}
.hero-title{font-size:32px;font-weight:800;line-height:1.2;color:var(--gray-900);margin-bottom:12px}
.hero-title span{color:var(--blue)}
.hero-desc{font-size:14px;color:var(--gray-500);margin-bottom:20px;line-height:1.6}
.hero-btns{display:flex;gap:10px}
.hero-img{background:linear-gradient(135deg,#1e3a5f,#2563EB);border-radius:12px;height:220px;display:flex;align-items:center;justify-content:center;font-size:72px}
.section{padding:40px 24px}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.section-title{font-size:18px;font-weight:700;color:var(--gray-900)}
.view-all{font-size:12px;color:var(--blue);cursor:pointer;font-weight:500}
.cat-grid-wrap{display:grid;grid-template-columns:1fr 1fr;gap:8px;height:220px}
.cat-card{border-radius:10px;height:100%;display:flex;align-items:flex-end;padding:12px;cursor:pointer;transition:opacity 0.15s}
.cat-card:hover{opacity:0.9}
.cat-label{background:rgba(0,0,0,0.6);color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;backdrop-filter:blur(4px)}
.cat-electronics{background:linear-gradient(135deg,#1e3a5f,#2563EB)}
.cat-fashion{background:linear-gradient(135deg,#78350f,#d97706)}
.cat-home{background:linear-gradient(135deg,#14532d,#16a34a)}
.cat-beauty{background:linear-gradient(135deg,#5b21b6,#8b5cf6)}
.cat-right{display:grid;grid-template-rows:1fr 1fr;gap:8px}
.cat-small{border-radius:10px;height:100%;display:flex;align-items:flex-end;padding:10px;cursor:pointer;transition:opacity 0.15s}
.cat-small:hover{opacity:0.9}
.prod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.prod-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.prod-card{border:1px solid var(--gray-200);border-radius:10px;overflow:hidden;cursor:pointer;transition:all 0.15s;background:#fff}
.prod-card:hover{box-shadow:0 4px 12px rgba(0,0,0,0.08);transform:translateY(-1px)}
.prod-img{height:140px;display:flex;align-items:center;justify-content:center;font-size:36px;position:relative}
.prod-badge{position:absolute;top:8px;left:8px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px}
.prod-info{padding:12px}
.prod-brand{font-size:10px;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px}
.prod-name{font-size:13px;font-weight:600;margin:2px 0 4px}
.prod-price{font-size:15px;font-weight:700;color:var(--blue)}
.prod-old-price{font-size:12px;color:var(--gray-500);text-decoration:line-through;margin-left:4px}
.prod-stars{color:#f59e0b;font-size:11px;margin-bottom:2px}
.add-cart{width:100%;margin-top:8px;padding:6px;background:var(--blue);color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:background 0.15s}
.add-cart:hover{background:var(--blue-dark)}
.trending-row{display:flex;gap:12px;overflow-x:auto;padding-bottom:4px}
.trend-card{flex:0 0 160px;border:1px solid var(--gray-200);border-radius:8px;padding:10px;display:flex;align-items:center;gap:10px;cursor:pointer;background:#fff}
.trend-img{width:40px;height:40px;border-radius:6px;background:var(--gray-100);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.trend-name{font-size:12px;font-weight:600;line-height:1.3}
.trend-price{font-size:13px;font-weight:700;color:var(--blue)}
.trend-sold{font-size:10px;color:var(--gray-500)}
.sale-banner{background:linear-gradient(135deg,#1e3a5f,#2563EB);border-radius:12px;padding:28px;display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:center;margin:0 24px}
.sale-title{color:#fff;font-size:24px;font-weight:800;line-height:1.2;margin-bottom:8px}
.sale-subtitle{color:rgba(255,255,255,0.75);font-size:13px;margin-bottom:16px}
.countdown{display:flex;gap:8px}
.countdown-item{background:rgba(255,255,255,0.15);border-radius:6px;padding:6px 10px;text-align:center;color:#fff}
.countdown-num{font-size:20px;font-weight:800;display:block;line-height:1}
.countdown-label{font-size:9px;opacity:0.7;text-transform:uppercase}
.sale-img{background:rgba(255,255,255,0.1);border-radius:10px;height:160px;display:flex;align-items:center;justify-content:center;font-size:64px}
footer{background:var(--gray-900);color:#fff;padding:32px 24px 16px;margin-top:40px}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1.5fr;gap:32px;margin-bottom:24px}
.footer-logo{font-size:20px;font-weight:800;color:#fff;margin-bottom:8px}
.footer-desc{font-size:12px;color:rgba(255,255,255,0.5);line-height:1.6}
.footer-heading{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:rgba(255,255,255,0.4);margin-bottom:12px}
.footer-link{display:block;font-size:12px;color:rgba(255,255,255,0.6);margin-bottom:6px;cursor:pointer}
.footer-link:hover{color:#fff}
.footer-newsletter{display:flex;gap:8px;margin-top:8px}
.footer-input{flex:1;padding:8px 12px;border-radius:6px;border:none;background:rgba(255,255,255,0.1);color:#fff;font-size:12px}
.footer-bottom{border-top:1px solid rgba(255,255,255,0.1);padding-top:16px;font-size:11px;color:rgba(255,255,255,0.4);text-align:center}

/* ── SHOP ── */
.shop-layout{display:grid;grid-template-columns:220px 1fr;gap:24px;padding:24px}
.sidebar{background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:16px;height:fit-content}
.sidebar-title{font-size:14px;font-weight:700;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--gray-100)}
.filter-group{margin-bottom:20px}
.filter-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--gray-500);margin-bottom:8px}
.filter-item{display:flex;align-items:center;gap:8px;margin-bottom:6px;cursor:pointer;font-size:13px}
.filter-item input{accent-color:var(--blue)}
.range-labels{display:flex;justify-content:space-between;font-size:12px;color:var(--gray-500);margin-top:4px}
.range-input{width:100%;accent-color:var(--blue);margin-top:4px}
.stars-filter{display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:6px;font-size:12px}
.star-icon{color:#f59e0b}
.shop-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.shop-search{padding:7px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:240px;outline:none}
.shop-search:focus{border-color:var(--blue)}
.shop-count{font-size:13px;color:var(--gray-500);margin-top:4px}
.sort-select{padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;background:#fff;cursor:pointer;outline:none}
.pagination{display:flex;justify-content:center;gap:4px;margin-top:24px}
.page-btn{width:32px;height:32px;border-radius:6px;border:1px solid var(--gray-200);background:#fff;cursor:pointer;font-size:13px;font-weight:500;transition:all 0.15s}
.page-btn:hover{border-color:var(--blue);color:var(--blue)}
.page-btn.active{background:var(--blue);color:#fff;border-color:var(--blue)}

/* ── ADMIN ── */
.dash-layout{display:grid;grid-template-columns:200px 1fr;min-height:calc(100vh - 52px)}
.sidebar-dash{background:var(--gray-900);color:#fff;padding:20px 0;display:flex;flex-direction:column}
.dash-user{padding:0 16px 20px;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:12px}
.dash-avatar{width:40px;height:40px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;margin-bottom:8px}
.dash-username{font-size:13px;font-weight:600}
.dash-role{font-size:10px;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:0.5px}
.dash-nav-item{display:flex;align-items:center;gap:10px;padding:9px 16px;font-size:13px;color:rgba(255,255,255,0.6);cursor:pointer;transition:all 0.15s;border-left:2px solid transparent}
.dash-nav-item:hover{background:rgba(255,255,255,0.08);color:#fff}
.dash-nav-item.active{background:rgba(255,255,255,0.08);color:#fff;border-left-color:var(--blue)}
.dash-main{padding:24px;background:var(--gray-50);overflow-y:auto}
.dash-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.dash-title{font-size:20px;font-weight:700}
.dash-date{font-size:12px;color:var(--gray-500)}
.metric-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.metric-card{background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:16px}
.metric-label{font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px}
.metric-value{font-size:22px;font-weight:800;color:var(--gray-900)}
.metric-change{font-size:11px;margin-top:4px;font-weight:600}
.metric-up{color:var(--green)}.metric-down{color:var(--red)}
.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.dash-card{background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:16px;margin-bottom:0}
.dash-card-title{font-size:14px;font-weight:700;margin-bottom:16px}
.chart-bars{display:flex;align-items:flex-end;gap:4px;height:100px}
.chart-bar{flex:1;border-radius:3px 3px 0 0;background:var(--blue);opacity:0.5;min-height:8px;transition:opacity 0.2s}
.chart-bar.highlight{opacity:1}
.chart-labels{display:flex;gap:4px;margin-top:4px}
.chart-day{flex:1;text-align:center;font-size:9px;color:var(--gray-500)}
.order-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--gray-100);font-size:12px}
.order-row:last-child{border:none}
.order-id{font-weight:600;color:var(--gray-700)}
.order-name{color:var(--gray-500);font-size:11px;margin-top:1px}
.inventory-circle{width:90px;height:90px;border-radius:50%;background:conic-gradient(var(--green) 0% 92%,var(--gray-200) 92% 100%);display:flex;align-items:center;justify-content:center;margin:8px auto}
.inv-inner{width:68px;height:68px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:var(--green)}
.inv-stats{display:flex;justify-content:space-around;margin-top:16px;text-align:center}
.inv-stat-label{font-size:10px;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.3px}
.inv-stat-value{font-size:16px;font-weight:700;color:var(--gray-900)}
.dash-quick{padding:16px;border-top:1px solid rgba(255,255,255,0.1);margin-top:auto}
.dash-quick-title{font-size:10px;color:rgba(255,255,255,0.3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px}
.dash-quick-link{font-size:11px;color:rgba(255,255,255,0.5);display:block;margin-bottom:4px}

.admin-panels{display:none}
.admin-panels.active{display:block}
.table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--gray-200);border-radius:10px;overflow:hidden}
.table th{background:#0f172a;color:#fff;text-align:left;padding:10px;font-size:12px}
.table td{padding:10px;border-bottom:1px solid var(--gray-100);font-size:12px}
.table tr:last-child td{border-bottom:none}
.small-input{width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none}
.small-input:focus{border-color:var(--blue)}
.inline-actions{display:flex;gap:8px;align-items:center}
.link-btn{background:transparent;border:none;color:var(--blue);cursor:pointer;font-weight:600;font-size:12px;padding:0}
.link-btn.danger{color:var(--red)}
.notice{background:#fff;border:1px solid var(--gray-200);border-left:4px solid var(--blue);border-radius:10px;padding:12px 14px;margin-bottom:12px;font-size:12px;color:var(--gray-700)}

/* ── CHECKOUT ── */
.checkout-wrap{background:var(--gray-50);min-height:calc(100vh - 52px);padding:24px 0}
.checkout-layout{max-width:860px;margin:0 auto;padding:0 24px}
.checkout-steps{display:flex;align-items:center;margin-bottom:32px}
.step{display:flex;align-items:center;gap:8px}
.step-circle{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.step-circle.done{background:var(--blue);color:#fff}
.step-circle.active-step{background:var(--blue);color:#fff;box-shadow:0 0 0 4px rgba(37,99,235,0.2)}
.step-circle.todo{background:var(--gray-200);color:var(--gray-500)}
.step-label{font-size:12px;font-weight:600;color:var(--gray-700)}
.step-label.todo{color:var(--gray-400)}
.step-line{flex:1;height:1px;background:var(--gray-200);margin:0 12px}
.step-line.done{background:var(--blue)}
.checkout-grid{display:grid;grid-template-columns:1fr 300px;gap:24px}
.form-section{background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:20px;margin-bottom:16px}
.form-section-title{font-size:15px;font-weight:700;margin-bottom:16px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-group{margin-bottom:12px}
.form-label{font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;display:block}
.form-input{width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none;transition:border 0.15s;font-family:inherit}
.form-input:focus{border-color:var(--blue)}
.payment-option{border:1px solid var(--gray-200);border-radius:8px;padding:12px;margin-bottom:8px;cursor:pointer;display:flex;align-items:center;gap:12px;transition:all 0.15s}
.payment-option:hover{border-color:var(--blue)}
.payment-option.selected{border-color:var(--blue);background:#eff6ff}
.payment-icon{width:44px;height:28px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:800;flex-shrink:0}
.mpesa-icon{background:#00A550;color:#fff}
.card-icon{background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);font-size:16px}
.pp-icon{background:#003087;color:#fff}
.payment-radio{width:18px;height:18px;border:2px solid var(--gray-300);border-radius:50%;margin-left:auto;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:border-color 0.15s}
.payment-radio.checked{border-color:var(--blue)}
.payment-radio.checked::after{content:'';width:9px;height:9px;border-radius:50%;background:var(--blue);display:block}
.mpesa-input-group{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:12px;margin-bottom:8px}
.mpesa-row{display:flex;gap:8px;align-items:center}
.mpesa-input{flex:1;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none;font-family:inherit}
.mpesa-input:focus{border-color:#00A550}
.mpesa-hint{font-size:11px;color:var(--gray-500);margin-top:6px;font-style:italic}
.order-summary{background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:20px;height:fit-content;position:sticky;top:72px}
.order-summary-title{font-size:15px;font-weight:700;margin-bottom:16px}
.order-item{display:flex;gap:10px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--gray-100)}
.order-item-img{width:52px;height:52px;border-radius:6px;background:var(--gray-100);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.order-item-name{font-size:12px;font-weight:600;line-height:1.3}
.order-item-variant{font-size:11px;color:var(--gray-500);margin-top:2px}
.order-item-price{font-size:13px;font-weight:700;color:var(--blue);margin-top:4px}
.summary-row{display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;color:var(--gray-700)}
.summary-total{display:flex;justify-content:space-between;font-size:16px;font-weight:800;padding-top:10px;border-top:1px solid var(--gray-200);margin-top:6px}
.security-badge{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--gray-500);margin-top:10px;justify-content:center}
.money-back{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:10px 12px;font-size:11px;color:var(--gray-600);margin-top:8px;line-height:1.6}
.cart-icon{position:relative;cursor:pointer;font-size:18px}
.cart-count{position:absolute;top:-6px;right:-6px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700}

@media(max-width:768px){
  .hero{grid-template-columns:1fr}.hero-img{display:none}
  .prod-grid{grid-template-columns:repeat(2,1fr)}
  .prod-grid-3{grid-template-columns:repeat(2,1fr)}
  .metric-grid{grid-template-columns:repeat(2,1fr)}
  .dash-grid{grid-template-columns:1fr}
  .checkout-grid{grid-template-columns:1fr}
  .footer-grid{grid-template-columns:1fr 1fr}
  .shop-layout{grid-template-columns:1fr}
  .sidebar{display:none}
  .dash-layout{grid-template-columns:1fr}
  .sidebar-dash{display:none}
}
</style>
</head>
<body>

<nav class="nav">
  <span class="nav-logo">SmartShop</span>
  <span class="nav-link" data-page="home" onclick="gotoPage('home',this)">Home</span>
  <span class="nav-link" data-page="shop" onclick="gotoPage('shop',this)">Shop</span>
  <?php if (isAdmin()): ?>
  <span class="nav-link" data-page="admin" onclick="gotoPage('admin',this)">Admin</span>
  <?php endif; ?>
  <span class="nav-link" data-page="checkout" onclick="gotoPage('checkout',this)">Checkout</span>
  <div class="nav-right">
    <div class="nav-search">🔍 Search products...</div>
    <div class="cart-icon" onclick="gotoPage('checkout',document.querySelector('.nav-link[data-page=\'checkout\']'))">
      🛒<span class="cart-count">2</span>
    </div>
    <?php if (isLoggedIn()): ?>
      <div style="display:flex;align-items:center;gap:10px">
        <div style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--gray-700)">
          <div style="width:28px;height:28px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff"><?= strtoupper(substr(currentUsername(), 0, 1)) ?></div>
          <?= h(currentUsername()) ?>
          <?php if (isAdmin()): ?>
            <span style="font-size:10px;background:#dbeafe;color:#1e40af;padding:1px 7px;border-radius:10px;font-weight:700">Admin</span>
          <?php else: ?>
            <span style="font-size:10px;background:#dcfce7;color:#15803d;padding:1px 7px;border-radius:10px;font-weight:700">User</span>
          <?php endif; ?>
        </div>
        <a href="logout.php" style="font-size:12px;color:var(--red);font-weight:600;text-decoration:none;padding:5px 12px;border:1px solid var(--red);border-radius:6px;transition:all .15s" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background=''">Logout</a>
      </div>
    <?php else: ?>
      <a href="login.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#fff;background:var(--blue);padding:6px 16px;border-radius:8px;text-decoration:none">👤 Login</a>
    <?php endif; ?>
  </div>
</nav>

<?php if ($flash !== ''): ?>
  <div id="flash-banner" style="background:#dcfce7; border-bottom:1px solid #bbf7d0; color:#15803d; padding:10px 24px; font-size:13px; text-align:center; font-weight:600; transition: opacity 0.5s ease;">
    <?php echo h($flash); ?>
  </div>
  <script>
    setTimeout(function() {
      var banner = document.getElementById('flash-banner');
      if (banner) {
        banner.style.opacity = '0';
        setTimeout(function() {
          banner.style.display = 'none';
        }, 500);
      }
    }, 4000);
  </script>
<?php endif; ?>

<!-- ══════════════ HOME PAGE ══════════════ -->
<div id="home" class="page">

  <!-- Hero -->
  <div class="hero">
    <div>
      <span class="hero-label">New Arrival 2026</span>
      <h1 class="hero-title">Elevate Your Living<br><span>With Intelligence.</span></h1>
      <p class="hero-desc">Discover our curated selection of smart home gadgets and premium lifestyle electronics designed for the modern user.</p>
      <div class="hero-btns">
        <button class="btn btn-primary" onclick="gotoPage('shop',document.querySelector('.nav-link[data-page="shop"]'))">Shop Now</button>
        <button class="btn btn-outline">Watch Demo</button>
      </div>
    </div>
    <div class="hero-img">⌚</div>
  </div>

  <!-- Categories -->
  <div class="section">
    <div class="section-header">
      <span class="section-title">Shop by Category</span>
      <span class="view-all" onclick="gotoPage('shop',document.querySelector('.nav-link[data-page="shop"]'))">View All →</span>
    </div>
    <div class="cat-grid-wrap">
      <div class="cat-card cat-electronics" onclick="gotoPage('shop',document.querySelector('.nav-link[data-page="shop"]'))">
        <span class="cat-label">Electronics</span>
      </div>
      <div class="cat-right">
        <div class="cat-small cat-fashion" onclick="gotoPage('shop',document.querySelector('.nav-link[data-page="shop"]'))">
          <span class="cat-label">Fashion</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;height:100%">
          <div class="cat-small cat-home" onclick="gotoPage('shop',document.querySelector('.nav-link[data-page="shop"]'))">
            <span class="cat-label">Home</span>
          </div>
          <div class="cat-small cat-beauty" onclick="gotoPage('shop',document.querySelector('.nav-link[data-page="shop"]'))">
            <span class="cat-label">Beauty</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Featured Collections (kept as-is demo content) -->
  <div class="section" style="padding-top:0">
    <div class="section-header">
      <span class="section-title">Featured Collections</span>
      <span class="view-all">View All →</span>
    </div>
    <div class="prod-grid">
      <div class="prod-card">
        <div class="prod-img" style="background:linear-gradient(135deg,#1e1e1e,#333)">
          <span>🎧</span><span class="prod-badge badge-blue">Audio</span>
        </div>
        <div class="prod-info">
          <div class="prod-brand">Aurix</div>
          <div class="prod-name">Pro Sonic Over-Ear</div>
          <div class="prod-stars">★★★★★</div>
          <div><span class="prod-price">$299.00</span></div>
          <button class="add-cart">Add to Cart</button>
        </div>
      </div>
      <div class="prod-card">
        <div class="prod-img" style="background:linear-gradient(135deg,#f0f0e8,#ddddd0)">
          <span>⌚</span><span class="prod-badge badge-green">New</span>
        </div>
        <div class="prod-info">
          <div class="prod-brand">Soltern</div>
          <div class="prod-name">Minimalist Smart Watch V2</div>
          <div class="prod-stars">★★★★☆</div>
          <div><span class="prod-price">$189.50</span><span class="prod-old-price">$220.00</span></div>
          <button class="add-cart">Add to Cart</button>
        </div>
      </div>
      <div class="prod-card">
        <div class="prod-img" style="background:linear-gradient(135deg,#1a1a2e,#16213e)">
          <span>💻</span>
        </div>
        <div class="prod-info">
          <div class="prod-brand">SmartTab</div>
          <div class="prod-name">UltraTab Pro 12&quot; Retina</div>
          <div class="prod-stars">★★★★★</div>
          <div><span class="prod-price">$749.00</span></div>
          <button class="add-cart">Add to Cart</button>
        </div>
      </div>
      <div class="prod-card">
        <div class="prod-img" style="background:linear-gradient(135deg,#2d1b00,#5c3a00)">
          <span>📷</span><span class="prod-badge badge-amber">Sale</span>
        </div>
        <div class="prod-info">
          <div class="prod-brand">Lumia</div>
          <div class="prod-name">Classic X Mirrorless</div>
          <div class="prod-stars">★★★★☆</div>
          <div><span class="prod-price">$1,150.00</span></div>
          <button class="add-cart">Add to Cart</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Trending Now -->
  <div class="section" style="padding-top:0">
    <div class="section-header"><span class="section-title">🔥 Trending Now</span></div>
    <div class="trending-row">
      <div class="trend-card">
        <div class="trend-img">🎧</div>
        <div><div class="trend-name">Wasten Studio</div><div class="trend-price">$45.00</div><div class="trend-sold">1.2k Sold</div></div>
      </div>
      <div class="trend-card">
        <div class="trend-img">🎵</div>
        <div><div class="trend-name">Studio Buds Pro</div><div class="trend-price">$79.00</div><div class="trend-sold">860 Sold</div></div>
      </div>
      <div class="trend-card">
        <div class="trend-img">🖨️</div>
        <div><div class="trend-name">Smart Print</div><div class="trend-price">$38.00</div><div class="trend-sold">0.4k Sold</div></div>
      </div>
      <div class="trend-card">
        <div class="trend-img">💻</div>
        <div><div class="trend-name">AirBook Pro</div><div class="trend-price">$1,299.00</div><div class="trend-sold">100 Sold</div></div>
      </div>
    </div>
  </div>

  <!-- Sale Banner -->
  <div class="section" style="padding-top:0;padding-left:0;padding-right:0">
    <div class="sale-banner">
      <div>
        <div style="font-size:11px;color:rgba(255,255,255,0.6);margin-bottom:6px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Season Sale</div>
        <div class="sale-title">Up to 50% Off</div>
        <div class="sale-subtitle">Refresh your collection with exclusive discounts on premium electronics and fashion. Limited time only.</div>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
          <button class="btn" style="background:#fff;color:var(--blue);font-weight:700">Claim Offer</button>
          <div>
            <div style="font-size:10px;color:rgba(255,255,255,0.5);margin-bottom:4px;letter-spacing:0.5px">ENDS IN</div>
            <div class="countdown">
              <div class="countdown-item"><span class="countdown-num" id="cd-h">04</span><span class="countdown-label">HRS</span></div>
              <div class="countdown-item"><span class="countdown-num" id="cd-m">22</span><span class="countdown-label">MIN</span></div>
              <div class="countdown-item"><span class="countdown-num" id="cd-s">15</span><span class="countdown-label">SEC</span></div>
            </div>
          </div>
        </div>
      </div>
      <div class="sale-img">🛍️</div>
    </div>
  </div>

  <!-- Footer -->
  <footer>
    <div class="footer-grid">
      <div>
        <div class="footer-logo">SmartShop</div>
        <div class="footer-desc">The future of frictionless commerce and inventory management. Premium experiences delivered daily.</div>
      </div>
      <div>
        <div class="footer-heading">Quick Links</div>
        <span class="footer-link">Track Order</span>
        <span class="footer-link">Store Locator</span>
        <span class="footer-link">Gift Cards</span>
        <span class="footer-link">Wholesale</span>
      </div>
      <div>
        <div class="footer-heading">Support</div>
        <span class="footer-link">Help Center</span>
        <span class="footer-link">Shipping Policy</span>
        <span class="footer-link">Return &amp; Refund</span>
        <span class="footer-link">Privacy Policy</span>
      </div>
      <div>
        <div class="footer-heading">Newsletter</div>
        <div class="footer-desc">Subscribe to get special offers and stay updated.</div>
        <div class="footer-newsletter">
          <input class="footer-input" placeholder="Your email address"/>
          <button class="btn btn-primary" style="padding:8px 14px;font-size:12px">Join</button>
        </div>
      </div>
    </div>
    <div class="footer-bottom">© 2024 SmartShop Ecosystem. All rights reserved.</div>
  </footer>
</div>

<!-- ══════════════ SHOP PAGE ══════════════ -->
<div id="shop" class="page">
  <div class="shop-layout">
    <!-- Sidebar Filters (UI only) -->
    <div class="sidebar">
      <div class="sidebar-title">Filters</div>
      <div class="filter-group">
        <div class="filter-label">Categories</div>
        <label class="filter-item"><input type="radio" name="cat" checked> Electronics</label>
        <label class="filter-item"><input type="radio" name="cat"> Smart Home</label>
        <label class="filter-item"><input type="radio" name="cat"> Audio &amp; Sound</label>
        <label class="filter-item"><input type="radio" name="cat"> Wearables</label>
      </div>
      <div class="filter-group">
        <div class="filter-label">Price Range</div>
        <input type="range" class="range-input" min="0" max="2000" value="1200"
          oninput="document.getElementById('price-val').textContent='$'+this.value">
        <div class="range-labels"><span>$0</span><span id="price-val">$1,200</span></div>
      </div>
      <div class="filter-group">
        <div class="filter-label">Ratings</div>
        <label class="stars-filter"><input type="radio" name="stars"> <span class="star-icon">★★★★★</span> &amp; Up</label>
        <label class="stars-filter"><input type="radio" name="stars"> <span class="star-icon">★★★★</span> &amp; Up</label>
        <label class="stars-filter"><input type="radio" name="stars" checked> <span class="star-icon">★★★</span> &amp; Up</label>
      </div>
      <div class="filter-group">
        <div class="filter-label">Brands</div>
        <label class="filter-item"><input type="checkbox" checked> SmartShop</label>
        <label class="filter-item"><input type="checkbox"> Nebula</label>
        <label class="filter-item"><input type="checkbox"> Quantix</label>
        <label class="filter-item"><input type="checkbox"> Titan Pro</label>
        <label class="filter-item"><input type="checkbox"> CJ Wearables</label>
      </div>
    </div>

    <!-- Product Grid (from DB) -->
    <div>
      <div class="shop-header">
        <div>
          <input class="shop-search" placeholder="🔍 Search products..." type="text" disabled>
          <div class="shop-count">Showing <?php echo count($shopProducts); ?> of <?php echo count($products); ?> products</div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:12px;color:var(--gray-500)">Sort by:</span>
          <select class="sort-select" disabled>
            <option>Popularity</option>
            <option>Price: Low to High</option>
            <option>Price: High to Low</option>
            <option>Rating</option>
          </select>
        </div>
      </div>

      <div class="prod-grid-3">
        <?php if (count($shopProducts) === 0): ?>
          <div class="notice">No products yet. Add some in Admin → Products.</div>
        <?php endif; ?>

        <?php foreach ($shopProducts as $p): ?>
          <div class="prod-card">
            <div class="prod-img" style="background:linear-gradient(135deg,#1e3a5f,#2563EB)">
              <span><?php echo h(productEmoji((int)$p['id'])); ?></span>
              <?php if ((int)$p['quantity'] <= 5): ?>
                <span class="prod-badge badge-amber">Low</span>
              <?php else: ?>
                <span class="prod-badge badge-green">In Stock</span>
              <?php endif; ?>
            </div>
            <div class="prod-info">
              <div class="prod-name"><?php echo h((string)$p['product_name']); ?></div>
              <div style="font-size:11px;color:var(--gray-500);margin-bottom:4px">Qty: <?php echo h((string)$p['quantity']); ?></div>
              <div class="prod-stars">★★★★★ <span style="color:var(--gray-500);font-size:11px">4.8</span></div>
              <div><span class="prod-price">$<?php echo h(number_format((float)$p['price'], 2)); ?></span></div>
              <button class="add-cart" onclick="gotoPage('checkout',document.querySelector('.nav-link[data-page="checkout"]'))">Add to Cart</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="pagination">
        <button class="page-btn active">1</button>
        <button class="page-btn" disabled>2</button>
        <button class="page-btn" disabled>3</button>
        <button class="page-btn" disabled>›</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════ ADMIN DASHBOARD (admin role only) ══════════════ -->
<?php if (isAdmin()): ?>
<div id="admin" class="page">
  <div class="dash-layout">
    <!-- Sidebar -->
    <div class="sidebar-dash">
      <div class="dash-user">
        <div class="dash-avatar"><?= strtoupper(substr(currentUsername(), 0, 2)) ?></div>
        <div class="dash-username"><?= h(currentFullName()) ?></div>
        <div class="dash-role">System Admin</div>
      </div>
      <div class="dash-nav-item" data-section="dashboard">📊 &nbsp;Dashboard</div>
      <div class="dash-nav-item" data-section="orders">📦 &nbsp;Orders</div>
      <div class="dash-nav-item" data-section="users">👥 &nbsp;Users</div>
      <div class="dash-nav-item" data-section="products">🏷️ &nbsp;Products</div>
      <div class="dash-nav-item" data-section="analytics">📈 &nbsp;Analytics</div>
      <div class="dash-nav-item" data-section="settings">⚙️ &nbsp;Settings</div>
      <div class="dash-nav-item" data-section="support">❓ &nbsp;Support</div>
      <div class="dash-quick">
        <div class="dash-quick-title">Quick Links</div>
        <span class="dash-quick-link">Support Center</span>
        <span class="dash-quick-link" style="line-height:1.6;margin-top:4px;display:block">Building the future of frictionless commerce and inventory management.</span>
      </div>
    </div>

    <!-- Main Content -->
    <div class="dash-main">

      <?php if ($flash !== ''): ?>
        <div class="notice"><?php echo h($flash); ?></div>
      <?php endif; ?>

      <!-- Dashboard panel (original design) -->
      <div class="admin-panels" id="admin-dashboard">
        <div class="dash-header">
          <div>
            <div class="dash-title">Dashboard Overview</div>
            <div class="dash-date">Management portal &nbsp;·&nbsp; October 24, 2023</div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="btn btn-outline" style="font-size:12px">Weekly ▾</button>
            <button class="btn btn-primary" style="font-size:12px">View All Orders</button>
          </div>
        </div>

        <!-- Metric Cards -->
        <div class="metric-grid">
          <div class="metric-card">
            <div class="metric-label">📈 Total Sales</div>
            <div class="metric-value">$124,592</div>
            <div class="metric-change metric-up">▲ +12.5% vs last month</div>
          </div>
          <div class="metric-card">
            <div class="metric-label">📋 Orders Count</div>
            <div class="metric-value">1,284</div>
            <div class="metric-change metric-up">▲ +8.2% this week</div>
          </div>
          <div class="metric-card">
            <div class="metric-label">👥 Active Users</div>
            <div class="metric-value">42,891</div>
            <div class="metric-change metric-down">▼ -2.4% vs last week</div>
          </div>
          <div class="metric-card">
            <div class="metric-label">💰 Total Revenue</div>
            <div class="metric-value">$892,100</div>
            <div class="metric-change metric-up">Goal: 94% ✓</div>
          </div>
        </div>

        <!-- Charts Row -->
        <div class="dash-grid">
          <div class="dash-card">
            <div class="dash-card-title">
              Revenue Forecast
              <span style="font-size:11px;color:var(--gray-500);font-weight:400;margin-left:6px">Monthly comparison for fiscal 2023</span>
            </div>
            <div class="chart-bars">
              <div class="chart-bar" style="height:60%"></div>
              <div class="chart-bar" style="height:75%"></div>
              <div class="chart-bar highlight" style="height:90%"></div>
              <div class="chart-bar" style="height:65%"></div>
              <div class="chart-bar highlight" style="height:100%"></div>
              <div class="chart-bar" style="height:45%"></div>
              <div class="chart-bar" style="height:30%"></div>
            </div>
            <div class="chart-labels">
              <div class="chart-day">MON</div>
              <div class="chart-day">TUE</div>
              <div class="chart-day">WED</div>
              <div class="chart-day">THU</div>
              <div class="chart-day">FRI</div>
              <div class="chart-day">SAT</div>
              <div class="chart-day">SUN</div>
            </div>
          </div>

          <div class="dash-card">
            <div class="dash-card-title">Inventory Health</div>
            <div class="inventory-circle">
              <div class="inv-inner">92%</div>
            </div>
            <div style="text-align:center;margin-top:6px">
              <span class="badge badge-green">● Optimal</span>
            </div>
            <div class="inv-stats">
              <div>
                <div class="inv-stat-value">1,402</div>
                <div class="inv-stat-label">In Stock</div>
              </div>
              <div style="width:1px;background:var(--gray-200)"></div>
              <div>
                <div class="inv-stat-value" style="color:var(--amber)">14</div>
                <div class="inv-stat-label">Low Stock</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Orders -->
        <div class="dash-card" style="margin-bottom:16px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <div class="dash-card-title" style="margin:0">Recent Orders</div>
            <div style="display:flex;gap:6px;align-items:center">
              <span class="badge badge-blue">Weekly</span>
              <span style="font-size:11px;color:var(--gray-500);cursor:pointer;padding:2px 8px">Monthly</span>
            </div>
          </div>
          <div class="order-row">
            <div><div class="order-id">#ORD-9421</div><div class="order-name">Sarah Jenkins</div></div>
            <div style="font-weight:600">$240.00</div>
            <span class="badge badge-green">SHIPPED</span>
          </div>
          <div class="order-row">
            <div><div class="order-id">#ORD-8311</div><div class="order-name">Michael Chen</div></div>
            <div style="font-weight:600">$1,420.50</div>
            <span class="badge badge-amber">PENDING</span>
          </div>
          <div class="order-row">
            <div><div class="order-id">#ORD-7590</div><div class="order-name">Emma Watson</div></div>
            <div style="font-weight:600">$45.99</div>
            <span class="badge badge-red">CANCELLED</span>
          </div>
        </div>

        <!-- Featured Product -->
        <div class="dash-card">
          <div class="dash-card-title">Featured Product — SmartWatch Series</div>
          <div style="display:grid;grid-template-columns:120px 1fr;gap:20px;align-items:start">
            <div style="background:linear-gradient(135deg,#1e3a5f,#2563EB);border-radius:10px;height:120px;display:flex;align-items:center;justify-content:center;font-size:52px">⌚</div>
            <div>
              <div style="font-size:22px;font-weight:800;margin-bottom:4px">$299.00</div>
              <div style="font-size:15px;font-weight:700;margin-bottom:6px">SmartWatch Series</div>
              <div style="font-size:12px;color:var(--gray-500);margin-bottom:12px;line-height:1.6">A next-generation wearable featuring advanced health tracking, integrated SmartShop checkout, and a 5-day battery life. Current inventory leader.</div>
              <div style="display:flex;gap:16px;font-size:12px;margin-bottom:12px">
                <span><strong>Total Sales:</strong> 1,402 units</span>
                <span><strong>Rating:</strong> ⭐ 4.9</span>
              </div>
              <div style="display:flex;gap:8px">
                <button class="btn btn-primary" style="font-size:12px;padding:6px 14px">Manage Stock</button>
                <button class="btn btn-outline" style="font-size:12px;padding:6px 14px">View Details</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Products panel (CRUD) -->
      <div class="admin-panels" id="admin-products">
        <div class="dash-header">
          <div>
            <div class="dash-title">Products</div>
            <div class="dash-date">Create, update, and delete products</div>
          </div>
          <div style="display:flex;gap:8px">
            <a class="btn btn-outline" style="font-size:12px;text-decoration:none" href="read.php">Open Classic List</a>
            <a class="btn btn-outline" style="font-size:12px;text-decoration:none" href="create.php">Classic Add</a>
          </div>
        </div>

        <div class="dash-card" style="margin-bottom:16px">
          <div class="dash-card-title">Add Product</div>
          <form method="POST" action="index.php?p=admin&section=products">
            <input type="hidden" name="action" value="create_product">
            <div class="form-row">
              <div class="form-group" style="margin:0">
                <label class="form-label">Product Name</label>
                <input class="small-input" name="product_name" placeholder="Product name" required>
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Price</label>
                <input class="small-input" type="number" step="0.01" min="0" name="price" placeholder="0.00" required>
              </div>
            </div>
            <div class="form-row" style="margin-top:12px">
              <div class="form-group" style="margin:0">
                <label class="form-label">Quantity</label>
                <input class="small-input" type="number" min="0" name="quantity" placeholder="0" required>
              </div>
              <div class="form-group" style="margin:0;display:flex;align-items:flex-end">
                <button class="btn btn-primary" style="width:100%;justify-content:center">Add Product</button>
              </div>
            </div>
          </form>
        </div>

        <?php if ($editProduct): ?>
          <div class="dash-card" style="margin-bottom:16px">
            <div class="dash-card-title">Edit Product #<?php echo h((string)$editProduct['id']); ?></div>
            <form method="POST" action="index.php?p=admin&section=products">
              <input type="hidden" name="action" value="update_product">
              <input type="hidden" name="id" value="<?php echo h((string)$editProduct['id']); ?>">
              <div class="form-row">
                <div class="form-group" style="margin:0">
                  <label class="form-label">Product Name</label>
                  <input class="small-input" name="product_name" value="<?php echo h((string)$editProduct['product_name']); ?>" required>
                </div>
                <div class="form-group" style="margin:0">
                  <label class="form-label">Price</label>
                  <input class="small-input" type="number" step="0.01" min="0" name="price" value="<?php echo h((string)$editProduct['price']); ?>" required>
                </div>
              </div>
              <div class="form-row" style="margin-top:12px">
                <div class="form-group" style="margin:0">
                  <label class="form-label">Quantity</label>
                  <input class="small-input" type="number" min="0" name="quantity" value="<?php echo h((string)$editProduct['quantity']); ?>" required>
                </div>
                <div class="form-group" style="margin:0;display:flex;align-items:flex-end;gap:8px">
                  <button class="btn btn-primary" style="width:100%;justify-content:center">Save Changes</button>
                  <a class="btn btn-outline" style="text-decoration:none" href="index.php?p=admin&section=products">Cancel</a>
                </div>
              </div>
            </form>
          </div>
        <?php endif; ?>

        <div class="dash-card">
          <div class="dash-card-title">Product List</div>
          <table class="table">
            <thead>
              <tr>
                <th style="width:70px">ID</th>
                <th>Product</th>
                <th style="width:120px">Price</th>
                <th style="width:110px">Quantity</th>
                <th style="width:170px">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($products) === 0): ?>
                <tr>
                  <td colspan="5" style="color:var(--gray-500)">No products yet.</td>
                </tr>
              <?php endif; ?>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><?php echo h((string)$p['id']); ?></td>
                  <td><?php echo h((string)$p['product_name']); ?></td>
                  <td>$<?php echo h(number_format((float)$p['price'], 2)); ?></td>
                  <td><?php echo h((string)$p['quantity']); ?></td>
                  <td>
                    <div class="inline-actions">
                      <a class="link-btn" href="index.php?p=admin&section=products&edit_id=<?php echo h((string)$p['id']); ?>">Edit</a>
                      <form method="POST" action="index.php?p=admin&section=products" onsubmit="return confirm('Delete this product?');" style="display:inline">
                        <input type="hidden" name="action" value="delete_product">
                        <input type="hidden" name="id" value="<?php echo h((string)$p['id']); ?>">
                        <button class="link-btn danger" type="submit">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Placeholder panels (kept minimal) -->
      <div class="admin-panels" id="admin-orders"><div class="notice">Orders panel (UI placeholder).</div></div>
      <div class="admin-panels" id="admin-users"><div class="notice">Users panel (UI placeholder).</div></div>
      <div class="admin-panels" id="admin-analytics"><div class="notice">Analytics panel (UI placeholder).</div></div>
      <div class="admin-panels" id="admin-settings"><div class="notice">Settings panel (UI placeholder).</div></div>
      <div class="admin-panels" id="admin-support"><div class="notice">Support panel (UI placeholder).</div></div>

    </div>
  </div>
</div>
<?php else: ?>
<div id="admin" class="page"></div>
<?php endif; ?>

<!-- ══════════════ CHECKOUT PAGE ══════════════ -->
<div id="checkout" class="page">
  <div class="checkout-wrap">
    <div class="checkout-layout">

      <!-- Steps -->
      <div class="checkout-steps">
        <div class="step">
          <div class="step-circle done">1</div>
          <div class="step-label">Shipping</div>
        </div>
        <div class="step-line done"></div>
        <div class="step">
          <div class="step-circle active-step">2</div>
          <div class="step-label">Payment</div>
        </div>
        <div class="step-line"></div>
        <div class="step">
          <div class="step-circle todo">3</div>
          <div class="step-label todo">Review</div>
        </div>
      </div>

      <div class="checkout-grid">
        <!-- Left: Forms -->
        <div>
          <!-- Shipping Info -->
          <div class="form-section">
            <div class="form-section-title">Shipping Information</div>
            <div class="form-row" style="margin-bottom:12px">
              <div class="form-group" style="margin:0">
                <label class="form-label">Full Name</label>
                <input class="form-input" value="John Doe" placeholder="John Doe">
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Phone Number</label>
                <input class="form-input" value="+1 (555) 000-0000" placeholder="+1 (555) 000-0000">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Street Address</label>
              <input class="form-input" value="123 Smart Street" placeholder="123 Smart Street">
            </div>
            <div class="form-row">
              <div class="form-group" style="margin:0">
                <label class="form-label">City</label>
                <input class="form-input" value="San Francisco" placeholder="City">
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Postal Code</label>
                <input class="form-input" value="94103" placeholder="Postal Code">
              </div>
            </div>
          </div>

          <!-- Payment Method -->
          <div class="form-section">
            <div class="form-section-title">Payment Method</div>

            <div class="payment-option selected" id="pay-mpesa" onclick="selectPayment('mpesa')">
              <div class="payment-icon mpesa-icon">M-PESA</div>
              <div>
                <div style="font-size:13px;font-weight:600">M-Pesa Mobile Wallet</div>
                <div style="font-size:11px;color:var(--gray-500)">Pay securely using your phone</div>
              </div>
              <div class="payment-radio checked" id="radio-mpesa"></div>
            </div>

            <div id="mpesa-input-group" class="mpesa-input-group">
              <div style="font-size:11px;font-weight:600;color:var(--gray-500);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.4px">M-Pesa Registered Number</div>
              <div class="mpesa-row">
                <input class="mpesa-input" value="0712 345 678" placeholder="07XX XXX XXX">
                <button class="btn btn-primary" style="font-size:12px;padding:7px 14px" type="button">Send Prompt</button>
              </div>
              <div class="mpesa-hint">Enter your number and check your phone for the M-Pesa PIN prompt.</div>
            </div>

            <div class="payment-option" id="pay-card" onclick="selectPayment('card')">
              <div class="payment-icon card-icon">💳</div>
              <div>
                <div style="font-size:13px;font-weight:600">Credit or Debit Card</div>
                <div style="font-size:11px;color:var(--gray-500)">Visa, Mastercard, Amex</div>
              </div>
              <div class="payment-radio" id="radio-card"></div>
            </div>

            <div class="payment-option" id="pay-paypal" onclick="selectPayment('paypal')">
              <div class="payment-icon pp-icon">PP</div>
              <div>
                <div style="font-size:13px;font-weight:600">PayPal</div>
                <div style="font-size:11px;color:var(--gray-500)">Express checkout with PayPal</div>
              </div>
              <div class="payment-radio" id="radio-paypal"></div>
            </div>
          </div>

          <button class="btn btn-primary" style="width:100%;padding:13px;font-size:15px;justify-content:center;border-radius:8px">
            Confirm Order →
          </button>
        </div>

        <!-- Right: Order Summary (demo UI) -->
        <div class="order-summary">
          <div class="order-summary-title">Order Summary</div>

          <div class="order-item">
            <div class="order-item-img">👟</div>
            <div>
              <div class="order-item-name">Veloce Runner Xl</div>
              <div class="order-item-variant">Size: 42 | Color: Crimson</div>
              <div class="order-item-price">$120.00</div>
            </div>
          </div>

          <div class="order-item">
            <div class="order-item-img">⌚</div>
            <div>
              <div class="order-item-name">SmartWatch Series 5</div>
              <div class="order-item-variant">Edition: Sport</div>
              <div class="order-item-price">$350.00</div>
            </div>
          </div>

          <div class="summary-row"><span>Subtotal</span><span>$470.00</span></div>
          <div class="summary-row"><span>Shipping</span><span style="color:var(--green);font-weight:600">FREE</span></div>
          <div class="summary-row"><span>Tax (5%)</span><span>$23.50</span></div>
          <div class="summary-total">
            <span>Total</span>
            <span style="color:var(--blue)">$493.50</span>
          </div>

          <button class="btn btn-primary" style="width:100%;margin-top:16px;padding:12px;font-size:14px;justify-content:center;border-radius:8px">
            Confirm Order →
          </button>

          <div class="security-badge">🔒 Secure 256-bit SSL Encrypted Payment</div>
          <div class="money-back">
            🛡️ <strong>Money Back Guarantee</strong><br>
            30-day hassle-free returns on all orders.
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// ── Page navigation (also updates URL query for refresh persistence) ──
function gotoPage(id, link) {
  const url = new URL(window.location.href);
  url.searchParams.set('p', id);
  if (id !== 'admin') {
    url.searchParams.delete('section');
    url.searchParams.delete('edit_id');
  }
  window.history.replaceState({}, '', url.toString());
  showPage(id, link);
}

function showPage(id, link) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  if (link) link.classList.add('active');
  window.scrollTo(0, 0);
}

// ── Payment selection ──
function selectPayment(type) {
  ['mpesa','card','paypal'].forEach(t => {
    document.getElementById('pay-'+t).classList.remove('selected');
    document.getElementById('radio-'+t).classList.remove('checked');
  });
  document.getElementById('pay-'+type).classList.add('selected');
  document.getElementById('radio-'+type).classList.add('checked');
  const mpesaGroup = document.getElementById('mpesa-input-group');
  mpesaGroup.style.display = type === 'mpesa' ? 'block' : 'none';
}

// ── Countdown timer ──
let totalSecs = 4 * 3600 + 22 * 60 + 15;
function pad(n) { return String(n).padStart(2, '0'); }
function tick() {
  if (totalSecs <= 0) return;
  totalSecs--;
  const h = Math.floor(totalSecs / 3600);
  const m = Math.floor((totalSecs % 3600) / 60);
  const s = totalSecs % 60;
  const hEl = document.getElementById('cd-h');
  const mEl = document.getElementById('cd-m');
  const sEl = document.getElementById('cd-s');
  if (hEl) hEl.textContent = pad(h);
  if (mEl) mEl.textContent = pad(m);
  if (sEl) sEl.textContent = pad(s);
}
setInterval(tick, 1000);

// ── Dashboard section switching ──
function showAdminSection(section) {
  document.querySelectorAll('.dash-nav-item').forEach(i => i.classList.remove('active'));
  document.querySelectorAll('.admin-panels').forEach(p => p.classList.remove('active'));

  const nav = document.querySelector('.dash-nav-item[data-section="' + section + '"]');
  if (nav) nav.classList.add('active');

  const panel = document.getElementById('admin-' + section);
  if (panel) panel.classList.add('active');

  const url = new URL(window.location.href);
  url.searchParams.set('p', 'admin');
  url.searchParams.set('section', section);
  window.history.replaceState({}, '', url.toString());
}

document.querySelectorAll('.dash-nav-item').forEach(item => {
  item.addEventListener('click', function() {
    showAdminSection(this.getAttribute('data-section'));
  });
});

// ── Pagination UI ──
document.querySelectorAll('.page-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.page-btn').forEach(b => b.classList.remove('active'));
    if (this.textContent !== '›') this.classList.add('active');
  });
});

// ── Initial route from PHP/query params ──
(function initFromUrl() {
  const url = new URL(window.location.href);
  const p = url.searchParams.get('p') || <?php echo json_encode($page); ?>;

  const navLink = document.querySelector('.nav-link[data-page="' + p + '"]');
  showPage(p, navLink);

  if (p === 'admin') {
    const section = url.searchParams.get('section') || <?php echo json_encode($adminSection); ?>;
    showAdminSection(section);
  }
})();
</script>
</body>
</html>
