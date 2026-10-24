<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Secure Authentication Portal
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already authenticated
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('staff/dashboard.php');
    }
}

$error = '';
$username = '';

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submitted_token)) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT u.*, d.department_name 
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.username = :username 
                    LIMIT 1
                ");
                $stmt->execute([':username' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'active') {
                        $error = 'Your account has been deactivated. Please contact your IT supervisor.';
                    } else {
                        // Prevent session fixation attack
                        session_regenerate_id(true);

                        $_SESSION['user'] = [
                            'id' => $user['id'],
                            'full_name' => $user['full_name'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'role' => $user['role'],
                            'department_id' => $user['department_id'],
                            'department_name' => $user['department_name'] ?? 'IT Directorate'
                        ];

                        set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');

                        if ($user['role'] === 'admin') {
                            redirect('admin/dashboard.php');
                        } else {
                            redirect('staff/dashboard.php');
                        }
                    }
                } else {
                    $error = 'Invalid username or password. Please check your credentials.';
                }
            } catch (PDOException $e) {
                $error = 'A database error occurred. Please ensure MySQL is running in XAMPP.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KATH IT Task Management and Reporting System</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/images/kath-badge.svg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0a192f 0%, #1e3a8a 50%, #0284c7 100%);
            min-height: 100vh;
        }
        .login-card {
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }
        .login-header {
            background: #0f172a;
            color: #ffffff;
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 3px solid #f59e0b;
        }
        .demo-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-5 col-lg-6 col-md-8 col-sm-11">
                <div class="login-card">
                    <!-- Header with Hospital Logo -->
                    <div class="login-header">
                        <img src="<?= BASE_URL ?>assets/images/kath-badge.svg" alt="KATH Badge" width="68" height="68" class="mb-2">
                        <h4 class="fw-bold mb-1" style="letter-spacing: 0.5px;">Komfo Anokye Teaching Hospital</h4>
                        <div class="text-info small fw-semibold text-uppercase" style="letter-spacing: 1px;">IT Task Management &amp; Reporting System</div>
                    </div>

                    <!-- Card Body Form -->
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h5 class="fw-bold text-dark mb-1">Sign In to Your Account</h5>
                            <p class="text-muted small">Enter your staff credentials to access your dashboard</p>
                        </div>

                        <?php display_flash(); ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                <div><?= h($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <form action="login.php" method="POST" autocomplete="off">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold text-secondary">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person-fill"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= h($username) ?>" placeholder="e.g. admin or staff1" required autofocus>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold text-secondary">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                                </div>
                            </div>

                            <div class="d-grid mb-4">
                                <button type="submit" class="btn btn-primary btn-lg shadow-sm fw-semibold">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                                </button>
                            </div>
                        </form>

                        <!-- Demo Credentials Helper Callout -->
                        <div class="demo-box p-3">
                            <div class="fw-bold text-primary mb-2 d-flex align-items-center">
                                <i class="bi bi-info-circle-fill me-2"></i>Demo Accounts for Review:
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="border rounded p-2 bg-white">
                                        <div class="fw-bold text-danger small"><i class="bi bi-shield-lock me-1"></i>Admin:</div>
                                        <div><code>admin</code></div>
                                        <div class="text-muted" style="font-size:0.75rem;">Pass: <code>Admin@123</code></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2 bg-white">
                                        <div class="fw-bold text-success small"><i class="bi bi-person-workspace me-1"></i>Staff:</div>
                                        <div><code>staff1</code></div>
                                        <div class="text-muted" style="font-size:0.75rem;">Pass: <code>Staff@123</code></div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-muted mt-2 text-center" style="font-size: 0.72rem;">
                                Note: In live deployment, change these demo credentials immediately.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer note -->
                <div class="text-center mt-3 text-white-50 small">
                    Komfo Anokye Teaching Hospital (KATH) &bull; Directorate of IT &copy; <?= date('Y') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
