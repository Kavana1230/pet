<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();
redirect_if_authenticated();

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if (!auth_csrf_is_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        foreach (load_auth_users() as $user) {
            if (strtolower($user['email'] ?? '') === $email && password_verify($password, $user['passwordHash'] ?? '')) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'] ?? '',
                    'address' => $user['address'] ?? '',
                ];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
<<<<<<< HEAD
                header('Location: choose-role.php');
=======
                header('Location: index.php');
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
                exit;
            }
        }
        $error = 'Email or password was not recognized.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · Paw Net</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <main class="auth-shell">
        <section class="auth-story">
            <a class="auth-brand" href="welcome.php"><span class="brand-mark">🐾</span><span>Paw Net<small>Find your kind of love.</small></span></a>
            <div class="story-copy">
                <span class="eyebrow">A BETTER BEGINNING</span>
                <h1>Good things happen when you meet your match.</h1>
                <p>Find a companion, follow your adoption journey, and make a home a little happier.</p>
            </div>
            <div class="story-note"><span>“</span><p>Some of our best days begin with a paw at the door.</p></div>
            <div class="story-decoration decoration-one">🐈</div><div class="story-decoration decoration-two">🐕</div>
        </section>
        <section class="auth-content">
            <form class="auth-card" method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
                <span class="eyebrow">WELCOME BACK</span>
                <h2>Sign in to Paw Net</h2>
                <p class="auth-subtitle">Your next best friend is waiting.</p>
                <?php if ($error !== ''): ?><div class="form-alert" role="alert"><?= auth_escape($error) ?></div><?php endif; ?>
                <label>Email address<input type="email" name="email" value="<?= auth_escape($email) ?>" autocomplete="email" placeholder="you@example.com" required></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required></label>
                <button class="auth-submit" type="submit">Sign in <span>→</span></button>
                <p class="auth-switch">New to Paw Net? <a href="register.php">Create an account</a></p>
                <a class="back-home" href="register.php">Start your adoption journey <span>↗</span></a>
            </form>
            <footer>By continuing, you agree to use Paw Net kindly and responsibly.</footer>
        </section>
    </main>
</body>
</html>
