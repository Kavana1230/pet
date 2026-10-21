<?php

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

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
    try {
        $pdo = pawnet_db();
        $stmt = $pdo->query('
            SELECT id, name, email, phone, address, role, status, password_hash AS passwordHash,
                   favorites_json, settings_json, created_at AS createdAt
            FROM users
        ');
        $rows = $stmt->fetchAll();
        $users = [];
        foreach ($rows as $row) {
            $favorites = json_decode((string) ($row['favorites_json'] ?? '[]'), true);
            $settings = json_decode((string) ($row['settings_json'] ?? '{}'), true);
            $users[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'phone' => (string) ($row['phone'] ?? ''),
                'address' => (string) ($row['address'] ?? ''),
                'role' => (string) $row['role'],
                'status' => (string) ($row['status'] ?? 'active'),
                'passwordHash' => (string) $row['passwordHash'],
                'favorites' => is_array($favorites) ? $favorites : [],
                'settings' => is_array($settings) ? $settings : [],
                'createdAt' => (string) $row['createdAt'],
            ];
        }
        return $users;
    } catch (Exception $e) {
        error_log('Paw Net DB user loading fallback: ' . $e->getMessage());
        $users = @include auth_users_file();
        return is_array($users) ? $users : [];
    }
}

function auth_user_role(array $user): string
{
    if (in_array($user['role'] ?? null, ['buyer', 'seller', 'admin'], true)) {
        return (string) $user['role'];
    }

    return 'buyer';
}

function auth_redirect_for_role(string $role): void
{
    $target = match ($role) {
        'admin' => 'admin.php',
        'seller' => 'seller.php',
        default => 'index.php',
    };
    header('Location: ' . $target);
    exit;
}

function auth_require_role(string $requiredRole): void
{
    start_auth_session();
    if (empty($_SESSION['user']['id'])) {
        header('Location: login.php?role=' . rawurlencode($requiredRole));
        exit;
    }

    $userId = (string) $_SESSION['user']['id'];
    foreach (load_auth_users() as $user) {
        if (($user['id'] ?? '') === $userId) {
            // Check account status
            if (($user['status'] ?? 'active') !== 'active') {
                $_SESSION = [];
                session_destroy();
                header('Location: login.php?error=suspended');
                exit;
            }

            $role = auth_user_role($user);
            $_SESSION['user']['role'] = $role;
            if ($role !== $requiredRole) {
                auth_redirect_for_role($role);
            }
            return;
        }
    }

    $_SESSION = [];
    header('Location: login.php?role=' . rawurlencode($requiredRole));
    exit;
}

function save_auth_users(array $users): bool
{
    $savedDb = false;
    try {
        $pdo = pawnet_db();
        $stmt = $pdo->prepare('
            INSERT INTO users (id, name, email, phone, address, role, status, password_hash, favorites_json, settings_json)
            VALUES (:id, :name, :email, :phone, :address, :role, :status, :password_hash, :favorites_json, :settings_json)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                email = VALUES(email),
                phone = VALUES(phone),
                address = VALUES(address),
                role = VALUES(role),
                status = VALUES(status),
                password_hash = VALUES(password_hash),
                favorites_json = VALUES(favorites_json),
                settings_json = VALUES(settings_json)
        ');
        foreach ($users as $u) {
            $stmt->execute([
                'id' => (string) $u['id'],
                'name' => (string) ($u['name'] ?? ''),
                'email' => strtolower((string) ($u['email'] ?? '')),
                'phone' => (string) ($u['phone'] ?? ''),
                'address' => (string) ($u['address'] ?? ''),
                'role' => (string) ($u['role'] ?? 'buyer'),
                'status' => (string) ($u['status'] ?? 'active'),
                'password_hash' => (string) ($u['passwordHash'] ?? ''),
                'favorites_json' => json_encode($u['favorites'] ?? []),
                'settings_json' => json_encode($u['settings'] ?? []),
            ]);
        }
        $savedDb = true;
    } catch (Exception $e) {
        error_log('Paw Net DB user save error: ' . $e->getMessage());
    }

    // Also persist file backup
    $php = "<?php\nreturn " . var_export($users, true) . ";\n";
    $savedFile = file_put_contents(auth_users_file(), $php, LOCK_EX) !== false;

    return $savedDb || $savedFile;
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
        auth_redirect_for_role(auth_user_role($_SESSION['user']));
    }
}
