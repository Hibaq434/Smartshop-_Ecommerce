<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

requireLogin();
ensureCoreSchema($conn);
ensureUserProfileColumns($conn);

$activePage = 'settings';
$pageTitle = 'Account Settings - SmartShop';
$cartCount = getCartCount($conn);

$userId = (int)($_SESSION['user_id'] ?? 0);
$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = (string)($_POST['form_type'] ?? '');

    if ($formType === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $stmt = mysqli_prepare($conn, 'SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$row || !password_verify($current, (string)$row['password_hash'])) {
            $errorMsg = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $errorMsg = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $errorMsg = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $updStmt = mysqli_prepare($conn, 'UPDATE users SET password_hash = ? WHERE id = ?');
            mysqli_stmt_bind_param($updStmt, 'si', $hash, $userId);
            $ok = mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);
            $successMsg = $ok ? 'Password updated successfully.' : 'Could not update password.';
        }
    } elseif ($formType === 'contact') {
        $phone = trim((string)($_POST['phone'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        $stmt = mysqli_prepare($conn, 'UPDATE users SET phone = ?, city = ?, address = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'sssi', $phone, $city, $address, $userId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $successMsg = $ok ? 'Contact details updated.' : 'Could not update contact details.';
    }
}

$stmt = mysqli_prepare($conn, 'SELECT username, email, phone, city, address FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$profile = $res ? mysqli_fetch_assoc($res) : [];
mysqli_stmt_close($stmt);

require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="section-head">
    <div>
      <h2>Account Settings</h2>
      <small>Manage your password, phone, city, and shipping address.</small>
    </div>
  </div>

  <?php if ($errorMsg !== ''): ?><div class="notice error"><?= h($errorMsg) ?></div><?php endif; ?>
  <?php if ($successMsg !== ''): ?><div class="notice"><?= h($successMsg) ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;flex-wrap:wrap" class="settings-grid">
    <div>
      <h3 style="margin-bottom:10px">Change Password</h3>
      <form method="POST" action="settings.php">
        <input type="hidden" name="form_type" value="password">
        <div class="filter-group">
          <label for="current_password">Current Password</label>
          <input class="input" id="current_password" name="current_password" type="password" required>
        </div>
        <div class="filter-group">
          <label for="new_password">New Password</label>
          <input class="input" id="new_password" name="new_password" type="password" required>
        </div>
        <div class="filter-group">
          <label for="confirm_password">Confirm New Password</label>
          <input class="input" id="confirm_password" name="confirm_password" type="password" required>
        </div>
        <button class="btn primary" type="submit">Update Password</button>
      </form>
    </div>

    <div>
      <h3 style="margin-bottom:10px">Contact &amp; Shipping</h3>
      <form method="POST" action="settings.php">
        <input type="hidden" name="form_type" value="contact">
        <div class="filter-group">
          <label>Username</label>
          <input class="input" type="text" value="<?= h((string)($profile['username'] ?? '')) ?>" disabled>
        </div>
        <div class="filter-group">
          <label>Email</label>
          <input class="input" type="text" value="<?= h((string)($profile['email'] ?? '')) ?>" disabled>
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
        <button class="btn primary" type="submit">Save Contact Details</button>
      </form>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>