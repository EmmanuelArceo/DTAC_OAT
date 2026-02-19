<?php
if (!ob_get_level()) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
include '../db.php';
include 'nav.php';
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'adviser'])) {
    header('Location: ../login.php');
    exit;
}

// MASS APPROVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mass_approve_ids'])) {
    $ids = array_filter(array_map('intval', explode(',', $_POST['mass_approve_ids'])));
    $multiplier = isset($_POST['mass_multiplier']) ? floatval($_POST['mass_multiplier']) : 1;
    if ($multiplier < 1) $multiplier = 1;
    $successCount = 0; $failCount = 0;
    foreach ($ids as $report_id) {
        $stmt = $oat->prepare("SELECT student_id, ot_hours, ot_date, approved FROM ot_reports WHERE id = ?");
        $stmt->bind_param("i", $report_id); $stmt->execute();
        $rep = $stmt->get_result()->fetch_assoc();
        if (!$rep || $rep['approved'] == 1) { $failCount++; continue; }
        $student_id = (int)$rep['student_id'];
        $approved_hours = (float)$rep['ot_hours'];
        $date = $rep['ot_date'];
        if (strpos($date, '/') !== false) {
            $dt = DateTime::createFromFormat('d/m/y', $date);
            if ($dt && $dt->format('Y') == '2012') $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
            $date = $dt ? $dt->format('Y-m-d') : $date;
        }
        $oat->begin_transaction();
        try {
            $mult = $multiplier; $rid = $report_id;
            $u = $oat->prepare("UPDATE ot_reports SET approved = 1, multiplier = ? WHERE id = ?");
            $u->bind_param("di", $mult, $rid); $u->execute();
            $ot_to_add = $approved_hours * $multiplier; $uid = $student_id;
            $v = $oat->prepare("UPDATE ojt_records SET ot_hours = COALESCE(ot_hours, 0) + ? WHERE user_id = ? AND date = ?");
            $v->bind_param("dis", $ot_to_add, $uid, $date); $v->execute();
            $oat->commit(); $successCount++;
        } catch (Exception $ex) { $oat->rollback(); $failCount++; }
    }
    $_SESSION['flash_success'] = "$successCount report(s) approved. $failCount failed.";
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$errors = []; $success = '';
if (isset($_SESSION['flash_success'])) { $success = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }
if (isset($_SESSION['flash_errors']))  { $errors  = $_SESSION['flash_errors'];  unset($_SESSION['flash_errors']);  }

// SINGLE APPROVE / REJECT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['report_id'])) {
    $report_id  = intval($_POST['report_id']);
    $action     = $_POST['action'];
    $multiplier = isset($_POST['multiplier']) ? floatval($_POST['multiplier']) : 1;
    if ($multiplier < 1) $multiplier = 1;

    if ($action === 'approve_requested') {
        $stmt = $oat->prepare("SELECT student_id, ot_hours, ot_date, approved FROM ot_reports WHERE id = ?");
        $stmt->bind_param("i", $report_id); $stmt->execute();
        $rep = $stmt->get_result()->fetch_assoc();
        if (!$rep) { $errors[] = 'Report not found.'; }
        elseif ($rep['approved'] == 1) { $errors[] = 'Report is already approved.'; }
        else {
            $student_id = (int)$rep['student_id']; $approved_hours = (float)$rep['ot_hours'];
            $date = $rep['ot_date'];
            if (strpos($date, '/') !== false) {
                $dt = DateTime::createFromFormat('d/m/y', $date);
                if ($dt && $dt->format('Y') == '2012') $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
                $date = $dt ? $dt->format('Y-m-d') : $date;
            }
            $oat->begin_transaction();
            try {
                $mult = $multiplier; $rid = $report_id;
                $u = $oat->prepare("UPDATE ot_reports SET approved = 1, multiplier = ? WHERE id = ?");
                $u->bind_param("di", $mult, $rid); $u->execute();
                $ot_to_add = $approved_hours * $multiplier; $uid = $student_id; $dt2 = $date;
                $v = $oat->prepare("UPDATE ojt_records SET ot_hours = COALESCE(ot_hours,0) + ? WHERE user_id = ? AND date = ?");
                $v->bind_param("dis", $ot_to_add, $uid, $dt2); $v->execute();
                $success = $v->affected_rows === 0
                    ? 'Report approved. Note: no attendance record found for that date.'
                    : 'Report approved and OT hours added.';
                $oat->commit();
            } catch (Exception $ex) { $oat->rollback(); $errors[] = 'Failed to approve report.'; }
        }
    } elseif ($action === 'reject') {
        $chk = $oat->prepare("SELECT student_id, ot_hours, ot_date, approved, multiplier FROM ot_reports WHERE id = ?");
        $chk->bind_param("i", $report_id); $chk->execute();
        $cur = $chk->get_result()->fetch_assoc();
        if (!$cur) { $errors[] = 'Report not found.'; }
        else {
            $student_id = (int)$cur['student_id']; $hours = (float)$cur['ot_hours'];
            $orig_mult = (float)($cur['multiplier'] ?? 1); $date = $cur['ot_date'];
            if (strpos($date, '/') !== false) {
                $dt = DateTime::createFromFormat('d/m/y', $date);
                if ($dt && $dt->format('Y') == '2012') $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
                $date = $dt ? $dt->format('Y-m-d') : $date;
            }
            $oat->begin_transaction();
            try {
                if ((int)$cur['approved'] === 1 && $hours > 0) {
                    $ot_to_sub = $hours * $orig_mult; $uid2 = $student_id; $dt3 = $date;
                    $u = $oat->prepare("UPDATE ojt_records SET ot_hours = GREATEST(0, COALESCE(ot_hours,0) - ?) WHERE user_id = ? AND date = ?");
                    $u->bind_param("dis", $ot_to_sub, $uid2, $dt3); $u->execute();
                }
                $s = $oat->prepare("UPDATE ot_reports SET approved = -1 WHERE id = ?");
                $s->bind_param("i", $report_id); $s->execute();
                $oat->commit(); $success = 'Report rejected.';
            } catch (Exception $ex) { $oat->rollback(); $errors[] = 'Failed to reject report.'; }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['flash_success'] = $success;
    $_SESSION['flash_errors']  = $errors;
    $loc = strtok($_SERVER['REQUEST_URI'], '?');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirecting…</title>'
       . "<script>window.location.href=" . json_encode($loc) . ";</script>"
       . '</head><body></body></html>';
    exit;
}

// FETCH REPORTS
$current_admin_id = $_SESSION['user_id'];
$is_super_admin   = ($_SESSION['role'] ?? '') === 'super_admin';
if ($is_super_admin) {
    $stmt = $oat->prepare("
        SELECT otr.id, u.id as user_id, u.fname, u.lname, otr.ot_hours, otr.ot_type,
               otr.reported_time_in, otr.reported_time_out, otr.ot_date, otr.ot_reason,
               otr.approved, otr.multiplier
        FROM ot_reports otr JOIN users u ON otr.student_id = u.id
        ORDER BY otr.submitted_at DESC, otr.id DESC");
} else {
    $stmt = $oat->prepare("
        SELECT otr.id, u.id as user_id, u.fname, u.lname, otr.ot_hours, otr.ot_type,
               otr.reported_time_in, otr.reported_time_out, otr.ot_date, otr.ot_reason,
               otr.approved, otr.multiplier
        FROM ot_reports otr JOIN users u ON otr.student_id = u.id
        WHERE u.adviser_id = ?
        ORDER BY otr.submitted_at DESC, otr.id DESC");
    $stmt->bind_param("i", $current_admin_id);
}
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function isValidDbTime($s) {
    if (!$s) return false;
    return !in_array(trim($s), ['00:00:00','0000-00-00 00:00:00','1970-01-01 00:00:00'], true);
}

function getVerificationStatus($oat, $report) {
    $date = $report['ot_date'];
    if (strpos($date, '/') !== false) {
        $dt = DateTime::createFromFormat('d/m/y', $date);
        if ($dt && $dt->format('Y') == '2012') $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
        $date = $dt ? $dt->format('Y-m-d') : $date;
    }
    $stmt = $oat->prepare("SELECT time_in, time_out, time_in_policy, time_out_policy, ot_hours FROM ojt_records WHERE user_id = ? AND date = ?");
    $stmt->bind_param("is", $report['user_id'], $date); $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    if (!$record) return ['status'=>'no_record','actual_in'=>null,'actual_out'=>null,'policy_in'=>null,'policy_out'=>null,'explanation'=>'No attendance record found.'];
    $isVDT = fn($s) => $s && !in_array(trim($s),['00:00:00','0000-00-00 00:00:00','1970-01-01 00:00:00'],true);
    $fmt = function($s) {
      if (!$s) return null;
      if ($s instanceof DateTime) return $s->format('g:i A');
      return date('g:i A', strtotime($s));
    };
    $policyIn  = new DateTime($date . ' ' . $record['time_in_policy']);
    $policyOut = new DateTime($date . ' ' . $record['time_out_policy']);
    $actualIn  = $isVDT($record['time_in'])  ? new DateTime($date . ' ' . $record['time_in'])  : null;
    $actualOut = $isVDT($record['time_out']) ? new DateTime($date . ' ' . $record['time_out']) : null;
    $issues = [];
    if (!empty($report['reported_time_in'])) {
        $rIn = new DateTime($date . ' ' . $report['reported_time_in']);
        if ($rIn < $policyIn) {
            if (!$actualIn) $issues[] = 'No actual time-in to verify early arrival.';
            elseif ($actualIn > $rIn) $issues[] = 'Reported early but actual time-in was later.';
        }
    }
    if (!empty($report['reported_time_out'])) {
        $rOut = new DateTime($date . ' ' . $report['reported_time_out']);
        if ($rOut > $policyOut) {
            if (!$actualOut) $issues[] = 'No actual time-out to verify late departure.';
            elseif ($actualOut < $rOut) $issues[] = 'Reported late departure but actual time-out was earlier.';
        }
    }
    if (empty($report['reported_time_in']) && empty($report['reported_time_out'])) {
        if ($actualIn && $actualOut) {
            $early = max(0,($policyIn->getTimestamp()-$actualIn->getTimestamp())/3600);
            $late  = max(0,($actualOut->getTimestamp()-$policyOut->getTimestamp())/3600);
            $calc  = round($early+$late);
            if ($calc != $report['ot_hours']) $issues[] = "Reported {$report['ot_hours']}h but calculated {$calc}h.";
        } else $issues[] = 'Insufficient attendance data.';
    }
    return [
        'status'     => empty($issues) ? 'verified' : 'mismatch',
        'actual_in'  => $actualIn  ? $fmt($actualIn)  : null,
        'actual_out' => $actualOut ? $fmt($actualOut) : null,
        'policy_in'  => $policyIn  ? $fmt($policyIn)  : null,
        'policy_out' => $policyOut ? $fmt($policyOut) : null,
        'explanation'=> empty($issues) ? 'Attendance matches OT requirements.' : implode(' ',$issues),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>OT Reports — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>

:root {
  --sky:        #3b82f6;
  --sky-light:  #eff6ff;
  --sky-mid:    #bfdbfe;
  --green:      #22c55e;
  --green-l:    #dcfce7;
  --red:        #ef4444;
  --red-l:      #fee2e2;
  --amber:      #f59e0b;
  --amber-l:    #fef3c7;
  --slate-50:   #f8fafc;
  --slate-100:  #f1f5f9;
  --slate-200:  #e2e8f0;
  --slate-400:  #94a3b8;
  --slate-600:  #475569;
  --slate-800:  #1e293b;
  --slate-900:  #0f172a;
  --white:      #ffffff;
  --radius-sm:  10px;
  --radius-md:  16px;
  --radius-lg:  24px;
  --radius-xl:  32px;
  --shadow-sm:  0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md:  0 4px 16px rgba(0,0,0,.08);
  --shadow-lg:  0 12px 40px rgba(0,0,0,.10);
  --shadow-sky: 0 4px 20px rgba(59,130,246,.18);
}

* { margin:0; padding:0; box-sizing:border-box; }

body {
  font-family: 'Inter', sans-serif;
  background: var(--slate-100);
  color: var(--slate-800);
  min-height: 100vh;
  padding-bottom: 6rem;
}

/* ── Page background accent ── */
body::before {
  content:'';
  position:fixed; top:-120px; right:-120px;
  width:500px; height:500px;
  background: radial-gradient(circle, rgba(59,130,246,.12) 0%, transparent 70%);
  pointer-events:none; z-index:0;
}
body::after {
  content:'';
  position:fixed; bottom:-80px; left:-80px;
  width:400px; height:400px;
  background: radial-gradient(circle, rgba(34,197,94,.08) 0%, transparent 70%);
  pointer-events:none; z-index:0;
}

.page { max-width:1380px; margin:0 auto; padding:1.5rem; position:relative; z-index:1; }

/* ── Header ── */
.top-header {
  background: var(--white);
  border-radius: var(--radius-xl);
  padding: 1.75rem 2rem;
  margin-bottom: 1.5rem;
  box-shadow: var(--shadow-md);
  display: flex;
  align-items: center;
  gap: 1.25rem;
}
.header-icon {
  width: 52px; height: 52px;
  background: linear-gradient(135deg, var(--sky) 0%, #6366f1 100%);
  border-radius: 16px;
  display: flex; align-items:center; justify-content:center;
  color:#fff; font-size:1.4rem;
  flex-shrink:0;
  box-shadow: var(--shadow-sky);
}
.header-text h1 { font-size:1.5rem; font-weight:700; color:var(--slate-900); line-height:1.2; }
.header-text p  { font-size:.875rem; color:var(--slate-400); margin-top:2px; }

/* ── Layout ── */
.main-grid { display:flex; gap:1.25rem; align-items:flex-start; }

/* ── Sidebar ── */
.sidebar {
  width:256px; flex-shrink:0;
  background: var(--white);
  border-radius: var(--radius-lg);
  padding: 1.25rem;
  box-shadow: var(--shadow-md);
  position: sticky; top:1rem;
}
.sidebar-title {
  font-size:.7rem; font-weight:700; letter-spacing:.08em;
  text-transform:uppercase; color:var(--slate-400);
  margin-bottom:1rem;
}
.filter-group { margin-bottom:1rem; }
.filter-label { font-size:.8rem; font-weight:600; color:var(--slate-600); margin-bottom:.35rem; display:block; }

/* Pill select */
.pill-select {
  width:100%; padding:.5rem .85rem;
  border-radius:50px; border:1.5px solid var(--slate-200);
  background:var(--slate-50); color:var(--slate-800);
  font-family:'Inter',sans-serif; font-size:.85rem;
  appearance:none;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat:no-repeat; background-position:right .75rem center;
  padding-right:2rem;
  transition:border-color .15s, box-shadow .15s;
  cursor:pointer;
}
.pill-select:focus { outline:none; border-color:var(--sky); box-shadow:0 0 0 3px rgba(59,130,246,.12); }

/* Search input */
.search-input {
  width:100%; padding:.55rem 1rem .55rem 2.4rem;
  border-radius:50px; border:1.5px solid var(--slate-200);
  background:var(--slate-50); color:var(--slate-800);
  font-family:'Inter',sans-serif; font-size:.875rem;
  transition:border-color .15s, box-shadow .15s;
}
.search-input:focus { outline:none; border-color:var(--sky); box-shadow:0 0 0 3px rgba(59,130,246,.12); }
.search-wrap { position:relative; }
.search-wrap .bi-search { position:absolute; left:.85rem; top:50%; transform:translateY(-50%); color:var(--slate-400); font-size:.85rem; pointer-events:none; }

.sidebar-divider { border:none; border-top:1.5px dashed var(--slate-200); margin:1rem 0; }

/* Mass approve box */
.mass-panel {
  background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
  border:1.5px solid var(--sky-mid);
  border-radius: var(--radius-md);
  padding:.875rem;
}
.mass-panel-title { font-size:.8rem; font-weight:700; color:var(--sky); margin-bottom:.6rem; }

/* ── Pill buttons ── */
.btn-pill {
  border-radius:50px; padding:.45rem 1.1rem;
  font-family:'Inter',sans-serif; font-weight:600;
  font-size:.82rem; border:none; cursor:pointer;
  transition:all .15s;
  display:inline-flex; align-items:center; gap:.35rem;
}
.btn-pill-sky    { background:var(--sky); color:#fff; box-shadow:var(--shadow-sky); }
.btn-pill-sky:hover { background:#2563eb; transform:translateY(-1px); }
.btn-pill-green  { background:var(--green); color:#fff; box-shadow:0 4px 12px rgba(34,197,94,.25); }
.btn-pill-green:hover { background:#16a34a; transform:translateY(-1px); }
.btn-pill-red    { background:transparent; color:var(--red); border:1.5px solid var(--red); }
.btn-pill-red:hover  { background:var(--red-l); }
.btn-pill-ghost  { background:var(--slate-100); color:var(--slate-600); }
.btn-pill-ghost:hover { background:var(--slate-200); }
.btn-pill-sm { padding:.3rem .8rem; font-size:.78rem; }
.btn-pill-full { width:100%; justify-content:center; }

/* Multiplier input */
.mult-group {
  display:flex; align-items:center; gap:.5rem;
  margin-bottom:.6rem;
  flex-wrap: wrap;
}
.mult-label { font-size:.78rem; color:var(--slate-600); font-weight:600; white-space:nowrap; }
.mult-input {
  flex:1; padding:.4rem .7rem;
  flex-wrap: wrap;
  border-radius:50px; border:1.5px solid var(--slate-200);
  font-family:'Inter',sans-serif; font-size:.82rem;
  background:var(--white);
  width: 50px;
}
.mult-input:focus { outline:none; border-color:var(--sky); }

/* ── Content panel ── */
.content-panel {
  flex:1; min-width:0;
  background:var(--white);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  overflow:hidden;
}
.panel-topbar {
  padding:1.25rem 1.5rem;
  border-bottom:1.5px solid var(--slate-100);
  display:flex; justify-content:space-between;
  align-items:center; flex-wrap:wrap; gap:.75rem;
}
.panel-topbar h2 { font-size:1rem; font-weight:700; color:var(--slate-800); }

/* ── Status chips ── */
.chip {
  display:inline-flex; align-items:center; gap:.3rem;
  padding:.2rem .7rem; border-radius:50px;
  font-size:.72rem; font-weight:700; letter-spacing:.02em;
}
.chip-pending  { background:var(--amber-l);  color:#92400e; }
.chip-approved { background:var(--green-l);  color:#166534; }
.chip-rejected { background:var(--red-l);    color:#991b1b; }
.chip-verified { background:var(--green-l);  color:#166534; }
.chip-mismatch { background:var(--red-l);    color:#991b1b; }
.chip-norecord { background:var(--slate-100);color:var(--slate-600); }
.chip-early    { background:var(--amber-l);  color:#92400e; }
.chip-late     { background:var(--sky-light);color:#1e40af; }

/* ── DESKTOP TABLE ── */
.dt-table { width:100%; border-collapse:collapse; }
.dt-table thead th {
  background:var(--slate-50);
  color:var(--slate-400); font-size:.7rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.06em;
  padding:.85rem 1rem; border-bottom:1.5px solid var(--slate-100);
  white-space:nowrap;
}
.dt-table thead th:first-child { border-radius:var(--radius-sm) 0 0 0; }
.dt-table thead th:last-child  { border-radius:0 var(--radius-sm) 0 0; }
.dt-table tbody td { padding:.85rem 1rem; border-bottom:1px solid var(--slate-100); vertical-align:middle; font-size:.875rem; }
.dt-table tbody tr:last-child td { border-bottom:none; }
.dt-table tbody tr:hover td { background:var(--slate-50); }
.dt-table .student-name { font-weight:600; color:var(--slate-900); }
.dt-table .student-id   { font-size:.72rem; color:var(--slate-400); margin-top:1px; font-family:'Inter',sans-serif; }
.dt-table .hours-val    { font-family:'Inter',sans-serif; font-size:.9rem;  }
.dt-table .reason-cell  { max-width:160px; color:var(--slate-600); font-size:.8rem; }
.sortable { cursor:pointer; user-select:none; }
.sortable:hover { color:var(--sky); }
.sort-icon { margin-left:3px; opacity:.35; font-size:.65rem; }
.sortable.asc  .sort-icon::after { content:'▲'; opacity:1; }
.sortable.desc .sort-icon::after { content:'▼'; opacity:1; }
.sortable:not(.asc):not(.desc) .sort-icon::after { content:'⇅'; }
.verify-dot { width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:4px; }
.dot-ok { background:var(--green); }
.dot-err { background:var(--red); }
.dot-none { background:var(--slate-400); }

/* ── MOBILE CARDS ── */
.card-list { display:none; padding:1rem; }

.ot-card {
  background:var(--white);
  border:1.5px solid var(--slate-200);
  border-radius: var(--radius-md);
  margin-bottom:.875rem;
  overflow:hidden;
  box-shadow: var(--shadow-sm);
  transition: box-shadow .2s, transform .15s;
  cursor:pointer;
}
.ot-card:active { transform:scale(.99); box-shadow:var(--shadow-md); }
.ot-card-top {
  padding:.875rem 1rem .65rem;
  display:flex; justify-content:space-between; align-items:flex-start; gap:.5rem;
}
.ot-card-name { font-weight:700; font-size:.95rem; color:var(--slate-900); }
.ot-card-sub  { font-size:.75rem; color:var(--slate-400); margin-top:2px; font-family:'Inter',sans-serif; }
.ot-card-chips { display:flex; flex-direction:column; align-items:flex-end; gap:.3rem; flex-shrink:0; }
.ot-card-body {
  padding:.5rem 1rem .75rem;
  display:grid; grid-template-columns:1fr 1fr;
  gap:.4rem .75rem;
}
.ot-card-field { }
.ot-card-field-label { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--slate-400); }
.ot-card-field-val   { font-size:.83rem; color:var(--slate-800); font-weight:500; margin-top:1px; }
.ot-card-reason {
  padding:0 1rem .75rem;
  font-size:.8rem; color:var(--slate-600);
  background:var(--slate-50); margin:0 .5rem .5rem; border-radius:var(--radius-sm);
  padding:.5rem .75rem;
}
.ot-card-actions {
  padding:.65rem 1rem;
  border-top:1.5px solid var(--slate-100);
  display:flex; gap:.5rem; align-items:center;
}
.ot-card-actions .btn-pill { font-size:.78rem; padding:.35rem .85rem; }

/* ── Pagination ── */
.pagination-bar {
  padding:1rem 1.5rem;
  border-top:1.5px solid var(--slate-100);
  display:flex; justify-content:space-between;
  align-items:center; flex-wrap:wrap; gap:.5rem;
}
.pagination-bar .info { font-size:.8rem; color:var(--slate-400); }
.pg-btns { display:flex; gap:.35rem; }
.pg-btn {
  width:32px; height:32px; border-radius:50%; border:1.5px solid var(--slate-200);
  background:var(--white); color:var(--slate-600);
  font-size:.8rem; cursor:pointer; display:flex; align-items:center; justify-content:center;
  transition:all .15s;
}
.pg-btn:hover:not(:disabled) { border-color:var(--sky); color:var(--sky); }
.pg-btn:disabled { opacity:.35; cursor:default; }
.pg-btn.active { background:var(--sky); border-color:var(--sky); color:#fff; }

/* ── Empty state ── */
.empty-state { padding:4rem 1rem; text-align:center; }
.empty-state .empty-icon { font-size:3rem; color:var(--slate-200); margin-bottom:1rem; }
.empty-state p { color:var(--slate-400); font-size:.9rem; }

/* ── Alerts ── */
.toast-alert {
  border-radius: var(--radius-md); padding:.875rem 1.25rem;
  margin-bottom:1rem; display:flex; align-items:center; gap:.75rem;
  font-size:.875rem; font-weight:500;
  border:1.5px solid;
}
.toast-success { background:var(--green-l); border-color:#bbf7d0; color:#166534; }
.toast-danger  { background:var(--red-l);   border-color:#fecaca; color:#991b1b; }

/* ── FAB (mobile) ── */
.fab {
  display:none;
  position:fixed; bottom:1.5rem; right:1.5rem; z-index:1039;
  background:var(--sky); color:#fff;
  border:none; border-radius:50px;
  padding:.7rem 1.35rem; font-size:.875rem; font-weight:700;
  font-family:'Inter',sans-serif;
  box-shadow:var(--shadow-sky);
  align-items:center; gap:.4rem;
  cursor:pointer;
  transition:transform .15s, box-shadow .15s;
}
.fab:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(59,130,246,.35); }
.fab-badge {
  background:var(--red); color:#fff; border-radius:50px;
  padding:1px 7px; font-size:.65rem; font-weight:800;
  display:none;
}
.fab-badge.on { display:inline; }

/* ── Drawer (mobile) ── */
.drawer-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(15,23,42,.4); z-index:1040;
  opacity:0; transition:opacity .25s;
  backdrop-filter:blur(3px);
}
.drawer-overlay.open { display:block; opacity:1; }
.drawer {
  position:fixed; bottom:0; left:0; right:0; z-index:1041;
  background:var(--white);
  border-radius: var(--radius-xl) var(--radius-xl) 0 0;
  padding:1rem 1.25rem 2rem;
  transform:translateY(100%);
  transition:transform .3s cubic-bezier(.33,1,.68,1);
  max-height:88vh; overflow-y:auto;
}
.drawer.open { transform:translateY(0); }
.drawer-pill { width:36px;height:4px;border-radius:9px;background:var(--slate-200);margin:0 auto .875rem; }
.drawer-title { font-size:.9rem; font-weight:700; color:var(--slate-900); margin-bottom:1rem; }

/* ── Modals ── */
.modal-backdrop { display:none !important; }
.modal { z-index:2100 !important; }
.modal-content { border-radius:var(--radius-lg) !important; border:none; box-shadow:var(--shadow-lg); }
.modal-header { border-radius:var(--radius-lg) var(--radius-lg) 0 0 !important; background:var(--slate-50) !important; border-bottom:1.5px solid var(--slate-100) !important; padding:1.25rem 1.5rem; }
.modal-header .modal-title { font-size:1rem; font-weight:700; color:var(--slate-900); }
.modal-body { padding:1.5rem; }
.modal-footer { border-top:1.5px solid var(--slate-100); padding:1rem 1.5rem; border-radius:0 0 var(--radius-lg) var(--radius-lg) !important; background:var(--white); flex-wrap:wrap; gap:.5rem; }
.modal-detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:.5rem 1.5rem; }
.modal-detail-row { padding:.4rem 0; border-bottom:1px solid var(--slate-100); }
.modal-detail-row:last-child { border-bottom:none; }
.modal-detail-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--slate-400); }
.modal-detail-val   { font-size:.9rem; color:var(--slate-800); font-weight:500; margin-top:2px; }

/* ── Checkbox style ── */
input[type=checkbox] {
  width:16px; height:16px; border-radius:5px;
  accent-color:var(--sky); cursor:pointer;
}

/* ── Responsive ── */
@media(max-width:768px) {
  .page { padding:.875rem; }
  .top-header { padding:1.1rem 1.25rem; border-radius:var(--radius-lg); }
  .header-text h1 { font-size:1.2rem; }
  .header-icon { width:42px;height:42px;font-size:1.1rem;border-radius:12px; }
  .sidebar { display:none; }
  .fab { display:flex; }
  .card-list { display:block; }
  .dt-wrap  { display:none; }
  .panel-topbar { padding:1rem; }
  .pagination-bar { padding:.875rem 1rem; }
  .main-grid { flex-direction:column; }
  .content-panel { border-radius:var(--radius-md); }
}
@media(min-width:769px) {
  .drawer, .drawer-overlay, .fab { display:none !important; }
}
</style>
</head>
<body>

<!-- ── Drawer overlay ── -->
<div class="drawer-overlay" id="drawerOverlay"></div>

<!-- ── Filter drawer (mobile) ── -->
<div class="drawer" id="filterDrawer">
  <div class="drawer-pill"></div>
  <div class="drawer-title"><i class="bi bi-sliders me-1"></i>Filters & Search</div>
  <div class="filter-group">
    <label class="filter-label">Search</label>
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input id="mSearchBox" class="search-input" placeholder="Name, reason, date…">
    </div>
  </div>
  <div class="filter-group">
    <label class="filter-label">OT Type</label>
    <select id="mFilterType" class="pill-select">
      <option value="">All types</option>
      <option value="early">☀️ Early OT</option>
      <option value="late">🌙 Normal OT</option>
    </select>
  </div>
  <div class="filter-group">
    <label class="filter-label">Status</label>
    <select id="mFilterStatus" class="pill-select">
      <option value="">All statuses</option>
      <option value="pending" selected>Pending</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
    </select>
  </div>
  <div style="display:flex;gap:.5rem;margin-top:.5rem;">
    <button id="mClearBtn" class="btn-pill btn-pill-ghost btn-pill-full">Clear</button>
    <button id="mApplyBtn" class="btn-pill btn-pill-sky btn-pill-full">Apply Filters</button>
  </div>
  <!-- Mobile mass approve -->
  <div id="mMassPanel" style="display:none;margin-top:1rem;">
    <div class="sidebar-divider"></div>
    <div class="mass-panel">
      <div class="mass-panel-title"><i class="bi bi-check2-all"></i> Approve</div>
      <div class="mult-group">
        <span class="mult-label">Multiplier ×</span>
        <input type="number" id="mMassMult" class="mult-input" step="0.01" min="1" value="1">
      </div>
      <button id="mMassBtn" class="btn-pill btn-pill-green btn-pill-full btn-pill-sm">
        <i class="bi bi-check2"></i> Approve <span id="mMassCount"></span>
      </button>
    </div>
  </div>
</div>

<!-- ── FAB ── -->
<button class="fab" id="fabBtn">
  <i class="bi bi-sliders"></i> Filter
  <span class="fab-badge" id="fabBadge"></span>
</button>

<div class="page">
  <!-- Header -->
  <div class="top-header">
    <div class="header-icon"><i class="bi bi-clock-history"></i></div>
    <div class="header-text">
      <h1>OT Reports</h1>
      <p>Review &amp; verify submitted overtime reports</p>
    </div>
  </div>

  <!-- Alerts -->
  <?php if ($success): ?>
    <div class="toast-alert toast-success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="toast-alert toast-danger"><i class="bi bi-exclamation-triangle-fill"></i>
      <div><?php foreach($errors as $e) echo htmlspecialchars($e).'<br>'; ?></div>
    </div>
  <?php endif; ?>

  <div class="main-grid">
    <!-- ── Sidebar (desktop) ── -->
    <div class="sidebar">
      <div class="sidebar-title"><i class="bi bi-sliders me-1"></i>Filters</div>
      <div class="filter-group">
        <label class="filter-label">Search</label>
        <div class="search-wrap">
          <i class="bi bi-search"></i>
          <input id="searchBox" class="search-input" placeholder="Name, reason, date…">
        </div>
      </div>
      <div class="filter-group">
        <label class="filter-label">OT Type</label>
        <select id="filterType" class="pill-select">
          <option value="">All types</option>
          <option value="early">☀️ Early OT</option>
          <option value="late">🌙 Normal OT</option>
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">Status</label>
        <select id="filterStatus" class="pill-select">
          <option value="">All statuses</option>
          <option value="pending" selected>Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <button id="clearFilters" class="btn-pill btn-pill-ghost btn-pill-full btn-pill-sm">
        <i class="bi bi-x-circle"></i> Clear Filters
      </button>

      <div class="sidebar-divider"></div>

      <div id="massPanel" style="display:none;" class="mass-panel">
        <div class="mass-panel-title"><i class="bi bi-check2-all"></i>  Approve</div>
        <div class="mult-group">
          <span class="mult-label">Multiplier ×</span>
          <input type="number" id="massMult" class="mult-input" step="0.01" min="1" value="1">
        </div>
        <button id="massBtn" class="btn-pill btn-pill-green btn-pill-full btn-pill-sm">
          <i class="bi bi-check2"></i> Approve <span id="massCount"></span>
        </button>
      </div>
    </div>

    <!-- ── Main content ── -->
    <div class="content-panel">
      <div class="panel-topbar">
        <h2><i class="bi bi-list-ul me-1"></i>Reports List</h2>
        <div style="display:flex;gap:.5rem;align-items:center;">
          <span id="resultCount" style="font-size:.78rem;color:var(--slate-400);"></span>
        </div>
      </div>

      <?php if (empty($reports)): ?>
        <div class="empty-state">
          <div class="empty-icon"><i class="bi bi-inbox"></i></div>
          <p>No OT reports found.</p>
        </div>
      <?php else: ?>

        <!-- ══ DESKTOP TABLE ══ -->
        <div class="dt-wrap" style="overflow-x:auto;">
          <table class="dt-table" id="dtTable">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                <th>Student</th>
                <th class="sortable" id="dateCol" style="width:110px;">Date<span class="sort-icon"></span></th>
                <th style="width:110px;">Type</th>
                <th style="width:80px;">Hours</th>
                <th style="width:65px;">Mult.</th>
                <th>Reason</th>
                <th style="width:130px;">Verification</th>
                <th style="width:110px;">Status</th>
                <th style="width:90px;text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody id="dtBody">
            <?php foreach ($reports as $r):
              $v = getVerificationStatus($oat, $r);
              $tl = match($r['ot_type']??'') { 'early'=>'Early OT','late'=>'Normal OT',default=>ucfirst($r['ot_type']??'') };
              $mult = $r['multiplier'] ?: 1;
              $sk = $r['approved']==1 ? 'approved' : ($r['approved']==-1 ? 'rejected' : 'pending');
              $sd = $r['ot_date'];
              if(strpos($sd,'/')!==false){$p=DateTime::createFromFormat('d/m/y',$sd);if($p&&$p->format('Y')=='2012')$p->setDate(2026,$p->format('m'),$p->format('d'));$sd=$p?$p->format('Y-m-d'):$sd;}
              $vDot = $v['status']==='verified' ? 'dot-ok' : ($v['status']==='mismatch' ? 'dot-err' : 'dot-none');
              $vLabel = ['verified'=>'Verified','mismatch'=>'Mismatch','no_record'=>'No Record'][$v['status']] ?? 'Unknown';
            ?>
              <tr data-name="<?=htmlspecialchars(strtolower($r['fname'].' '.$r['lname']))?>"
                  data-reason="<?=htmlspecialchars(strtolower($r['ot_reason']))?>"
                  data-date="<?=htmlspecialchars($r['ot_date'])?>"
                  data-sortdate="<?=htmlspecialchars($sd)?>"
                  data-type="<?=htmlspecialchars($r['ot_type']??'')?>"
                  data-status="<?=$sk?>">
                <td><?php if($sk==='pending'): ?><input type="checkbox" class="ot-sel" value="<?=$r['id']?>"><?php endif; ?></td>
                <td>
                  <div class="student-name"><?=htmlspecialchars($r['fname'].' '.$r['lname'])?></div>
                  <div class="student-id">#<?=htmlspecialchars($r['user_id'])?></div>
                </td>
                <td style="font-family:'Inter',sans-serif;font-size:.82rem;"><?=htmlspecialchars($r['ot_date'])?></td>
                <td><span class="chip <?=$r['ot_type']==='early'?'chip-early':'chip-late'?>"><?=htmlspecialchars($tl)?></span></td>
                <td><span class="hours-val"><?=htmlspecialchars($r['ot_hours'])?>h</span></td>
                <td style="font-family:'Inter',sans-serif;font-size:.82rem;">×<?=htmlspecialchars($mult)?></td>
                <td class="reason-cell"><?=htmlspecialchars(mb_strimwidth($r['ot_reason'],0,50,'…'))?></td>
                <td>
                  <span style="display:flex;align-items:center;gap:3px;font-size:.78rem;font-weight:600;">
                    <span class="verify-dot <?=$vDot?>"></span><?=$vLabel?>
                  </span>
                  <div style="font-size:.7rem;color:var(--slate-400);margin-top:2px;">
                    In: <?=htmlspecialchars($v['actual_in']??'—')?> / Call: <?=htmlspecialchars($v['policy_in']??'—')?>
                  </div>
                </td>
                <td><span class="chip chip-<?=$sk?>"><?=ucfirst($sk)?></span></td>
                <td style="text-align:right;">
                  <button class="btn-pill btn-pill-ghost btn-pill-sm" data-bs-toggle="modal" data-bs-target="#modal-<?=$r['id']?>">
                    <i class="bi bi-eye"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- ══ MOBILE CARDS ══ -->
        <div class="card-list" id="cardList">
        <?php foreach ($reports as $r):
          $v = getVerificationStatus($oat, $r);
          $tl = match($r['ot_type']??'') { 'early'=>'Early OT','late'=>'Normal OT',default=>ucfirst($r['ot_type']??'') };
          $mult = $r['multiplier'] ?: 1;
          $sk = $r['approved']==1 ? 'approved' : ($r['approved']==-1 ? 'rejected' : 'pending');
          $sd = $r['ot_date'];
          if(strpos($sd,'/')!==false){$p=DateTime::createFromFormat('d/m/y',$sd);if($p&&$p->format('Y')=='2012')$p->setDate(2026,$p->format('m'),$p->format('d'));$sd=$p?$p->format('Y-m-d'):$sd;}
        ?>
          <div class="ot-card"
               data-name="<?=htmlspecialchars(strtolower($r['fname'].' '.$r['lname']))?>"
               data-reason="<?=htmlspecialchars(strtolower($r['ot_reason']))?>"
               data-date="<?=htmlspecialchars($r['ot_date'])?>"
               data-sortdate="<?=htmlspecialchars($sd)?>"
               data-type="<?=htmlspecialchars($r['ot_type']??'')?>"
               data-status="<?=$sk?>">
            <div class="ot-card-top">
              <div>
                <?php if($sk==='pending'): ?>
                  <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                    <input type="checkbox" class="ot-sel-m" value="<?=$r['id']?>">
                    <span class="ot-card-name"><?=htmlspecialchars($r['fname'].' '.$r['lname'])?></span>
                  </label>
                <?php else: ?>
                  <div class="ot-card-name"><?=htmlspecialchars($r['fname'].' '.$r['lname'])?></div>
                <?php endif; ?>
                <div class="ot-card-sub">#<?=htmlspecialchars($r['user_id'])?> &nbsp;·&nbsp; <?=htmlspecialchars($r['ot_date'])?></div>
              </div>
              <div class="ot-card-chips">
                <span class="chip chip-<?=$sk?>"><?=ucfirst($sk)?></span>
                <span class="chip <?=$r['ot_type']==='early'?'chip-early':'chip-late'?>"><?=htmlspecialchars($tl)?></span>
              </div>
            </div>
            <div class="ot-card-body">
              <div class="ot-card-field">
                <div class="ot-card-field-label">Hours</div>
                <div class="ot-card-field-val" style="font-family:'Inter',sans-serif;color:var(--sky);font-weight:700;"><?=htmlspecialchars($r['ot_hours'])?>h ×<?=htmlspecialchars($mult)?></div>
              </div>
              <div class="ot-card-field">
                <div class="ot-card-field-label">Verification</div>
                <div class="ot-card-field-val">
                  <?php
                  $vs = $v['status'];
                  $vc = $vs==='verified'?'chip-verified':($vs==='mismatch'?'chip-mismatch':'chip-norecord');
                  $vl = ['verified'=>'✓ Verified','mismatch'=>'✗ Mismatch','no_record'=>'— No Record'][$vs]??$vs;
                  ?>
                  <span class="chip <?=$vc?>" style="font-size:.68rem;"><?=$vl?></span>
                </div>
              </div>
              <div class="ot-card-field">
                <div class="ot-card-field-label">Call Time</div>
                <div class="ot-card-field-val"><?=htmlspecialchars($v['policy_in']??'—')?></div>
              </div>
              <div class="ot-card-field">
                <div class="ot-card-field-label">Actual In</div>
                <div class="ot-card-field-val"><?=htmlspecialchars($v['actual_in']??'—')?></div>
              </div>
            </div>
            <div class="ot-card-reason"><?=htmlspecialchars(mb_strimwidth($r['ot_reason'],0,80,'…'))?></div>
            <div class="ot-card-actions">
              <button class="btn-pill btn-pill-ghost btn-pill-sm" style="flex:1;" data-bs-toggle="modal" data-bs-target="#modal-<?=$r['id']?>">
                <i class="bi bi-eye"></i> Details
              </button>
              <?php if($sk==='pending'): ?>
              <form method="POST" style="display:contents;">
                <input type="hidden" name="report_id" value="<?=$r['id']?>">
                <input type="hidden" name="multiplier" value="1">
                <button type="submit" name="action" value="approve_requested" class="btn-pill btn-pill-green btn-pill-sm" style="flex:1;">
                  <i class="bi bi-check2"></i> Approve
                </button>
                <button type="submit" name="action" value="reject" class="btn-pill btn-pill-red btn-pill-sm" style="flex:0;padding:.35rem .65rem;">
                  <i class="bi bi-x"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
          <div id="cardEmpty" style="display:none;text-align:center;padding:3rem 1rem;color:var(--slate-400);">
            <i class="bi bi-search" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
            No reports match your filters.
          </div>
        </div>

        <!-- Pagination -->
        <div class="pagination-bar">
          <span class="info" id="pageInfo">Loading…</span>
          <div class="pg-btns">
            <button class="pg-btn" id="pgPrev" disabled><i class="bi bi-chevron-left"></i></button>
            <button class="pg-btn active" id="pg1">1</button>
            <button class="pg-btn" id="pgNext"><i class="bi bi-chevron-right"></i></button>
          </div>
        </div>

      <?php endif; ?>
    </div><!-- content-panel -->
  </div><!-- main-grid -->
</div><!-- page -->

<!-- ── Detail Modals ── -->
<?php foreach ($reports as $r):
  $v = getVerificationStatus($oat, $r);
  $tl = match($r['ot_type']??'') { 'early'=>'Early OT','late'=>'Normal OT',default=>ucfirst($r['ot_type']??'') };
  $sk = $r['approved']==1 ? 'approved' : ($r['approved']==-1 ? 'rejected' : 'pending');
?>
<div class="modal fade" id="modal-<?=$r['id']?>" data-bs-backdrop="false" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex align-items-center gap-2">
          <div style="width:36px;height:36px;background:linear-gradient(135deg,var(--sky),#6366f1);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;">
            <i class="bi bi-file-earmark-text"></i>
          </div>
          <div>
            <div class="modal-title"><?=htmlspecialchars($r['fname'].' '.$r['lname'])?></div>
            <div style="font-size:.72rem;color:var(--slate-400);">OT Report · <?=htmlspecialchars($r['ot_date'])?></div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
          <span class="chip chip-<?=$r['ot_type']==='early'?'early':'late'?>"><?=htmlspecialchars($tl)?></span>
          <span class="chip chip-<?=$sk?>"><?=ucfirst($sk)?></span>
          <?php
            $vs=$v['status'];
            $vc=$vs==='verified'?'chip-verified':($vs==='mismatch'?'chip-mismatch':'chip-norecord');
            $vl=['verified'=>'Verified','mismatch'=>'Mismatch','no_record'=>'No Record'][$vs]??$vs;
          ?>
          <span class="chip <?=$vc?>"><?=$vl?></span>
        </div>
        <div class="modal-detail-grid">
          <div class="modal-detail-row">
            <div class="modal-detail-label">Hours Requested</div>
            <div class="modal-detail-val" style="font-family:'Inter',sans-serif;font-size:1.05rem;"><?=htmlspecialchars($r['ot_hours'])?>h</div>
          </div>
          <div class="modal-detail-row">
            <div class="modal-detail-label">Multiplier</div>
            <div class="modal-detail-val" style="font-family:'Inter',sans-serif;">×<?=htmlspecialchars($r['multiplier']??1)?></div>
          </div>
          <div class="modal-detail-row">
            <div class="modal-detail-label">Time in Policy</div>
            <div class="modal-detail-val"><?php
              $pin = $v['policy_in'] ?? null;
              $pout = $v['policy_out'] ?? null;
              if ($pin && $pout) {
                echo htmlspecialchars($pin . ' - ' . $pout);
              } elseif ($pin) {
                echo htmlspecialchars($pin);
              } elseif ($pout) {
                echo htmlspecialchars($pout);
              } else {
                echo '—';
              }
            ?></div>
          </div>
          <div class="modal-detail-row">
            <div class="modal-detail-label">Actual Time In</div>
            <div class="modal-detail-val"><?=htmlspecialchars($v['actual_in']??'—')?></div>
          </div>
          <div class="modal-detail-row">
            <div class="modal-detail-label">Actual Time Out</div>
            <div class="modal-detail-val"><?php
              $aout = $v['actual_out'] ?? null;
              echo $aout ? htmlspecialchars($aout) : '--';
            ?></div>
          </div>
          <?php if($r['ot_type']!=='early'): ?>
          <div class="modal-detail-row">
            <div class="modal-detail-label">Call Out</div>
            <div class="modal-detail-val"><?=htmlspecialchars($v['policy_out']??'—')?></div>
          </div>
          <?php endif; ?>
        </div>
        <div style="margin-top:1rem;background:var(--slate-50);border-radius:var(--radius-sm);padding:.875rem;">
          <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--slate-400);margin-bottom:.4rem;">Reason</div>
          <div style="font-size:.88rem;color:var(--slate-800);"><?=nl2br(htmlspecialchars($r['ot_reason']))?></div>
        </div>
        <?php if(!empty($v['explanation'])): ?>
        <div style="margin-top:.75rem;padding:.75rem;background:<?=$v['status']==='verified'?'var(--green-l)':'var(--red-l)'?>;border-radius:var(--radius-sm);">
          <div style="font-size:.78rem;color:<?=$v['status']==='verified'?'#166534':'#991b1b'?>;">
            <i class="bi bi-<?=$v['status']==='verified'?'check-circle':'exclamation-triangle'?>"></i>
            <?=htmlspecialchars($v['explanation'])?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <form method="POST" style="display:flex;align-items:center;gap:.75rem;flex:1;flex-wrap:wrap;">
          <input type="hidden" name="report_id" value="<?=$r['id']?>">
          <div class="mult-group" style="margin:0;">
            <span class="mult-label">×</span>
            <input type="number" step="0.01" min="1" name="multiplier" class="mult-input" style="width:80px;" value="<?=htmlspecialchars($r['multiplier']??1)?>" required>
          </div>
          <div style="display:flex;gap:.5rem;margin-left:auto;">
            <button type="submit" name="action" value="approve_requested" class="btn-pill btn-pill-green btn-pill-sm" <?=$r['approved']==1?'disabled':''?>>
              <i class="bi bi-check2"></i> Approve
            </button>
            <button type="submit" name="action" value="reject" class="btn-pill btn-pill-red btn-pill-sm" <?=$r['approved']==-1?'disabled':''?>>
              <i class="bi bi-x"></i> Reject
            </button>
          </div>
        </form>
        <button type="button" class="btn-pill btn-pill-ghost btn-pill-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ════════════════════════════════════════════════════════
// SHARED STATE
// ════════════════════════════════════════════════════════
const S = { q:'', type:'', status:'pending', sort:null };

// ── Desktop table ────────────────────────────────────────
(function() {
  const table = document.getElementById('dtTable');
  if (!table) return;
  const tbody = table.querySelector('#dtBody');
  const rows  = Array.from(tbody.rows);
  const dateCol = document.getElementById('dateCol');
  const PER = 10; let page = 1, vis = [];

  function filtered() {
    return rows.filter(r =>
      (!S.q      || r.dataset.name.includes(S.q) || r.dataset.reason.includes(S.q) || r.dataset.date.includes(S.q)) &&
      (!S.type   || r.dataset.type   === S.type) &&
      (!S.status || r.dataset.status === S.status)
    );
  }
  function sorted(arr) {
    if (!S.sort) return arr;
    return [...arr].sort((a,b) => S.sort==='asc'
      ? a.dataset.sortdate.localeCompare(b.dataset.sortdate)
      : b.dataset.sortdate.localeCompare(a.dataset.sortdate));
  }
  function render() {
    vis = sorted(filtered()); page = 1; draw();
  }
  function draw() {
    const s=(page-1)*PER, e=s+PER;
    vis.forEach(r=>tbody.appendChild(r));
    rows.forEach(r=>r.style.display='none');
    vis.slice(s,e).forEach(r=>r.style.display='');
    const total=Math.max(1,Math.ceil(vis.length/PER));
    document.getElementById('pageInfo').textContent = vis.length
      ? `Showing ${s+1}–${Math.min(e,vis.length)} of ${vis.length} reports`
      : 'No reports match the current filter.';
    document.getElementById('resultCount').textContent = `${vis.length} result${vis.length!==1?'s':''}`;
    document.getElementById('pgPrev').disabled = page===1;
    document.getElementById('pgNext').disabled = page>=total;
  }
  dateCol.addEventListener('click',()=>{
    S.sort = S.sort===null?'asc':S.sort==='asc'?'desc':null;
    dateCol.classList.toggle('asc',  S.sort==='asc');
    dateCol.classList.toggle('desc', S.sort==='desc');
    render();
  });
  document.getElementById('pgPrev').addEventListener('click', e=>{e.preventDefault();if(page>1){page--;draw();}});
  document.getElementById('pgNext').addEventListener('click', e=>{e.preventDefault();page++;draw();});
  document.getElementById('pg1').addEventListener('click',   e=>{e.preventDefault();page=1;draw();});

  // desktop inputs
  document.getElementById('searchBox').addEventListener('input', e=>{S.q=e.target.value.trim().toLowerCase();syncM();render();});
  document.getElementById('filterType').addEventListener('change', e=>{S.type=e.target.value;syncM();render();});
  document.getElementById('filterStatus').addEventListener('change', e=>{S.status=e.target.value;syncM();updateFab();render();});
  document.getElementById('clearFilters').addEventListener('click',()=>{ resetState(); syncD(); syncM(); updateFab(); render(); applyCards(); });

  window._dtRender = render;
  window._dtDraw   = draw;

  // init
  document.getElementById('filterStatus').value='pending';
  render();
})();

// ── Mobile cards ─────────────────────────────────────────
function applyCards() {
  const cards = document.querySelectorAll('#cardList .ot-card');
  let vis=0;
  cards.forEach(c=>{
    const ok =
      (!S.q    || c.dataset.name.includes(S.q)||c.dataset.reason.includes(S.q)||c.dataset.date.includes(S.q)) &&
      (!S.type || c.dataset.type===S.type) &&
      (!S.status || c.dataset.status===S.status);
    c.style.display = ok?'':'none';
    if(ok)vis++;
  });
  const empty=document.getElementById('cardEmpty');
  if(empty) empty.style.display=vis===0?'':'none';
}

// ── Sync helpers ─────────────────────────────────────────
function syncM(){
  const set=(id,v)=>{const el=document.getElementById(id);if(el)el.value=v;};
  set('mSearchBox',S.q); set('mFilterType',S.type); set('mFilterStatus',S.status);
}
function syncD(){
  const set=(id,v)=>{const el=document.getElementById(id);if(el)el.value=v;};
  set('searchBox',S.q); set('filterType',S.type); set('filterStatus',S.status);
}
function resetState(){ S.q=''; S.type=''; S.status='pending'; S.sort=null; document.getElementById('dateCol')?.classList.remove('asc','desc'); }
function updateFab(){
  const badge=document.getElementById('fabBadge');if(!badge)return;
  const n=(S.type?1:0)+(S.status&&S.status!=='pending'?1:0)+(S.q?1:0);
  badge.textContent=n||''; badge.classList.toggle('on',n>0);
}

// ── Drawer ───────────────────────────────────────────────
(function(){
  const fab=document.getElementById('fabBtn');
  const overlay=document.getElementById('drawerOverlay');
  const drawer=document.getElementById('filterDrawer');
  const openD=()=>{overlay.classList.add('open');drawer.classList.add('open');document.body.style.overflow='hidden';};
  const closeD=()=>{overlay.classList.remove('open');drawer.classList.remove('open');document.body.style.overflow='';};
  fab?.addEventListener('click',openD);
  overlay?.addEventListener('click',closeD);
  document.getElementById('mApplyBtn')?.addEventListener('click',()=>{ applyFromDrawer(); closeD(); });
  document.getElementById('mClearBtn')?.addEventListener('click',()=>{ resetState(); syncD(); syncM(); updateFab(); if(window._dtRender)window._dtRender(); applyCards(); closeD(); });
  function applyFromDrawer(){
    S.q     = (document.getElementById('mSearchBox')?.value||'').trim().toLowerCase();
    S.type  = document.getElementById('mFilterType')?.value||'';
    S.status= document.getElementById('mFilterStatus')?.value||'pending';
    syncD(); updateFab();
    if(window._dtRender)window._dtRender();
    applyCards();
  }
  [['mSearchBox','input'],['mFilterType','change'],['mFilterStatus','change']].forEach(([id,ev])=>{
    document.getElementById(id)?.addEventListener(ev, applyFromDrawer);
  });
})();

// ── Mass approve (desktop) ───────────────────────────────
(function(){
  const chks=document.querySelectorAll('.ot-sel');
  const panel=document.getElementById('massPanel');
  const countEl=document.getElementById('massCount');
  const selAll=document.getElementById('selectAll');
  function upd(){
    const sel=Array.from(chks).filter(c=>c.checked);
    if(panel) panel.style.display=sel.length?'':'none';
    if(countEl) countEl.textContent=sel.length?`(${sel.length})`:'';
  }
  selAll?.addEventListener('change',()=>{
    document.querySelectorAll('#dtBody tr').forEach(tr=>{
      if(tr.style.display!=='none'){const cb=tr.querySelector('.ot-sel');if(cb)cb.checked=selAll.checked;}
    }); upd();
  });
  chks.forEach(c=>c.addEventListener('change',upd));
  document.getElementById('massBtn')?.addEventListener('click',()=>{
    massSubmit([...chks].filter(c=>c.checked).map(c=>c.value), document.getElementById('massMult')?.value||'1');
  });
  upd();
})();

// ── Mass approve (mobile) ────────────────────────────────
(function(){
  const chks=document.querySelectorAll('.ot-sel-m');
  const panel=document.getElementById('mMassPanel');
  const countEl=document.getElementById('mMassCount');
  function upd(){
    const sel=Array.from(chks).filter(c=>c.checked);
    if(panel) panel.style.display=sel.length?'':'none';
    if(countEl) countEl.textContent=sel.length?`(${sel.length})`:'';
  }
  chks.forEach(c=>c.addEventListener('change',upd));
  document.getElementById('mMassBtn')?.addEventListener('click',()=>{
    massSubmit([...chks].filter(c=>c.checked).map(c=>c.value), document.getElementById('mMassMult')?.value||'1');
  });
  upd();
})();

function massSubmit(ids, mult){
  if(!ids.length)return;
  const f=document.createElement('form'); f.method='POST'; f.style.display='none';
  const a=document.createElement('input'); a.name='mass_approve_ids'; a.value=ids.join(','); f.appendChild(a);
  const b=document.createElement('input'); b.name='mass_multiplier'; b.value=mult; f.appendChild(b);
  document.body.appendChild(f); f.submit();
}

// ── Auto-open modal ──────────────────────────────────────
(function(){
  const id=new URL(location.href).searchParams.get('open_ot');
  if(!id)return;
  const el=document.getElementById('modal-'+id);
  if(el)setTimeout(()=>new bootstrap.Modal(el).show(),300);
})();

// Init
applyCards();
updateFab();
</script>
</body>
</html>
<?php if (ob_get_level()) ob_end_flush(); ?>