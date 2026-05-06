<?php
session_start();
if (!function_exists('getDBConnection')) {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        require_once __DIR__ . '/../../common/db.php';
    }
}
header('Content-Type: application/json; charset=utf-8');
function authRespond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') authRespond(['success' => false, 'message' => 'Method Not Allowed'], 405);
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = [];
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
if ($email === '') authRespond(['success' => false, 'message' => 'Email is required'], 400);
if ($password === '') authRespond(['success' => false, 'message' => 'Password is required'], 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) authRespond(['success' => false, 'message' => 'Invalid email format'], 400);
if (strlen((string)$password) < 8) authRespond(['success' => false, 'message' => 'Password too short'], 400);
try {
    $db = getDBConnection();
    $stmt = $db->prepare('SELECT id, name, email, password, is_admin FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($password, $user['password'])) authRespond(['success' => false, 'message' => 'Invalid credentials'], 401);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['is_admin'] = (int)$user['is_admin'];
    authRespond(['success' => true, 'message' => 'Login successful', 'user' => [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'is_admin' => (int)$user['is_admin']
    ]]);
} catch (Throwable $e) {
    authRespond(['success' => false, 'message' => 'Server error'], 500);
}
