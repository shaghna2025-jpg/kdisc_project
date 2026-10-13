<?php
// Candidate sign-up: details -> email OTP -> account created
require_once 'includes/bootstrap.php'; require_once 'includes/layout.php';
if (user()) redirect('index.php');
if (isset($_GET['restart'])) { unset($_SESSION['creg']); redirect('register_candidate.php'); }
$R =& $_SESSION['creg']; $dev = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act');
    if ($act === 'start') {
        $email = strtolower(post('email')); $pw = $_POST['password'] ?? '';
        if (post('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('Enter your name and a valid email.', 'danger');
        elseif (strlen($pw) < 8) flash('Password must be at least 8 characters.', 'danger');
        elseif (val('SELECT COUNT(*) FROM users WHERE email=?', [$email])) flash('This email already has an account. Please log in.', 'danger');
        else { $R = ['name' => post('name'), 'email' => $email, 'phone' => preg_replace('/\D/', '', post('phone')), 'pw' => password_hash($pw, PASSWORD_DEFAULT)]; $R['dev'] = send_otp($email, 'email'); redirect('register_candidate.php'); }
    } elseif ($act === 'verify' && $R) {
        if (!verify_otp($R['email'], 'email', post('otp'))) flash('Invalid or expired OTP.', 'danger');
        else {
            $pdo->beginTransaction();
            try {
                $cid = val('SELECT id FROM candidates WHERE email=?', [$R['email']]);   // claim an existing profile if the employer side already holds one
                if (!$cid) { q('INSERT INTO candidates (name,email,phone) VALUES (?,?,?)', [$R['name'], $R['email'], $R['phone']]); $cid = insert_id(); }
                q('INSERT INTO users (name,email,password_hash,role,candidate_id) VALUES (?,?,?,?,?)', [$R['name'], $R['email'], $R['pw'], 'Candidate', $cid]);
                $pdo->commit(); unset($_SESSION['creg']);
                flash('Account created. Please log in and complete your profile.'); redirect('index.php');
            } catch (Throwable $t) { $pdo->rollBack(); flash('Could not create account: ' . $t->getMessage(), 'danger'); }
        }
    } elseif ($act === 'resend' && $R) { $R['dev'] = send_otp($R['email'], 'email'); flash('OTP resent.'); redirect('register_candidate.php'); }
}
$dev = DEV_MODE ? ($R['dev'] ?? null) : null;
page_header('Create account'); ?>
<div class="row justify-content-center mt-4"><div class="col-md-5"><?php foreach (flash() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
<div class="card shadow-sm"><div class="card-body p-4"><h4>Job seeker sign-up</h4>
<?php if (!$R): ?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="start">
<div class="mb-2"><label class="form-label">Full name *</label><input name="name" class="form-control" required></div>
<div class="mb-2"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
<div class="mb-2"><label class="form-label">Mobile</label><input name="phone" class="form-control"></div>
<div class="mb-3"><label class="form-label">Password * (min 8)</label><input type="password" name="password" class="form-control" minlength="8" required></div>
<button class="btn btn-primary w-100">Send email OTP</button></form>
<?php else: ?>
<p>Enter the OTP sent to <b><?= e($R['email']) ?></b>.</p><?php if ($dev): ?><div class="alert alert-info">DEV MODE OTP: <b><?= e($dev) ?></b></div><?php endif; ?>
<form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="verify"><input name="otp" maxlength="6" class="form-control w-auto" required><button class="btn btn-primary">Verify</button></form>
<form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="act" value="resend"><button class="btn btn-link p-0">Resend OTP</button></form>
<a class="small" href="?restart=1">Start over</a>
<?php endif; ?><hr><a href="<?= e(url('index.php')) ?>">Already registered? Log in</a></div></div></div></div>
<?php page_footer();
