<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: 3_login.php");
    exit();
}

$id_usuario = (int)$_SESSION["usuario_id"];
$error  = "";
$exito  = "";

// ── ELIMINAR RIFA ────────────────────────────────────────────────────────────
if (isset($_GET["eliminar"])) {
    $rid = (int)$_GET["eliminar"];

    $stmt_chk = mysqli_prepare($conexion,
        "SELECT id FROM rifas WHERE id = ? AND id_organizador = ? AND estado = 'activa'"
    );
    mysqli_stmt_bind_param($stmt_chk, "ii", $rid, $id_usuario);
    mysqli_stmt_execute($stmt_chk);
    mysqli_stmt_store_result($stmt_chk);
    $existe = mysqli_stmt_num_rows($stmt_chk) > 0;
    mysqli_stmt_close($stmt_chk);

    if (!$existe) {
        $error = "No puedes eliminar esa rifa.";
    } else {
        $stmt_v = mysqli_prepare($conexion,
            "SELECT COUNT(*) as total FROM boletas WHERE id_rifa = ? AND estado = 'vendida'"
        );
        mysqli_stmt_bind_param($stmt_v, "i", $rid);
        mysqli_stmt_execute($stmt_v);
        $vendidas_chk = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_v))["total"];
        mysqli_stmt_close($stmt_v);

        if ($vendidas_chk > 0) {
            $error = "No puedes eliminar una rifa que ya tiene boletas vendidas ($vendidas_chk vendidas).";
        } else {
            mysqli_begin_transaction($conexion);
            try {
                $stmt_db = mysqli_prepare($conexion, "DELETE FROM boletas WHERE id_rifa = ?");
                mysqli_stmt_bind_param($stmt_db, "i", $rid);
                mysqli_stmt_execute($stmt_db);
                mysqli_stmt_close($stmt_db);

                $stmt_dr = mysqli_prepare($conexion, "DELETE FROM rifas WHERE id = ? AND id_organizador = ?");
                mysqli_stmt_bind_param($stmt_dr, "ii", $rid, $id_usuario);
                mysqli_stmt_execute($stmt_dr);
                mysqli_stmt_close($stmt_dr);

                mysqli_commit($conexion);
                $exito = "Rifa eliminada correctamente.";
            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $error = "Error al eliminar la rifa. Intenta de nuevo.";
            }
        }
    }
}

// ── CARGAR RIFA PARA EDITAR ──────────────────────────────────────────────────
$rifa_editar  = null;
$modo_edicion = false;

if (isset($_GET["editar"]) && empty($error)) {
    $rid_edit = (int)$_GET["editar"];
    $stmt_e = mysqli_prepare($conexion,
        "SELECT * FROM rifas WHERE id = ? AND id_organizador = ?"
    );
    mysqli_stmt_bind_param($stmt_e, "ii", $rid_edit, $id_usuario);
    mysqli_stmt_execute($stmt_e);
    $rifa_editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_e));
    mysqli_stmt_close($stmt_e);

    if ($rifa_editar) {
        $modo_edicion = true;
    } else {
        $error = "No puedes editar esa rifa.";
    }
}

// ── GUARDAR EDICIÓN ──────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["editar_id"])) {
    $rid_upd     = (int)$_POST["editar_id"];
    $titulo      = trim($_POST["titulo"]      ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $premio      = trim($_POST["premio"]      ?? "");
    $fecha_fin   = trim($_POST["fecha_fin"]   ?? "");
    $imagen_url  = trim($_POST["imagen_url"]  ?? "");

    $stmt_own = mysqli_prepare($conexion,
        "SELECT id FROM rifas WHERE id = ? AND id_organizador = ?"
    );
    mysqli_stmt_bind_param($stmt_own, "ii", $rid_upd, $id_usuario);
    mysqli_stmt_execute($stmt_own);
    mysqli_stmt_store_result($stmt_own);
    $es_suya = mysqli_stmt_num_rows($stmt_own) > 0;
    mysqli_stmt_close($stmt_own);

    if (!$es_suya) {
        $error = "No tienes permiso para editar esa rifa.";
    } elseif (empty($titulo) || empty($premio) || empty($fecha_fin)) {
        $error = "Título, premio y fecha de cierre son obligatorios.";
    } elseif (strlen($titulo) > 150) {
        $error = "El título no puede superar 150 caracteres.";
    } elseif (strtotime($fecha_fin) === false || strtotime($fecha_fin) <= time()) {
        $error = "La fecha de cierre debe ser una fecha futura válida.";
    } elseif (!empty($imagen_url) && !filter_var($imagen_url, FILTER_VALIDATE_URL)) {
        $error = "La URL de imagen no es válida.";
    } else {
        $stmt_upd = mysqli_prepare($conexion,
            "UPDATE rifas SET titulo=?, descripcion=?, premio=?, fecha_fin=?, imagen_url=? WHERE id=? AND id_organizador=?"
        );
        mysqli_stmt_bind_param($stmt_upd, "sssssii",
            $titulo, $descripcion, $premio, $fecha_fin, $imagen_url, $rid_upd, $id_usuario
        );
        if (mysqli_stmt_execute($stmt_upd)) {
            $exito = "¡Rifa actualizada correctamente!";
            $modo_edicion = false;
            $rifa_editar  = null;
        } else {
            $error = "Error al actualizar. Intenta de nuevo.";
        }
        mysqli_stmt_close($stmt_upd);
    }

    // Si hubo error al editar, recargar datos del formulario
    if ($error) {
        $rifa_editar = [
            'id' => $rid_upd, 'titulo' => $titulo, 'descripcion' => $descripcion,
            'premio' => $premio, 'fecha_fin' => $fecha_fin, 'imagen_url' => $imagen_url,
            'precio_boleta' => 0, 'cantidad_boletas' => 0
        ];
        $modo_edicion = true;
    }
}

// ── CREAR RIFA NUEVA ─────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["editar_id"])) {
    $titulo      = trim($_POST["titulo"]           ?? "");
    $descripcion = trim($_POST["descripcion"]      ?? "");
    $premio      = trim($_POST["premio"]           ?? "");
    $precio      = (float)($_POST["precio_boleta"] ?? 0);
    $cantidad    = (int)($_POST["cantidad_boletas"] ?? 0);
    $fecha_fin   = trim($_POST["fecha_fin"]         ?? "");
    $imagen_url  = trim($_POST["imagen_url"]        ?? "");

    if (empty($titulo) || empty($premio) || empty($fecha_fin)) {
        $error = "Título, premio y fecha de cierre son obligatorios.";
    } elseif (strlen($titulo) > 150) {
        $error = "El título no puede superar 150 caracteres.";
    } elseif ($precio < 500) {
        $error = "El precio mínimo por boleta es $500.";
    } elseif ($cantidad < 5 || $cantidad > 1000) {
        $error = "La cantidad de boletas debe estar entre 5 y 1000.";
    } elseif (strtotime($fecha_fin) === false || strtotime($fecha_fin) <= time()) {
        $error = "La fecha de cierre debe ser una fecha futura válida.";
    } elseif (!empty($imagen_url) && !filter_var($imagen_url, FILTER_VALIDATE_URL)) {
        $error = "La URL de imagen no es válida.";
    } else {
        $req_legales = "Rifa creada por usuario en plataforma Soplit.Luck";
        $estado      = "activa";

        $stmt = mysqli_prepare($conexion,
            "INSERT INTO rifas (id_organizador, titulo, descripcion, premio, precio_boleta, cantidad_boletas, fecha_inicio, fecha_fin, estado, requisitos_legales, imagen_url)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "isssdissss",
            $id_usuario, $titulo, $descripcion, $premio,
            $precio, $cantidad, $fecha_fin, $estado, $req_legales, $imagen_url
        );

        if (mysqli_stmt_execute($stmt)) {
            $id_rifa_nueva = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);

            $stmt_b = mysqli_prepare($conexion,
                "INSERT INTO boletas (id_rifa, numero, estado) VALUES (?, ?, 'disponible')"
            );
            for ($n = 1; $n <= $cantidad; $n++) {
                mysqli_stmt_bind_param($stmt_b, "ii", $id_rifa_nueva, $n);
                mysqli_stmt_execute($stmt_b);
            }
            mysqli_stmt_close($stmt_b);
            $exito = "¡Rifa creada exitosamente! Ya está visible para todos los usuarios.";
        } else {
            $error = "Error al crear la rifa: " . mysqli_error($conexion);
            if (isset($stmt)) mysqli_stmt_close($stmt);
        }
    }
}

// ── MIS RIFAS ────────────────────────────────────────────────────────────────
$stmt_mis = mysqli_prepare($conexion,
    "SELECT r.*,
        (SELECT COUNT(*) FROM boletas b WHERE b.id_rifa = r.id AND b.estado='vendida') as vendidas
     FROM rifas r WHERE r.id_organizador = ? ORDER BY r.fecha_creacion DESC"
);
mysqli_stmt_bind_param($stmt_mis, "i", $id_usuario);
mysqli_stmt_execute($stmt_mis);
$mis_rifas = mysqli_stmt_get_result($stmt_mis);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modo_edicion ? "Editar Rifa" : "Crear Rifa" ?> | Soplit.Luck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- NAV -->
<nav class="bg-white shadow-lg border-b sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <span class="text-xl">🍀</span>
                </div>
                <span class="text-2xl font-bold">Soplit.Luck</span>
            </div>
            <div class="hidden md:flex items-center space-x-1">
                <a href="4_inicio.php" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-lg hover:bg-gray-50 transition-colors">Inicio</a>
                <a href="9_informacion.php" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-lg hover:bg-gray-50 transition-colors">Mi Perfil</a>
                <a href="crear_rifa.php" class="px-4 py-2 text-blue-600 font-semibold border-b-2 border-blue-600">Crear Rifa</a>
                <a href="mi_saldo.php" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-lg hover:bg-gray-50 transition-colors">Mi Saldo</a>
            </div>
            <div class="flex items-center gap-3">
                <a href="9_informacion.php" class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                    <?= strtoupper(substr($_SESSION["usuario_nombre"], 0, 2)) ?>
                </a>
                <a href="logout.php" class="text-sm text-red-600 font-semibold">Salir</a>
            </div>
        </div>
    </div>
</nav>

<!-- HERO -->
<div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white py-10 text-center">
    <h1 class="text-4xl font-bold mb-2">
        <?= $modo_edicion ? "✏️ Editar Rifa" : "🎰 Crea tu Propia Rifa" ?>
    </h1>
    <p class="text-purple-100 text-lg">
        <?= $modo_edicion ? "Modifica los detalles de tu rifa" : "Organiza sorteos, vende boletos y gana dinero" ?>
    </p>
</div>

<div class="max-w-7xl mx-auto px-6 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

        <!-- FORMULARIO -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <h2 class="text-2xl font-bold mb-6">
                    <?= $modo_edicion ? "📝 Modificar datos" : "📋 Detalles de tu Rifa" ?>
                </h2>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-300 text-red-700 p-4 rounded-xl mb-6 text-sm">
                        ❌ <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if ($exito): ?>
                    <div class="bg-green-100 border border-green-300 text-green-800 p-4 rounded-xl mb-6 text-sm">
                        🎉 <?= htmlspecialchars($exito) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6" novalidate>
                    <?php if ($modo_edicion): ?>
                        <input type="hidden" name="editar_id" value="<?= (int)$rifa_editar['id'] ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Título de la Rifa *</label>
                        <input type="text" name="titulo" required maxlength="150"
                               value="<?= htmlspecialchars($modo_edicion ? ($rifa_editar['titulo'] ?? '') : ($_POST['titulo'] ?? '')) ?>"
                               placeholder="Ej: Rifa iPhone 15 Pro"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Descripción</label>
                        <textarea name="descripcion" rows="3" maxlength="500"
                                  placeholder="Describe tu rifa, requisitos, fecha del sorteo..."
                                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500"><?= htmlspecialchars($modo_edicion ? ($rifa_editar['descripcion'] ?? '') : ($_POST['descripcion'] ?? '')) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Premio *</label>
                        <input type="text" name="premio" required maxlength="200"
                               value="<?= htmlspecialchars($modo_edicion ? ($rifa_editar['premio'] ?? '') : ($_POST['premio'] ?? '')) ?>"
                               placeholder="Ej: iPhone 15 Pro 256GB Negro"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>

                    <?php if (!$modo_edicion): ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Precio por Boleta * (COP)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold">$</span>
                                <input type="number" name="precio_boleta" required min="500" max="1000000" step="500"
                                       value="<?= htmlspecialchars($_POST['precio_boleta'] ?? '') ?>"
                                       placeholder="Ej: 5000"
                                       class="w-full pl-7 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Mínimo: $500 COP</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Cantidad de Boletas *</label>
                            <input type="number" name="cantidad_boletas" required min="5" max="1000"
                                   value="<?= htmlspecialchars($_POST['cantidad_boletas'] ?? '') ?>"
                                   placeholder="Ej: 100"
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <p class="text-xs text-gray-400 mt-1">Entre 5 y 1000 boletas</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-yellow-700">
                        ⚠️ El precio y la cantidad de boletas no se pueden cambiar una vez creada la rifa. &nbsp;
                        Precio: <strong>$<?= number_format((float)($rifa_editar['precio_boleta'] ?? 0), 0, ',', '.') ?></strong> ·
                        Boletas: <strong><?= (int)($rifa_editar['cantidad_boletas'] ?? 0) ?></strong>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha de Cierre *</label>
                        <input type="datetime-local" name="fecha_fin" required
                               value="<?= htmlspecialchars($modo_edicion ? date('Y-m-d\TH:i', strtotime($rifa_editar['fecha_fin'] ?? 'now')) : ($_POST['fecha_fin'] ?? '')) ?>"
                               min="<?= date('Y-m-d\TH:i') ?>"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">URL de Imagen (opcional)</label>
                        <input type="url" name="imagen_url"
                               value="<?= htmlspecialchars($modo_edicion ? ($rifa_editar['imagen_url'] ?? '') : ($_POST['imagen_url'] ?? '')) ?>"
                               placeholder="https://ejemplo.com/imagen.jpg"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="flex-1 bg-gradient-to-r from-purple-600 to-blue-600 text-white py-4 rounded-xl font-bold text-lg hover:from-purple-700 hover:to-blue-700 transition-all shadow-lg">
                            <?= $modo_edicion ? "💾 Guardar cambios" : "🎰 Publicar mi Rifa" ?>
                        </button>
                        <?php if ($modo_edicion): ?>
                        <a href="crear_rifa.php"
                           class="px-6 py-4 border-2 border-gray-300 text-gray-600 rounded-xl font-bold hover:bg-gray-50 transition-colors text-center">
                            Cancelar
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div class="space-y-6">
            <?php if (!$modo_edicion): ?>
            <div class="bg-gradient-to-br from-purple-600 to-blue-600 text-white rounded-2xl p-6">
                <h3 class="font-bold text-lg mb-4">💡 ¿Cómo funciona?</h3>
                <div class="space-y-3 text-sm text-purple-100">
                    <div class="flex items-start gap-2"><span class="font-bold text-white">1.</span><span>Completa el formulario</span></div>
                    <div class="flex items-start gap-2"><span class="font-bold text-white">2.</span><span>Tu rifa se publica inmediatamente</span></div>
                    <div class="flex items-start gap-2"><span class="font-bold text-white">3.</span><span>Recibes el <strong class="text-white">80%</strong> de cada venta en tu saldo</span></div>
                    <div class="flex items-start gap-2"><span class="font-bold text-white">4.</span><span>Realiza el sorteo en la fecha indicada</span></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Mis Rifas con Editar / Eliminar -->
            <?php
            $mis_rifas_arr = [];
            while ($r = mysqli_fetch_assoc($mis_rifas)) $mis_rifas_arr[] = $r;
            mysqli_stmt_close($stmt_mis);
            if (!empty($mis_rifas_arr)):
            ?>
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="font-bold text-lg mb-4">🎰 Mis Rifas</h3>
                <div class="space-y-3">
                <?php foreach ($mis_rifas_arr as $r):
                    $pct = $r["cantidad_boletas"] > 0 ? round(($r["vendidas"]/$r["cantidad_boletas"])*100) : 0;
                    $color_estado = ["activa"=>"bg-green-100 text-green-700","finalizada"=>"bg-gray-100 text-gray-600"][$r["estado"]] ?? "bg-yellow-100 text-yellow-700";
                    $puede_eliminar = ((int)$r["vendidas"] === 0);
                    $es_editando = ($modo_edicion && (int)$r['id'] === (int)($rifa_editar['id'] ?? 0));
                ?>
                <div class="border rounded-xl p-4 <?= $es_editando ? 'border-purple-400 bg-purple-50' : '' ?>">
                    <div class="flex justify-between items-start mb-1">
                        <h4 class="font-semibold text-sm leading-tight pr-1"><?= htmlspecialchars($r["titulo"]) ?></h4>
                        <span class="text-xs px-2 py-0.5 rounded-full <?= $color_estado ?> shrink-0"><?= ucfirst($r["estado"]) ?></span>
                    </div>
                    <div class="text-xs text-gray-500 mb-2"><?= $r["vendidas"] ?>/<?= $r["cantidad_boletas"] ?> vendidas</div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mb-2">
                        <div class="bg-purple-500 h-1.5 rounded-full" style="width:<?= $pct ?>%"></div>
                    </div>
                    <?php if ($r["estado"] === "activa"): ?>
                    <div class="flex gap-2 mt-2">
                        <a href="crear_rifa.php?editar=<?= $r['id'] ?>"
                           class="flex-1 text-center text-xs bg-blue-50 text-blue-700 font-semibold py-1.5 rounded-lg hover:bg-blue-100 transition-colors">
                            ✏️ Editar
                        </a>
                        <?php if ($puede_eliminar): ?>
                        <a href="crear_rifa.php?eliminar=<?= $r['id'] ?>"
                           onclick="return confirm('¿Eliminar «<?= htmlspecialchars(addslashes($r['titulo'])) ?>»? No se puede deshacer.')"
                           class="flex-1 text-center text-xs bg-red-50 text-red-700 font-semibold py-1.5 rounded-lg hover:bg-red-100 transition-colors">
                            🗑️ Eliminar
                        </a>
                        <?php else: ?>
                        <span title="Ya tiene boletas vendidas"
                              class="flex-1 text-center text-xs bg-gray-100 text-gray-400 font-semibold py-1.5 rounded-lg cursor-not-allowed">
                            🔒 Con ventas
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
