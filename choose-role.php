<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();
if (empty($_SESSION['user']['id'])) {
    header('Location: welcome.php');
    exit;
}
auth_redirect_for_role(auth_user_role($_SESSION['user']));
?>
<!doctype html>
<html lang="en"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose your journey · Paw Net</title><link rel="stylesheet" href="auth.css">
    <style>
      .role-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:24px}.role-card{display:block;padding:24px;border:1px solid rgba(90,74,167,.15);border-radius:18px;background:#fff;text-decoration:none;color:#1f1d2b;transition:.2s}.role-card:hover{transform:translateY(-3px);box-shadow:0 16px 38px rgba(53,38,120,.13)}.role-card .emoji{font-size:32px}.role-card h3{margin:12px 0 6px}.role-card p{font-size:13px;color:#6d7287;line-height:1.6}.role-card span:last-child{display:block;margin-top:18px;color:#7b5cff;font-weight:700}@media(max-width:600px){.role-grid{grid-template-columns:1fr}}
    </style>
</head><body><main class="auth-shell"><section class="auth-story"><a class="auth-brand" href="welcome.php"><span class="brand-mark">🐾</span><span>Paw Net<small>Find your kind of love.</small></span></a><div class="story-copy"><span class="eyebrow">YOUR PAW NET JOURNEY</span><h1>Every good story starts with a choice.</h1><p>Welcome, <?= auth_escape((string) ($_SESSION['user']['name'] ?? 'friend')) ?>. Choose how you would like to use Paw Net today.</p></div><div class="story-decoration decoration-one">🐈</div><div class="story-decoration decoration-two">🐕</div></section><section class="auth-content"><div class="choice-card"><span class="eyebrow">WHAT BRINGS YOU HERE?</span><h2>How can we help?</h2><p class="auth-subtitle">You can come back and choose a different path any time.</p><div class="role-grid"><a class="role-card" href="index.php"><span class="emoji">🐾</span><h3>Adopt a pet</h3><p>Browse pets looking for a loving home and send an adoption request.</p><span>Explore pets →</span></a><a class="role-card" href="seller.php"><span class="emoji">🏡</span><h3>List a pet</h3><p>Create a listing for a pet and manage it from your seller page.</p><span>Open seller page →</span></a></div><p class="choice-footnote" style="margin-top:24px"><a href="logout.php">Sign out</a></p></div></section></main></body></html>
