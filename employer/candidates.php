<?php
// Published jobs listing -> candidates for a job with filters + bulk shortlist/screen
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$emp = require_employer_context(); $eid = $emp['id'];
$job = (int)($_GET['job'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $ids = array_map('intval', $_POST['sel'] ?? []); $act = post('act'); $n = 0;
    foreach ($ids as $aid) {
        $a = row('SELECT a.id, a.status, c.name, c.email, j.title FROM applications a JOIN jobs j ON j.id=a.job_id JOIN candidates c ON c.id=a.candidate_id WHERE a.id=? AND j.employer_id=?', [$aid, $eid]);
        if (!$a) continue;
        if ($act === 'screen') { screen_application($aid); $n++; }
        elseif (in_array($act, ['Shortlisted', 'Rejected', 'On Hold'], true)) {
            q('UPDATE applications SET status=? WHERE id=?', [$act, $aid]); $n++;
            notify($a['email'], "Application update - {$a['title']}", "Dear {$a['name']}, your application for {$a['title']} at {$emp['company_name']} is now: $act.", 'application', $aid);
        }
    }
    flash("$n application(s) updated."); redirect('employer/candidates.php' . ($job ? "?job=$job" : ''));
}
$jobs = rows("SELECT j.id,j.title,j.location,j.last_date,(SELECT COUNT(*) FROM applications WHERE job_id=j.id) apps FROM jobs j WHERE j.employer_id=? AND j.status='Published' ORDER BY j.published_at DESC", [$eid]);
page_header('Candidate Selection', 'cands');
if (!$job): ?>
<h6 class="text-muted">Published jobs</h6><div class="row g-3">
<?php foreach ($jobs as $j): ?><div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><h5><?= e($j['title']) ?></h5><div class="text-muted small mb-2"><?= e($j['location']) ?> | closes <?= e($j['last_date'] ?: '-') ?></div>
<div class="fs-4"><?= (int)$j['apps'] ?> <small class="fs-6 text-muted">applicants</small></div><a class="btn btn-brand mt-2" href="?job=<?= $j['id'] ?>">View candidates</a></div></div></div>
<?php endforeach; if (!$jobs): ?><div class="alert alert-info">No published jobs yet.</div><?php endif; ?></div>
<?php else:
    $jr = row('SELECT * FROM jobs WHERE id=? AND employer_id=?', [$job, $eid]);
    if (!$jr) { flash('Job not found.', 'danger'); redirect('employer/candidates.php'); }
    $st = $_GET['status'] ?? ''; $minexp = $_GET['minexp'] ?? ''; $skill = trim($_GET['skill'] ?? ''); $minscore = $_GET['minscore'] ?? ''; $loc = trim($_GET['loc'] ?? ''); $edu = trim($_GET['edu'] ?? '');
    $sql = 'SELECT a.*, c.name, c.email, c.phone, c.location, c.education, c.experience_years, c.skills FROM applications a JOIN candidates c ON c.id=a.candidate_id WHERE a.job_id=?'; $p = [$job];
    if ($st) { $sql .= ' AND a.status=?'; $p[] = $st; }
    if ($minexp !== '') { $sql .= ' AND c.experience_years>=?'; $p[] = (float)$minexp; }
    if ($minscore !== '') { $sql .= ' AND a.screening_score>=?'; $p[] = (int)$minscore; }
    if ($skill !== '') { $sql .= ' AND c.skills LIKE ?'; $p[] = "%$skill%"; }
    if ($loc !== '') { $sql .= ' AND c.location LIKE ?'; $p[] = "%$loc%"; }
    if ($edu !== '') { $sql .= ' AND c.education LIKE ?'; $p[] = "%$edu%"; }
    $list = rows($sql . ' ORDER BY a.screening_score IS NULL, a.screening_score DESC, a.applied_at DESC', $p); ?>
<a href="candidates.php" class="small">&larr; All jobs</a><h5 class="mt-1"><?= e($jr['title']) ?> <small class="text-muted">- <?= e($jr['skills']) ?></small></h5>
<form class="row g-2 mb-3"><input type="hidden" name="job" value="<?= $job ?>">
<div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">Any status</option><?php foreach (APP_STATUSES as $s): ?><option <?= $st === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><input name="skill" class="form-control form-control-sm" placeholder="Skill" value="<?= e($skill) ?>"></div>
<div class="col-md-1"><input name="minexp" type="number" step="0.5" class="form-control form-control-sm" placeholder="Min exp" value="<?= e($minexp) ?>"></div>
<div class="col-md-2"><input name="minscore" type="number" class="form-control form-control-sm" placeholder="Min screening %" value="<?= e($minscore) ?>"></div>
<div class="col-md-2"><input name="loc" class="form-control form-control-sm" placeholder="Location" value="<?= e($loc) ?>"></div>
<div class="col-md-2"><input name="edu" class="form-control form-control-sm" placeholder="Education" value="<?= e($edu) ?>"></div>
<div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Filter</button></div></form>
<form method="post"><?= csrf_field() ?>
<div class="mb-2 d-flex gap-2 flex-wrap"><button name="act" value="screen" class="btn btn-sm btn-outline-primary"><i class="bi bi-cpu"></i> Run screening</button>
<button name="act" value="Shortlisted" class="btn btn-sm btn-info">Shortlist</button><button name="act" value="On Hold" class="btn btn-sm btn-warning">On hold</button><button name="act" value="Rejected" class="btn btn-sm btn-danger">Reject</button></div>
<div class="card shadow-sm"><table class="table table-hover align-middle mb-0"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.sel').forEach(c=>c.checked=this.checked)"></th><th>Candidate</th><th>Exp</th><th>Education</th><th>Skills</th><th>Screening</th><th>Status</th><th></th></tr></thead>
<?php foreach ($list as $c): $sc = $c['screening_score']; ?><tr><td><input type="checkbox" class="sel" name="sel[]" value="<?= $c['id'] ?>"></td>
<td><?= e($c['name']) ?><div class="small text-muted"><?= e($c['email']) ?> | <?= e($c['location']) ?></div></td><td><?= e($c['experience_years']) ?> y</td><td><?= e($c['education']) ?></td><td class="small"><?= e($c['skills']) ?></td>
<td><?php if ($sc !== null): ?><div class="progress" style="width:90px" title="<?= e($c['screening_notes']) ?>"><div class="progress-bar <?= $sc >= 70 ? 'bg-success' : ($sc >= 40 ? 'bg-warning' : 'bg-danger') ?>" style="width:<?= (int)$sc ?>%"><?= (int)$sc ?>%</div></div><?php else: ?><span class="text-muted small">not screened</span><?php endif; ?></td>
<td><?= status_badge($c['status']) ?></td><td><a class="btn btn-sm btn-brand" href="candidate_view.php?id=<?= $c['id'] ?>">Open</a></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="8" class="text-muted p-3">No candidates match.</td></tr><?php endif; ?></table></div></form>
<?php endif; page_footer();
