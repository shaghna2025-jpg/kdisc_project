<?php
// Skill tests catalogue: verified skill levels
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id'];
$tests = rows("SELECT t.*, s.name skill, cs.level cur_level, cs.verified,
  (SELECT MAX(score_percent) FROM test_attempts WHERE candidate_id=? AND test_id=t.id AND status='Completed') best,
  (SELECT MAX(submitted_at) FROM test_attempts WHERE candidate_id=? AND test_id=t.id AND status<>'In Progress') last_at,
  (SELECT COUNT(*) FROM test_attempts WHERE candidate_id=? AND test_id=t.id AND status='In Progress') running,
  (SELECT COUNT(*) FROM test_questions WHERE test_id=t.id) nq,
  EXISTS (SELECT 1 FROM job_skills js JOIN jobs j ON j.id=js.job_id WHERE js.skill_id=t.skill_id AND j.status='Published'
          AND (j.id IN (SELECT job_id FROM applications WHERE candidate_id=?) OR j.id IN (SELECT job_id FROM saved_jobs WHERE candidate_id=?))) rec
  FROM skill_tests t JOIN skills s ON s.id=t.skill_id LEFT JOIN candidate_skills cs ON cs.skill_id=t.skill_id AND cs.candidate_id=?
  WHERE t.active=1 ORDER BY rec DESC, s.name", [$cid, $cid, $cid, $cid, $cid, $cid]);
$hist = rows("SELECT a.*, t.title FROM test_attempts a JOIN skill_tests t ON t.id=a.test_id WHERE a.candidate_id=? AND a.status<>'In Progress' ORDER BY a.submitted_at DESC LIMIT 10", [$cid]);
page_header('Skill tests', 'ctests'); ?>
<p class="text-muted">Pass a test to earn a <b>verified</b> level for that skill. Employers see the verified badge and your match score uses the verified level. Tests are timed and can be retaken after 24 hours.</p>
<div class="row g-3"><?php foreach ($tests as $t): $wait = $t['last_at'] && strtotime($t['last_at']) + 86400 > time(); ?>
<div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between"><h5><?= e($t['skill']) ?></h5><?= $t['rec'] ? '<span class="badge text-bg-info">Recommended</span>' : '' ?></div>
<div class="small text-muted mb-2"><?= (int)$t['questions_per_attempt'] ?> questions | <?= (int)$t['time_limit_min'] ?> min | pass <?= (int)$t['pass_percent'] ?>%</div>
<div class="mb-2">Your level: <?= $t['cur_level'] ? level_name($t['cur_level']) : '<span class="text-muted">not added</span>' ?> <?= $t['verified'] ? '<span class="badge text-bg-success">Verified</span>' : '' ?></div>
<?php if ($t['best'] !== null): ?><div class="small mb-2">Best score: <b><?= (int)$t['best'] ?>%</b></div><?php endif; ?>
<?php if ($t['nq'] < $t['questions_per_attempt']): ?><button class="btn btn-secondary btn-sm" disabled>Not ready</button>
<?php elseif ($t['running']): ?><a class="btn btn-warning btn-sm" href="test_take.php?test=<?= $t['id'] ?>">Resume test</a>
<?php elseif ($wait): ?><button class="btn btn-outline-secondary btn-sm" disabled>Retake after <?= e(date('d M, h:i A', strtotime($t['last_at']) + 86400)) ?></button>
<?php else: ?><a class="btn btn-brand btn-sm" href="test_take.php?test=<?= $t['id'] ?>"><?= $t['best'] !== null ? 'Retake' : 'Start test' ?></a><?php endif; ?></div></div></div>
<?php endforeach; if (!$tests): ?><div class="col-12"><div class="alert alert-info">No tests are available yet.</div></div><?php endif; ?></div>
<?php if ($hist): ?><div class="card shadow-sm mt-4"><div class="card-header">Recent attempts</div><table class="table mb-0 align-middle"><?php foreach ($hist as $h): ?>
<tr><td><?= e($h['title']) ?></td><td><?= e(date('d M Y, h:i A', strtotime($h['submitted_at']))) ?></td><td><?= $h['status'] === 'Expired' ? 'Timed out' : (int)$h['score_percent'] . '%' ?></td>
<td><?= status_badge($h['passed'] ? 'Passed' : 'Failed') ?></td><td><?= $h['level_awarded'] ? 'Level ' . (int)$h['level_awarded'] . ' (' . level_name($h['level_awarded']) . ')' : '-' ?></td><td><a href="test_result.php?id=<?= $h['id'] ?>">Details</a></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php page_footer();
