// ==============================
// CONFIRMATION DIALOGS
// ==============================

// Delete action (any button with class 'btn-delete' or onclick confirm)
document.addEventListener('DOMContentLoaded', function() {
    // All delete links (danger buttons with confirm)
    document.querySelectorAll('.btn-danger, .btn-delete, .btn-warning[onclick*="confirm"]').forEach(btn => {
        // Only add if not already handled
        if (!btn.hasAttribute('data-confirm-added')) {
            btn.setAttribute('data-confirm-added', 'true');
            btn.addEventListener('click', (e) => {
                if (!confirm('Are you sure? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        }
    });

    // Archive confirm (buttons with class 'btn-archive')
    document.querySelectorAll('.btn-archive').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm('Archive this mail? It will be moved to archive.')) {
                e.preventDefault();
            }
        });
    });

    // Restore confirm
    document.querySelectorAll('.btn-restore').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm('Restore this mail to inbox?')) {
                e.preventDefault();
            }
        });
    });

    // Reset password confirm
    document.querySelectorAll('.btn-reset').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm('Reset password to "admin123"?')) {
                e.preventDefault();
            }
        });
    });
});

// ==============================
// AUTO-HIDE ALERTS AFTER 5 SECONDS
// ==============================
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// ==============================
// TOOLTIPS (Bootstrap 5)
// ==============================
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// ==============================
// SIDEBAR TOGGLE FOR MOBILE (optional)
// ==============================
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// Add toggle button if needed (you can add a hamburger icon in header)
// For now, we keep it as is.

// ==============================
// AJAX UNREAD COUNT (optional)
// ==============================
function updateUnreadCount() {
    fetch('../backend/api/getUnreadCount.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.unread-badge');
            if (badge) {
                badge.textContent = data.unread || 0;
                if (data.unread > 0) badge.style.display = 'inline-block';
                else badge.style.display = 'none';
            }
        })
        .catch(error => console.error('Error fetching unread count:', error));
}

// Call every 30 seconds if needed
// setInterval(updateUnreadCount, 30000);