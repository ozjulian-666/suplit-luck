<?php
// =============================================
//  CONEXIÓN A LA BASE DE DATOS - Soplit.Luck
// =============================================
$host     = "localhost";
$usuario  = "root";
$password = "";
$base     = "soplit_luck";

$conexion = mysqli_connect($host, $usuario, $password, $base);

if (!$conexion) {
    die("❌ Error de conexión: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, "utf8mb4");
?>
