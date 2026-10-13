<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id'];
$nSkills = (int)val('SELECT COUNT(*) FROM candidate_skills WHERE candidate_id=?', [$cid]); $pc = profile_completeness($c, $nSkills);
$stats = [
 'Applications' => val('SELECT COUNT(*) FROM applications WHERE candidate_id=?', [$cid]),
 'In progress' => val("SELECT COUNT(*) FROM applications WHERE candidate_id=? AND status IN ('Shortlisted','In Process','Selected')", [$cid]),
 'Upcoming interviews' => val("SELECT COUNT(*) FROM interviews i JOIN applications a ON a.id=i.application_id WHERE a.candidate_id=? AND i.schedule_status='Scheduled' AND i.scheduled_at>=NOW()", [$cid]),
 'Offers' => val("SELECT COUNT(*) FROM applications WHERE candidate_id=? AND status IN ('Offered','Offer Accepted')", [$cid]),
 'Saved jobs' => val('SELECT COUNT(*) FROM saved_jobs WHERE candidate_id=?', [$cid]),
];
$ints = rows("SELECT i.*, j.title, e.company_name, r.round_name, a.id app_id FROM interviews i JOIN applications a ON a.id=i.application_id JOIN jobs j ON j.id=a.job_id JOIN employers e ON e.id=j.employer_id JOIN job_rounds r ON r.id=i.round_id
  WHERE a.candidate_id=? AND i.schedule_status='Scheduled' AND i.scheduled_at>=NOW() ORDER BY i.scheduled_at LIMIT 5", [$cid]);
$jobs = rows("SELECT j.id,j.title,j.location,e.company_name FROM jobs j JOIN employers e ON e.id=j.employer_id WHERE j.status='Published' AND e.status='Approved' AND (j.last_date IS NULL OR j.last_date>=CURDATE())
  AND j.id NOT IN (SELECT job_id FROM applications WHERE candidate_id=?) ORDER BY j.published_at DESC LIMIT 40", [$cid]);
foreach ($jobs as &$j) { $g = gap_analysis($cid, $j['id']); $j['score'] = $g['score']; } unset($j);
usort($jobs, fn($a, $b) => ($b['score'] ?? -1) <=> ($a['score'] ?? -1)); $jobs = array_slice($jobs, 0, 5);
$lack = rows('SELECT s.name, COUNT(DISTINCT js.job_id) n FROM job_skills js JOIN jobs j ON j.id=js.job_id AND j.status="Published" JOIN skills s ON s.id=js.skill_id
  LEFT JOIN candidate_skills cs ON cs.skill_id=s.id AND cs.candidate_id=? WHERE cs.skill_id IS NULL GROUP BY s.id ORDER BY n DESC LIMIT 5', [$cid]);
page_header('Welcome, ' . $c['name'], 'cdash'); ?>
<div class="row g-3 mb-3"><?php foreach ($stats as $k => $v): ?><div class="col"><div class="card stat shadow-sm"><div class="card-body"><div class="text-muted small"><?= e($k) ?></div><div class="fs-3 fw-bold"><?= (int)$v ?></div></div></div></div><?php endforeach; ?></div>
<?php if ($pc < 100): ?><div class="alert alert-warning d-flex justify-content-between align-items-center"><div>Your profile is <b><?= $pc ?>%</b> complete. A complete profile with skills gives accurate match scores.</div>
<a class="btn btn-sm btn-warning" href="profile.php">Complete profile</a></div><?php endif; ?>
<div class="row g-3"><div class="col-md-7"><div class="card shadow-sm mb-3"><div class="card-header">Best matches for you</div><table class="table mb-0 align-middle">
<?php foreach ($jobs as $j): ?><tr><td><a href="job_view.php?id=<?= $j['id'] ?>"><?= e($j['title']) ?></a><div class="small text-muted"><?= e($j['company_name']) ?> | <?= e($j['location']) ?></div></td><td class="text-end"><?= match_badge($j['score']) ?></td></tr>
<?php endforeach; if (!$jobs): ?><tr><td class="text-muted">No open jobs right now.</td></tr><?php endif; ?></table></div>
<div class="card shadow-sm"><div class="card-header">Upcoming interviews</div><table class="table mb-0">
<?php foreach ($ints as $i): ?><tr><td><a href="application_view.php?id=<?= $i['app_id'] ?>"><?= e($i['title']) ?></a> <span class="text-muted small">- <?= e($i['company_name']) ?></span><div class="small text-muted"><?= e($i['round_name']) ?> | <?= e($i['mode']) ?>: <?= e($i['venue_or_link']) ?></div></td><td><?= e(date('D d M, h:i A', strtotime($i['scheduled_at']))) ?></td></tr>
<?php endforeach; if (!$ints): ?><tr><td class="text-muted">No interviews scheduled.</td></tr><?php endif; ?></table></div></div>
<div class="col-md-5"><div class="card shadow-sm"><div class="card-header">In-demand skills you don't have yet</div><ul class="list-group list-group-flush">
<?php foreach ($lack as $l): ?><li class="list-group-item d-flex justify-content-between"><?= e($l['name']) ?><span class="text-muted small">in <?= (int)$l['n'] ?> open job(s)</span></li>
<?php endforeach; if (!$lack): ?><li class="list-group-item text-muted">You cover every skill currently in demand.</li><?php endif; ?></ul>
<div class="card-body"><a href="gap.php" class="btn btn-brand btn-sm">Open skill gap analysis</a></div></div></div></div>
<?php page_footer();
