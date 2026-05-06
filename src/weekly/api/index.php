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
        if ($action === 'comments') {
            $weekId = $_GET['week_id'] ?? null;
            $stmt = $db->prepare('SELECT id, week_id, author, text, created_at FROM comments_week WHERE week_id = ? ORDER BY created_at ASC');
            $stmt->execute([$weekId]);
            respond(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if (isset($_GET['id'])) {
            $stmt = $db->prepare('SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) respond(['success' => false], 404);
            $row['links'] = json_decode($row['links'] ?: '[]', true) ?: [];
            respond(['success' => true, 'data' => $row]);
        }
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $s = '%' . $_GET['search'] . '%';
            $stmt = $db->prepare('SELECT id, title, start_date, description, links, created_at FROM weeks WHERE title LIKE ? OR description LIKE ? ORDER BY start_date ASC, id ASC');
            $stmt->execute([$s, $s]);
        } else {
            $stmt = $db->query('SELECT id, title, start_date, description, links, created_at FROM weeks ORDER BY start_date ASC, id ASC');
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['links'] = json_decode($row['links'] ?: '[]', true) ?: [];
        respond(['success' => true, 'data' => $rows]);
    case 'POST':
        $data = inputData();
        if ($action === 'comment') {
            if (empty($data['week_id']) || empty($data['text'])) respond(['success' => false], 400);
            $check = $db->prepare('SELECT id FROM weeks WHERE id = ?');
            $check->execute([$data['week_id']]);
            if (!$check->fetch()) respond(['success' => false], 404);
            $stmt = $db->prepare('INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)');
            $stmt->execute([$data['week_id'], $data['author'] ?? 'Anonymous', $data['text']]);
            respond(['success' => true, 'id' => (int)$db->lastInsertId()], 201);
        }
        if (empty($data['title']) || empty($data['start_date'])) respond(['success' => false], 400);
        if (!validDateValue((string)$data['start_date'])) respond(['success' => false], 400);
        $stmt = $db->prepare('INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['title'], $data['start_date'], $data['description'] ?? '', json_encode($data['links'] ?? [])]);
        respond(['success' => true, 'id' => (int)$db->lastInsertId()], 201);
    case 'PUT':
        $data = inputData();
        if (empty($data['id'])) respond(['success' => false], 400);
        $check = $db->prepare('SELECT id FROM weeks WHERE id = ?');
        $check->execute([$data['id']]);
        if (!$check->fetch()) respond(['success' => false], 404);
        if (isset($data['start_date']) && !validDateValue((string)$data['start_date'])) respond(['success' => false], 400);
        $fields = [];
        $params = [];
        foreach (['title', 'start_date', 'description'] as $field) {
            if (array_key_exists($field, $data)) { $fields[] = "$field = ?"; $params[] = $data[$field]; }
        }
        if (array_key_exists('links', $data)) { $fields[] = 'links = ?'; $params[] = json_encode($data['links']); }
        if (!$fields) respond(['success' => false], 400);
        $params[] = $data['id'];
        $stmt = $db->prepare('UPDATE weeks SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
        respond(['success' => true]);
    case 'DELETE':
        if ($action === 'delete_comment') {
            $commentId = $_GET['comment_id'] ?? null;
            $stmt = $db->prepare('DELETE FROM comments_week WHERE id = ?');
            $stmt->execute([$commentId]);
            if ($stmt->rowCount() < 1) respond(['success' => false], 404);
            respond(['success' => true]);
        }
        $id = $_GET['id'] ?? null;
        if (!$id) respond(['success' => false], 400);
        $stmt = $db->prepare('DELETE FROM weeks WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) respond(['success' => false], 404);
        respond(['success' => true]);
    default:
        respond(['success' => false], 405);
}
