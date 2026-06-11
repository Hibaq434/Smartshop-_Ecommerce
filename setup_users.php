<?php
$conn = new mysqli("localhost", "root", "", "smart_ecommerce");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$adminPassword = password_hash("Admin@123", PASSWORD_DEFAULT);
$userPassword = password_hash("User@123", PASSWORD_DEFAULT);

$conn->query("
INSERT INTO users (username, email, password_hash, role, full_name)
VALUES
('admin', 'admin@example.com', '$adminPassword', 'admin', 'System Admin'),
('john', 'john@example.com', '$userPassword', 'user', 'John Doe')
");

echo "Users created successfully!";
?>