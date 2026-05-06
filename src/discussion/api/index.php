<?php
session_start();
require_once __DIR__ . '/../../common/db.php';

header("Content-Type: application/json; charset=UTF-8");

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

try {
    $db = getDBConnection();
} catch (Exception $e) {
    sendResponse(['success' => false, 'message' => 'Database connection failed'], 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$query = $_GET;
$action = $query['action'] ?? null;

switch ($method) {
    case 'GET':
        if ($action === 'replies') {
            $topicId = $query['topic_id'] ?? null;
            $stmt = $db->prepare("SELECT id, topic_id, text, author, created_at FROM replies WHERE topic_id = :tid ORDER BY created_at ASC");
            $stmt->execute([':tid' => $topicId]);
            sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if (!empty($query['id'])) {
            $stmt = $db->prepare("SELECT id, subject, message, author, created_at FROM topics WHERE id = :id");
            $stmt->execute([':id' => $query['id']]);
            $topic = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$topic) sendResponse(['success' => false], 404);
            sendResponse(['success' => true, 'data' => $topic]);
        }

        $sql = "SELECT id, subject, message, author, created_at FROM topics";
        $params = [];
        if (!empty($query['search'])) {
            $sql .= " WHERE subject LIKE :s OR message LIKE :s OR author LIKE :s";
            $params[':s'] = '%' . $query['search'] . '%';
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'POST':
        if ($action === 'reply') {
            $tid = $input['topic_id'] ?? null;
            $txt = $input['text'] ?? null;
            $aut = $input['author'] ?? null;

            if (!$tid || !$txt || !$aut) sendResponse(['success' => false], 400);

            $check = $db->prepare("SELECT id FROM topics WHERE id = ?");
            $check->execute([$tid]);
            if (!$check->fetch()) sendResponse(['success' => false], 404);

            $stmt = $db->prepare("INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)");
            $stmt->execute([$tid, $txt, $aut]);
            sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        }

        $sub = $input['subject'] ?? null;
        $msg = $input['message'] ?? null;
        $aut = $input['author'] ?? null;

        if (!$sub || !$msg || !$aut) sendResponse(['success' => false], 400);

        $stmt = $db->prepare("INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)");
        $stmt->execute([$sub, $msg, $aut]);
        sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
        break;

    case 'PUT':
        $id = $input['id'] ?? null;
        $sub = $input['subject'] ?? null;
        if (!$id) sendResponse(['success' => false], 400);

        $check = $db->prepare("SELECT id FROM topics WHERE id = ?");
        $check->execute([$id]);
        if (!$check->fetch()) sendResponse(['success' => false], 404);

        $stmt = $db->prepare("UPDATE topics SET subject = ? WHERE id = ?");
        $stmt->execute([$sub, $id]);
        sendResponse(['success' => true]);
        break;

    case 'DELETE':
        $id = $query['id'] ?? null;
        if (!$id) sendResponse(['success' => false], 400);

        if ($action === 'delete_reply') {
            $check = $db->prepare("SELECT id FROM replies WHERE id = ?");
            $check->execute([$id]);
            if (!$check->fetch()) sendResponse(['success' => false], 404);
            
            $db->prepare("DELETE FROM replies WHERE id = ?")->execute([$id]);
            sendResponse(['success' => true]);
        }

        $check = $db->prepare("SELECT id FROM topics WHERE id = ?");
        $check->execute([$id]);
        if (!$check->fetch()) sendResponse(['success' => false], 404);

        $db->prepare("DELETE FROM topics WHERE id = ?")->execute([$id]);
        sendResponse(['success' => true]);
        break;

    default:
        sendResponse(['success' => false], 405);
        break;
}
