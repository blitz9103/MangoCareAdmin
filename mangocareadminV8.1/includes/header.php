<header class="header">
    <div class="header-content">

        <!-- LEFT SIDE -->
        <div class="header-left">
            <h1 class="logo">🍃 MangoCare Admin</h1>
        </div>

        <!-- RIGHT SIDE -->
        <div class="header-right">
            
            <!-- NOTIFICATION BELL & DROPDOWN -->
            <div class="notif-wrapper" id="notifWrapper">
                <button type="button" class="notif-bell-btn" id="notifBellBtn" title="Notifications" onclick="window.toggleNotifDropdown(event)">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge" id="navNotifBadge" style="display: none;">0</span>
                </button>

                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-header">
                        <div class="notif-header-title">
                            <i class="fas fa-bell" style="color: #70B9A1;"></i>
                            <span>Notifications</span>
                            <span class="notif-count-pill" id="dropdownUnreadPill">0 New</span>
                        </div>
                        <button type="button" class="btn-mark-all-read" onclick="window.MangoNotifs.markAllRead(event)">
                            <i class="fas fa-check-double"></i> Mark all read
                        </button>
                    </div>

                    <div class="notif-dropdown-body" id="headerNotifList">
                        <div class="notif-loading">
                            <i class="fas fa-spinner fa-spin"></i> Loading notifications...
                        </div>
                    </div>

                    <div class="notif-dropdown-footer">
                        <a href="../admin/notifications.php" class="btn-view-all-notifs">
                            View All Notifications <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- USER MENU -->
            <div class="user-menu">
                <span class="user-name">
                    <?php echo ucfirst(htmlspecialchars($_SESSION['admin_username'] ?? 'Admin')); ?>
                </span>
                <a href="../auth/logout.php" class="btn btn-sm btn-outline">
                    Logout
                </a>
            </div>
        </div>

    </div>
</header>

<style>
/* ── NOTIFICATION BELL & DROPDOWN STYLING ── */
.notif-wrapper {
    position: relative;
    display: inline-block;
}

.notif-bell-btn {
    background: rgba(112, 185, 161, 0.1);
    border: 1px solid rgba(112, 185, 161, 0.3);
    color: #004737;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.15rem;
    position: relative;
    transition: all 0.25s ease;
}

.notif-bell-btn:hover {
    background: #004737;
    color: #ffffff;
    transform: scale(1.05);
}

.notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #e63946;
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 5px rgba(230, 57, 70, 0.4);
    animation: pulseBadge 2s infinite;
}

@keyframes pulseBadge {
    0% { transform: scale(1); }
    50% { transform: scale(1.15); }
    100% { transform: scale(1); }
}

.notif-dropdown {
    display: none;
    position: absolute;
    top: 50px;
    right: 0;
    width: 380px;
    max-width: 90vw;
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 15px 35px rgba(0, 71, 55, 0.2);
    border: 1px solid rgba(112, 185, 161, 0.3);
    z-index: 9999;
    overflow: hidden;
    animation: fadeInDown 0.2s ease-out;
}

@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.notif-dropdown.show {
    display: block;
}

.notif-dropdown-header {
    background: linear-gradient(135deg, #004737 0%, #005343 100%);
    color: #ffffff;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: 'Inter', sans-serif;
}

.notif-header-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    font-weight: 700;
}

.notif-count-pill {
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.btn-mark-all-read {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.78rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: color 0.2s;
}

.btn-mark-all-read:hover {
    color: #ffffff;
    text-decoration: underline;
}

.notif-dropdown-body {
    max-height: 350px;
    overflow-y: auto;
    padding: 0;
}

.notif-loading {
    padding: 24px;
    text-align: center;
    color: #6c757d;
    font-size: 0.9rem;
}

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
    position: relative;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}

.notif-item:hover {
    background: #f8faf9;
}

.notif-item.unread {
    background: #f0fdf4;
}

.notif-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #004737;
}

.notif-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.notif-icon-critical { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
.notif-icon-high { background: #ffedd5; color: #ea580c; border: 1px solid #fdba74; }
.notif-icon-medium { background: #fef9c3; color: #ca8a04; border: 1px solid #fde047; }
.notif-icon-low { background: #dcfce7; color: #16a34a; border: 1px solid #86efac; }

.notif-content {
    flex: 1;
    min-width: 0;
}

.notif-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2px;
}

.notif-item-title {
    font-weight: 700;
    font-size: 0.85rem;
    color: #2c3e50;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notif-item-time {
    font-size: 0.72rem;
    color: #94a3b8;
    white-space: nowrap;
}

.notif-item-desc {
    font-size: 0.78rem;
    color: #64748b;
    margin: 0 0 6px 0;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notif-meta-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.notif-tree-badge {
    background: #e2e8f0;
    color: #334155;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 4px;
}

.notif-priority-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 4px;
    text-transform: uppercase;
}

.priority-critical { background: #dc2626; color: #ffffff; }
.priority-high { background: #ea580c; color: #ffffff; }
.priority-medium { background: #eab308; color: #000000; }
.priority-low { background: #16a34a; color: #ffffff; }

.notif-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.notif-item:hover .notif-actions {
    opacity: 1;
}

.btn-notif-action {
    background: none;
    border: none;
    color: #94a3b8;
    width: 24px;
    height: 24px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.75rem;
    transition: all 0.2s;
}

.btn-notif-action:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.btn-notif-action.delete:hover {
    background: #fee2e2;
    color: #dc2626;
}

.notif-dropdown-footer {
    background: #f8faf9;
    padding: 10px 16px;
    text-align: center;
    border-top: 1px solid #f0f0f0;
}

.btn-view-all-notifs {
    color: #004737;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s;
}

.btn-view-all-notifs:hover {
    color: #70B9A1;
}

.notif-empty-state {
    padding: 32px 16px;
    text-align: center;
    color: #94a3b8;
}

.notif-empty-state i {
    font-size: 2.5rem;
    color: #cbd5e1;
    margin-bottom: 8px;
}
</style>

<!-- Firebase & Notification Script -->
<script>
    /* ── Notification UI core (no Firebase SDK dependency) ────────────────────
       The bell toggle + a server-backed fallback controller so the dropdown
       and its actions keep working even if the SDK module below is slow or
       unavailable. When the module loads, it replaces window.MangoNotifs with
       the realtime-SDK version. */
    (function () {
        const _esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        window.MangoNotifs = {
            items: [],
            _ready: false,

            getTimeAgo(ts) {
                if (!ts) return 'Just now';
                let seconds = Math.max(0, Math.floor((Date.now() - Number(ts)) / 1000));
                if (seconds < 60) return 'Just now';
                const minutes = Math.floor(seconds / 60);
                if (minutes < 60) return `${minutes}m ago`;
                const hours = Math.floor(minutes / 60);
                if (hours < 24) return `${hours}h ago`;
                const days = Math.floor(hours / 24);
                if (days < 30) return `${days}d ago`;
                return `${Math.floor(days / 30)}mo ago`;
            },

            getPriorityIcon(priority) {
                switch (priority) {
                    case 'critical': return 'fa-exclamation-triangle';
                    case 'high': return 'fa-exclamation-circle';
                    case 'medium': return 'fa-info-circle';
                    default: return 'fa-check-circle';
                }
            },

            getCategoryIcon(cat) {
                switch (cat) {
                    case 'spray': return 'fa-spray-can';
                    case 'disease': return 'fa-virus';
                    case 'tree': return 'fa-tree';
                    case 'scan': return 'fa-microscope';
                    default: return 'fa-shield-virus';
                }
            },

            _post(action, id) {
                const body = new URLSearchParams();
                body.set('action', action);
                if (id) body.set('id', id);
                return fetch('notif_action.php', { method: 'POST', body })
                    .then(r => r.json())
                    .catch(() => ({ success: false }))
                    .then(res => {
                        if (res && res.success) {
                            if (res.unread !== undefined) this._updateBadges(res.unread);
                            window.dispatchEvent(new CustomEvent('mangoNotifsUpdated'));
                        }
                        return res;
                    });
            },

            refresh() {
                return fetch('notif_action.php?action=list')
                    .then(r => r.json())
                    .then(data => {
                        if (!data || !data.success) return;
                        this.items = data.items || [];
                        this._ready = true;
                        this._updateBadges(data.unread || 0);
                        this._renderDropdown();
                        window.dispatchEvent(new CustomEvent('mangoNotifsUpdated', { detail: { items: this.items, unreadCount: data.unread || 0 } }));
                    })
                    .catch(() => {});
            },

            markRead(id)    { return this._post('mark_read', id); },
            markUnread(id)  { return this._post('mark_unread', id); },
            markAllRead()   { return this._post('mark_all', ''); },
            deleteNotif(id) { return this._post('delete', id); },
            clearReadNotifs(){ return this._post('clear_read', ''); },

            _updateBadges(unread) {
                const nav = document.getElementById('navNotifBadge');
                if (nav) {
                    if (unread > 0) {
                        nav.textContent = unread > 99 ? '99+' : unread;
                        nav.style.display = 'inline-block';
                    } else {
                        nav.style.display = 'none';
                    }
                }
                const side = document.getElementById('sidebarNotifBadge');
                if (side) {
                    if (unread > 0) {
                        side.textContent = unread;
                        side.style.display = 'inline-block';
                    } else {
                        side.style.display = 'none';
                    }
                }
                const pill = document.getElementById('dropdownUnreadPill');
                if (pill) pill.textContent = `${unread} New`;
            },

            _renderDropdown() {
                const listEl = document.getElementById('headerNotifList');
                if (!listEl) return;
                const items = (this.items || []).slice(0, 5);
                if (items.length === 0) {
                    listEl.innerHTML = `
                        <div class="notif-empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p style="margin:0; font-weight:600;">No notifications</p>
                            <small>All caught up!</small>
                        </div>`;
                    return;
                }
                listEl.innerHTML = items.map(item => {
                    const iconClass = this.getPriorityIcon(item.priority);
                    const prioClass = `notif-icon-${item.priority || 'low'}`;
                    const prioBadge = `priority-${item.priority || 'low'}`;
                    const isUnread  = !item.read ? 'unread' : '';
                    const timeAgo   = this.getTimeAgo(item.timestamp);
                    const target    = _esc(item.target_url || 'schedules.php');
                    const title     = _esc(item.title || 'Notification');
                    const desc      = _esc(item.description || '');
                    const treeId    = _esc(item.tree_id || '');
                    const priority  = _esc(item.priority || 'low');
                    const id        = _esc(item.id || '');
                    return `
                        <div class="notif-item ${isUnread}" onclick="window.MangoNotifs.markRead('${id}'); window.location.href='${target}';">
                            <div class="notif-icon-box ${prioClass}">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-title-row">
                                    <h4 class="notif-item-title">${title}</h4>
                                    <span class="notif-item-time">${timeAgo}</span>
                                </div>
                                <p class="notif-item-desc">${desc}</p>
                                <div class="notif-meta-row">
                                    <span class="notif-priority-badge ${prioBadge}">${priority}</span>
                                    ${treeId ? `<span class="notif-tree-badge"><i class="fas fa-tree"></i> ${treeId}</span>` : ''}
                                    <div class="notif-actions" style="margin-left:auto;">
                                        ${!item.read ? `<button type="button" class="btn-notif-action" title="Mark as read" onclick="event.stopPropagation(); window.MangoNotifs.markRead('${id}');"><i class="fas fa-check"></i></button>` : ''}
                                        <button type="button" class="btn-notif-action delete" title="Delete" onclick="event.stopPropagation(); window.MangoNotifs.deleteNotif('${id}');"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                }).join('');
            }
        };

        /* Bell toggle + outside-click close (pure UI) */
        window.toggleNotifDropdown = function (e) {
            if (e) e.stopPropagation();
            const dropdown = document.getElementById('notifDropdown');
            if (!dropdown) return;
            const opening = !dropdown.classList.contains('show');
            dropdown.classList.toggle('show');
            if (opening && window.MangoNotifs && !window.MangoNotifs._ready &&
                typeof window.MangoNotifs.refresh === 'function') {
                window.MangoNotifs.refresh();
            }
        };

        document.addEventListener('click', function (e) {
            const wrapper = document.getElementById('notifWrapper');
            const dropdown = document.getElementById('notifDropdown');
            if (wrapper && dropdown && !wrapper.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // On the dedicated Notification Center, hydrate via the server fallback
        // when the SDK module is unavailable.
        document.addEventListener('DOMContentLoaded', function () {
            if (document.getElementById('notifFeed') &&
                window.MangoNotifs && !window.MangoNotifs._ready &&
                typeof window.MangoNotifs.refresh === 'function') {
                window.MangoNotifs.refresh();
            }
        });
    })();
</script>
<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
    import { getDatabase, ref, onValue, update, remove } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-database.js";

    const firebaseConfig = {
        apiKey: "<?php echo addslashes(FIREBASE_API_KEY); ?>",
        authDomain: "<?php echo addslashes(FIREBASE_AUTH_DOMAIN); ?>",
        databaseURL: "<?php echo addslashes(FIREBASE_DATABASE_URL); ?>",
        projectId: "<?php echo addslashes(FIREBASE_PROJECT_ID); ?>",
        storageBucket: "<?php echo addslashes(FIREBASE_STORAGE_BUCKET); ?>",
        messagingSenderId: "<?php echo addslashes(FIREBASE_MESSAGING_SENDER_ID); ?>",
        appId: "<?php echo addslashes(FIREBASE_APP_ID); ?>"
    };

    const app = initializeApp(firebaseConfig);
    const db = getDatabase(app);

    function escHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    // ── Shared realtime helpers (window.MangoFB) ─────────────────────────────
    function to12Hour(t) {
        if (!t) return '';
        const m = String(t).trim().match(/^(\d{1,2}):(\d{2})\s*(AM|PM)?$/i);
        if (!m) return t;
        let h = parseInt(m[1], 10);
        const min = m[2];
        const suffix = m[3] ? m[3].toUpperCase() : (h >= 12 ? 'PM' : 'AM');
        const h12 = ((h + 11) % 12) + 1;
        return `${h12}:${min} ${suffix}`;
    }

    function formatDate(dt, fmt) {
        const d = dt instanceof Date ? dt : new Date(dt);
        if (isNaN(d.getTime())) return '';
        const pad = n => String(n).padStart(2, '0');
        const mins = pad(d.getMinutes());
        return fmt.replace(/Y/g, d.getFullYear())
                  .replace(/M/g, d.toLocaleString('en', { month: 'short' }))
                  .replace(/d/g, pad(d.getDate()))
                  .replace(/j/g, d.getDate())
                  .replace(/H/g, pad(d.getHours()))
                  .replace(/g/g, ((d.getHours() + 11) % 12) + 1)
                  .replace(/h/g, pad(((d.getHours() + 11) % 12) + 1))
                  .replace(/i/g, mins)
                  .replace(/A/g, d.getHours() >= 12 ? 'PM' : 'AM');
    }

    // Normalize a schedule date field (spray_date / date / timestamp ms|s) → 'YYYY-MM-DD'
    function scheduleDateStr(s) {
        if (s.spray_date) return String(s.spray_date).slice(0, 10);
        if (s.date) return String(s.date).slice(0, 10);
        if (s.scheduled_date) return String(s.scheduled_date).slice(0, 10);
        if (s.timestamp) {
            const ts = Number(s.timestamp) > 9999999999 ? Number(s.timestamp) / 1000 : Number(s.timestamp);
            if (ts > 0) return new Date(ts * 1000).toISOString().slice(0, 10);
        }
        return '';
    }

    function badgeFor(status) {
        const s = String(status || 'pending').toLowerCase();
        if (s === 'completed' || s === 'done' || s === 'sprayed' || s === 'finished' || s === 'complete') {
            return { cls: 'badge-success', label: s === 'sprayed' ? 'Sprayed' : (s === 'done' ? 'Done' : 'Completed') };
        }
        if (s === 'overdue' || s === 'cancelled') {
            return { cls: 'badge-danger', label: s === 'overdue' ? 'Overdue' : 'Cancelled' };
        }
        return { cls: 'badge-warning', label: 'Pending' };
    }

    window.MangoFB = {
        db, ref, onValue, update, remove, push, set,
        escHtml,
        getTimeAgo: (ts) => window.MangoNotifs ? window.MangoNotifs.getTimeAgo(ts) : 'Just now',
        to12Hour, formatDate, scheduleDateStr, badgeFor
    };

    // Global Notification Controller
    window.MangoNotifs = {
        items: [],
        
        getTimeAgo(ts) {
            if (!ts) return 'Just now';
            let seconds = Math.floor((Date.now() - ts) / 1000);
            if (seconds < 0) seconds = 0;
            if (seconds < 60) return 'Just now';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return `${minutes}m ago`;
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return `${hours}h ago`;
            const days = Math.floor(hours / 24);
            if (days < 30) return `${days}d ago`;
            return `${Math.floor(days / 30)}mo ago`;
        },

        getPriorityIcon(priority) {
            switch(priority) {
                case 'critical': return 'fa-exclamation-triangle';
                case 'high': return 'fa-exclamation-circle';
                case 'medium': return 'fa-info-circle';
                case 'low': default: return 'fa-check-circle';
            }
        },

        getCategoryIcon(cat) {
            switch(cat) {
                case 'spray': return 'fa-spray-can';
                case 'disease': return 'fa-virus';
                case 'tree': return 'fa-tree';
                case 'scan': return 'fa-microscope';
                case 'critical': default: return 'fa-shield-virus';
            }
        },

        markRead(id, e) {
            if (e) e.stopPropagation();
            if (!id) return;
            const notifRef = ref(db, `notifications/${id}`);
            update(notifRef, { read: true });
        },

        markAllRead(e) {
            if (e) e.stopPropagation();
            const updates = {};
            this.items.forEach(item => {
                if (!item.read) {
                    updates[`notifications/${item.id}/read`] = true;
                }
            });
            if (Object.keys(updates).length > 0) {
                update(ref(db), updates);
            }
        },

        deleteNotif(id, e) {
            if (e) e.stopPropagation();
            if (!id) return;
            remove(ref(db, `notifications/${id}`));
        },

        clearReadNotifs() {
            const updates = {};
            this.items.forEach(item => {
                if (item.read) {
                    updates[`notifications/${item.id}`] = null;
                }
            });
            if (Object.keys(updates).length > 0) {
                update(ref(db), updates);
            }
        }
    };

    // Listen to Firebase /notifications node in real-time
    const notifsRef = ref(db, 'notifications');
    onValue(notifsRef, (snapshot) => {
        const val = snapshot.val() || {};
        const items = Object.values(val).sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0));
        window.MangoNotifs.items = items;
        window.MangoNotifs._ready = true;

        const unreadCount = items.filter(i => !i.read).length;

        // 1. Update Header Nav Badge
        const navBadge = document.getElementById('navNotifBadge');
        if (navBadge) {
            if (unreadCount > 0) {
                navBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                navBadge.style.display = 'inline-block';
            } else {
                navBadge.style.display = 'none';
            }
        }

        // 2. Update Sidebar Badge if present
        const sidebarBadge = document.getElementById('sidebarNotifBadge');
        if (sidebarBadge) {
            if (unreadCount > 0) {
                sidebarBadge.textContent = unreadCount;
                sidebarBadge.style.display = 'inline-block';
            } else {
                sidebarBadge.style.display = 'none';
            }
        }

        // 3. Update Dropdown Header Pill
        const dropdownPill = document.getElementById('dropdownUnreadPill');
        if (dropdownPill) {
            dropdownPill.textContent = `${unreadCount} New`;
        }

        // 4. Update Dropdown List (Top 5)
        const headerList = document.getElementById('headerNotifList');
        if (headerList) {
            const top5 = items.slice(0, 5);

            if (top5.length === 0) {
                headerList.innerHTML = `
                    <div class="notif-empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <p style="margin:0; font-weight: 600;">No notifications</p>
                        <small>All caught up!</small>
                    </div>`;
            } else {
                headerList.innerHTML = top5.map(item => {
                    const iconClass = window.MangoNotifs.getPriorityIcon(item.priority);
                    const prioClass = `notif-icon-${item.priority || 'low'}`;
                    const prioBadge = `priority-${item.priority || 'low'}`;
                    const isUnread  = !item.read ? 'unread' : '';
                    const timeAgo   = window.MangoNotifs.getTimeAgo(item.timestamp);
                    const target    = escHtml(item.target_url || 'schedules.php');
                    const title     = escHtml(item.title || 'Notification');
                    const desc      = escHtml(item.description || '');
                    const treeId    = escHtml(item.tree_id || '');
                    const priority  = escHtml(item.priority || 'low');
                    const id        = escHtml(item.id || '');

                    return `
                        <div class="notif-item ${isUnread}" onclick="window.MangoNotifs.markRead('${id}'); window.location.href='${target}';">
                            <div class="notif-icon-box ${prioClass}">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-title-row">
                                    <h4 class="notif-item-title">${title}</h4>
                                    <span class="notif-item-time">${timeAgo}</span>
                                </div>
                                <p class="notif-item-desc">${desc}</p>
                                <div class="notif-meta-row">
                                    <span class="notif-priority-badge ${prioBadge}">${priority}</span>
                                    ${treeId ? `<span class="notif-tree-badge"><i class="fas fa-tree"></i> ${treeId}</span>` : ''}
                                    <div class="notif-actions" style="margin-left:auto;">
                                        ${!item.read ? `<button type="button" class="btn-notif-action" title="Mark as read" onclick="window.MangoNotifs.markRead('${id}', event)"><i class="fas fa-check"></i></button>` : ''}
                                        <button type="button" class="btn-notif-action delete" title="Delete" onclick="window.MangoNotifs.deleteNotif('${id}', event)"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                }).join('');
            }
        }

        // Trigger custom event for dedicated notifications page if open
        window.dispatchEvent(new CustomEvent('mangoNotifsUpdated', { detail: { items, unreadCount } }));
    });

    // Real-time refresh: periodically reconcile notifications with the server so
    // the RTDB stays accurate (new scans/schedules) without a full page reload.
    let _notifSyncing = false;
    setInterval(() => {
        if (_notifSyncing) return;
        _notifSyncing = true;
        fetch('notif_sync.php', { method: 'POST' })
            .catch(() => {})
            .finally(() => { _notifSyncing = false; });
    }, 15000);
</script>