<?php
// Candidate feedback form - opened from the tokenised link emailed after the interview process
require_once 'includes/bootstrap.php'; require_once 'includes/layout.php';
$t = $_GET['t'] ?? ($_POST['t'] ?? '');
$f = preg_match('/^[a-f0-9]{40}$/', $t) ? row("SELECT f.*, j.title, e.company_name FROM feedback f JOIN applications a ON a.id=f.application_id JOIN jobs j ON j.id=a.job_id JOIN employers e ON e.id=j.employer_id WHERE f.token=? AND f.from_type='Candidate'", [$t]) : null;
$done = false;
if ($f && !$f['submitted_at'] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); q('UPDATE feedback SET rating=?, comments=?, submitted_at=NOW() WHERE id=?', [max(1, min(5, (int)post('rating'))), post('comments'), $f['id']]); $done = true;
}
page_header('Interview Feedback'); ?>
<div class="row justify-content-center mt-4"><div class="col-md-6"><div class="card shadow-sm"><div class="card-body p-4">
<?php if (!$f): ?><div class="alert alert-danger mb-0">This feedback link is invalid.</div>
<?php elseif ($f['submitted_at'] || $done): ?><div class="alert alert-success mb-0">Thank you! Your feedback has been recorded.</div>
<?php else: ?><h4>How was your experience?</h4><p class="text-muted"><?= e($f['title']) ?> at <?= e($f['company_name']) ?></p>
<form method="post"><?= csrf_field() ?><input type="hidden" name="t" value="<?= e($t) ?>">
<label class="form-label">Overall rating</label><select name="rating" class="form-select mb-3"><?php for ($n = 5; $n >= 1; $n--): ?><option value="<?= $n ?>"><?= $n ?> - <?= ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'][$n] ?></option><?php endfor; ?></select>
<label class="form-label">Comments</label><textarea name="comments" rows="4" class="form-control mb-3"></textarea><button class="btn btn-primary">Submit feedback</button></form><?php endif; ?>
</div></div></div></div>
<?php page_footer();
