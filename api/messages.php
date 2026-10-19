<?php
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/message_database.php';

$sessionUser = api_require_user(['GET', 'POST']);
$userId = (string) $sessionUser['id'];
$userRole = auth_user_role($sessionUser);
$dataFile = __DIR__ . '/../data/messages.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $database = api_message_database();
        $messageQuery = $database->prepare(
            'SELECT c.id
             FROM conversations c
             WHERE c.buyer_id = :buyer_id OR c.seller_id = :seller_id
             ORDER BY COALESCE(
                 (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id = c.id),
                 c.created_at
             ) DESC'
        );
        $messageQuery->execute(['buyer_id' => $userId, 'seller_id' => $userId]);
        $conversations = [];
        foreach ($messageQuery->fetchAll(PDO::FETCH_COLUMN) as $conversationId) {
            $conversation = api_message_conversation($database, (string) $conversationId, $userId);
            if ($conversation !== null) {
                $conversations[] = $conversation;
            }
        }
    } catch (RuntimeException $error) {
        api_respond(['success' => false, 'message' => $error->getMessage()], 503);
    } catch (PDOException $error) {
        error_log('Paw Net could not load conversations: ' . $error->getMessage());
        api_respond(['success' => false, 'message' => 'Could not load conversations from the messaging database.'], 500);
    }

    $mine = array_values(array_filter(
        api_read_json_file($dataFile),
        static fn ($message) => ($message['userId'] ?? '') === $userId
    ));
    api_respond(['success' => true, 'messages' => $mine, 'conversations' => $conversations]);
}

$input = api_read_input();
$action = is_string($input['action'] ?? null) ? $input['action'] : 'support';
$accounts = load_auth_users();
$userName = (string) ($sessionUser['name'] ?? 'Paw Net member');
foreach ($accounts as $account) {
    if (($account['id'] ?? '') === $userId) {
        $userName = (string) ($account['name'] ?? $userName);
        break;
    }
}

if ($action === 'start') {
    if ($userRole !== 'buyer') {
        api_respond(['success' => false, 'message' => 'Only buyer accounts can start adoption inquiries.'], 403);
    }
    $petId = is_string($input['petId'] ?? null) ? $input['petId'] : '';
    $pets = api_read_json_file(__DIR__ . '/../data/pets.json');
    $pet = null;
    foreach ($pets as $candidate) {
        if (($candidate['id'] ?? '') === $petId && !empty($candidate['ownerId']) && ($candidate['status'] ?? 'available') !== 'sold') {
            $pet = $candidate;
            break;
        }
    }
    if ($pet === null) {
        api_respond(['success' => false, 'message' => 'This pet is no longer available.'], 404);
    }
    if ((string) ($pet['ownerId'] ?? '') === $userId) {
        api_respond(['success' => false, 'message' => 'You cannot message yourself about your own listing.'], 403);
    }

    $sellerName = 'Pet seller';
    foreach ($accounts as $account) {
        if (($account['id'] ?? '') === ($pet['ownerId'] ?? '')) {
            $sellerName = (string) ($account['name'] ?? $sellerName);
            break;
        }
    }
    $pickupLocation = trim((string) ($pet['pickupLocation'] ?? 'Pickup details to be confirmed with the seller'));
    $buyerNote = is_string($input['message'] ?? null) ? trim($input['message']) : '';
    if ($buyerNote === '') {
        $buyerNote = "Hi {$sellerName}, I'm interested in adopting {$pet['name']}. Could you share more about their health, personality, and routine, and let me know when we could arrange a meet-and-greet?";
    }
    $sex = ucfirst(strtolower((string) ($pet['sex'] ?? 'not specified')));
    $openingText = $buyerNote . "\n\nPet listing details: {$pet['name']} · {$pet['breed']} · {$pet['age']} years old · {$sex} · {$pet['type']}. {$pet['description']}\nPickup address/area: {$pickupLocation}";
    if (strlen($openingText) > 2000) {
        api_respond(['success' => false, 'message' => 'Keep your adoption inquiry under 2,000 characters.'], 400);
    }

    $conversationId = bin2hex(random_bytes(24));
    $messageId = bin2hex(random_bytes(24));
    $listing = [
        'name' => (string) ($pet['name'] ?? ''),
        'breed' => (string) ($pet['breed'] ?? ''),
        'type' => (string) ($pet['type'] ?? ''),
        'sex' => $sex,
        'age' => $pet['age'] ?? '',
        'description' => (string) ($pet['description'] ?? ''),
        'image' => (string) ($pet['image'] ?? ''),
        'pickupLocation' => $pickupLocation,
    ];

    try {
        $database = api_message_database();
        $database->beginTransaction();
        $existingQuery = $database->prepare(
            'SELECT id FROM conversations WHERE pet_id = :pet_id AND buyer_id = :buyer_id'
        );
        $existingQuery->execute(['pet_id' => $petId, 'buyer_id' => $userId]);
        $existingId = $existingQuery->fetchColumn();

        if ($existingId !== false) {
            $database->commit();
            $conversation = api_message_conversation($database, (string) $existingId, $userId);
            api_respond(['success' => true, 'conversation' => $conversation, 'message' => 'Your conversation is ready.']);
        }

        $insertConversation = $database->prepare(
            'INSERT INTO conversations
                (id, pet_id, pet_name, buyer_id, buyer_name, seller_id, seller_name, listing_json)
             VALUES
                (:id, :pet_id, :pet_name, :buyer_id, :buyer_name, :seller_id, :seller_name, :listing_json)'
        );
        $insertConversation->execute([
            'id' => $conversationId,
            'pet_id' => $petId,
            'pet_name' => (string) ($pet['name'] ?? 'Pet'),
            'buyer_id' => $userId,
            'buyer_name' => $userName,
            'seller_id' => (string) $pet['ownerId'],
            'seller_name' => $sellerName,
            'listing_json' => json_encode($listing, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);
        $insertMessage = $database->prepare(
            'INSERT INTO messages (id, conversation_id, sender_id, sender_name, message_text)
             VALUES (:id, :conversation_id, :sender_id, :sender_name, :message_text)'
        );
        $insertMessage->execute([
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_id' => $userId,
            'sender_name' => $userName,
            'message_text' => $openingText,
        ]);
        $database->commit();
        $conversation = api_message_conversation($database, $conversationId, $userId);
        api_respond(['success' => true, 'conversation' => $conversation, 'message' => 'Your message was sent to the seller.']);
    } catch (RuntimeException $error) {
        if (isset($database) && $database->inTransaction()) {
            $database->rollBack();
        }
        api_respond(['success' => false, 'message' => $error->getMessage()], 503);
    } catch (PDOException $error) {
        if (isset($database) && $database->inTransaction()) {
            $database->rollBack();
        }
        if ($error->getCode() === '23000') {
            try {
                $database = api_message_database();
                $existingQuery = $database->prepare(
                    'SELECT id FROM conversations WHERE pet_id = :pet_id AND buyer_id = :buyer_id'
                );
                $existingQuery->execute(['pet_id' => $petId, 'buyer_id' => $userId]);
                $existingId = $existingQuery->fetchColumn();
                if ($existingId !== false) {
                    $conversation = api_message_conversation($database, (string) $existingId, $userId);
                    api_respond(['success' => true, 'conversation' => $conversation, 'message' => 'Your conversation is ready.']);
                }
            } catch (RuntimeException $databaseError) {
                api_respond(['success' => false, 'message' => $databaseError->getMessage()], 503);
            }
        }
        error_log('Paw Net could not start conversation: ' . $error->getMessage());
        api_respond(['success' => false, 'message' => 'Could not start the conversation. Check that the MySQL schema is installed.'], 500);
    }
}

if ($action === 'reply' || $action === 'read') {
    $conversationId = is_string($input['conversationId'] ?? null) ? $input['conversationId'] : '';
    $text = is_string($input['message'] ?? null) ? trim($input['message']) : '';
    if ($action === 'reply' && ($text === '' || strlen($text) > 2000)) {
        api_respond(['success' => false, 'message' => 'Write a message of up to 2,000 characters.'], 400);
    }

    try {
        $database = api_message_database();
        $database->beginTransaction();
        $conversationQuery = $database->prepare(
            'SELECT id FROM conversations
             WHERE id = :conversation_id
               AND (buyer_id = :buyer_id OR seller_id = :seller_id)
             FOR UPDATE'
        );
        $conversationQuery->execute([
            'conversation_id' => $conversationId,
            'buyer_id' => $userId,
            'seller_id' => $userId,
        ]);
        if ($conversationQuery->fetchColumn() === false) {
            $database->rollBack();
            api_respond(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        if ($action === 'reply') {
            $insertMessage = $database->prepare(
                'INSERT INTO messages (id, conversation_id, sender_id, sender_name, message_text)
                 VALUES (:id, :conversation_id, :sender_id, :sender_name, :message_text)'
            );
            $insertMessage->execute([
                'id' => bin2hex(random_bytes(24)),
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'sender_name' => $userName,
                'message_text' => $text,
            ]);
        } else {
            $markRead = $database->prepare(
                'UPDATE messages
                 SET read_at = UTC_TIMESTAMP(6)
                 WHERE conversation_id = :conversation_id
                   AND sender_id <> :sender_id
                   AND read_at IS NULL'
            );
            $markRead->execute(['conversation_id' => $conversationId, 'sender_id' => $userId]);
        }

        $database->commit();
        $conversation = api_message_conversation($database, $conversationId, $userId);
        api_respond(['success' => true, 'conversation' => $conversation]);
    } catch (RuntimeException $error) {
        if (isset($database) && $database->inTransaction()) {
            $database->rollBack();
        }
        api_respond(['success' => false, 'message' => $error->getMessage()], 503);
    } catch (PDOException $error) {
        if (isset($database) && $database->inTransaction()) {
            $database->rollBack();
        }
        error_log('Paw Net could not update conversation: ' . $error->getMessage());
        api_respond(['success' => false, 'message' => 'Could not update the conversation in the messaging database.'], 500);
    }
}

$text = is_string($input['message'] ?? null) ? trim($input['message']) : '';
if ($text === '' || strlen($text) > 2000) {
    api_respond(['success' => false, 'message' => 'Write a message of up to 2,000 characters.'], 400);
}
$messages = api_read_json_file($dataFile);
$message = [
    'id' => bin2hex(random_bytes(12)),
    'userId' => $userId,
    'sender' => 'adopter',
    'text' => $text,
    'sentAt' => date(DATE_ATOM),
];
$messages[] = $message;
if (!api_write_json_file($dataFile, $messages)) {
    api_respond(['success' => false, 'message' => 'Message could not be saved. Check data folder write permissions.'], 500);
}
api_respond(['success' => true, 'message' => $message]);
