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

/**
 * Normalize role values from DB/session into canonical strings.
 * Supports string roles (admin/user) and numeric roles (1/0).
 */
function normalizeRole(mixed $role): string
{
    if (is_int($role) || is_float($role) || (is_string($role) && is_numeric($role))) {
        return ((int)$role === 1) ? 'admin' : 'user';
    }

    $value = strtolower(trim((string)$role));
    if ($value === 'admin') {
        return 'admin';
    }

    return 'user';
}

function isAdmin(): bool
{
    return isLoggedIn() && normalizeRole($_SESSION['role'] ?? '') === 'admin';
}

function isUser(): bool
{
    return isLoggedIn() && normalizeRole($_SESSION['role'] ?? '') === 'user';
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
    return normalizeRole($_SESSION['role'] ?? 'user');
}

function currentRoleLabel(): string
{
    return ucfirst(currentRole());
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
