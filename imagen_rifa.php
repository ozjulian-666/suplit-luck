<?php
// =============================================
//  HELPER: devuelve URL de imagen según premio
// =============================================
function getImagenRifa($premio, $titulo = "") {
    $texto = strtolower($premio . " " . $titulo);

    // iPhone
    if (strpos($texto, "iphone 15") !== false)
        return "https://kms-assets.tracapis.com/assets/products/product-assets/iphone%2015%20pro%20max%20(1).png";
    if (strpos($texto, "iphone 14") !== false)
        return "https://kms-assets.tracapis.com/composer/devices/null_39743/iPhone14.png";
    if (strpos($texto, "iphone") !== false)
        return "https://cdsassets.apple.com/live/7WUAS350/images/iphone/iphone-17-pro-max-colors.png";

    // Samsung Galaxy
    if (strpos($texto, "playstation 5") !== false)
        return "https://gameplay.com.co/cdn/shop/files/Consola-PS5-Disco-Returnal.jpg?v=1748806834";
    if (strpos($texto, "galaxy tab") !== false || strpos($texto, "tablet samsung") !== false)
        return "https://images.samsung.com/is/image/samsung/assets/co/tablets/galaxy-tab-s10/buy/Plus_Color_Selection_Moonstone_Gray_MO.png?imbypass=true";
    if (strpos($texto, "samsung") !== false)
        return "https://i.blogs.es/0e9c1d/galaxy-s20-hero/840_560.jpg";

    // PlayStation PS5
    if (strpos($texto, "ps5") !== false || strpos($texto, "playstation") !== false)
        return "https://gameplay.com.co/cdn/shop/files/Consola-PS5-Disco-Returnal.jpg?v=1748806834";

    // Xbox
    if (strpos($texto, "xbox series") !== false)
        return "https://img-prod-cms-rt-microsoft-com.akamaized.net/cms/api/am/imageFileData/RE4mRni?ver=c542";
    if (strpos($texto, "xbox") !== false)
        return "https://img-prod-cms-rt-microsoft-com.akamaized.net/cms/api/am/imageFileData/RE4mRni?ver=c542";

    // Nintendo Switch
    if (strpos($texto, "nintendo switch") !== false || strpos($texto, "switch oled") !== false)
        return "https://assets.nintendo.com/image/upload/ar_16:9,c_lpad,w_1240/b_white/f_auto/q_auto/ncom/software/switch/70010000063714/ac5e645e1d59e71df7d9a430d3b41e3c79e34fcb7acdebbad1c28b2dfafb56ab";

    // Portátil / Laptop
    if (strpos($texto, "portatil") !== false || strpos($texto, "laptop") !== false || strpos($texto, "hp") !== false)
        return "https://ssl-product-images.www8-hp.com/digmedialib/prodimg/knowledgebase/c08293877.png";

    // Moto / Motocicleta
    if (strpos($texto, "moto") !== false || strpos($texto, "akt") !== false)
        return "https://aktmotos.com/wp-content/uploads/2023/02/TTR-125-SE-2023.png";

    // Auto / Carro
    if (strpos($texto, "chevrolet") !== false || strpos($texto, "onix") !== false)
        return "https://www.chevrolet.com.co/content/dam/chevrolet/mercosur/colombia/spanish/index/cars/2023-onix/colorizer/01-images/chevrolet-onix-red.jpg";
    if (strpos($texto, "auto") !== false || strpos($texto, "carro") !== false || strpos($texto, "vehiculo") !== false)
        return "https://www.chevrolet.com.co/content/dam/chevrolet/mercosur/colombia/spanish/index/cars/2023-onix/colorizer/01-images/chevrolet-onix-red.jpg";

    // Silla gamer
    if (strpos($texto, "silla gamer") !== false || strpos($texto, "cougar") !== false)
        return "https://m.media-amazon.com/images/I/71Kln7KPXVL._AC_SL1500_.jpg";

    // Bicicleta
    if (strpos($texto, "bicicleta") !== false || strpos($texto, "bici") !== false || strpos($texto, "gw") !== false)
        return "https://gwmotos.com.co/wp-content/uploads/2022/07/ALTUS-700-GRIS.png";

    // Viaje / Turismo
    if (strpos($texto, "viaje") !== false || strpos($texto, "cartagena") !== false || strpos($texto, "turistico") !== false)
        return "https://upload.wikimedia.org/wikipedia/commons/thumb/f/f3/Cartagena_de_Indias_-_Vista_desde_el_Castillo_de_San_Felipe.jpg/1280px-Cartagena_de_Indias_-_Vista_desde_el_Castillo_de_San_Felipe.jpg";

    // Drone
    if (strpos($texto, "drone") !== false || strpos($texto, "dji") !== false)
        return "https://store.dji.com/cdn/content/mini-3-pro/2022-05/mini-3-pro-product-1.png";

    // Audífonos Sony
    if (strpos($texto, "wh-1000") !== false || strpos($texto, "wh1000") !== false || (strpos($texto, "audifonos") !== false && strpos($texto, "sony") !== false))
        return "https://m.media-amazon.com/images/I/61LMCiUC9ML._AC_SL1500_.jpg";

    // Parlante JBL
    if (strpos($texto, "jbl") !== false || strpos($texto, "parlante") !== false)
        return "https://m.media-amazon.com/images/I/71AiKNxkSBL._AC_SL1500_.jpg";

    // Barra de sonido LG
    if ((strpos($texto, "barra") !== false && strpos($texto, "sonido") !== false) || strpos($texto, "soundbar") !== false)
        return "https://m.media-amazon.com/images/I/71DtyBbNIoL._AC_SL1500_.jpg";

    // Monitor gamer
    if (strpos($texto, "monitor") !== false || strpos($texto, "acer") !== false)
        return "https://m.media-amazon.com/images/I/81XVQLN1hSL._AC_SL1500_.jpg";

    // Teclado mecánico / gamer
    if (strpos($texto, "teclado") !== false || strpos($texto, "redragon") !== false || strpos($texto, "logitech g") !== false)
        return "https://m.media-amazon.com/images/I/71aTH4FOiNL._AC_SL1500_.jpg";

    // Kit gamer (combo mouse teclado)
    if (strpos($texto, "gaming setup") !== false || strpos($texto, "combo gamer") !== false)
        return "https://m.media-amazon.com/images/I/71eSC1VJHSL._AC_SL1500_.jpg";

    // Cámara web
    if (strpos($texto, "camara web") !== false || strpos($texto, "logitech c920") !== false || strpos($texto, "webcam") !== false)
        return "https://m.media-amazon.com/images/I/71sVFdJjCBL._AC_SL1500_.jpg";

    // Cafetera Nespresso
    if (strpos($texto, "nespresso") !== false || strpos($texto, "cafetera") !== false)
        return "https://m.media-amazon.com/images/I/51eQYE5cTxL._AC_SL1500_.jpg";

    // Freidora de aire
    if (strpos($texto, "freidora") !== false || strpos($texto, "airfryer") !== false || strpos($texto, "kalley") !== false)
        return "https://m.media-amazon.com/images/I/711PdWaMwOL._AC_SL1500_.jpg";

    // Microondas
    if (strpos($texto, "microondas") !== false)
        return "https://m.media-amazon.com/images/I/61JWY0RWDOL._AC_SL1500_.jpg";

    // Perfume / Paco Rabanne
    if (strpos($texto, "perfume") !== false || strpos($texto, "paco rabanne") !== false)
        return "https://m.media-amazon.com/images/I/61Rua7CRRFL._AC_SL1200_.jpg";

    // Kit aseo / personal
    if (strpos($texto, "aseo") !== false)
        return "https://m.media-amazon.com/images/I/71u4kGRb+EL._AC_SL1500_.jpg";

    // Cocina / ollas
    if (strpos($texto, "cocina") !== false || strpos($texto, "tefal") !== false || strpos($texto, "ollas") !== false)
        return "https://m.media-amazon.com/images/I/71tOIDIaDqL._AC_SL1500_.jpg";

    // Kit de contenido / luces microfono
    if (strpos($texto, "contenido") !== false || strpos($texto, "kit estudio") !== false || strpos($texto, "microfono") !== false)
        return "https://m.media-amazon.com/images/I/71hkNzYPOcL._AC_SL1500_.jpg";

    // Silla de oficina
    if (strpos($texto, "silla") !== false && strpos($texto, "oficina") !== false)
        return "https://m.media-amazon.com/images/I/71qvVd7KOHL._AC_SL1500_.jpg";

    // Dinero / efectivo
    if (strpos($texto, "efectivo") !== false || strpos($texto, "dinero") !== false || strpos($texto, "pesos") !== false || strpos($texto, "cop") !== false)
        return "https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/Pesos_colombianos.jpg/1280px-Pesos_colombianos.jpg";

    // Default genérico trofeo
    return "https://cdn-icons-png.flaticon.com/512/3112/3112946.png";
}
?>
