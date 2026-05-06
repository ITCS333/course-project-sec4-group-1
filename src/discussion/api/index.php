<?php
if (!function_exists('getDBConnection')) {
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        require_once __DIR__ . '/../../common/db.php';
    }
}
header('Content-Type: application/json; charset=utf-8');
function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}
function inputData(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}
function validDateValue(?string $date): bool
{
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
try {
    $db = getDBConnection();
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Database connection failed'], 500);
}
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

switch ($method) {
    case 'GET':
        if ($action === 'replies') {
            $topicId = $_GET['topic_id'] ?? null;
            $stmt = $db->prepare('SELECT id, topic_id, text, author, created_at FROM replies WHERE topic_id = ? ORDER BY created_at ASC');
            $stmt->execute([$topicId]);
            respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if (isset($_GET['id'])) {
            $stmt = $db->prepare('SELECT id, subject, message, author, created_at FROM topics WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) respond(['success' => false], 404);
            respond(['success' => true, 'data' => $row]);
        }
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $s = '%' . $_GET['search'] . '%';
            $stmt = $db->prepare('SELECT id, subject, message, author, created_at FROM topics WHERE subject LIKE ? OR message LIKE ? OR author LIKE ? ORDER BY id ASC');
            $stmt->execute([$s, $s, $s]);
        } else {
            $stmt = $db->query('SELECT id, subject, message, author, created_at FROM topics ORDER BY id ASC');
        }
        respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    case 'POST':
        $data = inputData();
        if ($action === 'reply') {
            if (empty($data['topic_id']) || empty($data['author']) || empty($data['text'])) respond(['success' => false], 400);
            $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
            $check->execute([$data['topic_id']]);
            if (!$check->fetch()) respond(['success' => false], 404);
            $stmt = $db->prepare('INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)');
            $stmt->execute([$data['topic_id'], $data['text'], $data['author']]);
            respond(['success' => true, 'id' => (int)$db->lastInsertId()], 201);
        }
        if (empty($data['subject']) || empty($data['message']) || empty($data['author'])) respond(['success' => false], 400);
        $stmt = $db->prepare('INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)');
        $stmt->execute([$data['subject'], $data['message'], $data['author']]);
        respond(['success' => true, 'id' => (int)$db->lastInsertId()], 201);
    case 'PUT':
        $data = inputData();
        if (empty($data['id'])) respond(['success' => false], 400);
        $check = $db->prepare('SELECT id FROM topics WHERE id = ?');
        $check->execute([$data['id']]);
        if (!$check->fetch()) respond(['success' => false], 404);
        $fields = [];
        $params = [];
        foreach (['subject', 'message', 'author'] as $field) {
            if (array_key_exists($field, $data)) { $fields[] = "$field = ?"; $params[] = $data[$field]; }
        }
        if (!$fields) respond(['success' => false], 400);
        $params[] = $data['id'];
        $stmt = $db->prepare('UPDATE topics SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
        respond(['success' => true]);
    case 'DELETE':
        if ($action === 'delete_reply') {
            $replyId = $_GET['id'] ?? null;
            $stmt = $db->prepare('DELETE FROM replies WHERE id = ?');
            $stmt->execute([$replyId]);
            if ($stmt->rowCount() < 1) respond(['success' => false], 404);
            respond(['success' => true]);
        }
        $id = $_GET['id'] ?? null;
        if (!$id) respond(['success' => false], 400);
        $stmt = $db->prepare('DELETE FROM topics WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) respond(['success' => false], 404);
        respond(['success' => true]);
    default:
        respond(['success' => false], 405);
}
