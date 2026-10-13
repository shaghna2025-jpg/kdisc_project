<?php
require_once '../includes/bootstrap.php'; $c = require_candidate();
$o = row("SELECT o.*, j.title, e.company_name, e.logo, e.brand_color, e.tagline, e.address, e.city FROM offers o JOIN applications a ON a.id=o.application_id JOIN jobs j ON j.id=a.job_id JOIN employers e ON e.id=j.employer_id WHERE o.application_id=? AND a.candidate_id=?", [(int)($_GET['id'] ?? 0), $c['id']]);
if (!$o) { http_response_code(404); die('Offer not found'); } ?>
<!doctype html><html><head><meta charset="utf-8"><title>Offer <?= e($o['offer_no']) ?></title>
<style>body{font-family:Georgia,serif;max-width:780px;margin:30px auto;padding:30px;color:#222;line-height:1.55}header{border-bottom:4px solid <?= e($o['brand_color']) ?>;padding-bottom:12px;margin-bottom:24px;display:flex;align-items:center;gap:16px}header img{max-height:60px}h1{margin:0;font-size:22px;color:<?= e($o['brand_color']) ?>}table{border-collapse:collapse;width:100%;margin:16px 0}td{border:1px solid #ccc;padding:8px}@media print{.np{display:none}}</style></head><body>
<div class="np"><button onclick="window.print()">Print / Save as PDF</button></div>
<header><?php if ($o['logo']): ?><img src="<?= e(url($o['logo'])) ?>"><?php endif; ?><div><h1><?= e($o['company_name']) ?></h1><small><?= e($o['tagline']) ?><br><?= e($o['address']) ?> <?= e($o['city']) ?></small></div></header>
<p><b>Offer No:</b> <?= e($o['offer_no']) ?><br><b>Date:</b> <?= e(date('d M Y', strtotime($o['created_at']))) ?></p><p>Dear <?= e($c['name']) ?>,</p>
<p>We are pleased to offer you the position of <b><?= e($o['designation']) ?></b> at <?= e($o['company_name']) ?>.</p>
<table><tr><td>Designation</td><td><?= e($o['designation']) ?></td></tr><tr><td>Annual CTC</td><td>Rs. <?= number_format($o['ctc']) ?></td></tr><tr><td>Date of joining</td><td><?= e(date('d M Y', strtotime($o['joining_date']))) ?></td></tr>
<?php if ($o['valid_until']): ?><tr><td>Offer valid until</td><td><?= e(date('d M Y', strtotime($o['valid_until']))) ?></td></tr><?php endif; ?></table>
<?php if ($o['terms']): ?><p><b>Additional terms:</b> <?= nl2br(e($o['terms'])) ?></p><?php endif; ?><p>Sincerely,<br><br><b>Authorised Signatory</b><br><?= e($o['company_name']) ?></p></body></html>
