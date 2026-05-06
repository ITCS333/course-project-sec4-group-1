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
        if (isset($_GET['id'])) {
            $stmt = $db->prepare('SELECT id, name, email, is_admin, created_at FROM users WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) respond(['success' => false], 404);
            $row['is_admin'] = (int)$row['is_admin'];
            respond(['success' => true, 'data' => $row]);
        }
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $s = '%' . $_GET['search'] . '%';
            $stmt = $db->prepare('SELECT id, name, email, is_admin, created_at FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY id ASC');
            $stmt->execute([$s, $s]);
        } else {
            $stmt = $db->query('SELECT id, name, email, is_admin, created_at FROM users ORDER BY id ASC');
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['is_admin'] = (int)$row['is_admin'];
        respond(['success' => true, 'data' => $rows]);
    case 'POST':
        $data = inputData();
        if ($action === 'change_password') {
            if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) respond(['success' => false], 400);
            if (strlen((string)$data['new_password']) < 8) respond(['success' => false], 400);
            $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$data['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) respond(['success' => false], 404);
            if (!password_verify($data['current_password'], $user['password'])) respond(['success' => false], 401);
            $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($data['new_password'], PASSWORD_DEFAULT), $data['id']]);
            respond(['success' => true]);
        }
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) respond(['success' => false], 400);
        if (strlen((string)$data['password']) < 8) respond(['success' => false], 400);
        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$data['email']]);
        if ($check->fetch()) respond(['success' => false], 409);
        $stmt = $db->prepare('INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), (int)($data['is_admin'] ?? 0)]);
        respond(['success' => true, 'id' => (int)$db->lastInsertId()], 201);
    case 'PUT':
        $data = inputData();
        if (empty($data['id'])) respond(['success' => false], 400);
        $check = $db->prepare('SELECT id FROM users WHERE id = ?');
        $check->execute([$data['id']]);
        if (!$check->fetch()) respond(['success' => false], 404);
        if (isset($data['email'])) {
            $dup = $db->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $dup->execute([$data['email'], $data['id']]);
            if ($dup->fetch()) respond(['success' => false], 409);
        }
        $fields = [];
        $params = [];
        foreach (['name', 'email', 'is_admin'] as $field) {
            if (array_key_exists($field, $data)) { $fields[] = "$field = ?"; $params[] = $field === 'is_admin' ? (int)$data[$field] : $data[$field]; }
        }
        if (!$fields) respond(['success' => false], 400);
        $params[] = $data['id'];
        $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
        respond(['success' => true]);
    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (!$id) respond(['success' => false], 400);
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) respond(['success' => false], 404);
        respond(['success' => true]);
    default:
        respond(['success' => false], 405);
}
