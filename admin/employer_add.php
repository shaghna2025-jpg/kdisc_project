<?php
// EmpAdmin: add a single employer (Add Employer button) or edit an existing one (?id=)
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(['EmpAdmin', 'SuperAdmin']);
$id = (int)($_GET['id'] ?? 0); $e = $id ? row('SELECT * FROM employers WHERE id=?', [$id]) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = ['company_id' => nullable(post('company_id')), 'company_name' => post('company_name'), 'email' => strtolower(post('email')), 'mobile' => post('mobile'),
          'contact_person' => post('contact_person'), 'designation' => post('designation'), 'industry' => post('industry'), 'company_size' => post('company_size'),
          'website' => post('website'), 'address' => post('address'), 'city' => post('city'), 'state' => post('state'), 'country' => post('country', 'India'), 'about' => post('about')];
    if ($f['company_name'] === '' || !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) flash('Company name and a valid email are required.', 'danger');
    else try {
        if ($e) {
            q('UPDATE employers SET ' . implode(',', array_map(fn($k) => "$k=?", array_keys($f))) . ' WHERE id=?', [...array_values($f), $id]);
            flash('Employer updated.'); redirect('admin/employers.php?view=' . $id);
        }
        $pw = bin2hex(random_bytes(4)) . 'A1!';
        $pdo->beginTransaction();
        q('INSERT INTO employers (' . implode(',', array_keys($f)) . ',email_verified,mobile_verified,status,source,approved_by,approved_at) VALUES (' . implode(',', array_fill(0, count($f), '?')) . ',1,1,"Approved","EmpAdmin",?,NOW())', [...array_values($f), user()['id']]);
        $nid = insert_id();
        q('INSERT INTO users (name,email,password_hash,role,employer_id) VALUES (?,?,?,?,?)', [$f['contact_person'] ?: $f['company_name'], $f['email'], password_hash($pw, PASSWORD_DEFAULT), 'Employer', $nid]);
        $pdo->commit();
        notify($f['email'], 'Your employer account', "Your account was created. Login: {$f['email']} Temporary password: $pw (please change it).", 'employer', $nid);
        flash('Employer added and approved. Temporary password: ' . $pw . ' (also emailed).'); redirect('admin/employers.php');
    } catch (Throwable $t) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('Could not save (duplicate email/company ID?): ' . $t->getMessage(), 'danger'); }
    $e = $f;
}
$v = fn($k) => e($e[$k] ?? '');
page_header($id ? 'Edit Employer' : 'Add Employer', 'empadd'); ?>
<div class="card shadow-sm"><div class="card-body"><form method="post"><?= csrf_field() ?><div class="row g-3">
<div class="col-md-6"><label class="form-label">Company name *</label><input name="company_name" class="form-control" value="<?= $v('company_name') ?>" required></div>
<div class="col-md-3"><label class="form-label">Company ID</label><input name="company_id" class="form-control" value="<?= $v('company_id') ?>"></div>
<div class="col-md-3"><label class="form-label">Mobile</label><input name="mobile" class="form-control" value="<?= $v('mobile') ?>"></div>
<div class="col-md-4"><label class="form-label">Email (login) *</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>" required></div>
<div class="col-md-4"><label class="form-label">Contact person</label><input name="contact_person" class="form-control" value="<?= $v('contact_person') ?>"></div>
<div class="col-md-4"><label class="form-label">Designation</label><input name="designation" class="form-control" value="<?= $v('designation') ?>"></div>
<div class="col-md-4"><label class="form-label">Industry</label><input name="industry" class="form-control" value="<?= $v('industry') ?>"></div>
<div class="col-md-4"><label class="form-label">Company size</label><input name="company_size" class="form-control" value="<?= $v('company_size') ?>"></div>
<div class="col-md-4"><label class="form-label">Website</label><input name="website" class="form-control" value="<?= $v('website') ?>"></div>
<div class="col-md-12"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= $v('address') ?>"></div>
<div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= $v('city') ?>"></div>
<div class="col-md-4"><label class="form-label">State</label><input name="state" class="form-control" value="<?= $v('state') ?>"></div>
<div class="col-md-4"><label class="form-label">Country</label><input name="country" class="form-control" value="<?= $v('country') ?: 'India' ?>"></div>
<div class="col-12"><label class="form-label">About</label><textarea name="about" rows="3" class="form-control"><?= $v('about') ?></textarea></div>
</div><button class="btn btn-primary mt-3"><?= $id ? 'Save changes' : 'Add employer' ?></button></form></div></div>
<?php page_footer();
