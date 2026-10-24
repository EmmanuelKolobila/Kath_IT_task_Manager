<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Database Configuration and Connection Module
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials (Standard XAMPP default settings)
define('DB_HOST', 'localhost');
define('DB_NAME', 'kath_it_task_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Dynamic Base URL detection for portability across different XAMPP subdirectories
if (!defined('BASE_URL')) {
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $relPath = str_replace($docRoot, '', $appRoot);
    $baseUrl = '/' . ltrim(str_replace('\\', '/', $relPath), '/');
    $baseUrl = rtrim($baseUrl, '/') . '/';
    define('BASE_URL', $baseUrl);
}

// Global PDO connection handler
$pdo = null;

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // If running in CLI mode, echo message or don't die with HTML
    if (php_sapi_name() === 'cli') {
        // Allow scripts to handle or report error in CLI
        $db_connection_error = $e->getMessage();
    } else {
        // Friendly HTML error screen when MySQL is not running or DB not imported
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Database Connection Notice - KATH IT Task System</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        </head>
        <body class="bg-light d-flex align-items-center min-vh-100">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-7 col-md-9">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-danger text-white py-3">
                                <h5 class="mb-0"><i class="bi bi-database-exclamation me-2"></i>Database Connection Notice</h5>
                            </div>
                            <div class="card-body p-4">
                                <p class="lead text-secondary">
                                    Unable to connect to the MySQL database <code><?= htmlspecialchars(DB_NAME) ?></code>.
                                </p>
                                <div class="alert alert-warning mb-4">
                                    <strong>Diagnostic details:</strong> <?= htmlspecialchars($e->getMessage()) ?>
                                </div>
                                <h6>Please verify the following in XAMPP:</h6>
                                <ol class="text-secondary mb-4">
                                    <li>Open your <strong>XAMPP Control Panel</strong> and ensure the <strong>MySQL</strong> service is <strong>Started</strong> (Green).</li>
                                    <li>Open <strong>phpMyAdmin</strong> (<a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a>).</li>
                                    <li>Create a database named <code><?= htmlspecialchars(DB_NAME) ?></code> or import the provided <code>database.sql</code> file located in this project directory.</li>
                                    <li>Verify your database credentials in <code>config/database.php</code> if you changed default MySQL settings.</li>
                                </ol>
                                <div class="text-center">
                                    <a href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '#') ?>" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Retry Connection
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
