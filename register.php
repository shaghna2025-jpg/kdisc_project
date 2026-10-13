<?php
// Self registration: 1 basic details + email OTP -> 2 mobile + OTP -> 3 full details -> pending EmpAdmin approval
require_once 'includes/bootstrap.php'; require_once 'includes/layout.php';
if (user()) redirect('index.php');
if (isset($_GET['restart'])) { unset($_SESSION['reg']); redirect('register.php'); }
$R =& $_SESSION['reg']; $R = $R ?? ['step' => 1];
$step = $R['step']; $devOtp = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act');
    if ($act === 'basic') {
        $email = strtolower(post('email')); $pw = $_POST['password'] ?? ''; $cid = post('company_id');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || post('company_name') === '') flash('Enter a company name and valid email.', 'danger');
        elseif (strlen($pw) < 8) flash('Password must be at least 8 characters.', 'danger');
        elseif (val('SELECT COUNT(*) FROM employers WHERE email=?', [$email]) || val('SELECT COUNT(*) FROM users WHERE email=?', [$email])) flash('This email is already registered.', 'danger');
        elseif ($cid !== '' && val('SELECT COUNT(*) FROM employers WHERE company_id=?', [$cid])) flash('This Company ID is already registered.', 'danger');
        else {
            $R = ['step' => 1.5, 'company_name' => post('company_name'), 'company_id' => $cid, 'email' => $email, 'contact_person' => post('contact_person'), 'pw' => password_hash($pw, PASSWORD_DEFAULT)];
            $R['dev'] = send_otp($email, 'email'); redirect('register.php');
        }
    } elseif ($act === 'email_otp') {
        if (verify_otp($R['email'], 'email', post('otp'))) { $R['step'] = 2; flash('Email verified.'); redirect('register.php'); }
        flash('Invalid or expired OTP.', 'danger');
    } elseif ($act === 'resend_email') { $R['dev'] = send_otp($R['email'], 'email'); flash('OTP resent.'); redirect('register.php');
    } elseif ($act === 'mobile') {
        $m = preg_replace('/\D/', '', post('mobile'));
        if (strlen($m) < 10) flash('Enter a valid mobile number.', 'danger');
        else { $R['mobile'] = $m; $R['step'] = 2.5; $R['dev'] = send_otp($m, 'mobile'); redirect('register.php'); }
    } elseif ($act === 'mobile_otp') {
        if (verify_otp($R['mobile'], 'mobile', post('otp'))) { $R['step'] = 3; flash('Mobile verified.'); redirect('register.php'); }
        flash('Invalid or expired OTP.', 'danger');
    } elseif ($act === 'resend_mobile') { $R['dev'] = send_otp($R['mobile'], 'mobile'); flash('OTP resent.'); redirect('register.php');
    } elseif ($act === 'full' && $R['step'] == 3) {
        $pdo->beginTransaction();
        try {
            q('INSERT INTO employers (company_id,company_name,email,mobile,email_verified,mobile_verified,contact_person,designation,industry,company_size,website,address,city,state,country,about,status,source)
               VALUES (?,?,?,?,1,1,?,?,?,?,?,?,?,?,?,?,"Pending","Self")',
              [nullable($R['company_id']), $R['company_name'], $R['email'], $R['mobile'], post('contact_person', $R['contact_person']), post('designation'), post('industry'), post('company_size'),
               post('website'), post('address'), post('city'), post('state'), post('country', 'India'), post('about')]);
            $eid = insert_id();
            q('INSERT INTO users (name,email,password_hash,role,employer_id) VALUES (?,?,?,?,?)', [post('contact_person', $R['contact_person']) ?: $R['company_name'], $R['email'], $R['pw'], 'Employer', $eid]);
            $pdo->commit();
        } catch (Throwable $t) { $pdo->rollBack(); flash('Registration failed: ' . $t->getMessage(), 'danger'); redirect('register.php'); }
        notify($R['email'], 'Registration received', 'Thank you for registering. EmpAdmin will review your details and you will be notified on approval.', 'employer', $eid);
        unset($_SESSION['reg']); $step = 4;
    }
    $step = $_SESSION['reg']['step'] ?? 4;
}
$dev = DEV_MODE ? ($R['dev'] ?? null) : null;
page_header('Employer Registration'); ?>
<div class="row justify-content-center"><div class="col-md-7">
<?php foreach (flash() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
<div class="card shadow-sm"><div class="card-body p-4">
<h4>Employer Registration</h4>
<div class="progress mb-4" style="height:6px"><div class="progress-bar" style="width:<?= $step == 4 ? 100 : (int)floor($step) * 33 ?>%"></div></div>
<?php if ($step == 1): ?>
  <h6>Step 1 - Basic details</h6>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="basic">
    <div class="row g-3">
      <div class="col-md-8"><label class="form-label">Company name *</label><input name="company_name" class="form-control" required></div>
      <div class="col-md-4"><label class="form-label">Company ID (CIN/GST) <small class="text-muted">preferred</small></label><input name="company_id" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Contact person</label><input name="contact_person" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Official email *</label><input type="email" name="email" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">Password * (min 8)</label><input type="password" name="password" class="form-control" minlength="8" required></div>
    </div><button class="btn btn-primary mt-3">Send email OTP</button>
  </form>
<?php elseif ($step == 1.5): ?>
  <h6>Verify email: <?= e($R['email']) ?></h6>
  <?php if ($dev): ?><div class="alert alert-info">DEV MODE OTP: <b><?= e($dev) ?></b></div><?php endif; ?>
  <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="email_otp">
    <input name="otp" class="form-control w-auto" maxlength="6" placeholder="6-digit OTP" required><button class="btn btn-primary">Verify</button></form>
  <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="act" value="resend_email"><button class="btn btn-link p-0">Resend OTP</button></form>
<?php elseif ($step == 2): ?>
  <h6>Step 2 - Mobile verification</h6>
  <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="mobile">
    <input name="mobile" class="form-control w-auto" placeholder="Mobile number" required><button class="btn btn-primary">Send OTP</button></form>
<?php elseif ($step == 2.5): ?>
  <h6>Verify mobile: <?= e($R['mobile']) ?></h6>
  <?php if ($dev): ?><div class="alert alert-info">DEV MODE OTP: <b><?= e($dev) ?></b></div><?php endif; ?>
  <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="mobile_otp">
    <input name="otp" class="form-control w-auto" maxlength="6" placeholder="6-digit OTP" required><button class="btn btn-primary">Verify</button></form>
  <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="act" value="resend_mobile"><button class="btn btn-link p-0">Resend OTP</button></form>
<?php elseif ($step == 3): ?>
  <h6>Step 3 - Full company details</h6>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="full"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">Contact person</label><input name="contact_person" class="form-control" value="<?= e($R['contact_person']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Designation</label><input name="designation" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Industry</label><input name="industry" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Company size</label><select name="company_size" class="form-select"><option>1-10</option><option>11-50</option><option>51-200</option><option>201-1000</option><option>1000+</option></select></div>
    <div class="col-md-12"><label class="form-label">Website</label><input name="website" class="form-control"></div>
    <div class="col-md-12"><label class="form-label">Address</label><input name="address" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">State</label><input name="state" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Country</label><input name="country" class="form-control" value="India"></div>
    <div class="col-12"><label class="form-label">About the company</label><textarea name="about" rows="3" class="form-control"></textarea></div>
  </div><button class="btn btn-success mt-3">Submit for approval</button></form>
<?php else: ?>
  <div class="alert alert-success"><b>Registration submitted.</b> EmpAdmin will review and approve your account. You will be notified by email.</div>
  <a href="<?= e(url('index.php')) ?>" class="btn btn-primary">Back to login</a>
<?php endif; ?>
<?php if ($step != 4 && $step != 1): ?><a class="small d-block mt-3" href="?restart=1">Start over</a><?php endif; ?>
</div></div></div></div>
<?php page_footer();
