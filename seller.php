<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();
auth_require_role('seller');
$name = (string) ($_SESSION['user']['name'] ?? 'Pet parent');
$parts = preg_split('/\s+/', trim($name)) ?: [$name];
$initials = strtoupper(substr($parts[0] ?? 'P', 0, 1) . substr($parts[1] ?? '', 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= auth_escape(auth_csrf_token()) ?>">
    <meta name="seller-id" content="<?= auth_escape((string) $_SESSION['user']['id']) ?>">
    <title>Seller dashboard · Paw Net</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root{--purple:#7056df;--ink:#28263b;--muted:#85849a;--line:#eceaf2;--bg:#f8f7fb;--green:#16815d;--red:#d32f2f;--amber:#b45309}
        *{box-sizing:border-box}
        body{margin:0;background:var(--bg);color:var(--ink);font:14px 'DM Sans',Arial,sans-serif}
        .sidebar{position:fixed;inset:0 auto 0 0;width:245px;background:white;border-right:1px solid var(--line);padding:24px 17px;display:flex;flex-direction:column;z-index:3}
        .brand{display:flex;align-items:center;gap:11px;margin:0 5px 35px;color:var(--ink);text-decoration:none}
        .brand-icon{height:43px;width:43px;border-radius:14px;background:#f0ecff;display:grid;place-items:center;font-size:23px}
        .brand b,.brand small{display:block}.brand b{font-size:17px}.brand small{color:var(--muted);font-size:11px}
        .nav-caption{font-size:10px;letter-spacing:1.4px;font-weight:800;color:#a3a1b2;padding:0 10px 10px}
        .nav button{width:100%;display:flex;align-items:center;gap:12px;border:0;background:transparent;padding:12px;border-radius:10px;color:#77768b;text-align:left;font:600 13px 'DM Sans';cursor:pointer;margin:3px 0}
        .nav button:hover,.nav button.active{background:#f1eeff;color:var(--purple)}
        .nav button i{font-style:normal;width:20px;text-align:center;font-size:17px}
        .nav-count{margin-left:auto;background:#ede9fe;color:var(--purple);font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
        .sidebar-foot{margin-top:auto;border-top:1px solid var(--line);padding-top:13px}
        .sidebar-foot a{display:block;text-decoration:none;color:#737186;padding:10px;font-weight:600;font-size:12px}
        .main{margin-left:245px;min-height:100vh}
        .topbar{height:72px;background:white;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;padding:0 4%;position:sticky;top:0;z-index:2}
        .top-title{font-weight:700}
        .user{display:flex;align-items:center;gap:10px}
        .avatar{height:37px;width:37px;border-radius:50%;background:#eeeaff;display:grid;place-items:center;color:var(--purple);font-weight:800}
        .user b,.user small{display:block}.user b{font-size:12px}.user small{font-size:10px;color:var(--muted)}
        .menu{display:none;border:0;background:none;font-size:22px}
        .content{max-width:1400px;margin:auto;padding:32px 4% 55px}
        .view{display:none}.view.active{display:block}
        .heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:22px}
        .eyebrow{font-size:10px;letter-spacing:1.5px;font-weight:800;color:var(--purple);margin:0 0 6px}
        .heading h1{font:700 29px 'Playfair Display',serif;margin:0}
        .heading p:last-child{font-size:12px;color:var(--muted);margin:6px 0 0}
        .btn{border:0;border-radius:10px;background:var(--purple);color:white;padding:12px 16px;font:700 12px 'DM Sans';cursor:pointer}
        .btn:hover{background:#5940c6}
        .btn.light{background:#f1eeff;color:var(--purple)}
        .btn.sm{padding:6px 12px;font-size:11px;border-radius:7px}
        .btn.success{background:var(--green);color:white}
        .btn.danger{background:var(--red);color:white}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:19px}
        .stat,.panel{background:white;border:1px solid var(--line);border-radius:15px;padding:18px;box-shadow:0 5px 18px #2b23520a}
        .stat-head{display:flex;justify-content:space-between;color:var(--muted);font-size:11px;font-weight:700}
        .stat-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:11px;background:#f1eeff;font-size:17px}
        .stat strong{display:block;font-size:26px;margin:12px 0 2px}
        .stat small{color:var(--muted);font-size:10px}
        .columns{display:grid;grid-template-columns:1.5fr .8fr;gap:16px}
        .panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px}
        .panel-head h2{font-size:14px;margin:0}
        .link{border:0;background:none;color:var(--purple);font-weight:700;cursor:pointer}
        .recent{display:grid;gap:10px}
        .recent-row{display:grid;grid-template-columns:54px 1fr auto;gap:11px;align-items:center;border:1px solid #f0eef4;border-radius:12px;padding:9px}
        .recent-row img{width:54px;height:54px;border-radius:9px;object-fit:cover}
        .recent-row b{font-size:12px}
        .recent-row p{font-size:10px;color:var(--muted);margin:4px 0 0}
        .badge{padding:6px 8px;border-radius:20px;background:#e9f7f0;color:var(--green);font-weight:800;font-size:9px}
        .badge.sold{background:#fff3de;color:#9b7021}
        .badge.reserved{background:#e0f2fe;color:#0284c7}
        .badge.deleted{background:#fee2e2;color:var(--red)}
        .quick{background:linear-gradient(145deg,#7258df,#9479ef);border-radius:15px;padding:22px;color:white}
        .quick .spark{font-size:25px}
        .quick h2{font:700 20px 'Playfair Display';margin:12px 0 7px}
        .quick p{font-size:11px;line-height:1.7;color:#ffffffd9}
        .quick .btn{background:white;color:var(--purple);margin-top:10px}
        .tip{margin-top:14px;background:#f0edff;color:#5e5880;padding:12px;border-radius:10px;font-size:11px}
        .toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}
        .search{width:min(360px,100%);border:1px solid var(--line);border-radius:10px;background:white;padding:11px 13px;font:12px 'DM Sans'}
        .tabs{display:flex;gap:6px;flex-wrap:wrap}
        .tabs button{border:1px solid var(--line);background:white;border-radius:9px;padding:9px 12px;color:var(--muted);font:700 10px 'DM Sans';cursor:pointer}
        .tabs button.active{background:#f1eeff;border-color:#ddd6ff;color:var(--purple)}
        .cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .pet-card{overflow:hidden;background:white;border:1px solid var(--line);border-radius:14px;display:flex;flex-direction:column}
        .pet-card>img{width:100%;height:165px;object-fit:cover;background:#f0eef4}
        .pet-body{padding:13px;display:flex;flex-direction:column;flex:1}
        .pet-title{display:flex;align-items:start;justify-content:space-between;gap:6px}
        .pet-title h3{font-size:13px;margin:2px 0}
        .pet-body p{color:var(--muted);font-size:11px;line-height:1.5;margin:7px 0 12px}
        .actions{display:flex;gap:7px;margin-top:auto;flex-wrap:wrap}
        .actions button{flex:1;border:1px solid var(--line);background:white;color:#656378;padding:9px 6px;border-radius:8px;font:700 10px 'DM Sans';cursor:pointer}
        .actions .sold-btn{background:#eaf7f1;color:var(--green);border-color:#d9eee4}
        .actions .del-btn{background:#fff1f2;color:var(--red);border-color:#fecdd3}
        .actions .reopen-btn{background:#ede9fe;color:var(--purple);border-color:#ddd6ff}
        .actions .restore-btn{background:#e9f7f0;color:var(--green);border-color:#bbf7d0}
        .form-panel{max-width:850px}
        .form{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .form label{display:grid;gap:6px;color:#69677c;font-size:11px;font-weight:700}
        .form input,.form select,.form textarea{border:1px solid var(--line);border-radius:9px;background:white;padding:11px;font:12px 'DM Sans';color:var(--ink);width:100%}
        .form textarea{min-height:95px;resize:vertical}
        .wide{grid-column:1/-1}
        .form-actions{display:flex;gap:8px}
        .empty{padding:22px;background:#fbfaff;border:1px dashed #dcd7ea;border-radius:11px;color:var(--muted);font-size:12px;text-align:center}
        .toast{position:fixed;right:20px;bottom:20px;background:#29263d;color:white;padding:13px 17px;border-radius:10px;box-shadow:0 7px 22px #201b3c33;opacity:0;transform:translateY(8px);transition:.2s;pointer-events:none;z-index:7}
        .toast.show{opacity:1;transform:none}
        .seller-inbox{display:grid;grid-template-columns:minmax(230px,310px) minmax(0,1fr);min-height:510px;background:#fff;border:1px solid var(--line);border-radius:15px;overflow:hidden}
        .inbox-threads{padding:17px;border-right:1px solid var(--line)}
        .inbox-threads h2{font-size:13px;margin:0 0 14px}
        .inbox-thread{width:100%;display:flex;gap:10px;align-items:center;text-align:left;border:0;background:transparent;border-radius:10px;padding:10px;cursor:pointer;color:var(--ink)}
        .inbox-thread:hover,.inbox-thread.active{background:#f3f0ff}
        .inbox-thread-copy{min-width:0;flex:1}
        .inbox-thread b,.inbox-thread small{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .inbox-thread b{font-size:11px}.inbox-thread small{font-size:10px;color:var(--muted);margin-top:4px}
        .inbox-thread em{font-style:normal;font-size:9px;color:#98631d;background:#fff2d5;padding:4px 6px;border-radius:12px}
        .inbox-chat{min-width:0;display:flex;flex-direction:column}
        .inbox-chat-head{display:flex;align-items:center;gap:11px;padding:13px 17px;border-bottom:1px solid var(--line)}
        .inbox-chat-head b,.inbox-chat-head small{display:block}.inbox-chat-head b{font-size:12px}.inbox-chat-head small{font-size:10px;color:var(--muted);margin-top:4px}
        .app-action-bar{display:flex;align-items:center;justify-content:space-between;padding:10px 17px;background:#fbfaff;border-bottom:1px solid var(--line);font-size:11px;font-weight:700}
        .app-action-bar .actions{display:flex;gap:6px}
        .inbox-log{flex:1;min-height:280px;max-height:480px;overflow:auto;padding:17px;display:flex;flex-direction:column;gap:10px}
        .inbox-bubble{max-width:78%;align-self:flex-start;background:#f0eef6;border-radius:12px;padding:10px 12px;font-size:11px;line-height:1.55;overflow-wrap:anywhere}
        .inbox-bubble.mine{align-self:flex-end;background:#7056df;color:#fff}
        .inbox-bubble small{display:block;font-size:9px;opacity:.7;margin-top:5px}
        .inbox-reply{display:flex;gap:8px;padding:12px;border-top:1px solid var(--line)}
        .inbox-reply input{min-width:0;flex:1;border:1px solid var(--line);border-radius:9px;padding:11px;font:12px 'DM Sans'}
        .inbox-notice{color:var(--muted);font-size:11px}
        .inbox-notice.pending{color:#936716;font-weight:800}
        .gallery-thumbs{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
        .gallery-thumb-item{position:relative;width:60px;height:60px;border-radius:8px;overflow:hidden;border:2px solid var(--line)}
        .gallery-thumb-item.primary{border-color:var(--purple)}
        .gallery-thumb-item img{width:100%;height:100%;object-fit:cover}
        .gallery-thumb-item .thumb-del{position:absolute;top:2px;right:2px;background:rgba(0,0,0,0.6);color:#fff;border:0;border-radius:50%;width:16px;height:16px;font-size:10px;cursor:pointer;display:grid;place-items:center}
        @media(max-width:1050px){.stats{grid-template-columns:repeat(2,1fr)}.cards{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:740px){
            .sidebar{width:230px;transform:translateX(-100%);transition:.2s;box-shadow:8px 0 25px #201b3c22}.sidebar.open{transform:none}
            .main{margin:0}.menu{display:block}.topbar{padding:0 17px}.content{padding:25px 17px 40px}
            .seller-inbox{grid-template-columns:1fr;min-height:0}
            .inbox-threads{border-right:0;border-bottom:1px solid var(--line);max-height:220px;overflow:auto}
            .inbox-log{min-height:240px}.columns{grid-template-columns:1fr}.user-copy{display:none}
        }
        @media(max-width:520px){
            .stats{gap:8px}.stat{padding:13px}.stat strong{font-size:22px}
            .heading{align-items:flex-start;flex-direction:column}
            .toolbar{align-items:stretch;flex-direction:column}.search{width:100%}
            .tabs{overflow:auto}.tabs button{white-space:nowrap}.cards{grid-template-columns:1fr}.form{grid-template-columns:1fr}.wide{grid-column:auto}
        }
    </style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="seller.php">
        <span class="brand-icon">🐾</span>
        <span><b>Paw Net</b><small>Seller studio</small></span>
    </a>
    <div class="nav-caption">WORKSPACE</div>
    <nav class="nav">
        <button class="active" data-view="overview"><i>⌂</i>Overview</button>
        <button data-view="listings"><i>▧</i>My listings <span id="navCount" class="nav-count"></span></button>
        <button data-view="add"><i>＋</i>Add a pet</button>
        <button data-view="messages"><i>✉</i>Messages <span id="messageCount" class="nav-count"></span></button>
    </nav>
    <div class="sidebar-foot">
        <a href="logout.php">⇥ Sign out</a>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <button id="menu" class="menu" aria-label="Open menu">☰</button>
        <div class="top-title" id="topTitle">Seller overview</div>
        <div class="user">
            <span class="avatar"><?= auth_escape($initials) ?></span>
            <span class="user-copy"><b><?= auth_escape($name) ?></b><small>Pet seller</small></span>
        </div>
    </header>

    <div class="content">
        <!-- VIEW: OVERVIEW -->
        <section class="view active" id="overview">
            <div class="heading">
                <div>
                    <p class="eyebrow">YOUR SELLER STUDIO</p>
                    <h1>Hello, <?= auth_escape(explode(' ', trim($name))[0] ?: 'friend') ?>.</h1>
                    <p>Here’s how your pet listings are doing.</p>
                </div>
                <button class="btn" data-go="add">＋ List a pet</button>
            </div>
            <div class="stats">
                <article class="stat">
                    <div class="stat-head">Total listings <span class="stat-icon">▧</span></div>
                    <strong id="total">0</strong>
                    <small>Pets in your portfolio</small>
                </article>
                <article class="stat">
                    <div class="stat-head">Available <span class="stat-icon">🐾</span></div>
                    <strong id="available">0</strong>
                    <small>Visible in public catalog</small>
                </article>
                <article class="stat">
                    <div class="stat-head">Reserved / Meeting <span class="stat-icon">🤝</span></div>
                    <strong id="reserved">0</strong>
                    <small>Meetings scheduled</small>
                </article>
                <article class="stat">
                    <div class="stat-head">Found a home <span class="stat-icon">♥</span></div>
                    <strong id="sold">0</strong>
                    <small>Adoptions completed</small>
                </article>
            </div>
            <div class="columns">
                <section class="panel">
                    <div class="panel-head">
                        <h2>Recently listed</h2>
                        <button class="link" data-go="listings">View all →</button>
                    </div>
                    <div class="recent" id="recent"></div>
                </section>
                <aside>
                    <div class="quick">
                        <span class="spark">✨</span>
                        <h2>Help the right family find their friend.</h2>
                        <p>Add clear photos and share personality details. High quality listings connect pets with loving families faster.</p>
                        <button class="btn" data-go="add">Create a listing →</button>
                    </div>
                    <div class="tip">💡 <b>Listing tip:</b> Mention whether your pet gets along with children or other animals, their favorite toys, and routine.</div>
                </aside>
            </div>
        </section>

        <!-- VIEW: MY LISTINGS & RECYCLE BIN -->
        <section class="view" id="listings">
            <div class="heading">
                <div>
                    <p class="eyebrow">YOUR PETS</p>
                    <h1>My listings</h1>
                    <p>Manage your listings, handle meetings, or recover deleted pets.</p>
                </div>
                <button class="btn" data-go="add">＋ Add a pet</button>
            </div>
            <div class="toolbar">
                <input id="search" class="search" type="search" placeholder="Search by name, breed, or type">
                <div class="tabs">
                    <button class="active" data-filter="all">All</button>
                    <button data-filter="available">Available</button>
                    <button data-filter="reserved">Reserved / Meeting</button>
                    <button data-filter="sold">Sold</button>
                    <button data-filter="deleted">Recycle Bin 🗑</button>
                </div>
            </div>
            <div id="cards" class="cards"></div>
        </section>

        <!-- VIEW: MESSAGES / INBOX -->
        <section class="view" id="messages">
            <div class="heading">
                <div>
                    <p class="eyebrow">BUYER INBOX</p>
                    <h1>Messages & Applications</h1>
                    <p>Review adoption requests, schedule meetings, and talk with interested families.</p>
                </div>
                <span id="replyNotice" class="inbox-notice">Loading messages…</span>
            </div>
            <div class="seller-inbox">
                <aside class="inbox-threads">
                    <h2>People messaging you</h2>
                    <div id="messageThreads"><p class="empty">Loading conversations…</p></div>
                </aside>
                <section class="inbox-chat">
                    <header class="inbox-chat-head">
                        <span id="contactAvatar" class="stat-icon">🐾</span>
                        <div>
                            <b id="contactName">Choose a conversation</b>
                            <small id="contactPet">Buyer and pet details appear here</small>
                        </div>
                    </header>
                    <div id="messageLog" class="inbox-log">
                        <p class="empty">Choose a buyer to read and reply.</p>
                    </div>
                    <form id="replyForm" class="inbox-reply">
                        <input id="replyInput" maxlength="2000" placeholder="Write a reply…" autocomplete="off" disabled>
                        <button id="replyButton" class="btn" disabled>Send</button>
                    </form>
                </section>
            </div>
        </section>

        <!-- VIEW: ADD / EDIT PET (STAGE 2) -->
        <section class="view" id="add">
            <div class="heading">
                <div>
                    <p class="eyebrow">WELCOME A NEW FAMILY</p>
                    <h1 id="formTitle">List a pet</h1>
                    <p>Share photos and information to help adopters get to know this companion.</p>
                </div>
            </div>
            <div class="panel form-panel">
                <div class="panel-head">
                    <h2>Pet details</h2>
                    <span style="color:var(--muted);font-size:10px">Required fields marked *</span>
                </div>
                <form id="petForm" class="form">
                    <label>Pet name *<input name="name" maxlength="60" placeholder="e.g. Coco" required></label>
                    <label>Breed *<input name="breed" maxlength="80" placeholder="e.g. Beagle mix" required></label>
                    <label>Type *
                        <select name="type" required>
                            <option value="">Choose type</option>
                            <option value="dog">Dog</option>
                            <option value="cat">Cat</option>
                            <option value="hamster">Hamster</option>
                        </select>
                    </label>
                    <label>Sex *
                        <select name="sex" required>
                            <option value="">Select sex</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </label>
                    <label>Age in years *<input name="age" type="number" min="0" max="40" required></label>
                    <label>Adoption Fee ($)<input name="price" type="number" min="0" step="0.5" placeholder="0.00"></label>
                    <label>Vaccination Status<input name="vaccinationStatus" placeholder="e.g. Up to date, Fully vaccinated"></label>
                    <label>Health Information<input name="healthInfo" placeholder="e.g. Spayed/neutered, microchipped, healthy coat"></label>

                    <label class="wide">Upload Photo File (JPG, PNG, WEBP)
                        <input type="file" id="petImageUpload" accept="image/jpeg,image/png,image/webp,image/gif">
                        <small style="color:var(--muted);font-weight:normal;">Direct upload stored safely on server. Limit 5MB.</small>
                    </label>

                    <label class="wide">Or Photo Image URL
                        <div style="display:flex;gap:8px;">
                            <input id="newImageUrlInput" type="url" placeholder="https://images.unsplash.com/...">
                            <button type="button" class="btn light" id="addImageUrlBtn">+ Add to Gallery</button>
                        </div>
                    </label>

                    <div class="wide">
                        <span style="font-size:11px;font-weight:700;color:#69677c;">Listing Gallery (First image is Cover)</span>
                        <div id="formGalleryThumbs" class="gallery-thumbs"></div>
                    </div>

                    <label class="wide">Meet-up / pickup location *
                        <input name="pickupLocation" maxlength="250" placeholder="Area, city, or agreed pickup address" required>
                        <small style="color:var(--muted);font-weight:400">Kept private and shared with an adopter when you message them.</small>
                    </label>
                    <label class="wide">About this pet *
                        <textarea name="description" maxlength="1000" placeholder="Personality, favorite activities, diet, and what kind of home they need..." required></textarea>
                    </label>
                    <div class="wide form-actions">
                        <button class="btn" id="submitPet">Publish listing</button>
                        <button class="btn light" type="button" id="cancelEdit">Cancel</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script>
const csrf = document.querySelector('[name="csrf-token"]').content;
let pets = [], filter = 'all', editing = '', galleryImages = [];
const toast = document.getElementById('toast');

function esc(x) {
    return String(x ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function say(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(say.t);
    say.t = setTimeout(() => toast.classList.remove('show'), 3000);
}
window.say = say;

async function api(method = 'GET', data) {
    const r = await fetch('api/seller-pets.php' + (method === 'GET' && filter ? `?filter=${filter}` : ''), {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(data ? { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf } : {})
        },
        body: data ? JSON.stringify(data) : undefined
    });
    const x = await r.json();
    if (!r.ok || !x.success) throw Error(x.message || 'Could not complete the request');
    return x;
}

function go(id) {
    document.querySelectorAll('.view').forEach(v => v.classList.toggle('active', v.id === id));
    document.querySelectorAll('[data-view]').forEach(b => b.classList.toggle('active', b.dataset.view === id));
    document.getElementById('topTitle').textContent = ({
        overview: 'Seller overview',
        listings: filter === 'deleted' ? 'Recycle bin' : 'My listings',
        add: editing ? 'Edit listing' : 'Add a pet',
        messages: 'Messages & Applications'
    })[id] || 'Seller studio';
    document.getElementById('sidebar').classList.remove('open');
    if (id === 'listings') load();
    if (id === 'messages') window.loadSellerMessages?.();
}

function preview(p) {
    return `
    <article class="recent-row">
        <img src="${esc(p.image)}" alt="${esc(p.name)}">
        <div>
            <b>${esc(p.name)}</b>
            <p>${esc(p.breed)} · ${esc(p.type)} · ${esc(p.sex || "Sex not specified")} · ${esc(p.age)} years</p>
        </div>
        <span class="badge ${p.status}">${p.status === 'available' ? 'Available' : p.status === 'reserved' ? 'Reserved' : p.status === 'sold' ? 'Sold' : 'Deleted'}</span>
    </article>`;
}

function card(p) {
    let actionButtons = '';
    if (p.status === 'available') {
        actionButtons = `
            <button data-edit="${esc(p.id)}">Edit details</button>
            <button class="sold-btn" data-sold="${esc(p.id)}">Mark sold</button>
            <button class="del-btn" data-delete="${esc(p.id)}">Move to bin</button>
        `;
    } else if (p.status === 'reserved') {
        actionButtons = `
            <button class="sold-btn" data-sold="${esc(p.id)}">Mark sold</button>
            <button class="reopen-btn" data-reopen="${esc(p.id)}">Reopen Listing</button>
        `;
    } else if (p.status === 'sold') {
        actionButtons = `<button disabled>Found a home ♥</button><button class="del-btn" data-delete="${esc(p.id)}">Move to bin</button>`;
    } else if (p.status === 'deleted') {
        actionButtons = `
            <button class="restore-btn" data-restore="${esc(p.id)}">Restore</button>
            <button class="del-btn" data-perm="${esc(p.id)}">Delete Permanently</button>
        `;
    }

    return `
    <article class="pet-card">
        <img src="${esc(p.image)}" alt="${esc(p.name)}">
        <div class="pet-body">
            <div class="pet-title">
                <h3>${esc(p.name)}</h3>
                <span class="badge ${esc(p.status)}">${p.status === 'available' ? 'Available' : p.status === 'reserved' ? 'Reserved' : p.status === 'sold' ? 'Sold' : 'Deleted'}</span>
            </div>
            <p>
                ${esc(p.breed)} · ${esc(p.type)} · ${esc(p.sex || "Sex not specified")} · ${esc(p.age)} yrs old<br>
                ${p.price > 0 ? `Adoption fee: $${Number(p.price).toFixed(2)}<br>` : 'Adoption fee: Free<br>'}
                ${esc(p.description)}<br>
                📍 ${esc(p.pickupLocation || "Shared with buyer in chat")}
            </p>
            <div class="actions">${actionButtons}</div>
        </div>
    </article>`;
}

function renderCards() {
    const q = document.getElementById('search').value.trim().toLowerCase();
    const rows = pets.filter(p => `${p.name} ${p.breed} ${p.type}`.toLowerCase().includes(q));
    document.getElementById('cards').innerHTML = rows.length
        ? rows.map(card).join('')
        : `<p class="empty">${filter === 'deleted' ? 'Recycle bin is empty.' : 'No pets match this filter yet.'}</p>`;

    // Sold button
    document.querySelectorAll('[data-sold]').forEach(b => b.onclick = async () => {
        b.disabled = true;
        try {
            const x = await api('POST', { action: 'sold', id: b.dataset.sold });
            say(x.message);
            await load();
        } catch (e) {
            say(e.message);
            b.disabled = false;
        }
    });

    // Move to bin
    document.querySelectorAll('[data-delete]').forEach(b => b.onclick = async () => {
        if (!confirm('Move this listing to your Recycle Bin? It will be hidden from buyers.')) return;
        b.disabled = true;
        try {
            const x = await api('POST', { action: 'delete', id: b.dataset.delete });
            say(x.message);
            await load();
        } catch (e) {
            say(e.message);
            b.disabled = false;
        }
    });

    // Restore from bin
    document.querySelectorAll('[data-restore]').forEach(b => b.onclick = async () => {
        b.disabled = true;
        try {
            const x = await api('POST', { action: 'restore', id: b.dataset.restore });
            say(x.message);
            await load();
        } catch (e) {
            say(e.message);
            b.disabled = false;
        }
    });

    // Permanently delete
    document.querySelectorAll('[data-perm]').forEach(b => b.onclick = async () => {
        if (!confirm('⚠️ Are you sure you want to PERMANENTLY delete this listing? This cannot be undone.')) return;
        b.disabled = true;
        try {
            const x = await api('POST', { action: 'permanent_delete', id: b.dataset.perm });
            say(x.message);
            await load();
        } catch (e) {
            say(e.message);
            b.disabled = false;
        }
    });

    // Reopen listing
    document.querySelectorAll('[data-reopen]').forEach(b => b.onclick = async () => {
        if (!confirm('Reopen this listing so it becomes available to other adopters again?')) return;
        b.disabled = true;
        try {
            const x = await api('POST', { action: 'reopen', id: b.dataset.reopen });
            say(x.message);
            await load();
        } catch (e) {
            say(e.message);
            b.disabled = false;
        }
    });

    // Edit pet
    document.querySelectorAll('[data-edit]').forEach(b => b.onclick = () => editPet(b.dataset.edit));
}

function updateGalleryThumbs() {
    const container = document.getElementById('formGalleryThumbs');
    if (!container) return;
    if (!galleryImages.length) {
        container.innerHTML = '<span style="font-size:11px;color:var(--muted)">No images in gallery yet.</span>';
        return;
    }
    container.innerHTML = galleryImages.map((url, idx) => `
        <div class="gallery-thumb-item ${idx === 0 ? 'primary' : ''}">
            <img src="${esc(url)}" alt="Image ${idx + 1}">
            <button type="button" class="thumb-del" onclick="removeGalleryImage(${idx})" title="Remove image">✕</button>
        </div>
    `).join('');
}

window.removeGalleryImage = function(index) {
    galleryImages.splice(index, 1);
    updateGalleryThumbs();
};

document.getElementById('addImageUrlBtn').onclick = () => {
    const inp = document.getElementById('newImageUrlInput');
    const url = inp.value.trim();
    if (!url) return;
    galleryImages.push(url);
    inp.value = '';
    updateGalleryThumbs();
};

// Image file upload
document.getElementById('petImageUpload').onchange = async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image_file', file);
    formData.append('action', 'upload');

    try {
        const res = await fetch('api/seller-pets.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-Token': csrf
            },
            body: formData
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'Upload failed');
        galleryImages.push(json.imageUrl);
        updateGalleryThumbs();
        say('Photo uploaded successfully!');
    } catch (err) {
        say(err.message);
    } finally {
        e.target.value = '';
    }
};

function editPet(id) {
    const pet = pets.find(p => p.id === id);
    if (!pet) return;
    editing = id;
    const form = document.getElementById('petForm');
    for (const key of ['name', 'breed', 'type', 'sex', 'age', 'price', 'vaccinationStatus', 'healthInfo', 'pickupLocation', 'description']) {
        if (form.elements[key]) form.elements[key].value = pet[key] ?? '';
    }
    galleryImages = (pet.images || []).map(img => typeof img === 'string' ? img : img.url).filter(Boolean);
    if (!galleryImages.length && pet.image) galleryImages.push(pet.image);
    updateGalleryThumbs();

    document.getElementById('formTitle').textContent = 'Edit pet listing';
    document.getElementById('submitPet').textContent = 'Save changes';
    go('add');
}

async function load() {
    try {
        const res = await api('GET');
        pets = res.pets || [];
        const counts = res.counts || {};
        document.getElementById('total').textContent = counts.total || pets.length;
        document.getElementById('available').textContent = counts.available || 0;
        document.getElementById('reserved').textContent = counts.reserved || 0;
        document.getElementById('sold').textContent = counts.sold || 0;
        document.getElementById('navCount').textContent = counts.available || '';

        const latest = pets.filter(p => p.status !== 'deleted').slice(0, 4);
        document.getElementById('recent').innerHTML = latest.length
            ? latest.map(preview).join('')
            : '<p class="empty">No active listings yet. Add your first companion to get started.</p>';
        renderCards();
    } catch (e) {
        say(e.message);
    }
}

document.querySelectorAll('[data-view]').forEach(b => b.onclick = () => go(b.dataset.view));
document.querySelectorAll('[data-go]').forEach(b => b.onclick = () => go(b.dataset.go));
document.getElementById('menu').onclick = () => document.getElementById('sidebar').classList.toggle('open');
document.getElementById('search').oninput = renderCards;

document.querySelectorAll('[data-filter]').forEach(b => b.onclick = () => {
    filter = b.dataset.filter;
    document.querySelectorAll('[data-filter]').forEach(x => x.classList.toggle('active', x === b));
    load();
});

document.getElementById('petForm').onsubmit = async (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    data.age = Number(data.age);
    data.price = Number(data.price || 0);
    data.images = galleryImages;
    data.image = galleryImages[0] || '';

    if (editing) {
        data.action = 'update';
        data.id = editing;
    } else {
        data.action = 'create';
    }

    const submit = document.getElementById('submitPet');
    submit.disabled = true;
    try {
        const result = await api('POST', data);
        say(result.message);
        form.reset();
        galleryImages = [];
        updateGalleryThumbs();
        editing = '';
        document.getElementById('formTitle').textContent = 'List a pet';
        submit.textContent = 'Publish listing';
        await load();
        go('listings');
    } catch (err) {
        say(err.message);
    } finally {
        submit.disabled = false;
    }
};

document.getElementById('cancelEdit').onclick = () => {
    document.getElementById('petForm').reset();
    editing = '';
    galleryImages = [];
    updateGalleryThumbs();
    document.getElementById('formTitle').textContent = 'List a pet';
    document.getElementById('submitPet').textContent = 'Publish listing';
    go('listings');
};

load();
</script>
<script src="seller-inbox.js"></script>
</body>
</html>
