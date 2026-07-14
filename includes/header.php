<?php
$pageTitle = $pageTitle ?? 'SmartShop';
$activePage = $activePage ?? '';
$cartCount = $cartCount ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?></title>
  <link rel="stylesheet" href="assets/css/store.css">
</head>
<body>
<?php require __DIR__ . '/navbar.php'; ?>
<main class="page-wrap">
