<?php
// SkillsAdmin: course catalogue used by learning recommendations
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(['SuperAdmin', 'SkillsAdmin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act');
    if ($act === 'add' && post('title') !== '') {
        q('INSERT INTO courses (title,provider,url,hours,level_provided,cost_type) VALUES (?,?,?,?,?,?)', [post('title'), post('provider'), post('url'), max(1, (int)post('hours', 10)), max(1, min(5, (int)post('level', 3))), post('cost_type') === 'Paid' ? 'Paid' : 'Free']);
        $cid = insert_id(); foreach (array_map('intval', $_POST['skills'] ?? []) as $sid) q('INSERT IGNORE INTO skill_courses (skill_id,course_id) VALUES (?,?)', [$sid, $cid]); flash('Course added.');
    } elseif ($act === 'toggle') { q('UPDATE courses SET active=1-active WHERE id=?', [(int)post('id')]); }
    elseif ($act === 'delete') { q('DELETE FROM courses WHERE id=?', [(int)post('id')]); flash('Course deleted.'); }
    redirect('admin/courses.php');
}
$skills = rows('SELECT id,name FROM skills WHERE approved=1 ORDER BY name');
$list = rows('SELECT c.*, (SELECT GROUP_CONCAT(s.name SEPARATOR ", ") FROM skill_courses sc JOIN skills s ON s.id=sc.skill_id WHERE sc.course_id=c.id) skills FROM courses c ORDER BY c.title');
page_header('Courses', 'acourses'); ?>
<div class="card shadow-sm mb-3"><div class="card-body"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="act" value="add">
<div class="col-md-4"><input name="title" class="form-control" placeholder="Course title" required></div><div class="col-md-2"><input name="provider" class="form-control" placeholder="Provider"></div>
<div class="col-md-3"><input name="url" class="form-control" placeholder="https://..."></div><div class="col-md-1"><input type="number" name="hours" class="form-control" placeholder="Hours" value="10"></div>
<div class="col-md-1"><select name="level" class="form-select" title="Level reached"><?php foreach (LEVELS as $n => $l): ?><option value="<?= $n ?>" <?= $n == 3 ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
<div class="col-md-1"><select name="cost_type" class="form-select"><option>Free</option><option>Paid</option></select></div>
<div class="col-md-10"><select name="skills[]" multiple size="4" class="form-select"><?php foreach ($skills as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select><div class="form-text">Skills this course teaches (Ctrl/Cmd-click for several). "Level" is the skill level a learner reaches on completion.</div></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Add course</button></div></form></div></div>
<div class="card shadow-sm"><table class="table align-middle mb-0 small"><thead><tr><th>Course</th><th>Skills</th><th>Hours</th><th>Level</th><th>Cost</th><th>Active</th><th></th></tr></thead>
<?php foreach ($list as $c): ?><tr><td><?= e($c['title']) ?><div class="text-muted"><?= e($c['provider']) ?></div></td><td><?= e($c['skills']) ?></td><td><?= (int)$c['hours'] ?></td><td><?= level_name($c['level_provided']) ?></td><td><?= e($c['cost_type']) ?></td><td><?= $c['active'] ? 'Yes' : 'No' ?></td>
<td class="text-end text-nowrap"><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><button name="act" value="toggle" class="btn btn-sm btn-outline-secondary">Toggle</button>
<button name="act" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete course?')">Delete</button></form></td></tr><?php endforeach; ?></table></div>
<?php page_footer();
