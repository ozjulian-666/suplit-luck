<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"])) {
    header("Location: 3_login.php");
    exit();
}

$id_rifa = isset($_GET["id"]) ? (int)$_GET["id"] : 2;

$rifa = mysqli_fetch_assoc(
    mysqli_query($conexion, "SELECT * FROM rifas WHERE id = $id_rifa AND estado = 'activa'")
);

if (!$rifa) {
    header("Location: 4_inicio.php");
    exit();
}

// Obtener nombre del organizador
$stmt_org = mysqli_prepare($conexion, "SELECT nombre, apellido FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($stmt_org, "i", $rifa["id_organizador"]);
mysqli_stmt_execute($stmt_org);
$organizador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_org));
mysqli_stmt_close($stmt_org);
$nombre_org = $organizador ? htmlspecialchars($organizador["nombre"] . " " . $organizador["apellido"]) : "Usuario";

// Boletas vendidas y disponibles
$vendidas_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM boletas WHERE id_rifa = $id_rifa AND estado = 'vendida'");
$vendidas = mysqli_fetch_assoc($vendidas_res)["total"];

// Obtener estado de TODAS las boletas de esta rifa
$boletas_res = mysqli_query($conexion, "SELECT numero, estado FROM boletas WHERE id_rifa = $id_rifa ORDER BY numero ASC");
$boletas_estado = [];
while ($b = mysqli_fetch_assoc($boletas_res)) {
    $boletas_estado[$b["numero"]] = $b["estado"];
}

// Tiempo restante
$fecha_fin  = new DateTime($rifa["fecha_fin"]);
$ahora      = new DateTime();
$diff       = $ahora->diff($fecha_fin);
$tiempo_str = $diff->days . " días · " . $diff->h . " horas · " . $diff->i . " min";

// Mapa de imágenes
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
$img_url = $rifa["imagen_url"] ?? ($imagenes_rifas[$id_rifa] ?? "");
$pct = $rifa["cantidad_boletas"] > 0 ? round(($vendidas / $rifa["cantidad_boletas"]) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($rifa["titulo"]) ?> - Soplit.Luck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .countdown-timer { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .boleta-btn { transition: all 0.15s ease; user-select: none; }
        .boleta-btn.disponible:hover { transform: scale(1.08); }
        .boleta-btn.seleccionada { transform: scale(1.05); }
    </style>
</head>
<body class="bg-gray-50">

<!-- NAV -->
<nav class="bg-white shadow-sm border-b sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-3">
                <a href="4_inicio.php" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <span class="text-xl font-bold">🍀 Soplit.Luck</span>
            </div>
            <div class="text-sm text-gray-600 flex items-center gap-4">
                <span class="font-medium hidden md:block"><?= htmlspecialchars($_SESSION["usuario_nombre"]) ?></span>
                <a href="logout.php" class="text-red-600 font-semibold">Salir</a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-6 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Contenido principal -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Countdown -->
            <div class="countdown-timer text-white p-5 rounded-2xl text-center">
                <h2 class="text-xl font-bold mb-1">⏰ ¡El sorteo termina pronto!</h2>
                <p class="text-base font-mono"><?= $tiempo_str ?></p>
            </div>

            <!-- Imagen -->
            <div class="bg-white rounded-2xl shadow-lg p-6 flex items-center justify-center" style="min-height:240px">
                <img src="<?= htmlspecialchars($img_url) ?>"
                     alt="<?= htmlspecialchars($rifa['premio']) ?>"
                     class="max-h-56 w-full object-contain">
            </div>

            <!-- Info rifa -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h1 class="text-2xl font-bold mb-1"><?= htmlspecialchars($rifa["titulo"]) ?></h1>
                <p class="text-gray-500 mb-4"><?= htmlspecialchars($rifa["descripcion"]) ?></p>

                <div class="mb-3">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Vendidas</span>
                        <span class="font-semibold"><?= $vendidas ?> / <?= $rifa["cantidad_boletas"] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div class="bg-blue-500 h-2.5 rounded-full" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-xs font-medium">🏅 <?= htmlspecialchars($rifa["premio"]) ?></span>
                    <span class="bg-green-50 text-green-700 px-3 py-1 rounded-full text-xs font-medium">📅 Cierra: <?= date("d/m/Y", strtotime($rifa["fecha_fin"])) ?></span>
                    <span class="bg-purple-50 text-purple-700 px-3 py-1 rounded-full text-xs font-medium">👤 Rifa creada por: <?= $nombre_org ?></span>
                    <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs"><?= htmlspecialchars($rifa["requisitos_legales"]) ?></span>
                </div>
            </div>

            <!-- ===== TABLA DE SELECCIÓN DE BOLETAS ===== -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">🎫 Elige tus Números</h2>
                    <div class="flex gap-3 text-xs">
                        <span class="flex items-center gap-1"><span class="w-4 h-4 rounded bg-blue-500 inline-block"></span> Seleccionado</span>
                        <span class="flex items-center gap-1"><span class="w-4 h-4 rounded bg-gray-200 inline-block border"></span> Disponible</span>
                        <span class="flex items-center gap-1"><span class="w-4 h-4 rounded bg-red-300 inline-block"></span> Vendido</span>
                    </div>
                </div>

                <!-- Leyenda max -->
                <p class="text-xs text-gray-500 mb-4">Puedes seleccionar hasta <strong>15 boletas</strong> a la vez.</p>

                <!-- Grid de boletas -->
                <div class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-10 gap-1.5 mb-6" id="boletas-grid">
                    <?php for ($n = 1; $n <= $rifa["cantidad_boletas"]; $n++):
                        $est = $boletas_estado[$n] ?? 'disponible';
                        $vendida = ($est === 'vendida' || $est === 'reservada');
                    ?>
                    <button
                        type="button"
                        class="boleta-btn <?= $vendida ? 'vendida bg-red-200 text-red-600 cursor-not-allowed' : 'disponible bg-gray-100 hover:bg-blue-100 text-gray-700' ?> rounded-lg py-1.5 text-xs font-bold border <?= $vendida ? 'border-red-300' : 'border-gray-200' ?>"
                        data-numero="<?= $n ?>"
                        <?= $vendida ? 'disabled' : '' ?>
                        onclick="toggleBoleta(this, <?= $n ?>)">
                        <?= str_pad($n, 3, "0", STR_PAD_LEFT) ?>
                    </button>
                    <?php endfor; ?>
                </div>

                <!-- Seleccionadas -->
                <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold text-blue-800 text-sm">Boletas seleccionadas: <span id="count-sel">0</span></span>
                        <button onclick="limpiarSeleccion()" class="text-xs text-red-500 hover:underline">Limpiar</button>
                    </div>
                    <div id="selected-preview" class="flex flex-wrap gap-1 min-h-6">
                        <span class="text-xs text-blue-400 italic" id="empty-hint">Haz clic en un número para seleccionarlo</span>
                    </div>
                    <div class="mt-3 border-t border-blue-200 pt-3 flex justify-between items-center">
                        <span class="text-sm text-blue-700">Total a pagar:</span>
                        <span class="text-xl font-bold text-blue-700" id="total-price">$0</span>
                    </div>
                </div>
            </div>

            <!-- Garantías -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-2xl p-5">
                <h3 class="font-bold mb-3">🛡️ Compra con confianza</h3>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="flex items-center gap-2">✅ Sorteo certificado Coljuegos</div>
                    <div class="flex items-center gap-2">✅ Premio garantizado</div>
                    <div class="flex items-center gap-2">✅ Pago 100% seguro</div>
                    <div class="flex items-center gap-2">✅ Resultados en vivo</div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <div class="bg-white rounded-2xl shadow-lg p-6 sticky top-24">
                <div class="text-center mb-5">
                    <div class="text-3xl font-bold text-blue-600">$<?= number_format($rifa["precio_boleta"], 0, ',', '.') ?></div>
                    <div class="text-gray-500 text-sm">por boleto</div>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 mb-5 text-center">
                    <div class="text-sm text-gray-500 mb-1">Seleccionadas:</div>
                    <div class="text-4xl font-bold text-gray-800" id="sidebar-count">0</div>
                    <div class="text-sm text-gray-500">boletas</div>
                    <div class="mt-2 text-lg font-bold text-green-600" id="sidebar-total">$0</div>
                </div>

                <button id="btn-comprar" disabled
                        onclick="irAComprar()"
                        class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold text-base hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors mb-2">
                    💳 Ir al Pago
                </button>
                <p class="text-xs text-center text-gray-400">🔒 Pago seguro</p>

                <div class="mt-4 p-3 bg-yellow-50 rounded-lg text-xs text-yellow-700">
                    ⚡ <strong>Selecciona tus números</strong> en la tabla de la izquierda y luego haz clic en "Ir al Pago".
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const precio = <?= $rifa["precio_boleta"] ?>;
const id_rifa = <?= $id_rifa ?>;
let seleccionadas = new Set();
const MAX = 15;

function toggleBoleta(btn, numero) {
    if (seleccionadas.has(numero)) {
        seleccionadas.delete(numero);
        btn.classList.remove('seleccionada', 'bg-blue-500', 'text-white', 'border-blue-600');
        btn.classList.add('disponible', 'bg-gray-100', 'text-gray-700', 'border-gray-200');
    } else {
        if (seleccionadas.size >= MAX) {
            alert('Máximo ' + MAX + ' boletas por compra.');
            return;
        }
        seleccionadas.add(numero);
        btn.classList.remove('disponible', 'bg-gray-100', 'text-gray-700', 'border-gray-200');
        btn.classList.add('seleccionada', 'bg-blue-500', 'text-white', 'border-blue-600');
    }
    actualizarUI();
}

function actualizarUI() {
    const count = seleccionadas.size;
    const total = count * precio;
    const fmt = '$' + total.toLocaleString('es-CO');

    document.getElementById('count-sel').textContent = count;
    document.getElementById('total-price').textContent = fmt;
    document.getElementById('sidebar-count').textContent = count;
    document.getElementById('sidebar-total').textContent = fmt;
    document.getElementById('btn-comprar').disabled = count === 0;

    const preview = document.getElementById('selected-preview');
    const hint = document.getElementById('empty-hint');
    if (count === 0) {
        preview.innerHTML = '';
        if (hint) hint.style.display = 'inline';
        else {
            const h = document.createElement('span');
            h.id = 'empty-hint';
            h.className = 'text-xs text-blue-400 italic';
            h.textContent = 'Haz clic en un número para seleccionarlo';
            preview.appendChild(h);
        }
    } else {
        preview.innerHTML = '';
        seleccionadas.forEach(n => {
            const tag = document.createElement('span');
            tag.className = 'bg-blue-500 text-white px-2 py-0.5 rounded text-xs font-bold';
            tag.textContent = '#' + String(n).padStart(3, '0');
            preview.appendChild(tag);
        });
    }
}

function limpiarSeleccion() {
    seleccionadas.forEach(n => {
        const btn = document.querySelector(`[data-numero="${n}"]`);
        if (btn) {
            btn.classList.remove('seleccionada', 'bg-blue-500', 'text-white', 'border-blue-600');
            btn.classList.add('disponible', 'bg-gray-100', 'text-gray-700', 'border-gray-200');
        }
    });
    seleccionadas.clear();
    actualizarUI();
}

function irAComprar() {
    if (seleccionadas.size === 0) return;
    const nums = Array.from(seleccionadas).join(',');
    window.location.href = `6_compra.php?id_rifa=${id_rifa}&numeros=${nums}&qty=${seleccionadas.size}`;
}
</script>
</body>
</html>
