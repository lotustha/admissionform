<?php
session_start();
unset($_SESSION['student_id']);
header("Location: status_check.php");
exit;
