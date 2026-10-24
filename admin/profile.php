<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Administrator Profile & Security Settings
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$page_title = 'Admin Profile & Security';

$userId = (int)$_SESSION['user']['id'];
$errors = [];
$success = '';

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address is required.';

        // Check email uniqueness
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
            $stmt->execute([':email' => $email, ':id' => $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'This email address is already in use by another user.';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = :full_name, email = :email, phone = :phone, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':full_name' => $full_name, ':email' => $email, ':phone' => $phone, ':id' => $userId]);
                
                $_SESSION['user']['full_name'] = $full_name;
                $_SESSION['user']['email'] = $email;
                set_flash('success', 'Profile information updated successfully.');
                redirect('admin/profile.php');
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Handle Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password)) $errors[] = 'Current password is required.';
        if (strlen($new_password) < 6) $errors[] = 'New password must be at least 6 characters.';
        if ($new_password !== $confirm_password) $errors[] = 'New password confirmation does not match.';

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($current_password, $hash)) {
                $errors[] = 'Your current password is incorrect.';
            } else {
                try {
                    $newHash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([':password' => $newHash, ':id' => $userId]);
                    set_flash('success', 'Your password has been successfully changed.');
                    redirect('admin/profile.php');
                } catch (PDOException $e) {
                    $errors[] = 'Database error updating password: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch current user fresh data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$adminUser = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Administrator Account &amp; Security</h3>
        <p class="text-muted mb-0 small">Manage your supervisory profile details, contact info, and security credentials</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Please resolve the following:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Profile Card -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-person-circle text-primary me-2"></i>Personal Profile Information</h6>
            </div>
            <div class="card-body p-4">
                <form action="profile.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" class="form-control bg-light" value="<?= h($adminUser['username']) ?>" disabled>
                        <div class="form-text small text-muted">Usernames cannot be modified.</div>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" id="full_name" value="<?= h($adminUser['full_name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="email" value="<?= h($adminUser['email']) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="form-label fw-semibold">Phone / Extension</label>
                        <input type="text" class="form-control" name="phone" id="phone" value="<?= h($adminUser['phone']) ?>">
                    </div>

                    <button type="submit" class="btn btn-primary fw-semibold">
                        <i class="bi bi-save me-1"></i>Save Profile Details
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Change Password -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100" id="password">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-lock text-warning me-2"></i>Change Security Password</h6>
            </div>
            <div class="card-body p-4">
                <form action="profile.php#password" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="current_password" id="current_password" placeholder="Enter existing password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="new_password" id="new_password" placeholder="At least 6 characters" minlength="6" required>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Re-type new password" minlength="6" required>
                    </div>

                    <button type="submit" class="btn btn-warning fw-semibold">
                        <i class="bi bi-key-fill me-1"></i>Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
