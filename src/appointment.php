<?php
// --- 1. SETUP ---
require_once __DIR__ . '/utils/bootstrap.php'; 

// --- 2. NEW: AVAILABILITY CHECK (API MODE) ---
// Check if this is a fetch request for availability
if (isset($_GET['doctor']) && isset($_GET['date'])) {
    
    header('Content-Type: application/json');
    $doctor_id = (int)$_GET['doctor'];
    $date = $_GET['date'];
    $booked_times = [];

    try {
        $conn = db(); // Connect
        $sql = "SELECT appt_time FROM appointments 
                WHERE doctor_user_id = ? 
                AND appt_date = ? 
                AND status != 'cancelled'";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $doctor_id, $date);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $booked_times[] = (new DateTime($row['appt_time']))->format('H:i');
        }
        
        $stmt->close();
        $conn->close(); // Close
        
        echo json_encode($booked_times);

    } catch (Exception $e) {
        echo json_encode(['error' => 'Database query failed']);
    }
    
    exit; // Stop script here, only return JSON
}

// --- 3. FORM SUBMISSION (POST MODE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    
    if ($is_logged_in) {
        try {
            $conn = db(); // Connect
            $patient_user_id = $_SESSION['user_id'];
            $doctor_user_id = $_POST['doctor_id'];
            $appt_date = $_POST['appt_date'];
            $appt_time = $_POST['appt_time'];

            if (isset($_POST['update_id']) && !empty($_POST['update_id'])) {
                // UPDATE
                $appointment_id_to_update = $_POST['update_id'];
                $sql = "UPDATE appointments 
                        SET doctor_user_id = ?, appt_date = ?, appt_time = ?
                        WHERE id = ? AND patient_user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('issii', $doctor_user_id, $appt_date, $appt_time, $appointment_id_to_update, $patient_user_id);
                $stmt->execute();
            } else {
                // INSERT
                $sql = "INSERT INTO appointments (patient_user_id, doctor_user_id, appt_date, appt_time, status) 
                        VALUES (?, ?, ?, ?, 'confirmed')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iiss', $patient_user_id, $doctor_user_id, $appt_date, $appt_time);
                $stmt->execute();
            }
            
            $stmt->close();
            $conn->close(); // Close
            
            header('Location: appointment.php?success=booked');
            exit; 

        } catch (Exception $e) {
            header('Location: appointment.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header('Location: appointment.php?error=notloggedin');
        exit;
    }
}

// --- 4. PAGE LOAD (GET MODE) ---

$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

$all_doctors = [];
$upcoming_appointments = [];
$reschedule_mode = false;
$appointment_to_reschedule = null;

if ($is_logged_in) {
    try {
        $conn = db(); // Connect
        
        if (isset($_GET['reschedule']) && !empty($_GET['reschedule'])) {
            $reschedule_mode = true;
            $appt_id_to_reschedule = $_GET['reschedule'];

            $sql_reschedule = "SELECT * FROM appointments WHERE id = ? AND patient_user_id = ?";
            $stmt = $conn->prepare($sql_reschedule);
            $stmt->bind_param('ii', $appt_id_to_reschedule, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $appointment_to_reschedule = $res->fetch_assoc();
            $stmt->close();

            if (!$appointment_to_reschedule) {
                $reschedule_mode = false;
            }
        }

        if (!$reschedule_mode) {
            $sql = "
                SELECT 
                    a.id AS appointment_id, a.appt_date, a.appt_time,
                    u.id AS doctor_id, u.first_name, u.last_name, u.avatar_url
                FROM appointments a
                JOIN doctors d ON a.doctor_user_id = d.user_id
                JOIN users u ON d.user_id = u.id
                WHERE a.patient_user_id = ?
                AND a.status = 'confirmed' AND a.appt_date >= CURDATE()
                ORDER BY a.appt_date, a.appt_time
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $upcoming_appointments = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        
        if ($reschedule_mode || count($upcoming_appointments) == 0) {
            $sql_doctors = "
                SELECT u.id, u.first_name, u.last_name, u.avatar_url, d.specialization
                FROM users u
                JOIN doctors d ON u.id = d.user_id
                WHERE u.is_doctor = 1
                ORDER BY u.first_name, u.last_name
            ";
            $res = $conn->query($sql_doctors);
            $all_doctors = $res->fetch_all(MYSQLI_ASSOC);
            
            $preselected_doctor_id = isset($_GET['doctorId']) ? (int)$_GET['doctorId'] : null;
        }

        $conn->close(); // Close

    } catch (Exception $e) {
        if (isset($conn)) $conn->close(); // Close on error
        die("Database error: ". $e->getMessage());
    }
}
// --- 5. HTML RENDERING ---
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Book an Appointment - BrightSmile</title>
    
    <link rel="stylesheet" href="css/appointment.css" />
    <link rel="stylesheet" href="css/root.css" /> 
    
    <link
      href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap"
      rel="stylesheet"
    />
    <link
      href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
  </head>
  <body>

<?php
include __DIR__ . '/components/navbar.php';

// --- START: INLINED VIEW LOGIC ---

if (!$is_logged_in) {
?>
    <section class="content-wrapper">
        <div class="login-container">
            <h2>Login Required</h2>
            <p>Please login to book or check an appointment.</p>
            <div class="gap"></div>
            <a href="auth.php" class="btn-base btn-login btn-login-appointment">Login</a>
        </div>
    </section>
<?php
} else if (count($upcoming_appointments) > 0 && !$reschedule_mode) {
?>
    <main class="appointment-container">
    
        <h1 class="main-title">
            Book an <span>Appointment</span>
        </h1>

        <section class="appointments-banner">
            <div class="banner-text">
                <h3>Your upcoming appointments</h3>
                <p>You have <?php echo count($upcoming_appointments); ?> appointment<?php echo (count($upcoming_appointments) > 1) ? 's' : ''; ?> scheduled</p>
            </div>

            <?php foreach ($upcoming_appointments as $appt): ?>
                <section class="appointment-card">
                    <?php 
                        $doc_avatar_path = 'assets/images/default-avatar.png';
                        if ($appt['avatar_url']) {
                            $base_path = str_replace('src/', '', htmlspecialchars($appt['avatar_url']));
                            $doc_avatar_path = $base_path . ($appt['doctor_id'] == 4 ? '.jpg' : '.png');
                        }
                    ?>
                    <img src="<?php echo $doc_avatar_path; ?>" alt="Dr <?php echo htmlspecialchars($appt['first_name']); ?>" class="doctor-pic">
                    
                    <div class="appointment-details">
                        <?php
                            $date = new DateTime($appt['appt_date'] . ' ' . $appt['appt_time']);
                            $formatted_date = $date->format('l, j F Y \a\t H:i');
                        ?>
                        <span class="appointment-time"><?php echo $formatted_date; ?></span>
                        <span class="doctor-name">Dr <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?></span>
                    </div>
                    
                    <a href="appointment.php?reschedule=<?php echo $appt['appointment_id']; ?>" class="btn-base btn-reschedule">Reschedule</a>
                    
                </section>
            <?php endforeach; ?>
            
        </section>
    </main>
<?php
} else {
    // This covers both "no appointments" and "reschedule mode"
    $is_rescheduling = ($reschedule_mode && $appointment_to_reschedule);
    $preselected_doctor_id = $is_rescheduling ? $appointment_to_reschedule['doctor_user_id'] : (isset($_GET['doctorId']) ? (int)$_GET['doctorId'] : null);
    $preselected_date = $is_rescheduling ? $appointment_to_reschedule['appt_date'] : '';
    $preselected_time = $is_rescheduling ? (new DateTime($appointment_to_reschedule['appt_time']))->format('H:i') : '09:00';
?>
    <main class="appointment-container">
    
        <h1 class="main-title">
            <?php if ($is_rescheduling): ?>
                Reschedule <span>Appointment</span>
            <?php else: ?>
                Book an <span>Appointment</span>
            <?php endif; ?>
        </h1>

        <?php if (!$is_rescheduling): ?>
        <section class="no-appointments-banner">
            <div class="banner-text">
                <h3>You don't have any upcoming appointments</h3>
                <p>Book your visit now with our dental experts.</p>
                <a class="logo" aria-label="BrightSmile home">
                <img src="assets/icons/logo.svg" alt="Logo" />
                </a>
            </div>
        </section>
        <?php endif; ?>

        <form action="appointment.php" method="POST">
        
            <?php if ($is_rescheduling): ?>
                <input type="hidden" name="update_id" value="<?php echo $appointment_to_reschedule['id']; ?>">
            <?php endif; ?>

            <div class="booking-wrapper">
                
                <?php
                $preselected_doctor = null;
                if (isset($preselected_doctor_id)) {
                    foreach ($all_doctors as $doc) {
                        if ($doc['id'] == $preselected_doctor_id) {
                            $preselected_doctor = $doc;
                            break;
                        }
                    }
                }
                ?>

                <aside class="booking-card doctor-selector <?php echo $preselected_doctor ? 'has-selection' : ''; ?>">
                    <div class="card-header">
                        <h2>Select Doctor</h2>
                        <a href="doctors.html" class="btn-base btn-view-all">View All</a>
                    </div>

                    <div class="dropdown-mock">
                        <div class="dropdown-content-wrapper">
                            <?php if ($preselected_doctor): ?>
                                <?php 
                                    $doc_avatar_path = 'assets/images/default-avatar.png';
                                    if ($preselected_doctor['avatar_url']) {
                                        $base_path = str_replace('src/', '', htmlspecialchars($preselected_doctor['avatar_url']));
                                        $doc_avatar_path = $base_path . ($preselected_doctor['id'] == 4 ? '.jpg' : '.png');
                                    }
                                ?>
                                <div class="doctor-item">
                                    <img src="<?php echo $doc_avatar_path; ?>" alt="Dr <?php echo htmlspecialchars($preselected_doctor['first_name']); ?>">
                                    <div class="doctor-info">
                                        <span class="doctor-name">Dr <?php echo htmlspecialchars($preselected_doctor['first_name'] . ' ' . $preselected_doctor['last_name']); ?></span>
                                        <span class="doctor-specialty"><?php echo htmlspecialchars($preselected_doctor['specialization']); ?></span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span>Choose your preferred doctor</span>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-icon">
                            <img src="assets/icons/dropdown-arrow-white.svg" class="dropdown-arrow-icon"></img>
                        </div>
                    </div>

                    <input type="hidden" id="selected_doctor_id" name="doctor_id" value="<?php echo $preselected_doctor ? $preselected_doctor['id'] : ''; ?>" required>

                    <div class="doctor-list">
                        <?php foreach ($all_doctors as $doctor): ?>
                            <div class="doctor-item" data-doctor-id="<?php echo $doctor['id']; ?>">
                                <?php 
                                    $doc_avatar_path = 'assets/images/default-avatar.png';
                                    if ($doctor['avatar_url']) {
                                        $base_path = str_replace('src/', '', htmlspecialchars($doctor['avatar_url']));
                                        $doc_avatar_path = $base_path . ($doctor['id'] == 4 ? '.jpg' : '.png');
                                    }
                                ?>
                                <img src="<?php echo $doc_avatar_path; ?>" alt="Dr <?php echo htmlspecialchars($doctor['first_name']); ?>">
                                <div class="doctor-info">
                                    <span class="doctor-name">Dr <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></span>
                                    <span class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <section class="booking-card schedule-selector">
                    <div class="card-header">
                        <h2>Select Date</h2>
                        <a class="logo" aria-label="BrightSmile home">
                        <img src="assets/icons/logo.svg" alt="Logo" />
                        </a>
                    </div>

                    <div class="date-input-wrapper">
                        <input type="date" name="appt_date" id="appt_date_input" value="<?php echo htmlspecialchars($preselected_date); ?>" required>
                        <a class="date-icon" aria-label="Calendar Date">
                            <img src="assets/icons/benefit.svg" alt="Calendar Icon" />
                        </a>
                    </div>

                    <div class="timeslot-selector">
                        <h3>Select Timeslot</h3>
                        
                        <input type="hidden" id="selected_timeslot" name="appt_time" value="<?php echo htmlspecialchars($preselected_time); ?>" required>

                        <div class="timeslot-grid">
                            <?php 
                                $timeslots = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'];
                            ?>
                            <?php foreach ($timeslots as $slot): ?>
                                <button type="button" class="timeslot-btn 
                                    <?php echo ($slot === $preselected_time) ? 'selected' : ''; ?>
                                ">
                                    <?php echo $slot; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-controls">
                        <a href="appointment.php" class="cancel-btn">Cancel</a>
                        
                        <button type="submit" class="btn-base btn-book">
                            <?php echo $is_rescheduling ? 'Update Appointment' : 'Book Appointment'; ?>
                        </button>
                    </div>
                </section>
            </div>
        </form>
    </main>
<?php
}
// --- END: INLINED VIEW LOGIC ---


include __DIR__ . '/components/footer.php';
?>
  
  <script src="js/appointment.js"></script> 
  </body>
</html>