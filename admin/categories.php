<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * IT Task Category Management Module
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$page_title = 'IT Category Management';

// Handle Category Actions (Add, Edit, Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security token expired. Please try again.');
    } else {
        $action = $_POST['action'];

        if ($action === 'add') {
            $name = trim($_POST['category_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if (empty($name)) {
                set_flash('danger', 'Category name is required.');
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO categories (category_name, description, status, created_at) VALUES (:name, :desc, :status, NOW())");
                    $stmt->execute([':name' => $name, ':desc' => $desc, ':status' => $status]);
                    set_flash('success', "Category '{$name}' created successfully.");
                } catch (PDOException $e) {
                    set_flash('danger', 'Error adding category: Category name might already exist.');
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['category_id'] ?? 0);
            $name = trim($_POST['category_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

            if ($id <= 0 || empty($name)) {
                set_flash('danger', 'Valid category name is required.');
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE categories SET category_name = :name, description = :desc, status = :status, updated_at = NOW() WHERE id = :id");
                    $stmt->execute([':name' => $name, ':desc' => $desc, ':status' => $status, ':id' => $id]);
                    set_flash('success', "Category '{$name}' updated successfully.");
                } catch (PDOException $e) {
                    set_flash('danger', 'Error updating category: ' . $e->getMessage());
                }
            }
        } elseif ($action === 'toggle_status') {
            $id = (int)($_POST['category_id'] ?? 0);
            $currentStatus = $_POST['current_status'] ?? 'active';
            $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

            try {
                $stmt = $pdo->prepare("UPDATE categories SET status = :status, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':status' => $newStatus, ':id' => $id]);
                set_flash('success', "Category status updated to {$newStatus}.");
            } catch (PDOException $e) {
                set_flash('danger', 'Error updating status: ' . $e->getMessage());
            }
        }
    }
    redirect('admin/categories.php');
}

// Fetch categories with task counts
try {
    $stmt = $pdo->query("
        SELECT c.*, 
               COUNT(t.id) as total_tasks,
               SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
               SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM categories c
        LEFT JOIN tasks t ON c.id = t.category_id
        GROUP BY c.id
        ORDER BY c.id ASC
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">IT Task Categories</h3>
        <p class="text-muted mb-0 small">Manage problem classifications for hospital technology troubleshooting and resource tracking</p>
    </div>
    <div class="mt-3 mt-md-0">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-circle me-1"></i>Add New Category
        </button>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-tags-fill text-primary me-2"></i>Configured IT Problem Classifications</h6>
        <span class="badge bg-light text-dark border"><?= count($categories) ?> Categories</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="width: 220px;">Category Name</th>
                        <th>Description / Coverage</th>
                        <th>Incident Volume</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No categories configured.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $index => $cat): ?>
                            <tr>
                                <td class="text-muted small"><?= $index + 1 ?></td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <i class="bi bi-tag me-1 text-secondary"></i><?= h($cat['category_name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= h($cat['description'] ?: 'General technical issues in this classification.') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                        <?= (int)$cat['total_tasks'] ?> Incidents
                                    </span>
                                </td>
                                <td>
                                    <?php if ($cat['status'] === 'active'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCategoryModal"
                                                data-id="<?= $cat['id'] ?>"
                                                data-name="<?= h($cat['category_name']) ?>"
                                                data-description="<?= h($cat['description']) ?>"
                                                data-status="<?= $cat['status'] ?>"
                                                title="Edit Category">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="categories.php" class="d-inline" onsubmit="return confirm('Change active status of this category?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $cat['status'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= $cat['status'] === 'active' ? 'danger' : 'success' ?>" title="<?= $cat['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
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

<!-- Modal: Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="categories.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="addCategoryModalLabel">
                    <i class="bi bi-tag-fill text-primary me-2"></i>Add IT Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label for="addCategoryName" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="category_name" id="addCategoryName" placeholder="e.g. Telemedicine Systems" required>
                </div>

                <div class="mb-3">
                    <label for="addCategoryDesc" class="form-label fw-semibold">Description / Scope</label>
                    <textarea class="form-control" name="description" id="addCategoryDesc" rows="3" placeholder="Describe the types of technical hardware, software, or network tasks covered..."></textarea>
                </div>

                <div class="mb-3">
                    <label for="addCategoryStatus" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" id="addCategoryStatus" required>
                        <option value="active">Active (Available for task submission)</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Category -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="categories.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="category_id" id="editCatId" value="">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="editCategoryModalLabel">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit IT Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label for="editCatName" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="category_name" id="editCatName" required>
                </div>

                <div class="mb-3">
                    <label for="editCatDesc" class="form-label fw-semibold">Description / Scope</label>
                    <textarea class="form-control" name="description" id="editCatDesc" rows="3"></textarea>
                </div>

                <div class="mb-3">
                    <label for="editCatStatus" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" id="editCatStatus" required>
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
    const editCatModal = document.getElementById('editCategoryModal');
    if (editCatModal) {
        editCatModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('editCatId').value = button.getAttribute('data-id');
            document.getElementById('editCatName').value = button.getAttribute('data-name');
            document.getElementById('editCatDesc').value = button.getAttribute('data-description');
            document.getElementById('editCatStatus').value = button.getAttribute('data-status');
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
