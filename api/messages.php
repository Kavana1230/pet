<?php
require_once __DIR__ . '/common.php';

$sessionUser = api_require_user(['GET', 'POST']);
$userId      = (string) $sessionUser['id'];

$conversationFile = __DIR__ . '/../data/conversations.json';

/* ------------------------------------------------------------------ *
 * GET — return conversations the current user participates in
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conversations = array_values(array_filter(
        api_read_json_file($conversationFile),
        static fn ($c) => in_array($userId, $c['participants'] ?? [], true)
    ));
    api_respond([
        'success'       => true,
        'conversations' => $conversations,
    ]);
}

/* ------------------------------------------------------------------ *
 * POST — only "start" and "reply" are accepted
 * ------------------------------------------------------------------ */
$input  = api_read_input();
$action = is_string($input['action'] ?? null) ? $input['action'] : '';

if (!in_array($action, ['start', 'reply'], true)) {
    api_respond(['success' => false, 'message' => 'Unsupported action.'], 400);
}

$conversations = api_read_json_file($conversationFile);
$accounts      = load_auth_users();
$userName      = (string) ($sessionUser['name'] ?? 'Paw Net member');

foreach ($accounts as $account) {
    if (($account['id'] ?? '') === $userId) {
        $userName = (string) ($account['name'] ?? $userName);
        break;
    }
}

/* ------------------------------------------------------------------ *
 * START — buyer opens a conversation about a seller's listing
 * ------------------------------------------------------------------ */
if ($action === 'start') {
    $petId = is_string($input['petId'] ?? null) ? $input['petId'] : '';

    if (!preg_match('/^[a-f0-9]{24}$/', $petId)) {
        api_respond(['success' => false, 'message' => 'Invalid pet reference.'], 400);
    }

    $pets = api_read_json_file(__DIR__ . '/../data/pets.json');
    $pet  = null;

    foreach ($pets as $candidate) {
        if (
            ($candidate['id'] ?? '') === $petId
            && !empty($candidate['ownerId'])
            && ($candidate['status'] ?? 'available') !== 'sold'
        ) {
            $pet = $candidate;
            break;
        }
    }

    if ($pet === null) {
        api_respond(['success' => false, 'message' => 'This pet is no longer available.'], 404);
    }

    if (($pet['ownerId'] ?? '') === $userId) {
        api_respond(['success' => false, 'message' => 'You cannot message yourself about your own listing.'], 403);
    }

    foreach ($conversations as $conversation) {
        if (
            ($conversation['petId'] ?? '') === $petId
            && ($conversation['buyerId'] ?? '') === $userId
        ) {
            api_respond([
                'success'      => true,
                'conversation' => $conversation,
                'message'      => 'Your conversation is ready.',
            ]);
        }
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
        $buyerNote = "Hi {$sellerName}, I'm interested in adopting {$pet['name']}. "
                   . "Could you share more about their health, personality, and routine, "
                   . "and let me know when we could arrange a meet-and-greet?";
    }

    $sex         = ucfirst(strtolower((string) ($pet['sex'] ?? 'not specified')));
    $openingText = $buyerNote
                 . "\n\nPet listing details: {$pet['name']} · {$pet['breed']} · "
                 . "{$pet['age']} years old · {$sex} · {$pet['type']}. {$pet['description']}"
                 . "\nPickup address/area: {$pickupLocation}";

    if (mb_strlen($openingText, 'UTF-8') > 2000) {
        api_respond(['success' => false, 'message' => 'Keep your adoption inquiry under 2,000 characters.'], 400);
    }

    $opening = [
        'id'         => bin2hex(random_bytes(12)),
        'senderId'   => $userId,
        'senderName' => $userName,
        'text'       => $openingText,
        'sentAt'     => date(DATE_ATOM),
    ];

    $conversation = [
        'id'           => bin2hex(random_bytes(12)),
        'petId'        => $petId,
        'petName'      => (string) ($pet['name'] ?? 'Pet'),
        'buyerId'      => $userId,
        'buyerName'    => $userName,
        'sellerId'     => (string) $pet['ownerId'],
        'sellerName'   => $sellerName,
        'participants' => [$userId, (string) $pet['ownerId']],
        'createdAt'    => date(DATE_ATOM),
        'listing'      => [
            'name'           => (string) ($pet['name'] ?? ''),
            'breed'          => (string) ($pet['breed'] ?? ''),
            'type'           => (string) ($pet['type'] ?? ''),
            'sex'            => $sex,
            'age'            => $pet['age'] ?? '',
            'description'    => (string) ($pet['description'] ?? ''),
            'image'          => (string) ($pet['image'] ?? ''),
            'pickupLocation' => $pickupLocation,
        ],
        'status'   => 'discussion',
        'messages' => [$opening],
    ];

    $conversations[] = $conversation;

    if (!api_write_json_file($conversationFile, $conversations)) {
        api_respond(['success' => false, 'message' => 'Could not start your conversation.'], 500);
    }

    api_respond([
        'success'      => true,
        'conversation' => $conversation,
        'message'      => 'Your message was sent to the seller.',
    ]);
}

/* ------------------------------------------------------------------ *
 * REPLY — only participants of a conversation may reply
 * ------------------------------------------------------------------ */
if ($action === 'reply') {
    $conversationId = is_string($input['conversationId'] ?? null) ? $input['conversationId'] : '';
    $text           = is_string($input['message'] ?? null) ? trim($input['message']) : '';

    if (!preg_match('/^[a-f0-9]{24}$/', $conversationId)) {
        api_respond(['success' => false, 'message' => 'Invalid conversation reference.'], 400);
    }
    if ($text === '') {
        api_respond(['success' => false, 'message' => 'Write a message before sending.'], 400);
    }
    if (mb_strlen($text, 'UTF-8') > 2000) {
        api_respond(['success' => false, 'message' => 'Write a message of up to 2,000 characters.'], 400);
    }

    foreach ($conversations as &$conversation) {
        if (
            ($conversation['id'] ?? '') === $conversationId
            && in_array($userId, $conversation['participants'] ?? [], true)
        ) {
            $newMessage = [
                'id'         => bin2hex(random_bytes(12)),
                'senderId'   => $userId,
                'senderName' => $userName,
                'text'       => $text,
                'sentAt'     => date(DATE_ATOM),
            ];

            $conversation['messages'][] = $newMessage;
            $updatedConversation        = $conversation;
            unset($conversation);

            if (!api_write_json_file($conversationFile, $conversations)) {
                api_respond(['success' => false, 'message' => 'Message could not be saved.'], 500);
            }

            api_respond([
                'success'      => true,
                'conversation' => $updatedConversation,
                'messageId'    => $newMessage['id'],
            ]);
        }
    }
    unset($conversation);

    api_respond(['success' => false, 'message' => 'Conversation not found.'], 404);
}

api_respond(['success' => false, 'message' => 'Unsupported action.'], 400);