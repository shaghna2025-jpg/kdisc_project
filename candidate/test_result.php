<?php
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate();
$A = row("SELECT a.*, t.title, t.pass_percent, s.name skill FROM test_attempts a JOIN skill_tests t ON t.id=a.test_id JOIN skills s ON s.id=t.skill_id WHERE a.id=? AND a.candidate_id=? AND a.status<>'In Progress'", [(int)($_GET['id'] ?? 0), $c['id']]);
if (!$A) { flash('Result not found.', 'danger'); redirect('candidate/tests.php'); }
$ids = json_decode($A['question_ids'], true) ?: []; $ans = json_decode($A['answers'] ?? '{}', true) ?: []; $missed = [];
if ($ids) foreach (rows('SELECT id,question,correct,explanation FROM test_questions WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')') as $q) if (($ans[$q['id']] ?? '') !== $q['correct']) $missed[] = $q;
$tid = (int)$A['test_id'];
page_header($A['skill'] . ' test result', 'ctests'); ?>
<div class="card shadow-sm mb-3"><div class="card-body text-center">
<?php if ($A['status'] === 'Expired'): ?><h3>Time ran out</h3><p class="text-muted">This attempt was not submitted in time and counts as an attempt.</p>
<?php else: ?><div class="display-4 fw-bold"><?= (int)$A['score_percent'] ?>%</div><div><?= status_badge($A['passed'] ? 'Passed' : 'Failed') ?> <span class="text-muted">pass mark <?= (int)$A['pass_percent'] ?>%</span></div>
<?php if ($A['passed']): ?><p class="mt-3 mb-0">Verified level earned: <b><?= level_name($A['level_awarded']) ?></b>. It now shows on your profile and is used in your match scores.</p>
<?php else: ?><p class="mt-3 mb-0">Not passed this time. Review the topics below, take a recommended course, and retake after 24 hours.</p><?php endif; endif; ?>
<div class="mt-3"><a href="tests.php" class="btn btn-brand">All tests</a> <a href="skills.php" class="btn btn-outline-secondary">My skills</a> <a href="gap.php" class="btn btn-outline-secondary">Skill gap</a></div></div></div>
<?php if ($missed): ?><div class="card shadow-sm"><div class="card-header">Review the questions you missed</div><ul class="list-group list-group-flush">
<?php foreach ($missed as $q): ?><li class="list-group-item"><?= nl2br(e($q['question'])) ?><?php if ($q['explanation']): ?><div class="small text-muted mt-1"><i class="bi bi-lightbulb"></i> <?= e($q['explanation']) ?></div><?php endif; ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php page_footer();
