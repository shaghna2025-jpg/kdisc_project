<?php
// Skill gap analysis: per-job gap report, learning recommendations, what-if score after learning
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id']; $jid = (int)($_GET['job'] ?? $_POST['job'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $course = row('SELECT * FROM courses WHERE id=? AND active=1', [(int)post('course_id')]);
    if ($course) {
        if (post('act') === 'start') {
            q("INSERT INTO candidate_learning (candidate_id,course_id,status,started_at) VALUES (?,?,'In Progress',NOW()) ON DUPLICATE KEY UPDATE status=IF(status='Completed','Completed','In Progress')", [$cid, $course['id']]);
            flash('Course added to your learning plan.');
        } elseif (post('act') === 'complete') {
            q("INSERT INTO candidate_learning (candidate_id,course_id,status,started_at,completed_at) VALUES (?,?,'Completed',NOW(),NOW()) ON DUPLICATE KEY UPDATE status='Completed', completed_at=NOW()", [$cid, $course['id']]);
            foreach (rows('SELECT skill_id FROM skill_courses WHERE course_id=?', [$course['id']]) as $sc)   // raise skill level to what the course delivers
                q("INSERT INTO candidate_skills (candidate_id,skill_id,level,source) VALUES (?,?,?,'Course') ON DUPLICATE KEY UPDATE source=IF(VALUES(level)>level,'Course',source), level=GREATEST(level,VALUES(level))", [$cid, $sc['skill_id'], $course['level_provided']]);
            refresh_candidate_skill_csv($cid); flash('Course completed - your skill levels were updated.');
        }
    }
    redirect('candidate/gap.php' . ($jid ? "?job=$jid" : ''));
}
$targets = rows("SELECT j.id,j.title,e.company_name FROM jobs j JOIN employers e ON e.id=j.employer_id WHERE j.status='Published' AND e.status='Approved'
   OR j.id IN (SELECT job_id FROM applications WHERE candidate_id=" . (int)$cid . ') ORDER BY j.title');
$job = $jid ? row('SELECT j.*, e.company_name FROM jobs j JOIN employers e ON e.id=j.employer_id WHERE j.id=?', [$jid]) : null;
$g = null; $recs = []; $potential = null;
if ($job) {
    $g = gap_analysis($cid, $jid); save_gap_report($cid, $jid, $g); $extra = [];
    foreach ($g['items'] as $i) if ($i['state'] !== 'Strong') {
        $cs = rows('SELECT c.*, cl.status lstatus FROM courses c JOIN skill_courses sc ON sc.course_id=c.id LEFT JOIN candidate_learning cl ON cl.course_id=c.id AND cl.candidate_id=?
                    WHERE sc.skill_id=? AND c.active=1 ORDER BY (c.level_provided>=?) DESC, c.hours', [$cid, $i['id'], $i['rl']]);
        $recs[$i['id']] = $cs; if ($cs) $extra[$i['id']] = max(array_column($cs, 'level_provided'));
    }
    if ($extra) $potential = gap_analysis($cid, $jid, $extra)['score'];
}
$lib = rows("SELECT c.title,c.hours,cl.status FROM candidate_learning cl JOIN courses c ON c.id=cl.course_id WHERE cl.candidate_id=? ORDER BY cl.status, cl.started_at DESC", [$cid]);
page_header('Skill gap analysis', 'cgap'); ?>
<form class="d-flex gap-2 mb-3"><select name="job" class="form-select w-auto" onchange="this.form.submit()"><option value="0">Choose a target job...</option>
<?php foreach ($targets as $t): ?><option value="<?= $t['id'] ?>" <?= $jid == $t['id'] ? 'selected' : '' ?>><?= e($t['title']) ?> - <?= e($t['company_name']) ?></option><?php endforeach; ?></select></form>
<?php if (!$job): ?><div class="alert alert-info">Pick a job to see how your skills compare and which courses close the gap. You can also open this from any job page.</div>
<?php elseif ($g['score'] === null): ?><div class="alert alert-warning">The employer has not listed skills for this job yet, so a gap report is not available.</div>
<?php else: ?>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="card stat shadow-sm"><div class="card-body"><div class="text-muted small">Current match</div><div class="display-6 fw-bold"><?= (int)$g['score'] ?>%</div><?= match_badge($g['score']) ?></div></div></div>
<div class="col-md-4"><div class="card stat shadow-sm"><div class="card-body"><div class="text-muted small">Potential after recommended courses</div><div class="display-6 fw-bold"><?= $potential !== null ? (int)$potential . '%' : '-' ?></div><span class="small text-muted"><?= $potential !== null ? '+' . max(0, $potential - $g['score']) . ' points' : 'No courses available' ?></span></div></div></div>
<div class="col-md-4"><div class="card stat shadow-sm"><div class="card-body"><div class="text-muted small">Skills to improve</div><div class="display-6 fw-bold"><?= count(array_filter($g['items'], fn($i) => $i['state'] !== 'Strong')) ?> <small class="fs-6 text-muted">of <?= count($g['items']) ?></small></div></div></div></div></div>
<?php if ($g['mandatory_missing']): ?><div class="alert alert-danger">Mandatory skills below requirement: <b><?= e(implode(', ', $g['mandatory_missing'])) ?></b>. Employers may not shortlist without these.</div><?php endif; ?>
<div class="card shadow-sm mb-3"><div class="card-header">Skill-by-skill comparison for <?= e($job['title']) ?> (<?= e($job['company_name']) ?>)</div><table class="table align-middle mb-0">
<thead><tr><th>Skill</th><th>Required</th><th>You</th><th style="width:220px">Coverage</th><th>Status</th></tr></thead>
<?php foreach ($g['items'] as $i): $pct = min(100, (int)round($i['cl'] / max(1, $i['rl']) * 100)); ?><tr><td><?= e($i['name']) ?><?= $i['mandatory'] ? ' <span class="text-danger">*</span>' : '' ?><div class="small text-muted">importance <?= (int)$i['weight'] ?>/5</div></td>
<td><?= level_name($i['rl']) ?></td><td><?= $i['cl'] ? level_name($i['cl']) : '<span class="text-muted">none</span>' ?></td>
<td><div class="progress"><div class="progress-bar <?= $i['state'] === 'Strong' ? 'bg-success' : ($i['state'] === 'Weak' ? 'bg-warning' : 'bg-danger') ?>" style="width:<?= $pct ?>%"><?= $pct ?>%</div></div></td><td><?= skill_state_badge($i['state']) ?></td></tr>
<?php endforeach; ?></table></div>
<h5>Recommended learning</h5>
<?php foreach ($g['items'] as $i): if ($i['state'] === 'Strong') continue; ?><div class="card shadow-sm mb-2"><div class="card-body"><b><?= e($i['name']) ?></b> <span class="text-muted small">- reach <?= level_name($i['rl']) ?> (<?= $i['cl'] ? 'currently ' . level_name($i['cl']) : 'not yet started' ?>)</span>
<?php $tid = val('SELECT id FROM skill_tests WHERE skill_id=? AND active=1', [$i['id']]); if ($tid): ?> <a class="btn btn-sm btn-outline-success ms-2" href="test_take.php?test=<?= $tid ?>">Take verified test</a><?php endif; ?>
<?php if (empty($recs[$i['id']])): ?><div class="small text-muted mt-1">No catalogue course yet. Try a project or a certification for this skill.</div>
<?php else: foreach ($recs[$i['id']] as $co): ?><div class="d-flex justify-content-between align-items-center border-top mt-2 pt-2"><div><?= e($co['title']) ?>
<div class="small text-muted"><?= e($co['provider']) ?> | <?= (int)$co['hours'] ?> h | reaches <?= level_name($co['level_provided']) ?> | <?= e($co['cost_type']) ?><?= $co['url'] && $co['url'] !== '#' ? ' | <a target="_blank" href="' . e($co['url']) . '">open</a>' : '' ?></div></div>
<form method="post" class="d-flex gap-1 align-items-center"><?= csrf_field() ?><input type="hidden" name="job" value="<?= $jid ?>"><input type="hidden" name="course_id" value="<?= $co['id'] ?>">
<?php if ($co['lstatus'] === 'Completed'): ?><span class="badge text-bg-success">Completed</span>
<?php else: ?><?php if (!$co['lstatus']): ?><button name="act" value="start" class="btn btn-sm btn-outline-primary">Start</button><?php else: ?><span class="badge text-bg-info">In progress</span><?php endif; ?>
<button name="act" value="complete" class="btn btn-sm btn-success" onclick="return confirm('Mark as completed? Your skill level will be raised accordingly.')">Mark complete</button><?php endif; ?></form></div>
<?php endforeach; endif; ?></div></div><?php endforeach; ?>
<?php endif; ?>
<?php if ($lib): ?><div class="card shadow-sm mt-3"><div class="card-header">My learning plan</div><ul class="list-group list-group-flush"><?php foreach ($lib as $l): ?><li class="list-group-item d-flex justify-content-between"><?= e($l['title']) ?> <span><?= (int)$l['hours'] ?> h <?= status_badge($l['status'] === 'Completed' ? 'Completed' : 'In Process') ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php page_footer();
