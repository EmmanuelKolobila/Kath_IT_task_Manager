<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Staff My Tasks Interface (Search, Filter, Manage personal tasks)
 */

require_once __DIR__ . '/../includes/auth.php';

$page_title = 'My Submitted IT Tasks';
$userId = (int)$_SESSION['user']['id'];

// Handle Quick Status Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_status_update') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $note = trim($_POST['update_note'] ?? '');

        // Verify task ownership
        $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmt->execute([':id' => $taskId, ':user_id' => $userId]);
        if ($stmt->fetch() && in_array($newStatus, ['Pending', 'In Progress', 'Completed', 'Cancelled'])) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET status = :status,
                        date_completed = CASE WHEN :new_status = 'Completed' AND date_completed IS NULL THEN NOW() ELSE date_completed END,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([':status' => $newStatus, ':new_status' => $newStatus, ':id' => $taskId]);

                $logMsg = !empty($note) ? $note : "Technician updated status to {$newStatus}.";
                log_task_update($pdo, $taskId, $userId, $logMsg, $newStatus);

                set_flash('success', "Task status updated to {$newStatus}.");
            } catch (PDOException $e) {
                set_flash('danger', 'Error updating task: ' . $e->getMessage());
            }
        } else {
            set_flash('danger', 'Unauthorized or invalid status selection.');
        }
    } else {
        set_flash('danger', 'Security verification failed.');
    }
    redirect('staff/my_tasks.php?' . http_build_query($_GET));
}

// Search and filter parameters
$search = trim($_GET['search'] ?? '');
$category_id = trim($_GET['category_id'] ?? '');
$department_id = trim($_GET['department_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');

$limit = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = ['t.user_id = :user_id'];
$params = [':user_id' => $userId];

if (!empty($search)) {
    $where[] = "(t.task_number LIKE :search OR t.title LIKE :search OR t.problem_description LIKE :search)";
    $params[':search'] = "%{$search}%";
}
if (!empty($category_id)) {
    $where[] = "t.category_id = :category_id";
    $params[':category_id'] = $category_id;
}
if (!empty($department_id)) {
    $where[] = "t.department_id = :department_id";
    $params[':department_id'] = $department_id;
}
if (!empty($status)) {
    $where[] = "t.status = :status";
    $params[':status'] = $status;
}
if (!empty($priority)) {
    $where[] = "t.priority = :priority";
    $params[':priority'] = $priority;
}

$whereSql = implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch page records
$dataSql = "
    SELECT t.*, d.department_name, c.category_name
    FROM tasks t
    JOIN departments d ON t.department_id = d.id
    JOIN categories c ON t.category_id = c.id
    WHERE {$whereSql}
    ORDER BY t.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Fetch filter options
$departments = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM categories ORDER BY category_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">My Technical Support Tasks</h3>
        <p class="text-muted mb-0 small">Review, update, and search all tasks and IT problems handled by your account</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="<?= BASE_URL ?>staff/submit_task.php" class="btn btn-success btn-sm shadow-sm">
            <i class="bi bi-plus-circle-fill me-1"></i>Submit New Task
        </a>
    </div>
</div>

<!-- Filters Card -->
<div class="card shadow-sm border-0 mb-4 filter-card">
    <div class="card-body p-3">
        <form method="GET" action="my_tasks.php" class="row g-2 align-items-end">
            <div class="col-md-4 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Search Keywords</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Task #, title, description..." value="<?= h($search) ?>">
                </div>
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $department_id == $dept['id'] ? 'selected' : '' ?>>
                            <?= h($dept['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= h($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-sm-3">
                <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div class="col-md-2 col-sm-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
                <a href="my_tasks.php" class="btn btn-light btn-sm border" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Task Records Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-bold text-dark">Tasks Recorded: </span>
            <span class="badge bg-primary rounded-pill"><?= number_format($totalRecords) ?></span>
        </div>
        <div class="text-muted small">
            Showing Page <?= $page ?> of <?= $totalPages ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 130px;">Task #</th>
                        <th>Title &amp; Problem</th>
                        <th>Department</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date Started</th>
                        <th class="text-end" style="width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                No tasks found matching your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <a href="task_view.php?id=<?= $task['id'] ?>" class="task-number-pill">
                                        <?= h($task['task_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 240px;" title="<?= h($task['title']) ?>">
                                        <?= h($task['title']) ?>
                                    </div>
                                    <div class="text-muted small text-truncate" style="max-width: 240px;" title="<?= h($task['problem_description']) ?>">
                                        <?= h($task['problem_description']) ?>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= h($task['department_name']) ?></span></td>
                                <td class="small text-secondary fw-medium"><?= h($task['category_name']) ?></td>
                                <td><?= get_priority_badge($task['priority']) ?></td>
                                <td><?= get_status_badge($task['status']) ?></td>
                                <td class="small text-muted"><?= format_date($task['date_started']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="task_view.php?id=<?= $task['id'] ?>" class="btn btn-outline-secondary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="task_edit.php?id=<?= $task['id'] ?>" class="btn btn-outline-primary" title="Edit Task">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-success" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#quickStatusModal" 
                                                data-task-id="<?= $task['id'] ?>" 
                                                data-task-number="<?= h($task['task_number']) ?>" 
                                                data-current-status="<?= h($task['status']) ?>"
                                                title="Quick Status Update">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top py-3 d-flex justify-content-center">
            <nav aria-label="Task pagination">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $q = $_GET;
                    $q['page'] = max(1, $page - 1);
                    $prevUrl = '?' . http_build_query($q);
                    $q['page'] = min($totalPages, $page + 1);
                    $nextUrl = '?' . http_build_query($q);
                    ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $prevUrl ?>">Previous</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php 
                        $q['page'] = $p;
                        $pageUrl = '?' . http_build_query($q);
                        ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $pageUrl ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $nextUrl ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Status Update Modal -->
<div class="modal fade" id="quickStatusModal" tabindex="-1" aria-labelledby="quickStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="my_tasks.php?<?= h(http_build_query($_GET)) ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="quick_status_update">
            <input type="hidden" name="task_id" id="modalTaskId" value="">

            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="quickStatusModalLabel">
                    <i class="bi bi-arrow-repeat text-primary me-2"></i>Update Task Status
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-semibold">Task Reference</label>
                    <div id="modalTaskNumber" class="task-number-pill fs-6"></div>
                </div>

                <div class="mb-3">
                    <label for="modalStatusSelect" class="form-label fw-semibold">New Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" id="modalStatusSelect" required>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Pending">Pending</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="modalUpdateNote" class="form-label fw-semibold">Progress Remarks</label>
                    <textarea class="form-control" name="update_note" id="modalUpdateNote" rows="3" placeholder="Describe progress, parts replaced, or completion notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const quickModal = document.getElementById('quickStatusModal');
    if (quickModal) {
        quickModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('modalTaskId').value = button.getAttribute('data-task-id');
            document.getElementById('modalTaskNumber').textContent = button.getAttribute('data-task-number');
            document.getElementById('modalStatusSelect').value = button.getAttribute('data-current-status');
            document.getElementById('modalUpdateNote').value = '';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
