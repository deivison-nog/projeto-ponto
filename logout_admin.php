<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['admin_logged_in'], $_SESSION['admin_login']);
header('Location: login.php');
exit;
