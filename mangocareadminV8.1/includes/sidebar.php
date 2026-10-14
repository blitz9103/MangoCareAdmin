<aside class="sidebar" id="sidebar">
    <!-- Sidebar Header with Logo/Title -->
    <div class="sidebar-header">
    </div>

    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <a href="../admin/index.php" 
           class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <i class="fas fa-chart-line"></i>
            </span>
            <span class="nav-text">Dashboard</span>
            <?php if(basename($_SERVER['PHP_SELF']) === 'index.php'): ?>
                <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>

        <!-- Scan History -->
        <a href="../admin/scans.php" 
           class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'scans.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <i class="fas fa-microscope"></i>
            </span>
            <span class="nav-text">Scan History</span>
            <?php if(basename($_SERVER['PHP_SELF']) === 'scans.php'): ?>
                <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>

        <!-- Mango Trees -->
        <a href="../admin/trees.php" 
           class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'trees.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <i class="fas fa-tree"></i>
            </span>
            <span class="nav-text">Mango Trees</span>
            <?php if(basename($_SERVER['PHP_SELF']) === 'trees.php'): ?>
                <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>

        <!-- Tree Submissions -->
        <a href="../admin/tree_map.php"
           class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'tree_map.php' ? 'active' : ''; ?>"
           id="submissionsNavLink"
           title="View pending farmer tree submissions on the map">
            <span class="nav-icon">
                <i class="fas fa-inbox"></i>
            </span>
            <span class="nav-text">Tree Submissions</span>
            <span id="submissionsBadge" style="
                display:none;
                background:#2563EB;
                color:#fff;
                border-radius:10px;
                font-size:11px;
                font-weight:700;
                padding:1px 7px;
                margin-left:auto;
                min-width:18px;
                text-align:center;
            ">0</span>
            <?php if(basename($_SERVER['PHP_SELF']) === 'tree_map.php'): ?>
                <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>

        <!-- Tree Monitoring Map (Defense) -->
        <a href="../admin/tree_map.php" 
           class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'tree_map.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <i class="fas fa-map-marked-alt"></i>
            </span>
            <span class="nav-text">Tree Map</span>
            <?php if(basename($_SERVER['PHP_SELF']) === 'tree_map.php'): ?>
                <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>

        <!-- QR Code Generator -->
        <a href="../admin/qr-generator.php" 
           class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'qr-generator.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <i class="fas fa-qrcode"></i>
            </span>
            <span class="nav-text">QR Code Generator</span>
            <?php if(basename($_SERVER['PHP_SELF']) === 'qr-generator.php'): ?>
                <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>

        <!-- Notifications -->
        <a href="../admin/notifications.php" 
           class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <i class="fas fa-bell"></i>
            </span>
            <span class="nav-text">Notifications</span>
            <span id="sidebarNotifBadge" class="notif-badge" style="display:none; position:relative; top:0; right:0; margin-left:auto; animation:none;">0</span>
            <?php if(basename($_SERVER['PHP_SELF']) === 'notifications.php'): ?>
                <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>

        <!-- Reports -->
        <a href="../admin/reports.php" 
           class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <i class="fas fa-chart-pie"></i>
            </span>
            <span class="nav-text">Reports & Analytics</span>
            <?php if(basename($_SERVER['PHP_SELF']) === 'reports.php'): ?>
                <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>

        <!-- Manage Accounts -->
        <a href="../admin/manageaccount.php" 
           class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'manageaccount.php' ? 'active' : ''; ?>">
            <span class="nav-icon">
                <i class="fas fa-users-cog"></i>
            </span>
            <span class="nav-text">Manage Accounts</span>
            <?php if(basename($_SERVER['PHP_SELF']) === 'manageaccount.php'): ?>
                <span class="nav-indicator"></span>
            <?php endif; ?>
        </a>
    </nav>

   
</aside>

<style>
:root {
    --sidebar-width: 280px;
    --sidebar-collapsed-width: 80px;
    --primary-color: #004737;
    --secondary-color: #70B9A1;
    --sidebar-bg: #ffffff;
    --sidebar-hover: rgba(112, 185, 161, 0.1);
    --sidebar-active: linear-gradient(135deg, #004737 0%, #1b6b58 100%);
    --text-primary: #2c3e50;
    --text-secondary: #6c757d;
    --text-light: #ffffff;
    --border-color: #dee2e6;
    --card-shadow: 0 4px 6px rgba(0, 71, 55, 0.1);
}

.sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: var(--sidebar-bg);
    box-shadow: var(--card-shadow);
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    z-index: 1000;
    border-right: 1px solid rgba(112, 185, 161, 0.2);
}

.sidebar.collapsed {
    width: var(--sidebar-collapsed-width);
}

.sidebar.collapsed .nav-text,
.sidebar.collapsed .user-details,
.sidebar.collapsed .sidebar-logo span {
    display: none;
}

.sidebar.collapsed .nav-icon {
    margin-right: 0;
    font-size: 1.5rem;
}

.sidebar.collapsed .sidebar-footer {
    justify-content: center;
}

.sidebar.collapsed .logout-btn {
    margin: 0;
}

.sidebar.collapsed .user-avatar {
    margin: 0 auto;
}

/* Sidebar Header */
.sidebar-header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid var(--secondary-color);
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--primary-color);
}

.sidebar-logo i {
    font-size: 1.5rem;
    color: var(--secondary-color);
}

.sidebar-toggle {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--light-color);
    border: 1px solid rgba(112, 185, 161, 0.3);
    color: var(--secondary-color);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.sidebar-toggle:hover {
    background: var(--secondary-color);
    color: white;
    transform: scale(1.1);
}

/* Navigation */
.sidebar-nav {
    flex: 1;
    padding: 1.5rem 0;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--secondary-color) var(--light-color);
}

.sidebar-nav::-webkit-scrollbar {
    width: 5px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: var(--light-color);
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--secondary-color);
    border-radius: 10px;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 0.875rem 1.5rem;
    margin: 0.25rem 1rem;
    color: var(--text-primary);
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.3s;
    position: relative;
    gap: 12px;
}

.nav-item:hover {
    background: var(--sidebar-hover);
    transform: translateX(5px);
}

.nav-item.active {
    background: var(--sidebar-active);
    color: var(--text-light);
    box-shadow: 0 4px 10px rgba(0, 71, 55, 0.2);
}

.nav-item.active .nav-icon {
    color: white;
}

.nav-item.active .nav-indicator {
    position: absolute;
    right: -1rem;
    width: 4px;
    height: 70%;
    background: white;
    border-radius: 4px 0 0 4px;
}

.nav-icon {
    font-size: 1.25rem;
    min-width: 24px;
    text-align: center;
    color: var(--secondary-color);
    transition: all 0.3s;
}

.nav-item.active .nav-icon {
    color: white;
}

.nav-text {
    font-size: 0.95rem;
    font-weight: 500;
    white-space: nowrap;
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid rgba(112, 185, 161, 0.2);
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.user-details {
    flex: 1;
    min-width: 0;
}

.user-name {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    display: block;
    font-size: 0.75rem;
    color: var(--secondary-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.logout-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--danger-color);
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid rgba(220, 53, 69, 0.2);
}

.logout-btn:hover {
    background: var(--danger-color);
    color: white;
    transform: scale(1.1);
}

/* Main content adjustment */
.main-content {
    margin-left: var(--sidebar-width);
    transition: margin-left 0.3s ease;
}

.sidebar.collapsed + .main-content {
    margin-left: var(--sidebar-collapsed-width);
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0 !important;
    }
}

/* Tooltip for collapsed state */
.sidebar.collapsed .nav-item {
    position: relative;
}

.sidebar.collapsed .nav-item:hover .nav-text {
    display: block !important;
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: var(--primary-color);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.875rem;
    white-space: nowrap;
    margin-left: 10px;
    z-index: 1000;
    box-shadow: var(--card-shadow);
}

.sidebar.collapsed .nav-item:hover .nav-text::before {
    content: '';
    position: absolute;
    left: -5px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-top: 5px solid transparent;
    border-bottom: 5px solid transparent;
    border-right: 5px solid var(--primary-color);
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed');
    
    // Rotate toggle icon
    const toggleIcon = document.querySelector('#sidebarToggle i');
    if (sidebar.classList.contains('collapsed')) {
        toggleIcon.classList.remove('fa-chevron-left');
        toggleIcon.classList.add('fa-chevron-right');
        localStorage.setItem('sidebarCollapsed', 'true');
    } else {
        toggleIcon.classList.remove('fa-chevron-right');
        toggleIcon.classList.add('fa-chevron-left');
        localStorage.setItem('sidebarCollapsed', 'false');
    }
}

// Check localStorage for sidebar state
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (collapsed) {
        sidebar.classList.add('collapsed');
        const toggleIcon = document.querySelector('#sidebarToggle i');
        if (toggleIcon) {
            toggleIcon.classList.remove('fa-chevron-left');
            toggleIcon.classList.add('fa-chevron-right');
        }
    }
    
    // Mobile menu handling
    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
        });
    }
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const isMobile = window.innerWidth <= 768;
        if (isMobile && sidebar.classList.contains('mobile-open')) {
            if (!sidebar.contains(event.target) && !menuToggle?.contains(event.target)) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });
});

// Handle responsive behavior
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('mobile-open');
    }
});

/* ── Pending Submissions Badge ─────────────────────────────────────────
   Polls submissions_api.php every 30 seconds and shows a blue count
   badge on the "Tree Submissions" nav item when pending items exist.
──────────────────────────────────────────────────────────────────────── */
function updateSubmissionsBadge() {
    fetch('../admin/submissions_api.php?t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            const count  = Object.keys(data).length;
            const badge  = document.getElementById('submissionsBadge');
            if (!badge) return;
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(() => {}); // fail silently
}

// Run once on load, then every 30 s
document.addEventListener('DOMContentLoaded', function() {
    updateSubmissionsBadge();
    setInterval(updateSubmissionsBadge, 30000);
});

</script>