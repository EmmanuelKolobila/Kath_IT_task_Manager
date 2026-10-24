<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Edit Staff Profile & Permissions
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    set_flash('danger', 'Invalid user specified.');
    redirect('admin/users.php');
}

// Fetch user record
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    set_flash('danger', 'User account not found.');
    redirect('admin/users.php');
}

$page_title = 'Edit Staff: ' . $targetUser['full_name'];
$errors = [];

// Handle Edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $role = in_array($_POST['role'] ?? '', ['admin', 'staff']) ? $_POST['role'] : $targetUser['role'];
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : $targetUser['status'];
        $new_password = $_POST['new_password'] ?? '';

        // Prevent self demotion or deactivation
        if ($userId === (int)$_SESSION['user']['id']) {
            $role = 'admin';
            $status = 'active';
        }

        // Validation
        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($username)) $errors[] = 'Username is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (!empty($new_password) && strlen($new_password) < 6) $errors[] = 'New password must be at least 6 characters.';

        // Check unique username & email excluding current user
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id LIMIT 1");
            $stmt->execute([':username' => $username, ':email' => $email, ':id' => $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'The username or email is already taken by another account.';
            }
        }

        if (empty($errors)) {
            try {
                if (!empty($new_password)) {
                    $hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        UPDATE users SET 
                            full_name = :full_name,
                            username = :username,
                            email = :email,
                            phone = :phone,
                            department_id = :department_id,
                            role = :role,
                            status = :status,
                            password = :password,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':username' => $username,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':department_id' => $department_id,
                        ':role' => $role,
                        ':status' => $status,
                        ':password' => $hash,
                        ':id' => $userId
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET 
                            full_name = :full_name,
                            username = :username,
                            email = :email,
                            phone = :phone,
                            department_id = :department_id,
                            role = :role,
                            status = :status,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':username' => $username,
                        ':email' => $email,
                        ':phone' => $phone,
                        ':department_id' => $department_id,
                        ':role' => $role,
                        ':status' => $status,
                        ':id' => $userId
                    ]);
                }

                set_flash('success', "Staff account '{$full_name}' updated successfully.");
                redirect('admin/users.php');
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch departments
$departments = $pdo->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Edit IT Staff Profile</h3>
        <p class="text-muted mb-0 small">Update contact details, role permissions, or credentials for <strong><?= h($targetUser['full_name']) ?></strong></p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Directory
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Please resolve the issues below:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-person-gear text-primary me-2"></i>Account Information</h6>
    </div>
    <div class="card-body p-4">
        <form action="user_edit.php?id=<?= $targetUser['id'] ?>" method="POST" autocomplete="off">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= h($_POST['full_name'] ?? $targetUser['full_name']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="username" class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= h($_POST['username'] ?? $targetUser['username']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= h($_POST['email'] ?? $targetUser['email']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label fw-semibold">Phone / Extension</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= h($_POST['phone'] ?? $targetUser['phone']) ?>">
                </div>

                <div class="col-md-4">
                    <label for="department_id" class="form-label fw-semibold">Assigned Unit</label>
                    <select class="form-select" id="department_id" name="department_id">
                        <option value="">-- Directorate of IT --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= (($_POST['department_id'] ?? $targetUser['department_id']) == $dept['id']) ? 'selected' : '' ?>>
                                <?= h($dept['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="role" class="form-label fw-semibold">System Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="role" name="role" <?= ($userId === (int)$_SESSION['user']['id']) ? 'disabled' : '' ?>>
                        <option value="staff" <?= (($_POST['role'] ?? $targetUser['role']) === 'staff') ? 'selected' : '' ?>>IT Support Staff</option>
                        <option value="admin" <?= (($_POST['role'] ?? $targetUser['role']) === 'admin') ? 'selected' : '' ?>>System Administrator</option>
                    </select>
                    <?php if ($userId === (int)$_SESSION['user']['id']): ?>
                        <div class="form-text small text-muted">You cannot demote your own active account.</div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="status" class="form-label fw-semibold">Account Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" <?= ($userId === (int)$_SESSION['user']['id']) ? 'disabled' : '' ?>>
                        <option value="active" <?= (($_POST['status'] ?? $targetUser['status']) === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_POST['status'] ?? $targetUser['status']) === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <?php if ($userId === (int)$_SESSION['user']['id']): ?>
                        <div class="form-text small text-muted">You cannot deactivate your own account.</div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary"><i class="bi bi-shield-lock me-2"></i>Change Password (Optional)</h6>
                    <p class="text-muted small mb-2">Leave blank to keep the current password unchanged.</p>
                </div>

                <div class="col-md-6">
                    <label for="new_password" class="form-label fw-semibold">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password (optional)" minlength="6">
                </div>

                <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-light border px-4">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
