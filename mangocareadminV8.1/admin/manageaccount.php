<?php
require_once '../config/config.php';
requireLogin();

$firebase = new Firebase();

// Get all users from Firebase
$users = $firebase->get('Users') ?? [];
$users = is_array($users) ? $users : [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- PAGE BANNER -->
        <div class="page-banner">
            <div class="banner-left">
                <div class="banner-icon"><i class="fas fa-users-cog"></i></div>
                <div class="banner-text">
                    <h1>Manage Accounts</h1>
                    <p>View, create, enable/disable and delete user accounts</p>
                </div>
            </div>
            <div class="banner-badge" id="maBannerCount"><i class="fas fa-users"></i> <?= count($users); ?> Users</div>
        </div>

        <!-- ALERT MESSAGES -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; background: #EAF6F2; border-left: 4px solid #005343; color: #004737; border-radius: 8px; font-weight: 600;">
                <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i> <?= htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['err'])): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; background: #FDE8E8; border-left: 4px solid #E53E3E; color: #C53030; border-radius: 8px; font-weight: 600;">
                <i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i> <?= htmlspecialchars($_GET['err']); ?>
            </div>
        <?php endif; ?>

        <!-- STATS -->
        <?php
        $totalUsers = count($users);
        $adminCount = count(array_filter($users, function ($user) {
            return is_array($user) && ($user['role'] ?? 'worker') === 'admin';
        }));
        $activeCount = count(array_filter($users, function ($user) {
            if (!is_array($user)) return false;
            $st = strtolower(trim((string)($user['status'] ?? 'active')));
            return !in_array($st, ['disabled', 'inactive', 'banned', 'false', '0', 'no', 'off'], true);
        }));
        ?>
        <div class="stats-grid" style="margin-bottom: 1.75rem;">
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3 id="maStatTotal"><?= $totalUsers; ?></h3>
                    <p><i class="fas fa-user"></i> Total Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrapper"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3 id="maStatActive"><?= $activeCount; ?></h3>
                    <p><i class="fas fa-check-circle"></i> Active Users</p>
                </div>
            </div>
        </div>

        <!-- ACCOUNTS TABLE -->
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="fas fa-users"></i> User Accounts</span>
                <span style="display:flex;gap:.75rem;align-items:center;">
                    <span class="data-card-count" id="maRowCount"><?= $totalUsers; ?> users</span>
                    <button class="btn btn-sm btn-primary" onclick="openModal()"><i class="fas fa-user-plus"></i> Add
                        User</button>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> User</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-tag"></i> Role</th>
                            <th><i class="fas fa-circle"></i> Status</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody id="maTbody">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-users-slash"></i></div>
                                        <h3>No users found</h3>
                                        <p>Add your first user to get started</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $uid => $user): ?>
                                <?php
                                if (!is_array($user)) continue;
                                $role = $user['role'] ?? 'worker';
                                $rawStatus = strtolower(trim((string)($user['status'] ?? 'active')));
                                $isActive = !in_array($rawStatus, ['disabled', 'inactive', 'banned', 'false', '0', 'no', 'off'], true);
                                $statusText = $isActive ? 'active' : 'disabled';
                                ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar-sm"><?= strtoupper(substr($user['name'] ?? 'U', 0, 2)); ?>
                                            </div>
                                            <div class="user-meta">
                                                <span
                                                    class="user-name-text"><?= htmlspecialchars($user['name'] ?? 'N/A'); ?></span>
                                                <span
                                                    class="user-email-text"><?= htmlspecialchars($user['email'] ?? ''); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><i class="fas fa-envelope" style="color:var(--accent);margin-right:4px;"></i>
                                        <?= htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?= $role === 'admin' ? 'badge-admin' : 'badge-active'; ?>">
                                            <i class="fas <?= $role === 'admin' ? 'fa-crown' : 'fa-hard-hat'; ?>"></i>
                                            <?= ucfirst($role); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $isActive ? 'badge-success' : 'badge-danger'; ?>">
                                            <i class="fas <?= $isActive ? 'fa-check-circle' : 'fa-ban'; ?>"></i>
                                            <?= $isActive ? 'Active' : 'Disabled'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons" style="display:flex; gap:0.5rem;">
                                            <a href="user-profile.php?uid=<?= urlencode($uid); ?>"
                                                class="btn btn-sm btn-primary" title="View user profile"
                                                style="min-width: 100px; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                                                <i class="fas fa-eye"></i> View Profile
                                            </a>
                                            <button
                                                class="btn btn-sm <?= $isActive ? 'btn-danger' : 'btn-success'; ?>"
                                                onclick="toggleStatus('<?= htmlspecialchars($uid, ENT_QUOTES); ?>', '<?= $statusText; ?>')"
                                                title="<?= $isActive ? 'Disable user account' : 'Enable user account'; ?>"
                                                style="min-width: 100px;">
                                                <i class="fas <?= $isActive ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                <?= $isActive ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- ADD USER MODAL -->
        <div id="addUserModal" class="modal">
            <div class="modal-content">
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
                <h2><i class="fas fa-user-plus"></i> Add New User</h2>
                <form method="POST" action="create-user.php">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" placeholder="Full Name" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Email Address" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Password" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" class="form-control">
                            <option value="worker">Worker</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i>
                            Create</button>
                        <button type="button" class="btn btn-outline" onclick="closeModal()"><i
                                class="fas fa-times-circle"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function openModal() {
                document.getElementById('addUserModal').style.display = 'flex';
                // Prevent scrolling when modal is open
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                document.getElementById('addUserModal').style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            function toggleStatus(uid, currentStatus) {
                const action = currentStatus === 'active' ? 'disable' : 'enable';
                if (confirm(`⚠️ Are you sure you want to ${action} this user?`)) {
                    window.location.href = "toggle-user.php?uid=" + uid + "&status=" + currentStatus;
                }
            }

            // Close modal when clicking outside
            window.onclick = function (event) {
                const modal = document.getElementById('addUserModal');
                if (event.target === modal) {
                    closeModal();
                }
            }

            // Close modal with Escape key
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        </script>

        <script type="module">
            const _fb = window.MangoFB;
            if (_fb && _fb.db) {
                const { db, ref, onValue, escHtml } = _fb;

                function isActiveUser(user) {
                    if (!user || typeof user !== 'object') return false;
                    const st = String(user.status || 'active').toLowerCase().trim();
                    return ['disabled', 'inactive', 'banned', 'false', '0', 'no', 'off'].indexOf(st) === -1;
                }

                function renderUsers(snapshot) {
                    const users = snapshot.val() || {};
                    const entries = Object.entries(users).filter(([, u]) => u && typeof u === 'object');
                    const total = entries.length;
                    const adminCount = entries.filter(([, u]) => (u.role || 'worker') === 'admin').length;
                    const activeCount = entries.filter(([, u]) => isActiveUser(u)).length;

                    const setText = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
                    setText('maStatTotal', total);
                    setText('maStatActive', activeCount);
                    setText('maRowCount', `${total} users`);
                    const banner = document.getElementById('maBannerCount');
                    if (banner) banner.innerHTML = `<i class="fas fa-users"></i> ${total} Users`;

                    const tbody = document.getElementById('maTbody');
                    if (!tbody) return;
                    if (entries.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-users-slash"></i></div>
                                        <h3>No users found</h3>
                                        <p>Add your first user to get started</p>
                                    </div>
                                </td>
                            </tr>`;
                        return;
                    }

                    tbody.innerHTML = entries.map(([uid, user]) => {
                        const role = user.role || 'worker';
                        const isActive = isActiveUser(user);
                        const statusText = isActive ? 'active' : 'disabled';
                        const uidE = escHtml(uid);
                        const name = escHtml(user.name || 'N/A');
                        const email = escHtml(user.email || '');
                        const initials = escHtml(String(user.name || 'U').slice(0, 2).toUpperCase());
                        const roleBadge = role === 'admin'
                            ? `<span class="badge badge-admin"><i class="fas fa-crown"></i> ${escHtml(role.charAt(0).toUpperCase() + role.slice(1))}</span>`
                            : `<span class="badge badge-active"><i class="fas fa-hard-hat"></i> ${escHtml(role.charAt(0).toUpperCase() + role.slice(1))}</span>`;
                        const statusBadge = isActive
                            ? `<span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>`
                            : `<span class="badge badge-danger"><i class="fas fa-ban"></i> Disabled</span>`;
                        const toggleBtn = isActive
                            ? `<button class="btn btn-sm btn-danger" onclick="toggleStatus('${uidE}', 'active')" title="Disable user account" style="min-width: 100px;"><i class="fas fa-ban"></i> Disable</button>`
                            : `<button class="btn btn-sm btn-success" onclick="toggleStatus('${uidE}', 'disabled')" title="Enable user account" style="min-width: 100px;"><i class="fas fa-check"></i> Enable</button>`;

                        return `
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar-sm">${initials}</div>
                                        <div class="user-meta">
                                            <span class="user-name-text">${name}</span>
                                            <span class="user-email-text">${email}</span>
                                        </div>
                                    </div>
                                </td>
                                <td><i class="fas fa-envelope" style="color:var(--accent);margin-right:4px;"></i> ${email || 'N/A'}</td>
                                <td>${roleBadge}</td>
                                <td>${statusBadge}</td>
                                <td>
                                    <div class="action-buttons" style="display:flex; gap:0.5rem;">
                                        <a href="user-profile.php?uid=${encodeURIComponent(uid)}" class="btn btn-sm btn-primary" title="View user profile" style="min-width: 100px; display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                                            <i class="fas fa-eye"></i> View Profile
                                        </a>
                                        ${toggleBtn}
                                    </div>
                                </td>
                            </tr>`;
                    }).join('');
                }

                onValue(ref(db, 'Users'), renderUsers);
            }
        </script>
</body>

</html>