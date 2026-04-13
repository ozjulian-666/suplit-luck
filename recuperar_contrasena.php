<?php
session_start();
include("conexion.php");

$paso   = $_GET["paso"]  ?? "email";
$token  = $_GET["token"] ?? "";
$msg    = "";
$error  = "";

// ─── PASO 1: El usuario ingresa su correo ────────────────────────────────────
if ($paso === "email" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ingresa un correo electrónico válido.";
    } else {
        $stmt = mysqli_prepare($conexion, "SELECT id FROM usuarios WHERE email = ? AND estado = 'activo'");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) === 0) {
            // Seguridad: no revelar si el correo existe
            $msg = "Si ese correo está registrado, hemos generado un código de verificación.";
        } else {
            mysqli_stmt_bind_result($stmt, $id_usr);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            // Generar token único de 64 hex chars
            $token_gen = bin2hex(random_bytes(32));
            // Código numérico de 6 dígitos para verificar identidad
            $codigo_verificacion = str_pad(random_int(0, 999999), 6, "0", STR_PAD_LEFT);
            $expira = date("Y-m-d H:i:s", strtotime("+30 minutes"));

            // Invalidar tokens anteriores del mismo usuario
            $stmt_inv = mysqli_prepare($conexion,
                "UPDATE recuperacion_contrasena SET usado = 1 WHERE id_usuario = ?"
            );
            mysqli_stmt_bind_param($stmt_inv, "i", $id_usr);
            mysqli_stmt_execute($stmt_inv);
            mysqli_stmt_close($stmt_inv);

            $stmt_ins = mysqli_prepare($conexion,
                "INSERT INTO recuperacion_contrasena (id_usuario, token, fecha_expira) VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt_ins, "iss", $id_usr, $token_gen, $expira);
            mysqli_stmt_execute($stmt_ins);
            mysqli_stmt_close($stmt_ins);

            // Guardar código en sesión (simula envío por email)
            // En producción: enviar por email con PHPMailer
            $_SESSION["codigo_recuperacion"] = $codigo_verificacion;
            $_SESSION["token_recuperacion"]  = $token_gen;
            $_SESSION["email_recuperacion"]  = $email;

            // Redirigir al paso de verificación de código
            header("Location: recuperar_contrasena.php?paso=codigo");
            exit();
        }

        if (isset($stmt)) mysqli_stmt_close($stmt);
    }
}

// ─── PASO 2: Verificar el código numérico ───────────────────────────────────
if ($paso === "codigo" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $codigo_ingresado = trim($_POST["codigo"] ?? "");
    $codigo_guardado  = $_SESSION["codigo_recuperacion"] ?? "";
    $token_guardado   = $_SESSION["token_recuperacion"]  ?? "";

    if (empty($codigo_guardado) || empty($token_guardado)) {
        $error = "La sesión ha expirado. Intenta de nuevo.";
        $paso  = "email";
    } elseif ($codigo_ingresado !== $codigo_guardado) {
        $error = "El código de verificación es incorrecto.";
    } else {
        // Verificar que el token siga válido en BD
        $stmt_t = mysqli_prepare($conexion,
            "SELECT * FROM recuperacion_contrasena WHERE token = ? AND usado = 0 AND fecha_expira > NOW()"
        );
        mysqli_stmt_bind_param($stmt_t, "s", $token_guardado);
        mysqli_stmt_execute($stmt_t);
        $token_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t));
        mysqli_stmt_close($stmt_t);

        if (!$token_data) {
            $error = "El código ha expirado. Solicita uno nuevo.";
            $paso  = "expirado";
        } else {
            // Código correcto → ir a cambiar contraseña
            unset($_SESSION["codigo_recuperacion"]);
            header("Location: recuperar_contrasena.php?paso=nueva&token=" . urlencode($token_guardado));
            exit();
        }
    }
}

// ─── PASO 3: El usuario ingresa su nueva contraseña ─────────────────────────
$token_data = null;
if ($paso === "nueva") {
    if (empty($token)) {
        header("Location: recuperar_contrasena.php");
        exit();
    }

    $stmt_t = mysqli_prepare($conexion,
        "SELECT * FROM recuperacion_contrasena WHERE token = ? AND usado = 0 AND fecha_expira > NOW()"
    );
    mysqli_stmt_bind_param($stmt_t, "s", $token);
    mysqli_stmt_execute($stmt_t);
    $token_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_t));
    mysqli_stmt_close($stmt_t);

    if (!$token_data) {
        $error = "El enlace de recuperación es inválido o ha expirado.";
        $paso  = "expirado";
    }
}

if ($paso === "nueva" && $_SERVER["REQUEST_METHOD"] === "POST" && $token_data) {
    $nueva    = $_POST["nueva"]    ?? "";
    $confirma = $_POST["confirma"] ?? "";

    if (strlen($nueva) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($nueva !== $confirma) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $hash   = password_hash($nueva, PASSWORD_DEFAULT);
        $id_usr = (int)$token_data["id_usuario"];

        $stmt_up = mysqli_prepare($conexion, "UPDATE usuarios SET password_hash = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_up, "si", $hash, $id_usr);
        mysqli_stmt_execute($stmt_up);
        mysqli_stmt_close($stmt_up);

        $stmt_used = mysqli_prepare($conexion,
            "UPDATE recuperacion_contrasena SET usado = 1 WHERE token = ?"
        );
        mysqli_stmt_bind_param($stmt_used, "s", $token);
        mysqli_stmt_execute($stmt_used);
        mysqli_stmt_close($stmt_used);

        // Limpiar sesión de recuperación
        unset($_SESSION["token_recuperacion"], $_SESSION["email_recuperacion"]);

        header("Location: 3_login.php?recuperado=ok");
        exit();
    }
}

// Leer código de sesión para mostrarlo (solo en entorno de desarrollo)
$codigo_demo = $_SESSION["codigo_recuperacion"] ?? "";
$email_demo  = $_SESSION["email_recuperacion"]  ?? "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | Soplit.Luck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-gradient-luck { background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 50%, #1E3A8A 100%); }
    </style>
</head>
<body class="bg-gradient-luck min-h-screen flex items-center justify-center p-6">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">

        <div class="text-center mb-8">
            <a href="1_invitado.php" class="text-3xl font-black">🍀 Soplit.Luck</a>
            <h2 class="text-2xl font-bold mt-3 mb-1">Recuperar Contraseña</h2>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-300 text-red-700 p-3 rounded-lg mb-5 text-sm">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($msg): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded-lg mb-5 text-sm text-center">
                ℹ️ <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <!-- PASO 1: Ingresar correo -->
        <?php if ($paso === "email" && !$msg): ?>
        <p class="text-gray-500 text-sm mb-6 text-center">
            Ingresa tu correo electrónico y te enviaremos un código de verificación de 6 dígitos.
        </p>
        <form method="POST" action="recuperar_contrasena.php?paso=email" class="space-y-5" novalidate>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Correo electrónico</label>
                <input type="email" name="email" required maxlength="150" placeholder="tu@correo.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition-colors">
                Enviar código →
            </button>
        </form>

        <!-- PASO 2: Verificar código -->
        <?php elseif ($paso === "codigo"): ?>
        <div class="text-center mb-6">
            <div class="text-4xl mb-3">📱</div>
            <p class="text-gray-600 text-sm">
                Hemos generado un código de verificación para <strong><?= htmlspecialchars($email_demo) ?></strong>.
            </p>
        </div>

        <!-- En desarrollo: mostrar código (en producción esto se enviaría por email) -->
        <?php if (!empty($codigo_demo) && (defined('APP_ENV') ? APP_ENV === 'development' : true)): ?>
        <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 p-4 rounded-xl mb-5 text-center">
            <p class="text-xs font-semibold mb-1">📧 Código enviado (demo - en producción va por email):</p>
            <p class="text-3xl font-mono font-black tracking-widest text-yellow-700"><?= $codigo_demo ?></p>
            <p class="text-xs text-yellow-600 mt-1">Válido por 30 minutos</p>
        </div>
        <?php endif; ?>

        <form method="POST" action="recuperar_contrasena.php?paso=codigo" class="space-y-5" novalidate>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Código de verificación</label>
                <input type="text" name="codigo" required maxlength="6" placeholder="000000"
                       autocomplete="one-time-code"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-center text-2xl font-mono tracking-widest"
                       oninput="this.value=this.value.replace(/[^0-9]/g,'').substring(0,6)">
                <p class="text-xs text-gray-400 mt-1 text-center">Ingresa el código de 6 dígitos</p>
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition-colors">
                Verificar código →
            </button>
        </form>
        <div class="mt-4 text-center">
            <a href="recuperar_contrasena.php" class="text-sm text-gray-500 hover:text-blue-600">← Usar otro correo</a>
        </div>

        <!-- PASO 3: Nueva contraseña -->
        <?php elseif ($paso === "nueva" && $token_data): ?>
        <p class="text-gray-500 text-sm mb-6 text-center">
            Identidad verificada ✅ Crea una nueva contraseña segura para tu cuenta.
        </p>
        <form method="POST" action="recuperar_contrasena.php?paso=nueva&token=<?= htmlspecialchars(urlencode($token)) ?>" class="space-y-5" novalidate>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nueva contraseña</label>
                <input type="password" name="nueva" required minlength="6"
                       placeholder="Mínimo 6 caracteres"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Confirmar contraseña</label>
                <input type="password" name="confirma" required minlength="6"
                       placeholder="Repite la contraseña"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition-colors">
                Guardar nueva contraseña
            </button>
        </form>

        <?php elseif ($paso === "expirado"): ?>
        <div class="text-center py-6">
            <div class="text-5xl mb-4">⏱️</div>
            <p class="text-gray-600 mb-6">El código expiró o ya fue usado. Solicita uno nuevo.</p>
            <a href="recuperar_contrasena.php" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700">
                Intentar de nuevo
            </a>
        </div>

        <?php elseif ($msg): ?>
        <div class="text-center py-4">
            <div class="text-5xl mb-4">📧</div>
            <p class="text-gray-600 mb-6">Si tu correo estaba registrado, revisa el código generado.</p>
            <a href="recuperar_contrasena.php" class="text-blue-600 underline text-sm">Volver a intentarlo</a>
        </div>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="3_login.php" class="text-sm text-gray-500 hover:text-blue-600">← Volver al inicio de sesión</a>
        </div>
    </div>

</body>
</html>
