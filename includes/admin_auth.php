<?php
/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Admin-Only Authorization Guard
 */

require_once __DIR__ . '/auth.php';

if (!is_admin()) {
    set_flash('danger', 'Access denied. You do not have permission to access administrator resources.');
    redirect('staff/dashboard.php');
}
