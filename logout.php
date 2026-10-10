<?php
require_once __DIR__ . '/auth_helpers.php';
start_auth_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !auth_csrf_is_valid()) {
    http_response_code(403);
    exit('Invalid sign-out request.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: welcome.php');
exit;
