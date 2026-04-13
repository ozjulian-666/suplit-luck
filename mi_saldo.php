<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: 3_login.php");
    exit();
}

$id_usuario = (int)$_SESSION["usuario_id"];
$msg      = "";
$msg_tipo = "";

// Obtener datos del usuario
$stmt_u = mysqli_prepare($conexion, "SELECT * FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($stmt_u, "i", $id_usuario);
mysqli_stmt_execute($stmt_u);
$usuario = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u));
mysqli_stmt_close($stmt_u);

if (!$usuario) {
    session_destroy();
    header("Location: 3_login.php");
    exit();
}

$saldo = (float)($usuario["saldo"] ?? 0);

// ── Procesar recarga o retiro ────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";
    $monto  = (float)($_POST["monto"] ?? 0);

    if ($monto <= 0 || !is_numeric($_POST["monto"] ?? "")) {
        $msg      = "Ingresa un monto válido mayor a $0.";
        $msg_tipo = "error";
    } elseif ($monto > 10000000) {
        $msg      = "El monto máximo por operación es $10.000.000 COP.";
        $msg_tipo = "error";
    } elseif ($accion === "recargar") {

        $metodo_recarga = $_POST["metodo_recarga"] ?? "tarjeta";
        $metodos_validos = ["tarjeta", "nequi", "daviplata", "pse"];
        if (!in_array($metodo_recarga, $metodos_validos)) $metodo_recarga = "tarjeta";

        mysqli_begin_transaction($conexion);
        try {
            $stmt_r = mysqli_prepare($conexion, "UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_r, "di", $monto, $id_usuario);
            mysqli_stmt_execute($stmt_r);
            mysqli_stmt_close($stmt_r);

            $tipo = "recarga";
            $desc = "Recarga vía " . ucfirst($metodo_recarga) . " por $" . number_format($monto, 0, ',', '.');
            $stmt_mov = mysqli_prepare($conexion,
                "INSERT INTO movimientos_saldo (id_usuario, tipo, monto, descripcion) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt_mov, "isds", $id_usuario, $tipo, $monto, $desc);
            mysqli_stmt_execute($stmt_mov);
            mysqli_stmt_close($stmt_mov);

            mysqli_commit($conexion);
            $saldo   += $monto;
            $msg      = "✅ Recarga de $" . number_format($monto, 0, ',', '.') . " COP procesada exitosamente.";
            $msg_tipo = "exito";
        } catch (Exception $e) {
            mysqli_rollback($conexion);
            $msg      = "Error al procesar la recarga. Intenta de nuevo.";
            $msg_tipo = "error";
        }

    } elseif ($accion === "retirar") {
        if ($monto > $saldo) {
            $msg      = "Saldo insuficiente. Tu saldo actual es $" . number_format($saldo, 0, ',', '.') . " COP.";
            $msg_tipo = "error";
        } elseif ($monto < 10000) {
            $msg      = "El retiro mínimo es de $10.000 COP.";
            $msg_tipo = "error";
        } else {
            mysqli_begin_transaction($conexion);
            try {
                $stmt_sv = mysqli_prepare($conexion, "SELECT saldo FROM usuarios WHERE id = ? FOR UPDATE");
                mysqli_stmt_bind_param($stmt_sv, "i", $id_usuario);
                mysqli_stmt_execute($stmt_sv);
                $saldo_real = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_sv))["saldo"] ?? 0);
                mysqli_stmt_close($stmt_sv);

                if ($monto > $saldo_real) {
                    mysqli_rollback($conexion);
                    $msg      = "Saldo insuficiente. Tu saldo actual es $" . number_format($saldo_real, 0, ',', '.') . " COP.";
                    $msg_tipo = "error";
                } else {
                    $stmt_ret = mysqli_prepare($conexion, "UPDATE usuarios SET saldo = saldo - ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_ret, "di", $monto, $id_usuario);
                    mysqli_stmt_execute($stmt_ret);
                    mysqli_stmt_close($stmt_ret);

                    $tipo = "retiro";
                    $banco   = trim($_POST["banco_destino"]   ?? "");
                    $cuenta  = trim($_POST["cuenta_destino"]  ?? "");
                    $detalle = ($banco ? " · " . $banco : "") . ($cuenta ? " · " . $cuenta : "");
                    $desc = "Retiro a cuenta bancaria" . $detalle . " · $" . number_format($monto, 0, ',', '.');
                    $stmt_mov = mysqli_prepare($conexion,
                        "INSERT INTO movimientos_saldo (id_usuario, tipo, monto, descripcion) VALUES (?, ?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($stmt_mov, "isds", $id_usuario, $tipo, $monto, $desc);
                    mysqli_stmt_execute($stmt_mov);
                    mysqli_stmt_close($stmt_mov);

                    mysqli_commit($conexion);
                    $saldo   -= $monto;
                    $msg      = "✅ Solicitud de retiro de $" . number_format($monto, 0, ',', '.') . " COP procesada. Se acreditará en 1–3 días hábiles.";
                    $msg_tipo = "exito";
                }
            } catch (Exception $e) {
                mysqli_rollback($conexion);
                $msg      = "Error al procesar el retiro. Intenta de nuevo.";
                $msg_tipo = "error";
            }
        }
    }
}

// Movimientos recientes
$stmt_mov2 = mysqli_prepare($conexion,
    "SELECT * FROM movimientos_saldo WHERE id_usuario = ? ORDER BY fecha DESC LIMIT 15"
);
mysqli_stmt_bind_param($stmt_mov2, "i", $id_usuario);
mysqli_stmt_execute($stmt_mov2);
$movimientos_res = mysqli_stmt_get_result($stmt_mov2);

// Ganancias de rifas
$stmt_gan = mysqli_prepare($conexion,
    "SELECT SUM(p.valor * 0.8) as total
     FROM pagos p
     JOIN boletas b ON p.id_boleta = b.id
     JOIN rifas r ON b.id_rifa = r.id
     WHERE r.id_organizador = ? AND p.estado = 'aprobado'"
);
mysqli_stmt_bind_param($stmt_gan, "i", $id_usuario);
mysqli_stmt_execute($stmt_gan);
$ganancias_rifas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_gan));
mysqli_stmt_close($stmt_gan);
$total_ganancias_rifas = (float)($ganancias_rifas["total"] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Saldo | Soplit.Luck</title>
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
                <a href="4_inicio.php"      class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-lg hover:bg-gray-50 transition-colors">Inicio</a>
                <a href="9_informacion.php" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-lg hover:bg-gray-50 transition-colors">Mi Perfil</a>
                <a href="crear_rifa.php"    class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-lg hover:bg-gray-50 transition-colors">🎰 Crear Rifa</a>
                <a href="mi_saldo.php"      class="px-4 py-2 text-blue-600 font-semibold border-b-2 border-blue-600">💰 Mi Saldo</a>
            </div>
            <div class="flex items-center gap-3">
                <a href="9_informacion.php" class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                    <?= strtoupper(substr($usuario["nombre"], 0, 1) . substr($usuario["apellido"] ?? "", 0, 1)) ?>
                </a>
                <a href="logout.php" class="text-sm text-red-600 font-semibold">Salir</a>
            </div>
        </div>
    </div>
</nav>

<!-- HERO SALDO -->
<div class="bg-gradient-to-r from-blue-600 via-blue-700 to-purple-700 text-white py-12">
    <div class="max-w-7xl mx-auto px-6 text-center">
        <p class="text-blue-200 text-sm font-medium mb-2">Tu saldo disponible</p>
        <h1 class="text-6xl font-black mb-2">$<?= number_format($saldo, 0, ',', '.') ?></h1>
        <p class="text-blue-200">COP · <?= htmlspecialchars($usuario["nombre"] . " " . $usuario["apellido"]) ?></p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-6 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- COLUMNA IZQUIERDA: ACCIONES -->
        <div class="space-y-6">

            <!-- Mini stats -->
            <div class="grid grid-cols-1 gap-4">
                <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center text-2xl">💰</div>
                    <div>
                        <p class="text-sm text-gray-500">Ganancias de rifas</p>
                        <p class="text-xl font-bold text-green-600">$<?= number_format($total_ganancias_rifas, 0, ',', '.') ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl">💳</div>
                    <div>
                        <p class="text-sm text-gray-500">Saldo disponible</p>
                        <p class="text-xl font-bold text-blue-600">$<?= number_format($saldo, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>

            <?php if ($msg): ?>
            <div class="<?= $msg_tipo === 'exito' ? 'bg-green-100 border-green-300 text-green-800' : 'bg-red-100 border-red-300 text-red-800' ?> border rounded-xl p-4 text-sm">
                <?= htmlspecialchars($msg) ?>
            </div>
            <?php endif; ?>

            <!-- ── RECARGAR SALDO ─────────────────────────────────── -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                    <span class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">⬆️</span>
                    Recargar Saldo
                </h3>

                <form method="POST" class="space-y-4" novalidate>
                    <input type="hidden" name="accion" value="recargar">
                    <input type="hidden" name="metodo_recarga" id="metodo_recarga_input" value="tarjeta">

                    <!-- Montos rápidos -->
                    <div class="grid grid-cols-3 gap-2">
                        <?php foreach ([10000, 20000, 50000, 100000, 200000, 500000] as $v): ?>
                        <button type="button" onclick="setMonto(<?= $v ?>)"
                                class="py-2 px-1 border rounded-lg text-xs font-semibold hover:bg-green-50 hover:border-green-400 transition-colors">
                            $<?= number_format($v, 0, ',', '.') ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Monto -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monto a recargar (COP)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold">$</span>
                            <input id="monto-recarga" type="number" name="monto" min="1000" max="10000000" step="1000"
                                   placeholder="Ej: 50000"
                                   class="w-full border-2 border-gray-200 rounded-xl pl-7 pr-4 py-3 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>

                    <!-- Selector de método -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Método de pago</label>
                        <div class="grid grid-cols-4 gap-2">
                            <?php
                            $metodos = [
                                "tarjeta"   => ["💳", "Tarjeta"],
                                "nequi"     => ["🟣", "Nequi"],
                                "daviplata" => ["🔵", "Daviplata"],
                                "pse"       => ["🏦", "PSE"],
                            ];
                            foreach ($metodos as $key => [$icon, $label]): ?>
                            <button type="button" id="btn-<?= $key ?>"
                                    onclick="seleccionarMetodo('<?= $key ?>')"
                                    class="metodo-btn border-2 rounded-xl p-2 text-center transition-colors hover:border-green-400 <?= $key === 'tarjeta' ? 'border-green-500 bg-green-50' : 'border-gray-200' ?>">
                                <div class="text-xl"><?= $icon ?></div>
                                <div class="text-xs font-semibold mt-0.5 leading-tight"><?= $label ?></div>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Campos: Tarjeta -->
                    <div id="fields-tarjeta" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Número de tarjeta</label>
                            <input type="text" placeholder="1234 5678 9012 3456" maxlength="19"
                                   oninput="formatCard(this)"
                                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre del titular</label>
                            <input type="text" placeholder="Como aparece en la tarjeta"
                                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-green-500">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Vencimiento</label>
                                <input type="text" placeholder="MM/AA" maxlength="5"
                                       oninput="formatExp(this)"
                                       class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">CVV</label>
                                <input type="password" placeholder="•••" maxlength="4"
                                       class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>
                    </div>

                    <!-- Campos: Nequi -->
                    <div id="fields-nequi" class="space-y-3 hidden">
                        <div class="bg-purple-50 border border-purple-200 rounded-xl p-3 text-center">
                            <p class="text-xs font-semibold text-purple-700 mb-1">Número Nequi de Soplit.Luck</p>
                            <p class="text-xl font-black text-purple-600 tracking-widest">300 123 4567</p>
                            <p class="text-xs text-purple-500 mt-1">Envía exactamente el monto indicado</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tu número Nequi</label>
                            <input type="tel" placeholder="Ej: 3001234567" maxlength="10"
                                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Número de comprobante</label>
                            <input type="text" placeholder="# de la transacción enviada"
                                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>

                    <!-- Campos: Daviplata -->
                    <div id="fields-daviplata" class="space-y-3 hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-center">
                            <p class="text-xs font-semibold text-blue-700 mb-1">Número Daviplata de Soplit.Luck</p>
                            <p class="text-xl font-black text-blue-600 tracking-widest">301 987 6543</p>
                            <p class="text-xs text-blue-500 mt-1">Envía exactamente el monto indicado</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tu número Daviplata</label>
                            <input type="tel" placeholder="Ej: 3019876543" maxlength="10"
                                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Número de comprobante</label>
                            <input type="text" placeholder="# de la transacción"
                                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Campos: PSE -->
                    <div id="fields-pse" class="space-y-3 hidden">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Banco</label>
                            <select class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">Selecciona tu banco</option>
                                <option>Bancolombia</option>
                                <option>Davivienda</option>
                                <option>Banco de Bogotá</option>
                                <option>BBVA Colombia</option>
                                <option>Scotiabank Colpatria</option>
                                <option>Banco Popular</option>
                                <option>Banco Caja Social</option>
                                <option>Nequi (Bancolombia)</option>
                                <option>Otro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo de cuenta</label>
                            <select class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
                                <option>Cuenta de Ahorros</option>
                                <option>Cuenta Corriente</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Cédula del titular</label>
                            <input type="text" placeholder="Ej: 1234567890" maxlength="15"
                                   class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 transition-colors shadow">
                        ⬆️ Recargar Ahora
                    </button>
                </form>
            </div>

            <!-- ── RETIRAR SALDO ──────────────────────────────────── -->
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                    <span class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">⬇️</span>
                    Retirar Saldo
                </h3>
                <form method="POST" class="space-y-4" novalidate>
                    <input type="hidden" name="accion" value="retirar">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monto a retirar (COP)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-bold">$</span>
                            <input type="number" name="monto" min="10000" max="10000000" step="1000"
                                   placeholder="Mínimo $10.000"
                                   class="w-full border-2 border-gray-200 rounded-xl pl-7 pr-4 py-3 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cuenta destino</label>
                        <input type="text" name="cuenta_destino" placeholder="Número de cuenta o teléfono Nequi" maxlength="30"
                               class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Banco / Método</label>
                        <select name="banco_destino" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-400">
                            <option>Bancolombia</option>
                            <option>Davivienda</option>
                            <option>Banco de Bogotá</option>
                            <option>Nequi</option>
                            <option>Daviplata</option>
                            <option>Otro</option>
                        </select>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-xs text-yellow-800">
                        ⚠️ Retiro mínimo: <strong>$10.000 COP</strong>. Se acredita en 1–3 días hábiles.
                    </div>
                    <button type="submit"
                            class="w-full bg-red-600 text-white py-3 rounded-xl font-bold hover:bg-red-700 transition-colors shadow">
                        ⬇️ Solicitar Retiro
                    </button>
                </form>
            </div>
        </div>

        <!-- COLUMNA DERECHA: HISTORIAL -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="font-bold text-xl mb-6">📊 Historial de Movimientos</h3>

                <?php if (!$movimientos_res || mysqli_num_rows($movimientos_res) === 0): ?>
                    <div class="text-center py-16 text-gray-400">
                        <p class="text-4xl mb-3">📭</p>
                        <p class="font-medium">Sin movimientos aún</p>
                        <p class="text-sm mt-1">Recarga saldo o crea una rifa para comenzar</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Descripción</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Monto</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fecha</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            <?php while ($mov = mysqli_fetch_assoc($movimientos_res)):
                                $tipo_info = [
                                    "recarga"  => ["icon" => "⬆️", "color" => "text-green-600", "bg" => "bg-green-50",  "label" => "Recarga"],
                                    "retiro"   => ["icon" => "⬇️", "color" => "text-red-600",   "bg" => "bg-red-50",    "label" => "Retiro"],
                                    "ganancia" => ["icon" => "💰", "color" => "text-blue-600",  "bg" => "bg-blue-50",   "label" => "Ganancia"],
                                    "comision" => ["icon" => "🏢", "color" => "text-purple-600","bg" => "bg-purple-50", "label" => "Comisión"],
                                    "compra"   => ["icon" => "🎫", "color" => "text-orange-600","bg" => "bg-orange-50", "label" => "Compra"],
                                ][$mov["tipo"]] ?? ["icon" => "💸", "color" => "text-gray-600", "bg" => "bg-gray-50", "label" => "Movimiento"];
                                $signo = in_array($mov["tipo"], ["retiro", "compra"]) ? "-" : "+";
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $tipo_info["bg"] ?> <?= $tipo_info["color"] ?>">
                                        <?= $tipo_info["icon"] ?> <?= $tipo_info["label"] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs"><?= htmlspecialchars($mov["descripcion"]) ?></td>
                                <td class="px-4 py-3 font-bold <?= $tipo_info["color"] ?>">
                                    <?= $signo ?>$<?= number_format($mov["monto"], 0, ',', '.') ?>
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-xs"><?= date("d/m/Y H:i", strtotime($mov["fecha"])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <?php mysqli_stmt_close($stmt_mov2); ?>
            </div>

            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-2xl p-6 mt-6">
                <h3 class="font-bold text-lg mb-3">💡 ¿Cómo funciona el saldo?</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div class="bg-white/10 rounded-xl p-4">
                        <div class="text-2xl mb-2">⬆️</div>
                        <p class="font-semibold">Recarga</p>
                        <p class="text-blue-100 text-xs mt-1">Usa tarjeta, Nequi, Daviplata o PSE para añadir saldo</p>
                    </div>
                    <div class="bg-white/10 rounded-xl p-4">
                        <div class="text-2xl mb-2">🎫</div>
                        <p class="font-semibold">Compra boletas</p>
                        <p class="text-blue-100 text-xs mt-1">El saldo se descuenta automáticamente al confirmar</p>
                    </div>
                    <div class="bg-white/10 rounded-xl p-4">
                        <div class="text-2xl mb-2">🏦</div>
                        <p class="font-semibold">Retira ganancias</p>
                        <p class="text-blue-100 text-xs mt-1">Transfiere a tu cuenta en 1–3 días hábiles</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ── Método de pago activo ────────────────────────────────────────────────────
function seleccionarMetodo(metodo) {
    // Ocultar todos los campos
    ['tarjeta','nequi','daviplata','pse'].forEach(m => {
        document.getElementById('fields-' + m).classList.add('hidden');
        const btn = document.getElementById('btn-' + m);
        btn.classList.remove('border-green-500','bg-green-50');
        btn.classList.add('border-gray-200');
    });
    // Mostrar el seleccionado
    document.getElementById('fields-' + metodo).classList.remove('hidden');
    const btnActivo = document.getElementById('btn-' + metodo);
    btnActivo.classList.add('border-green-500','bg-green-50');
    btnActivo.classList.remove('border-gray-200');
    // Guardar en hidden input
    document.getElementById('metodo_recarga_input').value = metodo;
}

// ── Montos rápidos ───────────────────────────────────────────────────────────
function setMonto(valor) {
    document.getElementById('monto-recarga').value = valor;
}

// ── Formateo tarjeta ─────────────────────────────────────────────────────────
function formatCard(input) {
    let v = input.value.replace(/\D/g,'').substring(0,16);
    input.value = v.replace(/(.{4})/g,'$1 ').trim();
}
function formatExp(input) {
    let v = input.value.replace(/\D/g,'').substring(0,4);
    if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2);
    input.value = v;
}
</script>
</body>
</html>
