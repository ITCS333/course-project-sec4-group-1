<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$db   = 'course_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Helper to validate YYYY-MM-DD
function isValidDate($date) {
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

switch ($method) {
    case 'GET':
        if ($action === 'comments') {
            $week_id = (int)($_GET['week_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM comments_week WHERE week_id = ? ORDER BY created_at ASC");
            $stmt->execute([$week_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } elseif ($id) {
            $stmt = $pdo->prepare("SELECT * FROM weeks WHERE id = ?");
            $stmt->execute([$id]);
            $week = $stmt->fetch();
            if (!$week) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Week not found']);
            } else {
                $week['links'] = json_decode($week['links'] ?? '[]', true);
                echo json_encode(['success' => true, 'data' => $week]);
            }
        } else {
            $search = $_GET['search'] ?? null;
            if ($search) {
                $stmt = $pdo->prepare("SELECT * FROM weeks WHERE title LIKE ? OR description LIKE ? ORDER BY start_date ASC");
                $stmt->execute(["%$search%", "%$search%"]);
            } else {
                $stmt = $pdo->query("SELECT * FROM weeks ORDER BY start_date ASC");
            }
            $weeks = $stmt->fetchAll();
            foreach ($weeks as &$w) {
                $w['links'] = json_decode($w['links'] ?? '[]', true);
            }
            echo json_encode(['success' => true, 'data' => $weeks]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        if ($action === 'comment') {
            if (empty($data['text']) || empty($data['week_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing fields']);
                exit;
            }
            // Check if week exists
            $check = $pdo->prepare("SELECT id FROM weeks WHERE id = ?");
            $check->execute([$data['week_id']]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Week not found']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
            $stmt->execute([$data['week_id'], $data['author'] ?? 'Anonymous', $data['text']]);
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            if (empty($data['title']) || empty($data['start_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing title or start_date']);
                exit;
            }
            if (!isValidDate($data['start_date'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid date format']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['title'],
                $data['start_date'],
                $data['description'] ?? '',
                json_encode($data['links'] ?? [])
            ]);
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id'])) {
            http_response_code(400);
            exit;
        }
        
        // Check existence
        $check = $pdo->prepare("SELECT id FROM weeks WHERE id = ?");
        $check->execute([$data['id']]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false]);
            exit;
        }

        if (isset($data['start_date']) && !isValidDate($data['start_date'])) {
            http_response_code(400);
            echo json_encode(['success' => false]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE weeks SET title = COALESCE(?, title), start_date = COALESCE(?, start_date), description = COALESCE(?, description), links = COALESCE(?, links) WHERE id = ?");
        $stmt->execute([
            $data['title'] ?? null,
            $data['start_date'] ?? null,
            $data['description'] ?? null,
            isset($data['links']) ? json_encode($data['links']) : null,
            $data['id']
        ]);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        if ($action === 'delete_comment') {
            $comment_id = (int)($_GET['comment_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM comments_week WHERE id = ?");
            $stmt->execute([$comment_id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false]);
            } else {
                echo json_encode(['success' => true]);
            }
        } else {
            if (!$id) {
                http_response_code(400);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM weeks WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false]);
            } else {
                echo json_encode(['success' => true]);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        break;
}
