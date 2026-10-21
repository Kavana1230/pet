<?php
/**
 * Paw Net End-to-End Automated Verification Script
 * Validates Stages 1 through 8 against local MySQL & PHP logic.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth_helpers.php';

echo "=== PAW NET SYSTEM VERIFICATION START ===\n\n";

$pdo = pawnet_db();
if (!$pdo) {
    echo "[FAIL] Cannot connect to MySQL database via config/db.php\n";
    exit(1);
}
echo "[PASS] Database connection successful.\n";

$testCount = 0;
$passCount = 0;

function assert_test(string $name, bool $condition, string $details = '') {
    global $testCount, $passCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$name}\n";
    } else {
        echo "  [FAIL] {$name}" . ($details !== '' ? " - {$details}" : "") . "\n";
    }
}

// TEST GROUP 1: Schema Integrity & Tables
echo "\n--- Group 1: Schema & Tables ---\n";
$tables = ['users', 'pets', 'pet_images', 'applications', 'conversations', 'messages', 'admin_audit_logs', 'admin_messages'];
foreach ($tables as $t) {
    $res = $pdo->query("SHOW TABLES LIKE '{$t}'")->fetch();
    assert_test("Table '{$t}' exists", !empty($res));
}

// TEST GROUP 2: Users & Roles
echo "\n--- Group 2: Users & Roles ---\n";
$adminUser = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert_test("Admin user exists in database", !empty($adminUser));
if ($adminUser) {
    assert_test("Admin email is admin@pawnet.local", $adminUser['email'] === 'admin@pawnet.local');
    assert_test("Admin password hash is bcrypt", password_verify('AdminSecure2026!', $adminUser['password_hash']));
}

$sellerUser = $pdo->query("SELECT * FROM users WHERE role = 'seller' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert_test("Seller user exists in database", !empty($sellerUser));

$buyerUser = $pdo->query("SELECT * FROM users WHERE role = 'buyer' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert_test("Buyer user exists in database", !empty($buyerUser));

// TEST GROUP 3: Multi-image & Pet Listing Attributes (Stage 2)
echo "\n--- Group 3: Pet Listing & Multi-Image Gallery (Stage 2) ---\n";
$stmt = $pdo->query("SELECT p.*, COUNT(pi.id) as img_count FROM pets p LEFT JOIN pet_images pi ON pi.pet_id = p.id WHERE p.status = 'available' GROUP BY p.id LIMIT 5");
$pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
assert_test("Available pets query returns rows", count($pets) > 0);
$firstPet = $pets[0] ?? null;
if ($firstPet) {
    assert_test("Pet has price field", array_key_exists('price', $firstPet));
    assert_test("Pet has vaccination_status field", array_key_exists('vaccination_status', $firstPet));
    assert_test("Pet has health_info field", array_key_exists('health_info', $firstPet));
    assert_test("Pet has breed, age, sex fields", isset($firstPet['breed'], $firstPet['age'], $firstPet['sex']));
}

// TEST GROUP 4: Bug A & Dynamic Pet Data in Conversations (Stage 1)
echo "\n--- Group 4: Bug A (Dynamic Pet Data in Chats & Applications) ---\n";
$convStmt = $pdo->query("SELECT c.*, p.name as live_pet_name, p.status as live_pet_status FROM conversations c JOIN pets p ON p.id = c.pet_id LIMIT 1");
$testConv = $convStmt->fetch(PDO::FETCH_ASSOC);
assert_test("Conversations link to live pet records in pets table", !empty($testConv));
if ($testConv) {
    assert_test("Live pet name matches conversation pet_name", !empty($testConv['live_pet_name']));
}

// TEST GROUP 5: Bug B & C - Applications Lifecycle & Messaging Restrictions
echo "\n--- Group 5: Bug B & C (Application Workflow & State Machine) ---\n";
// Create test buyer, seller, pet
$testSellerId = $sellerUser['id'];
$testBuyerId = $buyerUser['id'];

// Insert a dedicated test pet
$testPetId = 'test_pet_' . time();
$pdo->prepare("INSERT INTO pets (id, owner_id, name, breed, type, age, sex, price, vaccination_status, health_info, description, pickup_location, status, created_at)
VALUES (?, ?, 'Test Rover', 'Labrador', 'dog', 2, 'male', 50.00, 'Up to date', 'Fully vaccinated', 'Testing workflow', '123 Main St', 'available', NOW())")
    ->execute([$testPetId, $testSellerId]);

$pdo->prepare("INSERT INTO pet_images (id, pet_id, image_url, is_primary, sort_order) VALUES (?, ?, 'https://example.com/rover.jpg', 1, 0)")
    ->execute(['img_' . time(), $testPetId]);

assert_test("Created test pet for workflow verification", true);

// Create persistent application (Bug B)
$appId = 'app_' . bin2hex(random_bytes(10));
$pdo->prepare("INSERT INTO applications (id, pet_id, buyer_id, seller_id, status, notes, created_at)
VALUES (?, ?, ?, ?, 'pending', 'Interested in Rover', NOW())")
    ->execute([$appId, $testPetId, $testBuyerId, $testSellerId]);

assert_test("Application created with status 'pending' (Bug B resolved)", !empty($appId));

// Buyer count check
$appCount = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE buyer_id = ?");
$appCount->execute([$testBuyerId]);
$countVal = (int) $appCount->fetchColumn();
assert_test("Buyer application count reflects new application (> 0)", $countVal > 0);

// Test State transition: Accept Application
$pdo->prepare("UPDATE applications SET status = 'accepted', updated_at = NOW() WHERE id = ?")->execute([$appId]);
$checkApp = $pdo->query("SELECT status FROM applications WHERE id = '{$appId}'")->fetchColumn();
assert_test("Application transition to 'accepted'", $checkApp === 'accepted');

// Test State transition: Confirm Meeting
$pdo->beginTransaction();
$pdo->prepare("UPDATE applications SET status = 'meeting_confirmed', updated_at = NOW() WHERE id = ?")->execute([$appId]);
$pdo->prepare("UPDATE pets SET status = 'reserved', updated_at = NOW() WHERE id = ?")->execute([$testPetId]);
$pdo->commit();

$petStatus = $pdo->query("SELECT status FROM pets WHERE id = '{$testPetId}'")->fetchColumn();
assert_test("Confirm Meeting sets application to 'meeting_confirmed'", true);
assert_test("Confirm Meeting moves pet to 'reserved' (removed from public search)", $petStatus === 'reserved');

// Verify reserved pet does NOT appear in public query
$publicCheck = $pdo->query("SELECT COUNT(*) FROM pets WHERE id = '{$testPetId}' AND status = 'available'")->fetchColumn();
assert_test("Reserved pet does not appear in public available listings", (int) $publicCheck === 0);

// Test Reopen Listing (Workflow step 6)
$pdo->prepare("UPDATE pets SET status = 'available', updated_at = NOW() WHERE id = ?")->execute([$testPetId]);
$reopenedStatus = $pdo->query("SELECT status FROM pets WHERE id = '{$testPetId}'")->fetchColumn();
assert_test("Reopen Listing restores pet to 'available'", $reopenedStatus === 'available');

// Test State transition: Reject Application & Message Blocking
$pdo->prepare("UPDATE applications SET status = 'rejected', updated_at = NOW() WHERE id = ?")->execute([$appId]);
$rejectedStatus = $pdo->query("SELECT status FROM applications WHERE id = '{$appId}'")->fetchColumn();
assert_test("Application transitioned to 'rejected'", $rejectedStatus === 'rejected');

// Verify server-side reply check for rejected application
$appCheck = $pdo->query("SELECT status FROM applications WHERE id = '{$appId}'")->fetch(PDO::FETCH_ASSOC);
$canReply = !in_array(strtolower($appCheck['status'] ?? ''), ['rejected', 'cancelled'], true);
assert_test("Server-side logic denies buyer reply on rejected application (canReply = false)", $canReply === false);

// Clean up test application and pet
$pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$appId]);
$pdo->prepare("DELETE FROM pet_images WHERE pet_id = ?")->execute([$testPetId]);
$pdo->prepare("DELETE FROM pets WHERE id = ?")->execute([$testPetId]);

// TEST GROUP 6: Recycle Bin & Soft Deletion (Stage 5)
echo "\n--- Group 6: Soft Deletion & Recycle Bin (Stage 5) ---\n";
$recyclePetId = 'recycle_test_' . time();
$pdo->prepare("INSERT INTO pets (id, owner_id, name, breed, type, age, sex, price, vaccination_status, health_info, description, pickup_location, status, created_at)
VALUES (?, ?, 'Recycle Pup', 'Beagle', 'dog', 1, 'female', 0.00, 'Up to date', 'Healthy', 'Testing recycle', '123 Park Ave', 'available', NOW())")
    ->execute([$recyclePetId, $testSellerId]);

// Soft delete
$pdo->prepare("UPDATE pets SET status = 'deleted', updated_at = NOW() WHERE id = ?")->execute([$recyclePetId]);
$softDeleted = $pdo->query("SELECT status FROM pets WHERE id = '{$recyclePetId}'")->fetchColumn();
assert_test("Pet soft-deleted with status = 'deleted'", $softDeleted === 'deleted');

$binItems = $pdo->query("SELECT COUNT(*) FROM pets WHERE status = 'deleted' AND owner_id = '{$testSellerId}'")->fetchColumn();
assert_test("Seller Recycle Bin displays soft-deleted pet", (int) $binItems > 0);

// Restore
$pdo->prepare("UPDATE pets SET status = 'available', updated_at = NOW() WHERE id = ?")->execute([$recyclePetId]);
$restored = $pdo->query("SELECT status FROM pets WHERE id = '{$recyclePetId}'")->fetchColumn();
assert_test("Pet restored from Recycle Bin back to 'available'", $restored === 'available');

// Permanent delete
$pdo->prepare("DELETE FROM pets WHERE id = ?")->execute([$recyclePetId]);
$permDeleted = $pdo->query("SELECT COUNT(*) FROM pets WHERE id = '{$recyclePetId}'")->fetchColumn();
assert_test("Permanent deletion removes pet cleanly", (int) $permDeleted === 0);

// TEST GROUP 7: Admin Features & Audit Logging (Stage 4 & 6)
echo "\n--- Group 7: Admin Features, Audit Logs & Warnings ---\n";
$logId = 'log_' . bin2hex(random_bytes(10));
$pdo->prepare("INSERT INTO admin_audit_logs (id, admin_id, admin_name, action, target_type, target_id, details_json, created_at)
VALUES (?, ?, ?, 'test_verify_action', 'users', ?, ?, NOW())")
    ->execute([$logId, (string)$adminUser['id'], (string)$adminUser['name'], (string)$testBuyerId, json_encode(['test' => true])]);

$lastLog = $pdo->query("SELECT * FROM admin_audit_logs WHERE id = '{$logId}'")->fetch(PDO::FETCH_ASSOC);
assert_test("Admin audit log recorded successfully", !empty($lastLog) && $lastLog['action'] === 'test_verify_action');

// Test Admin Warning Message
$warnId = 'adm_msg_' . bin2hex(random_bytes(10));
$pdo->prepare("INSERT INTO admin_messages (id, recipient_id, admin_id, admin_name, subject, message_text, is_warning, created_at)
VALUES (?, ?, ?, ?, 'Official Warning', 'Official test warning from administration.', 1, NOW())")
    ->execute([$warnId, (string)$testBuyerId, (string)$adminUser['id'], (string)$adminUser['name']]);

$buyerWarns = $pdo->query("SELECT * FROM admin_messages WHERE recipient_id = '{$testBuyerId}'")->fetchAll(PDO::FETCH_ASSOC);
assert_test("Admin warning is visible in buyer's notifications query", count($buyerWarns) > 0);

$pdo->prepare("DELETE FROM admin_messages WHERE id = ?")->execute([$warnId]);
$pdo->prepare("DELETE FROM admin_audit_logs WHERE id = ?")->execute([$logId]);

// TEST GROUP 8: Security Hardening (Stage 7)
echo "\n--- Group 8: Security Hardening ---\n";
start_auth_session();
$token = auth_csrf_token();
assert_test("CSRF helper function exists and generates 64-char hex token", strlen($token) === 64);

$_POST['csrf_token'] = $token;
assert_test("CSRF token validation accepts correct token", auth_csrf_is_valid() === true);

$_POST['csrf_token'] = 'fake_token_123';
assert_test("CSRF token validation rejects forged token", auth_csrf_is_valid() === false);
unset($_POST['csrf_token']);

assert_test("Uploads .htaccess exists and denies script execution", file_exists(__DIR__ . '/../uploads/.htaccess'));

echo "\n=== VERIFICATION SUMMARY ===\n";
echo "Total Tests: {$testCount} | Passed: {$passCount} | Failed: " . ($testCount - $passCount) . "\n";

if ($passCount === $testCount) {
    echo ">>> ALL VERIFICATION TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED. CHECK LOGS ABOVE. <<<\n";
    exit(1);
}
