<?php include("conexion.php"); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soplit.Luck - Invitado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .pulse-animation { animation: pulse 2s infinite; }
    </style>
</head>
<body class="bg-gray-50">

<!-- HEADER -->
<nav class="bg-white shadow-sm border-b sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <span class="text-2xl mr-2">🍀</span>
                <span class="text-2xl font-black">Soplit.Luck</span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="bg-yellow-400 text-white px-4 py-2 rounded-lg font-bold text-sm">👋 Invitado</span>
                <a href="3_login.php" class="border-2 border-blue-600 text-blue-600 px-5 py-2 rounded-lg font-medium hover:bg-blue-50">Iniciar Sesión</a>
                <a href="2_registro.php" class="bg-blue-600 text-white px-5 py-2 rounded-lg font-medium hover:bg-blue-700">Registrarse</a>
            </div>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero-gradient text-white py-20 text-center">
    <div class="max-w-4xl mx-auto px-6">
        <h1 class="text-5xl font-black mb-6">¡Tu Suerte Te Espera! 🍀</h1>
        <p class="text-xl mb-8">Participa en sorteos digitales, compra boletos y gana premios increíbles.</p>
        <div class="flex justify-center space-x-4">
            <a href="2_registro.php" class="bg-white text-purple-700 px-8 py-4 rounded-xl font-bold pulse-animation">Crear Cuenta Gratis</a>
            <a href="#sorteos" class="border-2 border-white px-8 py-4 rounded-xl font-bold">Ver Sorteos</a>
        </div>
    </div>
</section>

<!-- SORTEOS (vista previa desde la BD) -->
<section class="py-16" id="sorteos">
    <div class="max-w-7xl mx-auto px-6">
        <h2 class="text-4xl font-black text-center mb-12">Sorteos Activos 🎯</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <?php
        $rifas_inv = mysqli_query($conexion, "SELECT * FROM rifas WHERE estado = 'activa' LIMIT 3");
        while ($rifa = mysqli_fetch_assoc($rifas_inv)):
            $emojis = ["🏆","📱","🚗","🎮","💵","✈️","🎁"];
            $emoji = $emojis[$rifa["id"] % count($emojis)];
        ?>
        <div class="bg-white rounded-3xl shadow-lg p-6 card-hover text-center">
            <?php
$imagenes_rifas = [
    1 => "https://zagamotos.com/wp-content/uploads/2019/11/NKD-gris-3.webp",
    2 => "https://www.clevercel.co/cdn/shop/files/Portadas_iPhone14.webp?v=1757093048",
    3 => "https://i.ytimg.com/vi/knz-x6YVizU/mqdefault.jpg",
];
$img = $imagenes_rifas[$rifa["id"]] ?? "";
?>
<img src="<?= $img ?>" alt="<?= htmlspecialchars($rifa['premio']) ?>" class="h-24 w-full object-contain mb-4">
            <h3 class="text-xl font-bold mb-1"><?= htmlspecialchars($rifa["titulo"]) ?></h3>
            <p class="text-gray-500 mb-2"><?= htmlspecialchars($rifa["premio"]) ?></p>
            <p class="text-2xl font-bold text-blue-600 mb-4">$<?= number_format($rifa["precio_boleta"], 0, ',', '.') ?> por boleto</p>
            <button onclick="redirectRegister()"
                    class="w-full bg-purple-600 text-white py-3 rounded-xl font-bold hover:bg-purple-700">
                Comprar Boletos
            </button>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
</section>

<!-- CÓMO FUNCIONA -->
<section class="bg-white py-16">
    <div class="max-w-7xl mx-auto px-6 text-center">
        <h2 class="text-4xl font-black mb-8">¿Cómo Funciona? 🤔</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="p-6">
                <div class="text-5xl mb-4">1️⃣</div>
                <h3 class="text-xl font-bold mb-2">Regístrate</h3>
                <p class="text-gray-500">Crea tu cuenta gratis en segundos</p>
            </div>
            <div class="p-6">
                <div class="text-5xl mb-4">2️⃣</div>
                <h3 class="text-xl font-bold mb-2">Compra Boletos</h3>
                <p class="text-gray-500">Elige tu sorteo favorito y compra los boletos que quieras</p>
            </div>
            <div class="p-6">
                <div class="text-5xl mb-4">3️⃣</div>
                <h3 class="text-xl font-bold mb-2">¡Gana!</h3>
                <p class="text-gray-500">Espera el sorteo en vivo y cobra tu premio</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="bg-gradient-to-r from-purple-600 to-blue-600 text-white py-16 text-center">
    <h2 class="text-4xl font-black mb-6">¿Listo para Ganar? 🚀</h2>
    <a href="2_registro.php" class="bg-white text-purple-700 px-8 py-4 rounded-xl font-bold pulse-animation inline-block">
        Registrarme Ahora
    </a>
</section>

<footer class="bg-gray-900 text-white py-8 text-center">
    <p class="text-gray-400">&copy; 2024 Soplit.Luck</p>
</footer>

<script>
function redirectRegister() {
    alert("Debes registrarte o iniciar sesión para participar.");
    window.location.href = "2_registro.php";
}
</script>
</body>
</html>
