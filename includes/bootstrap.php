<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
$pdo->exec("SET time_zone='" . date('P') . "'"); // keep MySQL NOW() in step with PHP timezone
} catch (PDOException $ex) { die('Database connection failed. Check config.php'); }

/* ---------- helpers ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url($p = '') { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect($p) { header('Location: ' . (preg_match('#^https?://#', $p) ? $p : url($p))); exit; }
function q($sql, $params = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($params); return $st; }
function row($sql, $params = []) { return q($sql, $params)->fetch() ?: null; }
function rows($sql, $params = []) { return q($sql, $params)->fetchAll(); }
function val($sql, $params = []) { $r = q($sql, $params)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }
function insert_id() { global $pdo; return (int)$pdo->lastInsertId(); }
function post($k, $d = '') { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function nullable($v) { return ($v === '' || $v === null) ? null : $v; }

function flash($msg = null, $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}
function csrf_token() { return $_SESSION['csrf'] ??= bin2hex(random_bytes(20)); }
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); die('Invalid or expired form token. Go back and reload the page.');
    }
}

/* ---------- auth ---------- */
function user() { return $_SESSION['user'] ?? null; }
function is_admin() { $u = user(); return $u && !in_array($u['role'], ['Employer', 'Candidate'], true); }
function is_candidate() { $u = user(); return $u && $u['role'] === 'Candidate'; }
function cand_id() { $u = user(); return $u && $u['role'] === 'Candidate' ? (int)$u['candidate_id'] : 0; }
function home_url($role) { return $role === 'Employer' ? 'employer/dashboard.php' : ($role === 'Candidate' ? 'candidate/dashboard.php' : 'admin/dashboard.php'); }
function require_candidate() { require_login(['Candidate']); $c = row('SELECT * FROM candidates WHERE id=?', [cand_id()]); if (!$c) { die('Candidate profile missing.'); } return $c; }
function can_manage_employers() { $u = user(); return $u && in_array($u['role'], ['SuperAdmin', 'EmpAdmin'], true); }
function require_login(array $roles = []) {
    $u = user();
    if (!$u) redirect('index.php');
    if ($roles && !in_array($u['role'], $roles, true)) { http_response_code(403); die('Access denied for your role.'); }
}
/** Employer ID the current page works on. Employers: their own. EmpAdmin/SuperAdmin: the employer chosen in the switcher. */
function emp_id() {
    $u = user();
    if ($u['role'] === 'Employer') return (int)$u['employer_id'];
    return (int)($_SESSION['acting_emp'] ?? 0);
}
function require_employer_context() {
    require_login(['Employer', 'EmpAdmin', 'SuperAdmin']);
    if (!emp_id()) { flash('Select an employer first to work on its jobs and candidates.', 'warning'); redirect('admin/employers.php'); }
    $e = row('SELECT * FROM employers WHERE id=?', [emp_id()]);
    if (!$e || $e['status'] !== 'Approved') { flash('Employer is not approved.', 'danger'); redirect(is_admin() ? 'admin/employers.php' : 'logout.php'); }
    return $e;
}

/* ---------- notifications (email + SMS outbox) ---------- */
function notify($recipient, $subject, $body, $rel_type = null, $rel_id = null, $channel = 'Email') {
    $status = 'Queued';
    if (!DEV_MODE && $channel === 'Email') {
        $ok = @mail($recipient, $subject, $body, 'From: ' . MAIL_FROM . "\r\nContent-Type: text/plain; charset=UTF-8");
        $status = $ok ? 'Sent' : 'Failed';
    }
    // SMS: plug your gateway (MSG91, Twilio, etc.) here when $channel === 'SMS'
    q('INSERT INTO notifications (channel,recipient,subject,body,related_type,related_id,status) VALUES (?,?,?,?,?,?,?)',
      [$channel, $recipient, $subject, $body, $rel_type, $rel_id, $status]);
}

/* ---------- OTP ---------- */
function send_otp($target, $purpose) {
    $otp = (string)random_int(100000, 999999);
    q('UPDATE otp_verifications SET used=1 WHERE target=? AND purpose=?', [$target, $purpose]);
    q('INSERT INTO otp_verifications (target,purpose,otp_hash,expires_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))',
      [$target, $purpose, password_hash($otp, PASSWORD_DEFAULT)]);
    notify($target, 'Your verification code', "Your " . APP_NAME . " OTP is $otp. Valid for 10 minutes.", 'otp', null, $purpose === 'mobile' ? 'SMS' : 'Email');
    return $otp;
}
function verify_otp($target, $purpose, $code) {
    $r = row('SELECT * FROM otp_verifications WHERE target=? AND purpose=? AND used=0 AND expires_at>NOW() ORDER BY id DESC LIMIT 1', [$target, $purpose]);
    if (!$r || $r['attempts'] >= 5) return false;
    q('UPDATE otp_verifications SET attempts=attempts+1 WHERE id=?', [$r['id']]);
    if (!password_verify($code, $r['otp_hash'])) return false;
    q('UPDATE otp_verifications SET used=1 WHERE id=?', [$r['id']]);
    return true;
}

/* ---------- screening (rule based; replace body with an AI API call if desired) ---------- */
function screen_application($app_id) {
    $a = row('SELECT a.id, j.skills js, j.exp_min, j.exp_max, c.skills cs, c.experience_years ex
              FROM applications a JOIN jobs j ON j.id=a.job_id JOIN candidates c ON c.id=a.candidate_id WHERE a.id=?', [$app_id]);
    if (!$a) return null;
    $norm = fn($s) => array_filter(array_map(fn($x) => strtolower(trim($x)), explode(',', (string)$s)));
    $req = $norm($a['js']); $have = $norm($a['cs']);
    $match = array_values(array_intersect($req, $have));
    $skill = $req ? count($match) / count($req) * 70 : 70;
    $ex = (float)$a['ex']; $min = (float)$a['exp_min']; $max = $a['exp_max'] !== null ? (float)$a['exp_max'] : 99;
    $expPts = ($ex >= $min && $ex <= $max) ? 30 : ($ex > $max ? 22 : max(0, 30 - ($min - $ex) * 10));
    $score = (int)round(min(100, $skill + $expPts));
    $missing = array_diff($req, $have);
    $notes = 'Matched: ' . ($match ? implode(', ', $match) : 'none') . '. Missing: ' . ($missing ? implode(', ', $missing) : 'none') . '. Experience ' . $ex . ' yrs vs ' . $min . '-' . ($max == 99 ? '+' : $max) . '.';
    q('UPDATE applications SET screening_score=?, screening_notes=? WHERE id=?', [$score, mb_substr($notes, 0, 500), $app_id]);
    return $score;
}

/* ---------- social media auto-post queue ---------- */
function queue_social_posts($job_id) {
    foreach (['LinkedIn', 'Facebook', 'Twitter/X'] as $p) {
        q('INSERT INTO social_posts (job_id,platform,status) VALUES (?,?,?)', [$job_id, $p, 'Queued']);
        // TODO: call each platform's API here, then UPDATE status='Posted', post_ref, posted_at (or 'Failed').
    }
}

const APP_STATUSES = ['Applied', 'Shortlisted', 'In Process', 'Selected', 'On Hold', 'Rejected', 'Offered', 'Offer Accepted', 'Offer Declined', 'Withdrawn'];
const LEVELS = [1 => 'Beginner', 2 => 'Basic', 3 => 'Intermediate', 4 => 'Advanced', 5 => 'Expert'];
function status_badge($s) {
    $m = ['Applied' => 'secondary', 'Shortlisted' => 'info', 'In Process' => 'primary', 'Selected' => 'success', 'On Hold' => 'warning', 'Rejected' => 'danger',
          'Offered' => 'success', 'Offer Accepted' => 'success', 'Offer Declined' => 'dark', 'Pending' => 'warning', 'Approved' => 'success',
          'Suspended' => 'dark', 'Draft' => 'secondary', 'Published' => 'success', 'Closed' => 'dark', 'Passed' => 'success', 'Failed' => 'danger',
          'Withdrawn' => 'dark', 'Scheduled' => 'primary', 'Completed' => 'success', 'Cancelled' => 'dark', 'Verified' => 'success'];
    return '<span class="badge text-bg-' . ($m[$s] ?? 'secondary') . '">' . e($s) . '</span>';
}

/* ================= SKILL TAXONOMY & GAP ANALYSIS ================= */
function skill_find($name) {
    $n = trim((string)$name); if ($n === '') return null;
    $id = val('SELECT id FROM skills WHERE name=?', [$n]);
    if (!$id) $id = val('SELECT skill_id FROM skill_aliases WHERE alias=?', [$n]);
    return $id ? (int)$id : null;
}
/** Find a skill by name/alias or create it (unapproved, so SkillsAdmin can review). */
function skill_get_or_create($name) {
    $n = mb_substr(trim((string)$name), 0, 80); if ($n === '') return null;
    $id = skill_find($n); if ($id) return $id;
    q('INSERT INTO skills (name,category,approved) VALUES (?,?,0)', [$n, 'Other']); return insert_id();
}
/** Mirror jobs.skills (comma list) into job_skills; keeps weights already set, removes dropped skills. */
function sync_job_skills($job_id) {
    $ids = [];
    foreach (explode(',', (string)val('SELECT skills FROM jobs WHERE id=?', [$job_id])) as $s) { $sid = skill_get_or_create($s); if ($sid) $ids[$sid] = 1; }
    foreach ($ids as $sid => $_) q('INSERT IGNORE INTO job_skills (job_id,skill_id) VALUES (?,?)', [$job_id, $sid]);
    if ($ids) q('DELETE FROM job_skills WHERE job_id=? AND skill_id NOT IN (' . implode(',', array_map('intval', array_keys($ids))) . ')', [$job_id]);
    else q('DELETE FROM job_skills WHERE job_id=?', [$job_id]);
}
/** Keep candidates.skills (CSV) in step with candidate_skills so employer screening/filters keep working. */
function refresh_candidate_skill_csv($cid) {
    $names = array_column(rows('SELECT s.name FROM candidate_skills cs JOIN skills s ON s.id=cs.skill_id WHERE cs.candidate_id=? ORDER BY cs.level DESC, s.name', [$cid]), 'name');
    q('UPDATE candidates SET skills=? WHERE id=?', [mb_substr(implode(',', $names), 0, 500), $cid]);
    foreach (rows("SELECT id FROM applications WHERE candidate_id=? AND status='Applied'", [$cid]) as $a) screen_application($a['id']);
}
/** Weighted match: sum(weight * min(have, need)/need) / sum(weight). Mandatory skills count 1.5x. */
function gap_analysis($cid, $jid, $extra = []) {
    $items = rows('SELECT s.id, s.name, js.weight, js.required_level rl, js.mandatory, COALESCE(cs.level,0) cl, COALESCE(cs.verified,0) verified
        FROM job_skills js JOIN skills s ON s.id=js.skill_id LEFT JOIN candidate_skills cs ON cs.skill_id=js.skill_id AND cs.candidate_id=?
        WHERE js.job_id=? ORDER BY js.mandatory DESC, js.weight DESC, s.name', [$cid, $jid]);
    if (!$items) return ['score' => null, 'items' => [], 'mandatory_missing' => []];
    $num = 0; $den = 0; $mm = [];
    foreach ($items as &$i) {
        $rl = max(1, (int)$i['rl']); $cl = max((int)$i['cl'], (int)($extra[$i['id']] ?? 0)); $w = (float)$i['weight'] * ($i['mandatory'] ? 1.5 : 1);
        $num += $w * min($cl, $rl) / $rl; $den += $w;
        $i['cl'] = $cl; $i['state'] = $cl >= $rl ? 'Strong' : ($cl > 0 ? 'Weak' : 'Missing'); $i['gap'] = max(0, $rl - $cl);
        if ($i['mandatory'] && $cl < $rl) $mm[] = $i['name'];
    } unset($i);
    return ['score' => (int)round($num / $den * 100), 'items' => $items, 'mandatory_missing' => $mm];
}
function save_gap_report($cid, $jid, $g) {
    if ($g['score'] === null) return;
    q('INSERT INTO gap_reports (candidate_id,job_id,match_score,details) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE match_score=VALUES(match_score), details=VALUES(details), created_at=NOW()',
      [$cid, $jid, $g['score'], json_encode($g['items'])]);
}
function match_badge($s) {
    if ($s === null) return '<span class="badge text-bg-secondary">No skill data</span>';
    return '<span class="badge text-bg-' . ($s >= 70 ? 'success' : ($s >= 40 ? 'warning' : 'danger')) . '">' . (int)$s . '% match</span>';
}
function skill_state_badge($st) { return '<span class="badge text-bg-' . ['Strong' => 'success', 'Weak' => 'warning', 'Missing' => 'danger'][$st] . '">' . e($st) . '</span>'; }
function level_name($l) { return LEVELS[(int)$l] ?? '-'; }
function profile_completeness($c, $skillCount) {
    $checks = [$c['phone'], $c['location'], $c['headline'], $c['education'], $c['summary'], $c['resume_path'], $c['current_company'] ?: $c['experience_years'] !== null, $skillCount >= 3];
    return (int)round(count(array_filter($checks)) / count($checks) * 100);
}

/* ================= SKILL TESTS ================= */
/** Score band -> verified level. 0 = not passed. */
function test_level($pct, $pass) { if ($pct < $pass) return 0; return $pct >= 90 ? 5 : ($pct >= 80 ? 4 : ($pct >= 70 ? 3 : 2)); }
/** Write a verified level to the candidate's skill unless their declared level is higher (then it is left alone). */
function award_test_skill($cid, $skill_id, $level) {
    if ($level < 1) return false;
    $cur = val('SELECT level FROM candidate_skills WHERE candidate_id=? AND skill_id=?', [$cid, $skill_id]);
    if ($cur !== null && $level < (int)$cur) return false;
    q("INSERT INTO candidate_skills (candidate_id,skill_id,level,source,verified) VALUES (?,?,?,'Test',1) ON DUPLICATE KEY UPDATE level=VALUES(level), source='Test', verified=1", [$cid, $skill_id, $level]);
    refresh_candidate_skill_csv($cid); return true;
}
