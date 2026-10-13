<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $resume = $c['resume_path'];
    if (!empty($_FILES['resume']['name']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'doc', 'docx'], true) && $_FILES['resume']['size'] <= 3145728) {
            $dir = __DIR__ . '/../uploads/resumes'; if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fn = "cand{$cid}_" . bin2hex(random_bytes(6)) . ".$ext"; move_uploaded_file($_FILES['resume']['tmp_name'], "$dir/$fn"); $resume = "uploads/resumes/$fn";
        } else flash('Resume must be PDF/DOC/DOCX under 3 MB.', 'danger');
    }
    $g = in_array(post('gender'), ['Male', 'Female', 'Other'], true) ? post('gender') : null;
    q('UPDATE candidates SET name=?,phone=?,dob=?,gender=?,location=?,headline=?,education=?,experience_years=?,current_company=?,summary=?,expected_salary=?,preferred_location=?,job_type_pref=?,resume_path=? WHERE id=?',
      [post('name', $c['name']), post('phone'), nullable(post('dob')), $g, post('location'), post('headline'), post('education'), max(0, (float)post('experience_years', 0)), post('current_company'), post('summary'),
       nullable(post('expected_salary')), post('preferred_location'), post('job_type_pref'), $resume, $cid]);
    foreach (rows("SELECT id FROM applications WHERE candidate_id=? AND status='Applied'", [$cid]) as $a) screen_application($a['id']);
    flash('Profile saved.'); redirect('candidate/profile.php');
}
$nSkills = (int)val('SELECT COUNT(*) FROM candidate_skills WHERE candidate_id=?', [$cid]); $pc = profile_completeness($c, $nSkills);
$v = fn($k) => e($c[$k] ?? '');
page_header('Profile & resume', 'cprof'); ?>
<div class="progress mb-3" style="height:22px"><div class="progress-bar bg-success" style="width:<?= $pc ?>%">Profile <?= $pc ?>% complete</div></div>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="row g-3"><div class="col-md-8"><div class="card shadow-sm"><div class="card-header">Personal and career details</div><div class="card-body row g-3">
<div class="col-md-6"><label class="form-label">Full name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required></div>
<div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="<?= $v('email') ?>" disabled></div>
<div class="col-md-4"><label class="form-label">Mobile</label><input name="phone" class="form-control" value="<?= $v('phone') ?>"></div>
<div class="col-md-4"><label class="form-label">Date of birth</label><input type="date" name="dob" class="form-control" value="<?= $v('dob') ?>"></div>
<div class="col-md-4"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">-</option><?php foreach (['Male', 'Female', 'Other'] as $g): ?><option <?= $c['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Headline</label><input name="headline" class="form-control" placeholder="e.g. PHP developer with 3 years experience" value="<?= $v('headline') ?>"></div>
<div class="col-md-6"><label class="form-label">Current location</label><input name="location" class="form-control" value="<?= $v('location') ?>"></div>
<div class="col-md-6"><label class="form-label">Highest education</label><input name="education" class="form-control" value="<?= $v('education') ?>"></div>
<div class="col-md-4"><label class="form-label">Experience (years)</label><input type="number" step="0.5" min="0" name="experience_years" class="form-control" value="<?= $v('experience_years') ?>"></div>
<div class="col-md-8"><label class="form-label">Current company</label><input name="current_company" class="form-control" value="<?= $v('current_company') ?>"></div>
<div class="col-12"><label class="form-label">Summary</label><textarea name="summary" rows="4" class="form-control"><?= $v('summary') ?></textarea></div>
<div class="col-md-4"><label class="form-label">Expected salary (annual)</label><input type="number" name="expected_salary" class="form-control" value="<?= $v('expected_salary') ?>"></div>
<div class="col-md-4"><label class="form-label">Preferred location</label><input name="preferred_location" class="form-control" value="<?= $v('preferred_location') ?>"></div>
<div class="col-md-4"><label class="form-label">Job type</label><select name="job_type_pref" class="form-select"><option value="">Any</option><?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $t): ?><option <?= $c['job_type_pref'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
</div></div></div>
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header">Resume</div><div class="card-body">
<?php if ($c['resume_path']): ?><a class="btn btn-outline-primary btn-sm mb-3" target="_blank" href="<?= e(url($c['resume_path'])) ?>"><i class="bi bi-file-earmark-person"></i> View current resume</a><?php else: ?><p class="text-muted small">No resume uploaded.</p><?php endif; ?>
<input type="file" name="resume" accept=".pdf,.doc,.docx" class="form-control"><div class="form-text">PDF / DOC / DOCX, max 3 MB. Employers see this when you apply.</div></div></div>
<button class="btn btn-brand w-100 mt-3">Save profile</button><a href="skills.php" class="btn btn-outline-secondary w-100 mt-2">Manage skills (<?= $nSkills ?>)</a></div></div></form>
<?php page_footer();
