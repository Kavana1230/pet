<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();
$role = is_string($_GET['role'] ?? null) && in_array($_GET['role'], ['buyer', 'seller'], true)
    ? $_GET['role']
    : 'buyer';
if (!empty($_SESSION['user']['id'])) {
    auth_redirect_for_role(auth_user_role($_SESSION['user']));
}

$error = '';
$name = '';
$email = '';
$phone = '';
$address = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $readText = static function (string $key): string {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    };
    $name = $readText('name');
    $email = strtolower($readText('email'));
    $phone = $readText('phone');
    $address = $readText('address');
    $passwordInput = $_POST['password'] ?? '';
    $confirmPasswordInput = $_POST['confirm_password'] ?? '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $confirmPassword = is_string($confirmPasswordInput) ? $confirmPasswordInput : '';

    if (!auth_csrf_is_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter your name and a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Choose a password with at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } else {
        $users = load_auth_users();
        foreach ($users as $user) {
            if (strtolower($user['email'] ?? '') === $email) {
                $error = 'An account with this email already exists. Sign in instead.';
                break;
            }
        }

        if ($error === '') {
            $user = [
                'id' => bin2hex(random_bytes(16)),
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'role' => $role,
                'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
                'createdAt' => date(DATE_ATOM),
            ];
            $users[] = $user;

            if (!save_auth_users($users)) {
                $error = 'We could not create your account. Please try again.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'address' => $user['address'],
                    'role' => $role,
                ];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                auth_redirect_for_role($role);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create <?= $role ?> account · Paw Net</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="auth.css">
</head>
<body>
    <main class="auth-shell register-shell">
        <section class="auth-story">
            <a class="auth-brand" href="welcome.php"><span class="brand-mark">🐾</span><span>Paw Net<small>Find your kind of love.</small></span></a>
            <div class="story-copy">
                <span class="eyebrow">YOUR STORY STARTS HERE</span>
                <h1>Make room for a little more love.</h1>
                <p>Create your adopter profile to discover cats, dogs, and hamsters ready to find a home.</p>
            </div>
            <div class="story-note"><span>✦</span><p>A thoughtful match can change two lives at once.</p></div>
            <div class="story-decoration decoration-one">🐹</div><div class="story-decoration decoration-two">🐾</div>
        </section>
        <section class="auth-content">
            <form class="auth-card register-card" method="post" action="register.php?role=<?= auth_escape($role) ?>">
                <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
                <span class="eyebrow"><?= $role === 'seller' ? 'SELLER ACCOUNT' : 'BUYER ACCOUNT' ?></span>
                <h2>Create a <?= $role ?> account</h2>
                <p class="auth-subtitle"><?= $role === 'seller' ? 'Create listings and talk with interested adopters.' : 'Discover pets and contact the people listing them.' ?></p>
                <?php if ($error !== ''): ?><div class="form-alert" role="alert"><?= auth_escape($error) ?></div><?php endif; ?>
                <label>Your name<input type="text" name="name" value="<?= auth_escape($name) ?>" autocomplete="name" placeholder="Your full name" required></label>
                <label>Email address<input type="email" name="email" value="<?= auth_escape($email) ?>" autocomplete="email" placeholder="you@example.com" required></label>
                <div class="auth-row">
                    <label>Phone <span class="optional">(optional)</span><input type="tel" name="phone" value="<?= auth_escape($phone) ?>" autocomplete="tel" placeholder="Phone number"></label>
                    <label>City / region <span class="optional">(optional)</span><input type="text" name="address" value="<?= auth_escape($address) ?>" autocomplete="address-level2" placeholder="Where you live"></label>
                </div>
                <div class="auth-row">
                    <label>Password<input type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" minlength="8" required></label>
                    <label>Confirm password<input type="password" name="confirm_password" autocomplete="new-password" placeholder="Type it again" required></label>
                </div>
                <button class="auth-submit" type="submit">Create account <span>→</span></button>
                <p class="auth-switch">Already have a <?= auth_escape($role) ?> account? <a href="login.php?role=<?= auth_escape($role) ?>">Sign in</a></p>
            </form>
            <footer>Your details stay with your Paw Net account and are never shown publicly.</footer>
        </section>
    </main>
</body>
</html>
