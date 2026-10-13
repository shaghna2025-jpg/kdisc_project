<?php
// Edit profile + visual branding (logo, brand colour, tagline)
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$emp = require_employer_context(); $id = $emp['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $logo = $emp['logo'];
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) && $_FILES['logo']['size'] < 2097152 && @getimagesize($_FILES['logo']['tmp_name'])) {
            $dir = __DIR__ . '/../uploads/logos'; if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fn = "emp{$id}_" . bin2hex(random_bytes(4)) . ".$ext"; move_uploaded_file($_FILES['logo']['tmp_name'], "$dir/$fn"); $logo = "uploads/logos/$fn";
        } else flash('Logo must be an image under 2 MB.', 'danger');
    }
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', post('brand_color')) ? post('brand_color') : '#0d6efd';
    q('UPDATE employers SET mobile=?,contact_person=?,designation=?,industry=?,company_size=?,website=?,address=?,city=?,state=?,country=?,about=?,tagline=?,brand_color=?,logo=? WHERE id=?',
      [post('mobile'), post('contact_person'), post('designation'), post('industry'), post('company_size'), post('website'), post('address'), post('city'), post('state'), post('country'), post('about'), post('tagline'), $color, $logo, $id]);
    flash('Profile saved.'); redirect('employer/profile.php');
}
$v = fn($k) => e($emp[$k] ?? '');
page_header('Profile & Branding', 'profile'); ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-3"><div class="col-md-8"><div class="card shadow-sm"><div class="card-header">Company profile</div><div class="card-body row g-3">
<div class="col-md-6"><label class="form-label">Company name</label><input class="form-control" value="<?= $v('company_name') ?>" disabled></div>
<div class="col-md-6"><label class="form-label">Company ID</label><input class="form-control" value="<?= $v('company_id') ?>" disabled></div>
<div class="col-md-6"><label class="form-label">Mobile</label><input name="mobile" class="form-control" value="<?= $v('mobile') ?>"></div>
<div class="col-md-6"><label class="form-label">Contact person</label><input name="contact_person" class="form-control" value="<?= $v('contact_person') ?>"></div>
<div class="col-md-4"><label class="form-label">Designation</label><input name="designation" class="form-control" value="<?= $v('designation') ?>"></div>
<div class="col-md-4"><label class="form-label">Industry</label><input name="industry" class="form-control" value="<?= $v('industry') ?>"></div>
<div class="col-md-4"><label class="form-label">Size</label><input name="company_size" class="form-control" value="<?= $v('company_size') ?>"></div>
<div class="col-md-12"><label class="form-label">Website</label><input name="website" class="form-control" value="<?= $v('website') ?>"></div>
<div class="col-md-12"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= $v('address') ?>"></div>
<div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= $v('city') ?>"></div>
<div class="col-md-4"><label class="form-label">State</label><input name="state" class="form-control" value="<?= $v('state') ?>"></div>
<div class="col-md-4"><label class="form-label">Country</label><input name="country" class="form-control" value="<?= $v('country') ?>"></div>
<div class="col-12"><label class="form-label">About</label><textarea name="about" rows="4" class="form-control"><?= $v('about') ?></textarea></div></div></div></div>
<div class="col-md-4"><div class="card shadow-sm"><div class="card-header">Visual branding</div><div class="card-body">
<?php if ($emp['logo']): ?><img src="<?= e(url($emp['logo'])) ?>" class="img-thumbnail mb-3" style="max-height:90px"><?php endif; ?>
<label class="form-label">Logo (png/jpg, max 2 MB)</label><input type="file" name="logo" accept="image/*" class="form-control mb-3">
<label class="form-label">Brand colour</label><input type="color" name="brand_color" class="form-control form-control-color mb-3" value="<?= $v('brand_color') ?>">
<label class="form-label">Tagline</label><input name="tagline" class="form-control" value="<?= $v('tagline') ?>">
<p class="small text-muted mt-3">Your logo and colour appear in this portal, job posts, notifications and offer letters.</p></div></div>
<button class="btn btn-brand w-100 mt-3">Save profile</button></div></div></form>
<?php page_footer();
