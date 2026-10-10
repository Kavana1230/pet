<?php

declare(strict_types=1);

function start_auth_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function auth_users_file(): string
{
    return __DIR__ . '/data/accounts.php';
}

function load_auth_users(): array
{
    $users = @include auth_users_file();
    return is_array($users) ? $users : [];
}

function save_auth_users(array $users): bool
{
    $php = "<?php\nreturn " . var_export($users, true) . ";\n";
    return file_put_contents(auth_users_file(), $php, LOCK_EX) !== false;
}

function auth_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function auth_csrf_is_valid(): bool
{
    return isset($_SESSION['csrf_token'], $_POST['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function redirect_if_authenticated(): void
{
    if (!empty($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
}
