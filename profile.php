<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

requireLogin();
ensureCoreSchema($conn);
ensureUserProfileColumns($conn);

$activePage = 'profile';
$pageTitle = 'My Profile - SmartShop';
$cartCount = getCartCount($conn);

$userId = (int)($_SESSION['user_id'] ?? 0);
$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));

    if ($fullName === '') {
        $errorMsg = 'Full name is required.';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE users SET full_name = ?, phone = ?, city = ?, address = ? WHERE id = ?'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssi', $fullName, $phone, $city, $address, $userId);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['full_name'] = $fullName;
                $successMsg = 'Your profile has been updated.';
            } else {
                $errorMsg = 'Could not update your profile. Please try again.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$stmt = mysqli_prepare($conn, 'SELECT username, email, full_name, phone, city, address, role FROM users WHERE id = ? LIMIT 1');
$profile = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $profile = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="section-head">
    <div>
      <h2>My Profile</h2>
      <small>Manage your personal information.</small>
    </div>
  </div>

  <?php if ($errorMsg !== ''): ?><div class="notice error"><?= h($errorMsg) ?></div><?php endif; ?>
  <?php if ($successMsg !== ''): ?><div class="notice"><?= h($successMsg) ?></div><?php endif; ?>

  <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">
    <div style="width:96px;height:96px;border-radius:50%;background:#dbeafe;color:#1e40af;display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:800;flex-shrink:0">
      <?= h(strtoupper(substr((string)($profile['username'] ?? '?'), 0, 1))) ?>
    </div>

    <form method="POST" action="profile.php" style="flex:1;min-width:260px;max-width:520px">
      <div class="filter-group">
        <label for="username">Username</label>
        <input class="input" id="username" type="text" value="<?= h((string)($profile['username'] ?? '')) ?>" disabled>
      </div>
      <div class="filter-group">
        <label for="email">Email</label>
        <input class="input" id="email" type="text" value="<?= h((string)($profile['email'] ?? '')) ?>" disabled>
      </div>
      <div class="filter-group">
        <label for="full_name">Full Name</label>
        <input class="input" id="full_name" name="full_name" type="text" value="<?= h((string)($profile['full_name'] ?? '')) ?>" required>
      </div>
      <div class="filter-group">
        <label for="phone">Phone</label>
        <input class="input" id="phone" name="phone" type="text" value="<?= h((string)($profile['phone'] ?? '')) ?>">
      </div>
      <div class="filter-group">
        <label for="city">City</label>
        <input class="input" id="city" name="city" type="text" value="<?= h((string)($profile['city'] ?? '')) ?>">
      </div>
      <div class="filter-group">
        <label for="address">Shipping Address</label>
        <input class="input" id="address" name="address" type="text" value="<?= h((string)($profile['address'] ?? '')) ?>">
      </div>
      <button class="btn primary" type="submit">Save Changes</button>
      <a class="btn outline" href="settings.php">Account Settings</a>
    </form>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>