<?php
// ================================================
// auth.php — TeleCare
// User login and signup backend
// ================================================

header("Content-Type: application/json");
session_start();

$host     = "localhost";
$dbname   = "telecare_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB connection failed."]);
    exit;
}

$action = $_POST['action'] ?? '';

// ── SIGNUP ──
if ($action === 'signup') {
    $name  = htmlspecialchars(trim($_POST['name']  ?? ''));
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!$name || !$email || !$phone || !$pass) {
        echo json_encode(["success" => false, "message" => "All fields are required."]);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Invalid email."]);
        exit;
    }
    if (strlen($pass) < 6) {
        echo json_encode(["success" => false, "message" => "Password must be at least 6 characters."]);
        exit;
    }

    // Check if email exists
    $chk = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $chk->execute([':email' => $email]);
    if ($chk->rowCount() > 0) {
        echo json_encode(["success" => false, "message" => "This email is already registered. Please login."]);
        exit;
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $ins  = $pdo->prepare("INSERT INTO users (name, email, phone, password, created_at) VALUES (:n, :e, :p, :h, NOW())");
    $ins->execute([':n' => $name, ':e' => $email, ':p' => $phone, ':h' => $hash]);
    echo json_encode(["success" => true, "message" => "Account created successfully!"]);

// ── LOGIN ──
} elseif ($action === 'login') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        echo json_encode(["success" => false, "message" => "Email and password are required."]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($pass, $user['password'])) {
        echo json_encode(["success" => false, "message" => "Invalid email or password."]);
        exit;
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    echo json_encode(["success" => true, "message" => "Logged in!", "name" => $user['name']]);

} else {
    echo json_encode(["success" => false, "message" => "Invalid action."]);
}
?>
