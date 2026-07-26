<?php
// ================================================
// book_appointment.php — TeleCare
// Appointment booking backend
// ================================================

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// ── DATABASE CONFIG ──
$host     = "localhost";
$dbname   = "telecare_db";
$username = "root";
$password = "";   // XAMPP default blank hai

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB connection failed: " . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit;
}

// ── SANITIZE ──
function clean($v) { return htmlspecialchars(strip_tags(trim($v))); }

$patient_name      = clean($_POST['patient_name']     ?? '');
$email             = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$phone             = clean($_POST['phone']            ?? '');
$record            = clean($_POST['record']           ?? '');
$appointment_date  = clean($_POST['appointment_date'] ?? '');
$appointment_time  = clean($_POST['appointment_time'] ?? '');
$doctor_id         = intval($_POST['doctor_id']       ?? 0);

// ── VALIDATE ──
$errors = [];
if (empty($patient_name))     $errors[] = "Patient name is required.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
if (empty($phone))            $errors[] = "Phone number is required.";
if (empty($appointment_date)) $errors[] = "Date is required.";
if (empty($appointment_time)) $errors[] = "Time slot is required.";
if ($doctor_id <= 0)          $errors[] = "Invalid doctor.";
if (!empty($appointment_date) && $appointment_date < date('Y-m-d'))
    $errors[] = "Date cannot be in the past.";

if (!empty($errors)) {
    echo json_encode(["success" => false, "message" => implode(" ", $errors)]);
    exit;
}

// ── CHECK DUPLICATE SLOT ──
try {
    $check = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id=:d AND appointment_date=:dt AND appointment_time=:t LIMIT 1");
    $check->execute([':d' => $doctor_id, ':dt' => $appointment_date, ':t' => $appointment_time]);
    if ($check->rowCount() > 0) {
        echo json_encode(["success" => false, "message" => "This time slot is already booked. Please choose another."]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    exit;
}

// ── INSERT ──
try {
    $stmt = $pdo->prepare(
        "INSERT INTO appointments (patient_name, email, phone, record, appointment_date, appointment_time, doctor_id, created_at)
         VALUES (:name, :email, :phone, :record, :date, :time, :doc, NOW())"
    );
    $stmt->execute([
        ':name'  => $patient_name,
        ':email' => $email,
        ':phone' => $phone,
        ':record'=> $record,
        ':date'  => $appointment_date,
        ':time'  => $appointment_time,
        ':doc'   => $doctor_id
    ]);
    echo json_encode(["success" => true, "message" => "Appointment booked!", "id" => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Booking failed: " . $e->getMessage()]);
}
?>
