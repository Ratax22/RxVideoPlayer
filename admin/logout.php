<?php

session_start();
require_once '../config.php';       // conexión + constantes
require_once 'proteccion.php';      // chequeo de sesión y rol

session_destroy();
header("Location: login.php");
exit;
