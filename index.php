<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();
if (empty($_SESSION['user'])) {
    header('Location: welcome.php');
    exit;
}
auth_require_role('buyer');

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
                <button class="icon-btn" id="a11yBtn" aria-label="Open accessibility settings" title="Accessibility settings">
                    ♿
                </button>
                <button class="icon-btn" id="notificationBtn" aria-label="Notifications">
                    ♢<span class="notification-dot"></span>
                </button>
                <div class="user-menu">
                    <div class="avatar"><?= auth_escape($initials) ?></div>
                    <div class="user-summary">
                        <strong><?= auth_escape($userName) ?></strong>
                        <span>Buyer</span>
                    </div>
                    <button class="chevron" id="profileMenuBtn">⌄</button>
                </div>
                <form class="topbar-logout" action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
                    <button type="submit" aria-label="Sign out of Paw Net"><span aria-hidden="true">↪</span><span>Sign out</span></button>
                </form>
            </div>
        </header>

        <div id="adminNotificationsArea" style="padding: 16px 4% 0;"></div>

        <section class="dashboard-section active" id="overview">
            <div class="page-heading dashboard-hero">
                <div>
                    <p class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</p>
                    <h1>Welcome back, <?= auth_escape($firstName) ?>! 👋</h1>
                    <p>A new best friend might be closer than you think. Explore pets waiting to meet you.</p>
                </div>
                <button class="primary-btn" data-section-target="find-pets"><span aria-hidden="true">♡</span> Find your match</button>
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
                <div><p class="eyebrow">MEET YOUR NEW SIDEKICK</p><h1>Find your new best friend</h1><p>Every profile is a chance to make a life-long connection.</p></div>
                <button class="outline-btn" id="surprisePet" type="button">Surprise me <span aria-hidden="true">✦</span></button>
            </div>
            <div class="filter-bar">
                <label class="catalog-search"><span aria-hidden="true">⌕</span><input type="search" id="petSearch" placeholder="Try a name, breed, or personality..."></label>
                <select id="typeFilter" aria-label="Filter by animal"><option value="all">All companions</option><option value="dog">Dogs</option><option value="cat">Cats</option><option value="hamster">Hamsters</option></select>
                <select id="ageFilter" aria-label="Filter by age"><option value="all">Any age</option><option value="young">0–2 years</option><option value="adult">3+ years</option></select>
            </div>
            <div class="catalog-toolbar">
                <div class="species-chips" role="group" aria-label="Quick filter by animal">
                    <button type="button" class="species-chip-button active" data-pet-type="all" aria-pressed="true">All pets</button>
                    <button type="button" class="species-chip-button" data-pet-type="dog" aria-pressed="false">🐕 Dogs</button>
                    <button type="button" class="species-chip-button" data-pet-type="cat" aria-pressed="false">🐈 Cats</button>
                    <button type="button" class="species-chip-button" data-pet-type="hamster" aria-pressed="false">🐹 Hamsters</button>
                </div>
                <div class="catalog-options">
                    <span id="petResultCount" role="status" aria-live="polite">Finding your matches…</span>
                    <label>Sort
                        <select id="petSort" aria-label="Sort pets">
                            <option value="recommended">Recommended</option>
                            <option value="name">Name: A to Z</option>
                            <option value="youngest">Youngest first</option>
                            <option value="oldest">Oldest first</option>
                            <option value="newest">Recently listed</option>
                        </select>
                    </label>
                    <button class="text-btn" id="clearPetFilters" type="button">Clear filters</button>
                </div>
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
            <div class="page-heading"><div><p class="eyebrow">YOUR ADOPTION CONVERSATIONS</p><h1>Messages</h1><p>Ask questions, get to know the pet, and plan a meet-and-greet with the seller.</p><p id="messageError" class="message-error" role="alert" hidden></p></div><span class="chat-refresh-label"><span aria-hidden="true"></span> Inbox refreshes automatically</span></div>
            <div class="message-layout">
                <aside class="panel conversation-list">
                    <div class="conversation-list-heading"><div><h2>Your chats</h2><span id="conversationCount">0 conversations</span></div><span class="chat-list-icon" aria-hidden="true">✉</span></div>
                    <label class="conversation-search"><span aria-hidden="true">⌕</span><input id="conversationSearch" type="search" placeholder="Search conversations..." aria-label="Search conversations"></label>
                    <div id="conversationContacts" class="conversation-contacts"><p class="empty-state">Your seller conversations will appear here.</p></div>
                    <div class="new-seller-chat">
                        <label for="messagePetSelect">Start a new chat</label>
                        <select id="messagePetSelect"><option value="">Loading seller listings…</option></select>
                        <button class="primary-btn" id="startSellerConversation" type="button" disabled>Message seller <span aria-hidden="true">→</span></button>
                        <small>Choose a pet listed by another Paw Net member.</small>
                    </div>
                </aside>
                <div class="panel chat-panel">
                    <div class="chat-header"><div class="message-avatar">🐾</div><div><strong>Select a conversation</strong><span>Your messages go directly to the pet's seller.</span></div><span class="chat-header-status">PRIVATE CHAT</span></div>
                    <div id="chatListingCard" class="chat-listing-card" hidden></div>
                    <div class="chat-messages" id="chatMessages" aria-live="polite" aria-relevant="additions text"><div class="chat-welcome"><span>🐾</span><h2>Your next friendship starts with hello.</h2><p>Choose an existing conversation or start a chat about a pet listed by another member.</p></div></div>
                    <form class="message-form" id="messageForm"><div class="message-compose"><textarea id="messageInput" maxlength="2000" rows="1" placeholder="Choose a conversation to start chatting…" aria-label="Write a message" disabled></textarea><div class="message-compose-meta"><span id="messageHint">Your messages are shared only with this listing's seller.</span><span id="messageCharCount">0 / 2000</span></div></div><button type="submit" aria-label="Send message" disabled><span aria-hidden="true">➤</span></button></form>
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
        <button class="modal-close" id="closePetModal" aria-label="Close modal">×</button>
        <div class="modal-gallery-wrap">
            <button class="gallery-nav prev" id="modalGalleryPrev" type="button" aria-label="Previous image">‹</button>
            <img id="modalPetImage" src="" alt="Pet image">
            <button class="gallery-nav next" id="modalGalleryNext" type="button" aria-label="Next image">›</button>
            <div class="gallery-dots" id="modalGalleryDots"></div>
        </div>
        <div class="modal-content">
            <span class="modal-label" id="modalStatusBadge">AVAILABLE FOR ADOPTION</span>
            <h2 id="modalPetName">Pet</h2>
            <p id="modalPetMeta"></p>
            <div class="modal-extra-details">
                <span id="modalPetPrice" class="detail-chip">Fee: Free</span>
                <span id="modalPetVaccinated" class="detail-chip">Vaccinated</span>
                <span id="modalPetHealth" class="detail-chip">Health: Good</span>
            </div>
            <p id="modalPetDescription">This lovely pet is looking for a caring family and a safe, loving home.</p>
            <p id="modalPickupLocation" class="modal-pickup" hidden></p>
            <label id="modalInquiryWrap" class="modal-inquiry-wrap" hidden for="modalInquiryMessage">Your message to the seller<textarea id="modalInquiryMessage" maxlength="2000"></textarea><small>Pet details and the pickup location are included. You can add your questions.</small></label>
            <div class="modal-actions"><button class="outline-btn" id="modalFavorite">♡ Save Favorite</button><button class="primary-btn" id="modalApply">Apply to Adopt</button></div>
        </div>
    </div>
</div>

<!-- ACCESSIBILITY SETTINGS PANEL (OPTION C) -->
<div class="modal-backdrop" id="a11yModal">
    <div class="modal" style="max-width: 440px;">
        <button class="modal-close" id="closeA11yModal" type="button" aria-label="Close accessibility settings">×</button>
        <div class="modal-content" style="padding: 24px;">
            <span class="modal-label">ACCESSIBILITY</span>
            <h2>Display Settings</h2>
            <p style="font-size:12px;color:var(--muted);margin-bottom:16px;">Customize display options to suit your reading needs.</p>
            <div style="display:grid;gap:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><strong>Dyslexia-friendly font</strong><p style="font-size:11px;color:var(--muted);">Use high-legibility letterforms</p></div>
                    <label class="switch"><input type="checkbox" id="a11yDyslexicToggle"><span></span></label>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><strong>Text size</strong><p style="font-size:11px;color:var(--muted);">Enlarge dashboard text</p></div>
                    <select id="a11yTextSizeSelect" style="padding:6px;border-radius:8px;border:1px solid var(--border);">
                        <option value="default">Default (100%)</option>
                        <option value="medium">Medium (+15%)</option>
                        <option value="large">Large (+30%)</option>
                    </select>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><strong>High contrast</strong><p style="font-size:11px;color:var(--muted);">Enhanced borders & contrast</p></div>
                    <label class="switch"><input type="checkbox" id="a11yContrastToggle"><span></span></label>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><strong>Reduced motion</strong><p style="font-size:11px;color:var(--muted);">Minimize animations</p></div>
                    <label class="switch"><input type="checkbox" id="a11yMotionToggle"><span></span></label>
                </div>
            </div>
            <div style="margin-top:20px;display:flex;justify-content:flex-end;">
                <button class="outline-btn" id="a11yResetBtn" type="button">Reset to default</button>
            </div>
        </div>
    </div>
</div>

<script src="dashboard.js"></script>
</body>
</html>
