<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario_id"]) || !isset($_SESSION["compra"])) {
    header("Location: 4_inicio.php");
    exit();
}

$compra  = $_SESSION["compra"];
$numeros = $compra["numeros"];
unset($_SESSION["compra"]);

$sorteo = $_SESSION["sorteo_ejecutado"] ?? null;
unset($_SESSION["sorteo_ejecutado"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Pago Exitoso! - Soplit.Luck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .success-gradient { background: linear-gradient(135deg,#10B981,#059669); }
        .ticket-gradient { background: linear-gradient(135deg,#3B82F6,#1E40AF); }
        .confetti { animation: confetti 3s ease-in-out infinite; }
        @keyframes confetti { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
    </style>
</head>
<body class="bg-gray-50">

<nav class="bg-white shadow-sm border-b">
    <div class="max-w-7xl mx-auto px-6 flex justify-between items-center h-16">
        <a href="4_inicio.php" class="flex items-center font-bold text-xl">🍀 Soplit.Luck</a>
        <a href="logout.php" class="text-red-600 font-semibold text-sm">Salir</a>
    </div>
</nav>

<div class="max-w-4xl mx-auto px-6 py-10">

    <!-- Banner éxito -->
    <div class="success-gradient text-white p-8 rounded-2xl text-center mb-10 relative overflow-hidden">
        <div class="absolute top-4 left-4 text-2xl confetti">🎉</div>
        <div class="absolute top-8 right-8 text-2xl confetti">✨</div>
        <div class="absolute bottom-4 right-4 text-2xl confetti">🍀</div>

        <h1 class="text-4xl font-bold mb-2">¡Pago Exitoso! 🎉</h1>
        <p class="text-green-100 text-lg mb-6"><?= htmlspecialchars($compra["rifa_titulo"]) ?></p>

        <div class="flex justify-center gap-10">
            <div>
                <div class="text-3xl font-bold"><?= count($numeros) ?></div>
                <div class="text-green-100">Boleto<?= count($numeros) > 1 ? "s" : "" ?></div>
            </div>
            <div>
                <div class="text-3xl font-bold">$<?= number_format($compra["total"], 0, ',', '.') ?></div>
                <div class="text-green-100">Pagado</div>
            </div>
            <div>
                <div class="text-3xl font-bold"><?= date("d/m/Y", strtotime($compra["fecha_sorteo"])) ?></div>
                <div class="text-green-100">Fecha sorteo</div>
            </div>
        </div>
    </div>

    <!-- Banner sorteo automático (solo si se ejecutó al acabarse los boletos) -->
    <?php if ($sorteo): ?>
    <?php if ($sorteo["yo_gane"]): ?>
    <div class="relative overflow-hidden rounded-2xl text-center p-10 mb-10"
         style="background: linear-gradient(135deg,#F59E0B,#D97706);">
        <div class="absolute top-3 left-6 text-3xl confetti">🏆</div>
        <div class="absolute top-4 right-6 text-3xl confetti">🎊</div>
        <div class="absolute bottom-3 left-10 text-2xl confetti">⭐</div>
        <p class="text-white/80 text-sm font-semibold uppercase tracking-widest mb-2">¡Las boletas se agotaron y se realizó el sorteo!</p>
        <h2 class="text-5xl font-black text-white mb-3">¡¡GANASTE!! 🏆</h2>
        <p class="text-yellow-100 text-lg mb-1">
            Tu boleta <span class="font-black text-white text-2xl">#<?= str_pad($sorteo["numero_ganador"], 4, "0", STR_PAD_LEFT) ?></span> fue elegida ganadora
        </p>
        <p class="text-yellow-200 text-sm">Revisa tus notificaciones y contacta al organizador para reclamar tu premio.</p>
    </div>
    <?php else: ?>
    <div class="bg-indigo-600 text-white rounded-2xl text-center p-8 mb-10">
        <p class="text-indigo-200 text-sm uppercase tracking-widest mb-2">Las boletas se agotaron — sorteo ejecutado</p>
        <h2 class="text-3xl font-bold mb-2">🎲 Resultado del sorteo</h2>
        <p class="text-indigo-100 text-lg">
            Número ganador: <span class="font-black text-white text-2xl">#<?= str_pad($sorteo["numero_ganador"], 4, "0", STR_PAD_LEFT) ?></span>
        </p>
        <p class="text-indigo-300 text-sm mt-2">Esta vez no fue tu boleta, ¡pero sigue participando!</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Acciones -->
    <div class="flex flex-col md:flex-row justify-center gap-6 mb-12">
        <a href="4_inicio.php" class="bg-blue-600 text-white px-8 py-4 rounded-xl font-bold text-center hover:bg-blue-700">
            🏠 Volver al Inicio
        </a>
        <a href="9_informacion.php" class="bg-gray-200 px-8 py-4 rounded-xl font-bold text-center hover:bg-gray-300">
            👤 Ver Mis Boletos
        </a>
    </div>

    <!-- Tickets -->
    <h2 class="text-2xl font-bold mb-4">🎫 Tus Boletos</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($numeros as $num): ?>
        <div class="ticket-gradient text-white p-6 rounded-2xl">
            <p class="text-sm text-blue-200">Boleto #</p>
            <p class="text-4xl font-bold font-mono"><?= str_pad($num, 4, "0", STR_PAD_LEFT) ?></p>
            <p class="mt-4 text-sm text-blue-100"><?= htmlspecialchars($compra["premio"]) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Compartir -->
    <div class="bg-white rounded-2xl shadow-sm p-6 mt-10">
        <h3 class="text-xl font-bold mb-4">¡Comparte tu participación! 🚀</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button onclick="share('WhatsApp')" class="bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600">📱 WhatsApp</button>
            <button onclick="share('Facebook')" class="bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700">📘 Facebook</button>
            <button onclick="copiarEnlace()" class="border py-3 rounded-lg font-semibold hover:bg-gray-50">🔗 Copiar Enlace</button>
        </div>
    </div>

</div>

<script>
function share(red) {
    alert("Compartir en " + red + " (simulado)");
}
function copiarEnlace() {
    navigator.clipboard.writeText(window.location.origin);
    alert("¡Enlace copiado!");
}
</script>
</body>
</html>
