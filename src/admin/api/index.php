<?php
session_start();
require_once __DIR__ . '/../common/db.php';

$_SESSION['user'] = $_SESSION['user'] ?? 'guest';

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

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function Students() {
    $file = 'students.json';
    if (!file_exists($file)) return [];
    $jsonData = file_get_contents($file);
    return json_decode($jsonData, true);
}

function getStudents() {
    $students = Students();
    if (!$students) sendResponse(['success' => false, 'message' => 'No students found'], 404);
    sendResponse(['success' => true, 'data' => $students]);
}

function getStudentByIdJson($studentId) {
    $students = Students();
    foreach ($students as $student) {
        if ($student["id"] == $studentId) sendResponse(['success' => true, 'data' => $student]);
    }
    sendResponse(['success' => false, 'message' => 'Student not found'], 404);
}

function createStudent($db, $data) {
    $student_id = sanitizeInput($data['student_id'] ?? '');
    $name = sanitizeInput($data['name'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $password = $data['password'] ?? '';
    if (!$student_id || !$name || !$email || !$password) sendResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    if (!validateEmail($email)) sendResponse(['success' => false, 'message' => 'Invalid email'], 400);
    $stmt = $db->prepare("SELECT * FROM students WHERE student_id = :student_id OR email = :email");
    $stmt->execute([':student_id' => $student_id, ':email' => $email]);
    if ($stmt->fetch()) sendResponse(['success' => false, 'message' => 'Student ID or Email already exists'], 409);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO students (student_id, name, email, password) VALUES (:student_id, :name, :email, :password)");
    $success = $stmt->execute([':student_id' => $student_id, ':name' => $name, ':email' => $email, ':password' => $hashedPassword]);
    if ($success) sendResponse(['success' => true, 'message' => 'Student created'], 201);
    sendResponse(['success' => false, 'message' => 'Failed to create student'], 500);
}

function updateStudent($db, $data) {
    $student_id = sanitizeInput($data['student_id'] ?? '');
    if (!$student_id) sendResponse(['success' => false, 'message' => 'student_id required'], 400);
    $stmt = $db->prepare("SELECT * FROM students WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) sendResponse(['success' => false, 'message' => 'Student not found'], 404);
    $fields = [];
    $params = [':student_id' => $student_id];
    if (!empty($data['name'])) { $fields[] = 'name = :name'; $params[':name'] = sanitizeInput($data['name']); }
    if (!empty($data['email'])) {
        if (!validateEmail($data['email'])) sendResponse(['success' => false, 'message' => 'Invalid email'], 400);
        $stmt = $db->prepare("SELECT * FROM students WHERE email = :email AND student_id != :student_id");
        $stmt->execute([':email' => $data['email'], ':student_id' => $student_id]);
        if ($stmt->fetch()) sendResponse(['success' => false, 'message' => 'Email already exists'], 409);
        $fields[] = 'email = :email';
        $params[':email'] = sanitizeInput($data['email']);
    }
    if (!$fields) sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    $sql = "UPDATE students SET " . implode(', ', $fields) . " WHERE student_id = :student_id";
    $success = $db->prepare($sql)->execute($params);
    if ($success) sendResponse(['success' => true, 'message' => 'Student updated']);
    sendResponse(['success' => false, 'message' => 'Failed to update student'], 500);
}

function deleteStudent($db, $studentId) {
    if (!$studentId) sendResponse(['success' => false, 'message' => 'student_id required'], 400);
    $stmt = $db->prepare("SELECT * FROM students WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $studentId]);
    if (!$stmt->fetch()) sendResponse(['success' => false, 'message' => 'Student not found'], 404);
    $success = $db->prepare("DELETE FROM students WHERE student_id = :student_id")->execute([':student_id' => $studentId]);
    if ($success) sendResponse(['success' => true, 'message' => 'Student deleted']);
    sendResponse(['success' => false, 'message' => 'Failed to delete student'], 500);
}

function changePassword($db, $data) {
    $student_id = sanitizeInput($data['student_id'] ?? '');
    $current_password = $data['current_password'] ?? '';
    $new_password = $data['new_password'] ?? '';
    if (!$student_id || !$current_password || !$new_password) sendResponse(['success' => false, 'message' => 'Missing required fields'], 400);
    if (strlen($new_password) < 8) sendResponse(['success' => false, 'message' => 'Password too short'], 400);
    $stmt = $db->prepare("SELECT password FROM students WHERE student_id = :student_id");
    $stmt->execute([':student_id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student || !password_verify($current_password, $student['password'])) sendResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
    $success = $db->prepare("UPDATE students SET password = :password WHERE student_id = :student_id")
                  ->execute([':password' => $hashedPassword, ':student_id' => $student_id]);
    if ($success) sendResponse(['success' => true, 'message' => 'Password changed']);
    sendResponse(['success' => false, 'message' => 'Failed to change password'], 500);
}

try {
    if ($method === 'GET') {
        if (!empty($queryParams['student_id'])) getStudentByIdJson($queryParams['student_id']);
        getStudents();
    } elseif ($method === 'POST') {
        if (!empty($queryParams['action']) && $queryParams['action'] === 'change_password') changePassword($db, $input);
        createStudent($db, $input);
    } elseif ($method === 'PUT') {
        updateStudent($db, $input);
    } elseif ($method === 'DELETE') {
        $studentId = $queryParams['student_id'] ?? $input['student_id'] ?? '';
        deleteStudent($db, $studentId);
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    sendResponse(['success' => false, 'message' => 'Database error'], 500);
} catch (Exception $e) {
    sendResponse(['success' => false, 'message' => 'Server error'], 500);
}
?>

