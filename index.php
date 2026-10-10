<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();

if (empty($_SESSION['user'])) {
    header('Location: welcome.php');
    exit;
}

$currentUser = $_SESSION['user'];
$userName = trim((string) ($currentUser['name'] ?? 'Paw Net adopter'));
$userEmail = (string) ($currentUser['email'] ?? '');
$userPhone = (string) ($currentUser['phone'] ?? '');
$userAddress = (string) ($currentUser['address'] ?? '');
$nameParts = preg_split('/\s+/', $userName, 2) ?: [$userName];
$firstName = $nameParts[0] ?? $userName;
$lastName = $nameParts[1] ?? '';
$initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= auth_escape(auth_csrf_token()) ?>">
    <title>Paw Net - User Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>

<div class="app-shell">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-icon">🐾</div>
            <div>
                <strong>Paw Net</strong>
                <span>Pet Adoption</span>
            </div>
        </div>

        <nav class="side-nav">
            <p class="nav-label">MAIN MENU</p>
            <button class="nav-item active" data-section="overview">
                <span class="nav-icon">▦</span><span>Overview</span>
            </button>
            <button class="nav-item" data-section="find-pets">
                <span class="nav-icon">🐕</span><span>Find Pets</span>
            </button>
            <button class="nav-item" data-section="applications">
                <span class="nav-icon">▤</span><span>My Applications</span>
                <span class="nav-badge" id="applicationBadge">0</span>
            </button>
            <button class="nav-item" data-section="favorites">
                <span class="nav-icon">♡</span><span>Favorites</span>
            </button>

            <p class="nav-label second-label">ACCOUNT</p>
            <button class="nav-item" data-section="profile">
                <span class="nav-icon">◎</span><span>My Profile</span>
            </button>
            <button class="nav-item" data-section="messages">
                <span class="nav-icon">✉</span><span>Messages</span>
                <span class="nav-badge" id="messageBadge">0</span>
            </button>
            <button class="nav-item" data-section="settings">
                <span class="nav-icon">⚙</span><span>Settings</span>
            </button>
        </nav>

        <div class="help-card">
            <div class="help-icon">?</div>
            <strong>Need help?</strong>
            <p>Our adoption team is happy to help you.</p>
            <button id="contactTeamBtn">Contact Team</button>
        </div>

        <form action="logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
            <button class="logout-btn" id="logoutBtn" type="submit"><span>↪</span> Sign Out</button>
        </form>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Open menu">☰</button>

            <div class="search-box">
                <span>⌕</span>
                <input id="globalSearch" type="search" placeholder="Search pets, applications...">
            </div>

            <div class="topbar-actions">
                <button class="icon-btn" id="notificationBtn" aria-label="Notifications">
                    ♢<span class="notification-dot"></span>
                </button>
                <div class="user-menu">
                    <div class="avatar"><?= auth_escape($initials) ?></div>
                    <div class="user-summary">
                        <strong><?= auth_escape($userName) ?></strong>
                        <span>Adopter</span>
                    </div>
                    <button class="chevron" id="profileMenuBtn">⌄</button>
                </div>
                <form class="topbar-logout" action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
                    <button type="submit" aria-label="Sign out of Paw Net"><span aria-hidden="true">↪</span><span>Sign out</span></button>
                </form>
            </div>
        </header>

        <section class="dashboard-section active" id="overview">
            <div class="page-heading">
                <div>
                    <p class="eyebrow">USER DASHBOARD</p>
                    <h1>Welcome back, <?= auth_escape($firstName) ?>! 👋</h1>
                    <p>Here’s what’s happening with your adoption journey.</p>
                </div>
                <button class="primary-btn" data-section-target="find-pets">+ Find a Pet</button>
            </div>

            <div class="stats-grid">
                <article class="stat-card">
                    <div class="stat-icon purple">♡</div>
                    <div><span>Favorite Pets</span><strong id="favoriteCount">0</strong><small>Saved to your account</small></div>
                </article>
                <article class="stat-card">
                    <div class="stat-icon orange">▤</div>
                    <div><span>Applications</span><strong id="applicationCount">0</strong><small>Your adoption requests</small></div>
                </article>
                <article class="stat-card">
                    <div class="stat-icon green">✓</div>
                    <div><span>Adoptions</span><strong id="adoptionCount">0</strong><small>Happy homes created</small></div>
                </article>
                <article class="stat-card">
                    <div class="stat-icon blue">♥</div>
                    <div><span>Pets to meet</span><strong id="availablePetCount">0</strong><small>Cats, dogs and hamsters</small></div>
                </article>
            </div>

            <div class="content-grid">
                <article class="panel applications-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Recent Applications</h2>
                            <p>Track your adoption requests</p>
                        </div>
                        <button class="text-btn" data-section-target="applications">View all →</button>
                    </div>

                    <div class="application-list" id="recentApplications"><p class="empty-state">Your applications will appear here.</p></div>
                </article>

                <article class="panel appointment-panel">
                    <div class="panel-header">
                        <div>
                            <h2>Upcoming</h2>
                            <p>Your next appointment</p>
                        </div>
                        <span class="calendar-icon">▣</span>
                    </div>
                    <div class="appointment-card">
                        <span class="appointment-label">YOUR ADOPTION JOURNEY</span>
                        <h3>Find your first match</h3>
                        <p>Browse available companions and send an adoption request.</p>
                        <button class="outline-btn" data-section-target="find-pets">Explore pets</button>
                    </div>
                </article>
            </div>

            <div class="content-grid bottom-grid">
                <article class="panel">
                    <div class="panel-header">
                        <div>
                            <h2>Recommended for You</h2>
                            <p>Pets matching your preferences</p>
                        </div>
                        <button class="text-btn" data-section-target="find-pets">See all →</button>
                    </div>
                    <div class="pet-grid compact" id="recommendedPets"></div>
                </article>

                <article class="panel activity-panel">
                    <div class="panel-header">
                        <div><h2>Recent Activity</h2><p>Your latest updates</p></div>
                    </div>
                    <div class="activity-list" id="recentActivity"><p class="empty-state">Your Paw Net activity will appear here.</p></div>
                </article>
            </div>
        </section>

        <section class="dashboard-section" id="find-pets">
            <div class="page-heading">
                <div><p class="eyebrow">EXPLORE</p><h1>Find Your New Best Friend</h1><p>Browse pets looking for a loving home.</p></div>
            </div>
            <div class="filter-bar">
                <input type="search" id="petSearch" placeholder="Search by pet name or breed...">
                <select id="typeFilter"><option value="all">Cats, Dogs & Hamsters</option><option value="dog">Dogs</option><option value="cat">Cats</option><option value="hamster">Hamsters</option></select>
                <select id="ageFilter"><option value="all">Any Age</option><option value="young">0–2 years</option><option value="adult">3+ years</option></select>
            </div>
            <div class="full-pet-grid" id="fullPetGrid"></div>
        </section>

        <section class="dashboard-section" id="applications">
            <div class="page-heading">
                <div><p class="eyebrow">MY ACTIVITY</p><h1>My Applications</h1><p>Keep track of your adoption applications.</p></div>
            </div>
            <div class="panel table-panel">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Pet</th><th>Application Date</th><th>Status</th><th>Next Step</th><th></th></tr></thead>
                        <tbody id="applicationsTableBody"><tr><td colspan="5" class="empty-state">No applications yet. Find a pet to get started.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="dashboard-section" id="favorites">
            <div class="page-heading">
                <div><p class="eyebrow">SAVED PETS</p><h1>My Favorites</h1><p>Pets you’ve saved for later.</p></div>
            </div>
            <div class="full-pet-grid" id="favoriteGrid"></div>
        </section>

        <section class="dashboard-section" id="profile">
            <div class="page-heading"><div><p class="eyebrow">ACCOUNT</p><h1>My Profile</h1><p>Manage your adopter information.</p></div></div>
            <div class="profile-layout">
                <article class="panel profile-card">
                    <div class="large-avatar"><?= auth_escape($initials) ?></div>
                    <h2><?= auth_escape($userName) ?></h2><p>Pet Adopter</p>
                    <?php if ($userAddress !== ''): ?><div class="profile-location">📍 <?= auth_escape($userAddress) ?></div><?php endif; ?>
                    <hr>
                    <div class="profile-stat"><span>Member since</span><strong id="memberSince">—</strong></div>
                    <div class="profile-stat"><span>Pets adopted</span><strong id="profileAdoptionCount">0</strong></div>
                </article>
                <article class="panel form-panel">
                    <div class="panel-header"><div><h2>Personal Information</h2><p>Your contact details</p></div></div>
                    <form id="profileForm">
                        <label>Full name<input id="profileName" name="name" value="<?= auth_escape($userName) ?>" required maxlength="120"></label>
                        <label>Email<input id="profileEmail" name="email" type="email" value="<?= auth_escape($userEmail) ?>" required></label>
                        <div class="form-grid"><label>Phone<input id="profilePhone" name="phone" value="<?= auth_escape($userPhone) ?>" maxlength="40"></label><label>City / region<input id="profileAddress" name="address" value="<?= auth_escape($userAddress) ?>" maxlength="200"></label></div>
                        <button class="primary-btn" type="submit">Save profile</button>
                    </form>
                </article>
            </div>
        </section>

        <section class="dashboard-section" id="messages">
            <div class="page-heading"><div><p class="eyebrow">COMMUNICATION</p><h1>Messages</h1><p>Talk with pet sellers about listings you are interested in.</p></div></div>
            <div class="message-layout">
                <div class="panel conversation-list"><div class="new-seller-chat"><label for="messagePetSelect">Start a conversation about a pet</label><select id="messagePetSelect"><option value="">Choose a pet listed by a seller</option></select><button class="outline-btn" id="startSellerConversation" type="button">Message seller</button></div><div id="conversationContacts"><p class="empty-state">Choose a seller-listed pet above to start a conversation.</p></div></div>
                <div class="panel chat-panel">
                    <div class="chat-header"><div class="message-avatar">PN</div><div><strong>Paw Net Team</strong><span>Usually replies within a few hours</span></div></div>
                    <div class="chat-messages" id="chatMessages"><p class="empty-state">Loading your conversation…</p></div>
                    <form class="message-form" id="messageForm"><input id="messageInput" maxlength="2000" placeholder="Choose a conversation to message its contact" disabled><button type="submit" aria-label="Send message" disabled>➤</button></form>
                </div>
            </div>
        </section>

        <section class="dashboard-section" id="settings">
            <div class="page-heading"><div><p class="eyebrow">ACCOUNT</p><h1>Settings</h1><p>Customize your dashboard preferences.</p></div></div>
            <div class="panel settings-panel">
                <div class="setting-row"><div><strong>Email notifications</strong><p>Receive updates about applications and appointments.</p></div><label class="switch"><input type="checkbox" data-setting="emailNotifications"><span></span></label></div>
                <div class="setting-row"><div><strong>Pet recommendations</strong><p>Get notified when pets match your saved preferences.</p></div><label class="switch"><input type="checkbox" data-setting="recommendations"><span></span></label></div>
                <div class="setting-row"><div><strong>Appointment reminders</strong><p>Receive reminders before meet & greet appointments.</p></div><label class="switch"><input type="checkbox" data-setting="appointmentReminders"><span></span></label></div>
                <div class="setting-row"><div><strong>Dark mode</strong><p>Use a darker appearance for the dashboard.</p></div><label class="switch"><input type="checkbox" id="darkMode" data-setting="darkMode"><span></span></label></div>
            </div>
        </section>
    </main>
</div>

<div class="toast" id="toast"><span>✓</span><p id="toastMessage">Done!</p></div>

<div class="modal-backdrop" id="petModal">
    <div class="modal">
        <button class="modal-close" id="closePetModal">×</button>
        <img id="modalPetImage" src="" alt="">
        <div class="modal-content">
            <span class="modal-label">AVAILABLE FOR ADOPTION</span>
            <h2 id="modalPetName">Pet</h2>
            <p id="modalPetMeta"></p>
            <p id="modalPetDescription">This lovely pet is looking for a caring family and a safe, loving home.</p>
            <p id="modalPickupLocation" class="modal-pickup" hidden></p>
            <label id="modalInquiryWrap" class="modal-inquiry-wrap" hidden for="modalInquiryMessage">Your message to the seller<textarea id="modalInquiryMessage" maxlength="2000"></textarea><small>Pet details and the pickup location are included. You can add your questions.</small></label>
            <div class="modal-actions"><button class="outline-btn" id="modalFavorite">♡ Save Favorite</button><button class="primary-btn" id="modalApply">Apply to Adopt</button></div>
        </div>
    </div>
</div>

<script src="dashboard.js"></script>
</body>
</html>
