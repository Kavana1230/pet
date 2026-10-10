<?php
<<<<<<< HEAD
require_once __DIR__ . '/common.php';
api_require_user(['GET']);
=======
header('Content-Type: application/json');
>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7

$file = __DIR__ . '/../data/pets.json';

if (!file_exists($file)) {
    echo json_encode([]);
    exit;
}

$data = json_decode(file_get_contents($file), true);
$allowedTypes = ['cat', 'dog', 'hamster'];
$pets = array_filter($data ?: [], static function ($pet) use ($allowedTypes) {
<<<<<<< HEAD
    return isset($pet['type']) && in_array(strtolower($pet['type']), $allowedTypes, true)
        && ($pet['status'] ?? 'available') !== 'sold';
});

// Keep seller pickup addresses private until a buyer starts a conversation.
$pets = array_map(static function ($pet) {
    unset($pet['pickupLocation']);
    return $pet;
}, $pets);

=======
    return isset($pet['type']) && in_array(strtolower($pet['type']), $allowedTypes, true);
});

>>>>>>> 2fe54b1b48e42ea676b505281f68d6bd526c71b7
echo json_encode(array_values($pets));
