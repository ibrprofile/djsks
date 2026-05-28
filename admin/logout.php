<?php
session_start();
session_destroy();

require_once __DIR__ . '/../includes/functions.php';
redirect(url('admin/login.php'));
?>
