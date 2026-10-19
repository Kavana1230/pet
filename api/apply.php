<?php
require_once __DIR__ . '/common.php';
$user = api_require_user(['POST'], 'buyer');
$input = api_read_input();
$petId = is_string($input['petId'] ?? null) ? trim($input['petId']) : '';
$petName = is_string($input['petName'] ?? null) ? trim($input['petName']) : '';
if ($petId === '' && $petName === '') {
    api_respond(['success' => false, 'message' => 'Pet name is required.'], 400);
}

$pets = api_read_json_file(__DIR__ . '/../data/pets.json');
$pet = null;
foreach ($pets as $availablePet) {
    $matchesPet = $petId !== ''
        ? ($availablePet['id'] ?? '') === $petId
        : ($availablePet['name'] ?? '') === $petName;
    if ($matchesPet && ($availablePet['status'] ?? 'available') !== 'sold' && in_array(strtolower($availablePet['type'] ?? ''), ['cat', 'dog', 'hamster'], true)) {
        $pet = $availablePet;
        break;
    }
}
if ($pet === null) {
    api_respond(['success' => false, 'message' => 'That pet is not available for adoption.'], 404);
}
$petName = (string) $pet['name'];
if (!empty($pet['ownerId']) && (string) $pet['ownerId'] === (string) $user['id']) {
    api_respond(['success' => false, 'message' => 'You cannot apply to adopt your own listing.'], 403);
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
