<?php
// SkillsAdmin: create and manage skill tests
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(['SuperAdmin', 'SkillsAdmin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act'); $id = (int)post('id');
    if ($act === 'add' && (int)post('skill_id')) {
        $s = row('SELECT name FROM skills WHERE id=?', [(int)post('skill_id')]);
        q('INSERT INTO skill_tests (skill_id,title,pass_percent,time_limit_min,questions_per_attempt) VALUES (?,?,?,?,?)',
          [(int)post('skill_id'), post('title') ?: $s['name'] . ' skill test', max(30, min(100, (int)post('pass', 60))), max(1, (int)post('minutes', 10)), max(1, (int)post('qpa', 5))]);
        flash('Test created. Now add questions.'); redirect('admin/test_questions.php?test=' . insert_id());
    } elseif ($act === 'toggle') q('UPDATE skill_tests SET active=1-active WHERE id=?', [$id]);
    elseif ($act === 'delete') { q('DELETE FROM skill_tests WHERE id=?', [$id]); flash('Test deleted.'); }
    redirect('admin/tests.php');
}
$skills = rows('SELECT id,name FROM skills WHERE approved=1 ORDER BY name');
$list = rows('SELECT t.*, s.name skill, (SELECT COUNT(*) FROM test_questions WHERE test_id=t.id) nq, (SELECT COUNT(*) FROM test_attempts WHERE test_id=t.id AND status="Completed") att,
  (SELECT ROUND(AVG(score_percent)) FROM test_attempts WHERE test_id=t.id AND status="Completed") avg_score FROM skill_tests t JOIN skills s ON s.id=t.skill_id ORDER BY s.name');
page_header('Skill tests', 'atests'); ?>
<div class="card shadow-sm mb-3"><div class="card-body"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="act" value="add">
<div class="col-md-3"><select name="skill_id" class="form-select" required><option value="">Skill...</option><?php foreach ($skills as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><input name="title" class="form-control" placeholder="Title (optional)"></div><div class="col-md-1"><input type="number" name="pass" class="form-control" value="60" title="Pass %"></div>
<div class="col-md-1"><input type="number" name="minutes" class="form-control" value="10" title="Minutes"></div><div class="col-md-1"><input type="number" name="qpa" class="form-control" value="5" title="Questions per attempt"></div>
<div class="col-md-3"><button class="btn btn-primary w-100">Create test</button></div><div class="form-text">Fields: pass %, minutes, questions per attempt. Keep the question pool at least 2-3x larger than questions per attempt.</div></form></div></div>
<div class="card shadow-sm"><table class="table align-middle mb-0 small"><thead><tr><th>Test</th><th>Pool</th><th>Per attempt</th><th>Time</th><th>Pass</th><th>Attempts</th><th>Avg score</th><th>Active</th><th></th></tr></thead>
<?php foreach ($list as $t): ?><tr><td><?= e($t['title']) ?><div class="text-muted"><?= e($t['skill']) ?></div></td><td><?= (int)$t['nq'] ?></td><td><?= (int)$t['questions_per_attempt'] ?></td><td><?= (int)$t['time_limit_min'] ?> min</td><td><?= (int)$t['pass_percent'] ?>%</td><td><?= (int)$t['att'] ?></td><td><?= $t['avg_score'] !== null ? (int)$t['avg_score'] . '%' : '-' ?></td><td><?= $t['active'] ? 'Yes' : 'No' ?></td>
<td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="test_questions.php?test=<?= $t['id'] ?>">Questions</a>
<form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><button name="act" value="toggle" class="btn btn-sm btn-outline-secondary">Toggle</button><button name="act" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete test and its questions?')">Delete</button></form></td></tr>
<?php endforeach; ?></table></div>
<?php page_footer();
