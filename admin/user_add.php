<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Add New IT Staff / User Interface
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$page_title = 'Add New IT Staff';
$errors = [];

$full_name = '';
$username = '';
$email = '';
$phone = '';
$department_id = '';
$role = 'staff';
$status = 'active';

// Handle Add Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $role = in_array($_POST['role'] ?? '', ['admin', 'staff']) ? $_POST['role'] : 'staff';
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($username)) $errors[] = 'Username is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters long.';
        if ($password !== $confirm_password) $errors[] = 'Password confirmation does not match.';

        // Check unique username and email
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
            $stmt->execute([':username' => $username, ':email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = 'The username or email address is already registered in the system.';
            }
        }

        if (empty($errors)) {
            try {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (full_name, username, email, phone, password, role, department_id, status, created_at)
                    VALUES (:full_name, :username, :email, :phone, :password, :role, :department_id, :status, NOW())
                ");
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':username' => $username,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':password' => $password_hash,
                    ':role' => $role,
                    ':department_id' => $department_id,
                    ':status' => $status
                ]);

                set_flash('success', "Staff member '{$full_name}' added successfully.");
                redirect('admin/users.php');
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch departments for select
$departments = $pdo->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Register IT Support Staff</h3>
        <p class="text-muted mb-0 small">Create a new login profile for hospital technical staff or system administrator</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Directory
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Please review the form:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-person-fill-add text-primary me-2"></i>New Account Details</h6>
    </div>
    <div class="card-body p-4">
        <form action="user_add.php" method="POST" autocomplete="off">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= h($full_name) ?>" placeholder="e.g. Samuel Adjei" required>
                </div>

                <div class="col-md-6">
                    <label for="username" class="form-label fw-semibold">System Username <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= h($username) ?>" placeholder="e.g. sadjei or tech1" required>
                    <div class="form-text small">Used for portal login. Must be unique.</div>
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= h($email) ?>" placeholder="e.g. sadjei@kath.gov.gh" required>
                </div>

                <div class="col-md-6">
                    <label for="phone" class="form-label fw-semibold">Phone / Extension</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= h($phone) ?>" placeholder="e.g. +233 24 555 0192">
                </div>

                <div class="col-md-4">
                    <label for="department_id" class="form-label fw-semibold">Primary Unit / Department</label>
                    <select class="form-select" id="department_id" name="department_id">
                        <option value="">-- Directorate of IT (Default) --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= $department_id == $dept['id'] ? 'selected' : '' ?>>
                                <?= h($dept['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="role" class="form-label fw-semibold">System Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>IT Support Staff (Technician)</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>System Administrator (Supervisor)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="status" class="form-label fw-semibold">Account Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (Can Login)</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive (Disabled)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="password" class="form-label fw-semibold">Login Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="At least 6 characters" minlength="6" required>
                </div>

                <div class="col-md-6">
                    <label for="confirm_password" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Re-enter password" minlength="6" required>
                </div>

                <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-person-check-fill me-1"></i>Create Staff Account
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
