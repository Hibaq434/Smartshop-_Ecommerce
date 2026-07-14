<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/app.php';

header('Content-Type: application/json; charset=utf-8');

ensureNewsletterTable($conn);

$email = trim((string)($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$checkStmt = mysqli_prepare($conn, 'SELECT id FROM newsletter_subscribers WHERE email = ? LIMIT 1');
if ($checkStmt) {
    mysqli_stmt_bind_param($checkStmt, 's', $email);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);
    $exists = mysqli_stmt_num_rows($checkStmt) > 0;
    mysqli_stmt_close($checkStmt);

    if ($exists) {
        echo json_encode(['ok' => false, 'message' => 'This email is already subscribed.']);
        exit;
    }
}

$stmt = mysqli_prepare($conn, 'INSERT INTO newsletter_subscribers (email) VALUES (?)');
if (!$stmt) {
    echo json_encode(['ok' => false, 'message' => 'Database error. Please try again.']);
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $email);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Could not subscribe right now. Please try again.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Thanks for subscribing!']);
