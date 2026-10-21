<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../config/database.php';

$config = require __DIR__ . '/../config/database.php';
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "Starting data migration...\n";

// 1. Migrate accounts.php to users table
$accountsFile = __DIR__ . '/../data/accounts.php';
$accounts = [];
if (file_exists($accountsFile)) {
    $accounts = (array) include $accountsFile;
}

$userStmt = $pdo->prepare('
    INSERT INTO users (id, name, email, phone, address, role, status, password_hash, favorites_json, settings_json, created_at)
    VALUES (:id, :name, :email, :phone, :address, :role, :status, :password_hash, :favorites_json, :settings_json, :created_at)
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        phone = VALUES(phone),
        address = VALUES(address),
        role = VALUES(role),
        favorites_json = VALUES(favorites_json),
        settings_json = VALUES(settings_json)
');

$userCount = 0;
foreach ($accounts as $account) {
    if (empty($account['id']) || empty($account['email'])) {
        continue;
    }
    $createdAt = !empty($account['createdAt']) ? date('Y-m-d H:i:s', strtotime((string)$account['createdAt'])) : date('Y-m-d H:i:s');
    $userStmt->execute([
        'id' => (string) $account['id'],
        'name' => (string) ($account['name'] ?? 'User'),
        'email' => strtolower((string) $account['email']),
        'phone' => (string) ($account['phone'] ?? ''),
        'address' => (string) ($account['address'] ?? ''),
        'role' => in_array($account['role'] ?? '', ['buyer', 'seller', 'admin'], true) ? $account['role'] : 'buyer',
        'status' => 'active',
        'password_hash' => (string) ($account['passwordHash'] ?? ''),
        'favorites_json' => json_encode($account['favorites'] ?? []),
        'settings_json' => json_encode($account['settings'] ?? []),
        'created_at' => $createdAt,
    ]);
    $userCount++;
}
echo "Migrated {$userCount} accounts to MySQL users table.\n";

// 2. Migrate pets.json to pets and pet_images
$petsFile = __DIR__ . '/../data/pets.json';
$pets = [];
if (file_exists($petsFile)) {
    $decoded = json_decode((string) file_get_contents($petsFile), true);
    if (is_array($decoded)) {
        $pets = $decoded;
    }
}

// Add sample catalog pets if only 1 pet was present or catalog is empty
$defaultSamplePets = [
    [
        'id' => 'sample_bruno_dog',
        'ownerId' => '380ad8860fbf0d20913e568ed334cc85',
        'status' => 'available',
        'name' => 'Bruno',
        'breed' => 'Labrador Retriever',
        'type' => 'dog',
        'sex' => 'male',
        'age' => 2,
        'price' => 75.00,
        'healthInfo' => 'Healthy, microchipped, fully vaccinated, loves fetch and swimming.',
        'vaccinationStatus' => 'Fully vaccinated',
        'image' => 'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80',
        'description' => 'Bruno is a cheerful, gentle companion who loves long park walks and cuddling after playtime.',
        'pickupLocation' => 'Sunset Boulevard, Jodhpur',
        'createdAt' => '2026-10-15T10:00:00+02:00',
    ],
    [
        'id' => 'sample_luna_cat',
        'ownerId' => '380ad8860fbf0d20913e568ed334cc85',
        'status' => 'available',
        'name' => 'Luna',
        'breed' => 'Persian Cat',
        'type' => 'cat',
        'sex' => 'female',
        'age' => 1,
        'price' => 60.00,
        'healthInfo' => 'Spayed, dewormed, healthy coat, calm temperament.',
        'vaccinationStatus' => 'Up to date',
        'image' => 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?auto=format&fit=crop&w=900&q=80',
        'description' => 'Luna is calm, affectionate, and loves relaxing in sunny spots with quiet humans.',
        'pickupLocation' => 'Green Garden Avenue, Jodhpur',
        'createdAt' => '2026-10-16T11:30:00+02:00',
    ],
    [
        'id' => 'sample_pippin_hamster',
        'ownerId' => '380ad8860fbf0d20913e568ed334cc85',
        'status' => 'available',
        'name' => 'Pippin',
        'breed' => 'Syrian Hamster',
        'type' => 'hamster',
        'sex' => 'male',
        'age' => 1,
        'price' => 25.00,
        'healthInfo' => 'Active, healthy teeth, energetic wheel runner.',
        'vaccinationStatus' => 'Not required',
        'image' => 'https://images.unsplash.com/photo-1425082661705-1834bfd09dca?auto=format&fit=crop&w=900&q=80',
        'description' => 'Pippin is a curious little explorer who enjoys tunnels, gentle handling, and fresh veggie snacks.',
        'pickupLocation' => 'Central Market Road, Jodhpur',
        'createdAt' => '2026-10-17T09:15:00+02:00',
    ],
    [
        'id' => 'sample_milo_cat',
        'ownerId' => '380ad8860fbf0d20913e568ed334cc85',
        'status' => 'available',
        'name' => 'Milo',
        'breed' => 'Domestic Shorthair',
        'type' => 'cat',
        'sex' => 'male',
        'age' => 2,
        'price' => 45.00,
        'healthInfo' => 'Neutered, vaccinated, indoor-only cat.',
        'vaccinationStatus' => 'Up to date',
        'image' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=900&q=80',
        'description' => 'Milo is gentle, playful, and loves exploring cozy corners and soft fleece blankets.',
        'pickupLocation' => 'City Park View, Jodhpur',
        'createdAt' => '2026-10-18T14:20:00+02:00',
    ],
];

$existingIds = array_column($pets, 'id');
foreach ($defaultSamplePets as $sample) {
    if (!in_array($sample['id'], $existingIds, true)) {
        $pets[] = $sample;
    }
}

$petStmt = $pdo->prepare('
    INSERT INTO pets (id, owner_id, name, breed, type, sex, age, price, health_info, vaccination_status, description, pickup_location, status, created_at, updated_at, sold_at)
    VALUES (:id, :owner_id, :name, :breed, :type, :sex, :age, :price, :health_info, :vaccination_status, :description, :pickup_location, :status, :created_at, :updated_at, :sold_at)
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        breed = VALUES(breed),
        type = VALUES(type),
        sex = VALUES(sex),
        age = VALUES(age),
        price = VALUES(price),
        health_info = VALUES(health_info),
        vaccination_status = VALUES(vaccination_status),
        description = VALUES(description),
        pickup_location = VALUES(pickup_location),
        status = VALUES(status)
');

$imgStmt = $pdo->prepare('
    INSERT IGNORE INTO pet_images (id, pet_id, image_url, is_primary, sort_order, created_at)
    VALUES (:id, :pet_id, :image_url, :is_primary, :sort_order, :created_at)
');

$petCount = 0;
foreach ($pets as $p) {
    if (empty($p['name'])) {
        continue;
    }
    $id = !empty($p['id']) ? (string) $p['id'] : bin2hex(random_bytes(12));
    $ownerId = !empty($p['ownerId']) ? (string) $p['ownerId'] : '380ad8860fbf0d20913e568ed334cc85';
    $status = in_array($p['status'] ?? '', ['available', 'reserved', 'sold', 'deleted'], true) ? $p['status'] : 'available';
    $sex = strtolower((string) ($p['sex'] ?? 'unknown'));
    if (!in_array($sex, ['male', 'female'], true)) {
        $sex = 'unknown';
    }
    $createdAt = !empty($p['createdAt']) ? date('Y-m-d H:i:s', strtotime((string)$p['createdAt'])) : date('Y-m-d H:i:s');
    $updatedAt = !empty($p['updatedAt']) ? date('Y-m-d H:i:s', strtotime((string)$p['updatedAt'])) : $createdAt;
    $soldAt = !empty($p['soldAt']) ? date('Y-m-d H:i:s', strtotime((string)$p['soldAt'])) : null;

    $petStmt->execute([
        'id' => $id,
        'owner_id' => $ownerId,
        'name' => (string) $p['name'],
        'breed' => (string) ($p['breed'] ?? 'Mixed'),
        'type' => strtolower((string) ($p['type'] ?? 'dog')),
        'sex' => $sex,
        'age' => (int) ($p['age'] ?? 1),
        'price' => (float) ($p['price'] ?? 0.00),
        'health_info' => (string) ($p['healthInfo'] ?? 'Good overall health'),
        'vaccination_status' => (string) ($p['vaccinationStatus'] ?? 'Up to date'),
        'description' => (string) ($p['description'] ?? 'Friendly and looking for a loving home.'),
        'pickup_location' => (string) ($p['pickupLocation'] ?? 'Contact seller for pickup address'),
        'status' => $status,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
        'sold_at' => $soldAt,
    ]);

    // Insert primary image
    $mainImage = (string) ($p['image'] ?? 'https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80');
    $imgStmt->execute([
        'id' => bin2hex(random_bytes(16)),
        'pet_id' => $id,
        'image_url' => $mainImage,
        'is_primary' => 1,
        'sort_order' => 0,
        'created_at' => $createdAt,
    ]);

    // If additional images exist
    if (!empty($p['images']) && is_array($p['images'])) {
        $order = 1;
        foreach ($p['images'] as $imgUrl) {
            if (is_string($imgUrl) && $imgUrl !== '' && $imgUrl !== $mainImage) {
                $imgStmt->execute([
                    'id' => bin2hex(random_bytes(16)),
                    'pet_id' => $id,
                    'image_url' => $imgUrl,
                    'is_primary' => 0,
                    'sort_order' => $order++,
                    'created_at' => $createdAt,
                ]);
            }
        }
    }
    $petCount++;
}
echo "Migrated {$petCount} pets with primary images to MySQL.\n";

// 3. Link conversations with applications
$convs = $pdo->query('SELECT id, pet_id, buyer_id, seller_id, created_at FROM conversations')->fetchAll();
$appStmt = $pdo->prepare('
    INSERT INTO applications (id, pet_id, buyer_id, seller_id, conversation_id, status, notes, created_at, updated_at)
    VALUES (:id, :pet_id, :buyer_id, :seller_id, :conversation_id, :status, :notes, :created_at, :updated_at)
    ON DUPLICATE KEY UPDATE conversation_id = VALUES(conversation_id)
');
$updateConvStmt = $pdo->prepare('UPDATE conversations SET application_id = :app_id WHERE id = :conv_id');

$appCount = 0;
foreach ($convs as $c) {
    $appId = bin2hex(random_bytes(16));
    $appStmt->execute([
        'id' => $appId,
        'pet_id' => $c['pet_id'],
        'buyer_id' => $c['buyer_id'],
        'seller_id' => $c['seller_id'],
        'conversation_id' => $c['id'],
        'status' => 'pending',
        'notes' => 'Adoption application initiated via chat inquiry',
        'created_at' => $c['created_at'],
        'updated_at' => $c['created_at'],
    ]);
    // find actual application ID (in case of duplicate key)
    $fetchApp = $pdo->prepare('SELECT id FROM applications WHERE pet_id = :p AND buyer_id = :b');
    $fetchApp->execute(['p' => $c['pet_id'], 'b' => $c['buyer_id']]);
    $realAppId = $fetchApp->fetchColumn();
    if ($realAppId) {
        $updateConvStmt->execute(['app_id' => $realAppId, 'conv_id' => $c['id']]);
        $appCount++;
    }
}
echo "Linked {$appCount} conversations with applications in MySQL.\n";

echo "Migration finished successfully!\n";
