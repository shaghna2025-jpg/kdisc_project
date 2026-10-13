<?php
// Run every 15 minutes:  */15 * * * * php /path/to/employer-module/cron/reminders.php
// Sends reminders to candidates and interviewers for interviews in the next 24 hours; also marks queued social posts as posted placeholders.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../includes/bootstrap.php';
$due = rows("SELECT i.*, c.name cname, c.email cemail, j.title, r.round_name, iv.name iname, iv.email iemail FROM interviews i
  JOIN applications a ON a.id=i.application_id JOIN candidates c ON c.id=a.candidate_id JOIN jobs j ON j.id=a.job_id JOIN job_rounds r ON r.id=i.round_id LEFT JOIN interviewers iv ON iv.id=i.interviewer_id
  WHERE i.schedule_status='Scheduled' AND i.reminder_sent=0 AND i.scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)");
foreach ($due as $i) {
    $when = date('D, d M Y h:i A', strtotime($i['scheduled_at'])); $where = $i['mode'] . ': ' . $i['venue_or_link'];
    notify($i['cemail'], "Reminder: {$i['round_name']} for {$i['title']}", "Dear {$i['cname']}, this is a reminder of your {$i['round_name']} on $when.\n$where", 'interview', $i['id']);
    if ($i['iemail']) notify($i['iemail'], "Reminder: interview with {$i['cname']}", "Reminder: {$i['round_name']} for {$i['title']} with {$i['cname']} on $when.\n$where", 'interview', $i['id']);
    q('UPDATE interviews SET reminder_sent=1 WHERE id=?', [$i['id']]);
}
echo count($due) . " reminder(s) sent\n";
