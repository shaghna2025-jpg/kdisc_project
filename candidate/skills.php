<?php
// Candidate skills with proficiency; feeds match % and skill gap analysis
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act');
    if ($act === 'add') {
        $sid = skill_get_or_create(post('skill')); $lvl = max(1, min(5, (int)post('level', 3)));
        if (!$sid) flash('Enter a skill name.', 'danger');
        else {
            q("INSERT INTO candidate_skills (candidate_id,skill_id,level,years,source) VALUES (?,?,?,?,'Self') ON DUPLICATE KEY UPDATE level=VALUES(level), years=VALUES(years)", [$cid, $sid, $lvl, nullable(post('years'))]);
            flash('Skill saved.');
        }
    } elseif ($act === 'update') {
        foreach (($_POST['level'] ?? []) as $sid => $l) q('UPDATE candidate_skills SET level=? WHERE candidate_id=? AND skill_id=?', [max(1, min(5, (int)$l)), $cid, (int)$sid]);
        flash('Skill levels updated.');
    } elseif ($act === 'delete') { q('DELETE FROM candidate_skills WHERE candidate_id=? AND skill_id=?', [$cid, (int)post('skill_id')]); flash('Skill removed.'); }
    refresh_candidate_skill_csv($cid); redirect('candidate/skills.php');
}
$mine = rows('SELECT s.id,s.name,s.category,cs.* FROM candidate_skills cs JOIN skills s ON s.id=cs.skill_id WHERE cs.candidate_id=? ORDER BY cs.level DESC, s.name', [$cid]);
$all = rows('SELECT name FROM skills WHERE approved=1 ORDER BY name');
page_header('My skills', 'cskills'); ?>
<div class="row g-3"><div class="col-md-4"><div class="card shadow-sm"><div class="card-header">Add a skill</div><div class="card-body"><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="add">
<label class="form-label">Skill</label><input name="skill" list="sk" class="form-control mb-2" placeholder="Start typing..." required><datalist id="sk"><?php foreach ($all as $a): ?><option value="<?= e($a['name']) ?>"><?php endforeach; ?></datalist>
<label class="form-label">Your level</label><select name="level" class="form-select mb-2"><?php foreach (LEVELS as $n => $l): ?><option value="<?= $n ?>" <?= $n == 3 ? 'selected' : '' ?>><?= $n ?> - <?= $l ?></option><?php endforeach; ?></select>
<label class="form-label">Years of use (optional)</label><input type="number" step="0.5" min="0" name="years" class="form-control mb-3"><button class="btn btn-brand w-100">Add / update</button></form><a href="tests.php" class="btn btn-outline-success btn-sm w-100 mt-2">Verify skills with a test</a>
<p class="small text-muted mt-3 mb-0">Be honest: employers see the same levels and your match % is calculated from them. Skills you add that are not in the list go to the admin for review.</p></div></div></div>
<div class="col-md-8"><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="update"><div class="card shadow-sm"><table class="table align-middle mb-0">
<thead><tr><th>Skill</th><th>Level</th><th>Source</th><th></th></tr></thead>
<?php foreach ($mine as $m): ?><tr><td><?= e($m['name']) ?><div class="small text-muted"><?= e($m['category']) ?><?= $m['years'] !== null ? ' | ' . e($m['years']) . ' yrs' : '' ?></div></td>
<td style="width:200px"><select name="level[<?= $m['id'] ?>]" class="form-select form-select-sm"><?php foreach (LEVELS as $n => $l): ?><option value="<?= $n ?>" <?= $m['level'] == $n ? 'selected' : '' ?>><?= $n ?> - <?= $l ?></option><?php endforeach; ?></select></td>
<td><span class="badge text-bg-light border"><?= e($m['source']) ?></span> <?= $m['verified'] ? '<span class="badge text-bg-success">Verified</span>' : '' ?></td>
<td class="text-end"><button class="btn btn-sm btn-outline-danger" name="act" value="delete" formnovalidate onclick="this.form.skill_id.value=<?= $m['id'] ?>">Remove</button></td></tr>
<?php endforeach; if (!$mine): ?><tr><td colspan="4" class="text-muted p-3">No skills yet - add your first skill.</td></tr><?php endif; ?></table></div>
<input type="hidden" name="skill_id" value=""><?php if ($mine): ?><button class="btn btn-primary mt-3">Save levels</button><?php endif; ?></form></div></div>
<?php page_footer();
