<?php
// Track one application: stages, interviews, offer (accept/decline), withdraw, feedback
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id']; $aid = (int)($_GET['id'] ?? 0);
$A = row('SELECT a.*, j.title, j.id jid, e.company_name, e.email emp_email FROM applications a JOIN jobs j ON j.id=a.job_id JOIN employers e ON e.id=j.employer_id WHERE a.id=? AND a.candidate_id=?', [$aid, $cid]);
if (!$A) { flash('Application not found.', 'danger'); redirect('candidate/applications.php'); }
$offer = row('SELECT * FROM offers WHERE application_id=?', [$aid]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act');
    if ($act === 'withdraw' && in_array($A['status'], ['Applied', 'Shortlisted', 'In Process', 'On Hold', 'Selected'], true)) {
        q("UPDATE applications SET status='Withdrawn' WHERE id=?", [$aid]); q("UPDATE interviews SET schedule_status='Cancelled' WHERE application_id=? AND schedule_status='Scheduled'", [$aid]);
        notify($A['emp_email'], "Application withdrawn: {$c['name']}", "{$c['name']} withdrew the application for {$A['title']}.", 'application', $aid); flash('Application withdrawn.');
    } elseif ($act === 'offer' && $offer && $offer['status'] === 'Sent' && in_array(post('resp'), ['Accepted', 'Declined'], true)) {
        q('UPDATE offers SET status=? WHERE application_id=?', [post('resp'), $aid]);
        q('UPDATE applications SET status=? WHERE id=?', [post('resp') === 'Accepted' ? 'Offer Accepted' : 'Offer Declined', $aid]);
        notify($A['emp_email'], "Offer " . strtolower(post('resp')) . ": {$c['name']}", "{$c['name']} has " . strtolower(post('resp')) . " offer {$offer['offer_no']} for {$A['title']}.", 'offer', $offer['id']);
        flash('Your response has been sent to the employer.');
    } elseif ($act === 'feedback' && in_array($A['status'], ['Rejected', 'Selected', 'Offered', 'Offer Accepted', 'Offer Declined'], true)) {
        q("INSERT INTO feedback (application_id,from_type,rating,comments,submitted_at) VALUES (?, 'Candidate', ?, ?, NOW()) ON DUPLICATE KEY UPDATE rating=VALUES(rating), comments=VALUES(comments), submitted_at=NOW()", [$aid, max(1, min(5, (int)post('rating'))), post('comments')]);
        flash('Thank you for your feedback.');
    }
    redirect("candidate/application_view.php?id=$aid");
}
$ints = rows('SELECT i.*, r.round_name, r.round_no FROM interviews i JOIN job_rounds r ON r.id=i.round_id WHERE i.application_id=? ORDER BY i.scheduled_at', [$aid]);
$fb = row("SELECT * FROM feedback WHERE application_id=? AND from_type='Candidate'", [$aid]);
$hr = row('SELECT status FROM hr_verifications WHERE application_id=?', [$aid]);
$stages = ['Applied', 'Shortlisted', 'In Process', 'Selected', 'Offered', 'Offer Accepted']; $idx = array_search($A['status'], $stages, true);
page_header($A['title'], 'capps'); ?>
<a href="applications.php" class="small">&larr; All applications</a>
<div class="d-flex flex-wrap gap-2 my-3 align-items-center"><?php foreach ($stages as $k => $s): $done = $idx !== false && $k <= $idx; ?>
<span class="badge <?= $done ? 'text-bg-success' : 'text-bg-light border text-muted' ?> p-2"><?= $done ? '&#10003; ' : '' ?><?= e($s) ?></span><?php if ($k < count($stages) - 1): ?><i class="bi bi-chevron-right text-muted"></i><?php endif; endforeach; ?>
<?php if (in_array($A['status'], ['Rejected', 'On Hold', 'Withdrawn', 'Offer Declined'], true)): ?><?= status_badge($A['status']) ?><?php endif; ?></div>
<p class="text-muted"><?= e($A['company_name']) ?> | applied <?= e(date('d M Y', strtotime($A['applied_at']))) ?> | <a href="job_view.php?id=<?= $A['jid'] ?>">view job</a> | <a href="gap.php?job=<?= $A['jid'] ?>">skill gap</a></p>
<div class="row g-3"><div class="col-md-7"><div class="card shadow-sm mb-3"><div class="card-header">Interviews</div><table class="table mb-0 align-middle">
<?php foreach ($ints as $i): ?><tr><td><?= e($i['round_no']) ?>. <?= e($i['round_name']) ?></td><td><?= e(date('D d M, h:i A', strtotime($i['scheduled_at']))) ?></td><td><?= e($i['mode']) ?><div class="small text-muted"><?= e($i['venue_or_link']) ?></div></td>
<td><?= status_badge($i['schedule_status']) ?> <?= $i['schedule_status'] === 'Completed' ? status_badge($i['result']) : '' ?></td></tr>
<?php endforeach; if (!$ints): ?><tr><td class="text-muted">No interviews scheduled yet. You'll be notified when one is.</td></tr><?php endif; ?></table></div>
<?php if ($fb || in_array($A['status'], ['Rejected', 'Selected', 'Offered', 'Offer Accepted', 'Offer Declined'], true)): ?>
<div class="card shadow-sm"><div class="card-header">Your feedback on the process</div><div class="card-body"><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="feedback">
<select name="rating" class="form-select w-auto mb-2"><?php for ($n = 5; $n >= 1; $n--): ?><option value="<?= $n ?>" <?= ($fb['rating'] ?? 0) == $n ? 'selected' : '' ?>><?= $n ?> star</option><?php endfor; ?></select>
<textarea name="comments" rows="3" class="form-control mb-2" placeholder="How was your experience?"><?= e($fb['comments'] ?? '') ?></textarea><button class="btn btn-sm btn-primary"><?= $fb && $fb['submitted_at'] ? 'Update feedback' : 'Submit feedback' ?></button></form></div></div><?php endif; ?></div>
<div class="col-md-5"><?php if ($offer): ?><div class="card shadow-sm mb-3 border-success"><div class="card-header d-flex justify-content-between">Offer letter <?= status_badge($offer['status'] === 'Sent' ? 'Offered' : $offer['status']) ?></div><div class="card-body">
<p class="mb-1"><b><?= e($offer['designation']) ?></b> - <?= e($A['company_name']) ?></p><p class="mb-1">CTC: Rs. <?= number_format($offer['ctc']) ?> p.a.</p><p class="mb-1">Joining: <?= e(date('d M Y', strtotime($offer['joining_date']))) ?></p>
<?php if ($offer['valid_until']): ?><p class="mb-1">Valid until: <?= e(date('d M Y', strtotime($offer['valid_until']))) ?></p><?php endif; ?>
<a class="btn btn-sm btn-outline-primary" target="_blank" href="offer_print.php?id=<?= $aid ?>">View / print letter</a>
<?php if ($offer['status'] === 'Sent'): ?><form method="post" class="mt-3 d-flex gap-2" onsubmit="return confirm('Send this response? It cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="act" value="offer">
<button name="resp" value="Accepted" class="btn btn-success">Accept offer</button><button name="resp" value="Declined" class="btn btn-outline-dark">Decline</button></form><?php endif; ?></div></div>
<?php elseif ($A['status'] === 'Selected'): ?><div class="alert alert-success">You have been selected. <?= $hr && $hr['status'] === 'Verified' ? 'Verification is complete - your offer will follow.' : 'The employer is completing HR verification; keep your documents ready.' ?></div><?php endif; ?>
<?php if (in_array($A['status'], ['Applied', 'Shortlisted', 'In Process', 'On Hold', 'Selected'], true)): ?>
<form method="post" onsubmit="return confirm('Withdraw this application?')"><?= csrf_field() ?><input type="hidden" name="act" value="withdraw"><button class="btn btn-outline-danger btn-sm">Withdraw application</button></form><?php endif; ?></div></div>
<?php page_footer();
