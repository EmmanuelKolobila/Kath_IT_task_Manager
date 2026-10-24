/**
 * Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
 * Client-side JavaScript enhancements
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Sidebar Toggle for Mobile & Responsive screens
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    let overlay = document.querySelector('.sidebar-overlay');

    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // 2. Initialize Bootstrap 5 Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 3. Confirmation Dialog for destructive or sensitive actions
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(function (button) {
        button.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 4. Auto-dismiss Alert banners after 7 seconds
    const autoDismissAlerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    autoDismissAlerts.forEach(function (alertEl) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 7000);
    });

    // 5. File upload validation (Client-side helper)
    const fileInputs = document.querySelectorAll('input[type="file"][data-max-size]');
    fileInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            const maxSize = parseInt(this.getAttribute('data-max-size'), 10); // in bytes
            if (this.files && this.files[0]) {
                if (this.files[0].size > maxSize) {
                    alert('The selected file is too large. Maximum allowed size is ' + (maxSize / (1024 * 1024)) + ' MB.');
                    this.value = '';
                }
            }
        });
    });
});
