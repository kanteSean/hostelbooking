<?php
session_start();

/* Remove session data */
unset($_SESSION['manager_hostelid']);
unset($_SESSION['manager_hostelname']);
unset($_SESSION['manager_name']);

/* Destroy session completely */
session_destroy();

/* Redirect to login */
header("Location: manager_login.php");
exit();
?>