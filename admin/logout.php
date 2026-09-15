<?php
require_once __DIR__ . '/../includes/functions.php';
unset($_SESSION['admin']);
session_destroy();
header('Location: login.php');
exit;
