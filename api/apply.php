<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

$user = api_require_user(['POST'], 'buyer');
$userId = (string) $user['id'];
$input = api_read_input();
$petId = is_string($input['petId'] ?? null) ? trim($input['petId']) : '';
$petName = is_string($input['petName'] ?? null) ? trim($input['petName']) : '';

if ($petId === '' && $petName === '') {
    api_respond(['success' => false, 'message' => 'Pet identifier is required.'], 400);
}

$database = pawnet_db();

// 1. Fetch pet
if ($petId !== '') {
    $stmt = $database->prepare('SELECT * FROM pets WHERE id = :id');
    $stmt->execute(['id' => $petId]);
} else {
    $stmt = $database->prepare('SELECT * FROM pets WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $petName]);
}
$pet = $stmt->fetch();

if (!$pet) {
    // Check fallback
    $pets = api_read_json_file(__DIR__ . '/../data/pets.json');
    foreach ($pets as $cand) {
        if (($petId !== '' && ($cand['id'] ?? '') === $petId) || ($cand['name'] ?? '') === $petName) {
            $pet = $cand;
            break;
        }
    }
}

if (!$pet || ($pet['status'] ?? 'available') !== 'available') {
    api_respond(['success' => false, 'message' => 'That pet is not available for adoption.'], 404);
}

$realPetId = (string) $pet['id'];
$sellerId = (string) ($pet['owner_id'] ?? $pet['ownerId'] ?? '');
$realPetName = (string) $pet['name'];

if ($sellerId === $userId) {
    api_respond(['success' => false, 'message' => 'You cannot apply to adopt your own listing.'], 403);
}

// 2. Check duplicate application
$chk = $database->prepare('SELECT id, status FROM applications WHERE pet_id = :pet_id AND buyer_id = :buyer_id');
$chk->execute(['pet_id' => $realPetId, 'buyer_id' => $userId]);
$existing = $chk->fetch();
if ($existing) {
    api_respond(['success' => false, 'message' => 'You have already applied to adopt ' . $realPetName . '.'], 409);
}

// 3. Create application
$appId = bin2hex(random_bytes(16));
$insertApp = $database->prepare('
    INSERT INTO applications (id, pet_id, buyer_id, seller_id, status, notes, created_at, updated_at)
    VALUES (:id, :pet_id, :buyer_id, :seller_id, "pending", "Adoption request submitted via catalog modal", NOW(6), NOW(6))
');
$insertApp->execute([
    'id' => $appId,
    'pet_id' => $realPetId,
    'buyer_id' => $userId,
    'seller_id' => $sellerId,
]);

// Also update applications.json as fallback
$dataFile = __DIR__ . '/../data/applications.json';
$applications = api_read_json_file($dataFile);
$applications[] = [
    'id' => $appId,
    'userId' => $userId,
    'petName' => $realPetName,
    'status' => 'Pending Review',
    'date' => date('Y-m-d'),
];
api_write_json_file($dataFile, $applications);

api_respond([
    'success' => true,
    'message' => 'Your application for ' . $realPetName . ' has been submitted!',
    'application' => [
        'id' => $appId,
        'petId' => $realPetId,
        'petName' => $realPetName,
        'status' => 'pending',
        'date' => date('Y-m-d'),
    ]
]);
