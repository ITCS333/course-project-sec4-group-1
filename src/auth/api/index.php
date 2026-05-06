<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
function respond($data, int $code = 200): void { http_response_code($code); echo json_encode($data); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'], 405);
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim((string)($data['email'] ?? ''));
$password = (string)($data['password'] ?? '');
if ($email === '' || $password === '') respond(['success'=>false,'message'=>'Missing email or password'], 400);
$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT id, name, email, password, is_admin FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || !password_verify($password, $user['password'])) respond(['success'=>false,'message'=>'Invalid credentials'], 401);
unset($user['password']);
$user['is_admin'] = (int)$user['is_admin'];
respond(['success'=>true,'user'=>$user]);
}

try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, name, email, password, is_admin FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = $user['is_admin'];

        unset($user['password']);

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => (int)$user['is_admin']
            ]
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
