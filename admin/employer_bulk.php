<?php
// Bulk employer upload. Accepts CSV (export your Excel sheet as "CSV UTF-8"). For native .xlsx install PhpSpreadsheet via composer and swap the reader.
require_once '../includes/bootstrap.php'; require_once '../includes/layout.php';
require_login(['EmpAdmin', 'SuperAdmin']);
$cols = ['company_name', 'company_id', 'email', 'mobile', 'contact_person', 'industry', 'city', 'state', 'website'];
if (isset($_GET['template'])) {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="employer_bulk_template.csv"');
    echo implode(',', $cols) . "\nSample Pvt Ltd,CIN123,hr@sample.com,9999999999,Asha,IT,Kochi,Kerala,https://sample.com\n"; exit;
}
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK || strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) !== 'csv') flash('Upload a .csv file.', 'danger');
    else {
        $fh = fopen($_FILES['file']['tmp_name'], 'r'); $hdr = array_map(fn($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h))), fgetcsv($fh) ?: []);
        $ok = 0; $errs = []; $n = 1;
        while (($r = fgetcsv($fh)) !== false) {
            $n++; if (!array_filter($r)) continue;
            $d = array_combine($hdr, array_pad(array_slice($r, 0, count($hdr)), count($hdr), ''));
            $email = strtolower(trim($d['email'] ?? ''));
            if (empty(trim($d['company_name'] ?? '')) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errs[] = "Row $n: missing company name / invalid email"; continue; }
            if (val('SELECT COUNT(*) FROM employers WHERE email=?', [$email])) { $errs[] = "Row $n: $email already exists"; continue; }
            try {
                $pw = bin2hex(random_bytes(4)) . 'A1!'; $pdo->beginTransaction();
                q('INSERT INTO employers (company_name,company_id,email,mobile,contact_person,industry,city,state,website,email_verified,mobile_verified,status,source,approved_by,approved_at) VALUES (?,?,?,?,?,?,?,?,?,1,1,"Approved","Bulk",?,NOW())',
                  [trim($d['company_name']), nullable(trim($d['company_id'] ?? '')), $email, trim($d['mobile'] ?? ''), trim($d['contact_person'] ?? ''), trim($d['industry'] ?? ''), trim($d['city'] ?? ''), trim($d['state'] ?? ''), trim($d['website'] ?? ''), user()['id']]);
                $nid = insert_id();
                q('INSERT INTO users (name,email,password_hash,role,employer_id) VALUES (?,?,?,?,?)', [trim($d['contact_person'] ?? '') ?: trim($d['company_name']), $email, password_hash($pw, PASSWORD_DEFAULT), 'Employer', $nid]);
                $pdo->commit(); $ok++;
                notify($email, 'Your employer account', "Your account was created. Login: $email Temporary password: $pw (please change it).", 'employer', $nid);
            } catch (Throwable $t) { if ($pdo->inTransaction()) $pdo->rollBack(); $errs[] = "Row $n: " . $t->getMessage(); }
        }
        fclose($fh); $result = [$ok, $errs];
    }
}
page_header('Bulk Employer Upload', 'bulk'); ?>
<div class="card shadow-sm"><div class="card-body">
<p>Upload a CSV with columns: <code><?= implode(', ', $cols) ?></code>. Required: company_name, email. Employers are auto-approved and emailed a temporary password.</p>
<a class="btn btn-outline-secondary mb-3" href="?template=1">Download template</a>
<form method="post" enctype="multipart/form-data" class="d-flex gap-2"><?= csrf_field() ?><input type="file" name="file" accept=".csv" class="form-control w-auto" required><button class="btn btn-primary">Upload</button></form>
<?php if ($result): ?><hr><div class="alert alert-success"><?= $result[0] ?> employers added.</div>
<?php if ($result[1]): ?><div class="alert alert-warning"><b>Skipped:</b><ul class="mb-0"><?php foreach ($result[1] as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; endif; ?>
</div></div>
<?php page_footer();
