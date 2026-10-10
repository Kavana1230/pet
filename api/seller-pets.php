<?php
require_once __DIR__ . '/common.php';
$user = api_require_user(['GET', 'POST']);
$path = __DIR__ . '/../data/pets.json';
$pets = api_read_json_file($path);
$userId = (string) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mine = array_values(array_filter($pets, static fn ($pet) => ($pet['ownerId'] ?? '') === $userId));
    api_respond(['success' => true, 'pets' => $mine]);
}

$input = api_read_input();
$action = is_string($input['action'] ?? null) ? $input['action'] : 'create';
if ($action === 'update') {
    $petId = is_string($input['id'] ?? null) ? $input['id'] : '';
    $name = trim(is_string($input['name'] ?? null) ? $input['name'] : '');
    $breed = trim(is_string($input['breed'] ?? null) ? $input['breed'] : '');
    $type = strtolower(trim(is_string($input['type'] ?? null) ? $input['type'] : ''));
    $sex = strtolower(trim(is_string($input['sex'] ?? null) ? $input['sex'] : ''));
    $age = filter_var($input['age'] ?? null, FILTER_VALIDATE_INT);
    $image = trim(is_string($input['image'] ?? null) ? $input['image'] : '');
    $pickupLocation = trim(is_string($input['pickupLocation'] ?? null) ? $input['pickupLocation'] : '');
    $description = trim(is_string($input['description'] ?? null) ? $input['description'] : '');
    if ($name === '' || strlen($name) > 60 || $breed === '' || strlen($breed) > 80 || !in_array($type, ['cat', 'dog', 'hamster'], true) || !in_array($sex, ['male', 'female'], true) || $age === false || $age < 0 || $age > 40 || strlen($image) > 500 || ($image !== '' && !filter_var($image, FILTER_VALIDATE_URL)) || $pickupLocation === '' || strlen($pickupLocation) > 250 || $description === '' || strlen($description) > 1000) {
        api_respond(['success' => false, 'message' => 'Enter the pet name, breed, type, sex, age, pickup location, and description. Provide a valid image URL if you use one.'], 422);
    }
    foreach ($pets as &$pet) {
        if (($pet['id'] ?? '') === $petId && ($pet['ownerId'] ?? '') === $userId && ($pet['status'] ?? 'available') !== 'sold') {
            $pet = array_merge($pet, [
                'name' => $name, 'breed' => $breed, 'type' => $type, 'sex' => $sex, 'age' => $age,
                'image' => $image !== '' ? $image : ($pet['image'] ?? 'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80'),
                'description' => $description, 'pickupLocation' => $pickupLocation, 'updatedAt' => date(DATE_ATOM),
            ]);
            unset($pet);
            if (!api_write_json_file($path, $pets)) {
                api_respond(['success' => false, 'message' => 'Could not update this listing. Check data folder write permissions.'], 500);
            }
            api_respond(['success' => true, 'message' => 'Your listing has been updated.']);
        }
    }
    unset($pet);
    api_respond(['success' => false, 'message' => 'This available listing was not found in your account.'], 404);
}
if ($action === 'sold') {
    $petId = is_string($input['id'] ?? null) ? $input['id'] : '';
    foreach ($pets as &$pet) {
        if (($pet['id'] ?? '') === $petId && ($pet['ownerId'] ?? '') === $userId) {
            $pet['status'] = 'sold';
            $pet['soldAt'] = date(DATE_ATOM);
            unset($pet);
            if (!api_write_json_file($path, $pets)) {
                api_respond(['success' => false, 'message' => 'Could not update this listing. Check data folder write permissions.'], 500);
            }
            api_respond(['success' => true, 'message' => 'Marked as sold. This pet is now hidden from the adoption page.']);
        }
    }
    unset($pet);
    api_respond(['success' => false, 'message' => 'That listing was not found in your account.'], 404);
}

$name = trim(is_string($input['name'] ?? null) ? $input['name'] : '');
$breed = trim(is_string($input['breed'] ?? null) ? $input['breed'] : '');
$type = strtolower(trim(is_string($input['type'] ?? null) ? $input['type'] : ''));
$sex = strtolower(trim(is_string($input['sex'] ?? null) ? $input['sex'] : ''));
$age = filter_var($input['age'] ?? null, FILTER_VALIDATE_INT);
$image = trim(is_string($input['image'] ?? null) ? $input['image'] : '');
$pickupLocation = trim(is_string($input['pickupLocation'] ?? null) ? $input['pickupLocation'] : '');
$description = trim(is_string($input['description'] ?? null) ? $input['description'] : '');
if ($name === '' || strlen($name) > 60 || strlen($breed) > 80 || $breed === '' || !in_array($type, ['cat', 'dog', 'hamster'], true) || !in_array($sex, ['male', 'female'], true) || $age === false || $age < 0 || $age > 40 || strlen($image) > 500 || ($image !== '' && !filter_var($image, FILTER_VALIDATE_URL)) || $pickupLocation === '' || strlen($pickupLocation) > 250 || $description === '' || strlen($description) > 1000) {
    api_respond(['success' => false, 'message' => 'Enter the pet name, breed, type, sex, age, pickup location, and description. Provide a valid image URL if you use one.'], 422);
}
$pets[] = [
    'id' => bin2hex(random_bytes(12)), 'ownerId' => $userId, 'status' => 'available',
    'name' => $name, 'breed' => $breed, 'type' => $type, 'sex' => $sex, 'age' => $age,
    'image' => $image !== '' ? $image : 'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80',
    'description' => $description, 'pickupLocation' => $pickupLocation, 'createdAt' => date(DATE_ATOM),
];
if (!api_write_json_file($path, $pets)) {
    api_respond(['success' => false, 'message' => 'Could not save your listing. Check data folder write permissions.'], 500);
}
api_respond(['success' => true, 'message' => 'Your pet is now listed for adoption.', 'pet' => end($pets)], 201);
