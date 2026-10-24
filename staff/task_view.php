<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Staff Task Dossier View & Progress Updates
 */

require_once __DIR__ . '/../includes/auth.php';

$taskId = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user']['id'];

if ($taskId <= 0) {
    set_flash('danger', 'Invalid task specified.');
    redirect('staff/my_tasks.php');
}

// Handle Add Update Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task_update') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $newStatus = trim($_POST['status'] ?? '');
        $message = trim($_POST['update_message'] ?? '');

        // Verify task ownership
        $stmtCheck = $pdo->prepare("SELECT id FROM tasks WHERE id = :id AND user_id = :user_id LIMIT 1");
        $stmtCheck->execute([':id' => $taskId, ':user_id' => $userId]);

        if (!$stmtCheck->fetch()) {
            set_flash('danger', 'You do not have permission to update this task.');
            redirect('staff/my_tasks.php');
        }

        if (empty($message)) {
            set_flash('danger', 'Please enter progress or diagnostic remarks.');
        } elseif (!in_array($newStatus, ['Pending', 'In Progress', 'Completed', 'Cancelled'])) {
            set_flash('danger', 'Invalid status selection.');
        } else {
            try {
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

                log_task_update($pdo, $taskId, $userId, $message, $newStatus);

                set_flash('success', 'Progress update logged successfully.');
            } catch (PDOException $e) {
                set_flash('danger', 'Database error: ' . $e->getMessage());
            }
        }
    } else {
        set_flash('danger', 'Security verification failed.');
    }
    redirect("staff/task_view.php?id={$taskId}");
}

// Fetch Task Record (Check ownership: staff can only view tasks they submitted or are assigned to)
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u.full_name as staff_name, u.email as staff_email,
               d.department_name, 
               c.category_name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        JOIN departments d ON t.department_id = d.id
        JOIN categories c ON t.category_id = c.id
        WHERE t.id = :id AND t.user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([':id' => $taskId, ':user_id' => $userId]);
    $task = $stmt->fetch();

    if (!$task) {
        set_flash('danger', 'Task not found or access restricted.');
        redirect('staff/my_tasks.php');
    }

    // Fetch chronological task updates
    $stmtUpdates = $pdo->prepare("
        SELECT tu.*, u.full_name, u.role
        FROM task_updates tu
        JOIN users u ON tu.user_id = u.id
        WHERE tu.task_id = :task_id
        ORDER BY tu.created_at ASC
    ");
    $stmtUpdates->execute([':task_id' => $taskId]);
    $updates = $stmtUpdates->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$page_title = 'Task ' . $task['task_number'];
include __DIR__ . '/../includes/header.php';
?>

<!-- Print-only header -->
<div class="print-header text-center">
    <h3>KOMFO ANOKYE TEACHING HOSPITAL</h3>
    <h4>DIRECTORATE OF INFORMATION TECHNOLOGY</h4>
    <h5>TECHNICAL TASK DOSSIER</h5>
    <div class="text-muted small">Generated on: <?= date('d M Y, h:i A') ?></div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 no-print">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="task-number-pill fs-5"><?= h($task['task_number']) ?></span>
            <?= get_status_badge($task['status']) ?>
            <?= get_priority_badge($task['priority']) ?>
        </div>
        <h4 class="fw-bold mb-0 text-dark"><?= h($task['title']) ?></h4>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>staff/my_tasks.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to My Tasks
        </a>
        <a href="<?= BASE_URL ?>staff/task_edit.php?id=<?= $task['id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit Task
        </a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Details -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Task Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="text-muted small">Hospital Department:</div>
                        <div class="fw-bold text-dark fs-6"><?= h($task['department_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">IT Category:</div>
                        <div class="fw-bold text-primary fs-6"><?= h($task['category_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Date Started:</div>
                        <div class="fw-semibold text-dark"><?= format_datetime($task['date_started']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">Date Completed:</div>
                        <div class="fw-semibold text-dark"><?= format_datetime($task['date_completed']) ?></div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold text-secondary text-uppercase small" style="letter-spacing: 0.5px;">Problem Description</h6>
                    <div class="p-3 bg-light rounded border text-dark" style="white-space: pre-wrap;"><?= h($task['problem_description']) ?></div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold text-secondary text-uppercase small" style="letter-spacing: 0.5px;">Work Carried Out / Solution Applied</h6>
                    <div class="p-3 bg-light rounded border text-dark" style="white-space: pre-wrap;"><?= h($task['work_done']) ?></div>
                </div>

                <?php if (!empty($task['remarks'])): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small" style="letter-spacing: 0.5px;">Remarks &amp; Notes</h6>
                        <div class="p-3 bg-light rounded border text-muted" style="white-space: pre-wrap;"><?= h($task['remarks']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($task['attachment'])): ?>
                    <?php 
                    $attachmentUrl = BASE_URL . 'uploads/' . rawurlencode($task['attachment']);
                    $ext = strtolower(pathinfo($task['attachment'], PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                    ?>
                    <div class="mb-2">
                        <h6 class="fw-bold text-secondary text-uppercase small" style="letter-spacing: 0.5px;">Uploaded Evidence / Document</h6>
                        <div class="p-3 bg-light rounded border d-flex flex-column align-items-start gap-2">
                            <?php if ($isImage): ?>
                                <img src="<?= $attachmentUrl ?>" alt="Task Attachment" class="img-fluid rounded border mb-2" style="max-height: 280px; object-fit: contain;">
                            <?php endif; ?>
                            <a href="<?= $attachmentUrl ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i>View / Download File (<?= strtoupper($ext) ?>)
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chronological Timeline Updates -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-success me-2"></i>Action History Timeline</h6>
                <span class="badge bg-secondary-subtle text-secondary"><?= count($updates) ?> Logged</span>
            </div>
            <div class="card-body">
                <?php if (empty($updates)): ?>
                    <div class="text-center py-4 text-muted small">
                        No updates posted on this task yet.
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($updates as $update): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="fw-bold text-dark small">
                                            <?= h($update['full_name']) ?>
                                            <span class="badge bg-light text-muted border ms-1" style="font-size: 0.7rem;">
                                                <?= ucfirst($update['role']) ?>
                                            </span>
                                        </div>
                                        <div class="text-muted small" style="font-size: 0.75rem;">
                                            <i class="bi bi-clock me-1"></i><?= format_datetime($update['created_at']) ?>
                                        </div>
                                    </div>
                                    <div class="mb-2 text-secondary" style="white-space: pre-wrap; font-size: 0.9rem;"><?= h($update['update_message']) ?></div>
                                    <div>
                                        <span class="text-muted small me-1">Status:</span>
                                        <?= get_status_badge($update['status']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Status & Add Update -->
    <div class="col-lg-4">
        <!-- Add Update Form -->
        <div class="card shadow-sm border-0 mb-4 no-print">
            <div class="card-header bg-primary text-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-chat-left-dots me-2"></i>Add Progress Remark</h6>
            </div>
            <div class="card-body p-3">
                <form action="task_view.php?id=<?= $task['id'] ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_task_update">

                    <div class="mb-3">
                        <label for="status" class="form-label small fw-semibold">Task Status <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="status" id="status" required>
                            <option value="In Progress" <?= $task['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="Completed" <?= $task['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Pending" <?= $task['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Cancelled" <?= $task['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="update_message" class="form-label small fw-semibold">Progress / Action Taken <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" name="update_message" id="update_message" rows="4" placeholder="Describe the diagnostics performed or parts tested..." required></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send-fill me-1"></i>Post Update to History
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Task Metadata Card -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar3 text-info me-2"></i>Task Timeline Metadata</h6>
            </div>
            <div class="card-body small text-secondary">
                <div class="mb-2">
                    <strong>Logged by:</strong> <?= h($task['staff_name']) ?>
                </div>
                <div class="mb-2">
                    <strong>Created at:</strong> <?= format_datetime($task['created_at']) ?>
                </div>
                <div class="mb-2">
                    <strong>Last Updated:</strong> <?= format_datetime($task['updated_at']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
