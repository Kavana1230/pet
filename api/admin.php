<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

$admin = api_require_user(['GET', 'POST'], 'admin');
$adminId = (string) $admin['id'];
$adminName = (string) ($admin['name'] ?? 'Paw Net Administrator');
$database = pawnet_db();

function record_admin_audit(PDO $db, string $adminId, string $adminName, string $action, string $targetType, string $targetId, array $details = []): void
{
    try {
        $stmt = $db->prepare('
            INSERT INTO admin_audit_logs (id, admin_id, admin_name, action, target_type, target_id, details_json, created_at)
            VALUES (:id, :admin_id, :admin_name, :action, :target_type, :target_id, :details_json, NOW(6))
        ');
        $stmt->execute([
            'id' => bin2hex(random_bytes(16)),
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details_json' => json_encode($details, JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Exception $e) {
        error_log('Audit log failure: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $section = is_string($_GET['section'] ?? null) ? $_GET['section'] : 'stats';

    if ($section === 'stats') {
        // Total stats
        $userCounts = $database->query('SELECT role, status, COUNT(*) AS count FROM users GROUP BY role, status')->fetchAll();
        $petCounts = $database->query('SELECT status, COUNT(*) AS count FROM pets GROUP BY status')->fetchAll();
        $appCounts = $database->query('SELECT status, COUNT(*) AS count FROM applications GROUP BY status')->fetchAll();

        $stats = [
            'users' => [
                'total' => 0,
                'buyers' => 0,
                'sellers' => 0,
                'admins' => 0,
                'suspended' => 0,
            ],
            'pets' => [
                'total' => 0,
                'available' => 0,
                'reserved' => 0,
                'sold' => 0,
                'deleted' => 0,
            ],
            'applications' => [
                'total' => 0,
                'pending' => 0,
                'accepted' => 0,
                'meeting_confirmed' => 0,
                'completed' => 0,
                'rejected' => 0,
                'cancelled' => 0,
            ],
        ];

        foreach ($userCounts as $r) {
            $role = (string) $r['role'];
            $st = (string) $r['status'];
            $cnt = (int) $r['count'];
            $stats['users']['total'] += $cnt;
            if ($role === 'buyer') $stats['users']['buyers'] += $cnt;
            if ($role === 'seller') $stats['users']['sellers'] += $cnt;
            if ($role === 'admin') $stats['users']['admins'] += $cnt;
            if ($st === 'suspended') $stats['users']['suspended'] += $cnt;
        }

        foreach ($petCounts as $r) {
            $st = (string) $r['status'];
            $cnt = (int) $r['count'];
            $stats['pets']['total'] += $cnt;
            if (isset($stats['pets'][$st])) {
                $stats['pets'][$st] = $cnt;
            }
        }

        foreach ($appCounts as $r) {
            $st = (string) $r['status'];
            $cnt = (int) $r['count'];
            $stats['applications']['total'] += $cnt;
            if (isset($stats['applications'][$st])) {
                $stats['applications'][$st] = $cnt;
            }
        }

        api_respond(['success' => true, 'stats' => $stats]);
    }

    if ($section === 'users') {
        $search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
        $roleFilter = trim(is_string($_GET['role'] ?? null) ? $_GET['role'] : 'all');
        $statusFilter = trim(is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all');

        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(name LIKE :s OR email LIKE :s OR phone LIKE :s)';
            $params['s'] = '%' . $search . '%';
        }
        if ($roleFilter !== 'all') {
            $where[] = 'role = :role';
            $params['role'] = $roleFilter;
        }
        if ($statusFilter !== 'all') {
            $where[] = 'status = :status';
            $params['status'] = $statusFilter;
        }

        $sql = 'SELECT id, name, email, phone, address, role, status, created_at AS createdAt FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 100';
        $stmt = $database->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        api_respond(['success' => true, 'users' => $users]);
    }

    if ($section === 'sellers') {
        // Seller directory (Stage 6)
        $search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
        $params = [];
        $searchSql = '';
        if ($search !== '') {
            $searchSql = ' AND (u.name LIKE :s OR u.email LIKE :s OR u.phone LIKE :s OR u.address LIKE :s)';
            $params['s'] = '%' . $search . '%';
        }

        $sql = "
            SELECT u.id, u.name, u.email, u.phone, u.address, u.status, u.created_at AS createdAt,
                   COUNT(DISTINCT CASE WHEN p.status = 'available' THEN p.id END) AS activePets,
                   COUNT(DISTINCT CASE WHEN p.status = 'reserved' THEN p.id END) AS reservedPets,
                   COUNT(DISTINCT CASE WHEN p.status = 'sold' THEN p.id END) AS soldPets,
                   COUNT(DISTINCT CASE WHEN p.status = 'deleted' THEN p.id END) AS deletedPets,
                   COUNT(DISTINCT a.id) AS totalApplications
            FROM users u
            LEFT JOIN pets p ON p.owner_id = u.id
            LEFT JOIN applications a ON a.seller_id = u.id
            WHERE u.role = 'seller'
            $searchSql
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ";
        $stmt = $database->prepare($sql);
        $stmt->execute($params);
        $sellers = $stmt->fetchAll();

        api_respond(['success' => true, 'sellers' => $sellers]);
    }

    if ($section === 'pets') {
        $search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
        $statusFilter = trim(is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all');
        $typeFilter = trim(is_string($_GET['type'] ?? null) ? $_GET['type'] : 'all');

        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(p.name LIKE :s OR p.breed LIKE :s OR p.description LIKE :s OR u.name LIKE :s)';
            $params['s'] = '%' . $search . '%';
        }
        if ($statusFilter !== 'all') {
            $where[] = 'p.status = :status';
            $params['status'] = $statusFilter;
        }
        if ($typeFilter !== 'all') {
            $where[] = 'p.type = :type';
            $params['type'] = $typeFilter;
        }

        $sql = '
            SELECT p.id, p.owner_id AS ownerId, p.name, p.breed, p.type, p.sex, p.age, p.price,
                   p.health_info AS healthInfo, p.vaccination_status AS vaccinationStatus,
                   p.description, p.pickup_location AS pickupLocation, p.status,
                   p.created_at AS createdAt, p.deleted_at AS deletedAt, p.sold_at AS soldAt,
                   u.name AS ownerName, u.email AS ownerEmail,
                   COALESCE(
                       (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id AND pi.is_primary = 1 LIMIT 1),
                       (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id ORDER BY pi.sort_order ASC LIMIT 1),
                       "https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80"
                   ) AS image
            FROM pets p
            INNER JOIN users u ON u.id = p.owner_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY p.created_at DESC
            LIMIT 100
        ';
        $stmt = $database->prepare($sql);
        $stmt->execute($params);
        $pets = $stmt->fetchAll();

        api_respond(['success' => true, 'pets' => $pets]);
    }

    if ($section === 'applications') {
        $statusFilter = trim(is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all');
        $where = ['1=1'];
        $params = [];
        if ($statusFilter !== 'all') {
            $where[] = 'a.status = :status';
            $params['status'] = $statusFilter;
        }

        $sql = '
            SELECT a.id, a.pet_id AS petId, a.buyer_id AS buyerId, a.seller_id AS sellerId,
                   a.conversation_id AS conversationId, a.status, a.notes,
                   a.created_at AS createdAt, a.meeting_confirmed_at AS meetingConfirmedAt,
                   p.name AS petName, p.breed, p.type,
                   ub.name AS buyerName, ub.email AS buyerEmail,
                   us.name AS sellerName, us.email AS sellerEmail
            FROM applications a
            INNER JOIN pets p ON p.id = a.pet_id
            INNER JOIN users ub ON ub.id = a.buyer_id
            INNER JOIN users us ON us.id = a.seller_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY a.created_at DESC
            LIMIT 100
        ';
        $stmt = $database->prepare($sql);
        $stmt->execute($params);
        $apps = $stmt->fetchAll();

        api_respond(['success' => true, 'applications' => $apps]);
    }

    if ($section === 'audit_logs') {
        $stmt = $database->query('
            SELECT id, admin_name AS adminName, action, target_type AS targetType, target_id AS targetId,
                   details_json AS details, created_at AS createdAt
            FROM admin_audit_logs
            ORDER BY created_at DESC
            LIMIT 100
        ');
        $logs = $stmt->fetchAll();
        foreach ($logs as &$log) {
            $log['details'] = json_decode((string)($log['details'] ?? '{}'), true);
        }
        unset($log);
        api_respond(['success' => true, 'logs' => $logs]);
    }

    api_respond(['success' => false, 'message' => 'Unknown admin section requested.'], 400);
}

// POST actions
$input = api_read_input();
$action = is_string($input['action'] ?? null) ? $input['action'] : '';

// 1. User status management (suspend, reactivate, soft delete)
if ($action === 'set_user_status') {
    $targetUserId = trim(is_string($input['userId'] ?? null) ? $input['userId'] : '');
    $newStatus = trim(is_string($input['status'] ?? null) ? $input['status'] : '');

    if ($targetUserId === '' || !in_array($newStatus, ['active', 'suspended', 'deleted'], true)) {
        api_respond(['success' => false, 'message' => 'Valid user ID and status (active, suspended, deleted) required.'], 400);
    }
    if ($targetUserId === $adminId) {
        api_respond(['success' => false, 'message' => 'You cannot suspend or delete your own admin account.'], 400);
    }

    $stmt = $database->prepare('UPDATE users SET status = :status, updated_at = NOW(6) WHERE id = :id');
    $stmt->execute(['status' => $newStatus, 'id' => $targetUserId]);

    record_admin_audit($database, $adminId, $adminName, 'set_user_status_' . $newStatus, 'user', $targetUserId);
    api_respond(['success' => true, 'message' => "User status updated to {$newStatus}."]);
}

// 2. Pet management (soft delete, restore, permanent delete, update status)
if ($action === 'set_pet_status') {
    $targetPetId = trim(is_string($input['petId'] ?? null) ? $input['petId'] : '');
    $newStatus = trim(is_string($input['status'] ?? null) ? $input['status'] : '');

    if ($targetPetId === '' || !in_array($newStatus, ['available', 'reserved', 'sold', 'deleted', 'permanent_delete'], true)) {
        api_respond(['success' => false, 'message' => 'Valid pet ID and status required.'], 400);
    }

    if ($newStatus === 'permanent_delete') {
        $database->beginTransaction();
        try {
            $database->prepare('DELETE FROM pet_images WHERE pet_id = :id')->execute(['id' => $targetPetId]);
            $database->prepare('DELETE FROM pets WHERE id = :id')->execute(['id' => $targetPetId]);
            $database->commit();
            record_admin_audit($database, $adminId, $adminName, 'permanent_delete_pet', 'pet', $targetPetId);
            api_respond(['success' => true, 'message' => 'Pet listing permanently deleted.']);
        } catch (Exception $e) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Could not delete pet.'], 500);
        }
    }

    $deletedAt = ($newStatus === 'deleted') ? 'NOW(6)' : 'NULL';
    $stmt = $database->prepare("UPDATE pets SET status = :status, deleted_at = $deletedAt, updated_at = NOW(6) WHERE id = :id");
    $stmt->execute(['status' => $newStatus, 'id' => $targetPetId]);

    record_admin_audit($database, $adminId, $adminName, 'set_pet_status_' . $newStatus, 'pet', $targetPetId);
    api_respond(['success' => true, 'message' => "Pet status updated to {$newStatus}."]);
}

// 3. Send Admin message / warning to user (Stage 4)
if ($action === 'send_message') {
    $recipientId = trim(is_string($input['recipientId'] ?? null) ? $input['recipientId'] : '');
    $subject = trim(is_string($input['subject'] ?? null) ? $input['subject'] : '');
    $text = trim(is_string($input['message'] ?? null) ? $input['message'] : '');
    $isWarning = !empty($input['isWarning']) ? 1 : 0;

    if ($recipientId === '' || $subject === '' || $text === '') {
        api_respond(['success' => false, 'message' => 'Recipient, subject, and message are required.'], 400);
    }

    $stmt = $database->prepare('
        INSERT INTO admin_messages (id, recipient_id, admin_id, admin_name, subject, message_text, is_warning, created_at)
        VALUES (:id, :recipient_id, :admin_id, :admin_name, :subject, :text, :is_warning, NOW(6))
    ');
    $stmt->execute([
        'id' => bin2hex(random_bytes(16)),
        'recipient_id' => $recipientId,
        'admin_id' => $adminId,
        'admin_name' => $adminName,
        'subject' => $subject,
        'text' => $text,
        'is_warning' => $isWarning,
    ]);

    record_admin_audit($database, $adminId, $adminName, 'send_admin_message', 'user', $recipientId, [
        'subject' => $subject,
        'isWarning' => (bool)$isWarning,
    ]);

    api_respond(['success' => true, 'message' => 'Official admin notification sent successfully.']);
}

api_respond(['success' => false, 'message' => 'Invalid admin action.'], 400);
