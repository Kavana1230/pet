<?php
require_once __DIR__ . '/common.php';
$sessionUser = api_require_user(['GET', 'POST']);
$userId = (string) $sessionUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    foreach (load_auth_users() as $user) {
        if (($user['id'] ?? '') === $userId) {
            api_respond(['success' => true, 'profile' => [
                'name' => $user['name'] ?? '',
                'email' => $user['email'] ?? '',
                'phone' => $user['phone'] ?? '',
                'address' => $user['address'] ?? '',
            ]]);
        }
    }
    api_respond(['success' => false, 'message' => 'Account not found.'], 404);
}

$input = api_read_input();
$name = is_string($input['name'] ?? null) ? trim($input['name']) : '';
$email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
$phone = is_string($input['phone'] ?? null) ? trim($input['phone']) : '';
$address = is_string($input['address'] ?? null) ? trim($input['address']) : '';
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($name) > 120 || strlen($phone) > 40 || strlen($address) > 200) {
    api_respond(['success' => false, 'message' => 'Enter a name and valid email, and keep contact details within the limits.'], 400);
}

foreach (load_auth_users() as $user) {
    if (($user['id'] ?? '') !== $userId && strtolower($user['email'] ?? '') === $email) {
        api_respond(['success' => false, 'message' => 'That email is already in use.'], 409);
    }
}

$updatedUser = api_update_account($userId, static function (array $user) use ($name, $email, $phone, $address): array {
    $user['name'] = $name;
    $user['email'] = $email;
    $user['phone'] = $phone;
    $user['address'] = $address;
    return $user;
});
api_sync_session_user($updatedUser);
api_respond(['success' => true, 'profile' => ['name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address]]);
