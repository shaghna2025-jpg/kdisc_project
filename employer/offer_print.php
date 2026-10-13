<?php
// Printable offer letter with employer branding
require_once '../includes/bootstrap.php';
$emp = require_employer_context();
$o = row('SELECT o.*, c.name, c.email, c.location, j.title FROM offers o JOIN applications a ON a.id=o.application_id JOIN candidates c ON c.id=a.candidate_id JOIN jobs j ON j.id=a.job_id WHERE o.application_id=? AND j.employer_id=?', [(int)($_GET['id'] ?? 0), $emp['id']]);
if (!$o) { http_response_code(404); die('Offer not found'); } ?>
<!doctype html><html><head><meta charset="utf-8"><title>Offer <?= e($o['offer_no']) ?></title>
<style>body{font-family:Georgia,serif;max-width:780px;margin:30px auto;padding:30px;color:#222;line-height:1.55}header{border-bottom:4px solid <?= e($emp['brand_color']) ?>;padding-bottom:12px;margin-bottom:24px;display:flex;align-items:center;gap:16px}
header img{max-height:60px}h1{margin:0;font-size:22px;color:<?= e($emp['brand_color']) ?>}table{border-collapse:collapse;width:100%;margin:16px 0}td{border:1px solid #ccc;padding:8px}.no-print{margin-bottom:16px}@media print{.no-print{display:none}}</style></head><body>
<div class="no-print"><button onclick="window.print()">Print / Save as PDF</button></div>
<header><?php if ($emp['logo']): ?><img src="<?= e(url($emp['logo'])) ?>"><?php endif; ?><div><h1><?= e($emp['company_name']) ?></h1><small><?= e($emp['tagline']) ?><br><?= e($emp['address']) ?> <?= e($emp['city']) ?></small></div></header>
<p><b>Offer No:</b> <?= e($o['offer_no']) ?><br><b>Date:</b> <?= e(date('d M Y', strtotime($o['created_at']))) ?></p>
<p>Dear <?= e($o['name']) ?>,</p>
<p>We are pleased to offer you the position of <b><?= e($o['designation']) ?></b> at <?= e($emp['company_name']) ?>. We were impressed by your performance throughout the selection process and look forward to you joining us.</p>
<table><tr><td>Designation</td><td><?= e($o['designation']) ?></td></tr><tr><td>Annual CTC</td><td>Rs. <?= number_format($o['ctc']) ?></td></tr><tr><td>Date of joining</td><td><?= e(date('d M Y', strtotime($o['joining_date']))) ?></td></tr>
<?php if ($o['valid_until']): ?><tr><td>Offer valid until</td><td><?= e(date('d M Y', strtotime($o['valid_until']))) ?></td></tr><?php endif; ?></table>
<?php if ($o['terms']): ?><p><b>Additional terms:</b> <?= nl2br(e($o['terms'])) ?></p><?php endif; ?>
<p>This offer is subject to satisfactory verification of your documents and background. Please confirm your acceptance by the validity date.</p>
<p>Sincerely,<br><br><b>Authorised Signatory</b><br><?= e($emp['company_name']) ?></p></body></html>
