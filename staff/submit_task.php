<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Staff Task Submission Interface with Auto Task Reference Number
 */

require_once __DIR__ . '/../includes/auth.php';

$page_title = 'Submit IT Task';
$userId = (int)$_SESSION['user']['id'];
$errors = [];

// Compute preview of next task number
$nextTaskNumber = generate_task_number($pdo);

$title = '';
$department_id = '';
$category_id = '';
$problem_description = '';
$work_done = '';
$priority = 'Medium';
$status = 'Completed';
$date_started = date('Y-m-d\TH:i');
$date_completed = date('Y-m-d\TH:i');
$remarks = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid or expired. Please resubmit.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $department_id = (int)($_POST['department_id'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $problem_description = trim($_POST['problem_description'] ?? '');
        $work_done = trim($_POST['work_done'] ?? '');
        $priority = trim($_POST['priority'] ?? 'Medium');
        $status = trim($_POST['status'] ?? 'Completed');
        $date_started = trim($_POST['date_started'] ?? '');
        $date_completed = trim($_POST['date_completed'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        // Validation
        if (empty($title)) $errors[] = 'Task title is required.';
        if ($department_id <= 0) $errors[] = 'Please select a hospital department.';
        if ($category_id <= 0) $errors[] = 'Please select an IT problem category.';
        if (empty($problem_description)) $errors[] = 'Problem description is required.';
        if (empty($work_done)) $errors[] = 'Work carried out is required.';
        if (empty($date_started)) $errors[] = 'Date & time started is required.';
        if (!in_array($priority, ['Low', 'Medium', 'High', 'Critical'])) $errors[] = 'Invalid priority selection.';
        if (!in_array($status, ['Pending', 'In Progress', 'Completed', 'Cancelled'])) $errors[] = 'Invalid status selection.';

        // Handle File Attachment Upload
        $attachmentFilename = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = handle_task_attachment_upload('attachment', __DIR__ . '/../uploads/');
            if ($uploadResult['status']) {
                $attachmentFilename = $uploadResult['filename'];
            } else {
                $errors[] = $uploadResult['error'];
            }
        }

        if (empty($errors)) {
            try {
                // Ensure date_completed is null if not provided or pending
                $finalCompletedDate = (!empty($date_completed) && $status === 'Completed') ? $date_completed : ($status === 'Completed' ? date('Y-m-d H:i:s') : null);

                // Freshly generate and guarantee unique task number
                $finalTaskNumber = generate_task_number($pdo);

                $stmt = $pdo->prepare("
                    INSERT INTO tasks (
                        task_number, user_id, department_id, category_id,
                        title, problem_description, work_done, priority,
                        status, date_started, date_completed, remarks,
                        attachment, created_at, updated_at
                    ) VALUES (
                        :task_number, :user_id, :department_id, :category_id,
                        :title, :problem_description, :work_done, :priority,
                        :status, :date_started, :date_completed, :remarks,
                        :attachment, NOW(), NOW()
                    )
                ");

                $stmt->execute([
                    ':task_number'        => $finalTaskNumber,
                    ':user_id'            => $userId,
                    ':department_id'      => $department_id,
                    ':category_id'        => $category_id,
                    ':title'              => $title,
                    ':problem_description'=> $problem_description,
                    ':work_done'          => $work_done,
                    ':priority'           => $priority,
                    ':status'             => $status,
                    ':date_started'       => $date_started,
                    ':date_completed'     => $finalCompletedDate,
                    ':remarks'            => $remarks,
                    ':attachment'         => $attachmentFilename
                ]);

                $newTaskId = (int)$pdo->lastInsertId();

                // Insert initial audit timeline entry
                $initialNote = "Task logged with initial status '{$status}'. Work carried out documented.";
                log_task_update($pdo, $newTaskId, $userId, $initialNote, $status);

                set_flash('success', "Task {$finalTaskNumber} successfully recorded and saved in database.");
                redirect("staff/task_view.php?id={$newTaskId}");

            } catch (PDOException $e) {
                $errors[] = 'Database error saving task: ' . $e->getMessage();
            }
        }
    }
}

// Fetch active departments & categories
$departments = $pdo->query("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM categories WHERE status = 'active' ORDER BY category_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Submit IT Technical Task</h3>
        <p class="text-muted mb-0 small">Record a technical issue, maintenance action, or hardware/software intervention handled at KATH</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>staff/my_tasks.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i>View My Tasks
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please resolve the following errors:</div>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-card-text text-primary me-2"></i>IT Task Record Form</h6>
        <div>
            <span class="text-muted small">Generated Reference: </span>
            <span class="task-number-pill"><?= h($nextTaskNumber) ?></span>
        </div>
    </div>
    <div class="card-body p-4">
        <form action="submit_task.php" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row g-3">
                <!-- Task Title -->
                <div class="col-12">
                    <label for="title" class="form-label fw-semibold">Task Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" value="<?= h($title) ?>" placeholder="e.g. Network connection problem in ENT consulting room" required autofocus>
                    <div class="form-text small">Provide a concise, descriptive summary of the problem handled.</div>
                </div>

                <!-- Hospital Department -->
                <div class="col-md-6">
                    <label for="department_id" class="form-label fw-semibold">Department / Unit <span class="text-danger">*</span></label>
                    <select class="form-select" id="department_id" name="department_id" required>
                        <option value="">-- Select Hospital Department --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= ($department_id == $dept['id']) ? 'selected' : '' ?>>
                                <?= h($dept['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- IT Category -->
                <div class="col-md-6">
                    <label for="category_id" class="form-label fw-semibold">IT Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">-- Select IT Problem Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                                <?= h($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Priority -->
                <div class="col-md-3 col-sm-6">
                    <label for="priority" class="form-label fw-semibold">Priority Level <span class="text-danger">*</span></label>
                    <select class="form-select" id="priority" name="priority" required>
                        <option value="Low" <?= $priority === 'Low' ? 'selected' : '' ?>>Low (Routine maintenance)</option>
                        <option value="Medium" <?= $priority === 'Medium' ? 'selected' : '' ?>>Medium (Standard user request)</option>
                        <option value="High" <?= $priority === 'High' ? 'selected' : '' ?>>High (Clinical workstation down)</option>
                        <option value="Critical" <?= $priority === 'Critical' ? 'selected' : '' ?>>Critical (Department network down / GHIMS offline)</option>
                    </select>
                </div>

                <!-- Status -->
                <div class="col-md-3 col-sm-6">
                    <label for="status" class="form-label fw-semibold">Task Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed (Resolved)</option>
                        <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress (Work ongoing)</option>
                        <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending (Waiting for parts/access)</option>
                        <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <!-- Date Started -->
                <div class="col-md-3 col-sm-6">
                    <label for="date_started" class="form-label fw-semibold">Date &amp; Time Started <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" id="date_started" name="date_started" value="<?= h($date_started) ?>" required>
                </div>

                <!-- Date Completed -->
                <div class="col-md-3 col-sm-6">
                    <label for="date_completed" class="form-label fw-semibold">Date &amp; Time Completed</label>
                    <input type="datetime-local" class="form-control" id="date_completed" name="date_completed" value="<?= h($date_completed) ?>">
                    <div class="form-text small">Leave empty if still ongoing.</div>
                </div>

                <!-- Problem Description -->
                <div class="col-12">
                    <label for="problem_description" class="form-label fw-semibold">Problem Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="problem_description" name="problem_description" rows="3" placeholder="Describe the symptom or fault reported by hospital staff (e.g. computer could not connect to hospital network)..." required><?= h($problem_description) ?></textarea>
                </div>

                <!-- Work Carried Out -->
                <div class="col-12">
                    <label for="work_done" class="form-label fw-semibold">Work Carried Out <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="work_done" name="work_done" rows="3" placeholder="Describe the troubleshooting steps, tests, and configuration changes you executed (e.g. Checked ethernet cable, switch port and IP configuration. Reconfigured IP address and restored network connectivity)..." required><?= h($work_done) ?></textarea>
                </div>

                <!-- Remarks -->
                <div class="col-12">
                    <label for="remarks" class="form-label fw-semibold">Remarks &amp; Follow-up Notes</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Optional recommendations or preventive advice given to staff..."><?= h($remarks) ?></textarea>
                </div>

                <!-- Attachment -->
                <div class="col-12">
                    <label for="attachment" class="form-label fw-semibold">Optional Attachment / Evidence Photo</label>
                    <input type="file" class="form-control" id="attachment" name="attachment" data-max-size="5242880" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                    <div class="form-text small">Upload photo of equipment, error screenshot, or test report (JPG, PNG, GIF, PDF, DOC, max 5MB).</div>
                </div>

                <!-- Submit Button -->
                <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-success px-4 py-2 fw-semibold shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i>Save &amp; Submit IT Task
                    </button>
                    <a href="<?= BASE_URL ?>staff/dashboard.php" class="btn btn-light border px-4 py-2">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
