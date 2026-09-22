<?php
require_once '../config/sesion.php';
verificarAdmin();
header('Location: dashboard.php');
exit;
