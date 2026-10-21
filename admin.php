<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();
auth_require_role('admin');

$adminName = (string) ($_SESSION['user']['name'] ?? 'Paw Net Administrator');
$adminEmail = (string) ($_SESSION['user']['email'] ?? 'admin@pawnet.local');
$nameParts = preg_split('/\s+/', trim($adminName)) ?: [$adminName];
$initials = strtoupper(substr($nameParts[0] ?? 'A', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= auth_escape(auth_csrf_token()) ?>">
    <title>Admin Dashboard · Paw Net</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        :root {
            --purple: #7056df;
            --purple-hover: #5d43cf;
            --ink: #28263b;
            --muted: #85849a;
            --line: #eceaf2;
            --bg: #f8f7fb;
            --green: #16815d;
            --red: #d32f2f;
            --amber: #b45309;
        }
        .admin-badge {
            background: #ede9fe;
            color: var(--purple);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        .table-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 7px;
            cursor: pointer;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
        }
        .btn-sm:hover {
            background: #f4f3f9;
        }
        .btn-sm.danger {
            color: var(--red);
            border-color: #fca5a5;
            background: #fef2f2;
        }
        .btn-sm.danger:hover {
            background: #fee2e2;
        }
        .btn-sm.success {
            color: var(--green);
            border-color: #bbf7d0;
            background: #f0fdf4;
        }
        .btn-sm.primary {
            background: var(--purple);
            color: #fff;
            border-color: var(--purple);
        }
        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
        }
        .filter-tab.active {
            background: #f1eeff;
            color: var(--purple);
            border-color: #dcd4ff;
        }
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(40, 38, 59, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 16px;
        }
        .modal-overlay.open {
            display: flex;
        }
        .modal-card {
            background: #fff;
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .modal-card h3 {
            margin: 0 0 12px;
            font-size: 18px;
        }
        .modal-card form {
            display: grid;
            gap: 12px;
        }
        .modal-card label {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            display: grid;
            gap: 4px;
        }
        .modal-card input, .modal-card textarea, .modal-card select {
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
        }
        .modal-card .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 12px;
        }
    </style>
</head>
<body>

<div class="app-shell">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-icon">🐾</div>
            <div>
                <strong>Paw Net</strong>
                <span>Admin Portal</span>
            </div>
        </div>

        <nav class="side-nav">
            <p class="nav-label">OVERSIGHT</p>
            <button class="nav-item active" data-section="overview">
                <span class="nav-icon">▦</span><span>Overview</span>
            </button>
            <button class="nav-item" data-section="users">
                <span class="nav-icon">◎</span><span>Users & Roles</span>
            </button>
            <button class="nav-item" data-section="sellers">
                <span class="nav-icon">🏡</span><span>Seller Directory</span>
            </button>
            <button class="nav-item" data-section="pets">
                <span class="nav-icon">🐕</span><span>Pet Listings</span>
            </button>
            <button class="nav-item" data-section="applications">
                <span class="nav-icon">▤</span><span>Applications</span>
            </button>
            <button class="nav-item" data-section="recycle-bin">
                <span class="nav-icon">🗑</span><span>Recycle Bin</span>
            </button>
            <button class="nav-item" data-section="warnings">
                <span class="nav-icon">✉</span><span>Send Warnings</span>
            </button>
            <button class="nav-item" data-section="audit">
                <span class="nav-icon">🛡</span><span>Audit Log</span>
            </button>
        </nav>

        <form action="logout.php" method="post" style="margin-top: auto;">
            <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
            <button class="logout-btn" id="logoutBtn" type="submit"><span>↪</span> Sign Out</button>
        </form>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Open menu">☰</button>
            <div class="search-box">
                <span>⌕</span>
                <input id="adminGlobalSearch" type="search" placeholder="Search platform records...">
            </div>
            <div class="topbar-actions">
                <div class="user-menu">
                    <div class="avatar" style="background: #ede9fe; color: #7056df;"><?= auth_escape($initials) ?></div>
                    <div class="user-summary">
                        <strong><?= auth_escape($adminName) ?></strong>
                        <span class="admin-badge">Admin</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- SECTION: OVERVIEW -->
        <section class="dashboard-section active" id="overview">
            <div class="page-heading dashboard-hero">
                <div>
                    <p class="eyebrow">ADMINISTRATION & SAFETY</p>
                    <h1>Platform Control Center</h1>
                    <p>Live health, metrics, and records for the Paw Net community.</p>
                </div>
            </div>

            <div class="stats-grid">
                <article class="stat-card">
                    <div class="stat-icon purple">◎</div>
                    <div><span>Total Accounts</span><strong id="statTotalUsers">0</strong><small id="statUsersBreakdown">0 buyers · 0 sellers</small></div>
                </article>
                <article class="stat-card">
                    <div class="stat-icon blue">🐾</div>
                    <div><span>Active Listings</span><strong id="statActivePets">0</strong><small id="statPetsBreakdown">0 reserved · 0 sold</small></div>
                </article>
                <article class="stat-card">
                    <div class="stat-icon orange">▤</div>
                    <div><span>Applications</span><strong id="statTotalApps">0</strong><small id="statAppsBreakdown">0 pending · 0 confirmed</small></div>
                </article>
                <article class="stat-card">
                    <div class="stat-icon green">🗑</div>
                    <div><span>Recycle Bin</span><strong id="statDeletedPets">0</strong><small>Deleted listings</small></div>
                </article>
            </div>

            <div class="content-grid" style="margin-top: 24px;">
                <article class="panel">
                    <div class="panel-header">
                        <div><h2>Recent Applications</h2><p>Latest adoption inquiries</p></div>
                        <button class="text-btn" data-section-target="applications">View all →</button>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Pet</th><th>Buyer</th><th>Seller</th><th>Status</th></tr></thead>
                            <tbody id="overviewAppsTable"><tr><td colspan="4" class="empty-state">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-header">
                        <div><h2>Recent Audit Activity</h2><p>Administrative log</p></div>
                        <button class="text-btn" data-section-target="audit">View full log →</button>
                    </div>
                    <div class="activity-list" id="overviewAuditList"><p class="empty-state">Loading...</p></div>
                </article>
            </div>
        </section>

        <!-- SECTION: USERS -->
        <section class="dashboard-section" id="users">
            <div class="page-heading">
                <div><p class="eyebrow">USER DIRECTORY</p><h1>Manage Accounts</h1><p>View, suspend, or moderate registered members.</p></div>
            </div>
            <div class="filter-bar">
                <input type="search" id="userSearchInput" class="catalog-search" placeholder="Search by name, email, or phone...">
                <select id="userRoleFilter"><option value="all">All Roles</option><option value="buyer">Buyers</option><option value="seller">Sellers</option><option value="admin">Admins</option></select>
                <select id="userStatusFilter"><option value="all">All Statuses</option><option value="active">Active</option><option value="suspended">Suspended</option></select>
            </div>
            <div class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
                        <tbody id="usersTableBody"><tr><td colspan="6" class="empty-state">Loading users...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- SECTION: SELLER DIRECTORY (STAGE 6) -->
        <section class="dashboard-section" id="sellers">
            <div class="page-heading">
                <div><p class="eyebrow">PARTNERS</p><h1>Seller Directory</h1><p>Inspect verified sellers, active listings, and adoption activity.</p></div>
            </div>
            <div class="filter-bar">
                <input type="search" id="sellerSearchInput" class="catalog-search" placeholder="Search sellers by name, contact, city...">
            </div>
            <div class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Seller Name</th><th>Contact Details</th><th>Status</th><th>Active Pets</th><th>Reserved / Sold</th><th>Applications</th><th>Actions</th></tr></thead>
                        <tbody id="sellersTableBody"><tr><td colspan="7" class="empty-state">Loading sellers...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- SECTION: PETS -->
        <section class="dashboard-section" id="pets">
            <div class="page-heading">
                <div><p class="eyebrow">CATALOG MANAGEMENT</p><h1>Pet Listings</h1><p>Moderate pet listings and review adoption statuses.</p></div>
            </div>
            <div class="filter-tabs">
                <button class="filter-tab active" data-pet-status="all">All listings</button>
                <button class="filter-tab" data-pet-status="available">Available</button>
                <button class="filter-tab" data-pet-status="reserved">Reserved / Meeting</button>
                <button class="filter-tab" data-pet-status="sold">Sold</button>
            </div>
            <div class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Pet</th><th>Species</th><th>Seller</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody id="adminPetsTableBody"><tr><td colspan="6" class="empty-state">Loading pets...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- SECTION: RECYCLE BIN (STAGE 5) -->
        <section class="dashboard-section" id="recycle-bin">
            <div class="page-heading">
                <div><p class="eyebrow">RECYCLE BIN</p><h1>Deleted Pet Listings</h1><p>View deleted listings. You can restore them or permanently delete them.</p></div>
            </div>
            <div class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Pet</th><th>Owner</th><th>Deleted Date</th><th>Actions</th></tr></thead>
                        <tbody id="recycleBinTableBody"><tr><td colspan="4" class="empty-state">No deleted listings in recycle bin.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- SECTION: APPLICATIONS -->
        <section class="dashboard-section" id="applications">
            <div class="page-heading">
                <div><p class="eyebrow">APPLICATIONS</p><h1>Platform Adoption Requests</h1><p>Monitor all adoption applications between buyers and sellers.</p></div>
            </div>
            <div class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Pet</th><th>Buyer</th><th>Seller</th><th>Status</th><th>Submitted</th></tr></thead>
                        <tbody id="adminAppsTableBody"><tr><td colspan="5" class="empty-state">Loading applications...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- SECTION: WARNINGS / NOTIFICATIONS -->
        <section class="dashboard-section" id="warnings">
            <div class="page-heading">
                <div><p class="eyebrow">COMMUNICATION</p><h1>Send Admin Message / Warning</h1><p>Direct official communications to platform members.</p></div>
            </div>
            <div class="panel" style="max-width: 600px;">
                <form id="adminMessageForm" style="display: grid; gap: 14px;">
                    <label style="display:grid; gap:4px; font-weight:600; font-size:12px;">Recipient User
                        <select id="messageRecipientSelect" required><option value="">Select recipient...</option></select>
                    </label>
                    <label style="display:grid; gap:4px; font-weight:600; font-size:12px;">Subject
                        <input id="messageSubject" type="text" placeholder="e.g. Account Notice: Update your pet details" required>
                    </label>
                    <label style="display:grid; gap:4px; font-weight:600; font-size:12px;">Message Details
                        <textarea id="messageBody" rows="5" placeholder="Write your official notification here..." required></textarea>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; font-size:12px;">
                        <input type="checkbox" id="messageIsWarning"> Mark as formal Warning
                    </label>
                    <button class="primary-btn" type="submit">Send Notification</button>
                </form>
            </div>
        </section>

        <!-- SECTION: AUDIT LOG -->
        <section class="dashboard-section" id="audit">
            <div class="page-heading">
                <div><p class="eyebrow">SECURITY & ACCOUNTABILITY</p><h1>Administrative Audit Log</h1><p>Complete historical log of all moderation and administrative actions.</p></div>
            </div>
            <div class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Timestamp</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
                        <tbody id="auditTableBody"><tr><td colspan="5" class="empty-state">Loading audit logs...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

function toast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toast.tid);
    toast.tid = setTimeout(() => t.classList.remove('show'), 3000);
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function api(endpoint, method = 'GET', data = null) {
    const opts = {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
    };
    if (method !== 'GET') {
        opts.headers['Content-Type'] = 'application/json';
        opts.headers['X-CSRF-Token'] = csrf;
        opts.body = JSON.stringify(data || {});
    }
    const res = await fetch(endpoint, opts);
    const json = await res.json();
    if (!res.ok || !json.success) throw new Error(json.message || 'Operation failed');
    return json;
}

function showSection(id) {
    document.querySelectorAll('.dashboard-section').forEach(s => s.classList.toggle('active', s.id === id));
    document.querySelectorAll('.nav-item').forEach(b => b.classList.toggle('active', b.dataset.section === id));
    if (id === 'overview') loadOverview();
    if (id === 'users') loadUsers();
    if (id === 'sellers') loadSellers();
    if (id === 'pets') loadPets();
    if (id === 'recycle-bin') loadRecycleBin();
    if (id === 'applications') loadApplications();
    if (id === 'warnings') populateRecipients();
    if (id === 'audit') loadAuditLogs();
}

document.querySelectorAll('[data-section]').forEach(b => b.onclick = () => showSection(b.dataset.section));
document.querySelectorAll('[data-section-target]').forEach(b => b.onclick = () => showSection(b.dataset.sectionTarget));

// 1. Overview
async function loadOverview() {
    try {
        const statsRes = await api('api/admin.php?section=stats');
        const s = statsRes.stats;
        document.getElementById('statTotalUsers').textContent = s.users.total;
        document.getElementById('statUsersBreakdown').textContent = `${s.users.buyers} buyers · ${s.users.sellers} sellers (${s.users.suspended} suspended)`;
        document.getElementById('statActivePets').textContent = s.pets.available;
        document.getElementById('statPetsBreakdown').textContent = `${s.pets.reserved} reserved · ${s.pets.sold} sold`;
        document.getElementById('statTotalApps').textContent = s.applications.total;
        document.getElementById('statAppsBreakdown').textContent = `${s.applications.pending} pending · ${s.applications.meeting_confirmed} confirmed`;
        document.getElementById('statDeletedPets').textContent = s.pets.deleted;

        // Recent apps
        const appRes = await api('api/admin.php?section=applications');
        const apps = appRes.applications.slice(0, 5);
        document.getElementById('overviewAppsTable').innerHTML = apps.length ? apps.map(a => `
            <tr>
                <td><b>${esc(a.petName)}</b> <small>(${esc(a.type)})</small></td>
                <td>${esc(a.buyerName)}</td>
                <td>${esc(a.sellerName)}</td>
                <td><span class="status ${a.status === 'meeting_confirmed' || a.status === 'accepted' ? 'approved' : a.status === 'completed' ? 'completed' : 'pending'}">${esc(a.status)}</span></td>
            </tr>
        `).join('') : '<tr><td colspan="4" class="empty-state">No applications found.</td></tr>';

        // Recent audit
        const auditRes = await api('api/admin.php?section=audit_logs');
        const logs = auditRes.logs.slice(0, 4);
        document.getElementById('overviewAuditList').innerHTML = logs.length ? logs.map(l => `
            <div class="activity-item">
                <span class="activity-dot purple-dot"></span>
                <div>
                    <strong>${esc(l.action)} by ${esc(l.adminName)}</strong>
                    <p>Target: ${esc(l.targetType)} (${esc(l.targetId)})</p>
                    <small>${new Date(l.createdAt).toLocaleString()}</small>
                </div>
            </div>
        `).join('') : '<p class="empty-state">No audit logs recorded yet.</p>';
    } catch (e) {
        toast(e.message);
    }
}

// 2. Users
async function loadUsers() {
    const q = document.getElementById('userSearchInput').value.trim();
    const role = document.getElementById('userRoleFilter').value;
    const status = document.getElementById('userStatusFilter').value;
    try {
        const res = await api(`api/admin.php?section=users&search=${encodeURIComponent(q)}&role=${role}&status=${status}`);
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = res.users.length ? res.users.map(u => `
            <tr>
                <td><b>${esc(u.name)}</b></td>
                <td>${esc(u.email)}</td>
                <td><span class="admin-badge">${esc(u.role)}</span></td>
                <td><span class="status ${u.status === 'active' ? 'approved' : 'pending'}">${esc(u.status)}</span></td>
                <td>${new Date(u.createdAt).toLocaleDateString()}</td>
                <td>
                    <div class="table-actions">
                        ${u.role !== 'admin' ? (
                            u.status === 'active'
                            ? `<button class="btn-sm danger" onclick="setUserStatus('${u.id}', 'suspended')">Suspend</button>`
                            : `<button class="btn-sm success" onclick="setUserStatus('${u.id}', 'active')">Reactivate</button>`
                        ) : '<small style="color:var(--muted)">Protected</small>'}
                    </div>
                </td>
            </tr>
        `).join('') : '<tr><td colspan="6" class="empty-state">No users match filters.</td></tr>';
    } catch (e) {
        toast(e.message);
    }
}
document.getElementById('userSearchInput').oninput = loadUsers;
document.getElementById('userRoleFilter').onchange = loadUsers;
document.getElementById('userStatusFilter').onchange = loadUsers;

async function setUserStatus(userId, status) {
    if (!confirm(`Are you sure you want to set this user status to ${status}?`)) return;
    try {
        const res = await api('api/admin.php', 'POST', { action: 'set_user_status', userId, status });
        toast(res.message);
        loadUsers();
    } catch (e) {
        toast(e.message);
    }
}

// 3. Sellers Directory (Stage 6)
async function loadSellers() {
    const q = document.getElementById('sellerSearchInput').value.trim();
    try {
        const res = await api(`api/admin.php?section=sellers&search=${encodeURIComponent(q)}`);
        const tbody = document.getElementById('sellersTableBody');
        tbody.innerHTML = res.sellers.length ? res.sellers.map(s => `
            <tr>
                <td><b>${esc(s.name)}</b></td>
                <td>${esc(s.email)}<br><small style="color:var(--muted)">${esc(s.phone || 'No phone')} · ${esc(s.address || 'No address')}</small></td>
                <td><span class="status ${s.status === 'active' ? 'approved' : 'pending'}">${esc(s.status)}</span></td>
                <td><b>${s.activePets}</b> active</td>
                <td>${s.reservedPets} reserved · ${s.soldPets} sold</td>
                <td>${s.totalApplications} total</td>
                <td>
                    <div class="table-actions">
                        ${s.status === 'active'
                            ? `<button class="btn-sm danger" onclick="setUserStatus('${s.id}', 'suspended')">Suspend</button>`
                            : `<button class="btn-sm success" onclick="setUserStatus('${s.id}', 'active')">Reactivate</button>`}
                    </div>
                </td>
            </tr>
        `).join('') : '<tr><td colspan="7" class="empty-state">No sellers found.</td></tr>';
    } catch (e) {
        toast(e.message);
    }
}
document.getElementById('sellerSearchInput').oninput = loadSellers;

// 4. Pet Listings & Recycle Bin (Stage 5)
let currentPetTab = 'all';
async function loadPets() {
    try {
        const res = await api(`api/admin.php?section=pets&status=${currentPetTab}`);
        const tbody = document.getElementById('adminPetsTableBody');
        tbody.innerHTML = res.pets.length ? res.pets.map(p => `
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <img src="${esc(p.image)}" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">
                        <div><b>${esc(p.name)}</b><br><small style="color:var(--muted)">${esc(p.breed)} · ${Number(p.ageValue ?? p.age)} ${esc(p.ageUnit || 'years')}</small></div>
                    </div>
                </td>
                <td>${esc(p.type)}</td>
                <td>${esc(p.ownerName)}</td>
                <td><span class="status ${p.status === 'available' ? 'approved' : p.status === 'reserved' ? 'pending' : 'completed'}">${esc(p.status)}</span></td>
                <td>
                    <div class="table-actions">
                        ${p.status !== 'deleted'
                            ? `<button class="btn-sm danger" onclick="setPetStatus('${p.id}', 'deleted')">Move to Bin</button>`
                            : `<button class="btn-sm success" onclick="setPetStatus('${p.id}', 'available')">Restore</button>`}
                        ${p.status === 'reserved' ? `<button class="btn-sm" onclick="setPetStatus('${p.id}', 'available')">Reopen</button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('') : '<tr><td colspan="5" class="empty-state">No listings found for this filter.</td></tr>';
    } catch (e) {
        toast(e.message);
    }
}

document.querySelectorAll('[data-pet-status]').forEach(tab => {
    tab.onclick = () => {
        document.querySelectorAll('[data-pet-status]').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        currentPetTab = tab.dataset.petStatus;
        loadPets();
    };
});

async function setPetStatus(petId, status) {
    if (!confirm(`Confirm action: set pet status to ${status}?`)) return;
    try {
        const res = await api('api/admin.php', 'POST', { action: 'set_pet_status', petId, status });
        toast(res.message);
        loadPets();
        loadRecycleBin();
    } catch (e) {
        toast(e.message);
    }
}

// 5. Recycle Bin (Stage 5)
async function loadRecycleBin() {
    try {
        const res = await api('api/admin.php?section=pets&status=deleted');
        const tbody = document.getElementById('recycleBinTableBody');
        tbody.innerHTML = res.pets.length ? res.pets.map(p => `
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <img src="${esc(p.image)}" style="width:40px;height:40px;border-radius:8px;object-fit:cover;">
                        <div><b>${esc(p.name)}</b> <small>(${esc(p.breed)})</small></div>
                    </div>
                </td>
                <td>${esc(p.ownerName)}</td>
                <td>${p.deletedAt ? new Date(p.deletedAt).toLocaleString() : 'Recently'}</td>
                <td>
                    <div class="table-actions">
                        <button class="btn-sm success" onclick="setPetStatus('${p.id}', 'available')">Restore</button>
                        <button class="btn-sm danger" onclick="permanentDeletePet('${p.id}', '${esc(p.name)}')">Permanently Delete</button>
                    </div>
                </td>
            </tr>
        `).join('') : '<tr><td colspan="4" class="empty-state">Recycle bin is empty.</td></tr>';
    } catch (e) {
        toast(e.message);
    }
}

async function permanentDeletePet(petId, name) {
    if (!confirm(`⚠️ WARNING: Permanently delete '${name}'? This action is irreversible!`)) return;
    try {
        const res = await api('api/admin.php', 'POST', { action: 'set_pet_status', petId, status: 'permanent_delete' });
        toast(res.message);
        loadRecycleBin();
    } catch (e) {
        toast(e.message);
    }
}

// 6. Applications
async function loadApplications() {
    try {
        const res = await api('api/admin.php?section=applications');
        const tbody = document.getElementById('adminAppsTableBody');
        tbody.innerHTML = res.applications.length ? res.applications.map(a => `
            <tr>
                <td><b>${esc(a.petName)}</b> <small>(${esc(a.type)})</small></td>
                <td>${esc(a.buyerName)}<br><small style="color:var(--muted)">${esc(a.buyerEmail)}</small></td>
                <td>${esc(a.sellerName)}<br><small style="color:var(--muted)">${esc(a.sellerEmail)}</small></td>
                <td><span class="status ${a.status === 'meeting_confirmed' || a.status === 'accepted' ? 'approved' : a.status === 'completed' ? 'completed' : 'pending'}">${esc(a.status)}</span></td>
                <td>${new Date(a.createdAt).toLocaleDateString()}</td>
            </tr>
        `).join('') : '<tr><td colspan="5" class="empty-state">No applications found.</td></tr>';
    } catch (e) {
        toast(e.message);
    }
}

// 7. Warnings / Messages (Stage 4 item 9)
async function populateRecipients() {
    try {
        const res = await api('api/admin.php?section=users');
        const sel = document.getElementById('messageRecipientSelect');
        sel.innerHTML = '<option value="">Select recipient user...</option>' + res.users.map(u => `
            <option value="${esc(u.id)}">${esc(u.name)} (${esc(u.email)} - ${esc(u.role)})</option>
        `).join('');
    } catch (e) {
        toast(e.message);
    }
}

document.getElementById('adminMessageForm').onsubmit = async (e) => {
    e.preventDefault();
    const recipientId = document.getElementById('messageRecipientSelect').value;
    const subject = document.getElementById('messageSubject').value.trim();
    const message = document.getElementById('messageBody').value.trim();
    const isWarning = document.getElementById('messageIsWarning').checked;

    try {
        const res = await api('api/admin.php', 'POST', {
            action: 'send_message',
            recipientId,
            subject,
            message,
            isWarning
        });
        toast(res.message);
        document.getElementById('adminMessageForm').reset();
    } catch (err) {
        toast(err.message);
    }
};

// 8. Audit Logs
async function loadAuditLogs() {
    try {
        const res = await api('api/admin.php?section=audit_logs');
        const tbody = document.getElementById('auditTableBody');
        tbody.innerHTML = res.logs.length ? res.logs.map(l => `
            <tr>
                <td>${new Date(l.createdAt).toLocaleString()}</td>
                <td><b>${esc(l.adminName)}</b></td>
                <td><span class="admin-badge">${esc(l.action)}</span></td>
                <td>${esc(l.targetType)}: <small>${esc(l.targetId)}</small></td>
                <td><small style="color:var(--muted)">${esc(JSON.stringify(l.details || {}))}</small></td>
            </tr>
        `).join('') : '<tr><td colspan="5" class="empty-state">No audit logs available.</td></tr>';
    } catch (e) {
        toast(e.message);
    }
}

loadOverview();
</script>
</body>
</html>
