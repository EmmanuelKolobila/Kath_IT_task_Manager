<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Shared Header Layout Component
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = current_user();
$pageTitle = isset($page_title) ? $page_title . ' - KATH IT Task Management' : 'KATH IT Task Management & Reporting System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/images/kath-badge.svg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Hospital IT Theme Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar Navigation Component -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Navbar -->
            <nav class="top-navbar d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-lg-none me-2" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <div class="d-none d-sm-block">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 me-2">
                            <i class="bi bi-hospital me-1"></i>Komfo Anokye Teaching Hospital
                        </span>
                        <span class="text-muted small">Directorate of Information Technology</span>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <?php if ($user): ?>
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: 600;">
                                    <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <div class="text-start d-none d-md-block">
                                    <div class="fw-semibold small leading-tight"><?= h($user['full_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.72rem;">
                                        <?= $user['role'] === 'admin' ? '<span class="text-danger fw-bold">Administrator</span>' : '<span class="text-primary">IT Support Staff</span>' ?>
                                    </div>
                                </div>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li>
                                    <h6 class="dropdown-header">Signed in as <strong><?= h($user['username']) ?></strong></h6>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?><?= $user['role'] === 'admin' ? 'admin/profile.php' : 'staff/profile.php' ?>">
                                        <i class="bi bi-person me-2 text-primary"></i>My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= BASE_URL ?><?= $user['role'] === 'admin' ? 'admin/profile.php#password' : 'staff/profile.php#password' ?>">
                                        <i class="bi bi-key me-2 text-warning"></i>Change Password
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </nav>

            <!-- Page Body & Flash Alerts Container -->
            <div class="container-fluid p-4">
                <?php display_flash(); ?>
