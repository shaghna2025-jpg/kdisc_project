<?php
// SkillsAdmin: question bank for one test (add, delete, CSV import)
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(['SuperAdmin', 'SkillsAdmin']);
$tid = (int)($_GET['test'] ?? 0); $T = row('SELECT t.*, s.name skill FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE t.id=?', [$tid]);
if (!$T) { flash('Test not found.', 'danger'); redirect('admin/tests.php'); }
if (isset($_GET['template'])) { header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="questions_template.csv"'); echo "question,a,b,c,d,correct,explanation\nWhich function counts array items?,strlen(),count(),size(),length(),B,count() returns the number of elements\n"; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $act = post('act');
    if ($act === 'add' && post('question') !== '' && in_array(post('correct'), ['A', 'B', 'C', 'D'], true)) {
        q('INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) VALUES (?,?,?,?,?,?,?,?)', [$tid, post('question'), post('a'), post('b'), post('c'), post('d'), post('correct'), nullable(post('explanation'))]); flash('Question added.');
    } elseif ($act === 'delete') { q('DELETE FROM test_questions WHERE id=? AND test_id=?', [(int)post('qid'), $tid]); flash('Question deleted.'); }
    elseif ($act === 'import' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fh = fopen($_FILES['file']['tmp_name'], 'r'); fgetcsv($fh); $n = 0; $bad = 0;
        while (($r = fgetcsv($fh)) !== false) {
            $r = array_pad($r, 7, ''); $cor = strtoupper(trim($r[5]));
            if (trim($r[0]) === '' || !in_array($cor, ['A', 'B', 'C', 'D'], true) || in_array('', [trim($r[1]), trim($r[2]), trim($r[3]), trim($r[4])], true)) { $bad++; continue; }
            q('INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) VALUES (?,?,?,?,?,?,?,?)', [$tid, trim($r[0]), trim($r[1]), trim($r[2]), trim($r[3]), trim($r[4]), $cor, nullable(trim($r[6]))]); $n++;
        } fclose($fh); flash("$n question(s) imported" . ($bad ? ", $bad row(s) skipped" : '') . '.', $bad ? 'warning' : 'success');
    }
    redirect('admin/test_questions.php?test=' . $tid);
}
$qs = rows('SELECT * FROM test_questions WHERE test_id=? ORDER BY id DESC', [$tid]);
page_header('Questions: ' . $T['title'], 'atests'); ?>
<a href="tests.php" class="small">&larr; All tests</a>
<div class="row g-3 mt-1"><div class="col-md-5"><div class="card shadow-sm mb-3"><div class="card-header">Add question</div><div class="card-body"><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="add">
<textarea name="question" rows="2" class="form-control mb-2" placeholder="Question" required></textarea>
<?php foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $k => $L): ?><div class="input-group mb-1"><span class="input-group-text"><?= $L ?></span><input name="<?= $k ?>" class="form-control" required></div><?php endforeach; ?>
<select name="correct" class="form-select my-2" required><option value="">Correct answer...</option><option>A</option><option>B</option><option>C</option><option>D</option></select>
<input name="explanation" class="form-control mb-2" placeholder="Explanation shown if answered wrongly (optional)"><button class="btn btn-primary">Add</button></form></div></div>
<div class="card shadow-sm"><div class="card-header">Import from CSV</div><div class="card-body"><form method="post" enctype="multipart/form-data" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="act" value="import"><input type="file" name="file" accept=".csv" class="form-control" required><button class="btn btn-outline-primary">Import</button></form>
<a href="?test=<?= $tid ?>&template=1" class="small">Download template</a></div></div></div>
<div class="col-md-7"><div class="card shadow-sm"><div class="card-header"><?= count($qs) ?> question(s) in pool - <?= (int)$T['questions_per_attempt'] ?> asked per attempt</div><ul class="list-group list-group-flush small">
<?php foreach ($qs as $q): ?><li class="list-group-item d-flex justify-content-between gap-2"><div><?= e($q['question']) ?><div class="text-muted">A) <?= e($q['option_a']) ?> | B) <?= e($q['option_b']) ?> | C) <?= e($q['option_c']) ?> | D) <?= e($q['option_d']) ?></div><b class="text-success">Answer: <?= e($q['correct']) ?></b></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="qid" value="<?= $q['id'] ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete question?')">&times;</button></form></li>
<?php endforeach; if (!$qs): ?><li class="list-group-item text-muted">No questions yet.</li><?php endif; ?></ul></div></div></div>
<?php page_footer();
