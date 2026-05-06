<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
function body(): array { return json_decode(file_get_contents('php://input'), true) ?: []; }
function ok($data=null, int $code=200): void { http_response_code($code); echo json_encode(['success'=>true,'data'=>$data]); exit; }
function fail(string $message, int $code): void { http_response_code($code); echo json_encode(['success'=>false,'message'=>$message]); exit; }
function userById(PDO $pdo, int $id) { $s=$pdo->prepare('SELECT id,name,email,is_admin,created_at FROM users WHERE id=?'); $s->execute([$id]); return $s->fetch(); }
if ($action === 'change_password' && $method === 'POST') {
    $data = body();
    $id = (int)($data['id'] ?? 1);
    $current = (string)($data['current_password'] ?? '');
    $new = (string)($data['new_password'] ?? '');
    if ($current === '' || $new === '') fail('Missing fields', 400);
    if (strlen($new) < 8) fail('Password too short', 400);
    $s = $pdo->prepare('SELECT password FROM users WHERE id=?');
    $s->execute([$id]);
    $u = $s->fetch();
    if (!$u || !password_verify($current, $u['password'])) fail('Unauthorized', 401);
    $s = $pdo->prepare('UPDATE users SET password=? WHERE id=?');
    $s->execute([password_hash($new, PASSWORD_DEFAULT), $id]);
    ok(['id'=>$id]);
}
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $u = userById($pdo, (int)$_GET['id']);
        if (!$u) fail('User not found', 404);
        ok($u);
    }
    if (!empty($_GET['search'])) {
        $term = '%' . $_GET['search'] . '%';
        $s = $pdo->prepare('SELECT id,name,email,is_admin,created_at FROM users WHERE name LIKE ? OR email LIKE ?');
        $s->execute([$term,$term]);
        ok($s->fetchAll());
    }
    ok($pdo->query('SELECT id,name,email,is_admin,created_at FROM users')->fetchAll());
}
if ($method === 'POST') {
    $d = body();
    $name = trim((string)($d['name'] ?? ''));
    $email = trim((string)($d['email'] ?? ''));
    $password = (string)($d['password'] ?? '');
    $isAdmin = (int)($d['is_admin'] ?? 0);
    if ($name === '' || $email === '' || $password === '') fail('Missing fields', 400);
    if (strlen($password) < 8) fail('Password too short', 400);
    try {
        $s = $pdo->prepare('INSERT INTO users (name,email,password,is_admin) VALUES (?,?,?,?)');
        $s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$isAdmin]);
        ok(['id'=>(int)$pdo->lastInsertId()], 201);
    } catch (PDOException $e) { fail('Duplicate email', 409); }
}
if ($method === 'PUT') {
    $d = body();
    $id = (int)($d['id'] ?? $_GET['id'] ?? 0);
    if (!$id || !userById($pdo,$id)) fail('User not found', 404);
    $name = trim((string)($d['name'] ?? ''));
    $email = trim((string)($d['email'] ?? ''));
    $isAdmin = (int)($d['is_admin'] ?? 0);
    if ($name === '' || $email === '') fail('Missing fields', 400);
    try {
        $s = $pdo->prepare('UPDATE users SET name=?, email=?, is_admin=? WHERE id=?');
        $s->execute([$name,$email,$isAdmin,$id]);
        ok(['id'=>$id]);
    } catch (PDOException $e) { fail('Duplicate email', 409); }
}
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    $s = $pdo->prepare('DELETE FROM users WHERE id=?');
    $s->execute([$id]);
    if ($s->rowCount() < 1) fail('User not found', 404);
    ok(['id'=>$id]);
}
fail('Method not allowed', 405);
