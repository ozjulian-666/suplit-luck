<?php
session_start();
include("conexion.php");

if (isset($_SESSION["usuario_rol"]) && $_SESSION["usuario_rol"] === "admin") {
    header("Location: 11_dashboard_admin.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"]    ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $error = "Ingresa tu correo y contraseña.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo electrónico no es válido.";
    } else {
        // Prepared statement - solo admins activos
        $stmt = mysqli_prepare($conexion,
            "SELECT * FROM usuarios WHERE email = ? AND rol = 'admin' AND estado = 'activo'"
        );
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res  = mysqli_stmt_get_result($stmt);

        if ($res && mysqli_num_rows($res) > 0) {
            $admin = mysqli_fetch_assoc($res);
            $ok = password_verify($password, $admin["password_hash"])
               || (strpos($admin["password_hash"], '$2y$') !== 0 && $password === $admin["password_hash"]);

            if ($ok) {
                // Regenerar session ID al autenticar
                session_regenerate_id(true);

                $_SESSION["usuario_id"]     = $admin["id"];
                $_SESSION["usuario_nombre"] = $admin["nombre"] . " " . $admin["apellido"];
                $_SESSION["usuario_email"]  = $admin["email"];
                $_SESSION["usuario_rol"]    = "admin";

                mysqli_stmt_close($stmt);
                header("Location: 11_dashboard_admin.php");
                exit();
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "No existe un administrador con ese correo o está inactivo.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login Administrador | Soplit Luck</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-gradient-admin { background: linear-gradient(135deg, #1E3A8A 0%, #1E40AF 50%, #2563EB 100%); }
    </style>
</head>
<body class="bg-gradient-admin min-h-screen flex">

    <!-- Panel izquierdo (info) -->
    <div class="hidden lg:flex lg:w-1/2 flex-col justify-center p-14 text-white">
        <div class="mb-10">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white/10 rounded-2xl mb-6">
                <span class="text-4xl">🍀</span>
            </div>
            <h1 class="text-5xl font-black mb-3">Soplit.Luck</h1>
            <p class="text-xl text-blue-200">Panel de Administración</p>
        </div>

        <div class="space-y-4 text-blue-100">
            <div class="flex items-center gap-3 bg-white/10 rounded-xl px-5 py-4">
                <span class="text-2xl">📊</span>
                <div>
                    <p class="font-semibold text-white">Dashboard completo</p>
                    <p class="text-sm">Ingresos, rifas activas y usuarios en tiempo real</p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white/10 rounded-xl px-5 py-4">
                <span class="text-2xl">👥</span>
                <div>
                    <p class="font-semibold text-white">Gestión de usuarios</p>
                    <p class="text-sm">Consulta, filtra y administra todos los usuarios</p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white/10 rounded-xl px-5 py-4">
                <span class="text-2xl">🎰</span>
                <div>
                    <p class="font-semibold text-white">Control de rifas</p>
                    <p class="text-sm">Monitorea el progreso de cada sorteo activo</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel derecho (formulario) -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white">
        <div class="w-full max-w-md">

            <div class="text-center mb-8">
                <div class="lg:hidden text-4xl mb-3">🍀</div>
                <h2 class="text-3xl font-bold text-gray-900 mb-1">Acceso Administrador</h2>
                <p class="text-gray-500">Ingresa tus credenciales de administrador</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-200 text-red-700 p-4 rounded-xl mb-6 text-sm flex items-center gap-2">
                    <span>❌</span> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5" novalidate>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Correo electrónico</label>
                    <input type="email" name="email" required maxlength="150"
                           placeholder="admin@soplit.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Contraseña</label>
                    <input type="password" name="password" required
                           placeholder="••••••••"
                           class="w-full px-4 py-4 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>
                <button type="submit"
                        class="w-full bg-blue-600 text-white py-4 rounded-xl font-bold text-base hover:bg-blue-700 transition-colors shadow-lg">
                    Iniciar sesión como Administrador
                </button>
            </form>

<div class="mt-6 text-center">
                <a href="1_invitado.php" class="text-sm text-gray-500 hover:text-blue-600 transition-colors">
                    ← Volver al sitio principal
                </a>
            </div>
        </div>
    </div>

</body>
</html>
