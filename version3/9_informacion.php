<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: 3_login.php");
    exit();
}

$id_usuario = (int)$_SESSION["usuario_id"];

// Verificar si el usuario sigue activo
$stmt_check = mysqli_prepare($conexion, "SELECT estado FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($stmt_check, "i", $id_usuario);
mysqli_stmt_execute($stmt_check);
$check = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
mysqli_stmt_close($stmt_check);

if (!$check || $check["estado"] !== "activo") {
    session_destroy();
    header("Location: 3_login.php?bloqueado=1");
    exit();
}

// Cargar datos del usuario
$stmt_u = mysqli_prepare($conexion, "SELECT * FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($stmt_u, "i", $id_usuario);
mysqli_stmt_execute($stmt_u);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u));
mysqli_stmt_close($stmt_u);

$msg_perfil   = "";
$msg_password = "";

// ── Guardar datos de perfil ──────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {
    $nuevo_nombre   = trim($_POST["nombre"]    ?? "");
    $nuevo_apellido = trim($_POST["apellido"]  ?? "");
    $nuevo_email    = trim($_POST["email"]     ?? "");
    $nuevo_tel      = trim($_POST["telefono"]  ?? "");

    if (empty($nuevo_nombre) || empty($nuevo_apellido)) {
        $msg_perfil = "❌ Nombre y apellido son obligatorios.";
    } elseif (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
        $msg_perfil = "❌ El correo electrónico no es válido.";
    } elseif (strlen($nuevo_nombre) > 60 || strlen($nuevo_apellido) > 60) {
        $msg_perfil = "❌ El nombre o apellido no puede superar 60 caracteres.";
    } else {
        // Verificar que el nuevo email no pertenezca a otro usuario
        $stmt_ec = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE email = ? AND id != ?");
        mysqli_stmt_bind_param($stmt_ec, "si", $nuevo_email, $id_usuario);
        mysqli_stmt_execute($stmt_ec);
        mysqli_stmt_store_result($stmt_ec);
        $email_ocupado = mysqli_stmt_num_rows($stmt_ec) > 0;
        mysqli_stmt_close($stmt_ec);

        if ($email_ocupado) {
            $msg_perfil = "❌ Ese correo ya está en uso por otra cuenta.";
        } else {
            $stmt_upd = mysqli_prepare($conexion,
                "UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, telefono = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt_upd, "ssssi", $nuevo_nombre, $nuevo_apellido, $nuevo_email, $nuevo_tel, $id_usuario);
            mysqli_stmt_execute($stmt_upd);
            mysqli_stmt_close($stmt_upd);

            $_SESSION["usuario_nombre"] = $nuevo_nombre . " " . $nuevo_apellido;
            $msg_perfil = "✅ Cambios guardados correctamente.";

            // Recargar usuario
            $stmt_u2 = mysqli_prepare($conexion, "SELECT * FROM usuarios WHERE id = ?");
            mysqli_stmt_bind_param($stmt_u2, "i", $id_usuario);
            mysqli_stmt_execute($stmt_u2);
            $usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u2));
            mysqli_stmt_close($stmt_u2);
        }
    }
}

// ── Cambiar contraseña ───────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cambiar_password"])) {
    $pass_actual = $_POST["password_actual"] ?? "";
    $pass_nueva  = $_POST["password_nueva"]  ?? "";
    $pass_conf   = $_POST["password_conf"]   ?? "";

    $hash_actual = $usuario["password_hash"];
    $ok = password_verify($pass_actual, $hash_actual) || $pass_actual === $hash_actual;

    if (!$ok) {
        $msg_password = "error|La contraseña actual es incorrecta.";
    } elseif (strlen($pass_nueva) < 6) {
        $msg_password = "error|La nueva contraseña debe tener al menos 6 caracteres.";
    } elseif ($pass_nueva !== $pass_conf) {
        $msg_password = "error|Las contraseñas nuevas no coinciden.";
    } else {
        $nuevo_hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
        $stmt_pw = mysqli_prepare($conexion, "UPDATE usuarios SET password_hash = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_pw, "si", $nuevo_hash, $id_usuario);
        mysqli_stmt_execute($stmt_pw);
        mysqli_stmt_close($stmt_pw);
        $msg_password = "ok|Contraseña actualizada correctamente.";

        $stmt_u3 = mysqli_prepare($conexion, "SELECT * FROM usuarios WHERE id = ?");
        mysqli_stmt_bind_param($stmt_u3, "i", $id_usuario);
        mysqli_stmt_execute($stmt_u3);
        $usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u3));
        mysqli_stmt_close($stmt_u3);
    }
}

// ── Boletos activos ──────────────────────────────────────────────────────────
$stmt_bol = mysqli_prepare($conexion,
    "SELECT b.*, r.titulo, r.fecha_fin, r.premio
     FROM boletas b
     JOIN rifas r ON b.id_rifa = r.id
     WHERE b.id_usuario = ? AND b.estado = 'vendida'
     ORDER BY b.fecha_compra DESC LIMIT 20"
);
mysqli_stmt_bind_param($stmt_bol, "i", $id_usuario);
mysqli_stmt_execute($stmt_bol);
$boletos_activos = mysqli_stmt_get_result($stmt_bol);

// ── Historial de pagos ───────────────────────────────────────────────────────
$stmt_hist = mysqli_prepare($conexion,
    "SELECT p.*, r.titulo as rifa_titulo
     FROM pagos p
     JOIN boletas b ON p.id_boleta = b.id
     JOIN rifas r ON b.id_rifa = r.id
     WHERE b.id_usuario = ?
     ORDER BY p.fecha_pago DESC LIMIT 10"
);
mysqli_stmt_bind_param($stmt_hist, "i", $id_usuario);
mysqli_stmt_execute($stmt_hist);
$historial = mysqli_stmt_get_result($stmt_hist);

[$pw_tipo, $pw_texto] = !empty($msg_password) ? explode("|", $msg_password, 2) : ["", ""];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | Soplit Luck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .profile-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<header class="bg-white shadow border-b px-6 py-4 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <a href="4_inicio.php" class="p-2 rounded hover:bg-gray-100">←</a>
        <h1 class="text-xl font-bold">Mi Perfil</h1>
    </div>
    <div class="flex gap-3">
        <a href="4_inicio.php" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Inicio</a>
        <a href="logout.php" class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700">Cerrar sesión</a>
    </div>
</header>

<section class="profile-gradient text-white py-10">
    <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center gap-6">
        <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center text-4xl font-bold">
            <?= strtoupper(substr($usuario["nombre"], 0, 1) . substr($usuario["apellido"] ?? "", 0, 1)) ?>
        </div>
        <div class="flex-1">
            <h2 class="text-3xl font-extrabold"><?= htmlspecialchars($usuario["nombre"] . " " . $usuario["apellido"]) ?></h2>
            <p class="text-purple-100"><?= htmlspecialchars($usuario["email"]) ?></p>
            <p class="text-purple-100 text-sm mt-1">Miembro desde: <?= date("d/m/Y", strtotime($usuario["fecha_registro"])) ?></p>
        </div>
    </div>
</section>

<main class="max-w-6xl mx-auto px-6 py-10 grid grid-cols-1 md:grid-cols-4 gap-8">

    <aside class="md:col-span-1 bg-white rounded-xl shadow p-6 space-y-4 self-start">
        <a href="#boletos"       class="block font-medium hover:text-blue-600">🎫 Mis boletos</a>
        <a href="#historial"     class="block font-medium hover:text-blue-600">📄 Historial</a>
        <a href="#configuracion" class="block font-medium hover:text-blue-600">⚙️ Datos personales</a>
        <a href="#contrasena"    class="block font-medium hover:text-blue-600">🔒 Contraseña</a>
        <hr>
        <a href="4_inicio.php" class="block bg-green-600 text-white text-center py-3 rounded-lg font-bold hover:bg-green-700">
            Comprar boletos
        </a>
    </aside>

    <section class="md:col-span-3 space-y-8">

        <!-- BOLETOS ACTIVOS -->
        <section id="boletos" class="bg-white rounded-xl shadow p-6">
            <h3 class="text-xl font-bold mb-4">Mis boletos activos</h3>
            <?php if (mysqli_num_rows($boletos_activos) === 0): ?>
                <p class="text-gray-500 text-center py-6">No tienes boletos activos. <a href="4_inicio.php" class="text-blue-600 font-medium">¡Participa en una rifa!</a></p>
            <?php else: ?>
                <div class="space-y-3">
                <?php while ($b = mysqli_fetch_assoc($boletos_activos)): ?>
                <div class="border rounded-lg p-4 flex justify-between items-center">
                    <div>
                        <p class="font-bold"><?= htmlspecialchars($b["titulo"]) ?></p>
                        <p class="text-sm text-gray-500">Boleto #<?= str_pad($b["numero"], 4, "0", STR_PAD_LEFT) ?> · <?= htmlspecialchars($b["premio"]) ?></p>
                        <p class="text-xs text-gray-400">Sorteo: <?= date("d/m/Y", strtotime($b["fecha_fin"])) ?></p>
                    </div>
                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded text-sm font-semibold">Activo</span>
                </div>
                <?php endwhile; ?>
                </div>
            <?php endif; ?>
            <?php mysqli_stmt_close($stmt_bol); ?>
        </section>

        <!-- HISTORIAL -->
        <section id="historial" class="bg-white rounded-xl shadow p-6">
            <h3 class="text-xl font-bold mb-4">Historial de pagos</h3>
            <?php if (mysqli_num_rows($historial) === 0): ?>
                <p class="text-gray-500 text-center py-4">Sin movimientos aún.</p>
            <?php else: ?>
                <ul class="space-y-3 text-sm">
                <?php while ($h = mysqli_fetch_assoc($historial)): ?>
                <li class="flex justify-between items-center border-b pb-2">
                    <div>
                        <span class="font-medium"><?= htmlspecialchars($h["rifa_titulo"]) ?></span>
                        <span class="text-gray-400 ml-2">(<?= ucfirst(htmlspecialchars($h["metodo_pago"])) ?>)</span>
                        <div class="text-xs text-gray-400"><?= date("d/m/Y H:i", strtotime($h["fecha_pago"])) ?></div>
                    </div>
                    <span class="text-red-600 font-semibold">- $<?= number_format($h["valor"], 0, ',', '.') ?></span>
                </li>
                <?php endwhile; ?>
                </ul>
            <?php endif; ?>
            <?php mysqli_stmt_close($stmt_hist); ?>
        </section>

        <!-- DATOS PERSONALES -->
        <section id="configuracion" class="bg-white rounded-xl shadow p-6">
            <h3 class="text-xl font-bold mb-4">⚙️ Datos Personales</h3>

            <?php if ($msg_perfil): ?>
                <div class="<?= str_starts_with($msg_perfil, '✅') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> p-3 rounded mb-4 text-sm">
                    <?= htmlspecialchars($msg_perfil) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                        <input type="text" name="nombre" required maxlength="60"
                               value="<?= htmlspecialchars($usuario["nombre"]) ?>"
                               class="w-full border rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Apellido</label>
                        <input type="text" name="apellido" maxlength="60"
                               value="<?= htmlspecialchars($usuario["apellido"] ?? '') ?>"
                               class="w-full border rounded px-3 py-2">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                    <input type="email" name="email" required maxlength="150"
                           value="<?= htmlspecialchars($usuario["email"]) ?>"
                           class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                    <input type="tel" name="telefono" maxlength="15"
                           value="<?= htmlspecialchars($usuario["telefono"] ?? '') ?>"
                           class="w-full border rounded px-3 py-2">
                </div>
                <button type="submit" name="guardar"
                        class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-semibold">
                    Guardar cambios
                </button>
            </form>
        </section>

        <!-- CAMBIO DE CONTRASEÑA -->
        <section id="contrasena" class="bg-white rounded-xl shadow p-6">
            <h3 class="text-xl font-bold mb-1">🔒 Cambio de Contraseña</h3>
            <p class="text-gray-500 text-sm mb-5">Para cambiar tu contraseña debes ingresar la contraseña actual primero.</p>

            <?php if ($pw_texto): ?>
                <div class="<?= $pw_tipo === 'ok' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> p-3 rounded mb-4 text-sm">
                    <?= $pw_tipo === 'ok' ? '✅' : '❌' ?> <?= htmlspecialchars($pw_texto) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4 max-w-md" novalidate>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña anterior</label>
                    <input type="password" name="password_actual" required
                           placeholder="Tu contraseña actual"
                           class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña nueva</label>
                    <input type="password" name="password_nueva" required minlength="6"
                           placeholder="Mínimo 6 caracteres"
                           class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña nueva</label>
                    <input type="password" name="password_conf" required minlength="6"
                           placeholder="Repite la nueva contraseña"
                           class="w-full border-2 border-gray-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" name="cambiar_password"
                        class="bg-purple-600 text-white px-6 py-2 rounded-xl hover:bg-purple-700 font-semibold">
                    🔒 Actualizar Contraseña
                </button>
            </form>
        </section>

    </section>
</main>

<footer class="bg-gray-900 text-white py-6 text-center">
    <p class="text-sm">&copy; 2024 Soplit Luck</p>
</footer>
</body>
</html>
