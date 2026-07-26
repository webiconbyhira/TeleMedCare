<?php
// ================================================
// video_consult_backend.php — TeleCare
// Video consultation booking backend
// ================================================

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");

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

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ── GET DOCTOR AVAILABILITY ──
if ($action === 'get_availability') {
    $doctor_id = intval($_GET['doctor_id'] ?? 0);
    $date      = $_GET['date'] ?? date('Y-m-d');

    if ($doctor_id <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid doctor."]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT appointment_time FROM video_consultations WHERE doctor_id=:d AND consult_date=:dt AND status != 'cancelled'");
    $stmt->execute([':d' => $doctor_id, ':dt' => $date]);
    $booked = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'appointment_time');

    echo json_encode(["success" => true, "booked_slots" => $booked]);
    exit;
}

// ── GET ALL DOCTORS WITH LIVE AVAILABILITY ──
if ($action === 'get_doctors') {
    $today = date('Y-m-d');
    $stmt  = $pdo->query("SELECT d.*, 
        (SELECT COUNT(*) FROM video_consultations vc WHERE vc.doctor_id=d.id AND vc.consult_date='$today' AND vc.status!='cancelled') AS booked_today
        FROM doctors d ORDER BY d.name");
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($docs as &$doc) {
        $totalSlots = 6;
        $doc['availability_status'] = ($doc['booked_today'] >= $totalSlots) ? 'busy' : 'available';
        $doc['free_slots'] = max(0, $totalSlots - $doc['booked_today']);
    }

    echo json_encode(["success" => true, "doctors" => $docs]);
    exit;
}

// ── BOOK VIDEO CONSULTATION ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'book') {
    function clean($v) { return htmlspecialchars(strip_tags(trim($v))); }

    $patient_name  = clean($_POST['patient_name']  ?? '');
    $email         = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $concern       = clean($_POST['concern']       ?? '');
    $consult_date  = clean($_POST['consult_date']  ?? '');
    $consult_time  = clean($_POST['consult_time']  ?? '');
    $doctor_id     = intval($_POST['doctor_id']    ?? 0);

    // Validate
    $errors = [];
    if (empty($patient_name))  $errors[] = "Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required.";
    if (empty($consult_date))  $errors[] = "Date required.";
    if (empty($consult_time))  $errors[] = "Time slot required.";
    if ($doctor_id <= 0)       $errors[] = "Invalid doctor.";
    if (!empty($consult_date) && $consult_date < date('Y-m-d')) $errors[] = "Date cannot be in the past.";

    if (!empty($errors)) {
        echo json_encode(["success" => false, "message" => implode(" ", $errors)]);
        exit;
    }

    // Check duplicate slot
    $chk = $pdo->prepare("SELECT id FROM video_consultations WHERE doctor_id=:d AND consult_date=:dt AND consult_time=:t AND status!='cancelled' LIMIT 1");
    $chk->execute([':d' => $doctor_id, ':dt' => $consult_date, ':t' => $consult_time]);
    if ($chk->rowCount() > 0) {
        echo json_encode(["success" => false, "message" => "This time slot is already booked. Please choose another."]);
        exit;
    }

    // Generate Google Meet ID
    $meetId  = 'tlc-' . substr(md5(uniqid()), 0, 4) . '-' . substr(md5(uniqid()), 4, 4);
    $meetUrl = "https://meet.google.com/" . $meetId;

    // Insert
    $stmt = $pdo->prepare(
        "INSERT INTO video_consultations (patient_name, email, concern, consult_date, consult_time, doctor_id, meet_link, status, created_at)
         VALUES (:name, :email, :concern, :date, :time, :doc, :meet, 'confirmed', NOW())"
    );
    $stmt->execute([
        ':name'    => $patient_name,
        ':email'   => $email,
        ':concern' => $concern,
        ':date'    => $consult_date,
        ':time'    => $consult_time,
        ':doc'     => $doctor_id,
        ':meet'    => $meetUrl,
    ]);

    echo json_encode([
        "success"  => true,
        "message"  => "Video consultation booked!",
        "meet_link"=> $meetUrl,
        "meet_id"  => $meetId,
        "id"       => $pdo->lastInsertId()
    ]);
    exit;
}

echo json_encode(["success" => false, "message" => "Invalid request."]);
?>
