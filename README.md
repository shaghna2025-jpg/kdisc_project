# Employer Module (PHP 8 + MySQL + Bootstrap 5)

## Setup
1. Copy this folder to your web root (e.g. `htdocs/employer-module`). Needs PHP 8.0+ with PDO MySQL.
2. Import `sql/schema.sql` (creates DB `employer_module` + demo data), then `sql/candidate_module.sql` (candidate login, skill taxonomy, learning, gap tables), then `sql/skill_tests.sql` (skill tests + 20 sample questions for PHP, MySQL, JavaScript, Git).
3. Edit `config.php` (DB credentials, `BASE_URL`, `DEV_MODE`). Make `uploads/` writable.
4. Open `/employer-module/index.php`.

Demo logins (password `Admin@123`): `empadmin@example.com`, `superadmin@example.com`, employer `hr@acme.example`.
Set `DEV_MODE=false` in production (real `mail()` is used and OTPs are no longer shown on screen).
Cron (reminders): `*/15 * * * * php /path/employer-module/cron/reminders.php`

## Document -> file map
| Requirement | Files |
|---|---|
| Logins (SuperAdmin, EmpAdmin, CandAdmin, SkillsAdmin, DataAdmin, Employer) | index.php, users.role |
| Self registration (email OTP, mobile OTP, full details, approval) | register.php, admin/employers.php |
| EmpAdmin add / bulk add / edit | admin/employer_add.php, admin/employer_bulk.php |
| Edit profile, visual branding | employer/profile.php |
| Interviewers + roles | employer/interviewers.php |
| Jobs, rounds, publish, edit, social posting | employer/jobs.php, job_form.php, job_action.php |
| Candidate listing, filters, biodata, screening | employer/candidates.php, candidate_view.php |
| Scheduling, notifications, round status, HR verification, offer, feedback | candidate_view.php, offer_print.php, feedback.php, cron/reminders.php |
| Dashboard | employer/dashboard.php, admin/dashboard.php |
| Reports + export | admin/reports.php |

EmpAdmin works on behalf of an employer using the "Act as employer" dropdown in the top bar.

## Integration points (stubbed)
- `queue_social_posts()` in includes/bootstrap.php: queues LinkedIn/Facebook/X posts; add API calls there.
- `notify()`: add SMS gateway for channel 'SMS'.
- `screen_application()`: rule-based score; replace with an AI API call if wanted.
- Bulk upload accepts CSV (save Excel as CSV UTF-8). For .xlsx use PhpSpreadsheet.
- Candidates apply through your separate candidate module (tables `candidates`, `applications`).

## Candidate module + skill gap analysis
Demo candidate logins (password `Admin@123`): `anjali@example.com`, `rahul@example.com`, `meera@example.com`. Self sign-up: `register_candidate.php`.

| Feature | Files |
|---|---|
| Sign-up with email OTP (claims an existing profile with the same email) | register_candidate.php |
| Dashboard: stats, best-match jobs, interviews, in-demand skills you lack | candidate/dashboard.php |
| Profile, resume upload, completeness | candidate/profile.php |
| Skills with proficiency levels | candidate/skills.php |
| Job search, filters, match %, save job | candidate/jobs.php, job_view.php |
| Apply, track stages, interviews, offer accept/decline, withdraw, feedback | candidate/job_view.php, applications.php, application_view.php, offer_print.php |
| Skill gap report, learning plan, "potential after learning" | candidate/gap.php |
| Notifications | candidate/notifications.php |
| Employer sets skill weight / required level / mandatory per job | employer/job_skills.php |
| Taxonomy, courses, candidate admin | admin/skills.php, admin/courses.php, admin/candidates.php |

Match % = sum(weight x min(level, required) / required) / sum(weight); mandatory skills count 1.5x. Core logic: `gap_analysis()` in includes/bootstrap.php.

### Skill tests (verified levels)
Candidates: candidate/tests.php, test_take.php, test_result.php. Admin (SkillsAdmin): admin/tests.php, admin/test_questions.php (add / CSV import).
Timed multiple-choice tests drawn at random from a question pool; time limit enforced on the server; 24-hour retake cooldown. Score bands: pass mark = Basic, 70% = Intermediate, 80% = Advanced, 90% = Expert.
A pass writes a **verified** level to the candidate's skill (unless their declared level is already higher). Employers see a Verified badge. Tests are not proctored.
Not built yet: resume auto-parsing, job alerts by email.
