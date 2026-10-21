<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();
$role = is_string($_GET['role'] ?? null) && in_array($_GET['role'], ['buyer', 'seller', 'admin'], true)
    ? $_GET['role']
    : 'buyer';
if (!empty($_SESSION['user']['id'])) {
    auth_redirect_for_role(auth_user_role($_SESSION['user']));
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'suspended') {
    $error = 'This account has been deactivated or suspended by administration. Please contact support.';
}

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
        $users = load_auth_users();
        foreach ($users as $index => $user) {
            if (strtolower($user['email'] ?? '') === $email && password_verify($password, $user['passwordHash'] ?? '')) {
                if (($user['status'] ?? 'active') !== 'active') {
                    $error = 'This account is currently deactivated or suspended. Please contact administration.';
                    break;
                }

                $accountRole = auth_user_role($user);
                if ($accountRole !== $role) {
                    $error = 'This account is registered as a ' . $accountRole . '. Choose the matching sign-in option.';
                    break;
                }
                if (!isset($user['role'])) {
                    $users[$index]['role'] = $accountRole;
                    save_auth_users($users);
                    $user['role'] = $accountRole;
                }
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'] ?? '',
                    'address' => $user['address'] ?? '',
                    'role' => $accountRole,
                ];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                auth_redirect_for_role($accountRole);
                exit;
            }
        }
        if ($error === '') {
            $error = 'Email or password was not recognized.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($role) ?> sign in · Paw Net</title>
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
                <span class="eyebrow"><?= $role === 'admin' ? 'ADMINISTRATION PORTAL' : 'A BETTER BEGINNING' ?></span>
                <h1><?= $role === 'admin' ? 'Safe, responsible pet adoption oversight.' : 'Good things happen when you meet your match.' ?></h1>
                <p><?= $role === 'admin' ? 'Manage listings, support buyers and sellers, and monitor platform activity.' : 'Find a companion, follow your adoption journey, and make a home a little happier.' ?></p>
            </div>
            <div class="story-note"><span>“</span><p><?= $role === 'admin' ? 'Integrity, compassion, and security for every pet.' : 'Some of our best days begin with a paw at the door.' ?></p></div>
            <div class="story-decoration decoration-one">🐈</div><div class="story-decoration decoration-two">🐕</div>
        </section>
        <section class="auth-content">
            <form class="auth-card" method="post" action="login.php?role=<?= auth_escape($role) ?>">
                <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
                <span class="eyebrow"><?= strtoupper($role) ?> ACCOUNT</span>
                <h2>Sign in as <?= $role === 'admin' ? 'an Admin' : 'a ' . $role ?></h2>
                <p class="auth-subtitle"><?= match($role) {
                    'admin' => 'Access the Paw Net administrator dashboard.',
                    'seller' => 'Manage your listings and reply to adoption inquiries.',
                    default => 'Browse pets and contact their sellers.',
                } ?></p>
                <?php if ($error !== ''): ?><div class="form-alert" role="alert"><?= auth_escape($error) ?></div><?php endif; ?>
                <label>Email address<input type="email" name="email" value="<?= auth_escape($email) ?>" autocomplete="email" placeholder="you@example.com" required></label>
                <label>Password<input type="password" name="password" autocomplete="current-password" placeholder="Enter your password" required></label>
                <button class="auth-submit" type="submit">Sign in <span>→</span></button>
                <?php if ($role !== 'admin'): ?>
                <p class="auth-switch">New to Paw Net? <a href="register.php?role=<?= auth_escape($role) ?>">Create a <?= auth_escape($role) ?> account</a></p>
                <?php endif; ?>
                <a class="back-home" href="welcome.php">Choose account type <span>↗</span></a>
            </form>
            <footer>By continuing, you agree to use Paw Net kindly and responsibly.</footer>
        </section>
    </main>
</body>
</html>
