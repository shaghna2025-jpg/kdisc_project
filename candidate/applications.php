<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id']; $st = $_GET['status'] ?? '';
$sql = "SELECT a.*, j.title, j.location, e.company_name, (SELECT MIN(scheduled_at) FROM interviews i WHERE i.application_id=a.id AND i.schedule_status='Scheduled' AND i.scheduled_at>=NOW()) next_int
        FROM applications a JOIN jobs j ON j.id=a.job_id JOIN employers e ON e.id=j.employer_id WHERE a.candidate_id=?"; $p = [$cid];
if ($st && in_array($st, APP_STATUSES, true)) { $sql .= ' AND a.status=?'; $p[] = $st; }
$list = rows($sql . ' ORDER BY a.updated_at DESC', $p);
page_header('My applications', 'capps'); ?>
<form class="mb-3 d-flex gap-2"><select name="status" class="form-select w-auto" onchange="this.form.submit()"><option value="">All statuses</option><?php foreach (APP_STATUSES as $s): ?><option <?= $st === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></form>
<div class="card shadow-sm"><table class="table align-middle mb-0"><thead><tr><th>Job</th><th>Applied</th><th>Match</th><th>Next interview</th><th>Status</th><th></th></tr></thead>
<?php foreach ($list as $a): ?><tr><td><?= e($a['title']) ?><div class="small text-muted"><?= e($a['company_name']) ?> | <?= e($a['location']) ?></div></td><td><?= e(date('d M Y', strtotime($a['applied_at']))) ?></td>
<td><?= $a['screening_score'] !== null ? match_badge($a['screening_score']) : '-' ?></td><td><?= $a['next_int'] ? e(date('D d M, h:i A', strtotime($a['next_int']))) : '-' ?></td><td><?= status_badge($a['status']) ?></td>
<td class="text-end"><a class="btn btn-sm btn-brand" href="application_view.php?id=<?= $a['id'] ?>">Track</a></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="6" class="text-muted p-3">No applications yet. <a href="jobs.php">Find jobs</a>.</td></tr><?php endif; ?></table></div>
<?php page_footer();
