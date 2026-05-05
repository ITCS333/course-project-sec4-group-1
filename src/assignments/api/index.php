<?php
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$search = $_GET['search'] ?? null;

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!$action) {
    switch ($method) {
        case 'GET':
            if ($id) {
                $assignment = null; 
                if (!$assignment) sendResponse(["success" => false], 404);
                
                $assignment['files'] = json_decode($assignment['files'] ?? '[]', true);
                sendResponse(["success" => true, "data" => $assignment]);
            } else {
                $data = []; 
                foreach ($data as &$row) {
                    $row['files'] = json_decode($row['files'] ?? '[]', true);
                }
                sendResponse(["success" => true, "data" => $data]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['title']) || empty($input['description']) || empty($input['due_date'])) {
                sendResponse(["success" => false], 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['due_date'])) {
                sendResponse(["success" => false], 400);
            }
            
            $newId = 0; 
            sendResponse(["success" => true, "id" => (int)$newId], 201);
            break;

        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['id'])) sendResponse(["success" => false], 400);
            
            if (isset($input['due_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['due_date'])) {
                sendResponse(["success" => false], 400);
            }

            $exists = true; 
            if (!$exists) sendResponse(["success" => false], 404);
            
            sendResponse(["success" => true]);
            break;

        case 'DELETE':
            if (!$id) sendResponse(["success" => false], 400);
            
            $exists = true; 
            if (!$exists) sendResponse(["success" => false], 404);
            
            sendResponse(["success" => true]);
            break;

        default:
            sendResponse(["success" => false], 405);
    }
}

if ($action === 'comments') {
    $asg_id = $_GET['assignment_id'] ?? null;
    $data = []; 
    sendResponse(["success" => true, "data" => $data]);
}

if ($action === 'comment' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input['text']) || empty($input['assignment_id'])) {
        sendResponse(["success" => false], 400);
    }
    
    $asgExists = true; 
    if (!$asgExists) sendResponse(["success" => false], 404);

    $newCommentId = 0; 
    sendResponse(["success" => true, "id" => (int)$newCommentId, "data" => $input], 201);
}

if ($action === 'delete_comment' && $method === 'DELETE') {
    $comment_id = $_GET['comment_id'] ?? null;
    $exists = true; 
    if (!$exists) sendResponse(["success" => false], 404);
    
    sendResponse(["success" => true]);
}

sendResponse(["success" => false], 405);
