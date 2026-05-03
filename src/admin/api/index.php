<?php

header("Content-Type: application/json; charset=UTF-8");
require_once "../../db.php";

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);
$id = $_GET['id'] ?? null;
$search = $_GET['search'] ?? null;
$action = $_GET['action'] ?? null;

function sendResponse(bool $success, $data = null, int $code = 200) {
    http_response_code($code);
    echo json_encode(["success" => $success, "data" => $data]);
    exit;
}

switch ($method) {
    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare("SELECT id, name, email, is_admin FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                sendResponse(true, $user);
            } else {
                sendResponse(false, "User not found", 404);
            }
        } else {
            $query = "SELECT id, name, email, is_admin FROM users";
            $params = [];
            if ($search) {
                $query .= " WHERE name LIKE ? OR email LIKE ?";
                $params = ["%$search%", "%$search%"];
            }
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            sendResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        break;

    case 'POST':
        if ($action === 'change_password') {
            $userId = $input['id'] ?? null;
            $currentPw = $input['current_password'] ?? '';
            $newPw = $input['new_password'] ?? '';

            if (strlen($newPw) < 8) sendResponse(false, "Password too short", 400);

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user && ($currentPw === $user['password'] || password_verify($currentPw, $user['password']))) {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$newPw, $userId]);
                sendResponse(true, "Password updated");
            } else {
                sendResponse(false, "Invalid current password", 401);
            }
        } 
        else {
            $name = $input['name'] ?? null;
            $email = $input['email'] ?? null;
            $password = $input['password'] ?? null;
            $is_admin = $input['is_admin'] ?? 0;

            if (!$name || !$email || !$password) sendResponse(false, "Missing fields", 400);
            if (strlen($password) < 8) sendResponse(false, "Password too short", 400);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) sendResponse(false, "Email exists", 409);

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $password, $is_admin])) {
                sendResponse(true, "User created", 201);
            }
        }
        break;

    case 'PUT':
        $userId = $input['id'] ?? null;
        if (!$userId) sendResponse(false, "ID required", 400);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) sendResponse(false, "Not found", 404);

        if (isset($input['email'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$input['email'], $userId]);
            if ($stmt->fetch()) sendResponse(false, "Email taken", 409);
        }

        $name = $input['name'] ?? null;
        $email = $input['email'] ?? null;
        
        $stmt = $pdo->prepare("UPDATE users SET name = COALESCE(?, name), email = COALESCE(?, email) WHERE id = ?");
        $stmt->execute([$name, $email, $userId]);
        sendResponse(true, "User updated");
        break;

    case 'DELETE':
        if (!$id) sendResponse(false, "ID required", 400);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) sendResponse(false, "Not found", 404);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, "User deleted");
        break;

    default:
        sendResponse(false, "Method Not Allowed", 405);
        break;
}
