<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate();
$list = rows("SELECT * FROM notifications WHERE recipient=? AND related_type<>'otp' ORDER BY created_at DESC LIMIT 60", [$c['email']]);
page_header('Notifications', 'cnotif'); ?>
<?php foreach ($list as $n): ?><div class="card shadow-sm mb-2"><div class="card-body py-2"><div class="d-flex justify-content-between"><b><?= e($n['subject']) ?></b><span class="small text-muted"><?= e(date('d M Y, h:i A', strtotime($n['created_at']))) ?></span></div>
<div class="small text-muted"><?= nl2br(e($n['body'])) ?></div></div></div><?php endforeach; if (!$list): ?><div class="alert alert-info">No notifications yet.</div><?php endif; ?>
<?php page_footer();
