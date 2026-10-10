<?php
require_once __DIR__ . '/common.php';
$sessionUser = api_require_user(['GET', 'POST']);
$userId = (string) $sessionUser['id'];
$defaults = [
    'emailNotifications' => true,
    'recommendations' => true,
    'appointmentReminders' => true,
    'darkMode' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    foreach (load_auth_users() as $user) {
        if (($user['id'] ?? '') === $userId) {
            api_respond(['success' => true, 'settings' => array_merge($defaults, is_array($user['settings'] ?? null) ? $user['settings'] : [])]);
        }
    }
    api_respond(['success' => false, 'message' => 'Account not found.'], 404);
}

$input = api_read_input();
$settingsInput = $input['settings'] ?? null;
if (!is_array($settingsInput)) {
    api_respond(['success' => false, 'message' => 'Settings were not provided.'], 400);
}
$settings = [];
foreach ($defaults as $key => $default) {
    $value = $settingsInput[$key] ?? $default;
    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($parsed === null) {
        api_respond(['success' => false, 'message' => 'A setting value was invalid.'], 400);
    }
    $settings[$key] = $parsed;
}

api_update_account($userId, static function (array $user) use ($settings): array {
    $user['settings'] = $settings;
    return $user;
});
api_respond(['success' => true, 'settings' => $settings]);
