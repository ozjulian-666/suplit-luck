<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: 3_login.php");
    exit();
}

// Verificar usuario activo y obtener saldo
$stmt_check = mysqli_prepare($conexion, "SELECT estado, saldo FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($stmt_check, "i", $_SESSION["usuario_id"]);
mysqli_stmt_execute($stmt_check);
$check_usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
mysqli_stmt_close($stmt_check);

if (!$check_usuario || $check_usuario["estado"] !== "activo") {
    session_destroy();
    header("Location: 3_login.php?bloqueado=1");
    exit();
}

$saldo_usuario = (float)($check_usuario["saldo"] ?? 0);

$id_rifa = isset($_GET["id_rifa"]) ? (int)$_GET["id_rifa"] : 0;
$qty     = isset($_GET["qty"])     ? max(1, min(15, (int)$_GET["qty"])) : 1;

$numeros_elegidos = [];
if (!empty($_GET["numeros"])) {
    foreach (explode(',', $_GET["numeros"]) as $n) {
        $n = (int)trim($n);
        if ($n > 0) $numeros_elegidos[] = $n;
    }
    $numeros_elegidos = array_unique($numeros_elegidos);
}

// Cargar rifa
$stmt_rifa = mysqli_prepare($conexion, "SELECT * FROM rifas WHERE id = ? AND estado = 'activa'");
mysqli_stmt_bind_param($stmt_rifa, "i", $id_rifa);
mysqli_stmt_execute($stmt_rifa);
$rifa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rifa));
mysqli_stmt_close($stmt_rifa);

if (!$rifa) {
    header("Location: 4_inicio.php");
    exit();
}

$error = "";
$saldo_insuficiente = ($saldo_usuario < (float)$rifa["precio_boleta"]);

// ── PROCESAR COMPRA ──────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_usuario = (int)$_SESSION["usuario_id"];

    $nums_post = [];
    if (!empty($_POST["numeros_elegidos"])) {
        foreach (explode(',', $_POST["numeros_elegidos"]) as $n) {
            $n = (int)trim($n);
            if ($n > 0) $nums_post[] = $n;
        }
        $nums_post = array_unique($nums_post);
    }

    $qty_real          = count($nums_post);
    $total_compra_real = (float)$rifa["precio_boleta"] * $qty_real;

    if ($qty_real < 1 || $qty_real > 15) {
        $error = "Selecciona entre 1 y 15 boletas válidas.";
    } elseif ($saldo_usuario < $total_compra_real) {
        $falta = $total_compra_real - $saldo_usuario;
        $error = "Saldo insuficiente. Tu saldo es $" . number_format($saldo_usuario, 0, ',', '.') .
                 " COP y necesitas $" . number_format($total_compra_real, 0, ',', '.') .
                 " COP. Te faltan $" . number_format($falta, 0, ',', '.') . " COP.";
    } else {
        mysqli_begin_transaction($conexion);

        try {
            // Bloquear boletas disponibles
            $placeholders = implode(',', array_fill(0, $qty_real, '?'));
            $stmt_lock = mysqli_prepare($conexion,
                "SELECT id, numero FROM boletas
                 WHERE id_rifa = ? AND numero IN ($placeholders) AND estado = 'disponible'
                 FOR UPDATE"
            );
            $types  = "i" . str_repeat("i", $qty_real);
            $params = array_merge([$id_rifa], $nums_post);
            mysqli_stmt_bind_param($stmt_lock, $types, ...$params);
            mysqli_stmt_execute($stmt_lock);
            $res_lock = mysqli_stmt_get_result($stmt_lock);

            $boletas_ok = [];
            while ($b = mysqli_fetch_assoc($res_lock)) {
                $boletas_ok[$b["numero"]] = $b["id"];
            }
            mysqli_stmt_close($stmt_lock);

            if (count($boletas_ok) < $qty_real) {
                mysqli_rollback($conexion);
                $error = "Algunos números ya fueron vendidos. Por favor elige otros.";
            } else {
                $total_compra = (float)$rifa["precio_boleta"] * count($boletas_ok);

                // 1. Descontar saldo del comprador (verificación atómica)
                $stmt_desc = mysqli_prepare($conexion,
                    "UPDATE usuarios SET saldo = saldo - ? WHERE id = ? AND saldo >= ?"
                );
                mysqli_stmt_bind_param($stmt_desc, "did", $total_compra, $id_usuario, $total_compra);
                mysqli_stmt_execute($stmt_desc);
                $filas = mysqli_stmt_affected_rows($stmt_desc);
                mysqli_stmt_close($stmt_desc);

                if ($filas === 0) {
                    mysqli_rollback($conexion);
                    $error = "Saldo insuficiente al procesar el pago. Recarga e intenta de nuevo.";
                } else {
                    // 2. Registrar movimiento de compra del usuario
                    $desc_compra = "Compra de " . count($boletas_ok) . " boleta(s) - " . $rifa["titulo"];
                    $tipo_compra = "compra";
                    $stmt_mov_c  = mysqli_prepare($conexion,
                        "INSERT INTO movimientos_saldo (id_usuario, tipo, monto, descripcion) VALUES (?, ?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($stmt_mov_c, "isds", $id_usuario, $tipo_compra, $total_compra, $desc_compra);
                    mysqli_stmt_execute($stmt_mov_c);
                    mysqli_stmt_close($stmt_mov_c);

                    // 3. Marcar boletas como vendidas y registrar pagos
                    $numeros_comprados = [];
                    $stmt_upd  = mysqli_prepare($conexion,
                        "UPDATE boletas SET id_usuario = ?, estado = 'vendida', fecha_compra = NOW() WHERE id = ?"
                    );
                    $stmt_pago = mysqli_prepare($conexion,
                        "INSERT INTO pagos (id_boleta, metodo_pago, valor, estado) VALUES (?, ?, ?, 'aprobado')"
                    );
                    $metodo_pago = "saldo";

                    foreach ($boletas_ok as $numero => $id_boleta) {
                        mysqli_stmt_bind_param($stmt_upd, "ii", $id_usuario, $id_boleta);
                        mysqli_stmt_execute($stmt_upd);

                        mysqli_stmt_bind_param($stmt_pago, "isd", $id_boleta, $metodo_pago, $rifa["precio_boleta"]);
                        mysqli_stmt_execute($stmt_pago);

                        $numeros_comprados[] = $numero;
                    }
                    mysqli_stmt_close($stmt_upd);
                    mysqli_stmt_close($stmt_pago);

                    // 4. Acreditar 80% al organizador
                    $id_organizador = (int)$rifa["id_organizador"];
                    $ganancia_org   = round($total_compra * 0.80, 2);

                    if ($id_organizador > 0 && $ganancia_org > 0) {
                        $stmt_org = mysqli_prepare($conexion,
                            "UPDATE usuarios SET saldo = saldo + ? WHERE id = ?"
                        );
                        mysqli_stmt_bind_param($stmt_org, "di", $ganancia_org, $id_organizador);
                        mysqli_stmt_execute($stmt_org);
                        mysqli_stmt_close($stmt_org);

                        $desc_org = "Ganancia por venta de " . count($numeros_comprados) . " boleta(s) - " . $rifa["titulo"];
                        $tipo_org = "ganancia";
                        $stmt_mov_org = mysqli_prepare($conexion,
                            "INSERT INTO movimientos_saldo (id_usuario, tipo, monto, descripcion) VALUES (?, ?, ?, ?)"
                        );
                        mysqli_stmt_bind_param($stmt_mov_org, "isds", $id_organizador, $tipo_org, $ganancia_org, $desc_org);
                        mysqli_stmt_execute($stmt_mov_org);
                        mysqli_stmt_close($stmt_mov_org);
                    }

                    // 5. Acreditar 20% al admin principal
                    $comision_admin = round($total_compra * 0.20, 2);

                    if ($comision_admin > 0) {
                        $stmt_adm_id = mysqli_prepare($conexion,
                            "SELECT id FROM usuarios WHERE rol = 'admin' AND estado = 'activo' ORDER BY id ASC LIMIT 1"
                        );
                        mysqli_stmt_execute($stmt_adm_id);
                        $row_admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_adm_id));
                        mysqli_stmt_close($stmt_adm_id);

                        if ($row_admin) {
                            $id_admin = (int)$row_admin["id"];

                            $stmt_adm = mysqli_prepare($conexion,
                                "UPDATE usuarios SET saldo = saldo + ? WHERE id = ?"
                            );
                            mysqli_stmt_bind_param($stmt_adm, "di", $comision_admin, $id_admin);
                            mysqli_stmt_execute($stmt_adm);
                            mysqli_stmt_close($stmt_adm);

                            $desc_adm = "Comisión plataforma (20%) - " . count($numeros_comprados) . " boleta(s) - " . $rifa["titulo"];
                            $tipo_adm = "comision";
                            $stmt_mov_adm = mysqli_prepare($conexion,
                                "INSERT INTO movimientos_saldo (id_usuario, tipo, monto, descripcion) VALUES (?, ?, ?, ?)"
                            );
                            mysqli_stmt_bind_param($stmt_mov_adm, "isds", $id_admin, $tipo_adm, $comision_admin, $desc_adm);
                            mysqli_stmt_execute($stmt_mov_adm);
                            mysqli_stmt_close($stmt_mov_adm);
                        }
                    }

                    // 6. Notificación al comprador
                    $msg_notif  = "Tu compra de boletas fue registrada exitosamente";
                    $tipo_notif = "confirmacion";
                    $stmt_notif = mysqli_prepare($conexion,
                        "INSERT INTO notificaciones (id_usuario, id_rifa, mensaje, tipo) VALUES (?, ?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($stmt_notif, "iiss", $id_usuario, $id_rifa, $msg_notif, $tipo_notif);
                    mysqli_stmt_execute($stmt_notif);
                    mysqli_stmt_close($stmt_notif);

                    mysqli_commit($conexion);

                    // ══════════════════════════════════════════════════════════════
                    //  SORTEO AUTOMÁTICO
                    //  Se activa cuando no queda ninguna boleta disponible en la rifa
                    // ══════════════════════════════════════════════════════════════
                    $stmt_disp = mysqli_prepare($conexion,
                        "SELECT COUNT(*) as total FROM boletas WHERE id_rifa = ? AND estado = 'disponible'"
                    );
                    mysqli_stmt_bind_param($stmt_disp, "i", $id_rifa);
                    mysqli_stmt_execute($stmt_disp);
                    $boletas_disponibles = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_disp))["total"];
                    mysqli_stmt_close($stmt_disp);

                    if ($boletas_disponibles === 0) {
                        // Traer todas las boletas vendidas con su dueño
                        $stmt_pool = mysqli_prepare($conexion,
                            "SELECT b.id AS id_boleta, b.numero, b.id_usuario
                             FROM boletas b
                             WHERE b.id_rifa = ? AND b.estado = 'vendida' AND b.id_usuario IS NOT NULL"
                        );
                        mysqli_stmt_bind_param($stmt_pool, "i", $id_rifa);
                        mysqli_stmt_execute($stmt_pool);
                        $pool = mysqli_fetch_all(mysqli_stmt_get_result($stmt_pool), MYSQLI_ASSOC);
                        mysqli_stmt_close($stmt_pool);

                        if (!empty($pool)) {
                            // Elegir ganador aleatoriamente
                            $ganador = $pool[array_rand($pool)];

                            mysqli_begin_transaction($conexion);
                            try {
                                // 1. Actualizar o insertar en tabla sorteos
                                $stmt_s_exist = mysqli_prepare($conexion,
                                    "SELECT id FROM sorteos WHERE id_rifa = ? LIMIT 1"
                                );
                                mysqli_stmt_bind_param($stmt_s_exist, "i", $id_rifa);
                                mysqli_stmt_execute($stmt_s_exist);
                                $fila_sorteo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_s_exist));
                                mysqli_stmt_close($stmt_s_exist);

                                if ($fila_sorteo) {
                                    $id_sorteo = (int)$fila_sorteo["id"];
                                    $stmt_upd_s = mysqli_prepare($conexion,
                                        "UPDATE sorteos
                                         SET numero_ganador = ?, estado = 'ejecutado', fecha_sorteo = NOW()
                                         WHERE id = ?"
                                    );
                                    mysqli_stmt_bind_param($stmt_upd_s, "ii", $ganador["numero"], $id_sorteo);
                                    mysqli_stmt_execute($stmt_upd_s);
                                    mysqli_stmt_close($stmt_upd_s);
                                } else {
                                    $stmt_ins_s = mysqli_prepare($conexion,
                                        "INSERT INTO sorteos (id_rifa, fecha_sorteo, numero_ganador, estado)
                                         VALUES (?, NOW(), ?, 'ejecutado')"
                                    );
                                    mysqli_stmt_bind_param($stmt_ins_s, "ii", $id_rifa, $ganador["numero"]);
                                    mysqli_stmt_execute($stmt_ins_s);
                                    $id_sorteo = (int)mysqli_stmt_insert_id($stmt_ins_s);
                                    mysqli_stmt_close($stmt_ins_s);
                                }

                                // 2. Registrar en tabla ganadores
                                $stmt_gan = mysqli_prepare($conexion,
                                    "INSERT INTO ganadores (id_sorteo, id_usuario, id_boleta) VALUES (?, ?, ?)"
                                );
                                mysqli_stmt_bind_param($stmt_gan, "iii",
                                    $id_sorteo,
                                    $ganador["id_usuario"],
                                    $ganador["id_boleta"]
                                );
                                mysqli_stmt_execute($stmt_gan);
                                mysqli_stmt_close($stmt_gan);

                                // 3. Marcar la rifa como finalizada
                                $stmt_fin = mysqli_prepare($conexion,
                                    "UPDATE rifas SET estado = 'finalizada' WHERE id = ?"
                                );
                                mysqli_stmt_bind_param($stmt_fin, "i", $id_rifa);
                                mysqli_stmt_execute($stmt_fin);
                                mysqli_stmt_close($stmt_fin);

                                // 4. Notificar al GANADOR
                                $num_fmt      = str_pad($ganador["numero"], 4, "0", STR_PAD_LEFT);
                                $msg_ganador  = "🏆 ¡GANASTE la rifa \"" . $rifa["titulo"] . "\"! "
                                              . "Tu boleta #" . $num_fmt . " fue la ganadora. "
                                              . "Premio: " . $rifa["premio"] . ". Contacta al organizador para reclamar tu premio.";
                                $tipo_resultado = "resultado";
                                $stmt_noti_g = mysqli_prepare($conexion,
                                    "INSERT INTO notificaciones (id_usuario, id_rifa, mensaje, tipo) VALUES (?, ?, ?, ?)"
                                );
                                mysqli_stmt_bind_param($stmt_noti_g, "iiss",
                                    $ganador["id_usuario"], $id_rifa, $msg_ganador, $tipo_resultado
                                );
                                mysqli_stmt_execute($stmt_noti_g);
                                mysqli_stmt_close($stmt_noti_g);

                                mysqli_commit($conexion);

                                // Guardar en sesión para mostrar resultado en confirmación
                                $_SESSION["sorteo_ejecutado"] = [
                                    "rifa_titulo"    => $rifa["titulo"],
                                    "numero_ganador" => $ganador["numero"],
                                    "yo_gane"        => ((int)$ganador["id_usuario"] === $id_usuario),
                                ];

                            } catch (Exception $e_sorteo) {
                                mysqli_rollback($conexion);
                                // La compra ya se confirmó; el sorteo se reintentará en la próxima compra o manualmente
                            }
                        }
                    }
                    // ══════════════════════════════════════════════════════════════

                    $_SESSION["compra"] = [
                        "rifa_titulo"  => $rifa["titulo"],
                        "premio"       => $rifa["premio"],
                        "qty"          => count($numeros_comprados),
                        "total"        => $total_compra,
                        "numeros"      => $numeros_comprados,
                        "fecha_sorteo" => $rifa["fecha_fin"],
                    ];

                    header("Location: 7_confirmacion.php");
                    exit();
                }
            }
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $error = "Error al procesar la compra. Intenta de nuevo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Compra - Soplit.Luck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50">

<nav class="bg-white shadow-sm border-b sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 flex justify-between items-center h-16">
        <a href="5_rifaiphone.php?id=<?= $id_rifa ?>" class="flex items-center gap-2 text-gray-600 hover:text-gray-900 font-medium">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver
        </a>
        <span class="text-xl font-bold">🍀 Soplit.Luck</span>
        <a href="logout.php" class="text-red-600 font-semibold text-sm">Salir</a>
    </div>
</nav>

<div class="max-w-5xl mx-auto px-6 py-8 grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Columna principal -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Banner saldo -->
        <div class="bg-blue-50 border border-blue-200 p-4 rounded-xl flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-2xl">💰</span>
                <div>
                    <div class="font-semibold text-blue-800">Tu saldo disponible</div>
                    <div class="text-sm text-blue-600">Se descontará automáticamente al confirmar</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-blue-700">$<?= number_format($saldo_usuario, 0, ',', '.') ?></div>
                <?php if ($saldo_insuficiente): ?>
                    <a href="mi_saldo.php" class="text-xs text-red-600 font-semibold hover:underline">⚠️ Recargar saldo</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($saldo_insuficiente): ?>
        <!-- Bloqueo: saldo insuficiente -->
        <div class="bg-red-50 border border-red-300 rounded-xl p-6 text-center">
            <div class="text-5xl mb-3">💸</div>
            <h3 class="text-lg font-bold text-red-700 mb-2">Saldo insuficiente</h3>
            <p class="text-red-600 text-sm mb-5">
                Necesitas al menos <strong>$<?= number_format((float)$rifa["precio_boleta"], 0, ',', '.') ?></strong> por boleta
                y tu saldo actual es <strong>$<?= number_format($saldo_usuario, 0, ',', '.') ?></strong>.
            </p>
            <a href="mi_saldo.php" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-blue-700 transition-colors">
                💳 Recargar Saldo
            </a>
            <div class="mt-4">
                <a href="5_rifaiphone.php?id=<?= $id_rifa ?>" class="text-sm text-gray-500 hover:underline">← Volver a la rifa</a>
            </div>
        </div>

        <?php else: ?>
        <!-- Formulario de confirmación -->
        <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded-xl border border-red-300 text-sm">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-2xl shadow-sm p-7 space-y-6">
            <input type="hidden" name="numeros_elegidos" value="<?= htmlspecialchars(implode(',', $numeros_elegidos)) ?>">

            <h2 class="text-xl font-bold text-gray-900">Confirmar compra con saldo</h2>

            <!-- Desglose -->
            <?php
            $total_a_pagar  = (float)$rifa["precio_boleta"] * max(1, count($numeros_elegidos));
            $saldo_restante = $saldo_usuario - $total_a_pagar;
            ?>
            <div class="bg-gray-50 rounded-xl p-5 space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Boletas seleccionadas</span>
                    <span class="font-semibold"><?= max(1, count($numeros_elegidos)) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Precio por boleta</span>
                    <span>$<?= number_format((float)$rifa["precio_boleta"], 0, ',', '.') ?></span>
                </div>
                <div class="border-t pt-3 flex justify-between font-bold text-base">
                    <span>Total a descontar</span>
                    <span class="text-red-600">-$<?= number_format($total_a_pagar, 0, ',', '.') ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Tu saldo actual</span>
                    <span class="font-semibold text-blue-600">$<?= number_format($saldo_usuario, 0, ',', '.') ?></span>
                </div>
                <div class="border-t pt-3 flex justify-between">
                    <span class="text-gray-500">Saldo después de la compra</span>
                    <span class="font-bold <?= $saldo_restante >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        $<?= number_format($saldo_restante, 0, ',', '.') ?>
                    </span>
                </div>
            </div>

            <!-- Números elegidos -->
            <?php if (!empty($numeros_elegidos)): ?>
            <div>
                <p class="text-xs font-semibold text-gray-500 mb-2">Números seleccionados:</p>
                <div class="flex flex-wrap gap-1">
                    <?php foreach ($numeros_elegidos as $n): ?>
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-lg text-xs font-bold">
                        #<?= str_pad((int)$n, 4, "0", STR_PAD_LEFT) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3 text-sm text-green-800">
                <span class="text-xl">🔒</span>
                <span>El pago se descuenta de tu saldo. No necesitas ingresar datos de tarjeta.</span>
            </div>

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" required class="mt-1 accent-blue-600">
                <span class="text-sm text-gray-600">
                    Acepto los <a href="#" class="text-blue-600 underline">términos y condiciones</a> de participación en sorteos
                </span>
            </label>

            <button type="submit"
                    class="w-full bg-blue-600 text-white py-4 rounded-xl font-bold text-lg hover:bg-blue-700 transition-colors shadow-lg">
                ✅ Confirmar Compra · $<?= number_format($total_a_pagar, 0, ',', '.') ?>
            </button>
        </form>
        <?php endif; ?>

    </div>

    <!-- Sidebar resumen -->
    <div>
        <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-24">
            <h3 class="font-bold text-lg mb-4 text-gray-900">Resumen</h3>
            <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl mb-4">
                <div class="text-4xl mb-2">🏆</div>
                <div class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($rifa["titulo"]) ?></div>
                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($rifa["premio"]) ?></div>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Boletas</span>
                    <span class="font-semibold"><?= max(1, count($numeros_elegidos)) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Precio unitario</span>
                    <span>$<?= number_format((float)$rifa["precio_boleta"], 0, ',', '.') ?></span>
                </div>
                <div class="border-t pt-2 flex justify-between font-bold">
                    <span>Total</span>
                    <span class="text-green-600">$<?= number_format((float)$rifa["precio_boleta"] * max(1, count($numeros_elegidos)), 0, ',', '.') ?></span>
                </div>
            </div>
            <p class="text-xs text-gray-400 text-center mt-4">
                📅 Sorteo: <?= date("d/m/Y", strtotime($rifa["fecha_fin"])) ?>
            </p>
            <div class="mt-4 pt-4 border-t text-center">
                <p class="text-xs text-gray-400 mb-1">Tu saldo disponible</p>
                <p class="font-bold text-blue-600">$<?= number_format($saldo_usuario, 0, ',', '.') ?></p>
                <?php if ($saldo_insuficiente): ?>
                <a href="mi_saldo.php" class="mt-2 block text-xs bg-blue-600 text-white py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    ⬆️ Recargar saldo
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</body>
</html>
