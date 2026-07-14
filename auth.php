<?php
// ============================================================
// auth.php — Real database authentication
// Replaces the old demo-credentials version
// This file is kept for backwards compatibility if any page
// includes it, but the main login logic lives in login.php
// ============================================================
declare(strict_types=1);
session_start();

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/session_helper.php';

// Already logged in → go home
if (isLoggedIn()) {
    header(isAdmin() ? 'Location: dashboard.php' : 'Location: index.php');
    exit;
}

// Redirect all auth requests to the proper login page
header('Location: login.php');
exit;