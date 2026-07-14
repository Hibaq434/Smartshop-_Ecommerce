<?php
require_once 'dbconnect.php';

$adminPassword = password_hash('Admin@123', PASSWORD_DEFAULT);
$userPassword  = password_hash('User@123', PASSWORD_DEFAULT);

// Admin account
$stmt = mysqli_prepare($conn,
    "INSERT INTO users (username, email, password_hash, role, full_name)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
     password_hash = VALUES(password_hash),
     role = VALUES(role),
     full_name = VALUES(full_name)");

$username = "admin";
$email = "admin@smartshop.com";
$role = "admin";
$fullName = "Administrator";

mysqli_stmt_bind_param($stmt, "sssss",
    $username,
    $email,
    $adminPassword,
    $role,
    $fullName
);
mysqli_stmt_execute($stmt);

// User account
$username = "hibaq";
$email = "hibaq@example.com";
$role = "user";
$fullName = "Hibaq Abdi";

mysqli_stmt_bind_param($stmt, "sssss",
    $username,
    $email,
    $userPassword,
    $role,
    $fullName
);
mysqli_stmt_execute($stmt);

mysqli_stmt_close($stmt);

echo "Default users created successfully.<br><br>";
echo "Admin: admin / Admin@123<br>";
echo "User: Hibaq/ User@123";