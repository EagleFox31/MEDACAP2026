<?php
session_start();

// Unset all session variables specific to concours
unset($_SESSION["concours_username"]);
unset($_SESSION["concours_profile"]);
unset($_SESSION["concours_lastName"]);
unset($_SESSION["concours_firstName"]);
unset($_SESSION["concours_email"]);
unset($_SESSION["concours_id"]);
unset($_SESSION["concours_lang"]);

header("Location: index.php");
exit;
?>
