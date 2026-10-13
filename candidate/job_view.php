<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id']; $id = (int)($_GET['id'] ?? 0);
$job = row("SELECT j.*, e.company_name, e.logo, e.city, e.about, e.website, e.email emp_email, e.brand_color FROM jobs j JOIN employers e ON e.id=j.employer_id WHERE j.id=? AND e.status='Approved' AND (j.status='Published' OR EXISTS (SELECT 1 FROM applications a WHERE a.job_id=j.id AND a.candidate_id=?))", [$id, $cid]);
if (!$job) { flash('Job not available.', 'danger'); redirect('candidate/jobs.php'); }
$applied = row('SELECT * FROM applications WHERE job_id=? AND candidate_id=?', [$id, $cid]);
$open = $job['status'] === 'Published' && (!$job['last_date'] || $job['last_date'] >= date('Y-m-d'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act');
    if ($act === 'apply') {
        if (!$open) flash('Applications for this job are closed.', 'danger');
        elseif ($applied) flash('You have already applied.', 'warning');
        elseif (!val('SELECT COUNT(*) FROM candidate_skills WHERE candidate_id=?', [$cid])) { flash('Add your skills before applying so the employer can see your match.', 'warning'); redirect('candidate/skills.php'); }
        else {
            q('INSERT INTO applications (job_id,candidate_id) VALUES (?,?)', [$id, $cid]); $aid = insert_id(); screen_application($aid);
            save_gap_report($cid, $id, gap_analysis($cid, $id));
            notify($job['emp_email'], "New application: {$c['name']} for {$job['title']}", "{$c['name']} applied for {$job['title']}. Open the Candidates page to review.", 'application', $aid);
            notify($c['email'], "Application received - {$job['title']}", "Dear {$c['name']}, we have received your application for {$job['title']} at {$job['company_name']}. You will be notified as it progresses.", 'application', $aid);
            flash('Application submitted.');
        }
    } elseif ($act === 'save') {
        if (val('SELECT COUNT(*) FROM saved_jobs WHERE candidate_id=? AND job_id=?', [$cid, $id])) q('DELETE FROM saved_jobs WHERE candidate_id=? AND job_id=?', [$cid, $id]);
        else q('INSERT INTO saved_jobs (candidate_id,job_id) VALUES (?,?)', [$cid, $id]);
    }
    redirect("candidate/job_view.php?id=$id");
}
$g = gap_analysis($cid, $id); $saved = val('SELECT COUNT(*) FROM saved_jobs WHERE candidate_id=? AND job_id=?', [$cid, $id]);
page_header($job['title'], 'cjobs'); ?>
<a href="jobs.php" class="small">&larr; Back to jobs</a>
<div class="row g-3 mt-1"><div class="col-md-8"><div class="card shadow-sm"><div class="card-body">
<div class="d-flex gap-3 align-items-center mb-2"><?php if ($job['logo']): ?><img src="<?= e(url($job['logo'])) ?>" height="50" class="rounded border p-1 bg-white"><?php endif; ?>
<div><h4 class="mb-0"><?= e($job['title']) ?></h4><div class="text-muted"><?= e($job['company_name']) ?> | <?= e($job['location']) ?> | <?= e($job['job_type']) ?></div></div></div>
<div class="mb-3"><span class="badge text-bg-light border">Exp <?= e($job['exp_min']) ?>-<?= e($job['exp_max'] ?? '+') ?> yrs</span>
<?php if ($job['salary_min']): ?><span class="badge text-bg-light border">Rs <?= number_format($job['salary_min']) ?> - <?= number_format($job['salary_max'] ?? $job['salary_min']) ?></span><?php endif; ?>
<span class="badge text-bg-light border"><?= (int)$job['vacancies'] ?> vacancy</span><?php if ($job['last_date']): ?><span class="badge text-bg-light border">Apply by <?= e($job['last_date']) ?></span><?php endif; ?></div>
<h6>Job description</h6><p><?= nl2br(e($job['description'])) ?></p>
<h6>Interview process</h6><p><?php foreach (rows('SELECT round_name FROM job_rounds WHERE job_id=? ORDER BY round_no', [$id]) as $k => $r) echo ($k ? ' &rarr; ' : '') . e($r['round_name']); ?></p>
<?php if ($job['about']): ?><h6>About <?= e($job['company_name']) ?></h6><p class="small"><?= nl2br(e($job['about'])) ?></p><?php endif; ?></div></div></div>
<div class="col-md-4"><div class="card shadow-sm mb-3"><div class="card-body text-center">
<?php if ($applied): ?><div class="mb-2">Status <?= status_badge($applied['status']) ?></div><a class="btn btn-brand w-100" href="application_view.php?id=<?= $applied['id'] ?>">Track application</a>
<?php elseif ($open): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="apply"><button class="btn btn-brand btn-lg w-100">Apply now</button></form>
<?php else: ?><div class="alert alert-secondary mb-0">Applications closed</div><?php endif; ?>
<form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="act" value="save"><button class="btn btn-outline-secondary w-100"><i class="bi bi-bookmark<?= $saved ? '-fill' : '' ?>"></i> <?= $saved ? 'Saved' : 'Save job' ?></button></form></div></div>
<div class="card shadow-sm"><div class="card-header d-flex justify-content-between">Your skill match <?= match_badge($g['score']) ?></div>
<?php if ($g['items']): ?><ul class="list-group list-group-flush small"><?php foreach ($g['items'] as $i): ?><li class="list-group-item d-flex justify-content-between align-items-center"><span><?= e($i['name']) ?><?= $i['mandatory'] ? ' *' : '' ?> <span class="text-muted">(need <?= level_name($i['rl']) ?>)</span></span><?= skill_state_badge($i['state']) ?></li><?php endforeach; ?></ul>
<div class="card-body"><?php if ($g['mandatory_missing']): ?><div class="small text-danger mb-2">Mandatory skills below the required level: <?= e(implode(', ', $g['mandatory_missing'])) ?></div><?php endif; ?>
<a class="btn btn-sm btn-outline-primary" href="gap.php?job=<?= $id ?>">See gap report &amp; learning plan</a><div class="text-muted small mt-2">* mandatory</div></div>
<?php else: ?><div class="card-body text-muted small">The employer has not listed skills for this job yet.</div><?php endif; ?></div></div></div>
<?php page_footer();
