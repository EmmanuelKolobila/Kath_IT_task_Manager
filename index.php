<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Root Index Router
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('staff/dashboard.php');
    }
} else {
    redirect('login.php');
}
