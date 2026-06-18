<?php
/**
 * auth.php — Session Management Guard
 * Include at the top of every protected page.
 * Handles: login check, session timeout, role-based access.
 */

session_start();

define('SESSION_TIMEOUT', 1800); // 30 minutes

// 1. Check if logged in
if (!isset($_SESSION['emp_id'])) {
    header("Location: login.php?reason=not_logged_in");
    exit;
}

// 2. Session timeout check
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header("Location: login.php?reason=timeout");
    exit;
}

// 3. Refresh activity timestamp
$_SESSION['login_time'] = time();

/**
 * Call this on pages that require a specific role.
 * e.g. require_role('admin');
 */
function require_role(string $required_role): void {
    $hierarchy = ['employee' => 1, 'manager' => 2, 'admin' => 3];
    $user_level = $hierarchy[$_SESSION['emp_role']] ?? 0;
    $req_level  = $hierarchy[$required_role] ?? 99;

    if ($user_level < $req_level) {
        header("Location: dashboard.php?error=unauthorized");
        exit;
    }
}
?>