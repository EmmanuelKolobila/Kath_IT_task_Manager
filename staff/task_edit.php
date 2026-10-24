<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Staff Task Modification Interface (Own tasks only)
 */

require_once __DIR__ . '/../includes/auth.php';

$taskId = (int)($_GET['id'] ?? 0);
$userId = (int)$_SESSION['user']['id'];

if ($taskId <= 0) {
    set_flash('danger', 'Invalid task specified.');
    redirect('staff/my_tasks.php');
}

// Fetch existing task and enforce ownership
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id AND user_id = :user_id LIMIT 1");
$stmt->execute([':id' => $taskId, ':user_id' => $userId]);
$task = $stmt->fetch();

if (!$task) {
    set_flash('danger', 'Task not found or access unauthorized.');
    redirect('staff/my_tasks.php');
}

$page_title = 'Edit Task ' . $task['task_number'];
$errors = [];

// Handle Edit Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid or expired. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $department_id = (int)($_POST['department_id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $priority = trim($_POST['priority'] ?? 'Medium');
        $status = trim($_POST['status'] ?? 'Pending');
        $date_started = trim($_POST['date_started'] ?? '');
        $date_completed = trim($_POST['date_completed'] ?? '');
        $problem_description = trim($_POST['problem_description'] ?? '');
        $work_done = trim($_POST['work_done'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        // Validation
        if (empty($title)) $errors[] = 'Task title is required.';
        if ($department_id <= 0) $errors[] = 'Hospital department is required.';
        if ($category_id <= 0) $errors[] = 'IT category is required.';
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
                $compDate = !empty($date_completed) ? $date_completed : ($status === 'Completed' ? date('Y-m-d H:i:s') : null);

                $stmt = $pdo->prepare("
                    UPDATE tasks SET
                        title = :title,
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
                    WHERE id = :id AND user_id = :user_id
                ");

                $stmt->execute([
                    ':title' => $title,
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
                    ':id' => $taskId,
                    ':user_id' => $userId
                ]);

                // Record edit in timeline
                $editNote = "Task details revised by technician. Current status: {$status}.";
                log_task_update($pdo, $taskId, $userId, $editNote, $status);

                set_flash('success', 'Task ' . $task['task_number'] . ' successfully updated.');
                redirect("staff/task_view.php?id={$taskId}");

            } catch (PDOException $e) {
                $errors[] = 'Database error updating task: ' . $e->getMessage();
            }
        }
    }
}

// Fetch lists
$departments = $pdo->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="task-number-pill"><?= h($task['task_number']) ?></span>
            <span class="badge bg-secondary">Technician Edit</span>
        </div>
        <h3 class="fw-bold mb-0 text-dark">Edit My Task Record</h3>
    </div>
    <div>
        <a href="<?= BASE_URL ?>staff/task_view.php?id=<?= $task['id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-circle me-1"></i>Cancel &amp; View
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Please resolve the following:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Modify Incident Details</h6>
    </div>
    <div class="card-body p-4">
        <form action="task_edit.php?id=<?= $task['id'] ?>" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <label for="title" class="form-label fw-semibold">Task Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" value="<?= h($_POST['title'] ?? $task['title']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="department_id" class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department_id" name="department_id" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= (($_POST['department_id'] ?? $task['department_id']) == $dept['id']) ? 'selected' : '' ?>>
                                <?= h($dept['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="category_id" class="form-label fw-semibold">IT Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (($_POST['category_id'] ?? $task['category_id']) == $cat['id']) ? 'selected' : '' ?>>
                                <?= h($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 col-sm-6">
                    <label for="priority" class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                    <select class="form-select" id="priority" name="priority" required>
                        <?php foreach (['Low', 'Medium', 'High', 'Critical'] as $p): ?>
                            <option value="<?= $p ?>" <?= (($_POST['priority'] ?? $task['priority']) === $p) ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 col-sm-6">
                    <label for="status" class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <?php foreach (['Pending', 'In Progress', 'Completed', 'Cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= (($_POST['status'] ?? $task['status']) === $s) ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 col-sm-6">
                    <label for="date_started" class="form-label fw-semibold">Date Started <span class="text-danger">*</span></label>
                    <?php 
                    $dsVal = $_POST['date_started'] ?? $task['date_started'];
                    $dsFormat = $dsVal ? date('Y-m-d\TH:i', strtotime($dsVal)) : '';
                    ?>
                    <input type="datetime-local" class="form-control" id="date_started" name="date_started" value="<?= h($dsFormat) ?>" required>
                </div>

                <div class="col-md-3 col-sm-6">
                    <label for="date_completed" class="form-label fw-semibold">Date Completed</label>
                    <?php 
                    $dcVal = $_POST['date_completed'] ?? $task['date_completed'];
                    $dcFormat = $dcVal ? date('Y-m-d\TH:i', strtotime($dcVal)) : '';
                    ?>
                    <input type="datetime-local" class="form-control" id="date_completed" name="date_completed" value="<?= h($dcFormat) ?>">
                </div>

                <div class="col-12">
                    <label for="problem_description" class="form-label fw-semibold">Problem Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="problem_description" name="problem_description" rows="3" required><?= h($_POST['problem_description'] ?? $task['problem_description']) ?></textarea>
                </div>

                <div class="col-12">
                    <label for="work_done" class="form-label fw-semibold">Work Carried Out <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="work_done" name="work_done" rows="3" required><?= h($_POST['work_done'] ?? $task['work_done']) ?></textarea>
                </div>

                <div class="col-12">
                    <label for="remarks" class="form-label fw-semibold">Remarks &amp; Notes</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="2"><?= h($_POST['remarks'] ?? $task['remarks']) ?></textarea>
                </div>

                <div class="col-12">
                    <label for="attachment" class="form-label fw-semibold">Attachment / Photo</label>
                    <?php if (!empty($task['attachment'])): ?>
                        <div class="mb-2 small text-muted">
                            Current file: <code><?= h($task['attachment']) ?></code>
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" id="attachment" name="attachment" data-max-size="5242880" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                </div>

                <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    <a href="<?= BASE_URL ?>staff/task_view.php?id=<?= $task['id'] ?>" class="btn btn-light border px-4">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
