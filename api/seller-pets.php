<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

$user = api_require_user(['GET', 'POST'], 'seller');
$userId = (string) $user['id'];
$database = pawnet_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filter = is_string($_GET['filter'] ?? null) ? $_GET['filter'] : 'all';
    $where = 'WHERE p.owner_id = :owner_id';
    $params = ['owner_id' => $userId];

    if ($filter === 'available') {
        $where .= ' AND p.status = "available"';
    } elseif ($filter === 'reserved') {
        $where .= ' AND p.status = "reserved"';
    } elseif ($filter === 'sold') {
        $where .= ' AND p.status = "sold"';
    } elseif ($filter === 'deleted') {
        $where .= ' AND p.status = "deleted"';
    } else {
        $where .= ' AND p.status != "deleted"'; // 'all' view shows active/sold/reserved, recycle bin is separate
    }

    $stmt = $database->prepare("
        SELECT p.id, p.owner_id AS ownerId, p.name, p.breed, p.type, p.sex, p.age, p.price,
               p.health_info AS healthInfo, p.vaccination_status AS vaccinationStatus,
               p.description, p.pickup_location AS pickupLocation, p.status,
               p.created_at AS createdAt, p.updated_at AS updatedAt, p.sold_at AS soldAt, p.deleted_at AS deletedAt,
               COALESCE(
                   (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id AND pi.is_primary = 1 LIMIT 1),
                   (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id ORDER BY pi.sort_order ASC LIMIT 1),
                   'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80'
               ) AS image
        FROM pets p
        $where
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $pets = $stmt->fetchAll();

    // Fetch images for each pet
    if (!empty($pets)) {
        $petIds = array_column($pets, 'id');
        $inQuery = implode(',', array_fill(0, count($petIds), '?'));
        $imgStmt = $database->prepare("SELECT id, pet_id, image_url, is_primary, sort_order FROM pet_images WHERE pet_id IN ($inQuery) ORDER BY sort_order ASC");
        $imgStmt->execute($petIds);
        $imagesByPet = [];
        foreach ($imgStmt->fetchAll() as $img) {
            $imagesByPet[$img['pet_id']][] = [
                'id' => $img['id'],
                'url' => $img['image_url'],
                'isPrimary' => (bool) $img['is_primary'],
            ];
        }

        foreach ($pets as &$p) {
            $p['images'] = $imagesByPet[$p['id']] ?? [['id' => '1', 'url' => $p['image'], 'isPrimary' => true]];
            $p['age'] = (int) $p['age'];
            $p['price'] = (float) $p['price'];
        }
        unset($p);
    }

    // Also get overall counts for seller dashboard
    $countStmt = $database->prepare('
        SELECT status, COUNT(*) AS count
        FROM pets
        WHERE owner_id = :owner_id
        GROUP BY status
    ');
    $countStmt->execute(['owner_id' => $userId]);
    $counts = [
        'total' => 0,
        'available' => 0,
        'reserved' => 0,
        'sold' => 0,
        'deleted' => 0,
    ];
    foreach ($countStmt->fetchAll() as $row) {
        $st = $row['status'];
        $cnt = (int) $row['count'];
        if (isset($counts[$st])) {
            $counts[$st] = $cnt;
        }
        if ($st !== 'deleted') {
            $counts['total'] += $cnt;
        }
    }

    api_respond(['success' => true, 'pets' => $pets, 'counts' => $counts]);
}

// POST actions
$input = api_read_input();
$action = is_string($input['action'] ?? null) ? $input['action'] : 'create';

// 1. File upload handler (Stage 2)
if ($action === 'upload_image' || (!empty($_FILES['image_file']) && $action === 'upload')) {
    if (empty($_FILES['image_file']) || !is_uploaded_file($_FILES['image_file']['tmp_name'])) {
        api_respond(['success' => false, 'message' => 'No image file uploaded.'], 400);
    }

    $file = $_FILES['image_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        api_respond(['success' => false, 'message' => 'Upload error: code ' . $file['error']], 400);
    }

    // Limit size to 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        api_respond(['success' => false, 'message' => 'Image file must be under 5MB.'], 400);
    }

    // Validate MIME type securely using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowedMimes[$mime])) {
        api_respond(['success' => false, 'message' => 'Invalid file format. Please upload JPG, PNG, WEBP, or GIF.'], 400);
    }

    $ext = $allowedMimes[$mime];
    $filename = 'pet_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $targetDir = __DIR__ . '/../uploads/pets';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        api_respond(['success' => false, 'message' => 'Could not save the uploaded image file.'], 500);
    }

    $relativeUrl = 'uploads/pets/' . $filename;
    api_respond(['success' => true, 'imageUrl' => $relativeUrl, 'message' => 'Image uploaded successfully.']);
}

// 2. Mark as sold
if ($action === 'sold') {
    $petId = is_string($input['id'] ?? null) ? trim($input['id']) : '';
    $stmt = $database->prepare('UPDATE pets SET status = "sold", sold_at = NOW(6), updated_at = NOW(6) WHERE id = :id AND owner_id = :owner_id');
    $stmt->execute(['id' => $petId, 'owner_id' => $userId]);
    if ($stmt->rowCount() === 0) {
        api_respond(['success' => false, 'message' => 'Listing not found in your account.'], 404);
    }
    api_respond(['success' => true, 'message' => 'Marked as sold! This pet has found a loving home.']);
}

// 3. Soft Delete / Recycle Bin (Stage 5)
if ($action === 'delete') {
    $petId = is_string($input['id'] ?? null) ? trim($input['id']) : '';
    $stmt = $database->prepare('UPDATE pets SET status = "deleted", deleted_at = NOW(6), updated_at = NOW(6) WHERE id = :id AND owner_id = :owner_id');
    $stmt->execute(['id' => $petId, 'owner_id' => $userId]);
    if ($stmt->rowCount() === 0) {
        api_respond(['success' => false, 'message' => 'Listing not found in your account.'], 404);
    }
    api_respond(['success' => true, 'message' => 'Listing moved to Recycle Bin. You can restore it anytime.']);
}

// 4. Restore from Recycle Bin (Stage 5)
if ($action === 'restore') {
    $petId = is_string($input['id'] ?? null) ? trim($input['id']) : '';
    $stmt = $database->prepare('UPDATE pets SET status = "available", deleted_at = NULL, updated_at = NOW(6) WHERE id = :id AND owner_id = :owner_id');
    $stmt->execute(['id' => $petId, 'owner_id' => $userId]);
    if ($stmt->rowCount() === 0) {
        api_respond(['success' => false, 'message' => 'Listing not found in your Recycle Bin.'], 404);
    }
    api_respond(['success' => true, 'message' => 'Listing restored! It is now available in the adoption catalog.']);
}

// 5. Permanent Delete (Stage 5)
if ($action === 'permanent_delete') {
    $petId = is_string($input['id'] ?? null) ? trim($input['id']) : '';
    $database->beginTransaction();
    try {
        // Delete images first
        $database->prepare('DELETE FROM pet_images WHERE pet_id = :id')->execute(['id' => $petId]);
        // Delete pet
        $del = $database->prepare('DELETE FROM pets WHERE id = :id AND owner_id = :owner_id');
        $del->execute(['id' => $petId, 'owner_id' => $userId]);
        if ($del->rowCount() === 0) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Listing not found in your account.'], 404);
        }
        $database->commit();
        api_respond(['success' => true, 'message' => 'Listing permanently deleted.']);
    } catch (Exception $e) {
        $database->rollBack();
        error_log('Paw Net permanent delete error: ' . $e->getMessage());
        api_respond(['success' => false, 'message' => 'Could not permanently delete this listing.'], 500);
    }
}

// 6. Reopen a reserved listing (Stage 1 Bug C)
if ($action === 'reopen') {
    $petId = is_string($input['id'] ?? null) ? trim($input['id']) : '';
    $stmt = $database->prepare('UPDATE pets SET status = "available", updated_at = NOW(6) WHERE id = :id AND owner_id = :owner_id');
    $stmt->execute(['id' => $petId, 'owner_id' => $userId]);
    if ($stmt->rowCount() === 0) {
        api_respond(['success' => false, 'message' => 'Listing not found in your account.'], 404);
    }
    api_respond(['success' => true, 'message' => 'Listing reopened and restored to the active adoption catalog.']);
}

// Validation for Create and Update
$name = trim(is_string($input['name'] ?? null) ? $input['name'] : '');
$breed = trim(is_string($input['breed'] ?? null) ? $input['breed'] : '');
$type = strtolower(trim(is_string($input['type'] ?? null) ? $input['type'] : ''));
$sex = strtolower(trim(is_string($input['sex'] ?? null) ? $input['sex'] : ''));
$age = filter_var($input['age'] ?? null, FILTER_VALIDATE_INT);
$price = isset($input['price']) ? (float)$input['price'] : 0.00;
$healthInfo = trim(is_string($input['healthInfo'] ?? null) ? $input['healthInfo'] : 'Good overall health');
$vaccinationStatus = trim(is_string($input['vaccinationStatus'] ?? null) ? $input['vaccinationStatus'] : 'Up to date');
$pickupLocation = trim(is_string($input['pickupLocation'] ?? null) ? $input['pickupLocation'] : '');
$description = trim(is_string($input['description'] ?? null) ? $input['description'] : '');

// Multi-image list (Stage 2)
$imageUrls = [];
if (!empty($input['images']) && is_array($input['images'])) {
    foreach ($input['images'] as $img) {
        $url = trim(is_string($img) ? $img : ($img['url'] ?? ''));
        if ($url !== '') {
            $imageUrls[] = $url;
        }
    }
}
$singleImage = trim(is_string($input['image'] ?? null) ? $input['image'] : '');
if ($singleImage !== '' && !in_array($singleImage, $imageUrls, true)) {
    array_unshift($imageUrls, $singleImage);
}

if ($name === '' || strlen($name) > 60 || $breed === '' || strlen($breed) > 80
    || !in_array($type, ['cat', 'dog', 'hamster'], true)
    || !in_array($sex, ['male', 'female'], true)
    || $age === false || $age < 0 || $age > 40
    || $pickupLocation === '' || strlen($pickupLocation) > 250
    || $description === '' || strlen($description) > 1000) {
    api_respond(['success' => false, 'message' => 'Please fill all required pet details accurately (name, breed, type, sex, age, location, and description).'], 422);
}

// Fallback image if none provided
if (empty($imageUrls)) {
    $imageUrls[] = 'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80';
}

if ($action === 'update') {
    $petId = is_string($input['id'] ?? null) ? trim($input['id']) : '';
    $database->beginTransaction();
    try {
        $stmt = $database->prepare('
            UPDATE pets
            SET name = :name, breed = :breed, type = :type, sex = :sex, age = :age,
                price = :price, health_info = :health_info, vaccination_status = :vaccination_status,
                description = :description, pickup_location = :pickup_location, updated_at = NOW(6)
            WHERE id = :id AND owner_id = :owner_id
        ');
        $stmt->execute([
            'id' => $petId,
            'owner_id' => $userId,
            'name' => $name,
            'breed' => $breed,
            'type' => $type,
            'sex' => $sex,
            'age' => $age,
            'price' => $price,
            'health_info' => $healthInfo,
            'vaccination_status' => $vaccinationStatus,
            'description' => $description,
            'pickup_location' => $pickupLocation,
        ]);

        if ($stmt->rowCount() === 0) {
            // Verify if pet exists
            $chk = $database->prepare('SELECT id FROM pets WHERE id = :id AND owner_id = :owner_id');
            $chk->execute(['id' => $petId, 'owner_id' => $userId]);
            if (!$chk->fetch()) {
                $database->rollBack();
                api_respond(['success' => false, 'message' => 'Listing not found in your account.'], 404);
            }
        }

        // Replace or update images
        if (!empty($imageUrls)) {
            $database->prepare('DELETE FROM pet_images WHERE pet_id = :pet_id')->execute(['pet_id' => $petId]);
            $insImg = $database->prepare('
                INSERT INTO pet_images (id, pet_id, image_url, is_primary, sort_order, created_at)
                VALUES (:id, :pet_id, :image_url, :is_primary, :sort_order, NOW(6))
            ');
            $order = 0;
            foreach ($imageUrls as $imgUrl) {
                $insImg->execute([
                    'id' => bin2hex(random_bytes(16)),
                    'pet_id' => $petId,
                    'image_url' => $imgUrl,
                    'is_primary' => ($order === 0) ? 1 : 0,
                    'sort_order' => $order++,
                ]);
            }
        }

        $database->commit();
        api_respond(['success' => true, 'message' => 'Your listing has been updated.']);
    } catch (Exception $e) {
        $database->rollBack();
        error_log('Paw Net update pet error: ' . $e->getMessage());
        api_respond(['success' => false, 'message' => 'Could not update listing.'], 500);
    }
}

// Default action: Create new pet listing
$petId = bin2hex(random_bytes(12));
$database->beginTransaction();
try {
    $stmt = $database->prepare('
        INSERT INTO pets
            (id, owner_id, name, breed, type, sex, age, price, health_info, vaccination_status, description, pickup_location, status, created_at, updated_at)
        VALUES
            (:id, :owner_id, :name, :breed, :type, :sex, :age, :price, :health_info, :vaccination_status, :description, :pickup_location, "available", NOW(6), NOW(6))
    ');
    $stmt->execute([
        'id' => $petId,
        'owner_id' => $userId,
        'name' => $name,
        'breed' => $breed,
        'type' => $type,
        'sex' => $sex,
        'age' => $age,
        'price' => $price,
        'health_info' => $healthInfo,
        'vaccination_status' => $vaccinationStatus,
        'description' => $description,
        'pickup_location' => $pickupLocation,
    ]);

    // Insert pet images
    $insImg = $database->prepare('
        INSERT INTO pet_images (id, pet_id, image_url, is_primary, sort_order, created_at)
        VALUES (:id, :pet_id, :image_url, :is_primary, :sort_order, NOW(6))
    ');
    $order = 0;
    foreach ($imageUrls as $imgUrl) {
        $insImg->execute([
            'id' => bin2hex(random_bytes(16)),
            'pet_id' => $petId,
            'image_url' => $imgUrl,
            'is_primary' => ($order === 0) ? 1 : 0,
            'sort_order' => $order++,
        ]);
    }

    $database->commit();
    api_respond(['success' => true, 'message' => 'Your pet is now listed for adoption!', 'id' => $petId], 201);
} catch (Exception $e) {
    $database->rollBack();
    error_log('Paw Net create pet error: ' . $e->getMessage());
    api_respond(['success' => false, 'message' => 'Could not save listing.'], 500);
}
