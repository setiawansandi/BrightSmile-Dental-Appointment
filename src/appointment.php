<?php
// --- 1. SETUP ---
require_once __DIR__ . '/utils/bootstrap.php';

// ===== helper to check role =====
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_id = $is_logged_in ? (int) $_SESSION['user_id'] : null;
$is_doctor = false;
$doctor_fullname = '';
if ($is_logged_in) {
    try {
        $conn = db();
        $stmt = $conn->prepare("SELECT is_doctor, CONCAT_WS(' ', first_name, last_name) AS full_name FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        if ($res) {
            $is_doctor = ((int) $res['is_doctor'] === 1);
            $doctor_fullname = (string) ($res['full_name'] ?? '');
        }
    } catch (Exception $e) {
        $is_doctor = false;
    }
}

// --- 2. AVAILABILITY CHECK (API MODE) ---
if (isset($_GET['doctor']) && isset($_GET['date'])) {
    header('Content-Type: application/json');
    $doctor_id = (int) $_GET['doctor'];
    $date = $_GET['date'];
    $booked_times = [];
    try {
        $conn = db();
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
        $conn->close();
        echo json_encode($booked_times);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database query failed']);
    }
    exit;
}

// --- 3. FORM SUBMISSION (POST MODE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    if ($is_logged_in) {
        try {
            $conn = db();

            if ($is_doctor) {
                // doctor updates for a specific patient (must be provided)
                $patient_user_id = (int) ($_POST['patient_user_id'] ?? 0);
                if ($patient_user_id <= 0) {
                    header('Location: appointment.php?error=' . urlencode('patient_required'));
                    exit;
                }
                $doctor_user_id = $user_id; // force to current doctor
            } else {
                // patient books/updates for self; chooses doctor
                $patient_user_id = $user_id;
                $doctor_user_id = (int) ($_POST['doctor_id'] ?? 0);
            }

            $appt_date = $_POST['appt_date'];
            $appt_time = $_POST['appt_time'];

            if (!empty($_POST['update_id'])) {
                // UPDATE (allowed for both roles with ownership checks)
                $appointment_id_to_update = (int) $_POST['update_id'];
                if ($is_doctor) {
                    // doctor may update only their own appointment with that patient
                    $sql = "UPDATE appointments 
                            SET doctor_user_id = ?, appt_date = ?, appt_time = ?
                            WHERE id = ? AND doctor_user_id = ? AND patient_user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('issiii', $doctor_user_id, $appt_date, $appt_time, $appointment_id_to_update, $user_id, $patient_user_id);
                } else {
                    // patient may update their own appointment
                    $sql = "UPDATE appointments 
                            SET doctor_user_id = ?, appt_date = ?, appt_time = ?
                            WHERE id = ? AND patient_user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('issii', $doctor_user_id, $appt_date, $appt_time, $appointment_id_to_update, $patient_user_id);
                }
                $stmt->execute();
            } else {
                // INSERT: only patients can create here; doctors don't create on this page flow
                if ($is_doctor) {
                    header('Location: appointment.php?error=' . urlencode('doctor_cannot_create_here'));
                    exit;
                }
                $sql = "INSERT INTO appointments (patient_user_id, doctor_user_id, appt_date, appt_time, status) 
                        VALUES (?, ?, ?, ?, 'confirmed')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iiss', $patient_user_id, $doctor_user_id, $appt_date, $appt_time);
                $stmt->execute();
            }

            $stmt->close();

            // ===============================================
            // --- START: HELLOMAIL.PHP INTEGRATION ---
            // ===============================================
            // We send an email *after* the DB query is successful
            try {
                // 1. Get Patient Email and Name
                $stmt_user = $conn->prepare("SELECT email, first_name FROM users WHERE id = ?");
                $stmt_user->bind_param('i', $patient_user_id);
                $stmt_user->execute();
                $patient_data = $stmt_user->get_result()->fetch_assoc();
                $stmt_user->close();

                // 2. Get Doctor Name
                $actual_doctor_id = $is_doctor ? $user_id : $doctor_user_id;
                $stmt_doc = $conn->prepare("SELECT CONCAT_WS(' ', first_name, last_name) AS full_name FROM users WHERE id = ?");
                $stmt_doc->bind_param('i', $actual_doctor_id);
                $stmt_doc->execute();
                $doctor_data = $stmt_doc->get_result()->fetch_assoc();
                $stmt_doc->close();

                if ($patient_data && $doctor_data) {
                    $patient_email = $patient_data['email'];
                    $patient_name = $patient_data['first_name'];
                    $doctor_name = $doctor_data['full_name'];

                    // 3. Set dynamic subject/verb
                    $subject_action = $is_update ? 'Rescheduled' : 'Confirmed';
                    $message_action = $is_update ? 'rescheduled' : 'confirmed';

                    // 4. Format date/time for the email body
                    $pretty_date = (new DateTime($appt_date))->format('l, j F Y');
                    $pretty_time = (new DateTime($appt_time))->format('H:i A');

                    // 5. Build Mail components (from hellomail.php)
                    $from_email = 'f31ee@localhost'; // From hellomail.php
                    $to = 'f32ee@localhost';
                    $subject = "Your BrightSmile Appointment is $subject_action";
                    
                    // Build the message
                    $message =
                    "
                        Hello $patient_name,

                        This is to notify you that your appointment with Dr. $doctor_name has been $message_action.

                        New Details:
                        Date: $pretty_date
                        Time: $pretty_time

                        We look forward to seeing you.

                        - The BrightSmile Team
                    ";
                    
                    $headers = 'From: ' . $from_email . "\r\n" .
                               'Reply-To: ' . $from_email . "\r\n" .
                               'X-Mailer: PHP/' . phpversion();

                    // 6. Send mail (with -f flag for XAMPP)
                    mail($to, $subject, $message, $headers, '-f' . $from_email);
                }

            } catch (Exception $e) {
                // Fail silently. Mail failure should not block the user.
                // In a real app, you would log this error.
                // error_log('Mail failed to send: ' . $e->getMessage());
            }
            // ===============================================
            // --- END: HELLOMAIL.PHP INTEGRATION ---
            // ===============================================

            $conn->close();
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
$reschedule_mode = false;              // patient-side only
$appointment_to_reschedule = null;     // used by both
$doctor_total_appointments = 0;

// ===== DOCTOR: use appointmentId (REQUIRED). Always reschedule that appointment if valid.
$doctor_appointment_id = isset($_GET['appointmentId']) ? (int) $_GET['appointmentId'] : 0;
$doctor_has_resched = false;
$doctor_resched_patient_id = 0;

if ($is_logged_in) {
    try {
        $conn = db();

        // Count doctor appts for banner
        if ($is_doctor) {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM appointments WHERE doctor_user_id = ? AND `status` = 'confirmed'");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $doctor_total_appointments = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();
        }

        // PATIENT: explicit reschedule by query (kept)
        if (!$is_doctor && isset($_GET['reschedule']) && !empty($_GET['reschedule'])) {
            $reschedule_mode = true;
            $appt_id_to_reschedule = (int) $_GET['reschedule'];
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

        // PATIENT: upcoming list
        if (!$is_doctor && !$reschedule_mode) {
            $sql = "
                SELECT 
                    a.id AS appointment_id, a.appt_date, a.appt_time,
                    u.id AS doctor_id, u.first_name, u.last_name, u.avatar_url
                FROM appointments a
                JOIN doctors d ON a.doctor_user_id = d.user_id
                JOIN users u ON d.user_id = u.id
                WHERE a.patient_user_id = ?
                  AND a.status = 'confirmed'
                  AND a.appt_date >= CURDATE()
                ORDER BY a.appt_date, a.appt_time
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $upcoming_appointments = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // Doctors list (for patient flow and to render doctor's self card)
        if ((!$is_doctor && ($reschedule_mode || count($upcoming_appointments) == 0)) || $is_doctor) {
            $sql_doctors = "
                SELECT u.id, u.first_name, u.last_name, u.avatar_url, d.specialization
                FROM users u
                JOIN doctors d ON u.id = d.user_id
                WHERE u.is_doctor = 1
                ORDER BY u.first_name, u.last_name
            ";
            $res = $conn->query($sql_doctors);
            $all_doctors = $res->fetch_all(MYSQLI_ASSOC);
        }

        // ============================
        // DOCTOR: ALWAYS RESCHEDULE MODE WHEN appointmentId is present.
        // Load that appointment if it belongs to current doctor, is confirmed, and in the future.
        // ============================
        if ($is_doctor && $doctor_appointment_id > 0) {
            $sql_dr_resched = "
                SELECT *
                FROM appointments
                WHERE id = ?
                  AND doctor_user_id = ?
                  AND status = 'confirmed'
                  AND appt_date >= CURDATE()
                LIMIT 1
            ";
            $stmt = $conn->prepare($sql_dr_resched);
            $stmt->bind_param('ii', $doctor_appointment_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $appointment_to_reschedule = $res->fetch_assoc() ?: null;
            $stmt->close();

            if (!empty($appointment_to_reschedule)) {
                $doctor_has_resched = true;
                $doctor_resched_patient_id = (int) $appointment_to_reschedule['patient_user_id'];
            }
        }

        $conn->close();
    } catch (Exception $e) {
        if (isset($conn))
            $conn->close();
        die("Database error: " . $e->getMessage());
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
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>

<body>

    <?php include __DIR__ . '/components/navbar.php'; ?>

    <?php
    // --- START: INLINED VIEW LOGIC ---
    if (!$is_logged_in): ?>
        <section class="content-wrapper">
            <div class="login-container">
                <h2>Login Required</h2>
                <p>Please login to book or check an appointment.</p>
                <div class="gap"></div>
                <a href="auth.php" class="btn-base btn-login btn-login-appointment">Login</a>
            </div>
        </section>

    <?php elseif ($is_doctor): ?>
        <!-- ========================
         DOCTOR VIEW (ALWAYS RESCHEDULE when ?appointmentId is present)
         ======================== -->
        <main class="appointment-container">
            <h1 class="main-title">Book an <span>Appointment</span></h1>

            <?php if ($doctor_total_appointments > 0): ?>
                <section class="appointments-banner">
                    <div class="banner-text">
                        <h3>Your appointments</h3>
                        <p>You have <?php echo (int) $doctor_total_appointments; ?>
                            appointment<?php echo $doctor_total_appointments > 1 ? 's' : ''; ?> scheduled</p>
                    </div>
                    <a href="dashboard.php" class="btn-base btn-view-dash">View in Dashboard</a>
                </section>
            <?php endif; ?>

            <?php if ($doctor_appointment_id <= 0): ?>
                <section class="doctor-no-appointments-banner">
                    <div class="banner-text">
                        <h3>Open an appointment to reschedule</h3>
                        <p>Use the reschedule button from your dashboard (it will send you here).</p>
                    </div>
                </section>

            <?php elseif (!$doctor_has_resched): ?>
                <!-- Appointment not found/eligible for reschedule -->
                <section class="doctor-no-appointments-banner">
                    <div class="banner-text">
                        <h3>No appointment to reschedule</h3>
                        <p>This appointment is not found, not yours, cancelled, or in the past.</p>
                    </div>
                </section>

            <?php else:
                // render doctor's own card (locked to self) with PREFILLED date/time
                $preselected_doctor_id = $user_id;
                $preselected_doctor = null;
                foreach ($all_doctors as $doc) {
                    if ((int) $doc['id'] === (int) $preselected_doctor_id) {
                        $preselected_doctor = $doc;
                        break;
                    }
                }

                $preselected_date = $appointment_to_reschedule['appt_date'];
                $preselected_time = (new DateTime($appointment_to_reschedule['appt_time']))->format('H:i');
                ?>
                <form action="appointment.php" method="POST">
                    <!-- Context -->
                    <input type="hidden" name="patient_user_id" value="<?php echo (int) $doctor_resched_patient_id; ?>">
                    <input type="hidden" id="selected_doctor_id" name="doctor_id" value="<?php echo (int) $user_id; ?>" required>
                    <input type="hidden" name="update_id" value="<?php echo (int) $appointment_to_reschedule['id']; ?>">

                    <div class="booking-wrapper">
                        <aside class="booking-card doctor-selector has-selection">
                            <div class="card-header">
                                <h2>Doctor</h2>
                            </div>

                            <div class="dropdown-mock">
                                <div class="dropdown-content-wrapper">
                                    <?php
                                    $doc_avatar_path = 'assets/images/default-avatar.png';
                                    if ($preselected_doctor && $preselected_doctor['avatar_url']) {
                                        $base_path = str_replace('src/', '', htmlspecialchars($preselected_doctor['avatar_url']));
                                        $doc_avatar_path = $base_path . ((int) $preselected_doctor['id'] === 4 ? '.jpg' : '.png');
                                    }
                                    ?>
                                    <div class="doctor-item">
                                        <img src="<?php echo $doc_avatar_path; ?>"
                                            alt="Dr <?php echo htmlspecialchars($doctor_fullname ?: ($preselected_doctor['first_name'] ?? '')); ?>">
                                        <div class="doctor-info">
                                            <span class="doctor-name">
                                                Dr
                                                <?php echo htmlspecialchars($doctor_fullname ?: (($preselected_doctor['first_name'] ?? '') . ' ' . ($preselected_doctor['last_name'] ?? ''))); ?>
                                            </span>
                                            <?php if ($preselected_doctor): ?>
                                                <span
                                                    class="doctor-specialty"><?php echo htmlspecialchars($preselected_doctor['specialization']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="dropdown-icon">
                                    <img src="assets/icons/dropdown-arrow-white.svg" class="dropdown-arrow-icon" alt="">
                                </div>
                            </div>
                            <div class="doctor-list" style="display:none;"></div>
                        </aside>

                        <section class="booking-card schedule-selector">
                            <div class="card-header">
                                <h2>Select Date</h2>
                                <a class="logo" aria-label="BrightSmile home">
                                    <img src="assets/icons/logo.svg" alt="Logo" />
                                </a>
                            </div>

                            <div class="date-input-wrapper">
                                <input type="date" name="appt_date" id="appt_date_input"
                                    value="<?php echo htmlspecialchars($preselected_date); ?>" required>
                                <a class="date-icon" aria-label="Calendar Date"></a>
                            </div>

                            <div class="timeslot-selector">
                                <h3>Select Timeslot</h3>
                                <input type="hidden" id="selected_timeslot" name="appt_time"
                                    value="<?php echo htmlspecialchars($preselected_time); ?>" required>
                                <div class="timeslot-grid">
                                    <?php $timeslots = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00']; ?>
                                    <?php foreach ($timeslots as $slot): ?>
                                        <button type="button"
                                            class="timeslot-btn <?php echo ($slot === $preselected_time) ? 'selected' : ''; ?>">
                                            <?php echo $slot; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-controls">
                                <a href="dashboard.php" class="cancel-btn">Cancel</a>
                                <button type="submit" class="btn-base btn-book">Update Appointment</button>
                            </div>
                        </section>
                    </div>
                </form>
            <?php endif; ?>
        </main>

    <?php elseif (count($upcoming_appointments) > 0 && !$reschedule_mode): ?>
        <!-- ========================
         PATIENT VIEW — UPCOMING
         ======================== -->
        <main class="appointment-container">
            <h1 class="main-title">Book an <span>Appointment</span></h1>

            <section class="appointments-banner">
                <div class="banner-text">
                    <h3>Your upcoming appointments</h3>
                    <p>You have <?php echo count($upcoming_appointments); ?>
                        appointment<?php echo (count($upcoming_appointments) > 1) ? 's' : ''; ?> scheduled</p>
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
                        <img src="<?php echo $doc_avatar_path; ?>" alt="Dr <?php echo htmlspecialchars($appt['first_name']); ?>"
                            class="doctor-pic">
                        <div class="appointment-details">
                            <?php $date = new DateTime($appt['appt_date'] . ' ' . $appt['appt_time']); ?>
                            <span class="appointment-time"><?php echo $date->format('l, j F Y \a\t H:i'); ?></span>
                            <span class="doctor-name">Dr
                                <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?></span>
                        </div>
                        <a href="appointment.php?reschedule=<?php echo $appt['appointment_id']; ?>"
                            class="btn-base btn-reschedule">Reschedule</a>
                    </section>
                <?php endforeach; ?>
            </section>
        </main>

    <?php else:
        // PATIENT — BOOK / RESCHEDULE (cards)
        $is_rescheduling = ($reschedule_mode && $appointment_to_reschedule);
        $preselected_doctor_id = $is_rescheduling ? $appointment_to_reschedule['doctor_user_id'] : (isset($_GET['doctorId']) ? (int) $_GET['doctorId'] : null);
        $preselected_date = $is_rescheduling ? $appointment_to_reschedule['appt_date'] : '';
        $preselected_time = $is_rescheduling ? (new DateTime($appointment_to_reschedule['appt_time']))->format('H:i') : '09:00';

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
                                        <img src="<?php echo $doc_avatar_path; ?>"
                                            alt="Dr <?php echo htmlspecialchars($preselected_doctor['first_name']); ?>">
                                        <div class="doctor-info">
                                            <span class="doctor-name">Dr
                                                <?php echo htmlspecialchars($preselected_doctor['first_name'] . ' ' . $preselected_doctor['last_name']); ?></span>
                                            <span
                                                class="doctor-specialty"><?php echo htmlspecialchars($preselected_doctor['specialization']); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span>Choose your preferred doctor</span>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-icon">
                                <img src="assets/icons/dropdown-arrow-white.svg" class="dropdown-arrow-icon" alt="">
                            </div>
                        </div>

                        <input type="hidden" id="selected_doctor_id" name="doctor_id"
                            value="<?php echo $preselected_doctor ? $preselected_doctor['id'] : ''; ?>" required>

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
                                    <img src="<?php echo $doc_avatar_path; ?>"
                                        alt="Dr <?php echo htmlspecialchars($doctor['first_name']); ?>">
                                    <div class="doctor-info">
                                        <span class="doctor-name">Dr
                                            <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></span>
                                        <span
                                            class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialization']); ?></span>
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
                            <input type="date" name="appt_date" id="appt_date_input"
                                value="<?php echo htmlspecialchars($preselected_date); ?>" required>
                            <a class="date-icon" aria-label="Calendar Date"></a>
                        </div>

                        <div class="timeslot-selector">
                            <h3>Select Timeslot</h3>
                            <input type="hidden" id="selected_timeslot" name="appt_time"
                                value="<?php echo htmlspecialchars($preselected_time); ?>" required>
                            <div class="timeslot-grid">
                                <?php $timeslots = ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00']; ?>
                                <?php foreach ($timeslots as $slot): ?>
                                    <button type="button"
                                        class="timeslot-btn <?php echo ($slot === $preselected_time) ? 'selected' : ''; ?>">
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
    <?php endif; ?>

    <?php include __DIR__ . '/components/footer.php'; ?>

    <script src="js/appointment.js"></script>
</body>

</html>