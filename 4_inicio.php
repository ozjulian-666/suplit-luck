<?php
session_start();
include("conexion.php");
include("notif_helper.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: 3_login.php");
    exit();
}

$id_usuario = (int)$_SESSION["usuario_id"];

// Obtener saldo y verificar estado activo del usuario
$stmt_u = mysqli_prepare($conexion, "SELECT nombre, apellido, saldo, estado FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($stmt_u, "i", $id_usuario);
mysqli_stmt_execute($stmt_u);
$usuario_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u));
mysqli_stmt_close($stmt_u);

if (!$usuario_data || $usuario_data["estado"] !== "activo") {
    session_destroy();
    header("Location: 3_login.php?bloqueado=1");
    exit();
}

$nombre_usuario = $usuario_data["nombre"] . " " . $usuario_data["apellido"];
$saldo = (float)($usuario_data["saldo"] ?? 0);

// Notificaciones no leídas del usuario
$notificaciones = [];
$stmt_noti = mysqli_prepare($conexion,
    "SELECT id, mensaje, fecha FROM notificaciones WHERE id_usuario = ? AND leida = 0 ORDER BY fecha DESC"
);
if ($stmt_noti) {
    mysqli_stmt_bind_param($stmt_noti, "i", $id_usuario);
    mysqli_stmt_execute($stmt_noti);
    $notificaciones = mysqli_fetch_all(mysqli_stmt_get_result($stmt_noti), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_noti);
}

// Las notificaciones se marcan leídas vía fetch cuando el usuario las cierra (ver JS abajo)

// Cargar rifas activas (solo lectura — no usa datos del usuario, segura sin prepared)
$rifas = mysqli_query($conexion, "SELECT * FROM rifas WHERE estado = 'activa' ORDER BY fecha_fin ASC");

// Mapa de imágenes por ID de rifa (IDs 1-28 tras limpiar duplicados)
$imagenes_rifas = [
    1  => "https://zagamotos.com/wp-content/uploads/2019/11/NKD-gris-3.webp",
    2  => "https://www.clevercel.co/cdn/shop/files/Portadas_iPhone14.webp?v=1757093048",
    3  => "https://i.ytimg.com/vi/knz-x6YVizU/mqdefault.jpg",
    4  => "https://portatil.com.co/wp-content/uploads/2023/10/HP-AMD-Ryzen-5-Nucleos-Inicio-I-portatil.com_.co_.jpg",
    5  => "https://www.mipcparquecentral.com/cdn/shop/files/Armor-elite-02-2.png?v=1725379349",
    6  => "https://todoparaciclismo.com/cdn/shop/files/070904_800x.png?v=1719588672",
    7  => "https://i0.wp.com/boxyc.com.co/wp-content/uploads/2023/05/51.jpg?fit=500%2C500&ssl=1",
    8  => "https://www.solmaryluna.com/images/solmaryluna/banner-2024/banner-cartagena-de-indias-sol-mar-y-luna.jpg",
    9  => "https://images.samsung.com/es/galaxy-watch6/feature/galaxy-watch6-kv-pc.jpg",
    10 => "https://www.capitalcolombia.com/fotos/logitech_gaming__combo_4_en_1_teclado_mouse_pad_diadema_p1072s2.jpg",
    11 => "https://eoatecnologia.com/cdn/shop/products/Drone-DJI-Mini-3-Pro-RC-1.webp?v=1659375247",
    12 => "https://mac-center.com/cdn/shop/files/JBLCHARGE5BLKAM-3.png?v=1684870029",
    13 => "https://media.falabella.com/falabellaCO/126135027_01/w=1500,h=1500,fit=cover",
    14 => "https://http2.mlstatic.com/D_NQ_NP_814557-MLA99994005159_112025-O.webp",
    15 => "https://www.sony.com.co/image/6145c1d32e6ac8e63a46c912dc33c5bb?fmt=pjpeg&wid=330&bgcolor=FFFFFF&bgc=FFFFFF",
    16 => "https://redragon.es/content/uploads/2021/04/KUMARA.png",
    17 => "https://supernovedadesof.com/cdn/shop/files/Kitdecreacion.jpg?v=1721677825",
    18 => "https://perfumesbogota.com.co/cdn/shop/products/perfume-paco-rabanne-fame-eau-de-parfum-80ml-mujer-6795595_580x.jpg?v=1758669299",
    19 => "https://www.kalley.com.co/medias/7700149140010-001-1400Wx1400H?context=bWFzdGVyfGltYWdlc3wyNDQ4OHxpbWFnZS93ZWJwfGFEQm1MMmhrTkM4eE5EYzVOemd5TnpRME1EWTNNQzgzTnpBd01UUTVNVFF3TURFd1h6QXdNVjh4TkRBd1YzZ3hOREF3U0F8MzNlMTViOTI4OGE0OGJmZmZiNzlkYzRlNmM2ZDFmODZlMmZkZjNiMDVhMWE3MjA2OTAwZDM5ZjdhY2IxYjg4MQ",
    20 => "https://agaval.vtexassets.com/arquivos/ids/1838145-800-600?v=638919928895470000&width=800&height=600&aspect=true",
    21 => "https://exitocol.vtexassets.com/arquivos/ids/31472828/Cafetera-Nespresso-Essenza-Min-NESPRESSO-Essenza-Mini-3053689_a.jpg?v=638972584631900000",
    22 => "https://groupesebcol.vtexassets.com/arquivos/ids/168843/2100113050-1-1.jpg?v=638513991748270000",
    23 => "https://ecommerce.redox.com.co/backend/admin/backend/web/archivosDelCliente/categorias/images/20210709165457-Categorias-Aseo-y-Cuidado-Personal16258676978813.jpg",
    24 => "https://www.megared.com.py/storage/sku/samsung-microondas-microondas-samsung-20l-650w-black-ms20a3010al-3-1-1733940778.jpg",
    25 => "https://www.lg.com/content/dam/channel/wcms/co/images/barras-de-sonido/s60tr/gallery/av-soundbar-s60tr-gallery-01.jpg/jcr:content/renditions/thum-1600x1062.jpeg",
    26 => "https://http2.mlstatic.com/D_NQ_NP_810366-MLA81253809817_122024-O.webp",
    27 => "https://media.falabella.com/sodimacCO/906130_01/public",
    28 => "https://http2.mlstatic.com/D_NQ_NP_738507-MLA99509038916_112025-O.webp",
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soplit.Luck - Inicio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-gradient-header { background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 100%); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.25); }
    </style>
</head>
<body class="bg-gray-50">

<!-- NAV -->
<nav class="bg-white shadow-lg border-b sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                    <span class="text-xl">🍀</span>
                </div>
                <span class="text-2xl font-bold">Soplit.Luck</span>
            </div>

            <div class="hidden md:flex items-center space-x-1">
                <a href="4_inicio.php" class="px-4 py-2 text-blue-600 font-semibold border-b-2 border-blue-600">Inicio</a>
                <a href="9_informacion.php" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-lg hover:bg-gray-50 transition-colors">Mi Perfil</a>
                <a href="crear_rifa.php" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-lg hover:bg-gray-50 transition-colors">🎰 Crear Rifa</a>
                <a href="mi_saldo.php" class="px-4 py-2 text-gray-600 hover:text-gray-900 font-medium rounded-lg hover:bg-gray-50 transition-colors">💰 Mi Saldo</a>
            </div>

            <div class="flex items-center space-x-3">
                <a href="mi_saldo.php" class="hidden md:flex items-center gap-2 bg-green-50 border border-green-200 rounded-xl px-3 py-1.5 text-sm">
                    <span class="text-green-600 font-bold">$<?= number_format($saldo, 0, ',', '.') ?></span>
                    <span class="text-green-500 text-xs">saldo</span>
                </a>

                <!-- Campanita de notificaciones -->
                <div class="relative">
                    <button onclick="toggleNotifPanel()"
                            class="w-10 h-10 flex items-center justify-center rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors text-xl"
                            title="Notificaciones">
                        🔔
                    </button>
                    <?php if (count($notificaciones) > 0): ?>
                    <span id="notif-badge"
                          class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1 leading-none">
                        <?= count($notificaciones) ?>
                    </span>
                    <?php endif; ?>
                </div>

                <a href="9_informacion.php" class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                    <?= strtoupper(substr($nombre_usuario, 0, 2)) ?>
                </a>
                <div class="hidden md:block">
                    <div class="text-sm font-medium"><?= htmlspecialchars($nombre_usuario) ?></div>
                </div>
                <a href="logout.php" class="text-sm text-red-600 font-semibold ml-2">Salir</a>
            </div>
        </div>
    </div>
</nav>

<!-- HERO -->
<div class="bg-gradient-header text-white py-14 text-center">
    <h1 class="text-5xl font-bold mb-3">¡Tu suerte te espera, <?= htmlspecialchars(explode(' ', $nombre_usuario)[0]) ?>!</h1>
    <p class="text-xl text-blue-100">Participa en los mejores sorteos o crea tu propia rifa</p>
</div>

<!-- PANEL DE NOTIFICACIONES (dropdown desde campanita) -->
<div id="notif-panel"
     class="hidden fixed right-4 top-20 z-50 w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden"
     style="max-height: 480px; overflow-y: auto;">

    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
        <div class="flex items-center gap-2">
            <span class="text-lg">🔔</span>
            <span class="font-bold text-gray-800 text-sm">Notificaciones</span>
            <?php if (count($notificaciones) > 0): ?>
            <span id="notif-badge-panel" class="bg-red-500 text-white text-[10px] font-bold rounded-full px-1.5 py-0.5 leading-none">
                <?= count($notificaciones) ?>
            </span>
            <?php endif; ?>
        </div>
        <button onclick="toggleNotifPanel()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
    </div>

    <div id="notif-list">
    <?php if (empty($notificaciones)): ?>
        <div class="text-center py-10 text-gray-400">
            <div class="text-4xl mb-2">🔕</div>
            <p class="text-sm">Sin notificaciones</p>
        </div>
    <?php else: ?>
        <?php foreach ($notificaciones as $notif): ?>
        <div data-id="<?= (int)$notif['id'] ?>"
             class="notif-item flex items-start gap-3 px-5 py-4 border-b border-gray-50 hover:bg-amber-50 transition-colors">
            <div class="flex-shrink-0 w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center text-base mt-0.5">
                ⚠️
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-amber-800 mb-0.5">Aviso del administrador</p>
                <p class="text-xs text-gray-700 leading-relaxed"><?= htmlspecialchars($notif["mensaje"]) ?></p>
                <p class="text-[10px] text-gray-400 mt-1"><?= date("d/m/Y H:i", strtotime($notif["fecha"])) ?></p>
            </div>
            <button onclick="cerrarNotif(this, <?= (int)$notif['id'] ?>)"
                    class="flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-full text-gray-300 hover:bg-red-100 hover:text-red-500 transition-colors text-sm font-bold ml-1"
                    title="Descartar">✕</button>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<!-- Overlay para cerrar panel al hacer click fuera -->
<div id="notif-overlay" class="hidden fixed inset-0 z-40" onclick="toggleNotifPanel()"></div>

<script>
let notifCount = <?= count($notificaciones) ?>;

function toggleNotifPanel() {
    const panel   = document.getElementById('notif-panel');
    const overlay = document.getElementById('notif-overlay');
    const isOpen  = !panel.classList.contains('hidden');
    panel.classList.toggle('hidden');
    overlay.classList.toggle('hidden');
}

function cerrarNotif(btn, id) {
    const item = btn.closest('.notif-item');
    item.style.transition = 'opacity 0.3s, max-height 0.3s';
    item.style.opacity = '0';
    setTimeout(() => {
        item.remove();
        notifCount--;
        actualizarBadge();
        // Si no quedan, mostrar estado vacío
        if (notifCount === 0) {
            document.getElementById('notif-list').innerHTML =
                '<div class="text-center py-10 text-gray-400"><div class="text-4xl mb-2">🔕</div><p class="text-sm">Sin notificaciones</p></div>';
        }
    }, 300);
    fetch('marcar_notif_leida.php?id=' + id);
}

function actualizarBadge() {
    // Badge sobre la campanita
    const badge = document.getElementById('notif-badge');
    const badgePanel = document.getElementById('notif-badge-panel');
    if (notifCount > 0) {
        if (badge) badge.textContent = notifCount;
        if (badgePanel) badgePanel.textContent = notifCount;
    } else {
        if (badge) badge.remove();
        if (badgePanel) badgePanel.remove();
    }
}
</script>

<!-- SORTEOS DESDE LA BD -->
<div class="max-w-7xl mx-auto px-6 py-12">
    <h2 class="text-3xl font-bold mb-8">🔥 Sorteos Activos</h2>

    <?php if (mysqli_num_rows($rifas) === 0): ?>
        <div class="text-center py-16 text-gray-500">
            <p class="text-2xl">😔 No hay sorteos activos en este momento.</p>
            <p class="mt-2">Vuelve pronto o <a href="crear_rifa.php" class="text-blue-600 font-semibold underline">crea el tuyo</a>.</p>
        </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php while ($rifa = mysqli_fetch_assoc($rifas)):
            $id_rifa = (int)$rifa["id"];

            // Contar boletas vendidas para esta rifa
            $stmt_v = mysqli_prepare($conexion, "SELECT COUNT(*) as total FROM boletas WHERE id_rifa = ? AND estado = 'vendida'");
            mysqli_stmt_bind_param($stmt_v, "i", $id_rifa);
            mysqli_stmt_execute($stmt_v);
            $vendidas = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_v))["total"];
            mysqli_stmt_close($stmt_v);

            $porcentaje = $rifa["cantidad_boletas"] > 0 ? round(($vendidas / $rifa["cantidad_boletas"]) * 100) : 0;
            $img_url = $rifa["imagen_url"] ?? ($imagenes_rifas[$id_rifa] ?? "");
        ?>
        <div class="bg-white rounded-2xl shadow-lg card-hover overflow-hidden">
            <div class="bg-gray-50 h-44 flex items-center justify-center overflow-hidden">
                <img src="<?= htmlspecialchars($img_url) ?>"
                     alt="<?= htmlspecialchars($rifa['premio']) ?>"
                     class="h-full w-full object-contain p-4"
                     onerror="this.src='https://cdn-icons-png.flaticon.com/512/3112/3112946.png'">
            </div>
            <div class="p-6">
                <h3 class="text-xl font-bold mb-1"><?= htmlspecialchars($rifa["titulo"]) ?></h3>
                <p class="text-gray-500 text-sm mb-3"><?= htmlspecialchars($rifa["premio"]) ?></p>

                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-500">Progreso</span>
                    <span class="font-semibold"><?= $vendidas ?> / <?= $rifa["cantidad_boletas"] ?> boletas</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                    <div class="bg-blue-500 h-2 rounded-full" style="width: <?= $porcentaje ?>%"></div>
                </div>

                <div class="flex justify-between items-center mb-4">
                    <div>
                        <span class="text-2xl font-bold text-blue-600">$<?= number_format($rifa["precio_boleta"], 0, ',', '.') ?></span>
                        <span class="text-gray-500 text-sm"> / boleto</span>
                    </div>
                    <span class="text-xs text-gray-400">Cierra: <?= date("d M", strtotime($rifa["fecha_fin"])) ?></span>
                </div>

                <a href="5_rifaiphone.php?id=<?= $id_rifa ?>"
                   class="block text-center bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Participar Ahora
                </a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html> 