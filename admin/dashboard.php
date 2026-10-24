<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Administrator Dashboard
 */

require_once __DIR__ . '/../includes/admin_auth.php';

$page_title = 'Admin Dashboard';

// 1. Fetch Metric Counts
try {
    // Total Tasks
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
    $totalTasks = (int)$stmt->fetchColumn();

    // Completed Tasks
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'Completed'");
    $completedTasks = (int)$stmt->fetchColumn();

    // Pending Tasks
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'Pending'");
    $pendingTasks = (int)$stmt->fetchColumn();

    // In Progress Tasks
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'");
    $inProgressTasks = (int)$stmt->fetchColumn();

    // Critical Tasks
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE priority = 'Critical'");
    $criticalTasks = (int)$stmt->fetchColumn();

    // Total IT Staff
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'");
    $totalStaff = (int)$stmt->fetchColumn();

    // Total Departments
    $stmt = $pdo->query("SELECT COUNT(*) FROM departments WHERE status = 'active'");
    $totalDepartments = (int)$stmt->fetchColumn();

    // Total Categories
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories WHERE status = 'active'");
    $totalCategories = (int)$stmt->fetchColumn();

    // 2. Data for Chart 1: Tasks by IT Category
    $stmt = $pdo->query("
        SELECT c.category_name, COUNT(t.id) as task_count 
        FROM categories c
        LEFT JOIN tasks t ON c.id = t.category_id
        GROUP BY c.id, c.category_name
        ORDER BY task_count DESC
        LIMIT 10
    ");
    $categoryData = $stmt->fetchAll();
    $catLabels = [];
    $catCounts = [];
    foreach ($categoryData as $row) {
        $catLabels[] = $row['category_name'];
        $catCounts[] = (int)$row['task_count'];
    }

    // 3. Data for Chart 2: Tasks by Department
    $stmt = $pdo->query("
        SELECT d.department_name, COUNT(t.id) as task_count 
        FROM departments d
        LEFT JOIN tasks t ON d.id = t.department_id
        GROUP BY d.id, d.department_name
        ORDER BY task_count DESC
        LIMIT 8
    ");
    $departmentData = $stmt->fetchAll();
    $deptLabels = [];
    $deptCounts = [];
    foreach ($departmentData as $row) {
        $deptLabels[] = $row['department_name'];
        $deptCounts[] = (int)$row['task_count'];
    }

    // 4. Data for Chart 3: Tasks by Status
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM tasks 
        GROUP BY status
    ");
    $statusData = $stmt->fetchAll();
    $statusCounts = ['Completed' => 0, 'In Progress' => 0, 'Pending' => 0, 'Cancelled' => 0];
    foreach ($statusData as $row) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }

    // 5. Data for Chart 4: Tasks Over Time (Past 6 Months)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(date_started, '%b %Y') as month_label, 
               DATE_FORMAT(date_started, '%Y-%m') as sort_month,
               COUNT(*) as task_count
        FROM tasks
        WHERE date_started >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY sort_month, month_label
        ORDER BY sort_month ASC
    ");
    $monthlyData = $stmt->fetchAll();
    $monthLabels = [];
    $monthCounts = [];
    foreach ($monthlyData as $row) {
        $monthLabels[] = $row['month_label'];
        $monthCounts[] = (int)$row['task_count'];
    }
    // Fallback if empty
    if (empty($monthLabels)) {
        $monthLabels = [date('M Y')];
        $monthCounts = [$totalTasks];
    }

    // 6. Data for Chart 5: Tasks Handled by Staff
    $stmt = $pdo->query("
        SELECT u.full_name, COUNT(t.id) as task_count
        FROM users u
        LEFT JOIN tasks t ON u.id = t.user_id
        WHERE u.role = 'staff'
        GROUP BY u.id, u.full_name
        ORDER BY task_count DESC
        LIMIT 6
    ");
    $staffData = $stmt->fetchAll();
    $staffLabels = [];
    $staffCounts = [];
    foreach ($staffData as $row) {
        $staffLabels[] = $row['full_name'];
        $staffCounts[] = (int)$row['task_count'];
    }

    // 7. Recent Tasks
    $stmt = $pdo->query("
        SELECT t.*, u.full_name as staff_name, d.department_name, c.category_name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        JOIN departments d ON t.department_id = d.id
        JOIN categories c ON t.category_id = c.id
        ORDER BY t.created_at DESC
        LIMIT 6
    ");
    $recentTasks = $stmt->fetchAll();

    // 8. Urgent / Critical Unresolved Tasks
    $stmt = $pdo->query("
        SELECT t.*, u.full_name as staff_name, d.department_name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        JOIN departments d ON t.department_id = d.id
        WHERE t.priority = 'Critical' AND t.status != 'Completed'
        ORDER BY t.created_at DESC
        LIMIT 4
    ");
    $urgentTasks = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error fetching dashboard stats: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Executive IT Operations Dashboard</h3>
        <p class="text-muted mb-0 small">Overview of hospital-wide technical support activities, staff workload, and system metrics</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-printer me-1"></i>Generate Report
        </a>
        <a href="<?= BASE_URL ?>admin/analytics.php" class="btn btn-outline-success btn-sm">
            <i class="bi bi-graph-up-arrow me-1"></i>View In-depth Analytics
        </a>
    </div>
</div>

<!-- Critical Alert Notice (If any critical tickets are pending) -->
<?php if (!empty($urgentTasks)): ?>
    <div class="alert alert-danger border-danger shadow-sm mb-4">
        <div class="d-flex align-items-center mb-2">
            <i class="bi bi-exclamation-octagon-fill fs-4 me-2 text-danger"></i>
            <h6 class="mb-0 fw-bold text-danger">Attention Required: <?= count($urgentTasks) ?> Unresolved Critical Incident(s) Active</h6>
        </div>
        <div class="row g-2 mt-1">
            <?php foreach ($urgentTasks as $ut): ?>
                <div class="col-md-6">
                    <div class="p-2 bg-white rounded border border-danger-subtle d-flex justify-content-between align-items-center">
                        <div>
                            <a href="<?= BASE_URL ?>admin/task_view.php?id=<?= $ut['id'] ?>" class="task-number-pill me-2"><?= h($ut['task_number']) ?></a>
                            <span class="fw-semibold small text-dark"><?= h($ut['title']) ?></span>
                            <div class="text-muted" style="font-size: 0.75rem;">
                                Dept: <?= h($ut['department_name']) ?> &bull; Tech: <?= h($ut['staff_name']) ?>
                            </div>
                        </div>
                        <div>
                            <?= get_status_badge($ut['status']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- KPI Metric Cards Grid -->
<div class="row g-3 mb-4">
    <!-- Total Tasks -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-primary-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Total Tasks</div>
                <h3 class="fw-bold my-1 text-dark"><?= number_format($totalTasks) ?></h3>
                <div class="small text-primary"><i class="bi bi-activity me-1"></i>Recorded to date</div>
            </div>
            <div class="kpi-icon bg-primary-subtle text-primary">
                <i class="bi bi-clipboard-data"></i>
            </div>
        </div>
    </div>

    <!-- Completed Tasks -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-success-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Completed</div>
                <h3 class="fw-bold my-1 text-success"><?= number_format($completedTasks) ?></h3>
                <div class="small text-success">
                    <i class="bi bi-check2-all me-1"></i>
                    <?= $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0 ?>% resolution rate
                </div>
            </div>
            <div class="kpi-icon bg-success-subtle text-success">
                <i class="bi bi-check-circle"></i>
            </div>
        </div>
    </div>

    <!-- In Progress Tasks -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-info-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">In Progress</div>
                <h3 class="fw-bold my-1 text-info"><?= number_format($inProgressTasks) ?></h3>
                <div class="small text-muted"><i class="bi bi-hourglass-split me-1"></i>Being handled</div>
            </div>
            <div class="kpi-icon bg-info-subtle text-info">
                <i class="bi bi-tools"></i>
            </div>
        </div>
    </div>

    <!-- Pending Tasks -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-warning-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Pending Tasks</div>
                <h3 class="fw-bold my-1 text-warning"><?= number_format($pendingTasks) ?></h3>
                <div class="small text-muted"><i class="bi bi-clock-history me-1"></i>Awaiting attention</div>
            </div>
            <div class="kpi-icon bg-warning-subtle text-warning">
                <i class="bi bi-hourglass"></i>
            </div>
        </div>
    </div>

    <!-- Critical Tasks -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-danger-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Critical Priority</div>
                <h3 class="fw-bold my-1 text-danger"><?= number_format($criticalTasks) ?></h3>
                <div class="small text-danger"><i class="bi bi-fire me-1"></i>High-impact issues</div>
            </div>
            <div class="kpi-icon bg-danger-subtle text-danger">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
        </div>
    </div>

    <!-- Total Staff -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-purple-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">IT Technicians</div>
                <h3 class="fw-bold my-1 text-dark"><?= number_format($totalStaff) ?></h3>
                <a href="<?= BASE_URL ?>admin/users.php" class="small text-decoration-none text-primary">Manage staff &rarr;</a>
            </div>
            <div class="kpi-icon bg-secondary-subtle text-dark">
                <i class="bi bi-people"></i>
            </div>
        </div>
    </div>

    <!-- Total Departments -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-primary-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Departments</div>
                <h3 class="fw-bold my-1 text-dark"><?= number_format($totalDepartments) ?></h3>
                <a href="<?= BASE_URL ?>admin/departments.php" class="small text-decoration-none text-primary">View units &rarr;</a>
            </div>
            <div class="kpi-icon bg-primary-subtle text-primary">
                <i class="bi bi-hospital"></i>
            </div>
        </div>
    </div>

    <!-- Total Categories -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-info-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">IT Categories</div>
                <h3 class="fw-bold my-1 text-dark"><?= number_format($totalCategories) ?></h3>
                <a href="<?= BASE_URL ?>admin/categories.php" class="small text-decoration-none text-primary">View categories &rarr;</a>
            </div>
            <div class="kpi-icon bg-info-subtle text-info">
                <i class="bi bi-tags"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section: Row 1 -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Tasks by IT Category -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Tasks by IT Category</h6>
                <span class="badge bg-light text-muted border">Top Categories</span>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 3: Tasks by Status -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-pie-chart-fill text-success me-2"></i>Tasks by Status</h6>
                <span class="badge bg-light text-muted border">Resolution Breakdown</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div style="height: 280px; width: 100%;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section: Row 2 -->
<div class="row g-4 mb-4">
    <!-- Chart 2: Tasks by Department -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-building-fill text-warning me-2"></i>Tasks by Hospital Department</h6>
                <span class="badge bg-light text-muted border">Support Demand</span>
            </div>
            <div class="card-body">
                <div style="height: 280px;">
                    <canvas id="departmentChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 4: Tasks Over Time -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-graph-up text-primary me-2"></i>Tasks Over Time (Monthly Trend)</h6>
                <span class="badge bg-light text-muted border">Activity Volume</span>
            </div>
            <div class="card-body">
                <div style="height: 280px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section: Row 3 (Staff Workload) & Recent Tasks Table -->
<div class="row g-4 mb-4">
    <!-- Chart 5: Tasks Handled by Staff -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-person-lines-fill text-info me-2"></i>Technician Workload</h6>
                <span class="badge bg-light text-muted border">Tasks Handled</span>
            </div>
            <div class="card-body">
                <div style="height: 280px;">
                    <canvas id="staffChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tasks Table -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-secondary me-2"></i>Recent IT Tasks</h6>
                <a href="<?= BASE_URL ?>admin/tasks.php" class="btn btn-sm btn-outline-primary">View All Tasks &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Task #</th>
                                <th>Title</th>
                                <th>Dept</th>
                                <th>Staff</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTasks)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No task records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTasks as $task): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL ?>admin/task_view.php?id=<?= $task['id'] ?>" class="task-number-pill">
                                                <?= h($task['task_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width: 170px;" title="<?= h($task['title']) ?>">
                                                <?= h($task['title']) ?>
                                            </div>
                                            <div class="text-muted small" style="font-size: 0.75rem;"><?= h($task['category_name']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= h($task['department_name']) ?></span></td>
                                        <td class="small"><?= h($task['staff_name']) ?></td>
                                        <td><?= get_priority_badge($task['priority']) ?></td>
                                        <td><?= get_status_badge($task['status']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>admin/task_view.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Dossier">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Configuration Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Tasks by Category Chart
    const ctxCategory = document.getElementById('categoryChart');
    if (ctxCategory) {
        new Chart(ctxCategory, {
            type: 'bar',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{
                    label: 'Tasks Logged',
                    data: <?= json_encode($catCounts) ?>,
                    backgroundColor: [
                        '#0d6efd', '#0dcaf0', '#198754', '#ffc107', 
                        '#fd7e14', '#dc3545', '#6f42c1', '#20c997', 
                        '#6610f2', '#d63384'
                    ],
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 30
                        }
                    }
                }
            }
        });
    }

    // 2. Tasks by Department Chart (Horizontal Bar)
    const ctxDept = document.getElementById('departmentChart');
    if (ctxDept) {
        new Chart(ctxDept, {
            type: 'bar',
            data: {
                labels: <?= json_encode($deptLabels) ?>,
                datasets: [{
                    label: 'Department Requests',
                    data: <?= json_encode($deptCounts) ?>,
                    backgroundColor: '#0284c7',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // 3. Tasks by Status (Doughnut)
    const ctxStatus = document.getElementById('statusChart');
    if (ctxStatus) {
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Pending', 'Cancelled'],
                datasets: [{
                    data: [
                        <?= $statusCounts['Completed'] ?>,
                        <?= $statusCounts['In Progress'] ?>,
                        <?= $statusCounts['Pending'] ?>,
                        <?= $statusCounts['Cancelled'] ?>
                    ],
                    backgroundColor: ['#198754', '#0d6efd', '#ffc107', '#212529']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // 4. Tasks Over Time (Monthly Trend Line)
    const ctxTrend = document.getElementById('trendChart');
    if (ctxTrend) {
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [{
                    label: 'Tasks Created',
                    data: <?= json_encode($monthCounts) ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // 5. Tasks Handled by Staff
    const ctxStaff = document.getElementById('staffChart');
    if (ctxStaff) {
        new Chart(ctxStaff, {
            type: 'bar',
            data: {
                labels: <?= json_encode($staffLabels) ?>,
                datasets: [{
                    label: 'Tasks Handled',
                    data: <?= json_encode($staffCounts) ?>,
                    backgroundColor: '#8b5cf6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
