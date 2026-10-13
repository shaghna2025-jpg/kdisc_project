<?php
// Start / resume / submit a timed skill test. Time is enforced on the server.
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id']; $tid = (int)($_GET['test'] ?? $_POST['test'] ?? 0);
$T = row('SELECT t.*, s.name skill FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE t.id=? AND t.active=1', [$tid]);
if (!$T) { flash('Test not found.', 'danger'); redirect('candidate/tests.php'); }
$limit = (int)$T['time_limit_min'] * 60;
$att = row("SELECT * FROM test_attempts WHERE candidate_id=? AND test_id=? AND status='In Progress' ORDER BY id DESC LIMIT 1", [$cid, $tid]);
if ($att && time() > strtotime($att['started_at']) + $limit + 60) {          // past the deadline (+60s grace for submit lag)
    q("UPDATE test_attempts SET status='Expired', score_percent=0, submitted_at=NOW() WHERE id=?", [$att['id']]); $att = null; flash('Your previous attempt timed out.', 'warning');
}
$last = val("SELECT MAX(submitted_at) FROM test_attempts WHERE candidate_id=? AND test_id=? AND status<>'In Progress'", [$cid, $tid]);
$cool = $last && strtotime($last) + 86400 > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act');
    if ($act === 'start' && !$att && !$cool) {
        $ids = array_column(rows('SELECT id FROM test_questions WHERE test_id=?', [$tid]), 'id'); shuffle($ids); $ids = array_slice($ids, 0, (int)$T['questions_per_attempt']);
        if (count($ids) < (int)$T['questions_per_attempt']) { flash('This test is not ready yet.', 'warning'); redirect('candidate/tests.php'); }
        q('INSERT INTO test_attempts (candidate_id,test_id,question_ids) VALUES (?,?,?)', [$cid, $tid, json_encode($ids)]);
    } elseif ($act === 'submit' && $att && (int)post('attempt') === (int)$att['id']) {
        $ids = json_decode($att['question_ids'], true); $ans = []; $ok = 0;
        $qs = rows('SELECT id,correct FROM test_questions WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
        foreach ($qs as $q) { $a = strtoupper($_POST['ans'][$q['id']] ?? ''); $a = in_array($a, ['A', 'B', 'C', 'D'], true) ? $a : ''; $ans[$q['id']] = $a; if ($a === $q['correct']) $ok++; }
        $pct = (int)round($ok / max(1, count($ids)) * 100); $lvl = test_level($pct, (int)$T['pass_percent']); $pass = $lvl > 0 ? 1 : 0;
        q("UPDATE test_attempts SET answers=?, score_percent=?, passed=?, level_awarded=?, status='Completed', submitted_at=NOW() WHERE id=?", [json_encode($ans), $pct, $pass, $lvl, $att['id']]);
        if ($pass) {
            $applied = award_test_skill($cid, $T['skill_id'], $lvl);
            notify($c['email'], "Skill test passed: {$T['skill']}", "You scored $pct% in the {$T['skill']} test and earned a verified level: " . level_name($lvl) . '.' . ($applied ? '' : ' Your declared level is higher, so it was left unchanged.'), 'test', $att['id']);
        }
        redirect('candidate/test_result.php?id=' . $att['id']);
    }
    redirect("candidate/test_take.php?test=$tid");
}
page_header($T['skill'] . ' skill test', 'ctests');
if (!$att): ?>
<div class="card shadow-sm"><div class="card-body"><h5><?= e($T['title']) ?></h5>
<ul><li><?= (int)$T['questions_per_attempt'] ?> multiple-choice questions, drawn at random</li><li>Time limit: <?= (int)$T['time_limit_min'] ?> minutes - the timer starts when you press Start and keeps running if you leave</li>
<li>Pass mark: <?= (int)$T['pass_percent'] ?>%. Higher scores earn a higher verified level (70% = Intermediate, 80% = Advanced, 90% = Expert)</li><li>You can retake after 24 hours</li></ul>
<?php if ($cool): ?><div class="alert alert-warning">You can retake this test after <?= e(date('d M, h:i A', strtotime($last) + 86400)) ?>.</div>
<?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="test" value="<?= $tid ?>"><button name="act" value="start" class="btn btn-brand btn-lg">Start test</button></form><?php endif; ?>
<a href="tests.php" class="btn btn-link">Back to tests</a></div></div>
<?php else:
    $ids = json_decode($att['question_ids'], true); $map = [];
    foreach (rows('SELECT id,question,option_a,option_b,option_c,option_d FROM test_questions WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')') as $q) $map[$q['id']] = $q;
    $left = max(0, strtotime($att['started_at']) + $limit - time()); ?>
<div class="alert alert-primary d-flex justify-content-between"><span>Answer all questions, then submit.</span><b>Time left: <span id="clock"></span></b></div>
<form method="post" id="tf"><?= csrf_field() ?><input type="hidden" name="test" value="<?= $tid ?>"><input type="hidden" name="attempt" value="<?= $att['id'] ?>"><input type="hidden" name="act" value="submit">
<?php foreach ($ids as $n => $qid): $q = $map[$qid] ?? null; if (!$q) continue; ?><div class="card shadow-sm mb-3"><div class="card-body"><p class="fw-semibold mb-2"><?= $n + 1 ?>. <?= nl2br(e($q['question'])) ?></p>
<?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $L => $col): ?><div class="form-check"><input class="form-check-input" type="radio" name="ans[<?= $qid ?>]" id="q<?= $qid . $L ?>" value="<?= $L ?>"><label class="form-check-label" for="q<?= $qid . $L ?>"><?= e($q[$col]) ?></label></div><?php endforeach; ?></div></div><?php endforeach; ?>
<button class="btn btn-brand btn-lg" onclick="return confirm('Submit your answers?')">Submit test</button></form>
<script>var left=<?= (int)$left ?>,el=document.getElementById('clock');function tick(){var m=Math.floor(left/60),s=left%60;el.textContent=m+':'+(s<10?'0':'')+s;if(left<=0){document.getElementById('tf').submit();return}left--;setTimeout(tick,1000)}tick();</script>
<?php endif; page_footer();
