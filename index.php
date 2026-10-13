<?php
require_once 'includes/bootstrap.php'; require_once 'includes/layout.php';
if (user()) redirect(home_url(user()['role']));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = row('SELECT * FROM users WHERE email=?', [strtolower(post('email'))]);
    if ($u && $u['status'] === 'Active' && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        $blocked = null;
        if ($u['role'] === 'Employer') {
            $emp = row('SELECT status FROM employers WHERE id=?', [$u['employer_id']]);
            if (!$emp || $emp['status'] !== 'Approved') $blocked = 'Your employer account is ' . strtolower($emp['status'] ?? 'unavailable') . '. EmpAdmin approval is required before you can log in.';
        }
        if (!$blocked) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'employer_id' => $u['employer_id'], 'candidate_id' => $u['candidate_id']];
            q('UPDATE users SET last_login=NOW() WHERE id=?', [$u['id']]);
            redirect(home_url($u['role']));
        }
        flash($blocked, 'warning');
    } else flash('Invalid email or password.', 'danger');
}
page_header('Login'); ?>
<div class="row justify-content-center mt-5"><div class="col-md-4">
  <div class="card shadow-sm"><div class="card-body p-4">
    <h4 class="mb-1"><?= e(APP_NAME) ?></h4><p class="text-muted">Employers &amp; Admins sign in</p>
    <?php foreach (flash() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-primary w-100">Login</button>
    </form>
    <hr><a href="<?= e(url('register.php')) ?>">New employer? Register here</a><br><a href="<?= e(url('register_candidate.php')) ?>">Job seeker? Create an account</a>
  </div></div>
  <?php if (DEV_MODE): ?><p class="small text-muted mt-3">Demo: employer <code>hr@acme.example</code>, candidate <code>anjali@example.com</code>, admin <code>empadmin@example.com</code> / <code>Admin@123</code></p><?php endif; ?>
</div></div>
<?php page_footer();
