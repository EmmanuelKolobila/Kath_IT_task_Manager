<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Institutional IT Analytics & Industrial Attachment Research Insights
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$page_title = 'IT Workload Analytics & Insights';

try {
    // 1. Overall Totals
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
    $totalTasks = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'Completed'");
    $completedTasks = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'Pending'");
    $pendingTasks = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'");
    $inProgressTasks = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE priority = 'Critical'");
    $criticalTasks = (int)$stmt->fetchColumn();

    // 2. Category Distribution & Top Category
    $stmt = $pdo->query("
        SELECT c.id, c.category_name, COUNT(t.id) as task_count,
               SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
               SUM(CASE WHEN t.priority = 'Critical' THEN 1 ELSE 0 END) as critical_count
        FROM categories c
        LEFT JOIN tasks t ON c.id = t.category_id
        GROUP BY c.id, c.category_name
        ORDER BY task_count DESC
    ");
    $categoryStats = $stmt->fetchAll();
    $topCategory = !empty($categoryStats) ? $categoryStats[0] : null;

    // 3. Department Distribution & Top Department
    $stmt = $pdo->query("
        SELECT d.id, d.department_name, COUNT(t.id) as task_count,
               SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
               SUM(CASE WHEN t.status != 'Completed' THEN 1 ELSE 0 END) as unresolved_count
        FROM departments d
        LEFT JOIN tasks t ON d.id = t.department_id
        GROUP BY d.id, d.department_name
        ORDER BY task_count DESC
    ");
    $deptStats = $stmt->fetchAll();
    $topDept = !empty($deptStats) ? $deptStats[0] : null;

    // 4. GHIMS Network and CCTV specific metrics
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN c.category_name LIKE '%GHIMS%' THEN 1 ELSE 0 END) as ghims_count,
            SUM(CASE WHEN c.category_name LIKE '%CCTV%' THEN 1 ELSE 0 END) as cctv_count,
            SUM(CASE WHEN c.category_name = 'Networking' THEN 1 ELSE 0 END) as networking_count,
            SUM(CASE WHEN c.category_name = 'Computer Hardware' THEN 1 ELSE 0 END) as hardware_count
        FROM tasks t
        JOIN categories c ON t.category_id = c.id
    ");
    $specificCounts = $stmt->fetch();
    $ghimsCount = (int)($specificCounts['ghims_count'] ?? 0);
    $cctvCount = (int)($specificCounts['cctv_count'] ?? 0);
    $networkingCount = (int)($specificCounts['networking_count'] ?? 0);
    $hardwareCount = (int)($specificCounts['hardware_count'] ?? 0);

    // 5. Staff Performance Leaderboard
    $stmt = $pdo->query("
        SELECT u.id, u.full_name, u.email,
               COUNT(t.id) as total_handled,
               SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
               SUM(CASE WHEN t.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
               SUM(CASE WHEN t.status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks
        FROM users u
        LEFT JOIN tasks t ON u.id = t.user_id
        WHERE u.role = 'staff'
        GROUP BY u.id, u.full_name, u.email
        ORDER BY total_handled DESC, completed_tasks DESC
    ");
    $staffStats = $stmt->fetchAll();

    // 6. Monthly Task Trends (Past 12 months)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(date_started, '%b %Y') as month_name,
               DATE_FORMAT(date_started, '%Y-%m') as ym,
               COUNT(*) as task_count,
               SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_count
        FROM tasks
        GROUP BY ym, month_name
        ORDER BY ym ASC
        LIMIT 12
    ");
    $monthlyStats = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Institutional IT Workload &amp; Problem Analytics</h3>
        <p class="text-muted mb-0 small">Empirical intelligence addressing hospital IT infrastructure, recurring faults, and team capacity</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>View Full Reports
        </a>
    </div>
</div>

<!-- Highlight Banners: Top Problem & Top Department -->
<div class="row g-3 mb-4">
    <!-- Top Problem Category Highlight -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 bg-primary text-white h-100 position-relative overflow-hidden">
            <div class="card-body p-4">
                <span class="badge bg-warning text-dark text-uppercase fw-bold mb-2">
                    <i class="bi bi-trophy-fill me-1"></i>#1 Most Frequent Problem Category
                </span>
                <h3 class="fw-bold mb-2"><?= h($topCategory['category_name'] ?? 'N/A') ?></h3>
                <div class="display-5 fw-bold mb-2">
                    <?= number_format($topCategory['task_count'] ?? 0) ?> <span class="fs-6 fw-normal text-white-50">incidents recorded</span>
                </div>
                <p class="mb-0 text-white-50 small">
                    Accounts for <?= ($totalTasks > 0 && $topCategory) ? round(($topCategory['task_count'] / $totalTasks) * 100, 1) : 0 ?>% of all hospital technical support calls logged in the institution.
                </p>
            </div>
            <i class="bi bi-diagram-3-fill position-absolute text-white" style="font-size: 8rem; right: -20px; bottom: -20px; opacity: 0.12;"></i>
        </div>
    </div>

    <!-- Highest Support Demand Department Highlight -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 bg-dark text-white h-100 position-relative overflow-hidden">
            <div class="card-body p-4">
                <span class="badge bg-info text-dark text-uppercase fw-bold mb-2">
                    <i class="bi bi-hospital-fill me-1"></i>#1 Highest Support Seeking Directorate
                </span>
                <h3 class="fw-bold mb-2"><?= h($topDept['department_name'] ?? 'N/A') ?></h3>
                <div class="display-5 fw-bold mb-2">
                    <?= number_format($topDept['task_count'] ?? 0) ?> <span class="fs-6 fw-normal text-white-50">requests logged</span>
                </div>
                <p class="mb-0 text-white-50 small">
                    Requires the largest technical presence; <?= (int)($topDept['unresolved_count'] ?? 0) ?> pending issues currently in queue.
                </p>
            </div>
            <i class="bi bi-building-fill position-absolute text-white" style="font-size: 8rem; right: -20px; bottom: -20px; opacity: 0.12;"></i>
        </div>
    </div>
</div>

<!-- Special Clinical / Industrial Attachment Key Metrics -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clipboard2-pulse-fill text-danger me-2"></i>Critical Hospital IT Sub-System Health Check</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- GHIMS Metric -->
            <div class="col-md-3 col-sm-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted small text-uppercase fw-semibold">GHIMS Network Issues</div>
                    <div class="fs-3 fw-bold text-primary my-1"><?= $ghimsCount ?></div>
                    <div class="text-muted small">
                        <?= $totalTasks > 0 ? round(($ghimsCount / $totalTasks) * 100, 1) : 0 ?>% of hospital workload
                    </div>
                </div>
            </div>

            <!-- CCTV Metric -->
            <div class="col-md-3 col-sm-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted small text-uppercase fw-semibold">CCTV Surveillance Issues</div>
                    <div class="fs-3 fw-bold text-warning my-1"><?= $cctvCount ?></div>
                    <div class="text-muted small">
                        <?= $totalTasks > 0 ? round(($cctvCount / $totalTasks) * 100, 1) : 0 ?>% of security tickets
                    </div>
                </div>
            </div>

            <!-- Networking vs Hardware Comparison -->
            <div class="col-md-3 col-sm-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted small text-uppercase fw-semibold">Networking vs Hardware</div>
                    <div class="fs-4 fw-bold text-dark my-1"><?= $networkingCount ?> : <?= $hardwareCount ?></div>
                    <div class="text-muted small">
                        <?= $networkingCount >= $hardwareCount ? 'Network issues dominant' : 'Hardware issues dominant' ?>
                    </div>
                </div>
            </div>

            <!-- Unresolved Incidents -->
            <div class="col-md-3 col-sm-6">
                <div class="p-3 bg-light rounded border text-center">
                    <div class="text-muted small text-uppercase fw-semibold">Unresolved Incidents</div>
                    <div class="fs-3 fw-bold text-danger my-1"><?= ($pendingTasks + $inProgressTasks) ?></div>
                    <div class="text-muted small">
                        <?= $criticalTasks ?> tagged as Critical
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Tables: Category Distribution & Department Breakdown -->
<div class="row g-4 mb-4">
    <!-- Category Distribution Breakdown -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-tags-fill text-primary me-2"></i>IT Category Distribution &amp; Workload Share</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>IT Category</th>
                                <th style="width: 130px;">Share (%)</th>
                                <th>Tasks Logged</th>
                                <th>Resolved</th>
                                <th>Critical</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryStats as $index => $cat): ?>
                                <?php 
                                $percent = ($totalTasks > 0) ? round(($cat['task_count'] / $totalTasks) * 100, 1) : 0;
                                $isTop = ($index === 0 && $cat['task_count'] > 0);
                                ?>
                                <tr class="<?= $isTop ? 'table-primary-subtle fw-semibold' : '' ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($isTop): ?>
                                                <i class="bi bi-star-fill text-warning me-1" title="Top Category"></i>
                                            <?php endif; ?>
                                            <span><?= h($cat['category_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar <?= $isTop ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= $percent ?>%"></div>
                                            </div>
                                            <span class="small text-muted" style="font-size: 0.75rem; width: 35px;"><?= $percent ?>%</span>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-secondary rounded-pill"><?= (int)$cat['task_count'] ?></span></td>
                                    <td class="small text-success fw-semibold"><?= (int)$cat['completed_count'] ?></td>
                                    <td>
                                        <?php if ((int)$cat['critical_count'] > 0): ?>
                                            <span class="badge bg-danger text-white"><?= (int)$cat['critical_count'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">0</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Demand Breakdown -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-hospital-fill text-info me-2"></i>Department Support Demand</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Department</th>
                                <th>Total Tickets</th>
                                <th>Active / Pending</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptStats as $dept): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= h($dept['department_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary rounded-pill"><?= (int)$dept['task_count'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ((int)$dept['unresolved_count'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><?= (int)$dept['unresolved_count'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success">Clear</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Staff Performance Leaderboard Table -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-people-fill text-purple me-2"></i>IT Support Staff Performance &amp; Workload</h6>
        <span class="text-muted small"><?= count($staffStats) ?> Technicians active</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Technician</th>
                        <th>Email Contact</th>
                        <th>Total Tasks Handled</th>
                        <th>Completed</th>
                        <th>Active</th>
                        <th>Pending</th>
                        <th>Resolution Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffStats)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No staff workload data found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($staffStats as $stf): ?>
                            <?php 
                            $resRate = ($stf['total_handled'] > 0) ? round(($stf['completed_tasks'] / $stf['total_handled']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td class="fw-semibold text-dark">
                                    <i class="bi bi-person-circle me-1 text-primary"></i><?= h($stf['full_name']) ?>
                                </td>
                                <td class="small text-muted"><?= h($stf['email']) ?></td>
                                <td><span class="badge bg-dark rounded-pill"><?= (int)$stf['total_handled'] ?></span></td>
                                <td class="text-success fw-bold"><?= (int)$stf['completed_tasks'] ?></td>
                                <td class="text-primary fw-semibold"><?= (int)$stf['in_progress_tasks'] ?></td>
                                <td class="text-warning fw-semibold"><?= (int)$stf['pending_tasks'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; width: 70px;">
                                            <div class="progress-bar bg-success" style="width: <?= $resRate ?>%"></div>
                                        </div>
                                        <span class="small fw-semibold"><?= $resRate ?>%</span>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
