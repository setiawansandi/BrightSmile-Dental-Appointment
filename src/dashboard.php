<?php
require_once __DIR__ . '/utils/authguard.php';

/* ====== AUTH GUARD ====== */
if (empty($_SESSION['user_id'])) {
  redirect('/auth.php?login_required=1');
}
$userId = (int) $_SESSION['user_id'];

/* ====== DB CONNECTION ====== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = db();
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'"); // keep your timezone if needed

/* ====== HELPERS (page-local) ====== */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function badgeClass($status) {
  $k = strtolower((string)$status);
  return match ($k) {
    'confirmed' => 'badge badge--confirmed',
    'completed' => 'badge badge--completed',
    'cancelled' => 'badge badge--cancelled',
    default     => 'badge',
  };
}

/* ====== USER (PATIENT/DOCTOR) INFO ====== */
$sqlUser = "
  SELECT
      id,
      is_doctor,
      CONCAT_WS(' ', first_name, last_name) AS full_name,
      email,
      phone,
      DATE_FORMAT(dob, '%d/%m/%Y') AS dob_fmt,
      avatar_url
  FROM users
  WHERE id = ?
";
$stmt = $conn->prepare($sqlUser);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$me = $res->fetch_assoc() ?: [];
$stmt->close();

$isDoctor = !empty($me) && ((int)$me['is_doctor'] === 1);

/* ====== APPOINTMENT HISTORY (role-aware) ====== */
$appointments = [];

if ($isDoctor) {
  // Doctor view: show patients
  $sqlAppts = "
    SELECT
      a.id,
      DATE_FORMAT(a.appt_date, '%d/%m/%Y') AS date_fmt,
      DATE_FORMAT(a.appt_time, '%H:%i')    AS time_fmt,
      a.status,
      CONCAT_WS(' ', pu.first_name, pu.last_name) AS counterpart_name
    FROM appointments a
    JOIN users pu ON pu.id = a.patient_user_id
    WHERE a.doctor_user_id = ?
    ORDER BY a.appt_date DESC, a.appt_time DESC
  ";
} else {
  // Patient view: show doctors
  $sqlAppts = "
    SELECT
      a.id,
      DATE_FORMAT(a.appt_date, '%d/%m/%Y') AS date_fmt,
      DATE_FORMAT(a.appt_time, '%H:%i')    AS time_fmt,
      a.status,
      CONCAT_WS(' ', du.first_name, du.last_name) AS counterpart_name
    FROM appointments a
    JOIN users du ON du.id = a.doctor_user_id
    WHERE a.patient_user_id = ?
    ORDER BY a.appt_date DESC, a.appt_time DESC
  ";
}
$stmt = $conn->prepare($sqlAppts);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $appointments[] = $row;
}
$stmt->close();

$firstColHeader = $isDoctor ? 'Patient' : 'Doctor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Dashboard • BrightSmile</title>

  <link rel="stylesheet" href="css/root.css" />
  <link rel="stylesheet" href="css/dashboard.css" />
  <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet" />
</head>
<body>
  <?php require_once __DIR__ . '/components/navbar.php'; ?>
  <!-- DASHBOARD SECTION -->
  <section class="general dashboard">
    <h1 class="dash-title">My <span class="highlight">Dashboard</span></h1>

    <!-- User Information -->
    <div class="section-card patient-card">
      <p class="section-title"><?= $isDoctor ? 'Doctor Information' : 'Patient Information' ?></p>
      <div class="patient-grid">
        <div class="avatar">
          <?php if (!empty($me['avatar_url'])): ?>
            <img src="<?= e($me['avatar_url']) ?>" alt="Profile photo">
          <?php else: ?>
            <img src="assets/images/none.png" alt="" class="avatar-placeholder">
          <?php endif; ?>
        </div>

        <ul class="patient-fields">
          <li><span class="label">Name:</span> <span><?= e($me['full_name'] ?? '—') ?></span></li>
          <li><span class="label">Email:</span> <span><?= e($me['email'] ?? '—') ?></span></li>
          <li><span class="label">Phone:</span> <span><?= e($me['phone'] ?? '—') ?></span></li>
          <li><span class="label">Date of Birth:</span> <span><?= e($me['dob_fmt'] ?? '—') ?></span></li>
        </ul>

        <img src="assets/icons/logo.svg" alt="Logo" />
      </div>
    </div>

    <!-- Appointment History -->
    <div class="section-card history-card">
      <p class="section-title">Appointment History</p>

      <div class="table-wrap">
        <table class="appt-table">
          <colgroup>
            <col style="width:160px">
            <col style="width:120px">
            <col style="width:90px">
            <col style="width:120px">
            <col>
          </colgroup>
          <thead>
            <tr>
              <th><?= e($firstColHeader) ?></th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th class="actions-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$appointments): ?>
              <tr>
                <td colspan="5" class="empty-row">No appointments found.</td>
              </tr>
            <?php else: foreach ($appointments as $a): ?>
              <tr>
                <td><?= e($a['counterpart_name'] ?? '—') ?></td>
                <td><?= e($a['date_fmt']) ?></td>
                <td><?= e($a['time_fmt']) ?></td>
                <td><span class="<?= badgeClass($a['status']) ?>"><?= e(ucfirst($a['status'])) ?></span></td>
                <td class="actions">
                  <?php if (strtolower($a['status']) === 'confirmed'): ?>
                    <a class="btn-base btn-sm" href="/reschedule.php?id=<?= (int)$a['id'] ?>">Reschedule</a>
                  <?php else: ?>
                    <span class="muted">--</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-content">
      <div class="footer-left">
        <div class="footer-header">
          <img src="assets/icons/logo.svg" alt="Tooth Icon" class="footer-logo" />
          <div class="brand-name">BrightSmile</div>
        </div>
        <p>
          BrightSmile offers gentle, modern dental care with clear guidance and advanced technology.
          Comfortable visits, from checkups to cosmetic treatments.
        </p>
        <p class="copyright">© 2025 BrightSmile. All Rights Reserved.</p>
      </div>

      <div class="footer-right">
        <div class="footer-header">
          <div class="links-title">Quick Links</div>
        </div>
        <ul>
          <li><a href="index.html">Home</a></li>
          <li><a href="services.html">Services</a></li>
          <li><a href="doctors.html">Clinics</a></li>
          <li><a href="about.html">About</a></li>
          <li><a href="#">More</a></li>
        </ul>
      </div>
    </div>
  </footer>
</body>
</html>
