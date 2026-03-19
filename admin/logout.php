<?php
require_once 'proteccion.php';
session_destroy();
header("Location: login.php");
exit;
