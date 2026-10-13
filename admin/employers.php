<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(['EmpAdmin', 'SuperAdmin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $id = (int)post('id'); $act = post('act');
    $emp = row('SELECT * FROM employers WHERE id=?', [$id]);
    if ($emp) {
        $map = ['approve' => 'Approved', 'reject' => 'Rejected', 'suspend' => 'Suspended'];
        if (isset($map[$act])) {
            q('UPDATE employers SET status=?, approved_by=?, approved_at=NOW() WHERE id=?', [$map[$act], user()['id'], $id]);
            notify($emp['email'], 'Employer account ' . strtolower($map[$act]),
                $act === 'approve' ? "Your employer account for {$emp['company_name']} is approved. You can now log in and post jobs." : "Your employer account status is now: {$map[$act]}.", 'employer', $id);
            flash("{$emp['company_name']} marked {$map[$act]}.");
        }
    }
    redirect('admin/employers.php');
}
$status = $_GET['status'] ?? ''; $qs = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM employers WHERE 1'; $p = [];
if ($status) { $sql .= ' AND status=?'; $p[] = $status; }
if ($qs !== '') { $sql .= ' AND (company_name LIKE ? OR email LIKE ? OR company_id LIKE ?)'; array_push($p, "%$qs%", "%$qs%", "%$qs%"); }
$list = rows($sql . ' ORDER BY created_at DESC', $p);
$view = isset($_GET['view']) ? row('SELECT * FROM employers WHERE id=?', [(int)$_GET['view']]) : null;
page_header('Employers', 'emps'); ?>
<?php if ($view): ?>
<div class="card shadow-sm mb-4"><div class="card-header d-flex justify-content-between"><b><?= e($view['company_name']) ?></b> <?= status_badge($view['status']) ?></div>
<div class="card-body"><div class="row">
<?php foreach (['company_id' => 'Company ID', 'email' => 'Email', 'mobile' => 'Mobile', 'contact_person' => 'Contact', 'industry' => 'Industry', 'company_size' => 'Size', 'website' => 'Website', 'address' => 'Address', 'city' => 'City', 'state' => 'State', 'source' => 'Source'] as $k => $l): ?>
  <div class="col-md-4 mb-2"><small class="text-muted"><?= $l ?></small><div><?= e($view[$k]) ?: '-' ?></div></div><?php endforeach; ?>
  <div class="col-12"><small class="text-muted">About</small><div><?= nl2br(e($view['about'])) ?: '-' ?></div></div>
  <div class="col-12 mt-2"><small class="text-muted">Verified</small> Email: <?= $view['email_verified'] ? 'Yes' : 'No' ?> | Mobile: <?= $view['mobile_verified'] ? 'Yes' : 'No' ?></div></div>
<form method="post" class="mt-3 d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $view['id'] ?>">
  <button name="act" value="approve" class="btn btn-success">Approve</button>
  <button name="act" value="reject" class="btn btn-danger">Reject</button>
  <button name="act" value="suspend" class="btn btn-dark">Suspend</button>
  <a class="btn btn-outline-secondary" href="<?= e(url('admin/employer_add.php?id=' . $view['id'])) ?>">Edit profile</a></form></div></div>
<?php endif; ?>
<form class="row g-2 mb-3"><div class="col-auto"><input name="q" class="form-control" placeholder="Search name / email / ID" value="<?= e($qs) ?>"></div>
<div class="col-auto"><select name="status" class="form-select"><option value="">All statuses</option>
<?php foreach (['Pending', 'Approved', 'Rejected', 'Suspended'] as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
<div class="col-auto"><button class="btn btn-primary">Filter</button></div></form>
<div class="card shadow-sm"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Company</th><th>Email</th><th>City</th><th>Source</th><th>Status</th><th></th></tr></thead>
<?php foreach ($list as $r): ?><tr><td><?= e($r['company_name']) ?><div class="small text-muted"><?= e($r['company_id']) ?></div></td><td><?= e($r['email']) ?></td><td><?= e($r['city']) ?></td><td><?= e($r['source']) ?></td><td><?= status_badge($r['status']) ?></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?view=<?= $r['id'] ?>">View</a>
<?php if ($r['status'] === 'Approved'): ?><form method="post" action="<?= e(url('employer/switch.php')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-brand">Manage</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="6" class="text-muted">No employers found.</td></tr><?php endif; ?></table></div>
<?php page_footer();
