<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Staff Management Module
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$page_title = 'Staff Management';

// Handle Staff Activation / Deactivation toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security verification failed.');
    } else {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId === (int)$_SESSION['user']['id']) {
            set_flash('warning', 'You cannot alter or deactivate your own active administrator account.');
        } else {
            if ($_POST['action'] === 'toggle_status') {
                $targetStatus = ($_POST['current_status'] === 'active') ? 'inactive' : 'active';
                try {
                    $stmt = $pdo->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([':status' => $targetStatus, ':id' => $userId]);
                    set_flash('success', "Staff account status changed to {$targetStatus}.");
                } catch (PDOException $e) {
                    set_flash('danger', 'Database error: ' . $e->getMessage());
                }
            } elseif ($_POST['action'] === 'reset_password') {
                $newPassword = $_POST['new_password'] ?? '';
                if (strlen($newPassword) < 6) {
                    set_flash('danger', 'Password must be at least 6 characters long.');
                } else {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    try {
                        $stmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
                        $stmt->execute([':password' => $hash, ':id' => $userId]);
                        set_flash('success', 'Staff password reset successfully.');
                    } catch (PDOException $e) {
                        set_flash('danger', 'Error resetting password: ' . $e->getMessage());
                    }
                }
            }
        }
    }
    redirect('admin/users.php');
}

// Fetch Staff members with department name and task count
try {
    $stmt = $pdo->query("
        SELECT u.*, 
               d.department_name,
               COUNT(t.id) as total_tasks,
               SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
               SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
               SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN tasks t ON u.id = t.user_id
        GROUP BY u.id
        ORDER BY u.role ASC, u.full_name ASC
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">IT Support Staff Management</h3>
        <p class="text-muted mb-0 small">Manage technician credentials, hospital department allocations, and operational workloads</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="<?= BASE_URL ?>admin/user_add.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-person-plus-fill me-1"></i>Add New IT Staff
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill text-primary me-2"></i>System User Directory</h6>
        <span class="badge bg-light text-dark border"><?= count($users) ?> Total Accounts</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Staff Name &amp; Contact</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Workload</th>
                        <th>Account Status</th>
                        <th>Registered</th>
                        <th class="text-end" style="width: 170px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No staff records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-2 fw-bold" style="width: 38px; height: 38px;">
                                            <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= h($u['full_name']) ?></div>
                                            <div class="text-muted small" style="font-size: 0.78rem;">
                                                <i class="bi bi-envelope me-1"></i><?= h($u['email']) ?>
                                                <?php if (!empty($u['phone'])): ?>
                                                    &bull; <i class="bi bi-telephone ms-1 me-1"></i><?= h($u['phone']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= h($u['username']) ?></code></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Administrator</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">IT Staff</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= h($u['department_name'] ?? 'Unassigned') ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= (int)$u['total_tasks'] ?> total tasks</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">
                                        <span class="text-success"><?= (int)$u['completed_tasks'] ?> done</span> &bull; 
                                        <span class="text-primary"><?= (int)$u['in_progress_tasks'] ?> active</span> &bull; 
                                        <span class="text-warning"><?= (int)$u['pending_tasks'] ?> pending</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= format_date($u['created_at']) ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="user_edit.php?id=<?= $u['id'] ?>" class="btn btn-outline-primary" title="Edit Staff Details">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Reset Password Button -->
                                        <button type="button" class="btn btn-outline-warning" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#resetPasswordModal" 
                                                data-user-id="<?= $u['id'] ?>" 
                                                data-user-name="<?= h($u['full_name']) ?>"
                                                title="Reset Password">
                                            <i class="bi bi-key"></i>
                                        </button>

                                        <!-- Toggle Status (Cannot deactivate self) -->
                                        <?php if ((int)$u['id'] !== (int)$_SESSION['user']['id']): ?>
                                            <form method="POST" action="users.php" class="d-inline" onsubmit="return confirm('Change status for this account?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                                                <button type="submit" class="btn btn-outline-<?= $u['status'] === 'active' ? 'danger' : 'success' ?>" title="<?= $u['status'] === 'active' ? 'Deactivate Account' : 'Activate Account' ?>">
                                                    <i class="bi bi-power"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Password Reset Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="users.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetModalUserId" value="">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="resetPasswordModalLabel">
                    <i class="bi bi-key-fill text-warning me-2"></i>Reset Staff Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-semibold">Staff Member</label>
                    <div id="resetModalUserName" class="fw-bold fs-6 text-dark"></div>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="new_password" id="new_password" placeholder="Enter at least 6 characters" minlength="6" required>
                    <div class="form-text small text-muted">Use a secure password. The user will use this password to log in.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning btn-sm fw-semibold">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const resetModal = document.getElementById('resetPasswordModal');
    if (resetModal) {
        resetModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');

            document.getElementById('resetModalUserId').value = userId;
            document.getElementById('resetModalUserName').textContent = userName;
            document.getElementById('new_password').value = '';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
