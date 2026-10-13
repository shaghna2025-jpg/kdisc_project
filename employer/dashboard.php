<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$emp = require_employer_context(); $id = $emp['id'];
$stats = [
 'Active job postings' => val("SELECT COUNT(*) FROM jobs WHERE employer_id=? AND status='Published'", [$id]),
 'Candidates applied' => val('SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=?', [$id]),
 'Shortlisted / in process' => val("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=? AND a.status IN ('Shortlisted','In Process')", [$id]),
 'Selected' => val("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=? AND a.status IN ('Selected','Offered','Offer Accepted')", [$id]),
 'Upcoming interviews' => val("SELECT COUNT(*) FROM interviews i JOIN applications a ON a.id=i.application_id JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=? AND i.schedule_status='Scheduled' AND i.scheduled_at>=NOW()", [$id]),
];
$jobs = rows("SELECT j.id,j.title,j.status,j.last_date,(SELECT COUNT(*) FROM applications WHERE job_id=j.id) apps FROM jobs j WHERE j.employer_id=? AND j.status='Published' ORDER BY j.published_at DESC LIMIT 8", [$id]);
$ints = rows("SELECT i.*, c.name cand, a.id app_id, j.title, r.round_name FROM interviews i JOIN applications a ON a.id=i.application_id JOIN candidates c ON c.id=a.candidate_id JOIN jobs j ON j.id=a.job_id JOIN job_rounds r ON r.id=i.round_id
  WHERE j.employer_id=? AND i.schedule_status='Scheduled' AND i.scheduled_at>=NOW() ORDER BY i.scheduled_at LIMIT 8", [$id]);
$brk = rows('SELECT a.status, COUNT(*) n FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=? GROUP BY a.status', [$id]);
page_header('Employer Dashboard', 'dash'); ?>
<div class="row g-3 mb-4"><?php foreach ($stats as $k => $v): ?><div class="col"><div class="card stat shadow-sm"><div class="card-body"><div class="text-muted small"><?= e($k) ?></div><div class="fs-3 fw-bold"><?= (int)$v ?></div></div></div></div><?php endforeach; ?></div>
<div class="row g-3">
<div class="col-md-6"><div class="card shadow-sm"><div class="card-header">Active job postings</div><table class="table mb-0">
<?php foreach ($jobs as $j): ?><tr><td><a href="<?= e(url('employer/candidates.php?job=' . $j['id'])) ?>"><?= e($j['title']) ?></a></td><td><?= (int)$j['apps'] ?> applied</td><td class="text-muted small">closes <?= e($j['last_date'] ?: '-') ?></td></tr>
<?php endforeach; if (!$jobs): ?><tr><td class="text-muted">No published jobs. <a href="<?= e(url('employer/job_form.php')) ?>">Add one</a>.</td></tr><?php endif; ?></table></div></div>
<div class="col-md-6"><div class="card shadow-sm"><div class="card-header">Upcoming interviews</div><table class="table mb-0">
<?php foreach ($ints as $i): ?><tr><td><a href="<?= e(url('employer/candidate_view.php?id=' . $i['app_id'])) ?>"><?= e($i['cand']) ?></a><div class="small text-muted"><?= e($i['title']) ?> - <?= e($i['round_name']) ?></div></td><td><?= e(date('d M, h:i A', strtotime($i['scheduled_at']))) ?></td><td><?= e($i['mode']) ?></td></tr>
<?php endforeach; if (!$ints): ?><tr><td class="text-muted">Nothing scheduled.</td></tr><?php endif; ?></table></div></div>
<div class="col-12"><div class="card shadow-sm"><div class="card-header">Candidates by status</div><div class="card-body d-flex gap-3 flex-wrap">
<?php foreach ($brk as $b): ?><div><?= status_badge($b['status']) ?> <b><?= $b['n'] ?></b></div><?php endforeach; if (!$brk) echo '<span class="text-muted">No applications yet.</span>'; ?></div></div></div>
</div>
<?php page_footer();
