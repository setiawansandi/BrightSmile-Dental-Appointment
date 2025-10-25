<?php
// --- 1. SETUP & PROCESS POST REQUEST ---
// Use the same bootstrap file as navbar.php
// This will handle session_start() and provide the db() function.
// Adjust the path if your file structure is different.
require_once __DIR__ . '/utils/bootstrap.php'; 

// Get the MySQLi connection object from bootstrap
$conn = db();

// CHECK IF THIS IS A FORM SUBMISSION (POST REQUEST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    
    if ($is_logged_in) {
        try {
            $patient_user_id = $_SESSION['user_id'];
            $doctor_user_id = $_POST['doctor_id'];
            $appt_date = $_POST['appt_date'];
            $appt_time = $_POST['appt_time'];

            // --- CHECK IF THIS IS AN UPDATE (MySQLi) ---
            if (isset($_POST['update_id']) && !empty($_POST['update_id'])) {
                // This is an UPDATE (Reschedule)
                $appointment_id_to_update = $_POST['update_id'];

                $sql = "UPDATE appointments 
                        SET doctor_user_id = ?, appt_date = ?, appt_time = ?
                        WHERE id = ? AND patient_user_id = ?"; // Security check
                
                $stmt = $conn->prepare($sql);
                // MySQLi binds parameters with types (s=string, i=integer)
                $stmt->bind_param('issii', $doctor_user_id, $appt_date, $appt_time, $appointment_id_to_update, $patient_user_id);
                $stmt->execute();

            } else {
                // This is a new INSERT (New Booking)
                $sql = "INSERT INTO appointments (patient_user_id, doctor_user_id, appt_date, appt_time, status) 
                        VALUES (?, ?, ?, ?, 'confirmed')";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iiss', $patient_user_id, $doctor_user_id, $appt_date, $appt_time);
                $stmt->execute();
            }
            
            $stmt->close();
            
            // The redirect works for both cases!
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

// --- 2. DATA FETCHING & DISPLAY LOGIC (GET REQUEST) ---
// ... (Your testing logic can remain here) ...
// $_SESSION['user_id'] = 5; 

$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// We NO LONGER need $page_to_include
// We will use the variables directly

$all_doctors = [];
$upcoming_appointments = [];
$reschedule_mode = false;
$appointment_to_reschedule = null;

if ($is_logged_in) {
    // --- USER IS LOGGED IN ---
    try {
        // --- Check for Reschedule Mode FIRST (MySQLi) ---
        if (isset($_GET['reschedule']) && !empty($_GET['reschedule'])) {
            $reschedule_mode = true;
            $appt_id_to_reschedule = $_GET['reschedule'];

            $sql_reschedule = "SELECT * FROM appointments WHERE id = ? AND patient_user_id = ?";
            $stmt = $conn->prepare($sql_reschedule);
            $stmt->bind_param('ii', $appt_id_to_reschedule, $user_id);
            $stmt->execute();
            $res = $stmt->get_result(); // Get result object
            $appointment_to_reschedule = $res->fetch_assoc(); // Fetch as associative array
            $stmt->close();

            if (!$appointment_to_reschedule) {
                // Invalid reschedule ID or doesn't belong to user
                $reschedule_mode = false;
            }
        }

        // If not in reschedule mode, check for other appointments
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
        
        // If we are in reschedule mode OR have no appointments, we need the doctor list
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

    } catch (Exception $e) {
        die("Database error: ". $e->getMessage());
    }
}
$conn->close();

// --- 3. PAGE RENDERING ---

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
// Include the new navbar component
include __DIR__ . '/components/navbar.php';

// --- START: INLINED VIEW LOGIC ---

if (!$is_logged_in) {
    // === START: _view_login_required.php ===
?>
    <section class="content-wrapper">
        <div class="login-container">
            <h2>Login Required</h2>
            <p>Please login to book or check an appointment.</p>
            <div class="gap"></div>
            <a href="auth.php" class="btn-base btn-login btn-login-appointment">Login</a> <?php // Changed to auth.php to match navbar ?>
        </div>
    </section>
<?php
    // === END: _view_login_required.php ===

} else if (count($upcoming_appointments) > 0 && !$reschedule_mode) {
    // === START: _view_booking_booked.php ===
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
                            if ($appt['doctor_id'] == 4) { 
                                $doc_avatar_path = $base_path . '.jpg';
                            } else {
                                $doc_avatar_path = $base_path . '.png';
                            }
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
    // === END: _view_booking_booked.php ===

} else {
    // This covers both "no appointments" and "reschedule mode"
    // === START: _view_booking_none.php ===
    
    // Logic from top of _view_booking_none.php
    $is_rescheduling = ($reschedule_mode && $appointment_to_reschedule);
    $preselected_doctor_id = $is_rescheduling ? $appointment_to_reschedule['doctor_user_id'] : (isset($_GET['doctorId']) ? (int)$_GET['doctorId'] : null);
    $preselected_date = $is_rescheduling ? $appointment_to_reschedule['appt_date'] : '';
    $preselected_time = $is_rescheduling ? (new DateTime($appointment_to_reschedule['appt_time']))->format('H:i') : '09:00';
?>
    <main class="appointment-container">
    
        <h1 class="main-title">
            <?php // Change title if rescheduling ?>
            <?php if ($is_rescheduling): ?>
                Reschedule <span>Appointment</span>
            <?php else: ?>
                Book an <span>Appointment</span>
            <?php endif; ?>
        </h1>

        <?php // Hide the "no appointments" banner if we're rescheduling ?>
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
        
            <?php // --- NEW: Add hidden field if we are updating ---
            if ($is_rescheduling): ?>
                <input type="hidden" name="update_id" value="<?php echo $appointment_to_reschedule['id']; ?>">
            <?php endif; ?>

            <div class="booking-wrapper">
                
                <?php
                // Pre-selection logic for doctor
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
                        <input type="date" name="appt_date" value="<?php echo htmlspecialchars($preselected_date); ?>" required>
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
                                $disabled_slot = '14:00';
                            ?>
                            <?php foreach ($timeslots as $slot): ?>
                                <button type="button" 
                                        class="timeslot-btn 
                                            <?php echo ($slot === $preselected_time) ? 'selected' : ''; ?>
                                            <?php echo ($slot === $disabled_slot) ? 'disabled' : ''; ?>"
                                >
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
    // === END: _view_booking_none.php ===
}
// --- END: INLINED VIEW LOGIC ---


// Include the new footer component
include __DIR__ . '/components/footer.php';
?>
  
  <script src="js/appointment.js"></script> 
  </body>
</html>