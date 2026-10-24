<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * IT Support Staff Technician Dashboard
 */

require_once __DIR__ . '/../includes/auth.php';

$page_title = 'Staff Dashboard';
$userId = (int)$_SESSION['user']['id'];

try {
    // 1. Personal Task Counters
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = :id");
    $stmt->execute([':id' => $userId]);
    $myTotalTasks = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = :id AND status = 'Completed'");
    $stmt->execute([':id' => $userId]);
    $myCompletedTasks = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = :id AND status = 'In Progress'");
    $stmt->execute([':id' => $userId]);
    $myInProgressTasks = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = :id AND status = 'Pending'");
    $stmt->execute([':id' => $userId]);
    $myPendingTasks = (int)$stmt->fetchColumn();

    // 2. Personal Task Status Breakdown for Chart
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM tasks 
        WHERE user_id = :id 
        GROUP BY status
    ");
    $stmt->execute([':id' => $userId]);
    $statusRows = $stmt->fetchAll();
    $statusMap = ['Completed' => 0, 'In Progress' => 0, 'Pending' => 0, 'Cancelled' => 0];
    foreach ($statusRows as $sr) {
        $statusMap[$sr['status']] = (int)$sr['count'];
    }

    // 3. Recent Tasks Submitted / Handled by this Technician
    $stmt = $pdo->prepare("
        SELECT t.*, d.department_name, c.category_name
        FROM tasks t
        JOIN departments d ON t.department_id = d.id
        JOIN categories c ON t.category_id = c.id
        WHERE t.user_id = :id
        ORDER BY t.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([':id' => $userId]);
    $recentTasks = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Technician Support Portal</h3>
        <p class="text-muted mb-0 small">Welcome back, <strong><?= h($_SESSION['user']['full_name']) ?></strong>. Here is your task activity summary.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="<?= BASE_URL ?>staff/submit_task.php" class="btn btn-success shadow-sm">
            <i class="bi bi-plus-circle-fill me-1"></i>Submit New Task
        </a>
    </div>
</div>

<!-- Personal KPI Metrics Cards -->
<div class="row g-3 mb-4">
    <!-- My Total Tasks -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-primary-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">My Total Tasks</div>
                <h3 class="fw-bold my-1 text-dark"><?= number_format($myTotalTasks) ?></h3>
                <div class="small text-primary"><i class="bi bi-person-check me-1"></i>Logged by you</div>
            </div>
            <div class="kpi-icon bg-primary-subtle text-primary">
                <i class="bi bi-clipboard-check"></i>
            </div>
        </div>
    </div>

    <!-- Completed Tasks -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-success-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Resolved / Done</div>
                <h3 class="fw-bold my-1 text-success"><?= number_format($myCompletedTasks) ?></h3>
                <div class="small text-success">
                    <i class="bi bi-check-all me-1"></i>
                    <?= $myTotalTasks > 0 ? round(($myCompletedTasks / $myTotalTasks) * 100, 1) : 0 ?>% resolved
                </div>
            </div>
            <div class="kpi-icon bg-success-subtle text-success">
                <i class="bi bi-check2-circle"></i>
            </div>
        </div>
    </div>

    <!-- In Progress Tasks -->
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card border-info-kpi d-flex align-items-center justify-content-between">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">In Progress</div>
                <h3 class="fw-bold my-1 text-info"><?= number_format($myInProgressTasks) ?></h3>
                <div class="small text-muted"><i class="bi bi-hourglass-split me-1"></i>Active technical work</div>
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
                <div class="text-muted small fw-semibold text-uppercase">Pending Queue</div>
                <h3 class="fw-bold my-1 text-warning"><?= number_format($myPendingTasks) ?></h3>
                <div class="small text-muted"><i class="bi bi-clock-history me-1"></i>Awaiting attention</div>
            </div>
            <div class="kpi-icon bg-warning-subtle text-warning">
                <i class="bi bi-hourglass"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Chart: Personal Task Status Breakdown -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-pie-chart-fill text-primary me-2"></i>My Task Status Ratio</h6>
                <span class="badge bg-light text-muted border">Overview</span>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <?php if ($myTotalTasks === 0): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clipboard2-x fs-1 d-block mb-2"></i>
                        No tasks submitted yet. Click <strong>Submit New Task</strong> to record your work.
                    </div>
                <?php else: ?>
                    <div style="height: 260px; width: 100%;">
                        <canvas id="staffStatusChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Action / Guidelines Card -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Quick Actions &amp; Attachment Notes</h6>
                <a href="<?= BASE_URL ?>staff/submit_task.php" class="btn btn-sm btn-outline-success">Log Ticket</a>
            </div>
            <div class="card-body">
                <div class="alert alert-info-subtle border border-info-subtle rounded p-3 mb-3">
                    <h6 class="fw-bold text-primary mb-1"><i class="bi bi-info-circle me-1"></i>Documenting Technical Interventions</h6>
                    <p class="small text-secondary mb-0">
                        When recording work carried out, specify key technical details such as switch port numbers, IP addresses, replaced parts (RAM, toner, PSU), and firmware versions. This provides clear empirical evidence for KATH IT reports.
                    </p>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <a href="<?= BASE_URL ?>staff/submit_task.php" class="btn btn-light border w-100 text-start p-3 d-flex align-items-center">
                            <i class="bi bi-file-earmark-plus text-success fs-3 me-3"></i>
                            <div>
                                <div class="fw-bold text-dark">Submit New Task</div>
                                <div class="text-muted small">Record fresh problem handled</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="<?= BASE_URL ?>staff/my_tasks.php" class="btn btn-light border w-100 text-start p-3 d-flex align-items-center">
                            <i class="bi bi-card-checklist text-primary fs-3 me-3"></i>
                            <div>
                                <div class="fw-bold text-dark">View My Tasks</div>
                                <div class="text-muted small">Search &amp; update your tasks</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Personal Tasks Table -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-secondary me-2"></i>My Recently Logged IT Tasks</h6>
        <a href="<?= BASE_URL ?>staff/my_tasks.php" class="btn btn-sm btn-outline-primary">View All My Tasks &rarr;</a>
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
                        <th class="text-end" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentTasks)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-check fs-2 d-block mb-2"></i>
                                You have not submitted any technical tasks yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentTasks as $task): ?>
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
                                <td><span class="badge bg-light text-dark border"><?= h($task['department_name']) ?></span></td>
                                <td class="small"><?= h($task['category_name']) ?></td>
                                <td><?= get_priority_badge($task['priority']) ?></td>
                                <td><?= get_status_badge($task['status']) ?></td>
                                <td class="small text-muted"><?= format_date($task['date_started']) ?></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="task_view.php?id=<?= $task['id'] ?>" class="btn btn-outline-secondary" title="View Dossier">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="task_edit.php?id=<?= $task['id'] ?>" class="btn btn-outline-primary" title="Edit Task">
                                            <i class="bi bi-pencil"></i>
                                        </a>
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

<?php if ($myTotalTasks > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('staffStatusChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Pending', 'Cancelled'],
                datasets: [{
                    data: [
                        <?= $statusMap['Completed'] ?>,
                        <?= $statusMap['In Progress'] ?>,
                        <?= $statusMap['Pending'] ?>,
                        <?= $statusMap['Cancelled'] ?>
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
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
