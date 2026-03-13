<?php
require_once '../../config/session.php';
session_destroy();
header('Location: /pos-system/modules/auth/login.php');
exit;