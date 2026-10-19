<?php

function api_message_database(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = require __DIR__ . '/../config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );

    try {
        $connection = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $error) {
        error_log('Paw Net messaging database connection failed: ' . $error->getMessage());
        throw new RuntimeException(
            'Messaging is temporarily unavailable. Start MySQL in XAMPP and import database/schema.sql.',
            0,
            $error
        );
    }

    return $connection;
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

    $listing = json_decode((string) $row['listing_json'], true);
    return [
        'id' => $row['id'],
        'petId' => $row['pet_id'],
        'petName' => $row['pet_name'],
        'buyerId' => $row['buyer_id'],
        'buyerName' => $row['buyer_name'],
        'sellerId' => $row['seller_id'],
        'sellerName' => $row['seller_name'],
        'participants' => [$row['buyer_id'], $row['seller_id']],
        'listing' => is_array($listing) ? $listing : [],
        'createdAt' => gmdate('Y-m-d\TH:i:s\Z', strtotime($row['created_at'] . ' UTC')),
        'status' => 'discussion',
        'unreadCount' => (int) $row['unread_count'],
        'messages' => $messages,
    ];
}
