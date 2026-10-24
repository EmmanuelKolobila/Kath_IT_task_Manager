<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Comprehensive Reporting Module & CSV / Print Generator
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$page_title = 'IT Reports Generator';

// Capture Filter Parameters
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$user_id = trim($_GET['user_id'] ?? '');
$department_id = trim($_GET['department_id'] ?? '');
$category_id = trim($_GET['category_id'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$status = trim($_GET['status'] ?? '');
$export = trim($_GET['export'] ?? '');

// Build Query
$where = ['1=1'];
$params = [];

if (!empty($date_from)) {
    $where[] = "DATE(t.date_started) >= :date_from";
    $params[':date_from'] = $date_from;
}
if (!empty($date_to)) {
    $where[] = "DATE(t.date_started) <= :date_to";
    $params[':date_to'] = $date_to;
}
if (!empty($user_id)) {
    $where[] = "t.user_id = :user_id";
    $params[':user_id'] = $user_id;
}
if (!empty($department_id)) {
    $where[] = "t.department_id = :department_id";
    $params[':department_id'] = $department_id;
}
if (!empty($category_id)) {
    $where[] = "t.category_id = :category_id";
    $params[':category_id'] = $category_id;
}
if (!empty($priority)) {
    $where[] = "t.priority = :priority";
    $params[':priority'] = $priority;
}
if (!empty($status)) {
    $where[] = "t.status = :status";
    $params[':status'] = $status;
}

$whereSql = implode(' AND ', $where);

$query = "
    SELECT t.*, 
           u.full_name as staff_name, 
           d.department_name, 
           c.category_name
    FROM tasks t
    JOIN users u ON t.user_id = u.id
    JOIN departments d ON t.department_id = d.id
    JOIN categories c ON t.category_id = c.id
    WHERE {$whereSql}
    ORDER BY t.date_started DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Handle CSV Export Request
if ($export === 'csv') {
    $filename = 'kath_it_report_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for Excel compatibility
    fputs($output, "\xEF\xBB\xBF");

    // CSV Header row
    fputcsv($output, [
        'Task Number',
        'Title',
        'Hospital Department',
        'IT Category',
        'Assigned Technician',
        'Priority',
        'Status',
        'Date Started',
        'Date Completed',
        'Problem Description',
        'Work Done',
        'Remarks'
    ]);

    foreach ($records as $r) {
        fputcsv($output, [
            $r['task_number'],
            $r['title'],
            $r['department_name'],
            $r['category_name'],
            $r['staff_name'],
            $r['priority'],
            $r['status'],
            $r['date_started'],
            $r['date_completed'] ?? 'N/A',
            $r['problem_description'],
            $r['work_done'],
            $r['remarks'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// Summary Metrics on the Filtered Dataset
$totalCount = count($records);
$completedCount = 0;
$inProgressCount = 0;
$pendingCount = 0;
$criticalCount = 0;

foreach ($records as $r) {
    if ($r['status'] === 'Completed') $completedCount++;
    if ($r['status'] === 'In Progress') $inProgressCount++;
    if ($r['status'] === 'Pending') $pendingCount++;
    if ($r['priority'] === 'Critical') $criticalCount++;
}

// Fetch filter select options
$departments = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM categories ORDER BY category_name ASC")->fetchAll();
$staffList = $pdo->query("SELECT id, full_name FROM users WHERE role = 'staff' ORDER BY full_name ASC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Print-Only Official Report Header -->
<div class="print-header mb-4">
    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
        <div class="d-flex align-items-center">
            <img src="<?= BASE_URL ?>assets/images/kath-badge.svg" alt="KATH Badge" width="70" height="70" class="me-3">
            <div>
                <h3 class="fw-bold mb-0 text-dark">KOMFO ANOKYE TEACHING HOSPITAL</h3>
                <h5 class="text-secondary mb-0">DIRECTORATE OF INFORMATION TECHNOLOGY</h5>
                <h6 class="text-primary fw-bold mb-0 text-uppercase" style="letter-spacing: 1px;">IT Task Management &amp; Technical Activity Report</h6>
            </div>
        </div>
        <div class="text-end text-muted small">
            <div><strong>Report Date:</strong> <?= date('d M Y, h:i A') ?></div>
            <div><strong>Prepared By:</strong> <?= h($_SESSION['user']['full_name']) ?></div>
            <div><strong>Hospital Location:</strong> Kumasi, Ghana</div>
        </div>
    </div>

    <!-- Print Report Parameters -->
    <div class="row g-2 mb-3 small bg-light p-2 rounded border">
        <div class="col-4">
            <strong>Reporting Period:</strong> 
            <?= !empty($date_from) ? format_date($date_from) : 'Beginning' ?> to <?= !empty($date_to) ? format_date($date_to) : 'Present Date' ?>
        </div>
        <div class="col-4">
            <strong>Scope / Filter:</strong> 
            <?= !empty($department_id) ? 'Filtered Department' : 'All Departments' ?> &bull; 
            <?= !empty($category_id) ? 'Filtered Category' : 'All Categories' ?>
        </div>
        <div class="col-4 text-end">
            <strong>Total Tasks Analyzed:</strong> <?= $totalCount ?> (<?= $completedCount ?> Resolved)
        </div>
    </div>
</div>

<!-- Screen Top Bar (Hidden during print) -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 no-print">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Institutional IT Reports</h3>
        <p class="text-muted mb-0 small">Filter hospital IT technical incidents, generate printable reports, and export to CSV spreadsheet</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-printer-fill me-1"></i>Print Report
        </button>
        <?php
        $exportParams = $_GET;
        $exportParams['export'] = 'csv';
        $exportUrl = 'reports.php?' . http_build_query($exportParams);
        ?>
        <a href="<?= $exportUrl ?>" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel-fill me-1"></i>Export to CSV
        </a>
    </div>
</div>

<!-- Filter Box (Hidden during print) -->
<div class="card shadow-sm border-0 mb-4 filter-card no-print">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-funnel-fill text-primary me-2"></i>Report Criteria Filter</h6>
    </div>
    <div class="card-body p-3">
        <form method="GET" action="reports.php" class="row g-2 align-items-end">
            <!-- Date From -->
            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($date_from) ?>">
            </div>

            <!-- Date To -->
            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= h($date_to) ?>">
            </div>

            <!-- Department -->
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

            <!-- Category -->
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

            <!-- Staff -->
            <div class="col-md-2 col-sm-6">
                <label class="form-label small fw-semibold text-secondary mb-1">Technician</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All Technicians</option>
                    <?php foreach ($staffList as $stf): ?>
                        <option value="<?= $stf['id'] ?>" <?= $user_id == $stf['id'] ? 'selected' : '' ?>>
                            <?= h($stf['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Priority -->
            <div class="col-md-1 col-sm-3">
                <label class="form-label small fw-semibold text-secondary mb-1">Priority</label>
                <select name="priority" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Critical" <?= $priority === 'Critical' ? 'selected' : '' ?>>Critical</option>
                    <option value="High" <?= $priority === 'High' ? 'selected' : '' ?>>High</option>
                    <option value="Medium" <?= $priority === 'Medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="Low" <?= $priority === 'Low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>

            <!-- Status -->
            <div class="col-md-1 col-sm-3">
                <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="In Progress" <?= $status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="col-12 mt-2 d-flex justify-content-end gap-2">
                <a href="reports.php" class="btn btn-light btn-sm border">Reset Filters</a>
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-funnel-fill me-1"></i>Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Metrics Bar (Visible on both Screen and Print) -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3 bg-light rounded">
        <div class="row g-2 text-center">
            <div class="col-3 border-end">
                <div class="text-muted small text-uppercase fw-semibold">Total Records</div>
                <div class="fs-4 fw-bold text-dark"><?= $totalCount ?></div>
            </div>
            <div class="col-3 border-end">
                <div class="text-muted small text-uppercase fw-semibold">Completed</div>
                <div class="fs-4 fw-bold text-success"><?= $completedCount ?></div>
            </div>
            <div class="col-3 border-end">
                <div class="text-muted small text-uppercase fw-semibold">Active / Pending</div>
                <div class="fs-4 fw-bold text-warning"><?= ($inProgressCount + $pendingCount) ?></div>
            </div>
            <div class="col-3">
                <div class="text-muted small text-uppercase fw-semibold">Critical Priority</div>
                <div class="fs-4 fw-bold text-danger"><?= $criticalCount ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Report Table Card -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle mb-0" id="reportTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 120px;">Task #</th>
                        <th>Task Title</th>
                        <th>Staff</th>
                        <th>Department</th>
                        <th>Category</th>
                        <th style="width: 90px;">Priority</th>
                        <th style="width: 95px;">Status</th>
                        <th style="width: 105px;">Started</th>
                        <th style="width: 105px;">Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                No task entries found for the selected reporting parameters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td class="fw-bold">
                                    <span class="task-number-pill"><?= h($r['task_number']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= h($r['title']) ?></div>
                                    <div class="text-muted small no-print text-truncate" style="max-width: 250px;">
                                        <?= h($r['work_done']) ?>
                                    </div>
                                </td>
                                <td class="small"><?= h($r['staff_name']) ?></td>
                                <td class="small"><?= h($r['department_name']) ?></td>
                                <td class="small"><?= h($r['category_name']) ?></td>
                                <td><?= get_priority_badge($r['priority']) ?></td>
                                <td><?= get_status_badge($r['status']) ?></td>
                                <td class="small"><?= format_date($r['date_started']) ?></td>
                                <td class="small"><?= format_date($r['date_completed']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Print-Only Formal Endorsement / Signature Section -->
<div class="print-signature-area">
    <div style="width: 240px; text-align: center;">
        <hr style="border-top: 1px solid #000; margin-bottom: 5px;">
        <strong>Compiled By (IT Technician)</strong>
        <div class="small">Sign &amp; Date</div>
    </div>
    <div style="width: 240px; text-align: center;">
        <hr style="border-top: 1px solid #000; margin-bottom: 5px;">
        <strong>Verified By (IT Supervisor)</strong>
        <div class="small">Sign &amp; Date</div>
    </div>
    <div style="width: 240px; text-align: center;">
        <hr style="border-top: 1px solid #000; margin-bottom: 5px;">
        <strong>Head of Directorate (IT)</strong>
        <div class="small">Komfo Anokye Teaching Hospital</div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
