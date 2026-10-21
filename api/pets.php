<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
$sessionUser = api_require_user(['GET'], 'buyer');
$userId = (string) ($sessionUser['id'] ?? '');

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $pdo = pawnet_db();
    $where = 'WHERE p.status = "available"';
    $params = [];
    if ($userId !== '') {
        $where .= ' AND p.owner_id != :uid';
        $params['uid'] = $userId;
    }

    $stmt = $pdo->prepare("
        SELECT p.id, p.owner_id AS ownerId, owner.name AS ownerName, p.name, p.breed, p.type, p.sex, p.age,
               p.age_value AS ageValue, p.age_unit AS ageUnit, p.price,
               p.health_info AS healthInfo, p.vaccination_status AS vaccinationStatus,
               p.sterilized_status AS sterilizedStatus, p.dewormed_status AS dewormedStatus, p.trained_status AS trainedStatus,
               p.description, p.status, p.created_at AS createdAt, p.updated_at AS updatedAt,
               COALESCE(
                   (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id AND pi.is_primary = 1 LIMIT 1),
                   (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id ORDER BY pi.sort_order ASC LIMIT 1),
                   'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80'
               ) AS image
        FROM pets p
        LEFT JOIN users owner ON owner.id = p.owner_id AND owner.role = 'seller'
        $where
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $pets = $stmt->fetchAll();

    // Fetch all images for these pets
    if (!empty($pets)) {
        $petIds = array_column($pets, 'id');
        $inQuery = implode(',', array_fill(0, count($petIds), '?'));
        $imgStmt = $pdo->prepare("SELECT pet_id, image_url, is_primary FROM pet_images WHERE pet_id IN ($inQuery) ORDER BY sort_order ASC");
        $imgStmt->execute($petIds);
        $imagesByPet = [];
        foreach ($imgStmt->fetchAll() as $img) {
            $imagesByPet[$img['pet_id']][] = $img['image_url'];
        }

        foreach ($pets as &$pet) {
            $pet['images'] = $imagesByPet[$pet['id']] ?? [$pet['image']];
            $pet['age'] = (int) $pet['age'];
            $pet['ageValue'] = (float) $pet['ageValue'];
            $pet['price'] = (float) $pet['price'];
        }
        unset($pet);
    }

    echo json_encode(array_values($pets), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
} catch (Exception $e) {
    error_log('Paw Net pets API fallback: ' . $e->getMessage());
    $file = __DIR__ . '/../data/pets.json';
    if (!file_exists($file)) {
        echo json_encode([]);
        exit;
    }
    $data = json_decode((string) file_get_contents($file), true) ?: [];
    $allowedTypes = ['cat', 'dog', 'hamster'];
    $pets = array_filter($data, static function ($pet) use ($allowedTypes, $userId) {
        return isset($pet['type']) && in_array(strtolower($pet['type']), $allowedTypes, true)
            && ($pet['status'] ?? 'available') === 'available'
            && (empty($pet['ownerId']) || (string) $pet['ownerId'] !== $userId);
    });
    $pets = array_map(static function ($pet) {
        unset($pet['pickupLocation']);
        $pet['images'] = !empty($pet['images']) ? $pet['images'] : [$pet['image'] ?? ''];
        $pet['ageValue'] = isset($pet['ageValue']) ? (float) $pet['ageValue'] : (int) ($pet['age'] ?? 0);
        $pet['ageUnit'] = in_array($pet['ageUnit'] ?? '', ['days', 'months', 'years'], true) ? $pet['ageUnit'] : 'years';
        $pet['sterilizedStatus'] = $pet['sterilizedStatus'] ?? 'unknown';
        $pet['dewormedStatus'] = $pet['dewormedStatus'] ?? 'unknown';
        $pet['trainedStatus'] = $pet['trainedStatus'] ?? 'unknown';
        return $pet;
    }, $pets);
    echo json_encode(array_values($pets), JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
