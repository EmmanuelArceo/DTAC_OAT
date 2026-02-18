
<?php
include '../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'verify_selfie' || $action === 'unverify_selfie') {
        $new_status = $action === 'verify_selfie' ? 1 : 0;
        $stmt = $oat->prepare("UPDATE ojt_records SET selfie_verified = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $id);
        $stmt->execute();
        $stmt->close();
        // Refresh $rec for updated status
        $stmt = $oat->prepare("
            SELECT r.*, u.fname, u.lname, u.username, u.profile_img
            FROM ojt_records r
            JOIN users u ON u.id = r.user_id
            WHERE r.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $rec = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        // Output only the relevant HTML fragment for AJAX
        ob_start();
        ?>
        <div class="verify-status <?= $rec['selfie_verified'] ? 'verified' : 'unverified' ?>" id="verifyBadge">
          <i class="bi bi-<?= $rec['selfie_verified'] ? 'check-circle-fill' : 'hourglass-split' ?>"></i>
          <?= $rec['selfie_verified'] ? 'Selfie Verified' : 'Selfie Not Verified' ?>
        </div>
        <button id="verifyBtn" class="btn btn-sm <?= $rec['selfie_verified'] ? 'btn-outline-secondary' : 'btn-primary' ?>">
          <?= $rec['selfie_verified'] ? 'Unverify' : 'Verify' ?>
        </button>
        <div id="alert-area"><div class="alert alert-success">Status updated.</div></div>
        <?php
        echo ob_get_clean();
        exit;
    }
}



include '../db.php';
include 'nav.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'super_admin'])) {
    echo "<div class='container py-4'><div class='alert alert-danger'>Not authorized.</div></div>";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "<div class='container py-4'><div class='alert alert-danger'>Record not found.</div></div>";
    exit;
}

$stmt = $oat->prepare("
    SELECT r.*, u.fname, u.lname, u.username, u.profile_img
    FROM ojt_records r
    JOIN users u ON u.id = r.user_id
    WHERE r.id = ? LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec) {
    echo "<div class='container py-4'><div class='alert alert-danger'>Record not found.</div></div>";
    exit;
}

function fmtTimeCell($t) {
    if (empty($t) || $t === '00:00:00') return '--';
    $ts = strtotime($t);
    return $ts ? date('g:i A', $ts) : htmlspecialchars($t);
}

function resolveSelfieSrc(?string $val): ?string {
    $val = trim((string)$val);
    if ($val === '') return null;
    $maybe = json_decode($val, true);
    if (is_array($maybe)) {
        if (!empty($maybe['selfie'])) $val = $maybe['selfie'];
        elseif (!empty($maybe['path'])) $val = $maybe['path'];
    }
    if (stripos($val, 'data:') === 0) return $val;
    if (preg_match('#^https?://#i', $val) || strpos($val, '//') === 0) return $val;
    if (preg_match('#^[A-Za-z0-9+/=\s]+$#', $val) && strlen($val) > 100) {
        $clean = preg_replace('/\s+/', '', $val);
        $decoded = base64_decode($clean, true);
        if ($decoded !== false) {
            $mime = 'image/jpeg';
            if (function_exists('finfo_buffer')) {
                $f = finfo_open(FILEINFO_MIME_TYPE);
                $m = finfo_buffer($f, $decoded);
                finfo_close($f);
                if ($m) $mime = $m;
            }
            return 'data:' . $mime . ';base64,' . $clean;
        }
    }
    $candidates = [
        __DIR__ . '/../' . ltrim($val, '/\\'),
        __DIR__ . '/../uploads/' . ltrim($val, '/\\'),
        __DIR__ . '/../uploads/selfies/' . ltrim($val, '/\\'),
    ];
    foreach ($candidates as $p) {
        if (file_exists($p)) {
            $web = str_replace(DIRECTORY_SEPARATOR, '/', substr($p, strlen(__DIR__ . '/../')));
            return '../' . ltrim($web, '/');
        }
    }
    if (file_exists(__DIR__ . '/../' . ltrim($val, '/\\'))) {
        return '../' . ltrim($val, '/\\');
    }
    return null;
}

$imgSrc = resolveSelfieSrc($rec['selfie'] ?? null);

// profile avatar fallback
$avatar = '../uploads/noimg.png';
if (!empty($rec['profile_img']) && file_exists(__DIR__ . '/../' . $rec['profile_img'])) {
    $avatar = '../' . $rec['profile_img'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Verify DTR — <?= htmlspecialchars($rec['fname'] . ' ' . $rec['lname']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%); font-family: 'Inter', sans-serif; }
    .verify-main-card {
      max-width: 700px;
      margin: 48px auto;
      background: #fff;
      border-radius: 1.5rem;
      box-shadow: 0 8px 32px 0 rgba(60,178,204,0.13);
      border: 1px solid #e2e8f0;
      padding: 2.5rem 2rem;
    }
    .profile-header {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      margin-bottom: 2rem;
      flex-wrap: wrap;
    }
    .profile-avatar {
      width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
      border: 3px solid #4c8eb1; background: #fff;
      box-shadow: 0 4px 12px rgba(76,142,177,0.13);
    }
    .profile-info h2 { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.2rem; }
    .profile-info .username { color: #3cb2cc; font-weight: 500; }
    .profile-actions { margin-left: auto; display: flex; gap: 0.5rem; }
    .verify-status {
      display: inline-flex; align-items: center; gap: 0.5rem;
      font-size: 1rem; font-weight: 600; padding: 0.4rem 1.2rem;
      border-radius: 50px; margin-bottom: 1rem;
    }
    .verify-status.verified { background: #e6f9ef; color: #10b981; }
    .verify-status.unverified { background: #fef9e7; color: #f59e0b; }
    .section-title { font-size: 1.1rem; font-weight: 600; color: #4c8eb1; margin-bottom: 0.5rem; }
    .selfie-img {
      max-width: 220px; /* reduced size */
      width: 100%;
      border-radius: 1rem;
      border: 1px solid #e5e7eb;
      box-shadow: 0 8px 24px rgba(76,142,177,0.10);
      cursor: pointer;
      margin-bottom: 0.5rem;
      display: block;
      margin-left: auto;
      margin-right: auto;
    }
    .time-grid {
      display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
    }
    .time-box {
      flex: 1 1 110px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 1rem;
      padding: 1rem 0.5rem;
      text-align: center;
      min-width: 100px;
    }
    .time-label { color: #64748b; font-size: 0.92rem; }
    .time-value { font-weight: 700; font-size: 1.15rem; color: #0f172a; }
    .remarks-box {
      background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 1rem;
      padding: 1rem; color: #0f172a; min-height: 70px; margin-bottom: 1rem;
    }
    .verify-actions .btn { min-width: 110px; }
    @media (max-width: 600px) {
      .verify-main-card { padding: 1.2rem 0.5rem; }
      .profile-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
      .profile-actions { margin-left: 0; }
      .time-grid { flex-direction: column; gap: 0.7rem; }
    }
  </style>
</head>
<body>
  <main>
    <section class="verify-main-card">
      <div class="profile-header">
        <img src="<?= htmlspecialchars($avatar . '?t=' . time()) ?>" alt="<?= htmlspecialchars($rec['fname'] . ' ' . $rec['lname']) ?>" class="profile-avatar">
        <div class="profile-info">
          <h2><?= htmlspecialchars($rec['fname'] . ' ' . $rec['lname']) ?></h2>
          <div class="username">@<?= htmlspecialchars($rec['username']) ?> · <?= htmlspecialchars($rec['date']) ?></div>
        </div>
        <div class="profile-actions">
          <a href="dtrview.php?user_id=<?= (int)$rec['user_id'] ?>" class="btn btn-outline-secondary">Back to DTR</a>
          <button class="btn btn-light" onclick="history.back()">Close</button>
        </div>
      </div>

      <div id="alert-area"></div>

      <div class="verify-status <?= $rec['selfie_verified'] ? 'verified' : 'unverified' ?>" id="verifyBadge">
        <i class="bi bi-<?= $rec['selfie_verified'] ? 'check-circle-fill' : 'hourglass-split' ?>"></i>
        <?= $rec['selfie_verified'] ? 'Selfie Verified' : 'Selfie Not Verified' ?>
      </div>

      <div class="time-grid">
        <div class="time-box">
          <div class="time-label">Time In</div>
          <div class="time-value"><?= fmtTimeCell($rec['time_in']) ?></div>
        </div>
        <div class="time-box">
          <div class="time-label">Time Out</div>
          <div class="time-value"><?= fmtTimeCell($rec['time_out']) ?></div>
        </div>
        <div class="time-box">
          <div class="time-label">Overtime</div>
          <div class="time-value"><?= floor((float)($rec['ot_hours'] ?? 0)) ?> h</div>
        </div>
      </div>

      <div class="mb-4">
        <div class="section-title">Selfie</div>
        <?php if ($imgSrc): ?>
          <div class="d-flex flex-column align-items-center">
            <img id="selfiePreview" src="<?= htmlspecialchars($imgSrc) ?>" alt="Selfie" class="selfie-img mb-2">
            <div class="d-flex gap-2 verify-actions mb-2">
              <button id="verifyBtn" class="btn btn-sm <?= $rec['selfie_verified'] ? 'btn-outline-secondary' : 'btn-primary' ?>">
                <?= $rec['selfie_verified'] ? 'Unverify' : 'Verify' ?>
              </button>
            </div>
            <div class="small text-muted">Tap image to enlarge.</div>
          </div>
        <?php else: ?>
          <div class="text-muted">No selfie recorded for this session.</div>
        <?php endif; ?>
      </div>

      <div class="mb-4">
        <div class="section-title">Remarks</div>
        <div class="remarks-box" style="min-height:unset; font-size:0.97em; padding:0.6rem 1rem;"><?= nl2br(htmlspecialchars($rec['remarks'] ?? '—')) ?></div>
      </div>

      <?php
      // --- Calculation logic from dtrdetail.php ---
      $policy_time_in_str = $rec['time_in_policy'] ?? null;
      $policy_time_out_str = $rec['time_out_policy'] ?? null;
      $lunch_start = $rec['lunch_start'] ?? null;
      $lunch_end = $rec['lunch_end'] ?? null;

      $time_in = strtotime($rec['time_in']);
      $time_out = strtotime($rec['time_out']);
      $policy_time_in = strtotime($policy_time_in_str);
      $policy_time_out = strtotime($policy_time_out_str);

      $policy_duration = $policy_time_out - $policy_time_in;

      $lateness = $time_in - $policy_time_in;
        if ($lateness > 0) {
          $late_hours = floor($lateness / 3600);
          $late_minutes = floor(($lateness % 3600) / 60);
          $late_str = [];
          if ($late_hours > 0) $late_str[] = $late_hours . ' hour' . ($late_hours > 1 ? 's' : '');
          if ($late_minutes > 0) $late_str[] = $late_minutes . ' minute' . ($late_minutes > 1 ? 's' : '');
          $late_str = $late_str ? implode(' and ', $late_str) : 'less than a minute';

          if ($lateness >= 3600) {
            $count_start = strtotime(date('Y-m-d H:00:00', $time_in) . ' +1 hour');
            $count_start_hour = date('g:i A', $count_start);
            $late_note = "<span class='text-danger'><i class='bi bi-clock'></i> Late by $late_str, counted from next full hour (<b>counting starts at $count_start_hour</b>).</span>";
          } else {
            $count_start = $time_in;
            $late_note = "<span class='text-danger'><i class='bi bi-clock'></i> Late by $late_str.</span>";
          }
        } else {
          $count_start = $time_in;
          $late_note = "<span class='text-success'><i class='bi bi-check-circle'></i> On time.</span>";
        }

      // Only calculate regular hours if time_out is set
        if (!empty($rec['time_out']) && $rec['time_out'] !== '00:00:00') {
          $regular_end = $count_start + $policy_duration;
          $reg_hours = min($time_out, $regular_end) - $count_start;
          $reg_hours = $reg_hours / 3600;

          $lunch_start_ts = strtotime(date('Y-m-d', $count_start) . ' ' . $lunch_start);
          $lunch_end_ts = strtotime(date('Y-m-d', $count_start) . ' ' . $lunch_end);
          // Always check for overlap if any part of work session is within lunch
          if ($count_start < $lunch_end_ts && $time_out > $lunch_start_ts) {
            $deduct_start = max($count_start, $lunch_start_ts);
            $deduct_end = min($time_out, $lunch_end_ts);
            $overlap = max(0, $deduct_end - $deduct_start);
            if ($overlap > 0) {
              $lunch_note = "<span class='text-warning'><i class='bi bi-box-arrow-in-down'></i> Lunch break deducted: ";
              $lunch_note .= fmtTimeCell(date('H:i:s', $deduct_start)) . " – " . fmtTimeCell(date('H:i:s', $deduct_end));
              $lunch_note .= " (" . number_format($overlap/3600, 2) . " hour(s))</span>";
              $reg_hours -= $overlap / 3600;
            } else {
              $lunch_note = "<span class='text-success'><i class='bi bi-check-circle'></i> No lunch deduction.</span>";
            }
          } else {
            $lunch_note = "<span class='text-success'><i class='bi bi-check-circle'></i> No lunch deduction.</span>";
          }

          $ot_hours = (float)($rec['ot_hours'] ?? 0);
          $total_hours = max(0, max(0, $reg_hours) + $ot_hours); // Always add full OT hours
        } else {
          $reg_hours = 0;
          $lunch_note = "<span class='text-muted'><i class='bi bi-dash-circle'></i> Waiting for time out to compute lunch deduction.</span>";
          $ot_hours = (float)($rec['ot_hours'] ?? 0);
          $total_hours = $ot_hours;
        }
      ?>
      <div class="mb-4">
        <div class="section-title">Calculation Breakdown</div>
        <ul class="breakdown-list list-unstyled" style="font-size:0.97em;">
          <li><?= $late_note ?></li>
          <li><?= $lunch_note ?></li>
          <li><i class="bi bi-clock-history"></i> Regular hours counted: <strong><?= $reg_hours > 0 ? number_format($reg_hours, 2) . ' hour(s)' : '--' ?></strong></li>
          <li><i class="bi bi-plus-circle"></i> Overtime hours: <strong><?= $ot_hours ?> hour(s)</strong></li>
          <li><i class="bi bi-calculator"></i> <b>Total hours:</b> <span class="badge bg-info text-dark"><?= $total_hours ?> hour(s)</span></li>
        </ul>
      </div>

      <div>
        <div class="section-title">Session Details</div>
        <ul class="list-unstyled small text-muted mb-0">
            <li><strong>Policy In:</strong> <?= fmtTimeCell($rec['time_in_policy'] ?? '--') ?></li>
            <li><strong>Policy Out:</strong> <?= fmtTimeCell($rec['time_out_policy'] ?? '--') ?></li>
            <li><strong>Lunch:</strong> <?= fmtTimeCell($rec['lunch_start'] ?? '--') ?> — <?= fmtTimeCell($rec['lunch_end'] ?? '--') ?></li>
        </ul>
      </div>
    </section>

    <!-- Selfie Modal -->
    <div class="modal fade" id="selfieModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
          <div class="modal-body text-center p-3">
            <img id="selfieModalImg" src="" alt="Selfie full size" style="max-width:100%;height:auto;border-radius:10px;">
          </div>
          <div class="modal-footer">
            <a id="selfieModalDownload" class="btn btn-outline-primary" href="#" download="selfie.jpg">Download</a>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </main>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  (function(){
    // Selfie modal
    const preview = document.getElementById('selfiePreview');
    if (preview) {
      preview.addEventListener('click', () => {
        const src = preview.getAttribute('src');
        document.getElementById('selfieModalImg').src = src;
        document.getElementById('selfieModalDownload').href = src;
        new bootstrap.Modal(document.getElementById('selfieModal')).show();
      });
    }

    // AJAX verify/unverify
    let selfieVerified = <?= $rec['selfie_verified'] ? 'true' : 'false' ?>;
    const verifyBadge = document.getElementById('verifyBadge');
    const alertArea = document.getElementById('alert-area');

    function attachVerifyBtnHandler() {
      const verifyBtn = document.getElementById('verifyBtn');
      if (verifyBtn) {
        verifyBtn.addEventListener('click', function() {
          const action = selfieVerified ? 'unverify_selfie' : 'verify_selfie';
          verifyBtn.disabled = true;
          fetch(window.location.pathname + '?id=<?= (int)$rec['id'] ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=' + encodeURIComponent(action)
          })
          .then(r => r.text())
          .then(html => {
            // Parse returned HTML for new badge/button state and alert
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newBadge = doc.getElementById('verifyBadge');
            const newBtn = doc.getElementById('verifyBtn');
            const newAlert = doc.getElementById('alert-area');
            if (newBadge && verifyBadge) verifyBadge.outerHTML = newBadge.outerHTML;
            if (newBtn) {
              const oldBtn = document.getElementById('verifyBtn');
              if (oldBtn) oldBtn.outerHTML = newBtn.outerHTML;
              attachVerifyBtnHandler(); // Re-attach handler to new button
            }
            if (newAlert && alertArea) alertArea.innerHTML = newAlert.innerHTML;
            selfieVerified = !selfieVerified;
          })
          .catch(() => {
            alertArea.innerHTML = '<div class="alert alert-danger">Failed to update verification status.</div>';
          });
        });
      }
    }

    attachVerifyBtnHandler();
  })();
  </script>
</body>
</html>