<?php
// All interviews (upcoming / past) for the employer
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$emp = require_employer_context(); $eid = $emp['id'];
$when = $_GET['when'] ?? 'upcoming';
$cond = $when === 'past' ? "(i.scheduled_at<NOW() OR i.schedule_status<>'Scheduled')" : "(i.scheduled_at>=NOW() AND i.schedule_status='Scheduled')";
$list = rows("SELECT i.*, c.name cand, a.id app_id, j.title, r.round_name, iv.name int_name FROM interviews i JOIN applications a ON a.id=i.application_id JOIN candidates c ON c.id=a.candidate_id JOIN jobs j ON j.id=a.job_id
  JOIN job_rounds r ON r.id=i.round_id LEFT JOIN interviewers iv ON iv.id=i.interviewer_id WHERE j.employer_id=? AND $cond ORDER BY i.scheduled_at " . ($when === 'past' ? 'DESC' : 'ASC'), [$eid]);
page_header('Interview Schedule', 'sched'); ?>
<ul class="nav nav-pills mb-3"><li class="nav-item"><a class="nav-link <?= $when !== 'past' ? 'active' : '' ?>" href="?when=upcoming">Upcoming</a></li><li class="nav-item"><a class="nav-link <?= $when === 'past' ? 'active' : '' ?>" href="?when=past">Past</a></li></ul>
<div class="card shadow-sm"><table class="table align-middle mb-0"><thead><tr><th>When</th><th>Candidate</th><th>Job / Round</th><th>Mode</th><th>Interviewer</th><th>Status</th></tr></thead>
<?php foreach ($list as $i): ?><tr><td><?= e(date('D d M, h:i A', strtotime($i['scheduled_at']))) ?></td><td><a href="candidate_view.php?id=<?= $i['app_id'] ?>"><?= e($i['cand']) ?></a></td><td><?= e($i['title']) ?><div class="small text-muted"><?= e($i['round_name']) ?></div></td>
<td><?= e($i['mode']) ?><div class="small text-muted"><?= e($i['venue_or_link']) ?></div></td><td><?= e($i['int_name'] ?: '-') ?></td><td><?= status_badge($i['schedule_status']) ?> <?= $i['schedule_status'] === 'Completed' ? status_badge($i['result']) : '' ?></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="6" class="text-muted p-3">No interviews.</td></tr><?php endif; ?></table></div>
<?php page_footer();
