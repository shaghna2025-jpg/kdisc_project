<?php
// Set skill weight, required level and mandatory flag per job (drives candidate match % and skill gap)
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$emp = require_employer_context(); $id = (int)($_GET['id'] ?? 0);
$job = row('SELECT * FROM jobs WHERE id=? AND employer_id=?', [$id, $emp['id']]);
if (!$job) { flash('Job not found.', 'danger'); redirect('employer/jobs.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('act') === 'add') {
        $sid = skill_get_or_create(post('skill'));
        if ($sid) q('INSERT IGNORE INTO job_skills (job_id,skill_id) VALUES (?,?)', [$id, $sid]);
    } elseif (post('act') === 'remove') {
        q('DELETE FROM job_skills WHERE job_id=? AND skill_id=?', [$id, (int)post('skill_id')]);
    } else {
        foreach (($_POST['weight'] ?? []) as $sid => $w)
            q('UPDATE job_skills SET weight=?, required_level=?, mandatory=? WHERE job_id=? AND skill_id=?',
              [max(1, min(5, (int)$w)), max(1, min(5, (int)($_POST['level'][$sid] ?? 3))), isset($_POST['mand'][$sid]) ? 1 : 0, $id, (int)$sid]);
    }
    q('UPDATE jobs SET skills=? WHERE id=?', [mb_substr(implode(',', array_column(rows('SELECT s.name FROM job_skills js JOIN skills s ON s.id=js.skill_id WHERE js.job_id=?', [$id]), 'name')), 0, 500), $id]);
    flash('Job skills saved.'); redirect("employer/job_skills.php?id=$id");
}
$list = rows('SELECT s.id,s.name,js.* FROM job_skills js JOIN skills s ON s.id=js.skill_id WHERE js.job_id=? ORDER BY js.mandatory DESC, js.weight DESC, s.name', [$id]);
$all = rows('SELECT name FROM skills WHERE approved=1 ORDER BY name');
page_header('Skills for: ' . $job['title'], 'jobs'); ?>
<div class="row g-3"><div class="col-md-8"><form method="post"><?= csrf_field() ?><div class="card shadow-sm"><table class="table align-middle mb-0">
<thead><tr><th>Skill</th><th>Importance (1-5)</th><th>Required level</th><th>Mandatory</th><th></th></tr></thead>
<?php foreach ($list as $r): ?><tr><td><?= e($r['name']) ?></td>
<td><select name="weight[<?= $r['id'] ?>]" class="form-select form-select-sm"><?php for ($n = 1; $n <= 5; $n++): ?><option <?= $r['weight'] == $n ? 'selected' : '' ?>><?= $n ?></option><?php endfor; ?></select></td>
<td><select name="level[<?= $r['id'] ?>]" class="form-select form-select-sm"><?php foreach (LEVELS as $n => $l): ?><option value="<?= $n ?>" <?= $r['required_level'] == $n ? 'selected' : '' ?>><?= $n ?> - <?= $l ?></option><?php endforeach; ?></select></td>
<td><input type="checkbox" name="mand[<?= $r['id'] ?>]" <?= $r['mandatory'] ? 'checked' : '' ?>></td>
<td><button class="btn btn-sm btn-outline-danger" name="act" value="remove" onclick="this.form.skill_id.value=<?= $r['id'] ?>">&times;</button></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="5" class="text-muted p-3">No skills yet. Add them below or in the job form.</td></tr><?php endif; ?></table></div>
<input type="hidden" name="skill_id" value=""><button class="btn btn-brand mt-3" name="act" value="save">Save skill settings</button> <a href="jobs.php" class="btn btn-link">Back to jobs</a></form></div>
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header">Add skill</div><div class="card-body"><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="add">
<input name="skill" list="sk" class="form-control mb-2" placeholder="Start typing..." required><datalist id="sk"><?php foreach ($all as $a): ?><option value="<?= e($a['name']) ?>"><?php endforeach; ?></datalist>
<button class="btn btn-sm btn-primary">Add</button></form><p class="small text-muted mt-3">Importance and level feed each candidate's match % and skill gap report. Mandatory skills count 1.5x.</p></div></div></div></div>
<?php page_footer();
