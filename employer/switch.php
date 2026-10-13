<?php
require_once '../includes/bootstrap.php';
require_login(['EmpAdmin', 'SuperAdmin']); csrf_check();
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id && val("SELECT COUNT(*) FROM employers WHERE id=? AND status='Approved'", [$id])) { $_SESSION['acting_emp'] = $id; redirect('employer/dashboard.php'); }
unset($_SESSION['acting_emp']); redirect('admin/dashboard.php');
