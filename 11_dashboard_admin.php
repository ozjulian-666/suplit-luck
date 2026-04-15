<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_rol"]) || $_SESSION["usuario_rol"] !== "admin") {
    header("Location: 10_login_admin.php");
    exit();
}

// ── Acción: eliminar rifa + notificar al organizador ─────────────────────────
$msg_accion = "";
if (isset($_GET["accion"]) && $_GET["accion"] === "eliminar" && isset($_GET["rid"])) {
    $rid = (int)$_GET["rid"];
    if ($rid > 0) {
        // Obtener datos de la rifa antes de eliminar
        $stmt_info = mysqli_prepare($conexion, "SELECT id_organizador, titulo FROM rifas WHERE id = ? AND estado = 'activa'");
        mysqli_stmt_bind_param($stmt_info, "i", $rid);
        mysqli_stmt_execute($stmt_info);
        $rifa_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info));
        mysqli_stmt_close($stmt_info);

        if ($rifa_info) {
            // Marcar rifa como eliminada
            $stmt_fin = mysqli_prepare($conexion, "UPDATE rifas SET estado = 'eliminada' WHERE id = ?");
            mysqli_stmt_bind_param($stmt_fin, "i", $rid);
            mysqli_stmt_execute($stmt_fin);
            mysqli_stmt_close($stmt_fin);

            // Crear tabla notificaciones si aún no existe
            mysqli_query($conexion,
                "CREATE TABLE IF NOT EXISTS notificaciones (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_usuario INT NOT NULL,
                    mensaje TEXT NOT NULL,
                    leida TINYINT(1) DEFAULT 0,
                    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            // Insertar notificación para el organizador
            $id_org  = (int)$rifa_info["id_organizador"];
            $titulo  = $rifa_info["titulo"];
            $mensaje = "El administrador de Soplit.Luck ha eliminado tu rifa \"" . $titulo . "\". Si tienes dudas, comunícate con el equipo de soporte.";
            $stmt_noti = mysqli_prepare($conexion,
                "INSERT INTO notificaciones (id_usuario, mensaje, leida, fecha) VALUES (?, ?, 0, NOW())"
            );
            if ($stmt_noti) {
                mysqli_stmt_bind_param($stmt_noti, "is", $id_org, $mensaje);
                mysqli_stmt_execute($stmt_noti);
                mysqli_stmt_close($stmt_noti);
            }

            $msg_accion = "Rifa eliminada y organizador notificado.";
        } else {
            $msg_accion = "No se encontró la rifa o ya no está activa.";
        }
    }
}

// ── Acción: acreditar comisión 20% al admin por boletas vendidas ─────────────
// (se acredita automáticamente en 6_compra.php — aquí solo mostramos resumen)

// ── Estadísticas generales ───────────────────────────────────────────────────
$total_usuarios   = (int)mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as t FROM usuarios"))["t"];
$usuarios_activos = (int)mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as t FROM usuarios WHERE estado='activo'"))["t"];
$total_rifas_act  = (int)mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as t FROM rifas WHERE estado='activa'"))["t"];
$total_rifas_fin  = (int)mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as t FROM rifas WHERE estado='finalizada'"))["t"];
$ingresos_total   = (float)(mysqli_fetch_assoc(mysqli_query($conexion, "SELECT SUM(valor) as t FROM pagos WHERE estado='aprobado'"))["t"] ?? 0);
$comision_admin   = round($ingresos_total * 0.20, 2);
$boletas_vendidas = (int)mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as t FROM boletas WHERE estado='vendida'"))["t"];
$boletas_disp     = (int)mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as t FROM boletas WHERE estado='disponible'"))["t"];

// ── Resumen de ingresos por mes (últimos 6 meses) ───────────────────────────
$ingresos_mes = mysqli_query($conexion,
    "SELECT DATE_FORMAT(fecha_pago,'%Y-%m') as mes,
            SUM(valor) as total,
            COUNT(*) as ventas
     FROM pagos WHERE estado='aprobado'
     GROUP BY DATE_FORMAT(fecha_pago,'%Y-%m')
     ORDER BY mes DESC LIMIT 6"
);

// ── Top organizadores (más boletas vendidas) ─────────────────────────────────
$top_org = mysqli_query($conexion,
    "SELECT u.nombre, u.apellido, u.email, u.saldo,
            COUNT(b.id) as boletas_generadas,
            SUM(p.valor) as ingresos_brutos
     FROM usuarios u
     JOIN rifas r ON r.id_organizador = u.id
     JOIN boletas b ON b.id_rifa = r.id AND b.estado = 'vendida'
     JOIN pagos p ON p.id_boleta = b.id AND p.estado = 'aprobado'
     GROUP BY u.id
     ORDER BY ingresos_brutos DESC
     LIMIT 5"
);

// ── Rifas activas con progreso (todas) ──────────────────────────────────────
$rifas_activas = mysqli_query($conexion,
    "SELECT r.*,
        u.nombre as org_nombre, u.apellido as org_apellido,
        (SELECT COUNT(*) FROM boletas b WHERE b.id_rifa = r.id AND b.estado='vendida') as vendidas,
        (SELECT COUNT(*) FROM boletas b WHERE b.id_rifa = r.id) as total_b
     FROM rifas r
     JOIN usuarios u ON u.id = r.id_organizador
     WHERE r.estado = 'activa'
     ORDER BY r.fecha_fin ASC"
);

// ── Últimas transacciones ────────────────────────────────────────────────────
$ultimas_trans = mysqli_query($conexion,
    "SELECT p.*, u.nombre, u.apellido, r.titulo as rifa
     FROM pagos p
     JOIN boletas b ON p.id_boleta = b.id
     JOIN usuarios u ON b.id_usuario = u.id
     JOIN rifas r ON b.id_rifa = r.id
     WHERE p.estado = 'aprobado'
     ORDER BY p.fecha_pago DESC LIMIT 8"
);

// ── Usuarios recientes ───────────────────────────────────────────────────────
$ultimos_usuarios = mysqli_query($conexion,
    "SELECT * FROM usuarios ORDER BY fecha_registro DESC LIMIT 6"
);

// ── Últimas recargas de saldo ────────────────────────────────────────────────
$ultimas_recargas = mysqli_query($conexion,
    "SELECT ms.monto, ms.descripcion, ms.fecha,
            u.nombre, u.apellido, u.email
     FROM movimientos_saldo ms
     JOIN usuarios u ON u.id = ms.id_usuario
     WHERE ms.tipo = 'recarga'
     ORDER BY ms.fecha DESC LIMIT 8"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin | Soplit Luck</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link { transition: all 0.2s; }
        .sidebar-link:hover { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-gray-900 text-white flex flex-col fixed h-full z-40">
        <div class="p-6 border-b border-gray-700">
            <h1 class="text-2xl font-bold text-blue-400">🍀 Soplit.Luck</h1>
            <p class="text-sm text-gray-400 mt-1">Panel de Administración</p>
        </div>

        <div class="p-4 border-b border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center font-bold text-sm">
                    <?= strtoupper(substr($_SESSION["usuario_nombre"], 0, 2)) ?>
                </div>
                <div>
                    <div class="text-sm font-semibold"><?= htmlspecialchars($_SESSION["usuario_nombre"]) ?></div>
                    <div class="text-xs text-green-400">● Administrador</div>
                </div>
            </div>
        </div>

        <nav class="flex-1 p-4 space-y-1">
            <a href="11_dashboard_admin.php" class="sidebar-link flex items-center gap-3 px-4 py-3 bg-blue-600 rounded-xl text-white font-semibold text-sm">
                📊 Dashboard
            </a>
            <a href="8_info_usuarios.php" class="sidebar-link flex items-center gap-3 px-4 py-3 text-gray-400 rounded-xl text-sm">
                👥 Usuarios
            </a>
            <div class="pt-4 pb-2 px-4 text-xs text-gray-600 uppercase tracking-wider font-semibold">
                Acciones rápidas
            </div>
            <a href="crear_rifa.php" class="sidebar-link flex items-center gap-3 px-4 py-3 text-gray-400 rounded-xl text-sm">
                🎰 Crear Rifa
            </a>
            <a href="4_inicio.php" target="_blank" class="sidebar-link flex items-center gap-3 px-4 py-3 text-gray-400 rounded-xl text-sm">
                🌐 Ver sitio
            </a>
        </nav>

        <div class="p-4 border-t border-gray-700">
            <div class="bg-gray-800 rounded-xl p-3 mb-3 text-center">
                <p class="text-xs text-gray-400">Comisión plataforma (20%)</p>
                <p class="text-lg font-bold text-green-400">$<?= number_format($comision_admin, 0, ',', '.') ?></p>
            </div>
            <a href="logout.php" class="block text-center text-red-400 font-semibold hover:underline text-sm">
                🚪 Cerrar sesión
            </a>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="flex-1 ml-64 p-8 overflow-y-auto">

        <?php if ($msg_accion): ?>
        <div class="bg-green-100 border border-green-300 text-green-800 p-4 rounded-xl mb-6 text-sm">
            ✅ <?= htmlspecialchars($msg_accion) ?>
        </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800">Dashboard</h2>
            <span class="text-sm text-gray-500">📅 <?= date("d/m/Y H:i") ?></span>
        </div>

        <!-- ═══ TARJETAS ESTADÍSTICAS ═══════════════════════════════════════ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-5 rounded-2xl shadow">
                <p class="text-blue-100 text-xs font-medium uppercase tracking-wide">Ingresos Brutos</p>
                <p class="text-3xl font-bold mt-1">$<?= number_format($ingresos_total, 0, ',', '.') ?></p>
                <p class="text-blue-200 text-xs mt-2">Comisión admin: $<?= number_format($comision_admin, 0, ',', '.') ?></p>
            </div>
            <div class="bg-gradient-to-br from-green-500 to-green-600 text-white p-5 rounded-2xl shadow">
                <p class="text-green-100 text-xs font-medium uppercase tracking-wide">Usuarios</p>
                <p class="text-3xl font-bold mt-1"><?= $total_usuarios ?></p>
                <p class="text-green-200 text-xs mt-2"><?= $usuarios_activos ?> activos</p>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white p-5 rounded-2xl shadow">
                <p class="text-purple-100 text-xs font-medium uppercase tracking-wide">Rifas Activas</p>
                <p class="text-3xl font-bold mt-1"><?= $total_rifas_act ?></p>
                <p class="text-purple-200 text-xs mt-2"><?= $total_rifas_fin ?> finalizadas</p>
            </div>
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 text-white p-5 rounded-2xl shadow">
                <p class="text-orange-100 text-xs font-medium uppercase tracking-wide">Boletas Vendidas</p>
                <p class="text-3xl font-bold mt-1"><?= $boletas_vendidas ?></p>
                <p class="text-orange-200 text-xs mt-2"><?= $boletas_disp ?> disponibles</p>
            </div>
        </div>

        <!-- ═══ DISTRIBUCIÓN DE DINERO ════════════════════════════════════ -->
        <div class="bg-white rounded-2xl shadow p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-4">💰 Distribución de Ingresos</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 rounded-xl p-4 text-center border border-blue-100">
                    <div class="text-3xl mb-1">💳</div>
                    <p class="text-sm text-gray-500">Total recaudado</p>
                    <p class="text-xl font-bold text-blue-700">$<?= number_format($ingresos_total, 0, ',', '.') ?></p>
                    <p class="text-xs text-gray-400 mt-1">100% de todas las ventas</p>
                </div>
                <div class="bg-green-50 rounded-xl p-4 text-center border border-green-100">
                    <div class="text-3xl mb-1">🏆</div>
                    <p class="text-sm text-gray-500">Para organizadores (80%)</p>
                    <p class="text-xl font-bold text-green-700">$<?= number_format($ingresos_total * 0.80, 0, ',', '.') ?></p>
                    <p class="text-xs text-gray-400 mt-1">Acreditado en sus saldos</p>
                </div>
                <div class="bg-purple-50 rounded-xl p-4 text-center border border-purple-100">
                    <div class="text-3xl mb-1">🍀</div>
                    <p class="text-sm text-gray-500">Comisión plataforma (20%)</p>
                    <p class="text-xl font-bold text-purple-700">$<?= number_format($comision_admin, 0, ',', '.') ?></p>
                    <p class="text-xs text-gray-400 mt-1">Ingresos de Soplit.Luck</p>
                </div>
            </div>
        </div>

        <!-- ═══ FILA: RIFAS ACTIVAS + MÉTODOS DE PAGO ═══════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- Rifas activas -->
            <div class="lg:col-span-2 bg-white rounded-2xl shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">🎰 Rifas Activas</h3>
                    <a href="crear_rifa.php" class="text-sm text-blue-600 font-medium hover:underline">+ Nueva rifa</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Rifa</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Organizador</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Progreso</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Cierra</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        <?php while ($r = mysqli_fetch_assoc($rifas_activas)):
                            $total_b = max(1, (int)$r["total_b"]);
                            $vendidas = (int)$r["vendidas"];
                            $pct = round(($vendidas / $total_b) * 100);
                            $recaudado = $vendidas * (float)$r["precio_boleta"];
                            $dias_rest = (int)((strtotime($r["fecha_fin"]) - time()) / 86400);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-3">
                                <div class="font-medium text-gray-900 text-xs"><?= htmlspecialchars($r["titulo"]) ?></div>
                                <div class="text-gray-400 text-xs">$<?= number_format($r["precio_boleta"],0,',','.') ?>/boleta</div>
                            </td>
                            <td class="px-3 py-3 text-xs text-gray-500"><?= htmlspecialchars($r["org_nombre"] . " " . $r["org_apellido"]) ?></td>
                            <td class="px-3 py-3">
                                <div class="text-xs text-gray-500 mb-1"><?= $vendidas ?>/<?= $total_b ?> (<?= $pct ?>%)</div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="<?= $pct > 75 ? 'bg-green-500' : ($pct > 40 ? 'bg-blue-500' : 'bg-gray-400') ?> h-1.5 rounded-full" style="width:<?= $pct ?>%"></div>
                                </div>
                                <div class="text-xs text-green-600 mt-0.5 font-medium">$<?= number_format($recaudado,0,',','.') ?></div>
                            </td>
                            <td class="px-3 py-3">
                                <span class="text-xs <?= $dias_rest < 7 ? 'text-red-600 font-semibold' : 'text-gray-500' ?>">
                                    <?= $dias_rest >= 0 ? $dias_rest . "d" : 'Vencida' ?>
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <a href="11_dashboard_admin.php?accion=eliminar&rid=<?= $r['id'] ?>"
                                   onclick="return confirm('¿Eliminar esta rifa? El organizador será notificado.')"
                                   class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200 transition-colors font-semibold">
                                    🗑 Eliminar
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Últimas recargas -->
            <div class="bg-white rounded-2xl shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">⬆️ Últimas Recargas</h3>
                <?php
                $metodos_iconos = ["tarjeta" => "💳", "nequi" => "🟣", "daviplata" => "🔵", "pse" => "🏦"];
                $has_recargas = false;
                while ($rec = mysqli_fetch_assoc($ultimas_recargas)):
                    $has_recargas = true;
                    // Extraer método de la descripción: "Recarga vía Nequi por $..."
                    $metodo_raw = "";
                    if (preg_match('/vía\s+(\w+)/i', $rec["descripcion"], $m_match)) {
                        $metodo_raw = strtolower($m_match[1]);
                    }
                    $icon = $metodos_iconos[$metodo_raw] ?? "💰";
                    $metodo_label = $metodo_raw ? ucfirst($metodo_raw) : "Plataforma";
                ?>
                <div class="flex items-center justify-between mb-3 p-3 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-2">
                        <span class="text-xl"><?= $icon ?></span>
                        <div>
                            <p class="text-sm font-semibold"><?= htmlspecialchars($rec["nombre"] . " " . $rec["apellido"]) ?></p>
                            <p class="text-xs text-gray-400"><?= $metodo_label ?> · <?= date("d/m H:i", strtotime($rec["fecha"])) ?></p>
                        </div>
                    </div>
                    <span class="text-sm font-bold text-green-600">+$<?= number_format($rec["monto"], 0, ',', '.') ?></span>
                </div>
                <?php endwhile; ?>
                <?php if (!$has_recargas): ?>
                <p class="text-gray-400 text-sm text-center py-4">Sin recargas aún</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ TOP ORGANIZADORES ════════════════════════════════════════ -->
        <?php
        $top_data = [];
        while ($t = mysqli_fetch_assoc($top_org)) $top_data[] = $t;
        if (!empty($top_data)):
        ?>
        <div class="bg-white rounded-2xl shadow p-6 mb-8">
            <h3 class="text-lg font-bold text-gray-800 mb-4">🏅 Top Organizadores</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">#</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Organizador</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Boletas vendidas</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Ingresos brutos</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Ganancias (80%)</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Saldo actual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php foreach ($top_data as $i => $t): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400 font-bold"><?= $i+1 ?></td>
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= htmlspecialchars($t["nombre"] . " " . $t["apellido"]) ?></div>
                            <div class="text-xs text-gray-400"><?= htmlspecialchars($t["email"]) ?></div>
                        </td>
                        <td class="px-4 py-3 font-semibold"><?= $t["boletas_generadas"] ?></td>
                        <td class="px-4 py-3 text-blue-600 font-semibold">$<?= number_format($t["ingresos_brutos"],0,',','.') ?></td>
                        <td class="px-4 py-3 text-green-600 font-semibold">$<?= number_format($t["ingresos_brutos"]*0.80,0,',','.') ?></td>
                        <td class="px-4 py-3">
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">
                                $<?= number_format($t["saldo"],0,',','.') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ FILA: TRANSACCIONES + USUARIOS RECIENTES ════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Últimas transacciones -->
            <div class="bg-white rounded-2xl shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">🧾 Últimas Transacciones</h3>
                <div class="space-y-2">
                <?php
                $has_trans = false;
                while ($t = mysqli_fetch_assoc($ultimas_trans)):
                    $has_trans = true;
                    $iconos_metodo = ["tarjeta"=>"💳","nequi"=>"🟣","daviplata"=>"🔵","pse"=>"🏦"];
                    $icon = $iconos_metodo[$t["metodo_pago"]] ?? "💰";
                ?>
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <span class="text-xl"><?= $icon ?></span>
                        <div>
                            <div class="text-sm font-medium"><?= htmlspecialchars($t["nombre"] . " " . $t["apellido"]) ?></div>
                            <div class="text-xs text-gray-400"><?= htmlspecialchars(mb_strimwidth($t["rifa"], 0, 28, "...")) ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-green-600">+$<?= number_format($t["valor"],0,',','.') ?></div>
                        <div class="text-xs text-gray-400"><?= date("d/m H:i", strtotime($t["fecha_pago"])) ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php if (!$has_trans): ?>
                <p class="text-gray-400 text-sm text-center py-6">Sin transacciones aún.</p>
                <?php endif; ?>
                </div>
            </div>

            <!-- Usuarios recientes -->
            <div class="bg-white rounded-2xl shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">👥 Usuarios Recientes</h3>
                    <a href="8_info_usuarios.php" class="text-sm text-blue-600 hover:underline font-medium">Ver todos →</a>
                </div>
                <div class="space-y-2">
                <?php while ($u = mysqli_fetch_assoc($ultimos_usuarios)):
                    $color_estado = ["activo"=>"bg-green-100 text-green-700","suspendido"=>"bg-red-100 text-red-700","inactivo"=>"bg-gray-100 text-gray-600"][$u["estado"]] ?? "bg-gray-100 text-gray-600";
                    $color_rol = ["admin"=>"bg-purple-100 text-purple-700","organizador"=>"bg-blue-100 text-blue-700","participante"=>"bg-gray-100 text-gray-600"][$u["rol"]] ?? "";
                ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white font-bold text-xs">
                            <?= strtoupper(substr($u["nombre"],0,1) . substr($u["apellido"]??"",0,1)) ?>
                        </div>
                        <div>
                            <div class="text-sm font-medium"><?= htmlspecialchars($u["nombre"] . " " . $u["apellido"]) ?></div>
                            <div class="flex gap-1 mt-0.5">
                                <span class="text-xs px-1.5 py-0.5 rounded-full <?= $color_rol ?>"><?= ucfirst($u["rol"]) ?></span>
                                <span class="text-xs px-1.5 py-0.5 rounded-full <?= $color_estado ?>"><?= ucfirst($u["estado"]) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-400"><?= date("d/m/Y", strtotime($u["fecha_registro"])) ?></div>
                        <div class="text-xs font-semibold text-green-600">$<?= number_format($u["saldo"],0,',','.') ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
                </div>
            </div>
        </div>

    </main>
</body>
</html>
