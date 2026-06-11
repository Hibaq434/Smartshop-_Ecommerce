<?php
$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "smart_ecommerce"
);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>