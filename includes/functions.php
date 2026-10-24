<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Core Utility and Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape HTML output safely
 */
function h($string) {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token and store in session
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF input field
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

/**
 * Verify submitted CSRF token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set a session flash message
 * Types: success, danger, warning, info
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash message as Bootstrap 5 dismissible alert
 */
function display_flash() {
    $flash = get_flash();
    if ($flash) {
        $type = h($flash['type']);
        $message = h($flash['message']);
        $icon = 'bi-info-circle-fill';
        if ($type === 'success') $icon = 'bi-check-circle-fill';
        if ($type === 'danger') $icon = 'bi-exclamation-triangle-fill';
        if ($type === 'warning') $icon = 'bi-exclamation-circle-fill';

        echo "
        <div class=\"alert alert-{$type} alert-dismissible fade show d-flex align-items-center shadow-sm\" role=\"alert\">
            <i class=\"bi {$icon} me-2 fs-5\"></i>
            <div class=\"flex-grow-1\">{$message}</div>
            <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
        </div>";
    }
}

/**
 * Check if a user is currently logged in
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get current authenticated user details
 */
function current_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Check if the current user is an Administrator
 */
function is_admin() {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'admin';
}

/**
 * Redirect safely to an internal URL
 */
function redirect($path) {
    $target = BASE_URL . ltrim($path, '/');
    header("Location: " . $target);
    exit;
}

/**
 * Generate Next Unique KATH IT Task Reference Number
 * Format: KATH-IT-000001
 */
function generate_task_number($pdo) {
    try {
        $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM `tasks`");
        $row = $stmt->fetch();
        $next_id = ($row && $row['max_id']) ? ((int)$row['max_id'] + 1) : 1;
        return 'KATH-IT-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        // Fallback using random if query fails
        return 'KATH-IT-' . strtoupper(substr(uniqid(), -6));
    }
}

/**
 * Log a task update / chronological history remark
 */
function log_task_update($pdo, $task_id, $user_id, $message, $status) {
    $stmt = $pdo->prepare("
        INSERT INTO `task_updates` (`task_id`, `user_id`, `update_message`, `status`, `created_at`)
        VALUES (:task_id, :user_id, :message, :status, NOW())
    ");
    return $stmt->execute([
        ':task_id' => $task_id,
        ':user_id' => $user_id,
        ':message' => $message,
        ':status'  => $status
    ]);
}

/**
 * Get Bootstrap badge markup for Task Priority
 */
function get_priority_badge($priority) {
    $p = ucfirst(strtolower($priority));
    switch ($p) {
        case 'Critical':
            return '<span class="badge bg-danger text-white"><i class="bi bi-fire me-1"></i>Critical</span>';
        case 'High':
            return '<span class="badge bg-warning text-dark"><i class="bi bi-arrow-up-circle-fill me-1"></i>High</span>';
        case 'Medium':
            return '<span class="badge bg-info text-dark"><i class="bi bi-dash-circle-fill me-1"></i>Medium</span>';
        case 'Low':
            return '<span class="badge bg-secondary text-white"><i class="bi bi-arrow-down-circle-fill me-1"></i>Low</span>';
        default:
            return '<span class="badge bg-light text-dark">' . h($priority) . '</span>';
    }
}

/**
 * Get Bootstrap badge markup for Task Status
 */
function get_status_badge($status) {
    $s = ucwords(strtolower($status));
    switch ($s) {
        case 'Completed':
            return '<span class="badge bg-success text-white"><i class="bi bi-check2-circle me-1"></i>Completed</span>';
        case 'In Progress':
            return '<span class="badge bg-primary text-white"><i class="bi bi-hourglass-split me-1"></i>In Progress</span>';
        case 'Pending':
            return '<span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Pending</span>';
        case 'Cancelled':
            return '<span class="badge bg-dark text-white"><i class="bi bi-x-circle me-1"></i>Cancelled</span>';
        default:
            return '<span class="badge bg-secondary text-white">' . h($status) . '</span>';
    }
}

/**
 * Format datetime string for UI display
 */
function format_datetime($dt) {
    if (empty($dt) || $dt === '0000-00-00 00:00:00') {
        return '<span class="text-muted">N/A</span>';
    }
    $time = strtotime($dt);
    if (!$time) return h($dt);
    return date('d M Y, h:i A', $time);
}

/**
 * Format date string for UI display
 */
function format_date($d) {
    if (empty($d) || $d === '0000-00-00') {
        return '<span class="text-muted">N/A</span>';
    }
    $time = strtotime($d);
    if (!$time) return h($d);
    return date('d M Y', $time);
}

/**
 * Handle secure file upload for task attachments
 * Allows: jpg, jpeg, png, gif, pdf, doc, docx
 * Max size: 5MB
 */
function handle_task_attachment_upload($file_input_name, $upload_dir) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['status' => true, 'filename' => null];
    }

    $file = $_FILES[$file_input_name];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'error' => 'File upload encountered an error (Code: ' . $file['error'] . ').'];
    }

    // Maximum file size: 5MB (5 * 1024 * 1024 bytes)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['status' => false, 'error' => 'File size exceeds maximum allowed limit of 5MB.'];
    }

    // Allowed extensions and corresponding MIME types
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_extensions)) {
        return ['status' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, PDF, and DOC/DOCX files are permitted.'];
    }

    // Ensure uploads directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate safe unique filename
    $unique_prefix = 'kath_attach_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $new_filename = $unique_prefix . '.' . $file_extension;
    $target_path = rtrim($upload_dir, '/\\') . DIRECTORY_SEPARATOR . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['status' => true, 'filename' => $new_filename];
    } else {
        return ['status' => false, 'error' => 'Failed to save uploaded file to the server destination.'];
    }
}
