<?php
session_start();
require_once __DIR__ . '/../../common/db.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$queryParams = $_GET;

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function handleGet($db, $queryParams) {
    if (!empty($queryParams['id'])) {
        $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $queryParams['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) sendResponse(['success' => false, 'message' => 'User not found'], 404);
        sendResponse(['success' => true, 'data' => $user]);
    }

    $sql = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];

    if (!empty($queryParams['search'])) {
        $sql .= " WHERE name LIKE :search OR email LIKE :search";
        $params[':search'] = '%' . $queryParams['search'] . '%';
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $users]);
}

function handlePost($db, $input, $queryParams) {
    if (($queryParams['action'] ?? '') === 'change_password') {
        $id = $input['id'] ?? '';
        $current = $input['current_password'] ?? '';
        $new = $input['new_password'] ?? '';

        if (!$id || !$current || !$new) sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
        if (strlen($new) < 8) sendResponse(['success' => false, 'message' => 'Password too short'], 400);

        $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password'])) {
            sendResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password = :p WHERE id = :id")->execute([':p' => $hashed, ':id' => $id]);
        sendResponse(['success' => true, 'message' => 'Password updated']);
    }

    $name = $input['name'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $is_admin = $input['is_admin'] ?? 0;

    if (!$name || !$email || !$password) sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    if (strlen($password) < 8) sendResponse(['success' => false, 'message' => 'Short password'], 400);

    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) sendResponse(['success' => false, 'message' => 'Email exists'], 409);

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (name, email, password, is_admin) VALUES (:n, :e, :p, :a)";
    $db->prepare($sql)->execute([':n' => $name, ':e' => $email, ':p' => $hashed, ':a' => $is_admin]);
    
    sendResponse(['success' => true, 'message' => 'Created'], 201);
}

function handlePut($db, $input) {
    $id = $input['id'] ?? '';
    if (!$id) sendResponse(['success' => false, 'message' => 'ID required'], 400);

    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) sendResponse(['success' => false, 'message' => 'Not found'], 404);

    $fields = [];
    $params = [':id' => $id];

    if (!empty($input['name'])) { $fields[] = "name = :name"; $params[':name'] = $input['name']; }
    if (!empty($input['email'])) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $stmt->execute([':email' => $input['email'], ':id' => $id]);
        if ($stmt->fetch()) sendResponse(['success' => false, 'message' => 'Email taken'], 409);
        
        $fields[] = "email = :email";
        $params[':email'] = $input['email'];
    }

    if (empty($fields)) sendResponse(['success' => false, 'message' => 'Nothing to update'], 400);

    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
    $db->prepare($sql)->execute($params);
    sendResponse(['success' => true, 'message' => 'Updated']);
}

function handleDelete($db, $queryParams) {
    $id = $queryParams['id'] ?? '';
    if (!$id) sendResponse(['success' => false, 'message' => 'ID required'], 400);

    $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) sendResponse(['success' => false, 'message' => 'Not found'], 404);

    $db->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
    sendResponse(['success' => true, 'message' => 'Deleted']);
}

try {
    switch ($method) {
        case 'GET':    handleGet($db, $queryParams); break;
        case 'POST':   handlePost($db, $input, $queryParams); break;
        case 'PUT':    handlePut($db, $input); break;
        case 'DELETE': handleDelete($db, $queryParams); break;
        default:       sendResponse(['success' => false], 405); break;
    }
} catch (Exception $e) {
    sendResponse(['success' => false, 'message' => 'Server Error'], 500);
}
