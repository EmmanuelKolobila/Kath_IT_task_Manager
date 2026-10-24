<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Dynamic Role-Based Sidebar Navigation Component
 */

$user = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isAdmin = is_admin();
?>
<aside class="sidebar">
    <!-- Brand / Hospital Logo -->
    <a href="<?= BASE_URL ?><?= $isAdmin ? 'admin/dashboard.php' : 'staff/dashboard.php' ?>" class="sidebar-brand text-decoration-none">
        <img src="<?= BASE_URL ?>assets/images/kath-badge.svg" alt="KATH Badge" width="36" height="36" class="me-2">
        <div class="d-flex flex-column">
            <span class="lh-1 text-white" style="font-size: 1.05rem; letter-spacing: 0.5px;">KATH IT</span>
            <span class="text-white-50" style="font-size: 0.7rem; font-weight: 400;">TASK MANAGEMENT</span>
        </div>
    </a>

    <!-- Navigation Menu Items -->
    <div class="nav flex-column py-3 flex-grow-1 overflow-auto">
        <?php if ($isAdmin): ?>
            <!-- Administrator Navigation Links -->
            <div class="sidebar-heading">Main Navigation</div>

            <a href="<?= BASE_URL ?>admin/dashboard.php" class="nav-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= BASE_URL ?>admin/tasks.php" class="nav-link <?= in_array($currentScript, ['tasks.php', 'task_view.php', 'task_edit.php']) ? 'active' : '' ?>">
                <i class="bi bi-card-checklist"></i>
                <span>Tasks</span>
            </a>

            <a href="<?= BASE_URL ?>admin/reports.php" class="nav-link <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph"></i>
                <span>Reports</span>
            </a>

            <a href="<?= BASE_URL ?>admin/analytics.php" class="nav-link <?= $currentScript === 'analytics.php' ? 'active' : '' ?>">
                <i class="bi bi-pie-chart-fill"></i>
                <span>Analytics</span>
            </a>

            <div class="sidebar-heading">Administration</div>

            <a href="<?= BASE_URL ?>admin/users.php" class="nav-link <?= in_array($currentScript, ['users.php', 'user_add.php', 'user_edit.php']) ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>Staff Management</span>
            </a>

            <a href="<?= BASE_URL ?>admin/departments.php" class="nav-link <?= $currentScript === 'departments.php' ? 'active' : '' ?>">
                <i class="bi bi-building-gear"></i>
                <span>Departments</span>
            </a>

            <a href="<?= BASE_URL ?>admin/categories.php" class="nav-link <?= $currentScript === 'categories.php' ? 'active' : '' ?>">
                <i class="bi bi-tags-fill"></i>
                <span>IT Categories</span>
            </a>

            <div class="sidebar-heading">Account</div>

            <a href="<?= BASE_URL ?>admin/profile.php" class="nav-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Profile</span>
            </a>

            <a href="<?= BASE_URL ?>admin/profile.php#password" class="nav-link">
                <i class="bi bi-key-fill"></i>
                <span>Change Password</span>
            </a>

        <?php else: ?>
            <!-- Staff Navigation Links -->
            <div class="sidebar-heading">Technician Portal</div>

            <a href="<?= BASE_URL ?>staff/dashboard.php" class="nav-link <?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <a href="<?= BASE_URL ?>staff/submit_task.php" class="nav-link <?= $currentScript === 'submit_task.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle-fill text-success"></i>
                <span>Submit Task</span>
            </a>

            <a href="<?= BASE_URL ?>staff/my_tasks.php" class="nav-link <?= in_array($currentScript, ['my_tasks.php', 'task_view.php', 'task_edit.php']) ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                <span>My Tasks</span>
            </a>

            <div class="sidebar-heading">Account</div>

            <a href="<?= BASE_URL ?>staff/profile.php" class="nav-link <?= $currentScript === 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>

            <a href="<?= BASE_URL ?>staff/profile.php#password" class="nav-link">
                <i class="bi bi-key-fill"></i>
                <span>Change Password</span>
            </a>
        <?php endif; ?>

        <div class="mt-auto pt-3">
            <a href="<?= BASE_URL ?>logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Bottom User Status Box -->
    <?php if ($user): ?>
        <div class="sidebar-user d-flex align-items-center">
            <div class="flex-grow-1 overflow-hidden me-2">
                <div class="text-white text-truncate small fw-bold"><?= h($user['full_name']) ?></div>
                <div class="text-white-50 text-truncate" style="font-size: 0.72rem;">@<?= h($user['username']) ?></div>
            </div>
            <a href="<?= BASE_URL ?>logout.php" class="btn btn-sm btn-outline-secondary text-white-50 border-0 p-1" title="Sign Out">
                <i class="bi bi-power fs-6"></i>
            </a>
        </div>
    <?php endif; ?>
</aside>
