<?php
// CandAdmin: view candidates, application counts, activate / deactivate accounts
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(['SuperAdmin', 'CandAdmin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); q("UPDATE users SET status=IF(status='Active','Inactive','Active') WHERE candidate_id=? AND role='Candidate'", [(int)post('id')]); flash('Account status changed.'); redirect('admin/candidates.php');
}
$kw = trim($_GET['q'] ?? ''); $sql = "SELECT c.*, u.status ustatus, (SELECT COUNT(*) FROM applications WHERE candidate_id=c.id) apps, (SELECT COUNT(*) FROM candidate_skills WHERE candidate_id=c.id) nskills
  FROM candidates c LEFT JOIN users u ON u.candidate_id=c.id AND u.role='Candidate' WHERE 1"; $p = [];
if ($kw !== '') { $sql .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.skills LIKE ?)'; array_push($p, "%$kw%", "%$kw%", "%$kw%"); }
$list = rows($sql . ' ORDER BY c.created_at DESC LIMIT 200', $p);
page_header('Candidates', 'acands'); ?>
<form class="d-flex gap-2 mb-3"><input name="q" class="form-control w-auto" placeholder="Name, email or skill" value="<?= e($kw) ?>"><button class="btn btn-primary">Search</button></form>
<div class="card shadow-sm"><table class="table align-middle mb-0 small"><thead><tr><th>Candidate</th><th>Experience</th><th>Skills</th><th>Applications</th><th>Registered</th><th>Account</th><th></th></tr></thead>
<?php foreach ($list as $c): ?><tr><td><?= e($c['name']) ?><div class="text-muted"><?= e($c['email']) ?> | <?= e($c['phone']) ?></div></td><td><?= e($c['experience_years']) ?> y</td><td><?= (int)$c['nskills'] ?></td><td><?= (int)$c['apps'] ?></td><td><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
<td><?= $c['ustatus'] ? status_badge($c['ustatus'] === 'Active' ? 'Approved' : 'Suspended') : '<span class="text-muted">No login</span>' ?></td>
<td><?php if ($c['ustatus']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $c['ustatus'] === 'Active' ? 'Deactivate' : 'Activate' ?></button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php page_footer();
