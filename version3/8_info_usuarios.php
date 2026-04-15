<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_rol"]) || $_SESSION["usuario_rol"] !== "admin") {
    header("Location: 10_login_admin.php");
    exit();
}

$msg_accion = "";

// Activar / suspender usuario (solo acciones válidas en lista blanca)
if (isset($_GET["accion"]) && isset($_GET["uid"])) {
    $uid    = (int)$_GET["uid"];
    $accion = $_GET["accion"];
    $acciones_validas = ["suspender", "activar"];

    if (!in_array($accion, $acciones_validas)) {
        $msg_accion = "Acción no válida.";
    } elseif ($uid === (int)$_SESSION["usuario_id"]) {
        $msg_accion = "No puedes cambiar el estado de tu propia cuenta.";
    } elseif ($uid > 0) {
        $nuevo_estado = ($accion === "suspender") ? "suspendido" : "activo";
        $stmt_upd = mysqli_prepare($conexion, "UPDATE usuarios SET estado = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_upd, "si", $nuevo_estado, $uid);
        mysqli_stmt_execute($stmt_upd);
        mysqli_stmt_close($stmt_upd);

        $msg_accion = $accion === "suspender"
            ? "Usuario suspendido correctamente."
            : "Usuario activado correctamente.";
    }
}

// Stats (consultas simples de solo lectura, sin datos del usuario — seguras)
$activos     = (int)mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as t FROM usuarios WHERE estado='activo'"))["t"];
$suspendidos = (int)mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as t FROM usuarios WHERE estado='suspendido'"))["t"];
$total_users = (int)mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) as t FROM usuarios"))["t"];

// ── Filtros con prepared statement dinámico ──────────────────────────────────
$conditions = [];
$types      = "";
$params     = [];

if (!empty($_GET["buscar"])) {
    $buscar = "%" . trim($_GET["buscar"]) . "%";
    $conditions[] = "(nombre LIKE ? OR apellido LIKE ? OR email LIKE ?)";
    $types  .= "sss";
    $params  = array_merge($params, [$buscar, $buscar, $buscar]);
}

$estados_validos = ["activo", "inactivo", "suspendido"];
if (!empty($_GET["estado"]) && in_array($_GET["estado"], $estados_validos)) {
    $conditions[] = "estado = ?";
    $types  .= "s";
    $params[] = $_GET["estado"];
}

$roles_validos = ["admin", "organizador", "participante"];
if (!empty($_GET["rol"]) && in_array($_GET["rol"], $roles_validos)) {
    $conditions[] = "rol = ?";
    $types  .= "s";
    $params[] = $_GET["rol"];
}

$where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
$sql_usuarios = "SELECT * FROM usuarios $where_sql ORDER BY fecha_registro DESC";

$stmt_usr = mysqli_prepare($conexion, $sql_usuarios);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_usr, $types, ...$params);
}
mysqli_stmt_execute($stmt_usr);
$usuarios = mysqli_stmt_get_result($stmt_usr);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios - Soplit.Luck Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50">

<!-- HEADER -->
<div class="bg-white shadow-sm border-b px-8 py-4">
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="11_dashboard_admin.php" class="p-2 hover:bg-gray-100 rounded-lg">←</a>
            <h1 class="text-2xl font-bold text-gray-900">Gestión de Usuarios</h1>
        </div>
        <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">Cerrar sesión</a>
    </div>
</div>

<div class="p-8">

    <?php if ($msg_accion): ?>
    <div class="bg-green-100 border border-green-300 text-green-800 p-4 rounded-xl mb-6 text-sm">
        ✅ <?= htmlspecialchars($msg_accion) ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm p-6 flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Total Usuarios</p>
                <p class="text-3xl font-bold"><?= $total_users ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-2xl">👥</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Activos</p>
                <p class="text-3xl font-bold text-green-600"><?= $activos ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center text-2xl">✅</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Suspendidos</p>
                <p class="text-3xl font-bold text-red-600"><?= $suspendidos ?></p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center text-2xl">🚫</div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="bg-white rounded-xl shadow-sm p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                <input type="text" name="buscar" maxlength="100"
                       value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>"
                       placeholder="Nombre, email..."
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select name="estado" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Todos</option>
                    <option value="activo"     <?= ($_GET['estado'] ?? '') === 'activo'     ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo"   <?= ($_GET['estado'] ?? '') === 'inactivo'   ? 'selected' : '' ?>>Inactivo</option>
                    <option value="suspendido" <?= ($_GET['estado'] ?? '') === 'suspendido' ? 'selected' : '' ?>>Suspendido</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Rol</label>
                <select name="rol" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">Todos</option>
                    <option value="admin"        <?= ($_GET['rol'] ?? '') === 'admin'        ? 'selected' : '' ?>>Admin</option>
                    <option value="organizador"  <?= ($_GET['rol'] ?? '') === 'organizador'  ? 'selected' : '' ?>>Organizador</option>
                    <option value="participante" <?= ($_GET['rol'] ?? '') === 'participante' ? 'selected' : '' ?>>Participante</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 font-medium">
                    🔍 Filtrar
                </button>
                <a href="8_info_usuarios.php" class="py-2 px-3 border rounded-lg hover:bg-gray-50 text-sm text-gray-600">
                    Limpiar
                </a>
            </div>
        </div>
    </form>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">
                Usuarios (<?= mysqli_num_rows($usuarios) ?> resultados)
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase text-xs">Usuario</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase text-xs">Rol</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase text-xs">Estado</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase text-xs">Registro</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase text-xs">Teléfono</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase text-xs">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (mysqli_num_rows($usuarios) === 0): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-400">No se encontraron usuarios.</td>
                    </tr>
                <?php else: ?>
                <?php while ($u = mysqli_fetch_assoc($usuarios)):
                    $color_estado = [
                        "activo"     => "bg-green-100 text-green-800",
                        "suspendido" => "bg-red-100 text-red-800",
                        "inactivo"   => "bg-gray-100 text-gray-800",
                    ][$u["estado"]] ?? "bg-gray-100 text-gray-800";

                    $color_rol = [
                        "admin"        => "bg-purple-100 text-purple-800",
                        "organizador"  => "bg-blue-100 text-blue-800",
                        "participante" => "bg-gray-100 text-gray-700",
                    ][$u["rol"]] ?? "";

                    $es_yo = ((int)$u["id"] === (int)$_SESSION["usuario_id"]);

                    // Construir URL de acción preservando solo filtros relevantes
                    $filtros_actuales = http_build_query(array_filter([
                        "buscar" => $_GET["buscar"] ?? "",
                        "estado" => $_GET["estado"] ?? "",
                        "rol"    => $_GET["rol"]    ?? "",
                    ]));
                    $url_base = "8_info_usuarios.php?" . ($filtros_actuales ? $filtros_actuales . "&" : "");
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                <?= strtoupper(substr($u["nombre"], 0, 1) . substr($u["apellido"] ?? "", 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($u["nombre"] . " " . $u["apellido"]) ?></div>
                                <div class="text-gray-500 text-xs"><?= htmlspecialchars($u["email"]) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $color_rol ?>">
                            <?= ucfirst(htmlspecialchars($u["rol"])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $color_estado ?>">
                            <?= ucfirst(htmlspecialchars($u["estado"])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        <?= date("d/m/Y", strtotime($u["fecha_registro"])) ?>
                    </td>
                    <td class="px-6 py-4 text-gray-500"><?= htmlspecialchars($u["telefono"] ?? "—") ?></td>
                    <td class="px-6 py-4">
                        <?php if ($es_yo): ?>
                            <span class="text-xs text-gray-400 italic">Tu cuenta</span>
                        <?php elseif ($u["estado"] === "activo"): ?>
                            <a href="<?= $url_base ?>accion=suspender&uid=<?= (int)$u['id'] ?>"
                               onclick="return confirm('¿Suspender a <?= htmlspecialchars(addslashes($u['nombre']), ENT_QUOTES) ?>? No podrá iniciar sesión.')"
                               class="inline-flex items-center gap-1 bg-red-100 text-red-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-red-200 transition-colors">
                                🚫 Suspender
                            </a>
                        <?php else: ?>
                            <a href="<?= $url_base ?>accion=activar&uid=<?= (int)$u['id'] ?>"
                               onclick="return confirm('¿Activar la cuenta de <?= htmlspecialchars(addslashes($u['nombre']), ENT_QUOTES) ?>?')"
                               class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-3 py-1.5 rounded-lg text-xs font-semibold hover:bg-green-200 transition-colors">
                                ✅ Activar
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php mysqli_stmt_close($stmt_usr); ?>

</div>
</body>
</html>
