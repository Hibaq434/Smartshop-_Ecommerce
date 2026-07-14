<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/app.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true,
    'count' => getCartCount($conn),
]);
