<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(); if (!is_admin()) redirect(home_url(user()['role']));
$s = [
 'Pending approvals' => val("SELECT COUNT(*) FROM employers WHERE status='Pending'"),
 'Approved employers' => val("SELECT COUNT(*) FROM employers WHERE status='Approved'"),
 'Published jobs' => val("SELECT COUNT(*) FROM jobs WHERE status='Published'"),
 'Applications' => val('SELECT COUNT(*) FROM applications'),
 'Interviews upcoming' => val("SELECT COUNT(*) FROM interviews WHERE schedule_status='Scheduled' AND scheduled_at>=NOW()"),
 'Offers made' => val('SELECT COUNT(*) FROM offers'),
];
$pending = rows("SELECT * FROM employers WHERE status='Pending' ORDER BY created_at LIMIT 10");
page_header('Admin Dashboard', 'adash'); ?>
<div class="row g-3 mb-4"><?php foreach ($s as $k => $v): ?>
  <div class="col-md-2"><div class="card stat shadow-sm"><div class="card-body"><div class="text-muted small"><?= e($k) ?></div><div class="fs-3 fw-bold"><?= (int)$v ?></div></div></div></div>
<?php endforeach; ?></div>
<?php if (can_manage_employers()): ?>
<div class="card shadow-sm"><div class="card-header">Employers awaiting approval</div>
<table class="table mb-0"><tr><th>Company</th><th>Email</th><th>Registered</th><th></th></tr>
<?php foreach ($pending as $p): ?><tr><td><?= e($p['company_name']) ?></td><td><?= e($p['email']) ?></td><td><?= e($p['created_at']) ?></td>
<td><a class="btn btn-sm btn-primary" href="<?= e(url('admin/employers.php?view=' . $p['id'])) ?>">Review</a></td></tr>
<?php endforeach; if (!$pending): ?><tr><td colspan="4" class="text-muted">Nothing pending.</td></tr><?php endif; ?></table></div>
<?php else: ?><div class="alert alert-info">Your role (<?= e(user()['role']) ?>) has no employer-module tasks.</div><?php endif;
page_footer();
