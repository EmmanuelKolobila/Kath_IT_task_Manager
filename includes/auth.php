<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * General Authentication Guard (For all authenticated pages)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    set_flash('warning', 'Please log in to access the IT Task Management system.');
    redirect('login.php');
}

// Fetch fresh user record from database to verify account is still active
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, full_name, username, email, phone, role, department_id, status FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user']['id']]);
        $currentUser = $stmt->fetch();

        if (!$currentUser || $currentUser['status'] !== 'active') {
            unset($_SESSION['user']);
            session_destroy();
            session_start();
            set_flash('danger', 'Your account has been deactivated or does not exist. Please contact the IT Administrator.');
            redirect('login.php');
        }

        // Keep session updated with fresh data
        $_SESSION['user'] = $currentUser;
    } catch (PDOException $e) {
        // If query fails, fall back to existing session
    }
}
