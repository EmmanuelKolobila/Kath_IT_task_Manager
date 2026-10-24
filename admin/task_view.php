<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Admin Task Details Dossier & Progress Timeline
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$taskId = (int)($_GET['id'] ?? 0);
if ($taskId <= 0) {
    set_flash('danger', 'Invalid task requested.');
    redirect('admin/tasks.php');
}

// Handle Add Update / Status Change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task_update') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $newStatus = trim($_POST['status'] ?? '');
        $message = trim($_POST['update_message'] ?? '');

        if (empty($message)) {
            set_flash('danger', 'Please provide an update remark or notes on work performed.');
        } elseif (!in_array($newStatus, ['Pending', 'In Progress', 'Completed', 'Cancelled'])) {
            set_flash('danger', 'Invalid status selected.');
        } else {
            try {
                // Update task status and completion time if completed
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

                // Insert into task_updates history
                log_task_update($pdo, $taskId, $_SESSION['user']['id'], $message, $newStatus);

                set_flash('success', 'Task update added to timeline successfully.');
            } catch (PDOException $e) {
                set_flash('danger', 'Database error saving update: ' . $e->getMessage());
            }
        }
    } else {
        set_flash('danger', 'Security verification token invalid.');
    }
    redirect("admin/task_view.php?id={$taskId}");
}

// Fetch Task Details
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u.full_name as staff_name, u.email as staff_email, u.phone as staff_phone,
               d.department_name, 
               c.category_name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        JOIN departments d ON t.department_id = d.id
        JOIN categories c ON t.category_id = c.id
        WHERE t.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        set_flash('danger', 'Task not found.');
        redirect('admin/tasks.php');
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
    <h5>TECHNICAL INCIDENT &amp; TASK DOSSIER</h5>
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
        <a href="<?= BASE_URL ?>admin/tasks.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Tasks
        </a>
        <a href="<?= BASE_URL ?>admin/task_edit.php?id=<?= $task['id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit Task
        </a>
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer me-1"></i>Print Dossier
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Incident Dossier Details -->
    <div class="col-lg-8">
        <!-- Main Details Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Task Dossier Overview</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="text-muted small">Hospital Department:</div>
                        <div class="fw-bold text-dark fs-6"><?= h($task['department_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small">IT Problem Category:</div>
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
                    <h6 class="fw-bold text-secondary text-uppercase small" style="letter-spacing: 0.5px;">Work Carried Out / Resolution Action</h6>
                    <div class="p-3 bg-light rounded border text-dark" style="white-space: pre-wrap;"><?= h($task['work_done']) ?></div>
                </div>

                <?php if (!empty($task['remarks'])): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small" style="letter-spacing: 0.5px;">Initial Remarks</h6>
                        <div class="p-3 bg-light rounded border text-muted" style="white-space: pre-wrap;"><?= h($task['remarks']) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Optional Attachment Section -->
                <?php if (!empty($task['attachment'])): ?>
                    <?php 
                    $attachmentUrl = BASE_URL . 'uploads/' . rawurlencode($task['attachment']);
                    $ext = strtolower(pathinfo($task['attachment'], PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                    ?>
                    <div class="mb-2">
                        <h6 class="fw-bold text-secondary text-uppercase small" style="letter-spacing: 0.5px;">Attached Documentation / Photo Evidence</h6>
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
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-success me-2"></i>Action History &amp; Progress Timeline</h6>
                <span class="badge bg-secondary-subtle text-secondary"><?= count($updates) ?> Recorded</span>
            </div>
            <div class="card-body">
                <?php if (empty($updates)): ?>
                    <div class="text-center py-4 text-muted small">
                        No progress updates logged on this ticket yet.
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
                                        <span class="text-muted small me-1">Status set to:</span>
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

    <!-- Right Column: Assigned Staff Info & Add Update Form -->
    <div class="col-lg-4">
        <!-- Assigned Technician Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-person-badge text-info me-2"></i>Assigned Technician</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px; font-size: 1.2rem; font-weight: bold;">
                        <?= strtoupper(substr($task['staff_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="fw-bold text-dark"><?= h($task['staff_name']) ?></div>
                        <div class="text-muted small">IT Support Staff</div>
                    </div>
                </div>
                <div class="small text-secondary mb-1">
                    <i class="bi bi-envelope me-2 text-muted"></i><?= h($task['staff_email']) ?>
                </div>
                <?php if (!empty($task['staff_phone'])): ?>
                    <div class="small text-secondary mb-1">
                        <i class="bi bi-telephone me-2 text-muted"></i><?= h($task['staff_phone']) ?>
                    </div>
                <?php endif; ?>
                <div class="small text-secondary">
                    <i class="bi bi-calendar-check me-2 text-muted"></i>Logged on: <?= format_datetime($task['created_at']) ?>
                </div>
            </div>
        </div>

        <!-- Add Progress Update Form (No-print) -->
        <div class="card shadow-sm border-0 no-print">
            <div class="card-header bg-primary text-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-chat-left-text me-2"></i>Post Progress Update</h6>
            </div>
            <div class="card-body p-3">
                <form action="task_view.php?id=<?= $task['id'] ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_task_update">

                    <div class="mb-3">
                        <label for="status" class="form-label small fw-semibold">Update Task Status <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="status" id="status" required>
                            <option value="In Progress" <?= $task['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="Completed" <?= $task['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Pending" <?= $task['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Cancelled" <?= $task['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="update_message" class="form-label small fw-semibold">Action Remarks / Diagnosis Notes <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" name="update_message" id="update_message" rows="4" placeholder="Detail the steps taken, parts replaced, or next action plan..." required></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send-fill me-1"></i>Post Update to History
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Print-only Signature Area -->
<div class="print-signature-area">
    <div style="width: 250px; text-align: center;">
        <hr style="border-top: 1px solid #000; margin-bottom: 5px;">
        <strong>IT Staff Signature</strong>
        <div class="small"><?= h($task['staff_name']) ?></div>
    </div>
    <div style="width: 250px; text-align: center;">
        <hr style="border-top: 1px solid #000; margin-bottom: 5px;">
        <strong>IT Supervisor / Head of IT</strong>
        <div class="small">Komfo Anokye Teaching Hospital</div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
