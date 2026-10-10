<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();

if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Paw Net</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <main class="auth-shell welcome-shell">
        <section class="auth-story">
            <a class="auth-brand" href="welcome.php"><span class="brand-mark">🐾</span><span>Paw Net<small>Find your kind of love.</small></span></a>
            <div class="story-copy">
                <span class="eyebrow">A HOME FOR EVERY HEART</span>
                <h1>Your next best friend is waiting.</h1>
                <p>Meet cats, dogs, and hamsters looking for a loving home. Start with an account, then explore your adoption journey.</p>
            </div>
            <div class="story-note"><span>✦</span><p>Small paws. Big love. A new beginning for both of you.</p></div>
            <div class="story-decoration decoration-one">🐕</div><div class="story-decoration decoration-two">🐈</div>
        </section>
        <section class="auth-content">
            <div class="choice-card">
                <span class="eyebrow">WELCOME TO PAW NET</span>
                <h2>Let’s get you started.</h2>
                <p class="auth-subtitle">Choose an option to continue to your personal adoption dashboard.</p>
                <a class="auth-submit choice-primary" href="login.php">Sign in to your account <span>→</span></a>
                <a class="choice-secondary" href="register.php">Create a new account <span>↗</span></a>
                <div class="choice-divider"><span>YOUR JOURNEY INCLUDES</span></div>
                <ul class="choice-benefits">
                    <li><span>✓</span> Discover a growing collection of pets</li>
                    <li><span>✓</span> Save favorites and track applications</li>
                    <li><span>✓</span> Keep your profile and preferences together</li>
                </ul>
                <p class="choice-footnote">A safe, friendly place to find your new companion.</p>
            </div>
            <footer>Your Paw Net account keeps your adoption activity in one place.</footer>
        </section>
    </main>
</body>
</html>
