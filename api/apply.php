<?php
require_once __DIR__ . '/common.php';
$user = api_require_user(['POST']);
$input = api_read_input();
$petName = is_string($input['petName'] ?? null) ? trim($input['petName']) : '';
if ($petName === '') {
    api_respond(['success' => false, 'message' => 'Pet name is required.'], 400);
}

$pets = api_read_json_file(__DIR__ . '/../data/pets.json');
$pet = null;
foreach ($pets as $availablePet) {
    if (($availablePet['name'] ?? '') === $petName && ($availablePet['status'] ?? 'available') !== 'sold' && in_array(strtolower($availablePet['type'] ?? ''), ['cat', 'dog', 'hamster'], true)) {
        $pet = $availablePet;
        break;
    }
}
if ($pet === null) {
    api_respond(['success' => false, 'message' => 'That pet is not available for adoption.'], 404);
}

$dataFile = __DIR__ . '/../data/applications.json';
$applications = api_read_json_file($dataFile);
$userId = (string) $user['id'];
foreach ($applications as $application) {
    if (($application['userId'] ?? '') === $userId && ($application['petName'] ?? '') === $petName) {
        api_respond(['success' => false, 'message' => 'You have already applied to adopt ' . $petName . '.'], 409);
    }
}
$newApplication = [
    'id' => bin2hex(random_bytes(12)),
    'userId' => $userId,
    'petName' => $petName,
    'status' => 'Under Review',
    'date' => date('Y-m-d'),
];
$applications[] = $newApplication;
if (!api_write_json_file($dataFile, $applications)) {
    api_respond(['success' => false, 'message' => 'Your application could not be saved. Check data folder write permissions.'], 500);
}

api_respond(['success' => true, 'application' => $newApplication]);
