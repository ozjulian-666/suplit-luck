<?php
session_start();
$id = (int)($_GET["id"] ?? 0);
if ($id > 0) {
    if (!isset($_SESSION["notif_vistas"])) {
        $_SESSION["notif_vistas"] = [];
    }
    if (!in_array($id, $_SESSION["notif_vistas"])) {
        $_SESSION["notif_vistas"][] = $id;
    }
    // Limpiar lista si tiene más de 200 IDs para no inflar la sesión
    if (count($_SESSION["notif_vistas"]) > 200) {
        $_SESSION["notif_vistas"] = array_slice($_SESSION["notif_vistas"], -100);
    }
}
http_response_code(200);
exit();
