<?php
// Add / edit a job (mapped to the employer) with its interview rounds
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$emp = require_employer_context(); $eid = $emp['id'];
$id = (int)($_GET['id'] ?? 0);
$job = $id ? row('SELECT * FROM jobs WHERE id=? AND employer_id=?', [$id, $eid]) : null;
if ($id && !$job) { flash('Job not found.', 'danger'); redirect('employer/jobs.php'); }
$ints = rows("SELECT id,name,role FROM interviewers WHERE employer_id=? AND status='Active' ORDER BY name", [$eid]);
$types = ['Technical', 'GD', 'HR', 'Managerial', 'Aptitude', 'Other'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('title') === '' || post('description') === '') flash('Title and description are required.', 'danger');
    else {
        $d = [post('title'), post('department'), post('description'), post('skills'), post('location'), post('job_type', 'Full-time'), (float)post('exp_min', 0), nullable(post('exp_max')),
              nullable(post('salary_min')), nullable(post('salary_max')), max(1, (int)post('vacancies', 1)), nullable(post('last_date'))];
        $pdo->beginTransaction();
        try {
            if ($job) { q('UPDATE jobs SET title=?,department=?,description=?,skills=?,location=?,job_type=?,exp_min=?,exp_max=?,salary_min=?,salary_max=?,vacancies=?,last_date=? WHERE id=? AND employer_id=?', [...$d, $id, $eid]); }
            else { q('INSERT INTO jobs (title,department,description,skills,location,job_type,exp_min,exp_max,salary_min,salary_max,vacancies,last_date,employer_id,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$d, $eid, user()['id']]); $id = insert_id(); }
            // rebuild rounds; keep existing rounds that have interviews by updating in place (round_no is the key)
            $names = $_POST['round_name'] ?? []; $rt = $_POST['round_type'] ?? []; $ri = $_POST['round_int'] ?? []; $no = 0; $keep = [];
            foreach ($names as $k => $nm) {
                $nm = trim($nm); if ($nm === '') continue; $no++;
                $type = in_array($rt[$k] ?? '', $types, true) ? $rt[$k] : 'Other'; $iv = (int)($ri[$k] ?? 0) ?: null;
                if ($iv && !val('SELECT COUNT(*) FROM interviewers WHERE id=? AND employer_id=?', [$iv, $eid])) $iv = null;
                q('INSERT INTO job_rounds (job_id,round_no,round_name,round_type,interviewer_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE round_name=VALUES(round_name),round_type=VALUES(round_type),interviewer_id=VALUES(interviewer_id)', [$id, $no, $nm, $type, $iv]);
                $keep[] = $no;
            }
            if ($keep) q('DELETE FROM job_rounds WHERE job_id=? AND round_no NOT IN (' . implode(',', $keep) . ')', [$id]);
            $pdo->commit(); sync_job_skills($id);
        } catch (Throwable $t) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('Save failed: ' . $t->getMessage(), 'danger'); redirect('employer/job_form.php' . ($id ? "?id=$id" : '')); }
        flash($job && $job['status'] === 'Published' ? 'Job updated. Changes are live.' : 'Job saved. Publish it from the jobs list.'); redirect('employer/jobs.php');
    }
}
$rounds = $id ? rows('SELECT * FROM job_rounds WHERE job_id=? ORDER BY round_no', [$id]) : [['round_name' => 'Technical Interview', 'round_type' => 'Technical', 'interviewer_id' => null], ['round_name' => 'HR Interview', 'round_type' => 'HR', 'interviewer_id' => null]];
$v = fn($k, $d = '') => e($job[$k] ?? $d);
page_header($job ? 'Edit Job' : 'Add Job', 'jobs'); ?>
<form method="post"><?= csrf_field() ?><div class="row g-3"><div class="col-md-8"><div class="card shadow-sm"><div class="card-header">Job details</div><div class="card-body row g-3">
<div class="col-md-8"><label class="form-label">Job title *</label><input name="title" class="form-control" value="<?= $v('title') ?>" required></div>
<div class="col-md-4"><label class="form-label">Department</label><input name="department" class="form-control" value="<?= $v('department') ?>"></div>
<div class="col-12"><label class="form-label">Description *</label><textarea name="description" rows="5" class="form-control" required><?= $v('description') ?></textarea></div>
<div class="col-12"><label class="form-label">Required skills <small class="text-muted">(comma separated - used for screening)</small></label><input name="skills" class="form-control" value="<?= $v('skills') ?>"></div>
<div class="col-md-4"><label class="form-label">Location</label><input name="location" class="form-control" value="<?= $v('location') ?>"></div>
<div class="col-md-4"><label class="form-label">Job type</label><select name="job_type" class="form-select"><?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $t): ?><option <?= ($job['job_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Vacancies</label><input type="number" min="1" name="vacancies" class="form-control" value="<?= $v('vacancies', 1) ?>"></div>
<div class="col-md-3"><label class="form-label">Min exp (yrs)</label><input type="number" step="0.5" min="0" name="exp_min" class="form-control" value="<?= $v('exp_min', 0) ?>"></div>
<div class="col-md-3"><label class="form-label">Max exp (yrs)</label><input type="number" step="0.5" min="0" name="exp_max" class="form-control" value="<?= $v('exp_max') ?>"></div>
<div class="col-md-3"><label class="form-label">Salary min (annual)</label><input type="number" name="salary_min" class="form-control" value="<?= $v('salary_min') ?>"></div>
<div class="col-md-3"><label class="form-label">Salary max (annual)</label><input type="number" name="salary_max" class="form-control" value="<?= $v('salary_max') ?>"></div>
<div class="col-md-4"><label class="form-label">Last date to apply</label><input type="date" name="last_date" class="form-control" value="<?= $v('last_date') ?>"></div></div></div></div>
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header d-flex justify-content-between">Interview rounds <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRound()">+ Round</button></div><div class="card-body" id="rounds">
<?php foreach ($rounds as $r): ?><div class="round border rounded p-2 mb-2">
<input name="round_name[]" class="form-control form-control-sm mb-1" placeholder="Round name" value="<?= e($r['round_name']) ?>">
<div class="d-flex gap-1"><select name="round_type[]" class="form-select form-select-sm"><?php foreach ($types as $t): ?><option <?= $r['round_type'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
<select name="round_int[]" class="form-select form-select-sm"><option value="">Interviewer</option><?php foreach ($ints as $i): ?><option value="<?= $i['id'] ?>" <?= $r['interviewer_id'] == $i['id'] ? 'selected' : '' ?>><?= e($i['name']) ?> (<?= e($i['role']) ?>)</option><?php endforeach; ?></select>
<button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.round').remove()">&times;</button></div></div><?php endforeach; ?></div></div>
<button class="btn btn-brand w-100 mt-3">Save job</button><a href="jobs.php" class="btn btn-link w-100">Cancel</a></div></div></form>
<script>function addRound(){var d=document.querySelector('.round');var n=d.cloneNode(true);n.querySelectorAll('input').forEach(i=>i.value='');n.querySelectorAll('select').forEach(s=>s.selectedIndex=0);document.getElementById('rounds').appendChild(n);}</script>
<?php page_footer();
