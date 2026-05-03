<?php
/**
 * Authentication Handler for Login Form
 * Secure Login Script – PDO + JSON Response + Sessions
 */

session_start(); // Start session BEFORE any output

// --- Response Headers ---
header("Content-Type: application/json");

// Optional CORS (enable only if needed)
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: POST");
// header("Access-Control-Allow-Headers: Content-Type");

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method"
    ]);
    exit;
}

// --- Get POST Data ---
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

// Check required fields
if (!isset($data["email"]) || !isset($data["password"])) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required"
    ]);
    exit;
}

$email = trim($data["email"]);
$password = $data["password"];

// --- Server-Side Validation ---
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email format"
    ]);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 8 characters"
    ]);
    exit;
}

// --- Database Connection ---
require_once __DIR__ . '/../common/db.php';
 // Must contain getDBConnection()

try {
    $pdo = getDBConnection();

    // --- Prepare SQL Query ---
    $sql = "SELECT id, name, email, password FROM users WHERE email = :email LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([":email" => $email]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Verify User & Password ---
    if ($user && password_verify($password, $user["password"])) {

        // --- Create Session ---
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["logged_in"] = true;

        // --- Success Response ---
        echo json_encode([
            "success" => true,
            "message" => "Login successful",
            "user" => [
                "id" => $user["id"],
                "name" => $user["name"],
                "email" => $user["email"]
            ]
        ]);
        exit;

    } else {
        // --- Failed Authentication ---
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);
        exit;
    }

} catch (PDOException $e) {

    // Log the error (server log only)
    error_log("DB Error (Login): " . $e->getMessage());

    // Generic error for client
    echo json_encode([
        "success" => false,
        "message" => "An error occurred. Please try again later."
    ]);
    exit;
}

?>
