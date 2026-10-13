<?php
require_once 'includes/bootstrap.php';
$_SESSION = []; session_destroy();
header('Location: ' . url('index.php')); exit;
