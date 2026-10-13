<?php
// Search published jobs, with match % per job; save / unsave
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
$c = require_candidate(); $cid = $c['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); $jid = (int)post('job_id');
    if (val('SELECT COUNT(*) FROM saved_jobs WHERE candidate_id=? AND job_id=?', [$cid, $jid])) q('DELETE FROM saved_jobs WHERE candidate_id=? AND job_id=?', [$cid, $jid]);
    elseif (val("SELECT COUNT(*) FROM jobs WHERE id=? AND status='Published'", [$jid])) q('INSERT INTO saved_jobs (candidate_id,job_id) VALUES (?,?)', [$cid, $jid]);
    redirect('candidate/jobs.php' . (post('back') ? '?' . post('back') : ''));
}
$kw = trim($_GET['q'] ?? ''); $loc = trim($_GET['loc'] ?? ''); $type = $_GET['type'] ?? ''; $minsal = (int)($_GET['minsal'] ?? 0); $sort = $_GET['sort'] ?? 'match'; $savedOnly = isset($_GET['saved']);
$sql = "SELECT j.*, e.company_name, e.logo, (SELECT COUNT(*) FROM saved_jobs WHERE candidate_id=? AND job_id=j.id) saved, (SELECT COUNT(*) FROM applications WHERE candidate_id=? AND job_id=j.id) applied
        FROM jobs j JOIN employers e ON e.id=j.employer_id WHERE j.status='Published' AND e.status='Approved' AND (j.last_date IS NULL OR j.last_date>=CURDATE())"; $p = [$cid, $cid];
if ($kw !== '') { $sql .= ' AND (j.title LIKE ? OR j.description LIKE ? OR j.skills LIKE ? OR e.company_name LIKE ?)'; array_push($p, "%$kw%", "%$kw%", "%$kw%", "%$kw%"); }
if ($loc !== '') { $sql .= ' AND j.location LIKE ?'; $p[] = "%$loc%"; }
if (in_array($type, ['Full-time', 'Part-time', 'Contract', 'Internship'], true)) { $sql .= ' AND j.job_type=?'; $p[] = $type; }
if ($minsal > 0) { $sql .= ' AND (j.salary_max IS NULL OR j.salary_max>=?)'; $p[] = $minsal; }
if ($savedOnly) $sql .= ' AND j.id IN (SELECT job_id FROM saved_jobs WHERE candidate_id=' . (int)$cid . ')';
$jobs = rows($sql . ' ORDER BY j.published_at DESC LIMIT 60', $p);
foreach ($jobs as &$j) { $g = gap_analysis($cid, $j['id']); $j['score'] = $g['score']; $j['mm'] = $g['mandatory_missing']; } unset($j);
if ($sort === 'match') usort($jobs, fn($a, $b) => ($b['score'] ?? -1) <=> ($a['score'] ?? -1));
$back = http_build_query($_GET);
page_header('Find jobs', 'cjobs'); ?>
<form class="row g-2 mb-3"><div class="col-md-3"><input name="q" class="form-control" placeholder="Job title, skill or company" value="<?= e($kw) ?>"></div>
<div class="col-md-2"><input name="loc" class="form-control" placeholder="Location" value="<?= e($loc) ?>"></div>
<div class="col-md-2"><select name="type" class="form-select"><option value="">Any type</option><?php foreach (['Full-time', 'Part-time', 'Contract', 'Internship'] as $t): ?><option <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><input type="number" name="minsal" class="form-control" placeholder="Min salary / yr" value="<?= $minsal ?: '' ?>"></div>
<div class="col-md-1"><select name="sort" class="form-select"><option value="match" <?= $sort === 'match' ? 'selected' : '' ?>>Best match</option><option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Newest</option></select></div>
<div class="col-md-2 d-flex gap-2 align-items-center"><button class="btn btn-brand">Search</button><label class="small"><input type="checkbox" name="saved" <?= $savedOnly ? 'checked' : '' ?>> Saved</label></div></form>
<p class="text-muted small"><?= count($jobs) ?> job(s)</p>
<?php foreach ($jobs as $j): ?><div class="card shadow-sm mb-2"><div class="card-body d-flex gap-3 align-items-start">
<?php if ($j['logo']): ?><img src="<?= e(url($j['logo'])) ?>" height="44" class="rounded border p-1 bg-white"><?php endif; ?>
<div class="flex-grow-1"><h5 class="mb-0"><a href="job_view.php?id=<?= $j['id'] ?>"><?= e($j['title']) ?></a></h5>
<div class="text-muted"><?= e($j['company_name']) ?> | <?= e($j['location']) ?> | <?= e($j['job_type']) ?> | <?= e($j['exp_min']) ?>-<?= e($j['exp_max'] ?? '+') ?> yrs
<?= $j['salary_min'] ? ' | Rs ' . number_format($j['salary_min']) . '-' . number_format($j['salary_max'] ?? $j['salary_min']) : '' ?></div>
<div class="mt-1"><?php foreach (array_filter(array_map('trim', explode(',', (string)$j['skills']))) as $s): ?><span class="badge text-bg-light border"><?= e($s) ?></span> <?php endforeach; ?></div>
<?php if ($j['mm']): ?><div class="small text-danger mt-1">Mandatory skill gap: <?= e(implode(', ', $j['mm'])) ?></div><?php endif; ?></div>
<div class="text-end"><?= match_badge($j['score']) ?><?= $j['applied'] ? '<div><span class="badge text-bg-primary mt-1">Applied</span></div>' : '' ?>
<form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= $j['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-bookmark<?= $j['saved'] ? '-fill' : '' ?>"></i> <?= $j['saved'] ? 'Saved' : 'Save' ?></button></form></div>
</div></div><?php endforeach; if (!$jobs): ?><div class="alert alert-info">No jobs match your search.</div><?php endif; ?>
<?php page_footer();
