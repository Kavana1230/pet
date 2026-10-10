<?php
require_once __DIR__ . '/common.php';
$sessionUser = api_require_user(['GET', 'POST']);
$userId = (string) $sessionUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $users = load_auth_users();
    foreach ($users as $user) {
        if (($user['id'] ?? '') === $userId) {
            api_respond(['success' => true, 'favorites' => array_values(array_unique($user['favorites'] ?? []))]);
        }
    }
    api_respond(['success' => false, 'message' => 'Account not found.'], 404);
}

$input = api_read_input();
$petName = is_string($input['petName'] ?? null) ? trim($input['petName']) : '';
$isFavorite = filter_var($input['favorite'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($petName === '' || $isFavorite === null) {
    api_respond(['success' => false, 'message' => 'Choose a pet and a valid favorite action.'], 400);
}

$pets = api_read_json_file(__DIR__ . '/../data/pets.json');
$available = false;
foreach ($pets as $pet) {
    if (($pet['name'] ?? '') === $petName && in_array(strtolower($pet['type'] ?? ''), ['cat', 'dog', 'hamster'], true)) {
        $available = true;
        break;
    }
}
if (!$available) {
    api_respond(['success' => false, 'message' => 'That pet could not be found.'], 404);
}

$user = api_update_account($userId, static function (array $user) use ($petName, $isFavorite): array {
    $favorites = array_values(array_unique($user['favorites'] ?? []));
    if ($isFavorite && !in_array($petName, $favorites, true)) {
        $favorites[] = $petName;
    } elseif (!$isFavorite) {
        $favorites = array_values(array_filter($favorites, static fn ($name) => $name !== $petName));
    }
    $user['favorites'] = $favorites;
    return $user;
});

api_respond(['success' => true, 'favorites' => array_values(array_unique($user['favorites'] ?? []))]);
