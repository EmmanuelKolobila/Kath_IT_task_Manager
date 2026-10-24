<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Admin Task Edit and Reassignment Interface
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$taskId = (int)($_GET['id'] ?? 0);
if ($taskId <= 0) {
    set_flash('danger', 'Invalid task specified.');
    redirect('admin/tasks.php');
}

// Fetch existing task
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $taskId]);
$task = $stmt->fetch();

if (!$task) {
    set_flash('danger', 'Task not found.');
    redirect('admin/tasks.php');
}

$errors = [];

// Handle Task Edit POST Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $user_id = (int)($_POST['user_id'] ?? 0);
        $department_id = (int)($_POST['department_id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $priority = trim($_POST['priority'] ?? 'Medium');
        $status = trim($_POST['status'] ?? 'Pending');
        $date_started = trim($_POST['date_started'] ?? '');
        $date_completed = trim($_POST['date_completed'] ?? '');
        $problem_description = trim($_POST['problem_description'] ?? '');
        $work_done = trim($_POST['work_done'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        // Validations
        if (empty($title)) $errors[] = 'Task title is required.';
        if ($user_id <= 0) $errors[] = 'Assigned IT technician is required.';
        if ($department_id <= 0) $errors[] = 'Department selection is required.';
        if ($category_id <= 0) $errors[] = 'IT category selection is required.';
        if (empty($problem_description)) $errors[] = 'Problem description is required.';
        if (empty($work_done)) $errors[] = 'Work carried out is required.';
        if (empty($date_started)) $errors[] = 'Date started is required.';

        // Handle File Attachment Upload if provided
        $attachmentName = $task['attachment'];
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handle_task_attachment_upload('attachment', __DIR__ . '/../uploads/');
            if ($uploadResult['status']) {
                $attachmentName = $uploadResult['filename'];
            } else {
                $errors[] = $uploadResult['error'];
            }
        }

        if (empty($errors)) {
            try {
                // If status changed to Completed and no date_completed, set now
                $compDate = !empty($date_completed) ? $date_completed : ($status === 'Completed' ? date('Y-m-d H:i:s') : null);

                $stmt = $pdo->prepare("
                    UPDATE tasks SET
                        title = :title,
                        user_id = :user_id,
                        department_id = :department_id,
                        category_id = :category_id,
                        priority = :priority,
                        status = :status,
                        date_started = :date_started,
                        date_completed = :date_completed,
                        problem_description = :problem_description,
                        work_done = :work_done,
                        remarks = :remarks,
                        attachment = :attachment,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':title' => $title,
                    ':user_id' => $user_id,
                    ':department_id' => $department_id,
                    ':category_id' => $category_id,
                    ':priority' => $priority,
                    ':status' => $status,
                    ':date_started' => $date_started,
                    ':date_completed' => $compDate,
                    ':problem_description' => $problem_description,
                    ':work_done' => $work_done,
                    ':remarks' => $remarks,
                    ':attachment' => $attachmentName,
                    ':id' => $taskId
                ]);

                // Record update in history if status changed or supervisor edit
                $editNote = "Task edited by Administrator. Status: {$status}, Priority: {$priority}.";
                log_task_update($pdo, $taskId, $_SESSION['user']['id'], $editNote, $status);

                set_flash('success', 'Task ' . $task['task_number'] . ' successfully updated.');
                redirect("admin/task_view.php?id={$taskId}");

            } catch (PDOException $e) {
                $errors[] = 'Database error updating task: ' . $e->getMessage();
            }
        }
    }
}

// Fetch lists for selects
$departments = $pdo->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name ASC")->fetchAll();
$staffList = $pdo->query("SELECT id, full_name FROM users WHERE role = 'staff' AND status = 'active' ORDER BY full_name ASC")->fetchAll();

$page_title = 'Edit Task ' . $task['task_number'];
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="task-number-pill"><?= h($task['task_number']) ?></span>
            <span class="badge bg-secondary">Edit Mode</span>
        </div>
        <h3 class="fw-bold mb-0 text-dark">Edit IT Task Details</h3>
    </div>
    <div>
        <a href="<?= BASE_URL ?>admin/task_view.php?id=<?= $task['id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle me-1"></i>Cancel &amp; View
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Please correct the following errors:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Task Modification Form</h6>
    </div>
    <div class="card-body p-4">
        <form action="task_edit.php?id=<?= $task['id'] ?>" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row g-3">
                <!-- Task Title -->
                <div class="col-12">
                    <label for="title" class="form-label fw-semibold">Task Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" value="<?= h($_POST['title'] ?? $task['title']) ?>" placeholder="e.g. Network connection problem in ENT consulting room" required>
                </div>

                <!-- Assigned Technician -->
                <div class="col-md-4">
                    <label for="user_id" class="form-label fw-semibold">Assigned IT Technician <span class="text-danger">*</span></label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        <option value="">-- Select Technician --</option>
                        <?php foreach ($staffList as $stf): ?>
                            <option value="<?= $stf['id'] ?>" <?= (($_POST['user_id'] ?? $task['user_id']) == $stf['id']) ? 'selected' : '' ?>>
                                <?= h($stf['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Hospital Department -->
                <div class="col-md-4">
                    <label for="department_id" class="form-label fw-semibold">Hospital Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department_id" name="department_id" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= (($_POST['department_id'] ?? $task['department_id']) == $dept['id']) ? 'selected' : '' ?>>
                                <?= h($dept['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- IT Category -->
                <div class="col-md-4">
                    <label for="category_id" class="form-label fw-semibold">IT Problem Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? $task['category_id']) == $cat['id']) ? 'selected' : '' ?>>
                                <?= h($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Priority -->
                <div class="col-md-3">
                    <label for="priority" class="form-label fw-semibold">Priority Level <span class="text-danger">*</span></label>
                    <select class="form-select" id="priority" name="priority" required>
                        <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $p): ?>
                            <option value="<?= $p ?>" <?= (($_POST['priority'] ?? $task['priority']) === $p) ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div class="col-md-3">
                    <label for="status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <?php foreach (['Pending', 'In Progress', 'Completed', 'Cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= (($_POST['status'] ?? $task['status']) === $s) ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date Started -->
                <div class="col-md-3">
                    <label for="date_started" class="form-label fw-semibold">Date &amp; Time Started <span class="text-danger">*</span></label>
                    <?php 
                    $dsVal = $_POST['date_started'] ?? $task['date_started'];
                    $dsFormat = $dsVal ? date('Y-m-d\TH:i', strtotime($dsVal)) : '';
                    ?>
                    <input type="datetime-local" class="form-control" id="date_started" name="date_started" value="<?= h($dsFormat) ?>" required>
                </div>

                <!-- Date Completed -->
                <div class="col-md-3">
                    <label for="date_completed" class="form-label fw-semibold">Date &amp; Time Completed</label>
                    <?php 
                    $dcVal = $_POST['date_completed'] ?? $task['date_completed'];
                    $dcFormat = $dcVal ? date('Y-m-d\TH:i', strtotime($dcVal)) : '';
                    ?>
                    <input type="datetime-local" class="form-control" id="date_completed" name="date_completed" value="<?= h($dcFormat) ?>">
                    <div class="form-text small">Leave blank if still ongoing.</div>
                </div>

                <!-- Problem Description -->
                <div class="col-12">
                    <label for="problem_description" class="form-label fw-semibold">Problem Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="problem_description" name="problem_description" rows="3" required><?= h($_POST['problem_description'] ?? $task['problem_description']) ?></textarea>
                </div>

                <!-- Work Carried Out -->
                <div class="col-12">
                    <label for="work_done" class="form-label fw-semibold">Work Carried Out / Resolution Action <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="work_done" name="work_done" rows="3" required><?= h($_POST['work_done'] ?? $task['work_done']) ?></textarea>
                </div>

                <!-- Remarks -->
                <div class="col-12">
                    <label for="remarks" class="form-label fw-semibold">Remarks &amp; Recommendations</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="2"><?= h($_POST['remarks'] ?? $task['remarks']) ?></textarea>
                </div>

                <!-- Attachment -->
                <div class="col-12">
                    <label for="attachment" class="form-label fw-semibold">File / Photo Attachment</label>
                    <?php if (!empty($task['attachment'])): ?>
                        <div class="mb-2 small text-muted">
                            Current file: <code><?= h($task['attachment']) ?></code> 
                            (<a href="<?= BASE_URL ?>uploads/<?= rawurlencode($task['attachment']) ?>" target="_blank">View file</a>)
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" id="attachment" name="attachment" data-max-size="5242880" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                    <div class="form-text small">Upload replacement file if needed (PNG, JPG, PDF, DOC, max 5MB).</div>
                </div>

                <!-- Form Submit buttons -->
                <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i>Save Changes
                    </button>
                    <a href="<?= BASE_URL ?>admin/task_view.php?id=<?= $task['id'] ?>" class="btn btn-light border px-4">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
