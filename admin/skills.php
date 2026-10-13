<?php
// SkillsAdmin: manage the skill taxonomy (add, aliases, approve candidate-added skills, merge, delete)
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(['SuperAdmin', 'SkillsAdmin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act'); $id = (int)post('id');
    try {
        if ($act === 'add' && post('name') !== '') {
            q('INSERT INTO skills (name,category,approved) VALUES (?,?,1)', [post('name'), post('category', 'Other')]); $sid = insert_id();
            foreach (array_filter(array_map('trim', explode(',', post('aliases')))) as $al) q('INSERT IGNORE INTO skill_aliases (skill_id,alias) VALUES (?,?)', [$sid, $al]);
            flash('Skill added.');
        } elseif ($act === 'approve') { q('UPDATE skills SET approved=1, category=? WHERE id=?', [post('category', 'Other'), $id]); flash('Skill approved.'); }
        elseif ($act === 'alias' && post('alias') !== '') { q('INSERT IGNORE INTO skill_aliases (skill_id,alias) VALUES (?,?)', [$id, post('alias')]); flash('Alias added.'); }
        elseif ($act === 'merge') {   // merge duplicate skill $id into target skill
            $to = (int)post('target');
            if ($to && $to !== $id) {
                q('INSERT IGNORE INTO skill_aliases (skill_id,alias) SELECT ?, name FROM skills WHERE id=?', [$to, $id]);
                q('UPDATE IGNORE job_skills SET skill_id=? WHERE skill_id=?', [$to, $id]); q('UPDATE IGNORE candidate_skills SET skill_id=? WHERE skill_id=?', [$to, $id]);
                q('UPDATE IGNORE skill_courses SET skill_id=? WHERE skill_id=?', [$to, $id]); q('DELETE FROM skills WHERE id=?', [$id]); flash('Skills merged.');
            }
        } elseif ($act === 'delete') { q('DELETE FROM skills WHERE id=?', [$id]); flash('Skill deleted.'); }
    } catch (PDOException $x) { flash('Could not save (duplicate name or alias?).', 'danger'); }
    redirect('admin/skills.php');
}
$f = $_GET['f'] ?? ''; $kw = trim($_GET['q'] ?? '');
$sql = 'SELECT s.*, (SELECT COUNT(*) FROM job_skills WHERE skill_id=s.id) jobs, (SELECT COUNT(*) FROM candidate_skills WHERE skill_id=s.id) cands, (SELECT GROUP_CONCAT(alias SEPARATOR ", ") FROM skill_aliases WHERE skill_id=s.id) aliases FROM skills s WHERE 1'; $p = [];
if ($f === 'pending') $sql .= ' AND s.approved=0'; if ($kw !== '') { $sql .= ' AND s.name LIKE ?'; $p[] = "%$kw%"; }
$list = rows($sql . ' ORDER BY s.approved, s.name LIMIT 300', $p); $all = rows('SELECT id,name FROM skills WHERE approved=1 ORDER BY name');
page_header('Skill taxonomy', 'askills'); ?>
<div class="card shadow-sm mb-3"><div class="card-body"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="act" value="add">
<div class="col-md-3"><input name="name" class="form-control" placeholder="Skill name" required></div><div class="col-md-2"><input name="category" class="form-control" placeholder="Category" value="Other"></div>
<div class="col-md-5"><input name="aliases" class="form-control" placeholder="Aliases, comma separated (e.g. ReactJS, React.js)"></div><div class="col-md-2"><button class="btn btn-primary w-100">Add skill</button></div></form></div></div>
<form class="d-flex gap-2 mb-3"><input name="q" class="form-control w-auto" placeholder="Search" value="<?= e($kw) ?>"><select name="f" class="form-select w-auto"><option value="">All</option><option value="pending" <?= $f === 'pending' ? 'selected' : '' ?>>Pending review</option></select><button class="btn btn-outline-primary">Filter</button></form>
<div class="card shadow-sm"><table class="table align-middle mb-0 small"><thead><tr><th>Skill</th><th>Category</th><th>Aliases</th><th>Jobs</th><th>Candidates</th><th>Actions</th></tr></thead>
<?php foreach ($list as $s): ?><tr><td><?= e($s['name']) ?> <?= $s['approved'] ? '' : '<span class="badge text-bg-warning">Pending</span>' ?></td><td><?= e($s['category']) ?></td><td><?= e($s['aliases']) ?></td><td><?= (int)$s['jobs'] ?></td><td><?= (int)$s['cands'] ?></td>
<td class="text-nowrap"><form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
<?php if (!$s['approved']): ?><input name="category" class="form-control form-control-sm" style="width:100px" value="<?= e($s['category']) ?>"><button name="act" value="approve" class="btn btn-sm btn-success">Approve</button><?php endif; ?>
<input name="alias" class="form-control form-control-sm" style="width:90px" placeholder="alias"><button name="act" value="alias" class="btn btn-sm btn-outline-primary">+</button>
<select name="target" class="form-select form-select-sm" style="width:110px"><option value="">Merge into</option><?php foreach ($all as $a) if ($a['id'] != $s['id']): ?><option value="<?= $a['id'] ?>"><?= e($a['name']) ?></option><?php endif; ?></select><button name="act" value="merge" class="btn btn-sm btn-outline-secondary">Merge</button>
<button name="act" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this skill everywhere?')">Delete</button></form></td></tr>
<?php endforeach; ?></table></div>
<?php page_footer();
