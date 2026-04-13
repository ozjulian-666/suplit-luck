<?php
include("conexion.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre    = trim($_POST["nombre"]    ?? "");
    $apellido  = trim($_POST["apellido"]  ?? "");
    $email     = trim($_POST["email"]     ?? "");
    $telefono  = trim($_POST["telefono"]  ?? "");
    $cedula    = trim($_POST["cedula"]    ?? "");
    $ciudad    = trim($_POST["ciudad"]    ?? "");
    $password  = $_POST["password"]  ?? "";
    $password2 = $_POST["password2"] ?? "";
    $terms     = isset($_POST["terms"]);
    $age       = isset($_POST["age"]);

    // Validaciones
    if (!$terms || !$age) {
        $error = "Debes aceptar los términos y confirmar que eres mayor de edad.";
    } elseif (empty($nombre) || empty($apellido)) {
        $error = "Nombre y apellido son obligatorios.";
    } elseif (strlen($nombre) > 60 || strlen($apellido) > 60) {
        $error = "El nombre o apellido no puede superar 60 caracteres.";
    } elseif (empty($email)) {
        $error = "El correo electrónico es obligatorio.";
    } elseif (empty($cedula) || !ctype_digit($cedula) || strlen($cedula) < 6 || strlen($cedula) > 15) {
        $error = "Ingresa un número de cédula válido (solo números, entre 6 y 15 dígitos).";
    } elseif (empty($ciudad)) {
        $error = "Ingresa tu ciudad de residencia.";
    } elseif ($password !== $password2) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        // Verificar email duplicado con prepared statement
        $stmt = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Ya existe una cuenta con ese correo electrónico.";
        } else {
            mysqli_stmt_close($stmt);

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt2 = mysqli_prepare($conexion,
                "INSERT INTO usuarios (nombre, apellido, email, telefono, cedula, ciudad, password_hash, rol, estado, saldo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'participante', 'activo', 0.00)"
            );
            mysqli_stmt_bind_param($stmt2, "sssssss", $nombre, $apellido, $email, $telefono, $cedula, $ciudad, $hash);

            if (mysqli_stmt_execute($stmt2)) {
                mysqli_stmt_close($stmt2);
                header("Location: 3_login.php?registro=ok");
                exit();
            } else {
                $error = "Error al registrar. Intenta de nuevo.";
                mysqli_stmt_close($stmt2);
            }
        }

        if (isset($stmt) && $stmt) mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soplit.Luck - Registro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-gradient-luck { background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 50%, #60A5FA 100%); }
        .card-shadow { box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25); }
    </style>
</head>
<body class="bg-gradient-luck min-h-screen">
<div class="min-h-screen flex">
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
        <div class="w-full max-w-lg">
            <div class="bg-white rounded-2xl p-8 card-shadow">
                <div class="text-center mb-6">
                    <a href="1_invitado.php" class="text-2xl font-black">🍀 Soplit.Luck</a>
                    <h2 class="text-3xl font-bold text-gray-900 mt-2 mb-1">Crear Cuenta</h2>
                    <p class="text-gray-500 text-sm">Únete a la comunidad de ganadores</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-300 text-red-700 p-3 rounded-lg mb-5 text-sm flex items-start gap-2">
                        <span>❌</span><span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4" novalidate>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Nombre *</label>
                            <input type="text" name="nombre" required maxlength="60"
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Apellido *</label>
                            <input type="text" name="apellido" required maxlength="60"
                                   value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Correo Electrónico *</label>
                        <input type="email" name="email" required maxlength="150"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Teléfono</label>
                            <input type="tel" name="telefono" maxlength="15"
                                   value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Cédula *</label>
                            <input type="text" name="cedula" required maxlength="15"
                                   value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>"
                                   placeholder="Solo números"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Ciudad de Residencia *</label>
                        <input type="text" name="ciudad" required maxlength="80"
                               value="<?= htmlspecialchars($_POST['ciudad'] ?? '') ?>"
                               placeholder="Ej: Bogotá"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Contraseña *</label>
                            <input type="password" name="password" required minlength="6"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Confirmar Contraseña *</label>
                            <input type="password" name="password2" required
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-3 space-y-2">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="terms" required class="mt-0.5">
                            <span class="text-xs text-gray-600">Acepto los <a href="#" class="text-blue-600 font-semibold underline">Términos y Condiciones</a> y la política de privacidad</span>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="age" required class="mt-0.5">
                            <span class="text-xs text-gray-600">Confirmo que soy mayor de 18 años</span>
                        </label>
                    </div>

                    <button type="submit"
                            class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-3 rounded-xl font-bold text-sm hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg">
                        🍀 Crear Mi Cuenta Gratis
                    </button>
                </form>

                <div class="mt-5 text-center">
                    <p class="text-gray-500 text-sm">¿Ya tienes cuenta?
                        <a href="3_login.php" class="text-blue-600 font-bold hover:underline">Inicia sesión aquí</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="hidden lg:flex lg:w-1/2 bg-white/10 backdrop-blur-sm items-center justify-center p-12 text-white">
        <div class="text-center max-w-md">
            <div class="text-7xl mb-6">🍀</div>
            <h3 class="text-4xl font-bold mb-4">¡Únete Hoy!</h3>
            <p class="text-blue-100 mb-8 text-lg">Regístrate gratis y empieza a ganar premios increíbles o crea tus propias rifas</p>
            <div class="space-y-3 text-left bg-white/10 rounded-2xl p-6">
                <div class="flex items-center gap-3"><span class="text-2xl">🎫</span><span>Participa en sorteos exclusivos</span></div>
                <div class="flex items-center gap-3"><span class="text-2xl">🏆</span><span>Crea y administra tus propias rifas</span></div>
                <div class="flex items-center gap-3"><span class="text-2xl">💰</span><span>Gana dinero vendiendo boletos</span></div>
                <div class="flex items-center gap-3"><span class="text-2xl">🔒</span><span>Pagos seguros y transparentes</span></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
