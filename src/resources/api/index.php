<?php

declare(strict_types=1);

header('Content-Type: application/json');

$host = 'localhost';
$db   = 'educational_resources';
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
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? null;

switch ($method) {
    case 'GET':
        if ($action === 'comments') {
            $resourceId = $_GET['resource_id'] ?? null;
            $stmt = $pdo->prepare("SELECT * FROM comments_resource WHERE resource_id = ? ORDER BY created_at DESC");
            $stmt->execute([$resourceId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } elseif (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $resource = $stmt->fetch();
            if ($resource) {
                echo json_encode(['success' => true, 'data' => $resource]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Resource not found']);
            }
        } elseif (isset($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $stmt = $pdo->prepare("SELECT * FROM resources WHERE title LIKE ? OR description LIKE ?");
            $stmt->execute([$search, $search]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } else {
            $stmt = $pdo->query("SELECT * FROM resources ORDER BY created_at DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        }
        break;

    case 'POST':
        if ($action === 'comment') {
            $resourceId = $input['resource_id'] ?? null;
            $author = $input['author'] ?? 'Anonymous';
            $text = $input['text'] ?? null;

            if (!$resourceId || !$text) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                exit;
            }

            $check = $pdo->prepare("SELECT id FROM resources WHERE id = ?");
            $check->execute([$resourceId]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Resource does not exist']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
            $stmt->execute([$resourceId, $author, $text]);
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            $title = $input['title'] ?? null;
            $description = $input['description'] ?? '';
            $link = $input['link'] ?? null;

            if (!$title || !$link) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Title and link are required']);
                exit;
            }

            if (!filter_var($link, FILTER_VALIDATE_URL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid URL']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
            $stmt->execute([$title, $description, $link]);
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        break;

    case 'PUT':
        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID is required']);
            exit;
        }

        $check = $pdo->prepare("SELECT id FROM resources WHERE id = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Resource not found']);
            exit;
        }

        if (isset($input['link']) && !filter_var($input['link'], FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid URL']);
            exit;
        }

        $fields = [];
        $params = [];
        foreach (['title', 'description', 'link'] as $f) {
            if (isset($input[$f])) {
                $fields[] = "$f = ?";
                $params[] = $input[$f];
            }
        }
        $params[] = $id;

        $stmt = $pdo->prepare("UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($params);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        if ($action === 'delete_comment') {
            $id = $_GET['comment_id'] ?? null;
            $stmt = $pdo->prepare("DELETE FROM comments_resource WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Comment not found']);
            }
        } else {
            $id = $_GET['id'] ?? null;
            $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Resource not found']);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
        break;
}
