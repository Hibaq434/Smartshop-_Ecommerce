<?php
declare(strict_types=1);

/**
 * Session helper — must be called AFTER session_start().
 * Included by every protected page.
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function isUser(): bool
{
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'user';
}

function currentUsername(): string
{
    return (string)($_SESSION['username'] ?? '');
}

function currentFullName(): string
{
    return (string)($_SESSION['full_name'] ?? currentUsername());
}

function currentRole(): string
{
    return (string)($_SESSION['role'] ?? '');
}

/** Redirect to login if not authenticated at all. */
function requireLogin(string $redirect = 'login.php'): void
{
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

/** Redirect to login if not an admin. */
function requireAdmin(string $msg = 'Admin access required'): void
{
    if (!isAdmin()) {
        header('Location: login.php?error=' . urlencode($msg));
        exit;
    }
}
