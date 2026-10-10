<?php
require_once __DIR__ . '/common.php';
$sessionUser = api_require_user(['GET']);
$userId = (string) $sessionUser['id'];
$users = load_auth_users();
$currentUser = null;
foreach ($users as $user) {
    if (($user['id'] ?? '') === $userId) {
        $currentUser = $user;
        break;
    }
}
if ($currentUser === null) {
    api_respond(['success' => false, 'message' => 'Account was not found. Please sign in again.'], 401);
}
api_sync_session_user($currentUser);

$pets = api_read_json_file(__DIR__ . '/../data/pets.json');
$petByName = [];
foreach ($pets as $pet) {
    if (isset($pet['name'])) {
        $petByName[$pet['name']] = $pet;
    }
}

$applications = array_values(array_filter(
    api_read_json_file(__DIR__ . '/../data/applications.json'),
    static fn ($application) => ($application['userId'] ?? '') === $userId
));
foreach ($applications as &$application) {
    $pet = $petByName[$application['petName'] ?? ''] ?? [];
    $application['breed'] = $pet['breed'] ?? 'Pet';
    $application['type'] = $pet['type'] ?? '';
    $application['image'] = $pet['image'] ?? '';
    $application['nextStep'] = match ($application['status'] ?? '') {
        'Meet & Greet' => 'Meet & greet scheduled',
        'Approved' => 'Review adoption details',
        'Completed' => 'Adoption complete',
        default => 'Await review',
    };
}
unset($application);

$settings = $currentUser['settings'] ?? [];
$settings = array_merge([
    'emailNotifications' => true,
    'recommendations' => true,
    'appointmentReminders' => true,
    'darkMode' => false,
], is_array($settings) ? $settings : []);

$memberSince = '';
if (!empty($currentUser['createdAt'])) {
    $createdAt = strtotime((string) $currentUser['createdAt']);
    $memberSince = $createdAt ? date('F Y', $createdAt) : '';
}

$messages = array_values(array_filter(
    api_read_json_file(__DIR__ . '/../data/messages.json'),
    static fn ($message) => ($message['userId'] ?? '') === $userId
));

api_respond([
    'success' => true,
    'profile' => [
        'id' => $userId,
        'name' => $currentUser['name'] ?? '',
        'email' => $currentUser['email'] ?? '',
        'phone' => $currentUser['phone'] ?? '',
        'address' => $currentUser['address'] ?? '',
        'memberSince' => $memberSince,
    ],
    'favorites' => array_values(array_unique($currentUser['favorites'] ?? [])),
    'settings' => $settings,
    'applications' => $applications,
    'messages' => $messages,
    'stats' => [
        'favorites' => count(array_unique($currentUser['favorites'] ?? [])),
        'applications' => count($applications),
        'adoptions' => count(array_filter($applications, static fn ($item) => ($item['status'] ?? '') === 'Completed')),
        'messages' => count(array_filter($messages, static fn ($item) => ($item['sender'] ?? '') === 'team')),
    ],
]);
