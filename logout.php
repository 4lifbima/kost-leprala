<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    log_audit($conn, $_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id']);
}

session_destroy();
header('Location: index.php');
exit();
?>