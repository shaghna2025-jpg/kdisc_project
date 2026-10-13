<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$emp = require_employer_context(); $id = $emp['id'];
$jobs = rows('SELECT j.*, (SELECT COUNT(*) FROM applications WHERE job_id=j.id) apps, (SELECT COUNT(*) FROM job_rounds WHERE job_id=j.id) rounds FROM jobs j WHERE j.employer_id=? ORDER BY j.created_at DESC', [$id]);
page_header('Jobs', 'jobs'); ?>
<a href="<?= e(url('employer/job_form.php')) ?>" class="btn btn-brand mb-3"><i class="bi bi-plus-lg"></i> Add job</a>
<div class="card shadow-sm"><table class="table align-middle mb-0"><thead><tr><th>Title</th><th>Location</th><th>Rounds</th><th>Applied</th><th>Last date</th><th>Status</th><th></th></tr></thead>
<?php foreach ($jobs as $j): ?><tr><td><?= e($j['title']) ?><div class="small text-muted"><?= e($j['job_type']) ?> | <?= e($j['vacancies']) ?> vacancy</div></td><td><?= e($j['location']) ?></td><td><?= (int)$j['rounds'] ?></td><td><?= (int)$j['apps'] ?></td><td><?= e($j['last_date']) ?></td><td><?= status_badge($j['status']) ?></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="job_form.php?id=<?= $j['id'] ?>">Edit</a> <a class="btn btn-sm btn-outline-secondary" href="job_skills.php?id=<?= $j['id'] ?>">Skills</a>
<?php if ($j['status'] !== 'Published'): ?><form method="post" action="job_action.php" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $j['id'] ?>"><button name="act" value="publish" class="btn btn-sm btn-success">Publish</button></form>
<?php else: ?><form method="post" action="job_action.php" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $j['id'] ?>"><button name="act" value="close" class="btn btn-sm btn-outline-dark">Close</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$jobs): ?><tr><td colspan="7" class="text-muted p-3">No jobs yet.</td></tr><?php endif; ?></table></div>
<?php page_footer();
