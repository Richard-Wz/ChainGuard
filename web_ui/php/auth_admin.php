<?php
session_start();

// Check if the user is authenticated and has the 'admin' role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../php/login.php");
    exit;
}
?>
