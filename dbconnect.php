<?php

declare(strict_types=1);

// Central DB connection used by all pages.
// NOTE: This file must not echo/print anything.

mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_connect('localhost', 'root', '', 'smart_ecommerce');

if (!$conn) {
    http_response_code(500);
    exit('Database connection failed.');
}

mysqli_set_charset($conn, 'utf8mb4');

?>