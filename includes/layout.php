<?php
function page_header($title, $active = '') {
    $u = user(); $brand = '#0d6efd'; $logo = null; $company = APP_NAME;
    if ($u && emp_id()) {
        $e = row('SELECT company_name,brand_color,logo FROM employers WHERE id=?', [emp_id()]);
        if ($e) { $brand = $e['brand_color']; $logo = $e['logo']; $company = $e['company_name']; }
    }
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> - <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>:root{--brand:<?= e($brand) ?>}
.sidebar{min-height:100vh;background:#1e2430}.sidebar a{color:#c5cad3;text-decoration:none;display:block;padding:.55rem 1rem;border-radius:.4rem}
.sidebar a:hover,.sidebar a.active{background:var(--brand);color:#fff}.topbar{border-bottom:3px solid var(--brand)}
.btn-brand{background:var(--brand);border-color:var(--brand);color:#fff}.btn-brand:hover{filter:brightness(.9);color:#fff}
.stat{border-left:4px solid var(--brand)}</style></head><body class="bg-light">
<?php if (!$u): ?><div class="container py-4"><?php return; endif; ?>
<div class="container-fluid"><div class="row">
<nav class="col-md-2 sidebar p-3">
  <div class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
    <?php if ($logo): ?><img src="<?= e(url($logo)) ?>" height="32" class="rounded bg-white p-1"><?php endif; ?><span><?= e($company) ?></span></div>
  <?php
  $items = [];
  if (is_admin()) {
      $items[] = ['admin/dashboard.php', 'speedometer2', 'Admin Dashboard', 'adash'];
      if (can_manage_employers()) {
          $items[] = ['admin/employers.php', 'buildings', 'Employers', 'emps'];
          $items[] = ['admin/employer_add.php', 'building-add', 'Add Employer', 'empadd'];
          $items[] = ['admin/employer_bulk.php', 'file-earmark-spreadsheet', 'Bulk Upload', 'bulk'];
          $items[] = ['admin/reports.php', 'bar-chart-line', 'Reports', 'reports'];
      }
      if (in_array($u['role'], ['SuperAdmin', 'SkillsAdmin'], true)) {
          $items[] = ['admin/skills.php', 'diagram-3', 'Skill taxonomy', 'askills'];
          $items[] = ['admin/courses.php', 'mortarboard', 'Courses', 'acourses'];
          $items[] = ['admin/tests.php', 'clipboard-check', 'Skill tests', 'atests'];
      }
      if (in_array($u['role'], ['SuperAdmin', 'CandAdmin'], true)) $items[] = ['admin/candidates.php', 'people', 'Candidates', 'acands'];
  }
  if (is_candidate()) {
      $items[] = ['candidate/dashboard.php', 'speedometer2', 'Dashboard', 'cdash'];
      $items[] = ['candidate/jobs.php', 'search', 'Find jobs', 'cjobs'];
      $items[] = ['candidate/applications.php', 'send-check', 'My applications', 'capps'];
      $items[] = ['candidate/gap.php', 'bullseye', 'Skill gap analysis', 'cgap'];
      $items[] = ['candidate/tests.php', 'patch-check', 'Skill tests', 'ctests'];
      $items[] = ['candidate/skills.php', 'stars', 'My skills', 'cskills'];
      $items[] = ['candidate/profile.php', 'person-vcard', 'Profile & resume', 'cprof'];
      $items[] = ['candidate/notifications.php', 'bell', 'Notifications', 'cnotif'];
  }
  if (emp_id()) {
      $items[] = ['employer/dashboard.php', 'speedometer2', 'Employer Dashboard', 'dash'];
      $items[] = ['employer/profile.php', 'palette', 'Profile & Branding', 'profile'];
      $items[] = ['employer/interviewers.php', 'people', 'Interviewers', 'ints'];
      $items[] = ['employer/jobs.php', 'briefcase', 'Jobs', 'jobs'];
      $items[] = ['employer/candidates.php', 'person-lines-fill', 'Candidates', 'cands'];
      $items[] = ['employer/interviews.php', 'calendar-event', 'Interview Schedule', 'sched'];
  }
  foreach ($items as $i): ?>
    <a href="<?= e(url($i[0])) ?>" class="<?= $active === $i[3] ? 'active' : '' ?>"><i class="bi bi-<?= $i[1] ?> me-2"></i><?= e($i[2]) ?></a>
  <?php endforeach; ?>
  <hr class="border-secondary"><a href="<?= e(url('logout.php')) ?>"><i class="bi bi-box-arrow-left me-2"></i>Logout</a>
</nav>
<main class="col-md-10 p-0">
  <div class="topbar bg-white px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h4 class="m-0"><?= e($title) ?></h4>
    <div class="d-flex align-items-center gap-3">
      <?php if (can_manage_employers()):
          $all = rows("SELECT id,company_name FROM employers WHERE status='Approved' ORDER BY company_name"); ?>
        <form method="post" action="<?= e(url('employer/switch.php')) ?>" class="d-flex gap-2"><?= csrf_field() ?>
          <select name="id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="0">-- Act as employer --</option>
            <?php foreach ($all as $a): ?><option value="<?= $a['id'] ?>" <?= emp_id() == $a['id'] ? 'selected' : '' ?>><?= e($a['company_name']) ?></option><?php endforeach; ?>
          </select></form>
      <?php endif; ?>
      <span class="text-muted small"><i class="bi bi-person-circle"></i> <?= e($u['name']) ?> (<?= e($u['role']) ?>)</span>
    </div>
  </div>
  <div class="p-4">
  <?php foreach (flash() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?> alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endforeach;
}
function page_footer() { if (!user()) { ?></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html><?php return; } ?>
  </div></main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
<?php }
