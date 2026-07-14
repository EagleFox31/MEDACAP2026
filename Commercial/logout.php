<?php
session_start();

$adminProfiles = ['Super Admin', 'Admin', 'Admin Commercial'];
$isAdmin = in_array($_SESSION['profile'] ?? '', $adminProfiles);
$type    = isset($_SESSION['commercial_type']) ? $_SESSION['commercial_type'] : '';

session_unset();
session_destroy();

// Effacer le cookie d'auto-login
setcookie('cfao_admin_email', '', time() - 3600, '/', '', false, true);

if ($isAdmin) {
    header('Location: responses.php');
} else {
    header('Location: register.php' . ($type ? '?type=' . urlencode($type) : ''));
}
exit();
