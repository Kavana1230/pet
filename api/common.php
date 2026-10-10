<?php

require_once __DIR__ . '/../auth_helpers.php';

function api_respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function api_require_user(array $allowedMethods = ['GET']): array
{
    start_auth_session();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (empty($_SESSION['user']['id'])) {
        api_respond(['success' => false, 'message' => 'Please sign in to continue.'], 401);
    }

    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $allowedMethods, true)) {
        header('Allow: ' . implode(', ', $allowedMethods));
        api_respond(['success' => false, 'message' => 'This request method is not allowed.'], 405);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!isset($_SESSION['csrf_token']) || !is_string($requestToken) || !hash_equals($_SESSION['csrf_token'], $requestToken)) {
            api_respond(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 403);
        }
    }

    return $_SESSION['user'];
}

function api_read_input(): array
{
    if (is_array($_POST) && $_POST !== []) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    return is_array($decoded) ? $decoded : [];
}

function api_read_json_file(string $path): array
{
    $contents = @file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function api_write_json_file(string $path, array $data): bool
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    return $encoded !== false && file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) !== false;
}

function api_update_account(string $userId, callable $updater): ?array
{
    $users = load_auth_users();
    foreach ($users as $index => $user) {
        if (($user['id'] ?? '') === $userId) {
            $users[$index] = $updater($user);
            if (!save_auth_users($users)) {
                api_respond(['success' => false, 'message' => 'Unable to save account details. Check data folder write permissions.'], 500);
            }

            return $users[$index];
        }
    }

    api_respond(['success' => false, 'message' => 'Account was not found. Please sign in again.'], 401);
}

function api_sync_session_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? '',
        'address' => $user['address'] ?? '',
    ];
}
