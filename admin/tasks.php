<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Admin Master Task Registry with Search, Filters & Pagination
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$page_title = 'IT Task Management';

// Handle Quick Status Update via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_status_update') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');
        $note = trim($_POST['update_note'] ?? '');

        if ($taskId > 0 && in_array($newStatus, ['Pending', 'In Progress', 'Completed', 'Cancelled'])) {
            try {
                $completedAt = ($newStatus === 'Completed') ? date('Y-m-d H:i:s') : null;
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET status = :status, 
                        date_completed = CASE WHEN :new_status = 'Completed' AND date_completed IS NULL THEN NOW() ELSE date_completed END,
                        updated_at = NOW() 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':status' => $newStatus,
                    ':new_status' => $newStatus,
                    ':id' => $taskId
                ]);

                // Record in task_updates timeline
                $updateMsg = !empty($note) ? $note : "Status updated to {$newStatus} by Administrator.";
                log_task_update($pdo, $taskId, $_SESSION['user']['id'], $updateMsg, $newStatus);

                set_flash('success', "Task status successfully updated to {$newStatus}.");
            } catch (PDOException $e) {
                set_flash('danger', 'Error updating task status: ' . $e->getMessage());
            }
        } else {
            set_flash('danger', 'Invalid status selection.');
        }
    } else {
        set_flash('danger', 'Security verification failed.');
    }
    redirect('admin/tasks.php?' . http_build_query($_GET));
}

// Filter and Search parameters
$search = trim($_GET['search'] ?? '');
$category_id = trim($_GET['category_id'] ?? '');
$department_id = trim($_GET['department_id'] ?? '');
$user_id = trim($_GET['user_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Pagination settings
$limit = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Build WHERE clause dynamically
$where = ['1=1'];
$params = [];

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
if (!empty($user_id)) {
    $where[] = "t.user_id = :user_id";
    $params[':user_id'] = $user_id;
}
if (!empty($status)) {
    $where[] = "t.status = :status";
    $params[':status'] = $status;
}
if (!empty($priority)) {
    $where[] = "t.priority = :priority";
    $params[':priority'] = $priority;
}
if (!empty($date_from)) {
    $where[] = "DATE(t.date_started) >= :date_from";
    $params[':date_from'] = $date_from;
}
if (!empty($date_to)) {
    $where[] = "DATE(t.date_started) <= :date_to";
    $params[':date_to'] = $date_to;
}

$whereSql = implode(' AND ', $where);

// Count total matching records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $limit));

// Fetch paginated records
$dataSql = "
    SELECT t.*, u.full_name as staff_name, d.department_name, c.category_name
    FROM tasks t
    JOIN users u ON t.user_id = u.id
    JOIN departments d ON t.department_id = d.id
    JOIN categories c ON t.category_id = c.id
    WHERE {$whereSql}
    ORDER BY t.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Fetch dropdown options for filter form
$departments = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM categories ORDER BY category_name ASC")->fetchAll();
$staffList = $pdo->query("SELECT id, full_name FROM users WHERE role = 'staff' ORDER BY full_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Hospital IT Task Registry</h3>
        <p class="text-muted mb-0 small">Search, filter, inspect, and update all technical interventions logged across KATH</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export / Reports
        </a>
    </div>
</div>

<!-- Search & Multi-Filter Card -->
<div class="card shadow-sm border-0 mb-4 filter-card">
    <div class="card-body p-3">
        <form method="GET" action="tasks.php" class="row g-2 align-items-end">
            <!-- Keyword search -->
            <div class="col-md-3 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Search Keywords</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Task #, title, problem..." value="<?= h($search) ?>">
                </div>
            </div>

            <!-- Department filter -->
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

            <!-- Category filter -->
            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">IT Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= h($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Staff filter -->
            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Assigned Staff</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Staff</option>
                    <?php foreach ($staffList as $stf): ?>
                        <option value="<?= $stf['id'] ?>" <?= $user_id == $stf['id'] ? 'selected' : '' ?>>
                            <?= h($stf['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status filter -->
            <div class="col-md-1 col-sm-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <!-- Priority filter -->
            <div class="col-md-1 col-sm-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Priority</label>
                <select name="priority" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Critical" <?= $priority === 'Critical' ? 'selected' : '' ?>>Critical</option>
                    <option value="High" <?= $priority === 'High' ? 'selected' : '' ?>>High</option>
                    <option value="Medium" <?= $priority === 'Medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="Low" <?= $priority === 'Low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>

            <!-- Action buttons -->
            <div class="col-md-1 col-sm-4 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" title="Apply Filters">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
                <a href="tasks.php" class="btn btn-light btn-sm border" title="Reset Filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Task Records Card -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-bold text-dark">Tasks Found: </span>
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
                        <th>Technician</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Date Started</th>
                        <th class="text-end" style="width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                No IT tasks found matching your filter criteria.
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
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="<?= h($task['title']) ?>">
                                        <?= h($task['title']) ?>
                                    </div>
                                    <div class="text-muted small text-truncate" style="max-width: 220px;" title="<?= h($task['problem_description']) ?>">
                                        <?= h($task['problem_description']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= h($task['department_name']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-secondary small fw-medium">
                                        <?= h($task['category_name']) ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <i class="bi bi-person me-1 text-muted"></i><?= h($task['staff_name']) ?>
                                </td>
                                <td><?= get_priority_badge($task['priority']) ?></td>
                                <td><?= get_status_badge($task['status']) ?></td>
                                <td class="small text-muted">
                                    <?= format_date($task['date_started']) ?>
                                </td>
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

    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top py-3 d-flex justify-content-center">
            <nav aria-label="Task pagination">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $queryParams = $_GET;
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($totalPages, $page + 1);
                    
                    $queryParams['page'] = $prevPage;
                    $prevUrl = '?' . http_build_query($queryParams);
                    
                    $queryParams['page'] = $nextPage;
                    $nextUrl = '?' . http_build_query($queryParams);
                    ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $prevUrl ?>">Previous</a>
                    </li>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php 
                        $queryParams['page'] = $p;
                        $pageUrl = '?' . http_build_query($queryParams);
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
        <form method="POST" action="tasks.php?<?= h(http_build_query($_GET)) ?>" class="modal-content">
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
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="modalUpdateNote" class="form-label fw-semibold">Progress / Resolution Remarks</label>
                    <textarea class="form-control" name="update_note" id="modalUpdateNote" rows="3" placeholder="Enter remarks regarding this status change..."></textarea>
                    <div class="form-text small text-muted">This note will be automatically added to the task's chronological audit timeline.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save Status Change</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const quickStatusModal = document.getElementById('quickStatusModal');
    if (quickStatusModal) {
        quickStatusModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const taskId = button.getAttribute('data-task-id');
            const taskNumber = button.getAttribute('data-task-number');
            const currentStatus = button.getAttribute('data-current-status');

            document.getElementById('modalTaskId').value = taskId;
            document.getElementById('modalTaskNumber').textContent = taskNumber;
            document.getElementById('modalStatusSelect').value = currentStatus;
            document.getElementById('modalUpdateNote').value = '';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
