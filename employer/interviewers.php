<?php
// Add interviewers and assign roles (Technical, HR ...). Works for Employer and EmpAdmin.
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$emp = require_employer_context(); $id = $emp['id'];
$roles = ['Technical', 'HR', 'Group Discussion', 'Managerial', 'Other'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act'); $iid = (int)post('iid');
    if ($act === 'save') {
        $email = strtolower(post('email'));
        if (post('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array(post('role'), $roles, true)) flash('Name, valid email and role are required.', 'danger');
        else try {
            if ($iid) q('UPDATE interviewers SET name=?,email=?,phone=?,designation=?,role=?,status=? WHERE id=? AND employer_id=?', [post('name'), $email, post('phone'), post('designation'), post('role'), post('status', 'Active'), $iid, $id]);
            else q('INSERT INTO interviewers (employer_id,name,email,phone,designation,role) VALUES (?,?,?,?,?,?)', [$id, post('name'), $email, post('phone'), post('designation'), post('role')]);
            flash('Interviewer saved.');
        } catch (PDOException $x) { flash('This email is already added for this employer.', 'danger'); }
    } elseif ($act === 'delete') {
        q('DELETE FROM interviewers WHERE id=? AND employer_id=?', [$iid, $id]); flash('Interviewer removed.');
    }
    redirect('employer/interviewers.php');
}
$edit = isset($_GET['edit']) ? row('SELECT * FROM interviewers WHERE id=? AND employer_id=?', [(int)$_GET['edit'], $id]) : null;
$list = rows('SELECT * FROM interviewers WHERE employer_id=? ORDER BY name', [$id]);
page_header('Interviewers', 'ints'); ?>
<div class="row g-3"><div class="col-md-4"><div class="card shadow-sm"><div class="card-header"><?= $edit ? 'Edit interviewer' : 'Add interviewer' ?></div><div class="card-body">
<form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="iid" value="<?= (int)($edit['id'] ?? 0) ?>">
<div class="mb-2"><label class="form-label">Name *</label><input name="name" class="form-control" value="<?= e($edit['name'] ?? '') ?>" required></div>
<div class="mb-2"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?= e($edit['email'] ?? '') ?>" required></div>
<div class="mb-2"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($edit['phone'] ?? '') ?>"></div>
<div class="mb-2"><label class="form-label">Designation</label><input name="designation" class="form-control" value="<?= e($edit['designation'] ?? '') ?>"></div>
<div class="mb-2"><label class="form-label">Interviewer role *</label><select name="role" class="form-select"><?php foreach ($roles as $r): ?><option <?= ($edit['role'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
<?php if ($edit): ?><div class="mb-2"><label class="form-label">Status</label><select name="status" class="form-select"><option <?= $edit['status'] === 'Active' ? 'selected' : '' ?>>Active</option><option <?= $edit['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option></select></div><?php endif; ?>
<button class="btn btn-brand"><?= $edit ? 'Update' : 'Add' ?></button> <?php if ($edit): ?><a href="interviewers.php" class="btn btn-link">Cancel</a><?php endif; ?></form></div></div></div>
<div class="col-md-8"><div class="card shadow-sm"><table class="table mb-0 align-middle"><thead><tr><th>Name</th><th>Role</th><th>Contact</th><th>Status</th><th></th></tr></thead>
<?php foreach ($list as $i): ?><tr><td><?= e($i['name']) ?><div class="small text-muted"><?= e($i['designation']) ?></div></td><td><span class="badge text-bg-info"><?= e($i['role']) ?></span></td><td><?= e($i['email']) ?><div class="small text-muted"><?= e($i['phone']) ?></div></td><td><?= e($i['status']) ?></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= $i['id'] ?>">Edit</a>
<form method="post" class="d-inline" onsubmit="return confirm('Remove this interviewer?')"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="iid" value="<?= $i['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
<?php endforeach; if (!$list): ?><tr><td colspan="5" class="text-muted p-3">No interviewers yet.</td></tr><?php endif; ?></table></div></div></div>
<?php page_footer();
