<?php
// Publish (auto-queues social media posts) or close a job
require_once '../includes/bootstrap.php';
$emp = require_employer_context(); csrf_check();
$id = (int)post('id'); $job = row('SELECT * FROM jobs WHERE id=? AND employer_id=?', [$id, $emp['id']]);
if (!$job) { flash('Job not found.', 'danger'); redirect('employer/jobs.php'); }
if (post('act') === 'publish') {
    if (!val('SELECT COUNT(*) FROM job_rounds WHERE job_id=?', [$id])) { flash('Add at least one interview round before publishing.', 'warning'); redirect('employer/job_form.php?id=' . $id); }
    q("UPDATE jobs SET status='Published', published_at=NOW() WHERE id=?", [$id]);
    queue_social_posts($id);
    flash('Job published and queued for LinkedIn, Facebook and Twitter/X.');
} elseif (post('act') === 'close') { q("UPDATE jobs SET status='Closed' WHERE id=?", [$id]); flash('Job closed.'); }
redirect('employer/jobs.php');
