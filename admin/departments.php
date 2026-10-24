<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Hospital Department Management Module
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$page_title = 'Department Management';
$errors = [];

// Handle Department Actions (Add, Edit, Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security verification failed.');
    } else {
        $action = $_POST['action'];

        if ($action === 'add') {
            $name = trim($_POST['department_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if (empty($name)) {
                set_flash('danger', 'Department name is required.');
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO departments (department_name, description, status, created_at) VALUES (:name, :desc, :status, NOW())");
                    $stmt->execute([':name' => $name, ':desc' => $desc, ':status' => $status]);
                    set_flash('success', "Department '{$name}' created successfully.");
                } catch (PDOException $e) {
                    set_flash('danger', 'Error adding department: Name may already exist.');
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['department_id'] ?? 0);
            $name = trim($_POST['department_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if ($id <= 0 || empty($name)) {
                set_flash('danger', 'Valid department name is required.');
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE departments SET department_name = :name, description = :desc, status = :status, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([':name' => $name, ':desc' => $desc, ':status' => $status, ':id' => $id]);
                    set_flash('success', "Department '{$name}' updated successfully.");
                } catch (PDOException $e) {
                    set_flash('danger', 'Error updating department: ' . $e->getMessage());
                }
            }
        } elseif ($action === 'toggle_status') {
            $id = (int)($_POST['department_id'] ?? 0);
            $currentStatus = $_POST['current_status'] ?? 'active';
            $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

            try {
                $stmt = $pdo->prepare("UPDATE departments SET status = :status, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':status' => $newStatus, ':id' => $id]);
                set_flash('success', "Department status updated to {$newStatus}.");
            } catch (PDOException $e) {
                set_flash('danger', 'Error changing status: ' . $e->getMessage());
            }
        }
    }
    redirect('admin/departments.php');
}

// Fetch departments with associated task count metrics
try {
    $stmt = $pdo->query("
        SELECT d.*, 
               COUNT(t.id) as total_tasks,
               SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
               SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
               SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM departments d
        LEFT JOIN tasks t ON d.id = t.department_id
        GROUP BY d.id
        ORDER BY d.department_name ASC
    ");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Hospital Department Management</h3>
        <p class="text-muted mb-0 small">Add, customize, and monitor IT incident demand across clinical directorates and administrative units</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
            <i class="bi bi-plus-circle me-1"></i>Add New Department
        </button>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-building-gear text-primary me-2"></i>Directorates &amp; Units</h6>
        <span class="badge bg-light text-dark border"><?= count($departments) ?> Departments</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 240px;">Department Name</th>
                        <th>Scope / Description</th>
                        <th>IT Support Demand</th>
                        <th>Status</th>
                        <th>Last Modified</th>
                        <th class="text-end" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No hospital departments registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <i class="bi bi-hospital me-1 text-secondary"></i><?= h($dept['department_name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= h($dept['description'] ?: 'No specific description provided.') ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary rounded-pill"><?= (int)$dept['total_tasks'] ?> Tasks</span>
                                        <span class="text-muted small" style="font-size: 0.75rem;">
                                            (<?= (int)$dept['completed_tasks'] ?> resolved, <?= (int)$dept['pending_tasks'] ?> pending)
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($dept['status'] === 'active'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= format_date($dept['updated_at']) ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editDepartmentModal"
                                                data-id="<?= $dept['id'] ?>"
                                                data-name="<?= h($dept['department_name']) ?>"
                                                data-description="<?= h($dept['description']) ?>"
                                                data-status="<?= $dept['status'] ?>"
                                                title="Edit Department">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="departments.php" class="d-inline" onsubmit="return confirm('Change active status of this department?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="department_id" value="<?= $dept['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $dept['status'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= $dept['status'] === 'active' ? 'danger' : 'success' ?>" title="<?= $dept['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                                <i class="bi bi-power"></i>
                                            </button>
                                        </form>
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

<!-- Modal: Add Department -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="departments.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="addDepartmentModalLabel">
                    <i class="bi bi-building-add text-primary me-2"></i>Add Hospital Department
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label for="add_department_name" class="form-label fw-semibold">Department Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="department_name" id="add_department_name" placeholder="e.g. Pediatric Intensive Care" required>
                </div>

                <div class="mb-3">
                    <label for="add_description" class="form-label fw-semibold">Description / Scope</label>
                    <textarea class="form-control" name="description" id="add_description" rows="3" placeholder="Briefly specify locations, clinical units, or offices covered..."></textarea>
                </div>

                <div class="mb-3">
                    <label for="add_status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" id="add_status" required>
                        <option value="active">Active (Available for task submission)</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Department -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="departments.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="department_id" id="editDeptId" value="">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="editDepartmentModalLabel">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit Department
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label for="editDeptName" class="form-label fw-semibold">Department Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="department_name" id="editDeptName" required>
                </div>

                <div class="mb-3">
                    <label for="editDeptDesc" class="form-label fw-semibold">Description / Scope</label>
                    <textarea class="form-control" name="description" id="editDeptDesc" rows="3"></textarea>
                </div>

                <div class="mb-3">
                    <label for="editDeptStatus" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" id="editDeptStatus" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editDeptModal = document.getElementById('editDepartmentModal');
    if (editDeptModal) {
        editDeptModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('editDeptId').value = button.getAttribute('data-id');
            document.getElementById('editDeptName').value = button.getAttribute('data-name');
            document.getElementById('editDeptDesc').value = button.getAttribute('data-description');
            document.getElementById('editDeptStatus').value = button.getAttribute('data-status');
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
