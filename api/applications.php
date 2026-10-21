<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

$sessionUser = api_require_user(['GET', 'POST']);
$userId = (string) $sessionUser['id'];
$userRole = auth_user_role($sessionUser);

$database = pawnet_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if ($userRole === 'seller') {
            $stmt = $database->prepare('
                SELECT a.id, a.pet_id AS petId, a.buyer_id AS buyerId, a.seller_id AS sellerId,
                       a.conversation_id AS conversationId, a.status, a.notes,
                       a.created_at AS createdAt, a.updated_at AS updatedAt,
                       a.meeting_confirmed_at AS meetingConfirmedAt,
                       p.name AS petName, p.breed, p.type, p.sex, p.age, p.status AS petStatus,
                       u.name AS buyerName, u.email AS buyerEmail, u.phone AS buyerPhone,
                       COALESCE(
                           (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id AND pi.is_primary = 1 LIMIT 1),
                           (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id ORDER BY pi.sort_order ASC LIMIT 1),
                           "https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80"
                       ) AS image
                FROM applications a
                INNER JOIN pets p ON p.id = a.pet_id
                INNER JOIN users u ON u.id = a.buyer_id
                WHERE a.seller_id = :seller_id
                ORDER BY a.created_at DESC
            ');
            $stmt->execute(['seller_id' => $userId]);
        } elseif ($userRole === 'admin') {
            $stmt = $database->prepare('
                SELECT a.id, a.pet_id AS petId, a.buyer_id AS buyerId, a.seller_id AS sellerId,
                       a.conversation_id AS conversationId, a.status, a.notes,
                       a.created_at AS createdAt, a.updated_at AS updatedAt,
                       a.meeting_confirmed_at AS meetingConfirmedAt,
                       p.name AS petName, p.breed, p.type, p.sex, p.age, p.status AS petStatus,
                       ub.name AS buyerName, ub.email AS buyerEmail,
                       us.name AS sellerName, us.email AS sellerEmail,
                       COALESCE(
                           (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id AND pi.is_primary = 1 LIMIT 1),
                           (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id ORDER BY pi.sort_order ASC LIMIT 1),
                           "https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80"
                       ) AS image
                FROM applications a
                INNER JOIN pets p ON p.id = a.pet_id
                INNER JOIN users ub ON ub.id = a.buyer_id
                INNER JOIN users us ON us.id = a.seller_id
                ORDER BY a.created_at DESC
            ');
            $stmt->execute();
        } else {
            // Buyer view
            $stmt = $database->prepare('
                SELECT a.id, a.pet_id AS petId, a.buyer_id AS buyerId, a.seller_id AS sellerId,
                       a.conversation_id AS conversationId, a.status, a.notes,
                       a.created_at AS createdAt, a.updated_at AS updatedAt,
                       a.meeting_confirmed_at AS meetingConfirmedAt,
                       p.name AS petName, p.breed, p.type, p.sex, p.age, p.status AS petStatus,
                       u.name AS sellerName,
                       COALESCE(
                           (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id AND pi.is_primary = 1 LIMIT 1),
                           (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id ORDER BY pi.sort_order ASC LIMIT 1),
                           "https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80"
                       ) AS image
                FROM applications a
                INNER JOIN pets p ON p.id = a.pet_id
                INNER JOIN users u ON u.id = a.seller_id
                WHERE a.buyer_id = :buyer_id
                ORDER BY a.created_at DESC
            ');
            $stmt->execute(['buyer_id' => $userId]);
        }

        $applications = $stmt->fetchAll();
        foreach ($applications as &$app) {
            $app['date'] = date('Y-m-d', strtotime($app['createdAt']));
            $app['nextStep'] = match ($app['status']) {
                'pending' => 'Pending seller review',
                'accepted' => 'Seller accepted — ready to confirm meeting',
                'meeting_confirmed' => 'Meeting scheduled · Pet is reserved',
                'completed' => 'Adoption completed',
                'rejected' => 'Application closed',
                'cancelled' => 'Application cancelled',
                default => 'Under review',
            };
        }
        unset($app);

        api_respond(['success' => true, 'applications' => $applications]);
    } catch (Exception $e) {
        error_log('Paw Net applications fetch error: ' . $e->getMessage());
        api_respond(['success' => false, 'message' => 'Unable to load applications at this time.'], 500);
    }
}

// POST actions: accept, reject, confirm_meeting, reopen, complete, cancel
$input = api_read_input();
$action = is_string($input['action'] ?? null) ? $input['action'] : '';
$applicationId = is_string($input['applicationId'] ?? null) ? trim($input['applicationId']) : '';
$conversationId = is_string($input['conversationId'] ?? null) ? trim($input['conversationId']) : '';

if ($applicationId === '' && $conversationId === '') {
    api_respond(['success' => false, 'message' => 'Application or Conversation identifier is required.'], 400);
}

try {
    $database->beginTransaction();

    // Find target application
    if ($applicationId !== '') {
        $findStmt = $database->prepare('SELECT * FROM applications WHERE id = :id FOR UPDATE');
        $findStmt->execute(['id' => $applicationId]);
    } else {
        $findStmt = $database->prepare('SELECT * FROM applications WHERE conversation_id = :cid FOR UPDATE');
        $findStmt->execute(['cid' => $conversationId]);
    }
    $app = $findStmt->fetch();

    if (!$app) {
        $database->rollBack();
        api_respond(['success' => false, 'message' => 'Application not found.'], 404);
    }

    $isSeller = ($userId === $app['seller_id']);
    $isBuyer = ($userId === $app['buyer_id']);
    $isAdmin = ($userRole === 'admin');

    if ($action === 'accept') {
        if (!$isSeller && !$isAdmin) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Only the listing seller can accept an application.'], 403);
        }
        if ($app['status'] !== 'pending') {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'This application is not pending.'], 400);
        }

        $upd = $database->prepare('UPDATE applications SET status = "accepted", updated_at = NOW(6) WHERE id = :id');
        $upd->execute(['id' => $app['id']]);

        // Add automated system/chat message if conversation exists
        if (!empty($app['conversation_id'])) {
            $msgStmt = $database->prepare('
                INSERT INTO messages (id, conversation_id, sender_id, sender_name, message_text, created_at)
                VALUES (:id, :cid, :sid, :sname, :text, NOW(6))
            ');
            $msgStmt->execute([
                'id' => bin2hex(random_bytes(24)),
                'cid' => $app['conversation_id'],
                'sid' => $userId,
                'sname' => (string) ($sessionUser['name'] ?? 'Seller'),
                'text' => '✅ Application accepted! Please click "Confirm Meeting" to reserve the pet and schedule your visit.',
            ]);
        }

        $database->commit();
        api_respond(['success' => true, 'message' => 'Application accepted! You can now confirm the meeting.']);
    }

    if ($action === 'reject') {
        if (!$isSeller && !$isAdmin) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Only the listing seller can reject an application.'], 403);
        }

        $upd = $database->prepare('UPDATE applications SET status = "rejected", updated_at = NOW(6) WHERE id = :id');
        $upd->execute(['id' => $app['id']]);

        if (!empty($app['conversation_id'])) {
            $msgStmt = $database->prepare('
                INSERT INTO messages (id, conversation_id, sender_id, sender_name, message_text, created_at)
                VALUES (:id, :cid, :sid, :sname, :text, NOW(6))
            ');
            $msgStmt->execute([
                'id' => bin2hex(random_bytes(24)),
                'cid' => $app['conversation_id'],
                'sid' => $userId,
                'sname' => (string) ($sessionUser['name'] ?? 'Seller'),
                'text' => '❌ The seller has declined this application. This conversation is now closed.',
            ]);
        }

        $database->commit();
        api_respond(['success' => true, 'message' => 'Application declined. Conversation is now read-only.']);
    }

    if ($action === 'confirm_meeting') {
        // Both buyer and seller (or admin) can trigger meeting confirmation once accepted
        if (!$isSeller && !$isBuyer && !$isAdmin) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }
        if (!in_array($app['status'], ['accepted', 'pending'], true)) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Meeting can only be confirmed after acceptance.'], 400);
        }

        // Update application
        $updApp = $database->prepare('
            UPDATE applications
            SET status = "meeting_confirmed", meeting_confirmed_at = NOW(6), updated_at = NOW(6)
            WHERE id = :id
        ');
        $updApp->execute(['id' => $app['id']]);

        // Transition pet to 'reserved' and remove from public active search
        $updPet = $database->prepare('
            UPDATE pets
            SET status = "reserved", updated_at = NOW(6)
            WHERE id = :pet_id
        ');
        $updPet->execute(['pet_id' => $app['pet_id']]);

        // Post confirmation message
        if (!empty($app['conversation_id'])) {
            $msgStmt = $database->prepare('
                INSERT INTO messages (id, conversation_id, sender_id, sender_name, message_text, created_at)
                VALUES (:id, :cid, :sid, :sname, :text, NOW(6))
            ');
            $msgStmt->execute([
                'id' => bin2hex(random_bytes(24)),
                'cid' => $app['conversation_id'],
                'sid' => $userId,
                'sname' => (string) ($sessionUser['name'] ?? 'Member'),
                'text' => '📅 Meeting confirmed! The pet listing has been reserved and temporarily hidden from public adoption search while you meet.',
            ]);
        }

        $database->commit();
        api_respond(['success' => true, 'message' => 'Meeting confirmed! Listing moved to Reserved area.']);
    }

    if ($action === 'reopen') {
        // Seller or admin can reopen listing if meeting does not work out
        if (!$isSeller && !$isAdmin) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Only the seller or admin can reopen this listing.'], 403);
        }

        // Reopen pet back to available
        $updPet = $database->prepare('
            UPDATE pets
            SET status = "available", updated_at = NOW(6)
            WHERE id = :pet_id
        ');
        $updPet->execute(['pet_id' => $app['pet_id']]);

        // Mark application cancelled / not completed
        $updApp = $database->prepare('
            UPDATE applications
            SET status = "cancelled", updated_at = NOW(6), notes = CONCAT(COALESCE(notes, ""), " [Meeting did not work out; listing reopened]")
            WHERE id = :id
        ');
        $updApp->execute(['id' => $app['id']]);

        if (!empty($app['conversation_id'])) {
            $msgStmt = $database->prepare('
                INSERT INTO messages (id, conversation_id, sender_id, sender_name, message_text, created_at)
                VALUES (:id, :cid, :sid, :sname, :text, NOW(6))
            ');
            $msgStmt->execute([
                'id' => bin2hex(random_bytes(24)),
                'cid' => $app['conversation_id'],
                'sid' => $userId,
                'sname' => (string) ($sessionUser['name'] ?? 'Seller'),
                'text' => 'ℹ️ The seller has reopened the listing for public adoption.',
            ]);
        }

        $database->commit();
        api_respond(['success' => true, 'message' => 'Listing reopened and restored to public catalog.']);
    }

    if ($action === 'complete') {
        if (!$isSeller && !$isAdmin) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Only the seller or admin can mark adoption as complete.'], 403);
        }

        $updApp = $database->prepare('UPDATE applications SET status = "completed", updated_at = NOW(6) WHERE id = :id');
        $updApp->execute(['id' => $app['id']]);

        $updPet = $database->prepare('UPDATE pets SET status = "sold", sold_at = NOW(6), updated_at = NOW(6) WHERE id = :pet_id');
        $updPet->execute(['pet_id' => $app['pet_id']]);

        if (!empty($app['conversation_id'])) {
            $msgStmt = $database->prepare('
                INSERT INTO messages (id, conversation_id, sender_id, sender_name, message_text, created_at)
                VALUES (:id, :cid, :sid, :sname, :text, NOW(6))
            ');
            $msgStmt->execute([
                'id' => bin2hex(random_bytes(24)),
                'cid' => $app['conversation_id'],
                'sid' => $userId,
                'sname' => (string) ($sessionUser['name'] ?? 'Seller'),
                'text' => '🎉 Adoption completed! Congratulations on finding your new best friend!',
            ]);
        }

        $database->commit();
        api_respond(['success' => true, 'message' => 'Adoption completed! Pet marked as found a home.']);
    }

    if ($action === 'cancel') {
        if (!$isBuyer && !$isAdmin) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Only the buyer can cancel their application.'], 403);
        }

        $updApp = $database->prepare('UPDATE applications SET status = "cancelled", updated_at = NOW(6) WHERE id = :id');
        $updApp->execute(['id' => $app['id']]);

        // If pet was reserved under this application, restore to available
        if ($app['status'] === 'meeting_confirmed') {
            $database->prepare('UPDATE pets SET status = "available", updated_at = NOW(6) WHERE id = :pet_id')->execute(['pet_id' => $app['pet_id']]);
        }

        $database->commit();
        api_respond(['success' => true, 'message' => 'Application has been cancelled.']);
    }

    $database->rollBack();
    api_respond(['success' => false, 'message' => 'Invalid action requested.'], 400);

} catch (Exception $e) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Paw Net application action error: ' . $e->getMessage());
    api_respond(['success' => false, 'message' => 'An error occurred while processing the application.'], 500);
}
