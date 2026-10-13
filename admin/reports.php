<?php
// EmpAdmin reports: Selected / Shortlisted / Rejected / On Hold, filter job-wise and date-wise, CSV export
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(['EmpAdmin', 'SuperAdmin']);
$emp = (int)($_GET['employer'] ?? 0); $job = (int)($_GET['job'] ?? 0); $st = $_GET['status'] ?? '';
$from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$sql = 'SELECT a.id, e.company_name, j.title job, c.name candidate, c.email, c.phone, c.experience_years, a.screening_score, a.status, a.applied_at, a.updated_at
        FROM applications a JOIN jobs j ON j.id=a.job_id JOIN employers e ON e.id=j.employer_id JOIN candidates c ON c.id=a.candidate_id WHERE 1';
$p = [];
if ($emp) { $sql .= ' AND e.id=?'; $p[] = $emp; }
if ($job) { $sql .= ' AND j.id=?'; $p[] = $job; }
$groups = ['Selected' => ['Selected', 'Offered', 'Offer Accepted'], 'Shortlisted' => ['Shortlisted', 'In Process'], 'Rejected' => ['Rejected'], 'On Hold' => ['On Hold']];
if (isset($groups[$st])) { $sql .= ' AND a.status IN (' . implode(',', array_fill(0, count($groups[$st]), '?')) . ')'; array_push($p, ...$groups[$st]); }
if ($from) { $sql .= ' AND DATE(a.updated_at)>=?'; $p[] = $from; }
if ($to) { $sql .= ' AND DATE(a.updated_at)<=?'; $p[] = $to; }
$data = rows($sql . ' ORDER BY a.updated_at DESC', $p);
if (isset($_GET['export'])) {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="candidates_' . date('Ymd_His') . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, ['Employer', 'Job', 'Candidate', 'Email', 'Phone', 'Experience', 'Screening', 'Status', 'Applied', 'Updated']);
    foreach ($data as $r) fputcsv($o, [$r['company_name'], $r['job'], $r['candidate'], $r['email'], $r['phone'], $r['experience_years'], $r['screening_score'], $r['status'], $r['applied_at'], $r['updated_at']]);
    exit;
}
$emps = rows('SELECT id,company_name FROM employers ORDER BY company_name');
$jobs = rows('SELECT id,title,employer_id FROM jobs ' . ($emp ? 'WHERE employer_id=' . $emp : '') . ' ORDER BY title');
page_header('Candidate Reports', 'reports'); ?>
<form class="row g-2 mb-3 align-items-end">
<div class="col-md-2"><label class="form-label small">Employer</label><select name="employer" class="form-select"><option value="0">All</option><?php foreach ($emps as $x): ?><option value="<?= $x['id'] ?>" <?= $emp == $x['id'] ? 'selected' : '' ?>><?= e($x['company_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label class="form-label small">Job</label><select name="job" class="form-select"><option value="0">All</option><?php foreach ($jobs as $x): ?><option value="<?= $x['id'] ?>" <?= $job == $x['id'] ? 'selected' : '' ?>><?= e($x['title']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label class="form-label small">Status</label><select name="status" class="form-select"><option value="">All</option><?php foreach (array_keys($groups) as $g): ?><option <?= $st === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label class="form-label small">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
<div class="col-md-2"><label class="form-label small">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
<div class="col-md-2 d-flex gap-2"><button class="btn btn-primary">Filter</button><button name="export" value="1" class="btn btn-success">Export CSV</button></div></form>
<div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle"><thead><tr><th>Employer</th><th>Job</th><th>Candidate</th><th>Exp</th><th>Score</th><th>Status</th><th>Updated</th></tr></thead>
<?php foreach ($data as $r): ?><tr><td><?= e($r['company_name']) ?></td><td><?= e($r['job']) ?></td><td><?= e($r['candidate']) ?><div class="small text-muted"><?= e($r['email']) ?></div></td><td><?= e($r['experience_years']) ?></td><td><?= e($r['screening_score']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= e($r['updated_at']) ?></td></tr>
<?php endforeach; if (!$data): ?><tr><td colspan="7" class="text-muted p-3">No records.</td></tr><?php endif; ?></table></div></div>
<p class="text-muted small mt-2"><?= count($data) ?> record(s). "Selected" includes Offered / Offer Accepted; "Shortlisted" includes In Process.</p>
<?php page_footer();
