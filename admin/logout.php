<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$_SESSION = [];
session_destroy();
redirect('login.php');
