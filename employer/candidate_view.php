<?php
// Biodata, screening, interview scheduling, round results, HR verification, offer, feedback
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$emp = require_employer_context(); $eid = $emp['id'];
$aid = (int)($_GET['id'] ?? 0);
$A = row('SELECT a.*, c.*, a.id app_id, a.status app_status, j.title, j.id jid, j.vacancies FROM applications a JOIN candidates c ON c.id=a.candidate_id JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.employer_id=?', [$aid, $eid]);
if (!$A) { flash('Application not found.', 'danger'); redirect('employer/candidates.php'); }
$back = "employer/candidate_view.php?id=$aid";
$rounds = rows('SELECT r.*, i.name int_name FROM job_rounds r LEFT JOIN interviewers i ON i.id=r.interviewer_id WHERE r.job_id=? ORDER BY round_no', [$A['jid']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act');
    $msgCand = fn($subj, $body) => notify($A['email'], $subj, "Dear {$A['name']},\n\n$body\n\n- {$emp['company_name']}", 'application', $aid);

    if ($act === 'screen') { $s = screen_application($aid); flash("Screening complete: $s%."); }

    elseif ($act === 'status' && in_array(post('status'), ['Shortlisted', 'On Hold', 'Rejected', 'Selected'], true)) {
        q('UPDATE applications SET status=? WHERE id=?', [post('status'), $aid]); $msgCand("Application update - {$A['title']}", 'Your application status is now: ' . post('status') . '.'); flash('Status updated.');
    }

    elseif ($act === 'schedule') {
        $rid = (int)post('round_id'); $r = row('SELECT * FROM job_rounds WHERE id=? AND job_id=?', [$rid, $A['jid']]);
        $when = str_replace('T', ' ', post('scheduled_at')) . ':00'; $mode = post('mode') === 'Physical' ? 'Physical' : 'Virtual';
        if (!$r || !strtotime($when) || strtotime($when) < time()) flash('Pick a valid round and a future date/time.', 'danger');
        else {
            $iv = (int)post('interviewer_id') ?: $r['interviewer_id'];
            $iv = $iv && val('SELECT COUNT(*) FROM interviewers WHERE id=? AND employer_id=?', [$iv, $eid]) ? $iv : null;
            q('INSERT INTO interviews (application_id,round_id,interviewer_id,mode,scheduled_at,venue_or_link) VALUES (?,?,?,?,?,?)', [$aid, $rid, $iv, $mode, $when, post('venue_or_link')]); $newIid = insert_id();
            q("UPDATE applications SET status='In Process' WHERE id=? AND status IN ('Applied','Shortlisted','On Hold')", [$aid]);
            $nice = date('D, d M Y h:i A', strtotime($when)); $where = $mode . ': ' . post('venue_or_link');
            $msgCand("Interview scheduled - {$A['title']}", "Your {$r['round_name']} is scheduled on $nice.\n$where");
            if ($iv) { $I = row('SELECT * FROM interviewers WHERE id=?', [$iv]); notify($I['email'], "Interview assigned: {$A['name']}", "You are scheduled to conduct {$r['round_name']} for {$A['title']} with {$A['name']} on $nice.\n$where", 'interview', $newIid); }
            flash('Interview scheduled. Candidate and interviewer notified.');
        }
    }

    elseif ($act === 'round_result') {
        $iid = (int)post('interview_id'); $res = post('result');
        $I = row('SELECT i.*, r.round_name, r.round_no FROM interviews i JOIN job_rounds r ON r.id=i.round_id WHERE i.id=? AND i.application_id=?', [$iid, $aid]);
        if ($I && in_array($res, ['Passed', 'Failed', 'On Hold'], true)) {
            q("UPDATE interviews SET result=?, score=?, remarks=?, schedule_status='Completed' WHERE id=?", [$res, nullable(post('score')), post('remarks'), $iid]);
            $isLast = !val('SELECT COUNT(*) FROM job_rounds WHERE job_id=? AND round_no>?', [$A['jid'], $I['round_no']]);
            if ($res === 'Failed') $new = 'Rejected'; elseif ($res === 'On Hold') $new = 'On Hold'; else $new = $isLast ? 'Selected' : 'In Process';
            q('UPDATE applications SET status=? WHERE id=?', [$new, $aid]);
            if ($new === 'Selected') q('INSERT IGNORE INTO hr_verifications (application_id) VALUES (?)', [$aid]);
            $txt = ['Passed' => 'you have cleared', 'Failed' => 'unfortunately you were not selected in', 'On Hold' => 'your result is on hold for']; 
            $msgCand("Result: {$I['round_name']} - {$A['title']}", "After the {$I['round_name']}, {$txt[$res]} this round." . ($res === 'Passed' && !$isLast ? ' We will contact you about the next round.' : '') . ($new === 'Selected' ? ' You have been selected pending HR verification.' : ''));
            flash("Round result saved. Application is now $new.");
        }
    }

    elseif ($act === 'cancel_interview') {
        $I = row("SELECT i.*, r.round_name FROM interviews i JOIN job_rounds r ON r.id=i.round_id WHERE i.id=? AND i.application_id=? AND i.schedule_status='Scheduled'", [(int)post('interview_id'), $aid]);
        if ($I) { q("UPDATE interviews SET schedule_status='Cancelled' WHERE id=?", [$I['id']]); $msgCand('Interview cancelled', "Your {$I['round_name']} on " . date('d M Y h:i A', strtotime($I['scheduled_at'])) . ' has been cancelled. A new schedule will follow.'); flash('Interview cancelled.'); }
    }

    elseif ($act === 'hr_verify') {
        $stt = in_array(post('hr_status'), ['Pending', 'Verified', 'Failed'], true) ? post('hr_status') : 'Pending';
        q('INSERT INTO hr_verifications (application_id,status,docs_verified,background_verified,remarks,verified_by,verified_at) VALUES (?,?,?,?,?,?,?)
           ON DUPLICATE KEY UPDATE status=VALUES(status),docs_verified=VALUES(docs_verified),background_verified=VALUES(background_verified),remarks=VALUES(remarks),verified_by=VALUES(verified_by),verified_at=VALUES(verified_at)',
          [$aid, $stt, isset($_POST['docs']) ? 1 : 0, isset($_POST['bg']) ? 1 : 0, post('hr_remarks'), user()['id'], $stt === 'Pending' ? null : date('Y-m-d H:i:s')]);
        flash('HR verification saved.');
    }

    elseif ($act === 'offer') {
        $hr = row('SELECT status FROM hr_verifications WHERE application_id=?', [$aid]);
        if (!$hr || $hr['status'] !== 'Verified') flash('HR verification must be marked Verified before generating an offer.', 'warning');
        elseif (val('SELECT COUNT(*) FROM offers WHERE application_id=?', [$aid])) flash('Offer already generated.', 'warning');
        elseif ((int)post('ctc') <= 0 || !strtotime(post('joining_date'))) flash('Enter a valid CTC and joining date.', 'danger');
        else {
            $no = 'OFR-' . date('Y') . '-' . str_pad((string)$aid, 5, '0', STR_PAD_LEFT);
            q('INSERT INTO offers (application_id,offer_no,designation,ctc,joining_date,valid_until,terms,status,created_by) VALUES (?,?,?,?,?,?,?,"Sent",?)',
              [$aid, $no, post('designation', $A['title']), (int)post('ctc'), post('joining_date'), nullable(post('valid_until')), post('terms'), user()['id']]);
            q("UPDATE applications SET status='Offered' WHERE id=?", [$aid]);
            $msgCand("Offer letter - {$A['title']}", "Congratulations! We are pleased to offer you the position of " . post('designation', $A['title']) . ". Annual CTC: Rs. " . number_format((int)post('ctc')) . ". Joining date: " . post('joining_date') . ". Offer no: $no.\nPlease log in to your candidate account to view and respond to the offer letter.");
            flash("Offer $no generated and sent to the candidate.");
        }
    }

    elseif ($act === 'offer_response' && in_array(post('resp'), ['Accepted', 'Declined'], true)) {
        q('UPDATE offers SET status=? WHERE application_id=?', [post('resp'), $aid]);
        q('UPDATE applications SET status=? WHERE id=?', [post('resp') === 'Accepted' ? 'Offer Accepted' : 'Offer Declined', $aid]); flash('Offer response recorded.');
    }

    elseif ($act === 'feedback_request') {
        if (!val("SELECT COUNT(*) FROM feedback WHERE application_id=? AND from_type='Candidate'", [$aid])) {
            $tok = bin2hex(random_bytes(20));
            q("INSERT INTO feedback (application_id,from_type,token) VALUES (?, 'Candidate', ?)", [$aid, $tok]);
            $msgCand('Share your interview experience', 'Please give us feedback about your interview process: ' . (isset($_SERVER['HTTP_HOST']) ? (($_SERVER['HTTPS'] ?? '') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . url('feedback.php?t=' . $tok));
            flash('Feedback link sent to the candidate.');
        } else flash('Feedback already requested.', 'warning');
    }

    elseif ($act === 'employer_feedback') {
        $rt = max(1, min(5, (int)post('rating')));
        q("INSERT INTO feedback (application_id,from_type,rating,comments,submitted_at) VALUES (?, 'Employer', ?, ?, NOW()) ON DUPLICATE KEY UPDATE rating=VALUES(rating), comments=VALUES(comments), submitted_at=NOW()", [$aid, $rt, post('comments')]);
        flash('Employer feedback saved.');
    }
    redirect($back);
}

$ints = rows('SELECT i.*, r.round_name, r.round_no, iv.name int_name FROM interviews i JOIN job_rounds r ON r.id=i.round_id LEFT JOIN interviewers iv ON iv.id=i.interviewer_id WHERE i.application_id=? ORDER BY i.scheduled_at', [$aid]);
$hr = row('SELECT * FROM hr_verifications WHERE application_id=?', [$aid]);
$offer = row('SELECT * FROM offers WHERE application_id=?', [$aid]);
$fbs = rows('SELECT * FROM feedback WHERE application_id=?', [$aid]); $fb = [];
foreach ($fbs as $f) $fb[$f['from_type']] = $f;
$allInts = rows("SELECT id,name,role FROM interviewers WHERE employer_id=? AND status='Active' ORDER BY name", [$eid]);
page_header('Candidate: ' . $A['name'], 'cands'); ?>
<a href="candidates.php?job=<?= $A['jid'] ?>" class="small">&larr; Back to <?= e($A['title']) ?> candidates</a>
<div class="row g-3 mt-1">
<div class="col-md-4">
 <div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between">Biodata <?= status_badge($A['app_status']) ?></div><div class="card-body small">
  <h5><?= e($A['name']) ?></h5>
  <?php foreach (['email' => 'Email', 'phone' => 'Phone', 'location' => 'Location', 'dob' => 'DOB', 'gender' => 'Gender', 'education' => 'Education', 'experience_years' => 'Experience (yrs)', 'current_company' => 'Current company'] as $k => $l): ?>
   <div class="d-flex justify-content-between border-bottom py-1"><span class="text-muted"><?= $l ?></span><span><?= e($A[$k]) ?: '-' ?></span></div><?php endforeach; ?>
  <div class="mt-2"><span class="text-muted">Skills</span><div><?php foreach (array_filter(array_map('trim', explode(',', (string)$A['skills']))) as $s): ?><span class="badge text-bg-light border"><?= e($s) ?></span> <?php endforeach; ?></div></div>
  <p class="mt-2 mb-1"><?= nl2br(e($A['summary'])) ?></p>
  <?php if ($A['resume_path']): ?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e(url($A['resume_path'])) ?>"><i class="bi bi-file-earmark-person"></i> Resume</a><?php endif; ?></div></div>
 <div class="card shadow-sm"><div class="card-header">Screening <?= $A['screening_score'] !== null ? '- ' . (int)$A['screening_score'] . '%' : '' ?></div><div class="card-body small">
  <p><?= e($A['screening_notes'] ?: 'Not screened yet.') ?></p>
  <form method="post" class="mb-2"><?= csrf_field() ?><input type="hidden" name="act" value="screen"><button class="btn btn-sm btn-outline-primary">Run screening</button></form>
  <form method="post" class="d-flex flex-wrap gap-1"><?= csrf_field() ?><input type="hidden" name="act" value="status"><?php foreach (['Shortlisted' => 'info', 'On Hold' => 'warning', 'Rejected' => 'danger'] as $s => $c): ?><button name="status" value="<?= $s ?>" class="btn btn-sm btn-<?= $c ?>"><?= $s ?></button><?php endforeach; ?></form></div></div>
 <?php $G = gap_analysis($A['candidate_id'], $A['jid']); if ($G['items']): ?>
 <div class="card shadow-sm mt-3"><div class="card-header d-flex justify-content-between">Skill match <?= match_badge($G['score']) ?></div><table class="table table-sm mb-0 small">
  <?php foreach ($G['items'] as $gi): ?><tr><td><?= e($gi['name']) ?><?= $gi['mandatory'] ? ' *' : '' ?><?= $gi['verified'] ? ' <span class="badge text-bg-success">Verified</span>' : '' ?></td><td>need <?= (int)$gi['rl'] ?> / has <?= (int)$gi['cl'] ?></td><td><?= skill_state_badge($gi['state']) ?></td></tr><?php endforeach; ?></table></div>
 <?php endif; ?>
</div>

<div class="col-md-8">
 <div class="card shadow-sm mb-3"><div class="card-header">Interview schedule &amp; round status</div><div class="card-body">
 <table class="table table-sm align-middle"><thead><tr><th>Round</th><th>When</th><th>Mode</th><th>Interviewer</th><th>Status / Result</th><th></th></tr></thead>
 <?php foreach ($ints as $i): ?><tr><td><?= e($i['round_no']) ?>. <?= e($i['round_name']) ?></td><td><?= e(date('d M, h:i A', strtotime($i['scheduled_at']))) ?></td><td><?= e($i['mode']) ?><div class="small text-muted"><?= e($i['venue_or_link']) ?></div></td><td><?= e($i['int_name'] ?: '-') ?></td>
 <td><?= status_badge($i['schedule_status']) ?> <?= $i['schedule_status'] === 'Completed' ? status_badge($i['result']) . ($i['score'] !== null ? ' ' . (int)$i['score'] . '/10' : '') : '' ?><div class="small text-muted"><?= e($i['remarks']) ?></div></td>
 <td class="text-nowrap"><?php if ($i['schedule_status'] === 'Scheduled'): ?>
   <button class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#res<?= $i['id'] ?>">Update</button>
   <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="act" value="cancel_interview"><input type="hidden" name="interview_id" value="<?= $i['id'] ?>"><button class="btn btn-sm btn-outline-danger">Cancel</button></form><?php endif; ?></td></tr>
 <?php if ($i['schedule_status'] === 'Scheduled'): ?><tr class="collapse" id="res<?= $i['id'] ?>"><td colspan="6" class="bg-light"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="act" value="round_result"><input type="hidden" name="interview_id" value="<?= $i['id'] ?>">
  <div class="col-md-3"><select name="result" class="form-select form-select-sm"><option>Passed</option><option>Failed</option><option>On Hold</option></select></div>
  <div class="col-md-2"><input type="number" name="score" min="0" max="10" class="form-control form-control-sm" placeholder="Score /10"></div>
  <div class="col-md-5"><input name="remarks" class="form-control form-control-sm" placeholder="Remarks"></div><div class="col-md-2"><button class="btn btn-sm btn-success w-100">Save &amp; notify</button></div></form></td></tr><?php endif; ?>
 <?php endforeach; if (!$ints): ?><tr><td colspan="6" class="text-muted">No interviews scheduled.</td></tr><?php endif; ?></table>
 <?php if (!in_array($A['app_status'], ['Rejected', 'Offer Accepted', 'Offer Declined'], true) && $rounds): ?>
 <hr><h6>Schedule interview</h6>
 <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="act" value="schedule">
  <div class="col-md-4"><select name="round_id" class="form-select form-select-sm" required><?php foreach ($rounds as $r): ?><option value="<?= $r['id'] ?>"><?= $r['round_no'] ?>. <?= e($r['round_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><select name="interviewer_id" class="form-select form-select-sm"><option value="">Round default interviewer</option><?php foreach ($allInts as $iv): ?><option value="<?= $iv['id'] ?>"><?= e($iv['name']) ?> (<?= e($iv['role']) ?>)</option><?php endforeach; ?></select></div>
  <div class="col-md-4"><input type="datetime-local" name="scheduled_at" class="form-control form-control-sm" required></div>
  <div class="col-md-3"><select name="mode" class="form-select form-select-sm"><option>Virtual</option><option>Physical</option></select></div>
  <div class="col-md-6"><input name="venue_or_link" class="form-control form-control-sm" placeholder="Meeting link or venue address" required></div>
  <div class="col-md-3"><button class="btn btn-sm btn-brand w-100">Schedule &amp; notify</button></div></form>
 <?php endif; ?></div></div>

 <div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between">HR verification <?= $hr ? status_badge($hr['status']) : '' ?></div><div class="card-body">
  <?php if (in_array($A['app_status'], ['Selected', 'Offered', 'Offer Accepted', 'Offer Declined'], true) || $hr): ?>
  <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="act" value="hr_verify">
   <div class="col-md-3"><select name="hr_status" class="form-select form-select-sm"><?php foreach (['Pending', 'Verified', 'Failed'] as $s): ?><option <?= ($hr['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
   <div class="col-md-3 pt-1"><label><input type="checkbox" name="docs" <?= !empty($hr['docs_verified']) ? 'checked' : '' ?>> Documents</label></div>
   <div class="col-md-3 pt-1"><label><input type="checkbox" name="bg" <?= !empty($hr['background_verified']) ? 'checked' : '' ?>> Background</label></div>
   <div class="col-md-12"><input name="hr_remarks" class="form-control form-control-sm" placeholder="Remarks" value="<?= e($hr['remarks'] ?? '') ?>"></div>
   <div class="col-12"><button class="btn btn-sm btn-primary">Save verification</button></div></form>
  <?php else: ?><span class="text-muted">Available once the candidate clears all rounds (Selected).</span><?php endif; ?></div></div>

 <div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between">Offer letter <?= $offer ? status_badge($offer['status']) : '' ?></div><div class="card-body">
  <?php if ($offer): ?><p class="mb-2">Offer <b><?= e($offer['offer_no']) ?></b> - <?= e($offer['designation']) ?>, Rs. <?= number_format($offer['ctc']) ?> p.a., joining <?= e($offer['joining_date']) ?></p>
   <a class="btn btn-sm btn-outline-primary" target="_blank" href="offer_print.php?id=<?= $aid ?>"><i class="bi bi-printer"></i> View / print</a>
   <?php if ($offer['status'] === 'Sent'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="act" value="offer_response">
    <button name="resp" value="Accepted" class="btn btn-sm btn-success">Mark accepted</button> <button name="resp" value="Declined" class="btn btn-sm btn-outline-dark">Mark declined</button></form><?php endif; ?>
  <?php elseif ($hr && $hr['status'] === 'Verified'): ?>
  <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="act" value="offer">
   <div class="col-md-4"><input name="designation" class="form-control form-control-sm" value="<?= e($A['title']) ?>" placeholder="Designation" required></div>
   <div class="col-md-4"><input type="number" name="ctc" class="form-control form-control-sm" placeholder="Annual CTC (Rs)" required></div>
   <div class="col-md-4"><input type="date" name="joining_date" class="form-control form-control-sm" required></div>
   <div class="col-md-4"><input type="date" name="valid_until" class="form-control form-control-sm" title="Offer valid until"></div>
   <div class="col-md-8"><input name="terms" class="form-control form-control-sm" placeholder="Additional terms (optional)"></div>
   <div class="col-12"><button class="btn btn-sm btn-brand">Generate &amp; send offer</button></div></form>
  <?php else: ?><span class="text-muted">Requires HR verification = Verified.</span><?php endif; ?></div></div>

 <div class="card shadow-sm"><div class="card-header">Feedback</div><div class="card-body">
  <div class="row"><div class="col-md-6"><b>From candidate</b>
   <?php if (!empty($fb['Candidate']['submitted_at'])): ?><div><?= str_repeat('★', (int)$fb['Candidate']['rating']) ?><?= str_repeat('☆', 5 - (int)$fb['Candidate']['rating']) ?></div><p class="small"><?= e($fb['Candidate']['comments']) ?></p>
   <?php elseif (isset($fb['Candidate'])): ?><p class="text-muted small">Requested, awaiting response.</p>
   <?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="feedback_request"><button class="btn btn-sm btn-outline-primary">Request feedback</button></form><?php endif; ?></div>
  <div class="col-md-6"><b>Your feedback on process/candidate</b>
   <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="employer_feedback">
    <select name="rating" class="form-select form-select-sm my-1"><?php for ($n = 5; $n >= 1; $n--): ?><option value="<?= $n ?>" <?= ($fb['Employer']['rating'] ?? 0) == $n ? 'selected' : '' ?>><?= $n ?> star</option><?php endfor; ?></select>
    <textarea name="comments" rows="2" class="form-control form-control-sm mb-1" placeholder="Comments"><?= e($fb['Employer']['comments'] ?? '') ?></textarea><button class="btn btn-sm btn-primary">Save</button></form></div></div></div></div>
</div></div>
<?php page_footer();
