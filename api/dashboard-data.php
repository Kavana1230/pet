<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../config/db.php';

$sessionUser = api_require_user(['GET'], 'buyer');
$userId = (string) $sessionUser['id'];

header('Cache-Control: no-cache, no-store, must-revalidate');

$users = load_auth_users();
$currentUser = null;
foreach ($users as $user) {
    if (($user['id'] ?? '') === $userId) {
        $currentUser = $user;
        break;
    }
}
if ($currentUser === null) {
    api_respond(['success' => false, 'message' => 'Account was not found. Please sign in again.'], 401);
}
api_sync_session_user($currentUser);

$database = pawnet_db();

// Load persistent applications from MySQL (fixes Bug B)
$appStmt = $database->prepare('
    SELECT a.id, a.pet_id AS petId, a.buyer_id AS buyerId, a.seller_id AS sellerId,
           a.conversation_id AS conversationId, a.status, a.notes,
           a.created_at AS createdAt, a.updated_at AS updatedAt,
           a.meeting_confirmed_at AS meetingConfirmedAt,
           p.name AS petName, p.breed, p.type, p.sex, p.age, p.status AS petStatus,
           COALESCE(
               (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id AND pi.is_primary = 1 LIMIT 1),
               (SELECT pi.image_url FROM pet_images pi WHERE pi.pet_id = p.id ORDER BY pi.sort_order ASC LIMIT 1),
               "https://images.unsplash.com/photo-1552053831-71594a27632d?auto=format&fit=crop&w=900&q=80"
           ) AS image
    FROM applications a
    INNER JOIN pets p ON p.id = a.pet_id
    WHERE a.buyer_id = :buyer_id
    ORDER BY a.created_at DESC
');
$appStmt->execute(['buyer_id' => $userId]);
$applications = $appStmt->fetchAll();

foreach ($applications as &$application) {
    $application['date'] = date('Y-m-d', strtotime($application['createdAt']));
    $rawStatus = (string) $application['status'];
    $application['displayStatus'] = match ($rawStatus) {
        'pending' => 'Pending Review',
        'accepted' => 'Accepted',
        'meeting_confirmed' => 'Meeting Confirmed',
        'completed' => 'Completed',
        'rejected' => 'Declined',
        'cancelled' => 'Cancelled',
        default => ucfirst($rawStatus),
    };
    $application['nextStep'] = match ($rawStatus) {
        'pending' => 'Awaiting seller review',
        'accepted' => 'Ready to confirm meeting',
        'meeting_confirmed' => 'Meeting scheduled · Pet is reserved',
        'completed' => 'Adoption complete',
        'rejected' => 'Conversation closed',
        'cancelled' => 'Listing reopened',
        default => 'In progress',
    };
}
unset($application);

// Check for admin messages/warnings
$adminMsgStmt = $database->prepare('
    SELECT id, admin_name AS adminName, subject, message_text AS text, is_warning AS isWarning, created_at AS sentAt
    FROM admin_messages
    WHERE recipient_id = :user_id
    ORDER BY created_at DESC
');
$adminMsgStmt->execute(['user_id' => $userId]);
$adminMessages = $adminMsgStmt->fetchAll();

$settings = $currentUser['settings'] ?? [];
$settings = array_merge([
    'emailNotifications' => true,
    'recommendations' => true,
    'appointmentReminders' => true,
    'darkMode' => false,
], is_array($settings) ? $settings : []);

$memberSince = '';
if (!empty($currentUser['createdAt'])) {
    $createdAt = strtotime((string) $currentUser['createdAt']);
    $memberSince = $createdAt ? date('F Y', $createdAt) : '';
}

$supportMessages = array_values(array_filter(
    api_read_json_file(__DIR__ . '/../data/messages.json'),
    static fn ($message) => ($message['userId'] ?? '') === $userId
));

api_respond([
    'success' => true,
    'profile' => [
        'id' => $userId,
        'name' => $currentUser['name'] ?? '',
        'email' => $currentUser['email'] ?? '',
        'phone' => $currentUser['phone'] ?? '',
        'address' => $currentUser['address'] ?? '',
        'memberSince' => $memberSince,
    ],
    'favorites' => array_values(array_unique($currentUser['favorites'] ?? [])),
    'settings' => $settings,
    'applications' => $applications,
    'messages' => $supportMessages,
    'adminMessages' => $adminMessages,
    'stats' => [
        'favorites' => count(array_unique($currentUser['favorites'] ?? [])),
        'applications' => count($applications),
        'adoptions' => count(array_filter($applications, static fn ($item) => ($item['status'] ?? '') === 'completed')),
        'messages' => count(array_filter($supportMessages, static fn ($item) => ($item['sender'] ?? '') === 'team')) + count($adminMessages),
    ],
]);
