<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function api_message_database(): PDO
{
    return pawnet_db();
}

function api_message_conversation(PDO $database, string $conversationId, string $userId): ?array
{
    $conversationQuery = $database->prepare(
        'SELECT c.id, c.pet_id, c.pet_name, c.buyer_id, c.buyer_name, c.seller_id, c.seller_name,
                c.listing_json, c.created_at,
                (SELECT COUNT(*) FROM messages unread
                 WHERE unread.conversation_id = c.id
                   AND unread.sender_id <> :unread_sender_id
                   AND unread.read_at IS NULL) AS unread_count
         FROM conversations c
         WHERE c.id = :conversation_id
           AND (c.buyer_id = :buyer_id OR c.seller_id = :seller_id)'
    );
    $conversationQuery->execute([
        'unread_sender_id' => $userId,
        'conversation_id' => $conversationId,
        'buyer_id' => $userId,
        'seller_id' => $userId,
    ]);
    $row = $conversationQuery->fetch();
    if ($row === false) {
        return null;
    }

    $messageQuery = $database->prepare(
        'SELECT id, sender_id, sender_name, message_text, created_at
         FROM messages
         WHERE conversation_id = :conversation_id
         ORDER BY created_at, id'
    );
    $messageQuery->execute(['conversation_id' => $conversationId]);
    $messages = [];
    foreach ($messageQuery->fetchAll() as $message) {
        $messages[] = [
            'id' => $message['id'],
            'senderId' => $message['sender_id'],
            'senderName' => $message['sender_name'],
            'text' => $message['message_text'],
            'sentAt' => gmdate('Y-m-d\TH:i:s\Z', strtotime($message['created_at'] . ' UTC')),
        ];
    }

    $historicalListing = json_decode((string) $row['listing_json'], true);
    if (!is_array($historicalListing)) {
        $historicalListing = [];
    }

    // Fetch the LIVE pet record from the database to ensure latest details (fixes Bug A)
    $petQuery = $database->prepare('
        SELECT p.id, p.name, p.breed, p.type, p.sex, p.age, p.price, p.description,
               p.pickup_location AS pickupLocation, p.status, p.health_info AS healthInfo,
               p.vaccination_status AS vaccinationStatus,
               COALESCE(
                   (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id AND pi.is_primary = 1 LIMIT 1),
                   (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id ORDER BY pi.sort_order ASC LIMIT 1),
                   "https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80"
               ) AS image
        FROM pets p
        WHERE p.id = :pet_id
    ');
    $petQuery->execute(['pet_id' => $row['pet_id']]);
    $livePet = $petQuery->fetch();

    $currentListing = $historicalListing;
    if ($livePet) {
        $currentListing = [
            'name' => (string) $livePet['name'],
            'breed' => (string) $livePet['breed'],
            'type' => (string) $livePet['type'],
            'sex' => ucfirst(strtolower((string) $livePet['sex'])),
            'age' => (int) $livePet['age'],
            'price' => (float) $livePet['price'],
            'description' => (string) $livePet['description'],
            'image' => (string) $livePet['image'],
            'pickupLocation' => (string) $livePet['pickupLocation'],
            'status' => (string) $livePet['status'],
            'healthInfo' => (string) ($livePet['healthInfo'] ?? ''),
            'vaccinationStatus' => (string) ($livePet['vaccinationStatus'] ?? ''),
        ];
    }

    // Fetch corresponding application record
    $appQuery = $database->prepare('
        SELECT id, status, notes, meeting_confirmed_at, created_at
        FROM applications
        WHERE pet_id = :pet_id AND buyer_id = :buyer_id
        LIMIT 1
    ');
    $appQuery->execute([
        'pet_id' => $row['pet_id'],
        'buyer_id' => $row['buyer_id'],
    ]);
    $appRow = $appQuery->fetch();

    $appStatus = $appRow ? (string) $appRow['status'] : 'pending';
    $appId = $appRow ? (string) $appRow['id'] : null;

    // A buyer cannot send further messages if application is rejected (Bug C requirement)
    $isBuyer = ($userId === $row['buyer_id']);
    $canReply = !($isBuyer && $appStatus === 'rejected');

    return [
        'id' => $row['id'],
        'petId' => $row['pet_id'],
        'petName' => $livePet ? (string) $livePet['name'] : (string) $row['pet_name'],
        'buyerId' => $row['buyer_id'],
        'buyerName' => $row['buyer_name'],
        'sellerId' => $row['seller_id'],
        'sellerName' => $row['seller_name'],
        'participants' => [$row['buyer_id'], $row['seller_id']],
        'listing' => $currentListing,
        'originalListing' => $historicalListing,
        'createdAt' => gmdate('Y-m-d\TH:i:s\Z', strtotime($row['created_at'] . ' UTC')),
        'status' => $appStatus,
        'unreadCount' => (int) $row['unread_count'],
        'messages' => $messages,
        'application' => $appRow ? [
            'id' => $appRow['id'],
            'status' => $appRow['status'],
            'meetingConfirmedAt' => $appRow['meeting_confirmed_at'],
            'createdAt' => $appRow['created_at'],
        ] : null,
        'canReply' => $canReply,
    ];
}
