<?php
session_start();
include("conexion.php");

$error = "";

if (isset($_SESSION["usuario_id"])) {
    header("Location: 4_inicio.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"]    ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error = "Ingresa tu correo y contraseña.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo electrónico no es válido.";
    } else {
        // Prepared statement para evitar SQL Injection
        $stmt = mysqli_prepare($conexion, "SELECT * FROM usuarios WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);

        if ($resultado && mysqli_num_rows($resultado) > 0) {
            $usuario = mysqli_fetch_assoc($resultado);

            if ($usuario["estado"] !== "activo") {
                $error = "Tu cuenta está " . htmlspecialchars($usuario["estado"]) . ". Contacta al administrador.";
            } else {
                // Verificar contraseña: soporta hash moderno y texto plano legacy
                $ok = password_verify($password, $usuario["password_hash"])
                   || (strpos($usuario["password_hash"], '$2y$') !== 0 && $password === $usuario["password_hash"]);

                if ($ok) {
                    // Regenerar session ID por seguridad
                    session_regenerate_id(true);

                    $_SESSION["usuario_id"]     = $usuario["id"];
                    $_SESSION["usuario_nombre"] = $usuario["nombre"] . " " . $usuario["apellido"];
                    $_SESSION["usuario_email"]  = $usuario["email"];
                    $_SESSION["usuario_rol"]    = $usuario["rol"];

                    mysqli_stmt_close($stmt);

                    if ($usuario["rol"] === "admin") {
                        header("Location: 11_dashboard_admin.php");
                    } else {
                        header("Location: 4_inicio.php");
                    }
                    exit();
                } else {
                    $error = "Contraseña incorrecta.";
                }
            }
        } else {
            $error = "No existe una cuenta con ese correo.";
        }

        mysqli_stmt_close($stmt);
    }
}

$registroOk = isset($_GET["registro"]) && $_GET["registro"] === "ok";
$bloqueado  = isset($_GET["bloqueado"]) && $_GET["bloqueado"] === "1";
$recuperado = isset($_GET["recuperado"]) && $_GET["recuperado"] === "ok";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soplit.Luck - Iniciar Sesión</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-gradient-luck { background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 50%, #1E3A8A 100%); }
    </style>
</head>
<body class="bg-gradient-luck min-h-screen overflow-hidden">
<div class="min-h-screen flex">

    <div class="w-1/2 bg-gradient-to-br from-blue-800/80 to-blue-900/90 p-12 flex flex-col justify-center">
        <div>
            <div class="mb-12">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white/10 rounded-2xl mb-6">
                    <span class="text-4xl">🍀</span>
                </div>
                <h1 class="text-6xl font-bold text-white mb-4">Soplit.Luck</h1>
                <p class="text-2xl text-blue-100">Plataforma líder en rifas virtuales</p>
            </div>
            <div class="space-y-4 text-white text-xl">
                <p>✔ Sorteos transparentes</p>
                <p>✔ Premios diarios</p>
                <p>✔ Pagos seguros</p>
            </div>
        </div>
    </div>

    <div class="w-1/2 flex items-center justify-center p-12 bg-white">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h2 class="text-4xl font-bold mb-2">¡Bienvenido!</h2>
                <p class="text-gray-600">Inicia sesión para continuar</p>
            </div>

            <?php if ($registroOk): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-sm text-center">
                    🎉 Registro exitoso. Ya puedes iniciar sesión.
                </div>
            <?php endif; ?>

            <?php if ($bloqueado): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm text-center">
                    🚫 Tu sesión fue cerrada porque tu cuenta fue desactivada. Contacta al administrador.
                </div>
            <?php endif; ?>

            <?php if ($recuperado): ?>
                <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4 text-sm text-center">
                    ✅ Contraseña cambiada correctamente. Ya puedes iniciar sesión.
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm">
                    ❌ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6" novalidate>
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Correo Electrónico</label>
                    <input type="email" name="email" required maxlength="150"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Contraseña</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl">
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white py-4 rounded-xl font-semibold hover:bg-blue-700">
                    Iniciar Sesión
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="recuperar_contrasena.php" class="text-sm text-blue-500 hover:underline">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>

            <div class="text-center mt-4">
                <p class="text-gray-600">¿No tienes cuenta?
                    <a href="2_registro.php" class="text-blue-600 font-semibold hover:underline">Regístrate gratis</a>
                </p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
