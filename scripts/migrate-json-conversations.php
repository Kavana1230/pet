<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../api/message_database.php';

$source = __DIR__ . '/../data/conversations.json';
$contents = is_file($source) ? file_get_contents($source) : '[]';
$conversations = json_decode($contents === false ? '' : $contents, true);
if (!is_array($conversations)) {
    fwrite(STDERR, "The existing conversations.json file is not valid JSON.\n");
    exit(1);
}

try {
    $database = api_message_database();
    $database->beginTransaction();
    $findConversation = $database->prepare(
        'SELECT id FROM conversations WHERE pet_id = :pet_id AND buyer_id = :buyer_id'
    );
    $insertConversation = $database->prepare(
        'INSERT INTO conversations
            (id, pet_id, pet_name, buyer_id, buyer_name, seller_id, seller_name, listing_json, created_at)
         VALUES
            (:id, :pet_id, :pet_name, :buyer_id, :buyer_name, :seller_id, :seller_name, :listing_json, :created_at)'
    );
    $findMessage = $database->prepare('SELECT id FROM messages WHERE id = :id');
    $insertMessage = $database->prepare(
        'INSERT INTO messages
            (id, conversation_id, sender_id, sender_name, message_text, created_at)
         VALUES
            (:id, :conversation_id, :sender_id, :sender_name, :message_text, :created_at)'
    );

    $importedMessages = 0;
    foreach ($conversations as $conversation) {
        if (!is_array($conversation)
            || empty($conversation['id'])
            || empty($conversation['petId'])
            || empty($conversation['buyerId'])
            || empty($conversation['sellerId'])) {
            continue;
        }

        $petId = (string) $conversation['petId'];
        $buyerId = (string) $conversation['buyerId'];
        $findConversation->execute(['pet_id' => $petId, 'buyer_id' => $buyerId]);
        $conversationId = $findConversation->fetchColumn();
        if ($conversationId === false) {
            $conversationId = (string) $conversation['id'];
            $createdAt = strtotime((string) ($conversation['createdAt'] ?? 'now')) ?: time();
            $listing = is_array($conversation['listing'] ?? null) ? $conversation['listing'] : [];
            $insertConversation->execute([
                'id' => $conversationId,
                'pet_id' => $petId,
                'pet_name' => (string) ($conversation['petName'] ?? 'Pet'),
                'buyer_id' => $buyerId,
                'buyer_name' => (string) ($conversation['buyerName'] ?? 'Paw Net member'),
                'seller_id' => (string) $conversation['sellerId'],
                'seller_name' => (string) ($conversation['sellerName'] ?? 'Pet seller'),
                'listing_json' => json_encode($listing, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => gmdate('Y-m-d H:i:s', $createdAt),
            ]);
        } else {
            $conversationId = (string) $conversationId;
        }

        foreach (($conversation['messages'] ?? []) as $message) {
            if (!is_array($message) || empty($message['senderId']) || !isset($message['text'])) {
                continue;
            }
            $messageId = (string) ($message['id'] ?? bin2hex(random_bytes(24)));
            $findMessage->execute(['id' => $messageId]);
            if ($findMessage->fetchColumn() !== false) {
                continue;
            }
            $sentAt = strtotime((string) ($message['sentAt'] ?? 'now')) ?: time();
            $insertMessage->execute([
                'id' => $messageId,
                'conversation_id' => $conversationId,
                'sender_id' => (string) $message['senderId'],
                'sender_name' => (string) ($message['senderName'] ?? 'Paw Net member'),
                'message_text' => (string) $message['text'],
                'created_at' => gmdate('Y-m-d H:i:s', $sentAt),
            ]);
            $importedMessages++;
        }
    }

    $database->commit();
    fwrite(STDOUT, sprintf("Imported %d conversation(s) and %d message(s).\n", count($conversations), $importedMessages));
} catch (PDOException | RuntimeException $error) {
    if (isset($database) && $database->inTransaction()) {
        $database->rollBack();
    }
    error_log('Paw Net conversation migration failed: ' . $error->getMessage());
    fwrite(STDERR, "Could not migrate conversations. Verify MySQL is running and database/schema.sql was imported.\n");
    exit(1);
}
