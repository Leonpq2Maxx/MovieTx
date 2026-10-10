<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/Mostrar-Mas/funciones-peliculas-y-series-agregados.php';
require_once __DIR__ . '/funciones_catalogo.php';

/* Esta página usa solo los datos del carrusel para alimentar el hero nuevo. */
define('MOVIETX_HERO_ONLY', true);
require_once __DIR__ . '/Funcion-carrusel.php';

/* =========================================================
   DATOS DEL HERO/CARRUSEL NUEVO CONECTADOS A Funcion-carrusel.php
   - NO imprime el carrusel viejo.
   - Solo toma sus datos (imagen, título, id, tipo y enlace)
     para alimentar el carrusel/hero visual nuevo.
========================================================= */
function construirHeroCarrusel($dispositivo = 'auto')
{
    $sets = [];

    foreach (['inicio', 'peliculas', 'series', 'adulto'] as $menuHero) {
        $sets[$menuHero] = [];

        foreach (obtenerCarrusel($menuHero, $dispositivo) as $itemHero) {
            $metaHero = obtenerMetadatosCarrusel($itemHero);

            $sets[$menuHero][] = [
                (string)($itemHero['titulo'] ?? 'Contenido'),
                $metaHero['anio'] !== '' ? $metaHero['anio'] : '2026',
                $metaHero['genero'] !== '' ? $metaHero['genero'] : 'Acción',
                $metaHero['descripcion'] !== '' ? $metaHero['descripcion'] : 'Una nueva historia te espera. Descubre el contenido destacado y comienza a disfrutarlo.',
                (string)($itemHero['imagen'] ?? ''),
                (string)($itemHero['id'] ?? ''),
                obtenerEnlaceCarrusel($itemHero),
                (string)($itemHero['tipo'] ?? 'pelicula'),
                $metaHero['etiqueta'],
                $metaHero['calidad'] !== '' ? $metaHero['calidad'] : 'HD'
            ];
        }
    }

    return $sets;
}

$carruselHeroDispositivo = normalizarDispositivoCarrusel('auto');
$carruselHeroSets = construirHeroCarrusel($carruselHeroDispositivo);

/* =========================================================
   ACTUALIZACIÓN EN VIVO DEL HERO/CARRUSEL NUEVO
   - Relee Carrusel.php para PC/tablet.
   - Relee Carrusel-Android-Iphone.php para Android/iPhone.
   - No recarga la página completa.
========================================================= */
if (
    (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['actualizar_carrusel_hero']))
    || (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['actualizar_carrusel_hero']))
) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');

    $dispositivoHero = normalizarDispositivoCarrusel(
        $_POST['dispositivo'] ?? $_GET['dispositivo'] ?? 'auto'
    );

    $setsHeroActualizados = construirHeroCarrusel($dispositivoHero);

    echo json_encode([
        'success' => true,
        'dispositivo' => $dispositivoHero,
        'sets' => $setsHeroActualizados,
        'version' => md5(
            json_encode($setsHeroActualizados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '|' . obtenerVersionArchivoCarrusel($dispositivoHero)
        )
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit();
}

/* =========================================================
   ACTUALIZACIÓN PARCIAL DEL CATÁLOGO
   - No recarga la página completa.
   - Lee contenido.php nuevamente en cada petición.
   - Devuelve solamente las filas de tarjetas de Inicio.
========================================================= */

/* =========================================================
   ACTUALIZACIÓN PARCIAL DE MENÚS DINÁMICOS
   ========================================================= */
function renderCatalogoMenuActualizado($menu)
{
    $menu = strtolower(trim((string)$menu));
    $mapa = [
        'peliculas' => [
            ['Hoy', 'hoy', '', 10],
            ['Animales', 'animales', '', 10],
            ['Acción', 'accion', 'Mostrar-Mas/Accion.php', 10],
            ['Aventura', 'aventura', '', 10],
            ['Animación', 'animacion', 'Mostrar-Mas/Animacion.php', 10],
            ['Anime', 'anime', 'Mostrar-Mas/Anime.php', 10],
            ['Artes-Marciales', 'artes_marciales', '', 10],
            ['Biblia', 'biblia', '', 10],
            ['Comedia', 'comedia', 'Mostrar-Mas/Comedia.php', 10],
            ['Crimen', 'crimen', 'Mostrar-Mas/Crimen.php', 10],
            ['Deporte', 'deporte', '', 10],
            ['Disney', 'disney', 'Mostrar-Mas/Disney.php', 10],
            ['Drama', 'drama', 'Mostrar-Mas/Drama.php', 10],
            ['Guerra', 'guerra', '', 10],
            ['Marvel', 'marvel', 'Mostrar-Mas/Marvel.php', 10],
            ['Musical', 'musical', '', 10],
            ['Princesas', 'princesas', 'Mostrar-Mas/Princesas.php', 10],
            ['Romance', 'romance', 'Mostrar-Mas/Romance.php', 10],
            ['Suspenso', 'suspenso', '', 10],
            ['Terror', 'terror', 'Mostrar-Mas/Terror.php', 10],
        ],
        'series' => [
            ['Series Completas', 'series_completas', 'Mostras Mas/Series.php', 10],
            ['Pronto', 'Pronto', '', 30],
            ['Pronto', 'Pronto_serie', '', 30],
            ['Anime', 'serie_anime', '', 10],
            ['Acción', 'accion', '', 20],
            ['Aventura', 'aventura', '', 20],
            ['Animación', 'animacion', '', 20],
            ['Biblia', 'biblia', '', 10],
            ['Crimen', 'crimen', '', 10],
            ['Disney', 'disney', 'Mostras Mas/Agregado hoy.php', 20],
            ['Drama', 'drama', '', 20],
            ['Marvel', 'marvel', '', 20],
            ['Misterio', 'misterio', 'Mostras Mas/Agregado hoy.php', 10],
            ['Romance', 'romance', 'Mostras Mas/Agregado hoy.php', 20],
            ['Terror', 'terror', 'Mostras Mas/Agregado hoy.php', 20],
        ],
        'trailers' => [
            ['2026', '2026', 'Mostras Mas/Agregado hoy.php', 10],
            ['Agregados hoy', 'agregados_hoy', 'Mostras Mas/Agregado hoy.php', 10],
            ['Anime', 'anime', '', 10],
            ['Animación', 'animacion_2026', 'Mostras Mas/Agregado hoy.php', 10],
            ['Series Completas', 'series_completas', 'Mostras Mas/Series.php', 10],
            ['Pronto', 'pronto', '', 100],
            ['Terror', 'terror', '', 100],
            ['Trailer', 'trailer', 'Mostras Mas/Trailers.php', 15],
        ],
        'adulto' => [
            ['Musical', 'musical', 'Mostras Mas/Agregado hoy.php', 10],
            ['Agregados hoy', 'agregados_hoy', 'Mostras Mas/Agregado hoy.php', 10],
            ['Anime', 'anime', '', 10],
            ['Animación', 'animacion_2026', 'Mostras Mas/Agregado hoy.php', 10],
            ['Series Completas', 'series_completas', 'Mostras Mas/Series.php', 10],
            ['Pronto', 'pronto', '', 100],
            ['Terror', 'terror', '', 100],
            ['Trailer', 'trailer', 'Mostras Mas/Trailers.php', 15],
        ],
    ];
    if (!isset($mapa[$menu])) return '';
    global $adultoActivo, $esPerfilKids;
    if ($menu === 'adulto' && (!$adultoActivo || $esPerfilKids)) return '';
    $menuActual = $menu;
    ob_start();
    foreach ($mapa[$menu] as $fila) {
        if ($menu === 'peliculas' && !$esPerfilKids && in_array($fila[1], ['animales','princesas'], true)) continue;
        echo crearFilaCategoria($fila[0], $fila[1], $fila[2], $fila[3], $menuActual);
    }
    return ob_get_clean();
}

function renderCatalogoInicioActualizado()
{
    return renderFilasInicioCatalogoModerno();
}

/*
|--------------------------------------------------------------------------
| TARJETAS DEL CATÁLOGO PARA EL DISEÑO NUEVO
|--------------------------------------------------------------------------
|
| El archivo inicio Nuevo corregido usa un diseño distinto al HTML que
| genera crearFilaCatalogo(). Para no modificar funciones_catalogo.php
| ni romper otros archivos que ya dependen de ella, aquí reutilizamos
| sus funciones de datos y de tarjetas y solamente adaptamos el
| contenedor al diseño de esta página.
|
| IMPORTANTE:
| - Las imágenes salen exclusivamente de contenido.php.
| - Se respetan Kids/adulto/perfil mediante funciones_catalogo.php.
| - Se respetan los menús y categorías reales del catálogo.
*/
function renderTarjetasCatalogoModerno($categoria, $menu = null, $cantidad = 10, $modo = 'categoria')
{
    if ($modo === 'menu') {
        $items = obtenerPorMenu($categoria);
    } else {
        $items = obtenerPorCategoria($categoria, $menu);
    }

    if (!is_array($items) || empty($items)) {
        return '';
    }

    $html = '';

    foreach (array_slice($items, 0, (int)$cantidad) as $item) {
        $html .= crearTarjetaCatalogo($item);
    }

    return $html;
}

/*
|--------------------------------------------------------------------------
| FILAS DE INICIO
|--------------------------------------------------------------------------
|
| No usamos las imágenes de ejemplo que estaban en const data.
| Cada fila se alimenta directamente de contenido.php.
*/
function renderFilasInicioCatalogoModerno()
{
    ob_start();

    /*
     * MISMAS FILAS Y MISMO COMPORTAMIENTO DE "VER TODO" QUE inicio.php.
     * En inicio.php solamente "Agregados hoy" tiene enlace de Ver Todo.
     */
    $filas = [
        ['Tendencia', 'tendencia', 'inicio', 10, ''],
        ['Populares', 'tendencia', 'inicio', 10, '', 'categoria'],
        ['2026', '2026', 'inicio', 10, ''],
        ['Agregados hoy', 'agregados_hoy', 'inicio', 13, 'Mostras Mas/Agregado hoy.php'],
        ['Próximamente', 'pronto', 'inicio', 100, ''],
        ['Animación', 'animacion_2026', 'inicio', 10, ''],
        ['Anime', 'anime', 'inicio', 10, ''],
        ['Serie Anime', 'serie_anime', 'inicio', 10, ''],
        ['Series Completas', 'series_completas', 'inicio', 10, ''],
        ['Trailer', 'trailer', 'inicio', 15, ''],
    ];

    foreach ($filas as $fila) {
        $modoFila = $fila[5] ?? 'categoria';
        $cards = renderTarjetasCatalogoModerno($fila[1], $fila[2], $fila[3], $modoFila);

        if ($cards === '') {
            continue;
        }

        $tituloFila = htmlspecialchars($fila[0], ENT_QUOTES, 'UTF-8');
        $verTodo = trim((string)($fila[4] ?? ''));

        echo '<section class="section catalogo-fila-dinamica">';
        echo '<div class="section-head"><h2>' . $tituloFila . '</h2>';

        if ($verTodo !== '') {
            echo '<a class="view-all" href="' . htmlspecialchars($verTodo, ENT_QUOTES, 'UTF-8') . '">Ver todo</a>';
        }

        echo '</div>';
        echo '<div class="media-row premium-scroll category-row catalogo-row-modern">';
        echo $cards;
        echo '</div>';
        echo '</section>';
    }

    return ob_get_clean();
}

/*
|--------------------------------------------------------------------------
| FILAS DE PELÍCULAS
|--------------------------------------------------------------------------
*/
function renderFilasPeliculasCatalogoModerno()
{
    global $esPerfilKids;

    /*
     * "Ver todo" funciona igual que en inicio.php:
     * si la categoría tiene enlace, se muestra; si el enlace es vacío,
     * no se muestra el botón.
     */
    $filas = [
        ['Hoy', 'hoy', '', 10],
        ['Animales', 'animales', '', 10],
        ['Acción', 'accion', 'Mostrar-Mas/Accion.php', 10],
        ['Aventura', 'aventura', '', 10],
        ['Animación', 'animacion', 'Mostrar-Mas/Animacion.php', 10],
        ['Anime', 'anime', 'Mostrar-Mas/Anime.php', 10],
        ['Artes-Marciales', 'artes_marciales', '', 10],
        ['Biblia', 'biblia', '', 10],
        ['Comedia', 'comedia', 'Mostrar-Mas/Comedia.php', 10],
        ['Crimen', 'crimen', 'Mostrar-Mas/Crimen.php', 10],
        ['Deporte', 'deporte', '', 10],
        ['Disney', 'disney', 'Mostrar-Mas/Disney.php', 10],
        ['Drama', 'drama', 'Mostrar-Mas/Drama.php', 10],
        ['Guerra', 'guerra', '', 10],
        ['Marvel', 'marvel', 'Mostrar-Mas/Marvel.php', 10],
        ['Musical', 'musical', '', 10],
        ['Princesas', 'princesas', 'Mostrar-Mas/Princesas.php', 10],
        ['Romance', 'romance', 'Mostrar-Mas/Romance.php', 10],
        ['Suspenso', 'suspenso', '', 10],
        ['Terror', 'terror', 'Mostrar-Mas/Terror.php', 10],
    ];

    if ($esPerfilKids) {
        // Las mismas categorías especiales para Kids que ya tenía esta vista.
        // Los enlaces se conservan exactamente como en inicio.php.
    } else {
        $filas = array_values(array_filter(
            $filas,
            static fn($fila) => !in_array($fila[1], ['animales', 'princesas'], true)
        ));
    }

    ob_start();

    foreach ($filas as $fila) {
        $cards = renderTarjetasCatalogoModerno($fila[1], 'peliculas', $fila[3]);
        if ($cards === '') {
            continue;
        }

        $titulo = htmlspecialchars($fila[0], ENT_QUOTES, 'UTF-8');
        $enlace = trim((string)$fila[2]);

        echo '<section class="section catalogo-fila-dinamica">';
        echo '<div class="section-head"><h2>' . $titulo . '</h2>';

        if ($enlace !== '') {
            echo '<a class="view-all" href="' . htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') . '">Ver todo</a>';
        }

        echo '</div>';
        echo '<div class="media-row premium-scroll category-row catalogo-row-modern">' . $cards . '</div>';
        echo '</section>';
    }

    return ob_get_clean();
}

function renderFilasAdultoCatalogoModerno()
{
    global $adultoActivo, $esPerfilKids;
    if (!$adultoActivo || $esPerfilKids) {
        return '';
    }

    $filas = [
        ['Musical', 'musical', 'Mostras Mas/Agregado hoy.php', 20],
        ['Agregados hoy', 'agregados_hoy', 'Mostras Mas/Agregado hoy.php', 20],
        ['Anime', 'anime', '', 20],
        ['Animación', 'animacion_2026', 'Mostras Mas/Agregado hoy.php', 20],
        ['Series Completas', 'series_completas', 'Mostras Mas/Series.php', 20],
        ['Próximamente', 'pronto', '', 30],
        ['Terror', 'terror', '', 20],
        ['Trailer', 'trailer', 'Mostras Mas/Trailers.php', 15],
    ];

    ob_start();

    foreach ($filas as $fila) {
        $cards = renderTarjetasCatalogoModerno($fila[1], 'adulto', $fila[3]);
        if ($cards === '') {
            continue;
        }

        $titulo = htmlspecialchars($fila[0], ENT_QUOTES, 'UTF-8');
        $enlace = trim((string)$fila[2]);

        echo '<section class="section catalogo-fila-dinamica">';
        echo '<div class="section-head"><h2>' . $titulo . '</h2>';

        if ($enlace !== '') {
            echo '<a class="view-all" href="' . htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') . '">Ver todo</a>';
        }

        echo '</div>';
        echo '<div class="media-row premium-scroll category-row catalogo-row-modern">' . $cards . '</div>';
        echo '</section>';
    }

    return ob_get_clean();
}

/*
|--------------------------------------------------------------------------
| FILAS DE SERIES
|--------------------------------------------------------------------------
*/
function renderFilasSeriesCatalogoModerno()
{
    $filas = [
        ['Series Completas', 'series_completas', 'Mostras Mas/Series.php', 10],
        ['Pronto', 'Pronto', '', 30],
        ['Pronto', 'Pronto_serie', '', 30],
        ['Anime', 'serie_anime', '', 10],
        ['Acción', 'accion', '', 20],
        ['Aventura', 'aventura', '', 20],
        ['Animación', 'animacion', '', 20],
        ['Biblia', 'biblia', '', 10],
        ['Crimen', 'crimen', '', 10],
        ['Disney', 'disney', 'Mostras Mas/Agregado hoy.php', 20],
        ['Drama', 'drama', '', 20],
        ['Marvel', 'marvel', '', 20],
        ['Misterio', 'misterio', 'Mostras Mas/Agregado hoy.php', 10],
        ['Romance', 'romance', 'Mostras Mas/Agregado hoy.php', 20],
        ['Terror', 'terror', 'Mostras Mas/Agregado hoy.php', 20],
    ];

    ob_start();

    foreach ($filas as $fila) {
        $cards = renderTarjetasCatalogoModerno($fila[1], 'series', $fila[3]);

        if ($cards === '') {
            continue;
        }

        $titulo = htmlspecialchars($fila[0], ENT_QUOTES, 'UTF-8');
        $enlace = trim((string)$fila[2]);

        echo '<section class="section catalogo-fila-dinamica">';
        echo '<div class="section-head"><h2>' . $titulo . '</h2>';

        if ($enlace !== '') {
            echo '<a class="view-all" href="' . htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') . '">Ver todo</a>';
        }

        echo '</div>';
        echo '<div class="media-row premium-scroll category-row catalogo-row-modern">' . $cards . '</div>';
        echo '</section>';
    }

    return ob_get_clean();
}

/*
|--------------------------------------------------------------------------
| CONTENIDO ADULTO
|--------------------------------------------------------------------------
*/
function renderFilaAdultoCatalogoModerno(){ return renderFilasAdultoCatalogoModerno(); }



/* =========================
   VALIDAR SESIÓN
========================= */

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$userId = (int) $_SESSION['id'];


/*
|--------------------------------------------------------------------------
| AVISO CENTRAL DE "ESTAMOS REALIZANDO MODIFICACIONES"
|--------------------------------------------------------------------------
| El cartel ya no usa un texto/localStorage fijo. Lee el aviso creado
| desde Dashboard > Notificar y respeta activo, usuario, páginas y fecha.
*/
$maintenanceNotice = null;

$conn->query("
    CREATE TABLE IF NOT EXISTS movietx_admin_avisos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        admin_id INT UNSIGNED NOT NULL,
        usuario_id INT UNSIGNED NULL,
        paginas TEXT NOT NULL,
        tipo VARCHAR(30) NOT NULL DEFAULT 'manual',
        titulo VARCHAR(190) NULL,
        mensaje TEXT NULL,
        creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expira_at DATETIME NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_admin_aviso_usuario (admin_id, usuario_id, activo),
        KEY idx_admin_aviso_expira (expira_at, activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$paginaActualAviso = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: $_SERVER['PHP_SELF']);
if ($paginaActualAviso === '') {
    $paginaActualAviso = 'inicio.php';
}

$stmtMaintenance = $conn->prepare("
    SELECT id, titulo, mensaje, paginas, expira_at
    FROM movietx_admin_avisos
    WHERE usuario_id=?
      AND tipo='admin_mantenimiento'
      AND activo=1
      AND (expira_at IS NULL OR expira_at > NOW())
    ORDER BY creado_at DESC, id DESC
    LIMIT 20
");

if ($stmtMaintenance) {
    $stmtMaintenance->bind_param("i", $userId);
    $stmtMaintenance->execute();
    $resMaintenance = $stmtMaintenance->get_result();

    while ($rowMaintenance = $resMaintenance->fetch_assoc()) {
        $paginasMaintenance = json_decode((string)($rowMaintenance['paginas'] ?? ''), true);

        if (!is_array($paginasMaintenance)) {
            continue;
        }

        $paginasMaintenance = array_map(
            static fn($pagina) => strtolower(trim((string)$pagina)),
            $paginasMaintenance
        );

        if (
            in_array(strtolower($paginaActualAviso), $paginasMaintenance, true) ||
            in_array('todos.php', $paginasMaintenance, true) ||
            in_array('*', $paginasMaintenance, true)
        ) {
            $maintenanceNotice = [
                'id' => (int)$rowMaintenance['id'],
                'titulo' => trim((string)($rowMaintenance['titulo'] ?? '')),
                'mensaje' => trim((string)($rowMaintenance['mensaje'] ?? ''))
            ];
            break;
        }
    }

    $stmtMaintenance->close();
}

if ($maintenanceNotice) {
    if ($maintenanceNotice['titulo'] === '') {
        $maintenanceNotice['titulo'] = 'Estamos realizando modificaciones';
    }

    if ($maintenanceNotice['mensaje'] === '') {
        $maintenanceNotice['mensaje'] =
            'Estamos realizando algunas modificaciones en MovieTx. El sitio puede presentar cambios o interrupciones temporales mientras trabajamos para mejorar la plataforma.';
    }
}

/* =========================
   OBTENER USUARIO COMPLETO
========================= */

$stmt = $conn->prepare("SELECT id, name, email, foto, status, paid_until, theme, plan, precio FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$theme = trim((string)($user['theme'] ?? ''));
$allowedThemes = ['light', 'dark', 'blue', 'sky', 'red', 'pink'];

if (!in_array($theme, $allowedThemes, true)) {
    $theme = 'light';
}

// 🚨 Si el usuario NO existe en base
if (!$user) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

/* =========================================================
   DESTINATARIO DE NOTIFICACIONES
   - Usuario principal: perfil_id = NULL
   - Perfil normal: solo películas nuevas
   - Perfil Kids: solo contenido marcado kids
========================================================= */
$perfilActivo = isset($_SESSION['perfil_id']) && (int)$_SESSION['perfil_id'] > 0;
$perfilIdNotificaciones = $perfilActivo ? (int)$_SESSION['perfil_id'] : null;
$tipoPerfilNotificaciones = $perfilActivo && function_exists('peliculaObtenerTipoPerfil')
    ? peliculaObtenerTipoPerfil()
    : 'normal';
$esUsuarioPrincipalNotificaciones = !$perfilActivo;

/* Estado de Adulto disponible desde el principio porque los endpoints
   de actualización parcial del catálogo se ejecutan antes del HTML. */
$esPerfilKids = (($tipoPerfilNotificaciones ?? 'normal') === 'kids');
$esUsuarioPrincipal = !$perfilActivo;
$adultoActivo = false;
if ($esUsuarioPrincipal) {
    $stmtAdultoInicial = $conn->prepare("SELECT activo FROM adultos WHERE email=? LIMIT 1");
    if ($stmtAdultoInicial) {
        $stmtAdultoInicial->bind_param('s', $user['email']);
        $stmtAdultoInicial->execute();
        $adultoDataInicial = $stmtAdultoInicial->get_result()->fetch_assoc();
        $adultoActivo = $adultoDataInicial && (int)$adultoDataInicial['activo'] === 1;
        $stmtAdultoInicial->close();
    }
} elseif ($perfilIdNotificaciones) {
    $stmtAdultoInicial = $conn->prepare("SELECT activo FROM perfiles_adultos WHERE user_id=? AND perfil_id=? LIMIT 1");
    if ($stmtAdultoInicial) {
        $stmtAdultoInicial->bind_param('ii', $userId, $perfilIdNotificaciones);
        $stmtAdultoInicial->execute();
        $adultoDataInicial = $stmtAdultoInicial->get_result()->fetch_assoc();
        $adultoActivo = $adultoDataInicial && (int)$adultoDataInicial['activo'] === 1;
        $stmtAdultoInicial->close();
    }
}

/* =========================
   VALIDAR ESTADO
========================= */

if ($user['status'] !== 'active') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_catalogo_inicio'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $htmlCatalogo = renderCatalogoInicioActualizado();

    echo json_encode([
        'success' => true,
        'html' => $htmlCatalogo,
        'version' => md5($htmlCatalogo)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_catalogo_menu'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $menuSolicitado = strtolower(trim((string)($_POST['menu'] ?? '')));
    $menusPermitidos = ['peliculas','series','trailers','adulto'];
    if ($menuSolicitado === 'adulto' && (!$adultoActivo || $esPerfilKids)) { echo json_encode(['success'=>false,'message'=>'Contenido adulto no disponible para este perfil.'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit(); }
    if (!in_array($menuSolicitado, $menusPermitidos, true)) {
        echo json_encode(['success'=>false,'message'=>'Menú no válido.'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit();
    }
    $htmlCatalogo = renderCatalogoMenuActualizado($menuSolicitado);
    echo json_encode(['success'=>true,'menu'=>$menuSolicitado,'html'=>$htmlCatalogo,'version'=>md5($htmlCatalogo)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit();
}

/* =========================================================
   NOTIFICACIONES DEL USUARIO
========================================================= */
$conn->query("CREATE TABLE IF NOT EXISTS movietx_user_notificaciones (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(40) NOT NULL DEFAULT 'general',
    titulo VARCHAR(190) NOT NULL,
    mensaje TEXT NOT NULL,
    creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    referencia_fecha DATETIME NULL,
    contenido_id VARCHAR(190) NULL,
    imagen TEXT NULL,
    genero VARCHAR(500) NULL,
    enlace TEXT NULL,
    tipo_contenido VARCHAR(30) NULL,
    expira_at DATETIME NULL,
    oculta TINYINT(1) NOT NULL DEFAULT 0,
    perfil_id INT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user_notif (user_id, leida, creado_at),
    KEY idx_contenido_notif (user_id, tipo, contenido_id),
    KEY idx_expira_notif (tipo, expira_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$notificationColumns = [
    'referencia_fecha' => 'DATETIME NULL',
    'contenido_id' => 'VARCHAR(190) NULL',
    'imagen' => 'TEXT NULL',
    'genero' => 'VARCHAR(500) NULL',
    'enlace' => 'TEXT NULL',
    'tipo_contenido' => 'VARCHAR(30) NULL',
    'expira_at' => 'DATETIME NULL',
    'oculta' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'perfil_id' => 'INT UNSIGNED NULL DEFAULT NULL'
];
foreach ($notificationColumns as $column => $definition) {
    $safeColumn = $conn->real_escape_string($column);
    $colCheck = $conn->query("SHOW COLUMNS FROM movietx_user_notificaciones LIKE '{$safeColumn}'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE movietx_user_notificaciones ADD COLUMN {$column} {$definition}");
    }
    if ($colCheck) $colCheck->free();
}

// Eliminar físicamente las notificaciones con más de 2 días, excepto Bienvenido.
$conn->query("DELETE FROM movietx_user_notificaciones WHERE oculta=0 AND (LOWER(TRIM(tipo)) <> 'bienvenido' AND LOWER(TRIM(titulo)) NOT LIKE 'bienvenido%') AND creado_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
$conn->query("DELETE FROM movietx_user_notificaciones WHERE oculta=0 AND tipo='contenido_nuevo' AND expira_at IS NOT NULL AND expira_at <= NOW()");

$conn->query("DELETE FROM movietx_user_notificaciones WHERE oculta=1 AND creado_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");

// Detectar contenido agregado recientemente y crear la notificación para este usuario.
sincronizarNotificacionesContenidoNuevo($conn, $userId);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_user_notifications'])) {
    if ($esUsuarioPrincipalNotificaciones) generarAvisosVencimientoUsuario($conn, $userId, $user);
    header('Content-Type: application/json; charset=utf-8');
    $itemsNotif=[]; $unreadNotif=0;
    $scopeSql=$perfilActivo ? "perfil_id=?" : "perfil_id IS NULL";
    $st=$conn->prepare("SELECT id,tipo,titulo,mensaje,creado_at,leida,contenido_id,imagen,genero,enlace,tipo_contenido,expira_at FROM movietx_user_notificaciones WHERE user_id=? AND {$scopeSql} AND oculta=0 ORDER BY creado_at DESC,id DESC LIMIT 50");
    if($st){
        if($perfilActivo)$st->bind_param('ii',$userId,$perfilIdNotificaciones);else$st->bind_param('i',$userId);
        $st->execute();$res=$st->get_result();
        while($n=$res->fetch_assoc()){
            $leida=(int)($n['leida']??0);if($leida===0)$unreadNotif++;
            $itemsNotif[]=['id'=>(int)$n['id'],'tipo'=>(string)$n['tipo'],'titulo'=>(string)$n['titulo'],'mensaje'=>(string)$n['mensaje'],'creado_at'=>(string)$n['creado_at'],'leida'=>$leida,'contenido_id'=>(string)($n['contenido_id']??''),'imagen'=>(string)($n['imagen']??''),'genero'=>(string)($n['genero']??''),'enlace'=>(string)($n['enlace']??''),'tipo_contenido'=>(string)($n['tipo_contenido']??''),'expira_at'=>(string)($n['expira_at']??'')];
        }$st->close();
    }
    echo json_encode(['success'=>true,'unread'=>$unreadNotif,'items'=>$itemsNotif],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_user_notification_read'])) {
    $id=(int)($_POST['notification_id']??0);if($id>0){$scopeSql=$perfilActivo?"perfil_id=?":"perfil_id IS NULL";$st=$conn->prepare("UPDATE movietx_user_notificaciones SET leida=1 WHERE id=? AND user_id=? AND {$scopeSql} LIMIT 1");if($st){if($perfilActivo)$st->bind_param('iii',$id,$userId,$perfilIdNotificaciones);else$st->bind_param('ii',$id,$userId);$st->execute();$st->close();}}http_response_code(204);exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_user_notifications_read'])) {
    $scopeSql=$perfilActivo?"perfil_id=?":"perfil_id IS NULL";$st=$conn->prepare("UPDATE movietx_user_notificaciones SET leida=1 WHERE user_id=? AND {$scopeSql} AND leida=0 AND oculta=0");if($st){if($perfilActivo)$st->bind_param('ii',$userId,$perfilIdNotificaciones);else$st->bind_param('i',$userId);$st->execute();$st->close();}http_response_code(204);exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_notifications'])) {
    $ids=$_POST['notification_ids']??[];if(!is_array($ids))$ids=[$ids];$ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>$id>0)));header('Content-Type: application/json; charset=utf-8');if(!$ids){echo json_encode(['success'=>false,'message'=>'Selecciona al menos una notificación.']);exit();}
    $ph=implode(',',array_fill(0,count($ids),'?'));$scopeSql=$perfilActivo?"perfil_id=?":"perfil_id IS NULL";$st=$conn->prepare("UPDATE movietx_user_notificaciones SET oculta=1 WHERE user_id=? AND {$scopeSql} AND leida=1 AND id IN ($ph)");if(!$st){echo json_encode(['success'=>false,'message'=>'No se pudieron eliminar las notificaciones.']);exit();}
    if($perfilActivo){$vals=array_merge([$userId,$perfilIdNotificaciones],$ids);$types='ii'.str_repeat('i',count($ids));}else{$vals=array_merge([$userId],$ids);$types='i'.str_repeat('i',count($ids));}$refs=[$types];foreach($vals as $k=>$v)$refs[]=&$vals[$k];call_user_func_array([$st,'bind_param'],$refs);$ok=$st->execute();$deleted=$st->affected_rows;$st->close();echo json_encode($ok?['success'=>true,'deleted'=>$deleted]:['success'=>false,'message'=>'No se pudieron eliminar las notificaciones.']);exit();
}

/* GENERAR AVISOS AUTOMÁTICOS DE VENCIMIENTO */
function generarAvisosVencimientoUsuario($conn, $userId, $user)
{
    $paidUntilAviso = $user['paid_until'] ?? null;
    if (empty($paidUntilAviso) || strtotime($paidUntilAviso) <= time()) return;
    $diasRestantesAviso = (int)ceil((strtotime($paidUntilAviso) - time()) / 86400);
    $tipoAviso = null;
    if ($diasRestantesAviso <= 7 && $diasRestantesAviso > 1) $tipoAviso = 'vencimiento_7_dias';
    elseif ($diasRestantesAviso <= 1) $tipoAviso = 'vencimiento_1_dia';
    if (!$tipoAviso) return;

    $stAviso = $conn->prepare("SELECT id FROM movietx_user_notificaciones WHERE user_id=? AND tipo=? AND referencia_fecha=? LIMIT 1");
    if (!$stAviso) return;
    $stAviso->bind_param('iss', $userId, $tipoAviso, $paidUntilAviso);
    $stAviso->execute();
    $existeAviso = $stAviso->get_result()->fetch_assoc();
    $stAviso->close();
    if ($existeAviso) return;

    $planAviso = nombrePlan($user['plan'] ?? '');
    if ($tipoAviso === 'vencimiento_7_dias') {
        $tituloAviso = 'Tu cuenta vence pronto';
        $mensajeAviso = 'Tu cuenta vence en aproximadamente una semana.' . "\n\n" .
            'Fecha de vencimiento: ' . date('d/m/Y H:i', strtotime($paidUntilAviso)) . "\n" .
            'Plan: ' . $planAviso;
    } else {
        $tituloAviso = 'Tu cuenta vence mañana';
        $mensajeAviso = 'Tu cuenta está a 1 día de vencer.' . "\n\n" .
            'Fecha de vencimiento: ' . date('d/m/Y H:i', strtotime($paidUntilAviso)) . "\n" .
            'Plan: ' . $planAviso;
    }
    $stAvisoIns = $conn->prepare("INSERT INTO movietx_user_notificaciones (user_id,tipo,titulo,mensaje,referencia_fecha) VALUES (?,?,?,?,?)");
    if ($stAvisoIns) {
        $stAvisoIns->bind_param('issss', $userId, $tipoAviso, $tituloAviso, $mensajeAviso, $paidUntilAviso);
        $stAvisoIns->execute();
        $stAvisoIns->close();
    }
}

if ($esUsuarioPrincipalNotificaciones) {
    generarAvisosVencimientoUsuario($conn, $userId, $user);
}

/* =========================================================
   ESTADO REAL DE NOTIFICACIONES PARA EL HTML INICIAL
   ---------------------------------------------------------
   No se inventan números ni se muestra el punto rojo si no
   existe una notificación pendiente en la base de datos.
========================================================= */
$notificacionesNoLeidasUsuario = 0;
$notificacionesTotalesUsuario = 0;
$scopeSqlNotificaciones = $perfilActivo ? "perfil_id=?" : "perfil_id IS NULL";

$stNotifCount = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN leida=0 THEN 1 ELSE 0 END), 0) AS no_leidas
    FROM movietx_user_notificaciones
    WHERE user_id=?
      AND {$scopeSqlNotificaciones}
      AND oculta=0
");
if ($stNotifCount) {
    if ($perfilActivo) {
        $stNotifCount->bind_param('ii', $userId, $perfilIdNotificaciones);
    } else {
        $stNotifCount->bind_param('i', $userId);
    }

    $stNotifCount->execute();
    $rowNotifCount = $stNotifCount->get_result()->fetch_assoc();

    $notificacionesTotalesUsuario = (int)($rowNotifCount['total'] ?? 0);
    $notificacionesNoLeidasUsuario = (int)($rowNotifCount['no_leidas'] ?? 0);

    $stNotifCount->close();
}

/* =========================
   🔥 VALIDAR PLAN (FIX TOTAL)
========================= */

// ❌ PLAN CANCELADO (paid_until NULL)
if (empty($user['paid_until'])) {

    $stmt = $conn->prepare("UPDATE users SET status='suspended' WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: index.php?plan=cancelado");
    exit();
}

// ❌ PLAN VENCIDO
if (strtotime($user['paid_until']) < time()) {

    $stmt = $conn->prepare("UPDATE users SET status='suspended' WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: index.php?expired=1");
    exit();
}

/*=======================
  CONTINUAR VIENDO PELÍCULAS
========================*/
/* =========================
   DETECTAR PERFIL
========================= */
$perfilActivo = isset($_SESSION['perfil_id']);
$perfilId = $_SESSION['perfil_id'] ?? null;


/* =========================
   USUARIO NORMAL (TU CÓDIGO ORIGINAL)
========================= */
$stmt_peliculas = $conn->prepare("
    SELECT pelicula_id AS id, titulo, imginicio AS imagen, progreso, fecha, 'pelicula' AS tipo
    FROM continuar_viendo 
    WHERE user_id=? 
");
$stmt_peliculas->bind_param("i", $userId);
$stmt_peliculas->execute();
$result_peliculas = $stmt_peliculas->get_result();

/* Detectar columnas opcionales de temporada/capítulo en series. */
$serieCols = [];
$colRes = $conn->query("SHOW COLUMNS FROM continuar_serie");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        $serieCols[strtolower($c['Field'])] = $c['Field'];
    }
}
$seasonCol = null;
foreach (['temporada','season','temporada_num','season_number'] as $candidate) {
    if (isset($serieCols[$candidate])) { $seasonCol = $serieCols[$candidate]; break; }
}
$episodeCol = null;
foreach (['capitulo','capítulo','episodio','episode','episode_number','numero_episodio'] as $candidate) {
    $key = strtolower($candidate);
    if (isset($serieCols[$key])) { $episodeCol = $serieCols[$key]; break; }
}
$seasonExpr = $seasonCol ? "`$seasonCol` AS temporada" : "NULL AS temporada";
$episodeExpr = $episodeCol ? "`$episodeCol` AS capitulo" : "NULL AS capitulo";

$stmt_series = $conn->prepare("
    SELECT serie_id AS id, titulo, imgserie AS imagen, progreso, fecha, $seasonExpr, $episodeExpr, 'serie' AS tipo
    FROM continuar_serie
    WHERE user_id=? 
");
$stmt_series->bind_param("i", $userId);
$stmt_series->execute();
$result_series = $stmt_series->get_result();


/* =========================
   PERFIL (NUEVO)
========================= */
if($perfilActivo){

    // 🔥 SERIES PERFIL
    /* Detectar columnas opcionales de temporada/capítulo del perfil. */
    $profileSerieCols = [];
    $colResPerfil = $conn->query("SHOW COLUMNS FROM perfiles_continuar_serie");
    if ($colResPerfil) {
        while ($c = $colResPerfil->fetch_assoc()) {
            $profileSerieCols[strtolower($c['Field'])] = $c['Field'];
        }
    }
    $profileSeasonCol = null;
    foreach (['temporada','season','temporada_num','season_number'] as $candidate) {
        if (isset($profileSerieCols[$candidate])) { $profileSeasonCol = $profileSerieCols[$candidate]; break; }
    }
    $profileEpisodeCol = null;
    foreach (['capitulo','capítulo','episodio','episode','episode_number','numero_episodio'] as $candidate) {
        $key = strtolower($candidate);
        if (isset($profileSerieCols[$key])) { $profileEpisodeCol = $profileSerieCols[$key]; break; }
    }
    $profileSeasonExpr = $profileSeasonCol ? "`$profileSeasonCol` AS temporada" : "NULL AS temporada";
    $profileEpisodeExpr = $profileEpisodeCol ? "`$profileEpisodeCol` AS capitulo" : "NULL AS capitulo";

    $stmt_series_perfil = $conn->prepare("
        SELECT serie_id AS id, titulo, imgserie AS imagen, progreso, fecha, $profileSeasonExpr, $profileEpisodeExpr, 'serie' AS tipo
        FROM perfiles_continuar_serie
        WHERE perfil_id=? 
    ");
    $stmt_series_perfil->bind_param("i", $perfilId);
    $stmt_series_perfil->execute();
    $result_series_perfil = $stmt_series_perfil->get_result();

    // 🔥 PELICULAS PERFIL (NUEVO)
    $stmt_peliculas_perfil = $conn->prepare("
        SELECT pelicula_id AS id, titulo, imginicio AS imagen, progreso, fecha, 'pelicula' AS tipo
        FROM perfiles_continuar_viendo
        WHERE perfil_id=? 
    ");
    $stmt_peliculas_perfil->bind_param("i", $perfilId);
    $stmt_peliculas_perfil->execute();
    $result_peliculas_perfil = $stmt_peliculas_perfil->get_result();

}


/* =========================
   ARRAY FINAL
========================= */
$items = [];


/* =========================
   SI ES PERFIL → SOLO PERFIL
========================= */
if($perfilActivo){

    // 🔥 SERIES
    while($row = $result_series_perfil->fetch_assoc()){
        $items[] = $row;
    }

    // 🔥 PELICULAS (NUEVO)
    while($row = $result_peliculas_perfil->fetch_assoc()){
        $items[] = $row;
    }

}else{

    // 🔥 TU LÓGICA ORIGINAL (NO TOCADA)

    // meter series
    while($row = $result_series->fetch_assoc()){
        $items[] = $row;
    }

    // meter peliculas
    while($row = $result_peliculas->fetch_assoc()){
        $items[] = $row;
    }

}


/* =========================
   SINCRONIZAR PROGRESO REAL DE SERIES
   user_progress / user_progress_perfil es la fuente exacta
   de tiempo, temporada y episodio guardados por el reproductor.
========================= */
$progressMap = [];

if ($perfilActivo) {
    $stmtProgress = $conn->prepare("
        SELECT movie_id, temporada, episodio, tiempo, updated_at
        FROM user_progress_perfil
        WHERE perfil_id=?
        ORDER BY updated_at DESC
    ");
    if ($stmtProgress) {
        $stmtProgress->bind_param("i", $perfilId);
        $stmtProgress->execute();
        $resProgress = $stmtProgress->get_result();
        while ($p = $resProgress->fetch_assoc()) {
            $key = (string)$p['movie_id'];
            // Al estar ordenado de más reciente a más antiguo,
            // conservamos solamente el último progreso de cada serie.
            if (!isset($progressMap[$key])) {
                $progressMap[$key] = $p;
            }
        }
        $stmtProgress->close();
    }
} else {
    $userEmailForProgress = $user['email'] ?? '';
    if ($userEmailForProgress !== '') {
        $stmtProgress = $conn->prepare("
            SELECT movie_id, temporada, episodio, tiempo, update_at
            FROM user_progress
            WHERE email=?
            ORDER BY update_at DESC
        ");
        if ($stmtProgress) {
            $stmtProgress->bind_param("s", $userEmailForProgress);
            $stmtProgress->execute();
            $resProgress = $stmtProgress->get_result();
            while ($p = $resProgress->fetch_assoc()) {
                $key = (string)$p['movie_id'];
                if (!isset($progressMap[$key])) {
                    $progressMap[$key] = $p;
                }
            }
            $stmtProgress->close();
        }
    }
}

foreach ($items as &$item) {
    if (($item['tipo'] ?? '') !== 'serie') {
        continue;
    }

    $key = (string)($item['id'] ?? '');
    if ($key === '' || !isset($progressMap[$key])) {
        continue;
    }

    $realProgress = $progressMap[$key];

    // tiempo está guardado en segundos por el reproductor.
    if (isset($realProgress['tiempo']) && is_numeric($realProgress['tiempo'])) {
        $item['progreso'] = (float)$realProgress['tiempo'];
    }

    /*
     * El reproductor guarda el episodio completo en formato "t1e1".
     * Se conserva como texto: convertirlo a entero lo transforma en 0
     * y hace que el episodio mostrado sea incorrecto.
     * Para "Continuar viendo" usamos únicamente episodio.
     */
    $episodioReal = trim((string)($realProgress['episodio'] ?? ''));

    if ($episodioReal !== '') {
        $item['capitulo'] = $episodioReal;
    }
}
unset($item);

/* =========================
   ORDENAR
========================= */
usort($items, function($a, $b){
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});


/* =========================
   LIMITE
========================= */
$items = array_slice($items, 0, 20);

/* CONTINUAR VIENDO: misma fuente real que inicio.php. */
$continuarCatalogo=[];
foreach($items as $itemContinuar){
    $tipoContinuar=(($itemContinuar['tipo']??'')==='serie')?'serie':'pelicula';
    $progresoRaw=(float)($itemContinuar['progreso']??0);$duracionReferencia=$tipoContinuar==='serie'?2700:5400;
    $porcentajeContinuar=$progresoRaw>100?min(100,max(0,($progresoRaw/$duracionReferencia)*100)):min(100,max(0,$progresoRaw));
    $idContinuar=(string)($itemContinuar['id']??'');
    $continuarCatalogo[]=['id'=>$idContinuar,'type'=>$tipoContinuar,'t'=>(string)($itemContinuar['titulo']??''),'m'=>$tipoContinuar==='serie'?'Serie'.(!empty($itemContinuar['capitulo'])?' · '.(string)$itemContinuar['capitulo']:''):'Película','img'=>(string)($itemContinuar['imagen']??''),'p'=>round($porcentajeContinuar,2),'url'=>$tipoContinuar==='serie'?'View-Peliculas/Reproductor-Universal-Series.php?id='.rawurlencode($idContinuar):'View-Peliculas/Reproductor-Universal.php?id='.rawurlencode($idContinuar)];
}



/* =========================
   DATOS DEL USUARIO
========================= */

$nombre = $user['name'] ?? 'Usuario';
$email  = $user['email'] ?? '';
$foto   = !empty($user['foto']) ? $user['foto'] : 'Logo Poster MovieTx PNG/Logo MovieTx.png';
$esPerfilActivo = false;
$nombrePerfilActivo = '';
$fotoPerfilActivo = '';
$esPerfilKids = (($tipoPerfilNotificaciones ?? 'normal') === 'kids');

/* =========================
   PERFIL SELECCIONADO
   Usuario principal / Perfil normal / Perfil Kids
========================= */

if (isset($_SESSION['perfil_id']) && (int)$_SESSION['perfil_id'] > 0) {

    $perfilId = (int)$_SESSION['perfil_id'];

    $stmtPerfil = $conn->prepare("
        SELECT nombre, foto
        FROM perfiles
        WHERE id=? AND user_id=?
        LIMIT 1
    ");

    if ($stmtPerfil) {
        $stmtPerfil->bind_param("ii", $perfilId, $userId);
        $stmtPerfil->execute();
        $resPerfil = $stmtPerfil->get_result();

        if ($resPerfil && $resPerfil->num_rows > 0) {
            $perfil = $resPerfil->fetch_assoc();

            $nombrePerfilActivo = trim((string)($perfil['nombre'] ?? ''));
            $fotoPerfilActivo = trim((string)($perfil['foto'] ?? ''));

            if ($nombrePerfilActivo !== '') {
                $nombre = $nombrePerfilActivo;
            }

            if ($fotoPerfilActivo !== '') {
                $foto = "uploads/perfiles/" . $fotoPerfilActivo;
            }

            $esPerfilActivo = true;
        } else {
            unset($_SESSION['perfil_id']);
            $perfilId = null;
            $esPerfilActivo = false;
            $esPerfilKids = false;
        }

        $stmtPerfil->close();
    }
}

$fotoActualHeader = trim((string)$foto);
if ($fotoActualHeader === '') {
    $fotoActualHeader = 'Logo Poster MovieTx PNG/Logo MovieTx.png';
}

$nombreActualHeader = trim((string)$nombre);
if ($nombreActualHeader === '') {
    $nombreActualHeader = 'Usuario';
}

$tipoActualHeader = $esPerfilKids ? 'Kids' : ($esPerfilActivo ? 'Perfil' : 'Usuario');

/* =========================
   CONFIGURACIÓN MODO ADULTO
   ========================= */

/*
 * El estado se toma de la misma estructura utilizada
 * en Privacidad.php:
 *
 * Usuario principal -> adultos
 * Perfil            -> perfiles_adultos
 */
$esUsuarioPrincipal = !isset($_SESSION['perfil_id']);
$adultoActivo = false;

if ($esUsuarioPrincipal) {

    $stmtAdulto = $conn->prepare("
        SELECT activo
        FROM adultos
        WHERE email=?
        LIMIT 1
    ");

    $stmtAdulto->bind_param("s", $user['email']);
    $stmtAdulto->execute();

    $adultoData = $stmtAdulto->get_result()->fetch_assoc();

} else {

    $perfilAdultoId = (int)$_SESSION['perfil_id'];

    $stmtAdulto = $conn->prepare("
        SELECT activo
        FROM perfiles_adultos
        WHERE user_id=?
        AND perfil_id=?
        LIMIT 1
    ");

    $stmtAdulto->bind_param(
        "ii",
        $userId,
        $perfilAdultoId
    );

    $stmtAdulto->execute();

    $adultoData = $stmtAdulto->get_result()->fetch_assoc();
}

if ($adultoData) {
    $adultoActivo = ((int)$adultoData['activo'] === 1);
}

/* =========================
   APAGAR MODO ADULTO
   ========================= */

if (
    isset($_POST['action']) &&
    $_POST['action'] === 'adulto_apagar'
) {

    header("Content-Type: application/json; charset=utf-8");

    /*
     * Usuario principal
     */
    if ($esUsuarioPrincipal) {

        $stmtAdulto = $conn->prepare("
            UPDATE adultos
            SET activo=0
            WHERE email=?
        ");

        $stmtAdulto->bind_param(
            "s",
            $user['email']
        );

    /*
     * Perfil
     */
    } else {

        $stmtAdulto = $conn->prepare("
            UPDATE perfiles_adultos
            SET activo=0
            WHERE user_id=?
            AND perfil_id=?
        ");

        $stmtAdulto->bind_param(
            "ii",
            $userId,
            $perfilAdultoId
        );
    }

    if ($stmtAdulto->execute()) {

        echo json_encode([
            "success" => true,
            "activo" => 0,
            "message" => "Modo adulto desactivado"
        ]);

    } else {

        echo json_encode([
            "success" => false,
            "message" => "No se pudo desactivar el modo adulto"
        ]);
    }

    exit();
}

/* =========================
   VERIFICACIÓN AJAX (FIX)
========================= */

if (isset($_GET['check_status'])) {

    $stmt = $conn->prepare("SELECT status, paid_until FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (
        !$data ||
        $data['status'] !== 'active' ||
        empty($data['paid_until']) ||
        strtotime($data['paid_until']) < time()
    ) {
        session_unset();
        session_destroy();
        echo "logout";
    } else {
        echo "ok";
    }

    exit();
}

/* =========================
   GUARDAR TEMA (AJAX)
========================= */

if (isset($_POST['theme'])) {

    $theme = $_POST['theme'];

    $allowedThemes = ['light', 'dark', 'blue', 'sky', 'red', 'pink'];

    if (!in_array($theme, $allowedThemes)) {
        echo "error";
        exit();
    }

    // PERFIL
    if(isset($_SESSION['perfil_id'])){

        $perfilId = $_SESSION['perfil_id'];

        $stmt = $conn->prepare("
            UPDATE perfiles
            SET theme=?
            WHERE id=? AND user_id=?
        ");

        $stmt->bind_param("sii", $theme, $perfilId, $userId);

    }else{

        // USUARIO NORMAL
        $stmt = $conn->prepare("
            UPDATE users
            SET theme=?
            WHERE id=?
        ");

        $stmt->bind_param("si", $theme, $userId);
    }

    $stmt->execute();

    echo "ok";
    exit();
}

/* =========================
   TEMA SEGÚN PERFIL
========================= */

if(isset($_SESSION['perfil_id'])){

    $perfilId = $_SESSION['perfil_id'];

    $stmtTheme = $conn->prepare("
        SELECT theme
        FROM perfiles
        WHERE id=? AND user_id=?
        LIMIT 1
    ");

    $stmtTheme->bind_param("ii", $perfilId, $userId);
    $stmtTheme->execute();

    $resTheme = $stmtTheme->get_result()->fetch_assoc();

    if($resTheme){
        $themePerfil = trim((string)($resTheme['theme'] ?? ''));

        if (in_array($themePerfil, $allowedThemes, true)) {
            $theme = $themePerfil;
        } else {
            $theme = 'light';
        }
    }
}

/* =========================
   CAMBIAR CONTRASEÑA
========================= */

if (isset($_POST['change_password'])) {

    $newPass = $_POST['new_password'] ?? '';

    if (strlen($newPass) >= 6) {

        $hash = password_hash($newPass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $userId);
        $stmt->execute();

        header("Location: inicio.php?pass=ok");
        exit();
    }
}

/* =========================
   ELIMINAR CUENTA
========================= */

if (isset($_POST['delete_account'])) {

    $fotoActual = $user['foto'] ?? '';

    if (!empty($fotoActual) && $fotoActual !== 'Logo Poster MovieTx PNG/Logo MovieTx.png') {
        if (file_exists($fotoActual)) {
            unlink($fotoActual);
        }
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit();
}

/* =========================
   ACTUALIZAR FOTO (PRO)
========================= */

if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {

    $extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $nombreArchivo = uniqid() . "." . $extension;

    if (isset($_SESSION['perfil_id'])) {

        $perfilId = $_SESSION['perfil_id'];

        $stmt = $conn->prepare("SELECT foto FROM perfiles WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $perfilId, $userId);
        $stmt->execute();
        $perfil = $stmt->get_result()->fetch_assoc();

        if ($perfil) {

            $fotoActual = "uploads/perfiles/" . $perfil['foto'];

            if (!empty($perfil['foto']) && file_exists($fotoActual)) {
                unlink($fotoActual);
            }

            if (!is_dir("uploads/perfiles/")) {
                mkdir("uploads/perfiles/", 0755, true);
            }

            $rutaDestino = "uploads/perfiles/" . $nombreArchivo;

            move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino);

            $stmt = $conn->prepare("UPDATE perfiles SET foto=? WHERE id=? AND user_id=?");
            $stmt->bind_param("sii", $nombreArchivo, $perfilId, $userId);
            $stmt->execute();
        }

    } else {

        $fotoActual = $user['foto'] ?? '';

        if (!empty($fotoActual) && $fotoActual !== 'Logo Poster MovieTx PNG/Logo MovieTx.png') {
            if (file_exists($fotoActual)) {
                unlink($fotoActual);
            }
        }

        if (!is_dir("uploads/usuarios/")) {
            mkdir("uploads/usuarios/", 0755, true);
        }

        $rutaDestino = "uploads/usuarios/" . $nombreArchivo;

        move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino);

        $stmt = $conn->prepare("UPDATE users SET foto=? WHERE id=?");
        $stmt->bind_param("si", $rutaDestino, $userId);
        $stmt->execute();
    }

    header("Location: inicio.php");
    exit();
}

/* =========================
   DATOS DEL USUARIO
========================= */

$nombre = $user['name'] ?? 'Usuario';
$email  = $user['email'] ?? '';
$foto   = !empty($user['foto'])
    ? $user['foto']
    : 'Logo Poster MovieTx PNG/Logo MovieTx.png';


/* =========================
   PERFIL SELECCIONADO
========================= */

if(isset($_SESSION['perfil_id'])){

    $perfilId = $_SESSION['perfil_id'];

    $stmtPerfil = $conn->prepare("
        SELECT nombre, foto
        FROM perfiles
        WHERE id=? AND user_id=?
        LIMIT 1
    ");

    $stmtPerfil->bind_param(
        "ii",
        $perfilId,
        $userId
    );

    $stmtPerfil->execute();

    $resPerfil =
    $stmtPerfil->get_result();

    if($resPerfil->num_rows > 0){

        $perfil =
        $resPerfil->fetch_assoc();

        $nombre =
        $perfil['nombre'];

        $foto =
        "uploads/perfiles/" .
        $perfil['foto'];

    }else{

        unset(
        $_SESSION['perfil_id']
        );

    }

}

/* ======================================
   🔥 ACTIVO DEL USUARIO
====================================== */
if(isset($_SESSION['id']) && isset($_COOKIE['device_token'])){

    $stmt = $conn->prepare("
        UPDATE dispositivos 
        SET last_ping = NOW(), is_active = 1 
        WHERE user_id = ? AND token = ?
    ");
    $stmt->bind_param("is", $_SESSION['id'], $_COOKIE['device_token']);
    $stmt->execute();
}

/* ======================================
   🚫 VERIFICAR SI EL DISPOSITIVO ESTÁ BLOQUEADO
====================================== */
if(isset($_SESSION['id']) && isset($_COOKIE['device_token'])){

    $stmt = $conn->prepare("
        SELECT blocked
        FROM dispositivos
        WHERE user_id = ?
        AND token = ?
        LIMIT 1
    ");

    $stmt->bind_param("is", $_SESSION['id'], $_COOKIE['device_token']);
    $stmt->execute();

    $res = $stmt->get_result()->fetch_assoc();

    // SI ESTÁ BLOQUEADO
    if($res && intval($res['blocked']) === 1){

        // DESTRUIR SESIÓN
        $_SESSION = [];
        session_destroy();

        // ELIMINAR COOKIE
        setcookie("device_token", "", time() - 3600, "/");

        // REDIRIGIR
        header("Location: index.php");
        exit;
    }
}

/* ======================================
   ⚫ LIMPIAR INACTIVOS (GLOBAL)
====================================== */
$conn->query("
    UPDATE dispositivos
    SET is_active = 0
    WHERE is_active = 1
    AND last_ping < NOW() - INTERVAL 2 MINUTE
");

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link
    rel="icon"
    type="image/png"
    href="Logo/Logo Nuevo -512x512.png"
>
<script>
/* Al refrescar/reabrir esta página, siempre comenzar arriba. */
if ('scrollRestoration' in history) {
  history.scrollRestoration = 'manual';
}
window.addEventListener('pageshow', function () {
  window.scrollTo(0, 0);
});
</script>
<title>MovieTx — Inicio</title>
<style>
*{box-sizing:border-box}
:root{
  --bg:#08090d;--surface:#11131a;--surface2:#171a23;--text:#fff;--muted:#9ba0ad;
  --accent:#e50914;--line:rgba(255,255,255,.08);--radius:16px;
}
/* Scroll vertical premium SOLO en PC/escritorio. */
@media(max-width:1100px){
  html{scrollbar-width:none !important;}
  html::-webkit-scrollbar{display:none !important;width:0 !important;}
}
html{scroll-behavior:smooth;background:var(--bg);overscroll-behavior-y:none}
body{margin:0;min-height:100vh;background:var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;overscroll-behavior-y:none;-webkit-overflow-scrolling:touch}
body.modal-open{
  overflow:hidden !important;
  height:100vh !important;
  touch-action:none !important;
}
body.modal-open #app,
body.modal-open .app-header,
body.modal-open .mobile-nav{
  pointer-events:none !important;
}
body.modal-open .notification-panel,
body.modal-open .notification-panel *,
body.modal-open .notification-close,
body.modal-open .notification-backdrop{
  pointer-events:auto;
}

button,a{font:inherit}
button{border:0}
a{text-decoration:none;color:inherit}
.app-header{
 position:fixed;top:0;left:0;right:0;height:72px;z-index:100;
 display:flex;align-items:center;padding:0 34px;gap:28px;
 background:linear-gradient(180deg,rgba(5,6,9,.96),rgba(5,6,9,.72),transparent);
 transition:none;
 -webkit-backface-visibility:hidden;backface-visibility:hidden;
}
.app-header.scrolled{background:linear-gradient(180deg,rgba(5,6,9,.96),rgba(5,6,9,.72),transparent);border-bottom:1px solid var(--line)}
.logo{font-size:24px;font-weight:900;letter-spacing:-1px;margin-right:8px}
.logo span{color:var(--accent)}
.main-nav{display:flex;gap:6px;align-items:center}
.main-nav button{
 background:transparent;color:#aaa;padding:10px 14px;border-radius:10px;cursor:pointer;
 transition:.2s
}
.main-nav button:hover,.main-nav button.active{color:#fff;background:rgba(255,255,255,.08)}
.header-right{margin-left:auto;display:flex;align-items:center;gap:10px}
.current-user{display:flex;align-items:center;gap:8px;min-width:0;max-width:180px}
.current-user img{width:38px;height:38px;border-radius:50%;object-fit:cover;border:1px solid rgba(255,255,255,.18);background:#151821;flex:0 0 38px}
.current-user-copy{min-width:0;line-height:1.05}
.current-user-copy strong{display:block;max-width:115px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#fff}
.current-user-copy small{display:block;margin-top:4px;font-size:9px;color:#8f95a2;white-space:nowrap}

.icon-btn{
 width:42px;height:42px;border-radius:50%;background:rgba(255,255,255,.08);
 color:#fff;display:grid;place-items:center;cursor:pointer;position:relative;
}
.icon-btn svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.icon-btn.badge:after{
 content:"";position:absolute;right:8px;top:8px;width:7px;height:7px;border-radius:50%;
 background:var(--accent);box-shadow:0 0 0 2px #08090d
}
.avatar{width:39px;height:39px;border-radius:50%;object-fit:cover;border:1px solid rgba(255,255,255,.18)}
.settings-wrap{position:relative}
.settings-menu{
 position:absolute;right:0;top:51px;width:225px;padding:8px;background:rgba(20,22,30,.98);
 border:1px solid rgba(255,255,255,.1);border-radius:16px;box-shadow:0 24px 55px #000b;
 opacity:0;pointer-events:none;transform:translateY(-7px) scale(.98);transition:.18s;
 backdrop-filter:blur(18px)
}
.settings-wrap.open .settings-menu{opacity:1;pointer-events:auto;transform:none}
.settings-menu a{
 display:flex;flex-direction:column;gap:2px;padding:12px 13px;border-radius:11px;color:#eee;font-size:14px
}
.settings-menu a span{font-weight:700}
.settings-menu a small{font-size:11px;color:#777d89}
.settings-menu a:hover{background:rgba(255,255,255,.07);color:#fff}
.settings-menu a:hover small{color:#a9adb6}
.settings-menu .logout{margin-top:3px;border-top:1px solid var(--line);border-radius:0 0 10px 10px}
.settings-menu .logout span{color:#ff6670}
.settings-icon svg{width:22px;height:22px}
body.settings-open{overflow:hidden}

/* ===== NOTIFICACIONES ===== */
.notification-backdrop{
 position:fixed;inset:0;z-index:140;background:rgba(0,0,0,.58);
 opacity:0;visibility:hidden;pointer-events:none;transition:opacity .2s ease,visibility .2s ease;
}
.notification-backdrop.open{opacity:1;visibility:visible;pointer-events:auto}
.notification-panel{
 position:fixed;right:24px;top:72px;width:470px;max-height:calc(100vh - 82px);z-index:151;
 display:flex;flex-direction:column;background:rgba(14,16,22,.985);
 border:1px solid rgba(255,255,255,.11);border-radius:0 0 22px 22px;
 box-shadow:0 30px 90px rgba(0,0,0,.72);opacity:0;visibility:hidden;
 pointer-events:none;transform:translateY(-10px);transition:opacity .2s ease,transform .2s ease,visibility .2s ease;
 overflow:hidden;backdrop-filter:blur(20px)
}
.notification-panel.open{opacity:1;visibility:visible;pointer-events:auto;transform:none}
.notification-head{display:flex;align-items:center;justify-content:space-between;padding:18px 18px 15px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#191c25,#12141a);flex:none}
.notification-profile{display:flex;align-items:center;gap:11px}
.notification-profile strong{display:block;font-size:16px}.notification-profile span{display:block;color:#777e8a;font-size:11px;margin-top:3px}
.notification-bell{width:40px;height:40px;border-radius:12px;background:rgba(229,9,20,.12);display:grid;place-items:center;color:#ff5961}
.notification-bell svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.notification-close{
 width:36px;height:36px;min-width:36px;
 margin:0 0 0 12px;padding:0;
 border-radius:10px;background:rgba(255,255,255,.07);
 color:#aaa;cursor:pointer;font-size:21px;line-height:1;
 display:grid;place-items:center;text-align:center;
 flex:none;transform:translateY(-1px);
}
.notification-close:hover{background:rgba(255,255,255,.12);color:#fff}
.notification-tabs{display:flex;gap:4px;padding:10px 14px 6px;flex:none}
.notification-tabs button{background:transparent;color:#737985;padding:8px 11px;border-radius:8px;cursor:pointer;font-size:12px}
.notification-tabs button.active{background:rgba(255,255,255,.07);color:#fff}
.notification-tabs b{font-size:10px;color:#999;margin-left:4px}
.notification-list{padding:4px 10px 10px;flex:1;min-height:0;overflow:auto;overscroll-behavior:contain;scrollbar-width:thin;scrollbar-color:#555 #11131a}
.notification-list::-webkit-scrollbar{width:6px}.notification-list::-webkit-scrollbar-track{background:#11131a}.notification-list::-webkit-scrollbar-thumb{background:linear-gradient(#656b77,#363b45);border-radius:20px}
.notice{display:flex;gap:11px;padding:11px;border-radius:13px;margin-bottom:5px;cursor:pointer;position:relative}
.notice:hover{background:rgba(255,255,255,.055)}
.notice-img{width:56px;height:76px;flex:none;border-radius:9px;object-fit:cover;background:#1a1d26;border:1px solid rgba(255,255,255,.08)}
.notice-body{min-width:0;flex:1}
.notice-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
.notice-title{font-size:12px;font-weight:800}.notice-time{font-size:10px;color:#626874;white-space:nowrap}
.notice-text{
 font-size:11px;color:#9297a2;line-height:1.45;margin-top:5px;
 display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;
 overflow:hidden;overflow-wrap:anywhere;
}
.notice-hint{
 margin-top:6px;color:#78dfff;font-size:9px;font-weight:800;
 letter-spacing:.01em;opacity:.82;
}
.notice-action{background:none;padding:6px 0 0;color:#ddd;font-size:10px;font-weight:700;cursor:pointer}
.notice-action:hover{color:#fff}
.notice-new:before{content:"";position:absolute;left:2px;top:17px;width:5px;height:5px;border-radius:50%;background:var(--accent)}
.notification-footer{border-top:1px solid var(--line);padding:13px;text-align:center;background:#11131a;flex:none}
.notification-footer a{font-size:11px;color:#aaa}.notification-footer a:hover{color:#fff}

/* ===== SCROLL PREMIUM: NOTIFICACIONES Y DETALLE ===== */
.notification-list,
.notification-detail-scroll{
 scrollbar-width:thin;
 scrollbar-color:#f18bbd #10131b;
}
.notification-list::-webkit-scrollbar,
.notification-detail-scroll::-webkit-scrollbar{width:7px}
.notification-list::-webkit-scrollbar-track,
.notification-detail-scroll::-webkit-scrollbar-track{
 background:linear-gradient(180deg,#10131b,#171421);
 border-radius:999px;
}
.notification-list::-webkit-scrollbar-thumb,
.notification-detail-scroll::-webkit-scrollbar-thumb{
 background:linear-gradient(180deg,#7ddcff 0%,#bda7ff 48%,#f28fbd 100%);
 border-radius:999px;
 border:1px solid rgba(255,255,255,.18);
 box-shadow:0 0 10px rgba(125,220,255,.22),0 0 10px rgba(242,143,189,.18);
}
.notification-list::-webkit-scrollbar-thumb:hover,
.notification-detail-scroll::-webkit-scrollbar-thumb:hover{
 background:linear-gradient(180deg,#8fe4ff 0%,#cbb8ff 48%,#ffa2cb 100%);
}

/* ===== MODAL PREMIUM DE NOTIFICACIÓN ===== */
.notification-detail-backdrop{
 position:fixed;inset:0;z-index:190;background:rgba(4,7,14,.72);
 opacity:0;visibility:hidden;pointer-events:none;
 transition:opacity .2s ease,visibility .2s ease;
 backdrop-filter:blur(7px);
}
.notification-detail-backdrop.open{opacity:1;visibility:visible;pointer-events:auto}
.notification-detail-modal{
 position:fixed;left:50%;top:50%;width:min(620px,calc(100vw - 30px));
 max-height:min(720px,calc(100vh - 34px));z-index:191;
 display:flex;flex-direction:column;overflow:hidden;
 background:linear-gradient(180deg,rgba(24,27,38,.99),rgba(12,15,23,.99));
 border:1px solid rgba(255,255,255,.12);border-radius:24px;
 box-shadow:0 35px 110px rgba(0,0,0,.72),0 0 45px rgba(132,205,255,.08);
 opacity:0;visibility:hidden;pointer-events:none;
 transform:translate(-50%,-47%) scale(.97);
 transition:opacity .2s ease,visibility .2s ease,transform .2s ease;
}
.notification-detail-modal.open{opacity:1;visibility:visible;pointer-events:auto;transform:translate(-50%,-50%) scale(1)}
.notification-detail-head{
 display:flex;align-items:flex-start;justify-content:space-between;gap:16px;
 padding:20px 20px 16px;flex:none;
 border-bottom:1px solid rgba(255,255,255,.08);
 background:linear-gradient(135deg,rgba(125,220,255,.08),rgba(242,143,189,.08));
}
.notification-detail-kicker{display:block;color:#8fdfff;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;margin-bottom:5px}
.notification-detail-head h3{margin:0;color:#fff;font-size:19px;line-height:1.2}
.notification-detail-close{width:34px;height:34px;border:0;border-radius:11px;background:rgba(255,255,255,.07);color:#c8ccd6;cursor:pointer;font-size:23px;line-height:1;flex:none}
.notification-detail-close:hover{background:rgba(255,255,255,.12);color:#fff}
.notification-detail-body{display:flex;flex-direction:column;min-height:0;padding:18px}
.notification-detail-media{display:flex;justify-content:center;align-items:center;margin:0 0 15px;flex:none}
.notification-detail-media img{display:block;width:min(250px,55vw);max-height:300px;object-fit:cover;border-radius:17px;border:1px solid rgba(255,255,255,.12);box-shadow:0 18px 40px rgba(0,0,0,.38)}
.notification-detail-scroll{max-height:340px;min-height:0;overflow-y:auto;overflow-x:hidden;padding:2px 12px 2px 2px;overscroll-behavior:contain}
.notification-detail-message{color:#d7dae2;font-size:14px;line-height:1.7;white-space:pre-wrap;overflow-wrap:anywhere}
.notification-detail-play{width:100%;margin-top:16px;padding:13px 18px;border:0;border-radius:13px;background:linear-gradient(135deg,#73d9ff,#ef8fbd);color:#10131a;font-weight:900;font-size:13px;cursor:pointer;box-shadow:0 10px 30px rgba(115,217,255,.16)}
.notification-detail-play:hover{filter:brightness(1.06);transform:translateY(-1px)}
body.notification-detail-open{overflow:hidden}

/* ===== TARJETAS PREMIUM DEL CATÁLOGO ===== */
.catalogo-row-modern .card-link{
 position:relative;
 padding:7px 7px 10px;
 border-radius:18px;
 background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.018));
 border:1px solid rgba(255,255,255,.075);
 box-shadow:0 12px 28px rgba(0,0,0,.18);
 transition:transform .22s ease,border-color .22s ease,box-shadow .22s ease,background .22s ease;
}
.catalogo-row-modern .card-link:hover{
 transform:translateY(-4px);
 border-color:rgba(125,220,255,.25);
 box-shadow:0 18px 38px rgba(0,0,0,.28),0 0 24px rgba(242,143,189,.06);
 background:linear-gradient(180deg,rgba(255,255,255,.075),rgba(255,255,255,.025));
}
.catalogo-row-modern .card-link .xplus .xaviec{
 border-radius:13px;
 box-shadow:0 7px 18px rgba(0,0,0,.25);
}
.catalogo-row-modern .xplus i{
 padding:14px 3px 2px !important;
 font-size:13px !important;
 line-height:1.28;
 font-weight:800;
 letter-spacing:-.1px;
 color:#f6f7fb;
 display:-webkit-box;
 -webkit-box-orient:vertical;
 -webkit-line-clamp:2;
 white-space:normal;
 overflow:hidden;
 text-overflow:ellipsis;
 min-height:34px;
}
.catalogo-row-modern .card-info{padding:7px 2px 0}
.catalogo-row-modern .card-title{font-size:13px;font-weight:800;line-height:1.25;color:#f7f8fb}
.catalogo-row-modern .card-meta{font-size:10px;color:#8e95a4;margin-top:4px}

.hero-content{width:min(650px,52vw);min-width:0}
.hero h1{
 font-size:clamp(30px,4.2vw,58px);line-height:.98;letter-spacing:-1.8px;
 display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;
 overflow:hidden;overflow-wrap:anywhere;max-width:100%;min-height:1.96em;
}
.hero p{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden}

.settings-user{display:flex;align-items:center;gap:10px;padding:9px 8px 12px;margin-bottom:4px;border-bottom:1px solid var(--line)}
.settings-user img{width:42px;height:42px;border-radius:50%;object-fit:cover;border:1px solid rgba(255,255,255,.14)}
.settings-user strong{display:block;font-size:13px}.settings-user small{display:block;color:#777e89;font-size:10px;margin-top:2px}
.settings-menu a{display:flex!important;flex-direction:row!important;align-items:center;gap:10px}
.menu-svg{width:31px;height:31px;border-radius:9px;background:rgba(255,255,255,.06);display:grid;place-items:center;flex:none;color:#b9bdc6}
.menu-svg svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.menu-copy{display:flex;flex-direction:column;gap:2px}.menu-copy b{font-size:12px}.menu-copy small{font-size:10px;color:#777e89}

.adult-nav-item[hidden]{display:none!important}
.adult-setting{
  width:100%;display:flex;align-items:center;gap:10px;padding:10px 8px;margin:3px 0;
  background:transparent;color:#eee;border-radius:11px;cursor:pointer;text-align:left;
}
.adult-setting:hover{background:rgba(255,255,255,.07)}
.adult-setting .menu-copy{flex:1}
.adult-switch{
  width:36px;height:20px;border-radius:999px;background:#343842;position:relative;flex:none;
  border:1px solid rgba(255,255,255,.08);transition:.18s;
}
.adult-switch i{
  position:absolute;top:3px;left:3px;width:12px;height:12px;border-radius:50%;
  background:#b9bdc6;transition:.18s;
}
.adult-setting.active .adult-switch{background:var(--accent)}
.adult-setting.active .adult-switch i{left:19px;background:#fff}
.hero{
 min-height:650px;position:relative;display:flex;align-items:flex-end;padding:0 7vw 70px;
 overflow:hidden;background:#08090d;
}
.hero-bg{position:absolute;inset:0;background-size:cover;background-position:center;transition:opacity .35s}
@media(max-width:1100px){
  .hero{touch-action:pan-y;}
  .hero-bg{touch-action:pan-y;}
}
.hero-bg:after{
 content:"";position:absolute;inset:0;
 background:linear-gradient(90deg,#08090d 0%,rgba(8,9,13,.84) 26%,rgba(8,9,13,.26) 65%,rgba(8,9,13,.62) 100%),
 linear-gradient(0deg,#08090d 0%,transparent 45%,rgba(0,0,0,.18));
}
.hero-content{position:relative;z-index:2;max-width:650px}
.eyebrow{color:#c8cbd2;font-size:12px;text-transform:uppercase;letter-spacing:2px;margin-bottom:12px}
.hero h1{font-size:clamp(38px,5vw,68px);line-height:.98;margin:0 0 16px;letter-spacing:-2.5px}
.meta{display:flex;gap:10px;align-items:center;color:#c5c7ce;font-size:14px;margin-bottom:18px}
.dot{width:4px;height:4px;background:#777;border-radius:50%}
.hero p{color:#b7bac4;line-height:1.6;max-width:580px;margin:0 0 25px}
.hero-actions{display:flex;gap:10px}
.primary,.secondary{padding:12px 18px;border-radius:9px;cursor:pointer;font-weight:700}
.primary{background:#fff;color:#000}.secondary{background:rgba(255,255,255,.12);color:#fff}
.hero-dots{position:absolute;right:7vw;bottom:76px;z-index:3;display:flex;gap:7px}
.hero-dots button{width:8px;height:8px;padding:0;border-radius:50%;background:#777;cursor:pointer}
.hero-dots button.active{width:24px;border-radius:5px;background:#fff}
.page{width:100%;max-width:none;padding:0 28px 60px}
.section{margin-top:28px}
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.section h2{font-size:21px;margin:0;letter-spacing:-.4px}
.section-sub{font-size:12px;color:#7f8490}
.view-all{font-size:11px;color:#d9dbe1;font-weight:800;white-space:nowrap;padding:7px 11px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);border-radius:999px;text-decoration:none;transition:all .18s ease;line-height:1}
.view-all:hover{color:#fff;background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.24);transform:translateY(-1px)}

/* =========================================================
   MOVIETX — CONTROL GLOBAL DE SCROLL
   ========================================================= */
html{
  max-width:100%;
}

/* Scroll vertical premium de la página en PC */
html{
  scrollbar-width:thin;
  scrollbar-color:#8edfff #10131b;
}
html::-webkit-scrollbar{
  width:9px;
}
html::-webkit-scrollbar-track{
  background:linear-gradient(180deg,#0d1118 0%,#17131f 100%);
  border-left:1px solid rgba(255,255,255,.05);
}
html::-webkit-scrollbar-thumb{
  background:linear-gradient(180deg,#72ddff 0%,#b9b2ff 48%,#f28fbd 100%);
  border:2px solid #10131b;
  border-radius:999px;
  box-shadow:0 0 10px rgba(114,221,255,.28),0 0 12px rgba(242,143,189,.22);
}
html::-webkit-scrollbar-thumb:hover{
  background:linear-gradient(180deg,#92e7ff 0%,#cec6ff 48%,#ffa5cc 100%);
}

/* ===== FILAS / SCROLL PREMIUM ===== */

.media-row{
 display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:16px;
}
.media-row .poster{aspect-ratio:2/3}
.media-row.premium-scroll{
 display:flex;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;
 gap:18px;padding:2px 3px 14px 2px;scroll-behavior:smooth;
 scroll-snap-type:x proximity;overscroll-behavior-x:contain;
 scrollbar-width:thin;scrollbar-color:#626874 #141720;
}
.media-row.premium-scroll::-webkit-scrollbar{height:7px}
.media-row.premium-scroll::-webkit-scrollbar-track{
 background:linear-gradient(90deg,#11141b,#1a1e28,#11141b);border-radius:99px
}
.media-row.premium-scroll::-webkit-scrollbar-thumb{
 background:linear-gradient(90deg,#6f7683,#c3c7cf,#6f7683);
 border-radius:99px;border:2px solid #141720
}
.media-row.premium-scroll .card{
 flex:0 0 190px;scroll-snap-align:start
}
.media-row.premium-scroll .poster{aspect-ratio:2/3}
#continueRow.premium-scroll .card{flex-basis:310px}
#continueRow.premium-scroll .poster{aspect-ratio:16/9}
.card{min-width:0;cursor:pointer}
.poster{
 aspect-ratio:2/3;border-radius:12px;overflow:hidden;position:relative;background:#181b23;
 border:1px solid rgba(255,255,255,.05)
}
.poster img{width:100%;height:100%;object-fit:cover;display:block;transition:.25s}
.card:hover .poster img{transform:scale(1.035)}
.poster:after{content:"";position:absolute;inset:45% 0 0;background:linear-gradient(transparent,rgba(0,0,0,.8))}
.play-center{
 position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:3;
 width:48px;height:48px;border-radius:50%;display:grid;place-items:center;
 background:rgba(0,0,0,.64);border:1px solid rgba(255,255,255,.45);
 opacity:.96;backdrop-filter:blur(5px)
}
.play-center svg{width:20px;height:20px;fill:#fff;margin-left:2px}
.progress{position:absolute;left:8px;right:8px;bottom:8px;height:4px;background:#555b;border-radius:5px;z-index:4;overflow:hidden}
.progress i{display:block;height:100%;background:var(--accent);width:64%}
.continue-percent{
  position:absolute;
  right:9px;
  bottom:17px;
  z-index:5;
  font-size:10px;
  line-height:1;
  font-weight:800;
  color:#fff;
  text-shadow:0 1px 4px rgba(0,0,0,.9);
  pointer-events:none;
}
.card-info{padding:9px 2px 0}
#continueRow .card-title{font-size:14px}
#continueRow .card-meta{font-size:11px}
.card-title{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card-meta{font-size:11px;color:#858a96;margin-top:4px}
.chips{display:flex;gap:8px;overflow:auto;padding-bottom:3px}
.chips::-webkit-scrollbar{height:0}
.chip{padding:8px 13px;border-radius:999px;background:#151821;color:#aeb2bc;border:1px solid var(--line);cursor:pointer;white-space:nowrap}
.chip.active,.chip:hover{background:#fff;color:#000}
.empty{display:none;padding:55px 20px;text-align:center;color:#818692}

#view-adulto .section-head h2{display:flex;align-items:center;gap:8px}
#view-adulto .section-head h2:after{content:"18+";font-size:9px;font-weight:800;color:#fff;background:var(--accent);padding:3px 6px;border-radius:5px;letter-spacing:.3px}
.mobile-nav{display:none}
@media(max-width:1100px){
 .media-row{grid-template-columns:repeat(4,minmax(0,1fr))}
 .media-row.premium-scroll{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));overflow:visible;padding:0}
 .media-row.premium-scroll .card,
 #continueRow.premium-scroll .card{flex:none}
 .notification-panel{right:16px;width:min(470px,calc(100vw - 32px))}
}
@media(max-width:1100px){
 .hero{
   min-height:clamp(560px,78svh,700px);
   padding:0 clamp(22px,5vw,52px) 68px;
 }
 .hero-bg{background-position:center center;background-size:cover}
 .hero-content{width:min(100%,650px);max-width:650px}
 .hero h1{font-size:clamp(34px,6vw,54px);line-height:1.01;margin-bottom:11px;min-height:0}
 .hero p{font-size:13px;line-height:1.5;margin-bottom:16px;max-width:600px}
 .hero-actions{gap:8px}
 .primary,.secondary{padding:10px 14px;font-size:12px}
}

@media(max-width:700px){
 .app-header{height:62px;padding:0 15px;gap:10px}
 .logo{font-size:20px}.main-nav{display:none}.header-right{gap:5px}
 .icon-btn{width:38px;height:38px}
 /* Android/iPhone: ocultar Sugerencias y Ajustes del header. */
 .app-header .header-suggestions{display:none}
 .app-header #settingsWrap > .settings-icon{display:none}
 .app-header #settingsWrap{position:static}
 .app-header #settingsWrap .settings-menu{
  position:fixed;
  top:auto;
  right:10px;
  bottom:80px;
  left:auto;
  width:min(300px,calc(100vw - 20px));
  max-height:calc(100vh - 150px);
  overflow-y:auto;
  overscroll-behavior:contain;
  -webkit-overflow-scrolling:touch;
  z-index:180;
 }

 /* Hero móvil: contenido compacto y sin espacios excesivos. */
 .hero{
   min-height:clamp(500px,82svh,650px);
   padding:0 18px 72px;
   align-items:flex-end;
 }
 .hero-bg{
   background-size:cover;
   background-position:center center;
 }
 .hero-content{
   width:min(100%,620px);
   max-width:100%;
   margin:0;
   padding:0;
 }
 .eyebrow{font-size:10px;letter-spacing:1.5px;margin-bottom:7px}
 .hero h1{
   font-size:clamp(28px,8.8vw,42px);
   line-height:1.02;
   letter-spacing:-1.2px;
   margin:0 0 9px;
   min-height:0;
 }
 .meta{font-size:11px;gap:7px;margin-bottom:9px}
 .dot{width:3px;height:3px}
 .hero p{
   font-size:12px;
   line-height:1.42;
   max-width:100%;
   margin:0 0 13px;
   -webkit-line-clamp:3;
 }
 .hero-actions{gap:7px;flex-wrap:nowrap}
 .primary,.secondary{
   min-height:34px;
   padding:8px 11px;
   border-radius:8px;
   font-size:11px;
   line-height:1;
   white-space:nowrap;
 }
 .hero-dots{right:18px;bottom:27px}

 /* Carruseles táctiles: una sola fila desplazable. */
 .media-row.premium-scroll{
   display:flex !important;
   flex-wrap:nowrap !important;
   overflow-x:auto !important;
   overflow-y:hidden !important;
   -webkit-overflow-scrolling:touch;
   touch-action:pan-x;
   overscroll-behavior-x:contain;
   scroll-snap-type:x proximity;
 }
 .media-row.premium-scroll .card{
   flex:0 0 min(43vw,180px) !important;
   width:min(43vw,180px);
   min-width:min(43vw,180px);
 }
 .hero-content{width:100%;max-width:100%}
 .hero h1{font-size:clamp(28px,9vw,42px);letter-spacing:-1px;min-height:1.96em}
 .hero{min-height:560px;padding:0 20px 54px}.hero h1{font-size:42px}.hero p{font-size:13px}
 .hero-dots{right:20px;bottom:27px}
 .page{padding:0 15px 82px}
 .media-row{grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
 .media-row.premium-scroll{grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;overflow:visible;padding:0}
 .section{margin-top:24px}.section h2{font-size:18px}.poster{border-radius:9px}
 .play-center{width:40px;height:40px}.play-center svg{width:17px;height:17px}
 .notification-panel{
   left:0;right:0;top:62px;width:auto;max-height:calc(100vh - 62px);
   border-radius:0 0 18px 18px
 }
 .notification-list{max-height:none}
 .notification-backdrop{background:rgba(0,0,0,.66)}
 .notification-detail-modal{width:calc(100vw - 22px);max-height:calc(100vh - 24px);border-radius:21px}
 .notification-detail-body{padding:15px}
 .notification-detail-media img{width:min(210px,58vw);max-height:250px}
 .notification-detail-scroll{max-height:min(42vh,330px)}
 .notification-detail-message{font-size:13px;line-height:1.65}
 .notification-detail-head{padding:17px 16px 14px}
 .notification-detail-head h3{font-size:17px}
 .mobile-nav{
  position:fixed;display:flex;left:10px;right:10px;bottom:10px;height:60px;z-index:120;
  background:rgba(18,20,27,.94);backdrop-filter:blur(15px);border:1px solid var(--line);
  border-radius:17px;justify-content:space-around;align-items:center
 }
 .mobile-nav button{background:none;color:#777;display:flex;flex-direction:column;align-items:center;gap:3px;font-size:10px}
 .mobile-nav button.active{color:#fff}.mobile-nav svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8}
}
/* Nunca mostrar barras de desplazamiento de las filas en Android/iPhone/tablet. */
@media(max-width:1100px){
 .media-row.premium-scroll::-webkit-scrollbar{display:none}
 .media-row.premium-scroll{
   scrollbar-width:none;
   -webkit-overflow-scrolling:touch;
   touch-action:pan-x pan-y;
 }
}
/* ===== FIN RESPONSIVE ===== */

/* =========================================================
   MOVIETX — SCROLL HORIZONTAL PREMIUM CELESTE + ROSA
   ========================================================= */
.media-row.premium-scroll{
  scrollbar-width:thin;
  scrollbar-color:#9fe7ff #11131b;
  scroll-behavior:smooth;
  overscroll-behavior-x:contain;
}
.media-row.premium-scroll::-webkit-scrollbar{
  height:8px;
}
.media-row.premium-scroll::-webkit-scrollbar-track{
  background:linear-gradient(90deg,#0d1118 0%,#17131f 50%,#0d1118 100%);
  border:1px solid rgba(255,255,255,.06);
  border-radius:999px;
  box-shadow:inset 0 1px 4px rgba(0,0,0,.75);
}
.media-row.premium-scroll::-webkit-scrollbar-thumb{
  background:linear-gradient(90deg,#70dcff 0%,#a9ddff 25%,#c6b8ff 52%,#f08fbd 78%,#ff9fc8 100%);
  border:2px solid #151821;
  border-radius:999px;
  box-shadow:
    0 0 8px rgba(112,220,255,.28),
    0 0 10px rgba(240,143,189,.22);
}
.media-row.premium-scroll::-webkit-scrollbar-thumb:hover{
  background:linear-gradient(90deg,#8ce5ff 0%,#b8e6ff 25%,#d5caff 52%,#f7a2c9 78%,#ffb0d3 100%);
}

/* Firefox: mantener el tono celeste/rosa del carrusel */
.category-row,
.catalogo-row-modern{
  scrollbar-width:thin;
  scrollbar-color:#a5e6ff #11131b;
}

/* Continuar viendo siempre horizontal */
#continueRow,
#continueMoviesRow,
#continueSeriesRow{
  display:flex !important;
  flex-wrap:nowrap !important;
  gap:18px;
  overflow-x:auto;
  overflow-y:hidden;
  padding:3px 3px 14px;
  scroll-snap-type:x proximity;
  scroll-behavior:smooth;
  overscroll-behavior-x:contain;
  -webkit-overflow-scrolling:touch;
}
#continueRow .card,
#continueMoviesRow .card,
#continueSeriesRow .card{
  flex:0 0 310px;
  width:310px;
  min-width:310px;
  scroll-snap-align:start;
}
#continueRow .poster,
#continueMoviesRow .poster,
#continueSeriesRow .poster{
  aspect-ratio:16/9 !important;
  position:relative;
}

/* X para eliminar */
.continue-remove{
  position:absolute;
  z-index:10;
  top:9px;
  right:9px;
  width:30px;
  height:30px;
  padding:0;
  border:1px solid rgba(255,255,255,.30);
  border-radius:50%;
  display:grid;
  place-items:center;
  color:#fff;
  background:rgba(5,7,10,.72);
  box-shadow:0 5px 18px rgba(0,0,0,.48);
  backdrop-filter:blur(9px);
  -webkit-backdrop-filter:blur(9px);
  cursor:pointer;
  transition:transform .16s ease,background .16s ease,border-color .16s ease;
}
.continue-remove:hover{
  transform:scale(1.08);
  background:rgba(229,9,20,.90);
  border-color:rgba(255,255,255,.55);
}
.continue-remove svg{
  width:15px;
  height:15px;
  fill:none;
  stroke:currentColor;
  stroke-width:2;
  stroke-linecap:round;
}
#continueRow .progress,
#continueMoviesRow .progress,
#continueSeriesRow .progress{
  left:9px;
  right:9px;
  bottom:9px;
}

/* PC: filas normales horizontales */
@media(min-width:1101px){
  .media-row.premium-scroll{
    display:flex !important;
    flex-wrap:nowrap !important;
    overflow-x:auto;
    overflow-y:hidden;
    gap:16px;
    padding:2px 3px 14px;
  }
  .media-row.premium-scroll .card{
    flex:0 0 190px;
    width:190px;
    min-width:190px;
  }
}

/* Tablet / Android / iPhone:
   tarjetas normales una al lado de otra;
   continuar viendo sigue siendo horizontal. */
@media(max-width:1100px){
  .media-row.premium-scroll{
    display:flex !important;
    flex-wrap:nowrap !important;
    overflow-x:auto !important;
    overflow-y:hidden !important;
    -webkit-overflow-scrolling:touch;
    touch-action:pan-x;
    scrollbar-width:none;
  }
  .media-row.premium-scroll::-webkit-scrollbar{display:none}

  .media-row.premium-scroll .card{
    flex:0 0 190px !important;
    width:190px;
    min-width:190px;
  }

  #continueRow,
  #continueMoviesRow,
  #continueSeriesRow{
    scrollbar-width:none;
    padding-bottom:5px;
  }
  #continueRow::-webkit-scrollbar,
  #continueMoviesRow::-webkit-scrollbar,
  #continueSeriesRow::-webkit-scrollbar{
    display:none;
  }
}

@media(max-width:700px){
  #continueRow,
  #continueMoviesRow,
  #continueSeriesRow{
    gap:13px;
  }
  #continueRow .card,
  #continueMoviesRow .card,
  #continueSeriesRow .card{
    flex:0 0 min(48vw,198px);
    width:min(48vw,198px);
    min-width:min(48vw,198px);
  }
  .continue-remove{
    width:29px;
    height:29px;
    top:7px;
    right:7px;
    top:6px;
    right:6px;
    border-width:1px;
  }
  .continue-remove svg{width:15px;height:15px}
}


@media(max-width:1100px){
  /* Android / iPhone / tablet: una tarjeta al lado de la otra,
     en una sola fila horizontal deslizable. */
  #historyRow,
  #favoritesRow,
  #suggestionsRow,
  #moviesRow,
  #moviesRecRow,
  #seriesRow,
  #seriesRecRow{
    display:flex !important;
    flex-wrap:nowrap !important;
    grid-template-columns:none !important;
    gap:12px;
    overflow-x:auto;
    overflow-y:hidden;
    padding:2px 2px 8px;
    scroll-snap-type:x proximity;
    scroll-behavior:smooth;
    overscroll-behavior-x:contain;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:none;
  }
  #historyRow::-webkit-scrollbar,
  #favoritesRow::-webkit-scrollbar,
  #suggestionsRow::-webkit-scrollbar,
  #moviesRow::-webkit-scrollbar,
  #moviesRecRow::-webkit-scrollbar,
  #seriesRow::-webkit-scrollbar,
  #seriesRecRow::-webkit-scrollbar{
    display:none;
  }
  #historyRow .card,
  #favoritesRow .card,
  #suggestionsRow .card,
  #moviesRow .card,
  #moviesRecRow .card,
  #seriesRow .card,
  #seriesRecRow .card{
    flex:0 0 190px;
    width:190px;
    min-width:190px;
    scroll-snap-align:start;
  }
}
@media(max-width:700px){
  #historyRow,
  #favoritesRow,
  #suggestionsRow,
  #moviesRow,
  #moviesRecRow,
  #seriesRow,
  #seriesRecRow{
    gap:10px;
  }
  /* Android / iPhone: tarjetas normales más pequeñas.
     No modificar Continuar viendo ni los carruseles premium. */
  #historyRow .card,
  #favoritesRow .card,
  #suggestionsRow .card,
  #moviesRow .card,
  #moviesRecRow .card,
  #seriesRow .card,
  #seriesRecRow .card{
    flex:0 0 min(25vw,105px);
    width:min(25vw,105px);
    min-width:min(25vw,105px);
  }
  .view-all{font-size:11px}
}


/* AJUSTE FINAL: tarjetas normales PEQUEÑAS en Android/iPhone.
   No afecta Continuar viendo ni ningún carrusel premium. */
@media screen and (max-width:700px){
  #historyRow .card,
  #favoritesRow .card,
  #suggestionsRow .card,
  #moviesRow .card,
  #moviesRecRow .card,
  #seriesRow .card,
  #seriesRecRow .card{
    flex:0 0 115px !important;
    width:115px !important;
    min-width:115px !important;
    max-width:115px !important;
  }
  #historyRow .poster,
  #favoritesRow .poster,
  #suggestionsRow .poster,
  #moviesRow .poster,
  #moviesRecRow .poster,
  #seriesRow .poster,
  #seriesRecRow .poster{
    width:115px !important;
    max-width:115px !important;
  }
}


/* Inercia táctil para carruseles en Android/iPhone. */
@media screen and (max-width:700px){
  .media-row.premium-scroll{
    -webkit-overflow-scrolling:touch !important;

    /* No bloquear el scroll vertical de la página al tocar
       directamente una portada del carrusel. */
    touch-action:pan-x pan-y pinch-zoom !important;

    overscroll-behavior-x:contain !important;
    scroll-snap-type:x proximity;
    scrollbar-width:none;
  }
  .media-row.premium-scroll::-webkit-scrollbar{display:none}

  /* Android / iPhone:
     Reducir únicamente las tarjetas del catálogo.
     "Continuar viendo" (#continueRow) queda intacto. */
  .catalogo-row-modern .card-link{
    flex:0 0 120px !important;
    width:120px !important;
    min-width:120px !important;
    max-width:120px !important;
  }
}

/* Categorías nuevas: mismo carrusel visual, independiente de Historial/Favoritos/Sugerencias. */
.category-row{
  display:flex !important;
  flex-wrap:nowrap !important;
  overflow-x:auto !important;
  overflow-y:hidden !important;
  gap:16px;
  scrollbar-width:none;
  -webkit-overflow-scrolling:touch;
  touch-action:pan-x;
  overscroll-behavior-x:contain;
  scroll-snap-type:x proximity;
}

/* =========================================================
   SCROLL VERTICAL EN MÓVIL SOBRE LAS PORTADAS
   El usuario puede comenzar el gesto directamente sobre la imagen
   y la página seguirá subiendo/bajando. El carrusel conserva el
   desplazamiento horizontal.
   ========================================================= */
@media(max-width:1100px){
  .catalogo-row-modern,
  .catalogo-row-modern .card-link,
  .catalogo-row-modern .xplus,
  .catalogo-row-modern .xaviec,
  .catalogo-row-modern .card-info{
    touch-action:pan-x pan-y pinch-zoom !important;
  }
}

/* =========================================================
   TARJETAS DEL CATÁLOGO REAL
   Generadas por funciones_catalogo.php
   ========================================================= */
.catalogo-row-modern{
  display:flex !important;
  flex-wrap:nowrap !important;
  overflow-x:auto !important;
  overflow-y:hidden !important;
  gap:16px;
  padding:2px 3px 14px;
  -webkit-overflow-scrolling:touch;
  scrollbar-width:none;
}
.catalogo-row-modern::-webkit-scrollbar{display:none}

.catalogo-row-modern .card-link{
  display:block;
  flex:0 0 180px;
  width:180px;
  min-width:180px;
  color:inherit;
  text-decoration:none;
}

.catalogo-row-modern .xplus{
  position:relative;
  width:100%;
  aspect-ratio:2 / 3;
  overflow:hidden;
  border-radius:14px;
  background:#11141a;
  box-shadow:0 10px 24px rgba(0,0,0,.24);
}

.catalogo-row-modern .xaviec{
  display:block;
  width:100%;
  height:100%;
  object-fit:cover;
  transition:transform .22s ease,filter .22s ease;
}

.catalogo-row-modern .card-link:hover .xaviec{
  transform:scale(1.035);
  filter:brightness(1.06);
}

.catalogo-row-modern .xplus i{
  position:absolute;
  left:0;
  right:0;
  bottom:0;
  display:block;
  padding:30px 10px 10px;
  color:#fff;
  font-style:normal;
  font-size:13px;
  font-weight:700;
  line-height:1.2;
  text-shadow:0 2px 8px rgba(0,0,0,.9);
  background:linear-gradient(transparent,rgba(0,0,0,.82));
}

.catalogo-row-modern .card-link[data-href=""]{
  cursor:not-allowed;
}

.catalogo-fila-dinamica{
  margin-bottom:28px;
}

@media(max-width:1100px){
  .catalogo-row-modern .card-link{
    flex:0 0 180px;
    width:180px;
    min-width:180px;
  }
}
/* Adaptación de títulos del catálogo en tablet/móvil.
   No afecta "Continuar viendo" ni el carrusel/hero. */
@media(max-width:1100px){
  .catalogo-row-modern .xplus i{
    padding:13px 3px 2px !important;
    font-size:12px !important;
    line-height:1.24 !important;
    min-height:31px;
  }
  .catalogo-row-modern .card-info{
    padding:6px 2px 0;
  }
  .catalogo-row-modern .card-title{
    font-size:12px;
    line-height:1.22;
  }
  .catalogo-row-modern .card-meta{
    font-size:9.5px;
    margin-top:3px;
  }
}
@media(max-width:700px){
  .catalogo-row-modern .xplus i{
    padding:11px 2px 2px !important;
    font-size:11px !important;
    line-height:1.2 !important;
    min-height:29px;
  }
  .catalogo-row-modern .card-title{
    font-size:11px;
    line-height:1.2;
  }
  .catalogo-row-modern .card-meta{
    font-size:9px;
  }
}
.category-row::-webkit-scrollbar{display:none}
.category-row .card{
  flex:0 0 190px;
  width:190px;
  min-width:190px;
  scroll-snap-align:start;
}
@media(max-width:700px){
  .media-row.category-row{gap:7px !important}
  .media-row.category-row .card,
  .category-row .card{
    flex:0 0 105px !important;
    width:105px !important;
    min-width:105px !important;
    max-width:105px !important;
  }
}

.notification-count{position:absolute;top:-4px;right:-5px;min-width:17px;height:17px;padding:0 4px;border-radius:999px;background:var(--accent);color:#fff;display:none;align-items:center;justify-content:center;font-size:9px;font-weight:800;line-height:1;box-shadow:0 0 0 2px var(--bg)}
.icon-btn{position:relative}

/* =========================================================
   CATÁLOGO — TEXTO DEBAJO DE LA IMAGEN COMO EN EL HTML
   No afecta "Continuar viendo".
   ========================================================= */
.catalogo-row-modern .xplus{
  aspect-ratio:auto !important;
  overflow:visible !important;
  box-shadow:none !important;
  background:transparent !important;
}

.catalogo-row-modern .xplus .xaviec{
  display:block;
  width:100%;
  height:auto;
  aspect-ratio:2 / 3;
  object-fit:cover;
  border-radius:14px;
}

.catalogo-row-modern .xplus i{
  position:static !important;
  display:block;
  padding:9px 2px 0 !important;
  color:#fff;
  font-style:normal;
  font-size:13px !important;
  font-weight:700;
  line-height:1.2;
  text-shadow:none !important;
  background:none !important;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* Cuando la tarjeta contiene título y metadatos separados,
   conservar el mismo aspecto tipográfico del HTML. */
.catalogo-row-modern .card-info{
  padding:9px 2px 0;
}
.catalogo-row-modern .card-title{
  font-size:13px;
  font-weight:700;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.catalogo-row-modern .card-meta{
  font-size:11px;
  color:#858a96;
  margin-top:4px;
}


/* =========================================================
   CATÁLOGO — CONTENIDO SIN ENLACE
   Las películas/series sin "enlace" se muestran, pero quedan
   oscurecidas y no permiten interacción.
   No afecta "Continuar viendo".
   ========================================================= */
.catalogo-row-modern .card-link.sin-enlace{
  cursor:not-allowed !important;
}

.catalogo-row-modern .card-link.sin-enlace .xplus{
  position:relative;
}

.catalogo-row-modern .card-link.sin-enlace .xplus::after{
  content:"";
  position:absolute;
  inset:0;
  z-index:2;
  border-radius:14px;
  background:rgba(0,0,0,.52);
  pointer-events:none;
}

.catalogo-row-modern .card-link.sin-enlace .xaviec{
  filter:brightness(.62);
  transform:none !important;
}

.catalogo-row-modern .card-link.sin-enlace:hover .xaviec{
  filter:brightness(.62);
  transform:none !important;
}

.catalogo-row-modern .card-link.sin-enlace i{
  position:static !important;
  z-index:3;
}

</style>
<style>

/* =========================================================
   AJUSTE FINAL — HERO + TARJETAS DEL CATÁLOGO
   - Acerca heroTitle de .meta en todas las pantallas.
   - Elimina la altura mínima artificial del título.
   - Android/iPhone: título un poco más pequeño.
   - Android/iPhone: reduce SOLO las tarjetas del catálogo.
   - NO modifica el carrusel ni Continuar viendo.
   ========================================================= */

/* PC / escritorio */
.hero h1#heroTitle{
  min-height:0 !important;
  margin-bottom:8px !important;
}

@media (min-width:1101px){
  .hero h1#heroTitle{
    font-size:clamp(34px,4vw,56px) !important;
    line-height:1.02 !important;
    margin-bottom:8px !important;
  }
  .hero .meta{
    margin-top:0 !important;
  }
}

/* Tablet */
@media (min-width:701px) and (max-width:1100px){
  .hero h1#heroTitle{
    font-size:clamp(32px,5.2vw,50px) !important;
    line-height:1.02 !important;
    letter-spacing:-1.5px !important;
    min-height:0 !important;
    margin-bottom:7px !important;
  }
  .hero .meta{
    margin-top:0 !important;
  }
}

/* Android / iPhone */
@media (max-width:700px){
  .hero h1#heroTitle{
    font-size:clamp(25px,7.8vw,36px) !important;
    line-height:1.04 !important;
    letter-spacing:-.8px !important;
    min-height:0 !important;
    margin-bottom:6px !important;
  }

  .hero .meta{
    font-size:10px !important;
    gap:6px !important;
    margin-top:0 !important;
    margin-bottom:9px !important;
  }

  /* Solo tarjetas normales de películas/series.
     La tarjeta se ensancha un poco, pero la imagen conserva exactamente
     los 112px actuales. No toca carrusel ni "Continuar viendo". */
  .catalogo-row-modern .card-link{
    flex:0 0 128px !important;
    width:128px !important;
    min-width:128px !important;
    max-width:128px !important;
  }

  .catalogo-row-modern .xplus{
    width:112px !important;
    min-width:112px !important;
    max-width:112px !important;
  }

  .catalogo-row-modern .xplus .xaviec{
    width:112px !important;
    max-width:112px !important;
  }

  .catalogo-row-modern .card-info{
    width:112px !important;
    max-width:112px !important;
  }
}

</style>
</head>
<body>

<div id="loader-screen">

    <div class="loader-box">

        <!-- NOMBRE -->
        <h1 class="loader-title">
            Movie<span>Tx</span>
        </h1>

        <!-- TEXTO -->
        <p class="loader-text" id="loader-message">
            Preparando contenido
        </p>

        <!-- BARRA -->
        <div class="loader-progress">

            <div class="loader-progress-bar">
                <div
                    class="loader-progress-fill"
                    id="loading-fill">
                </div>
            </div>

            <div class="loader-info">

                <span>Cargando</span>

                <span id="loading-percent">
                    0%
                </span>

            </div>

        </div>

        <!-- PUNTO DE ESTADO -->
        <div class="loader-status">

            <span class="status-dot"></span>

            <span>
                Un momento...
            </span>

        </div>

    </div>

</div>

<style>

/* =========================================================
   🔒 BLOQUEO INMEDIATO DEL DOCUMENTO
   Se activa desde que el navegador encuentra este CSS.
========================================================= */

html,
body{
    overscroll-behavior:none;
}


/* =========================================================
   RESET
========================================================= */

#loader-screen,
#loader-screen *{
    box-sizing:border-box;
}


/* =========================================================
   🔒 BLOQUEAR PAGINA DURANTE EL LOADER
========================================================= */




/* =========================================================
   BLOQUEO ADICIONAL DEL BODY
========================================================= */




/* =========================================================
   PANTALLA
========================================================= */

#loader-screen{

    position:fixed;

    inset:0;

    width:100%;
    height:100vh;
    height:100dvh;

    display:flex;

    align-items:center;
    justify-content:center;

    padding:25px;

    background:#08090d;

    z-index:999999;

    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;

    opacity:1;

    visibility:visible;

    pointer-events:auto;

    user-select:none;

    -webkit-user-select:none;

    transition:
        opacity .55s ease,
        visibility .55s ease;

}


/* =========================================================
   🔒 EVITAR CUALQUIER INTERACCION DETRAS
========================================================= */

#loader-screen{

    touch-action:auto;

    overscroll-behavior:none;

}


/* =========================================================
   OCULTAR
========================================================= */

#loader-screen.hidden{

    opacity:0;

    visibility:hidden;

    pointer-events:none;

}


/* =========================================================
   CAJA PRINCIPAL
========================================================= */

.loader-box{

    width:100%;

    max-width:420px;

    text-align:center;

    animation:
        loaderAppear .6s ease both;

}


@keyframes loaderAppear{

    from{

        opacity:0;

        transform:
            translateY(12px);

    }

    to{

        opacity:1;

        transform:
            translateY(0);

    }

}


/* =========================================================
   TITULO
========================================================= */

.loader-title{

    margin:0 0 12px;

    font-size:clamp(
        2.4rem,
        7vw,
        3.6rem
    );

    line-height:1;

    font-weight:800;

    letter-spacing:-1.5px;

    color:#ffffff;

}


.loader-title span{

    color:#e50914;

}


/* =========================================================
   TEXTO
========================================================= */

.loader-text{

    margin:0 0 32px;

    min-height:20px;

    color:rgba(
        255,
        255,
        255,
        .55
    );

    font-size:.9rem;

    font-weight:500;

    letter-spacing:.3px;

    transition:
        opacity .2s ease;

}


/* =========================================================
   PROGRESO
========================================================= */

.loader-progress{

    width:100%;

}


/* =========================================================
   BARRA
========================================================= */

.loader-progress-bar{

    position:relative;

    width:100%;

    height:4px;

    overflow:hidden;

    border-radius:20px;

    background:
        rgba(
            255,
            255,
            255,
            .10
        );

}


/* =========================================================
   PROGRESO ACTUAL
========================================================= */

.loader-progress-fill{

    width:0%;

    height:100%;

    border-radius:20px;

    background:#e50914;

    box-shadow:
        0 0 12px
        rgba(
            229,
            9,
            20,
            .55
        );

    transition:
        width .2s ease;

}


/* =========================================================
   INFORMACION DE LA BARRA
========================================================= */

.loader-info{

    display:flex;

    align-items:center;

    justify-content:space-between;

    margin-top:10px;

    font-size:.72rem;

    font-weight:600;

    color:
        rgba(
            255,
            255,
            255,
            .40
        );

}


#loading-percent{

    color:
        rgba(
            255,
            255,
            255,
            .75
        );

}


/* =========================================================
   ESTADO
========================================================= */

.loader-status{

    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    margin-top:28px;

    color:
        rgba(
            255,
            255,
            255,
            .35
        );

    font-size:.72rem;

}


/* =========================================================
   PUNTO
========================================================= */

.status-dot{

    width:6px;

    height:6px;

    flex-shrink:0;

    border-radius:50%;

    background:#e50914;

    box-shadow:
        0 0 8px
        rgba(
            229,
            9,
            20,
            .7
        );

    animation:
        statusPulse 1.3s ease-in-out infinite;

}


@keyframes statusPulse{

    0%,
    100%{

        opacity:.45;

        transform:scale(.8);

    }

    50%{

        opacity:1;

        transform:scale(1);

    }

}


/* =========================================================
   📱 MOVIL
========================================================= */

@media(max-width:480px){

    #loader-screen{

        padding:
            20px;

    }


    .loader-box{

        max-width:330px;

    }


    .loader-title{

        font-size:2.5rem;

    }


    .loader-text{

        font-size:.82rem;

        margin-bottom:28px;

    }


    .loader-progress-bar{

        height:4px;

    }


    .loader-status{

        margin-top:24px;

        font-size:.68rem;

    }

}


/* =========================================================
   📱 MOVILES MUY PEQUEÑOS
========================================================= */

@media(max-width:360px){

    .loader-title{

        font-size:2.2rem;

    }


    .loader-text{

        font-size:.78rem;

    }

}


/* =========================================================
   💻 PC GRANDE
========================================================= */

@media(min-width:1200px){

    .loader-box{

        max-width:440px;

    }


    .loader-title{

        font-size:3.8rem;

    }


    .loader-text{

        font-size:.95rem;

    }

}

</style>


<script>

/* =========================================================
   🔒 BLOQUEO INMEDIATO
   Se ejecuta ANTES de DOMContentLoaded.
   
   Esto evita que el usuario pueda:
   - bajar
   - subir
   - usar rueda del mouse
   - deslizar en Android/iPhone
   - hacer overscroll
========================================================= */

document.documentElement.classList.add(
    "loader-active"
);


/* =========================================================
   🔒 BLOQUEAR TECLAS DE SCROLL
   PC / TV / TECLADO
========================================================= */

document.addEventListener(
    "keydown",
    function(e){

        if(
            !document.documentElement.classList.contains(
                "loader-active"
            )
        ){

            return;

        }


        const teclasBloqueadas = [

            "ArrowUp",
            "ArrowDown",
            "PageUp",
            "PageDown",
            "Home",
            "End",
            " "

        ];


        if(
            teclasBloqueadas.includes(e.key)
        ){

            e.preventDefault();

        }

    },
    {
        passive:false
    }
);


/* =========================================================
   LOADER
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function(){

        /* =====================================================
           ELEMENTOS
        ===================================================== */

        const loader =
            document.getElementById(
                "loader-screen"
            );


        const fill =
            document.getElementById(
                "loading-fill"
            );


        const percent =
            document.getElementById(
                "loading-percent"
            );


        const message =
            document.getElementById(
                "loader-message"
            );


        /* =====================================================
           COMPROBAR ELEMENTOS
        ===================================================== */

        if(
            !loader ||
            !fill ||
            !percent
        ){

            return;

        }


        /* =====================================================
           🔒 BLOQUEAR PAGINA
           Se mantiene como segunda capa de seguridad.
        ===================================================== */

        document.body.classList.add(
            "loading"
        );


        /* =====================================================
           MENSAJES
        ===================================================== */

        const texts = [

            "Preparando contenido",

            "Cargando catálogo",

            "Buscando películas",

            "Preparando series",

            "Casi listo"

        ];


        let textIndex = 0;


        /* =====================================================
           CAMBIO DE MENSAJES
        ===================================================== */

        const textInterval =
            setInterval(
                function(){

                    textIndex =
                        (
                            textIndex + 1
                        ) %
                        texts.length;


                    if(message){

                        message.style.opacity =
                            "0";


                        setTimeout(
                            function(){

                                message.textContent =
                                    texts[textIndex];

                                message.style.opacity =
                                    "1";

                            },
                            120
                        );

                    }

                },
                900
            );


        /* =====================================================
           PROGRESO
        ===================================================== */

        let progress = 0;

        let finished = false;


        function updateProgress(
            value
        ){

            progress =
                Math.min(
                    100,
                    value
                );


            fill.style.width =
                progress + "%";


            percent.textContent =
                Math.floor(
                    progress
                ) + "%";

        }


        /* =====================================================
           CARGA SUAVE
        ===================================================== */

        const progressInterval =
            setInterval(
                function(){

                    if(
                        progress < 90
                    ){

                        /*
                         * Progreso lento y natural.
                         * No llega al 100% hasta
                         * que la página termina.
                         */

                        const increase =
                            Math.random() *
                            3;


                        updateProgress(
                            progress +
                            increase
                        );

                    }

                },
                140
            );


        /* =====================================================
           FINALIZAR
        ===================================================== */

        function finishLoader(){

            if(finished){

                return;

            }


            finished = true;


            clearInterval(
                progressInterval
            );


            clearInterval(
                textInterval
            );


            /* ================================================
               LLEGAR AL 100%
            ================================================ */

            let finalProgress =
                progress;


            const finalInterval =
                setInterval(
                    function(){

                        finalProgress += 4;


                        if(
                            finalProgress >=
                            100
                        ){

                            finalProgress =
                                100;

                        }


                        updateProgress(
                            finalProgress
                        );


                        if(
                            finalProgress >=
                            100
                        ){

                            clearInterval(
                                finalInterval
                            );


                            /* =================================
                               OCULTAR
                            ================================= */

                            setTimeout(
                                function(){

                                    loader.classList.add(
                                        "hidden"
                                    );


                                    /*
                                     * 🔓 LIBERAR SCROLL
                                     */

                                    document.body.classList.remove(
                                        "loading"
                                    );


                                    document.documentElement.classList.remove(
                                        "loader-active"
                                    );


                                    /*
                                     * Restaurar comportamiento
                                     * normal del documento.
                                     */

                                    document.documentElement.style.overflow =
                                        "";

                                    document.body.style.overflow =
                                        "";


                                    /* =============================
                                       ELIMINAR DEL DOM
                                    ============================= */

                                    setTimeout(
                                        function(){

                                            if(
                                                loader &&
                                                loader.parentNode
                                            ){

                                                loader.remove();

                                            }

                                        },
                                        600
                                    );

                                },
                                250
                            );

                        }

                    },
                    20
                );

        }


        /* =====================================================
           CUANDO TERMINA DE CARGAR LA PAGINA
        ===================================================== */

        window.addEventListener(
            "load",
            function(){

                setTimeout(
                    function(){

                        finishLoader();

                    },
                    250
                );

            }
        );


        /* =====================================================
           🔐 SEGURIDAD
           
           Si por algún motivo el evento LOAD no responde,
           el loader se cerrará después de 5 segundos.
        ===================================================== */

        setTimeout(
            function(){

                finishLoader();

            },
            5000
        );

    }
);

</script>


<header class="app-header" id="header">
  <a class="logo" href="inicio.php">Movie<span>Tx</span></a>

  <nav class="main-nav">
    <button class="active" data-view="inicio">Inicio</button>
    <button data-view="peliculas">Películas</button>
    <button data-view="series">Series</button>
    <?php if ($adultoActivo && !$esPerfilKids): ?>
    <button data-view="adulto" id="adultNavBtn" class="adult-nav-item">Adulto</button>
    <?php endif; ?>
  </nav>

  <div class="header-right">
    <!-- Search -->
    <a class="icon-btn" href="Mostrar-Mas/Buscador.php" title="Buscar" aria-label="Buscar">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 5 5"/></svg>
    </a>

    <!-- Sugerencias -->
    <a class="icon-btn header-suggestions" href="sugerencias.php" title="Sugerencias" aria-label="Sugerencias">
      <svg viewBox="0 0 24 24"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M8.3 14.5A6 6 0 1 1 15.7 14c-.9.7-1.7 1.5-1.7 2.5h-4c0-1-.8-1.8-1.7-2.5Z"/><path d="M12 2v1.2"/></svg>
    </a>

    <!-- Favoritos -->
    <a class="icon-btn" href="View-Peliculas/favoritos.php" title="Favoritos" aria-label="Favoritos">
      <svg viewBox="0 0 24 24"><path d="M20.8 8.8c0 5.1-8.8 10-8.8 10s-8.8-4.9-8.8-10A4.8 4.8 0 0 1 12 6a4.8 4.8 0 0 1 8.8 2.8Z"/></svg>
    </a>

    <!-- Historial -->
    <a class="icon-btn" href="View-Peliculas/historial_usuario.php" title="Historial" aria-label="Historial">
      <svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 7v5l3 2"/></svg>
    </a>

    <!-- Notificaciones: el indicador solo existe si la BD tiene pendientes -->
    <button class="icon-btn" id="notificationBtn" title="Notificaciones" aria-label="Notificaciones">
      <svg viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
      <?php if ($notificacionesNoLeidasUsuario > 0): ?>
        <span class="notification-count" id="notificationCount" aria-label="Número de notificaciones">
          <?= $notificacionesNoLeidasUsuario > 99 ? '99+' : (int)$notificacionesNoLeidasUsuario ?>
        </span>
      <?php endif; ?>
    </button>

    <!-- Usuario / Perfil activo oculto: no se muestra junto a notificaciones. -->

    <!-- Ajustes -->
    <div class="settings-wrap" id="settingsWrap">
      <button class="icon-btn settings-icon" id="settingsBtn" title="Cuenta y ajustes" aria-label="Cuenta y ajustes">
        <svg viewBox="0 0 24 24">
          <circle cx="12" cy="8" r="3.2"/>
          <path d="M5.5 20c.7-3.5 2.8-5.2 6.5-5.2s5.8 1.7 6.5 5.2"/>
        </svg>
      </button>
      <div class="settings-menu">
  <div class="settings-user">
    <img
      src="<?= htmlspecialchars($fotoActualHeader, ENT_QUOTES, 'UTF-8') ?>"
      alt="<?= htmlspecialchars($nombreActualHeader, ENT_QUOTES, 'UTF-8') ?>"
      onerror="this.onerror=null;this.src='Logo Poster MovieTx PNG/Logo MovieTx.png';"
    >
    <div>
      <strong><?= htmlspecialchars($nombreActualHeader, ENT_QUOTES, 'UTF-8') ?></strong>
      <small>
        <?= htmlspecialchars($tipoActualHeader, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($esPerfilKids): ?> · KIDS<?php endif; ?>
      </small>
    </div>
  </div>
  <a href="Dashboard/Dashboard.php"><span class="menu-svg"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20c.7-3.5 2.8-5.2 6.5-5.2s5.8 1.7 6.5 5.2"/></svg></span><span class="menu-copy"><b>Mi cuenta</b><small>Cuenta y datos</small></span></a>
  <a href="perfiles.php"><span class="menu-svg"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="16.5" cy="9.5" r="2.3"/><path d="M3.5 20c.5-3.6 2.3-5.4 5.5-5.4s5 1.8 5.5 5.4"/><path d="M14 15.5c2.7 0 4.5 1.3 5 4.5"/></svg></span><span class="menu-copy"><b>Perfiles</b><small>Cambiar perfil</small></span></a>
  <a href="logout.php" class="logout"><span class="menu-svg"><svg viewBox="0 0 24 24"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5"/><path d="m14 16 4-4-4-4"/><path d="M18 12H8"/></svg></span><span class="menu-copy"><b>Cerrar sesión</b><small>Salir de MovieTx</small></span></a>
</div></div>
  </div>
</header>
<div class="notification-backdrop" id="notificationBackdrop" aria-hidden="true"></div>
<div class="notification-panel" id="notificationPanel">
  <div class="notification-head">
    <div class="notification-profile">
      <div class="notification-bell">
        <svg viewBox="0 0 24 24"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
      </div>
      <div><strong>Notificaciones</strong><span>Tienes novedades para ti</span></div>
    </div>
    <button class="notification-close" id="notificationClose">×</button>
  </div>
  <div class="notification-tabs" role="tablist" aria-label="Filtrar notificaciones">
    <button class="active" type="button" data-notification-filter="all" role="tab" aria-selected="true">
      Todas <b id="notificationTotalCount"><?= (int)$notificacionesTotalesUsuario ?></b>
    </button>
  </div>
  <div class="notification-list" id="notificationList">
    <div class="notification-empty" id="notificationEmpty">Cargando notificaciones...</div>
  </div>
  <div class="notification-footer"><a href="Notificaciones.php">Ver todas las notificaciones</a></div>
</div>

<!-- DETALLE DE NOTIFICACIÓN -->
<div class="notification-detail-backdrop" id="notificationDetailBackdrop" aria-hidden="true"></div>
<div class="notification-detail-modal" id="notificationDetailModal" role="dialog" aria-modal="true" aria-labelledby="notificationDetailTitle" aria-hidden="true">
  <div class="notification-detail-head">
    <div>
      <span class="notification-detail-kicker" id="notificationDetailKicker">Notificación</span>
      <h3 id="notificationDetailTitle">Notificación</h3>
    </div>
    <button class="notification-detail-close" id="notificationDetailClose" type="button" aria-label="Cerrar">×</button>
  </div>
  <div class="notification-detail-body">
    <div class="notification-detail-media" id="notificationDetailMedia" hidden></div>
    <div class="notification-detail-scroll" id="notificationDetailScroll">
      <div class="notification-detail-message" id="notificationDetailMessage"></div>
    </div>
    <button class="notification-detail-play" id="notificationDetailPlay" type="button" hidden>▶ Reproducir</button>
  </div>
</div>

<main id="app"
      data-user-id="<?= (int)$userId ?>"
      data-profile-active="<?= $perfilActivo ? '1' : '0' ?>"
      data-profile-id="<?= (int)($perfilId ?? 0) ?>"
      data-profile-type="<?= htmlspecialchars((string)($tipoPerfilNotificaciones ?? 'normal'), ENT_QUOTES, 'UTF-8') ?>"
      data-is-kids="<?= (($tipoPerfilNotificaciones ?? '') === 'kids') ? '1' : '0' ?>">
<?php
$heroInicial = $carruselHeroSets['inicio'][0] ?? null;
$heroTituloInicial = is_array($heroInicial) ? (string)($heroInicial[0] ?? '') : '';
$heroAnioInicial = is_array($heroInicial) ? (string)($heroInicial[1] ?? '') : '';
$heroGeneroInicial = is_array($heroInicial) ? (string)($heroInicial[2] ?? '') : '';
$heroDescripcionInicial = is_array($heroInicial) ? (string)($heroInicial[3] ?? '') : '';
$heroImagenInicial = is_array($heroInicial) ? (string)($heroInicial[4] ?? '') : '';
$heroTipoInicial = is_array($heroInicial) ? (string)($heroInicial[8] ?? 'PELÍCULA DESTACADA') : 'PELÍCULA DESTACADA';
$heroCalidadInicial = is_array($heroInicial) ? (string)($heroInicial[9] ?? 'HD') : 'HD';
?>
<section class="hero" id="hero">
  <div class="hero-bg" id="heroBg"<?php if ($heroImagenInicial !== ''): ?> style="background-image:url(<?= htmlspecialchars($heroImagenInicial, ENT_QUOTES, 'UTF-8') ?>)"<?php endif; ?>></div>
  <div class="hero-content">
    <div class="eyebrow" id="heroType"><?= htmlspecialchars($heroTipoInicial, ENT_QUOTES, 'UTF-8') ?></div>
    <h1 id="heroTitle"><?= htmlspecialchars($heroTituloInicial, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="meta"><span id="heroYear"><?= htmlspecialchars($heroAnioInicial, ENT_QUOTES, 'UTF-8') ?></span><span class="dot"></span><span id="heroGenre"><?= htmlspecialchars($heroGeneroInicial, ENT_QUOTES, 'UTF-8') ?></span><span class="dot"></span><span id="heroQuality"><?= htmlspecialchars($heroCalidadInicial, ENT_QUOTES, 'UTF-8') ?></span></div>
    <p id="heroDesc"><?= htmlspecialchars($heroDescripcionInicial, ENT_QUOTES, 'UTF-8') ?></p>
    <div class="hero-actions">
      <button class="primary" id="heroPlayBtn" type="button">▶ Reproducir</button><button class="secondary" id="heroListBtn" type="button">＋ Mi lista</button>
    </div>
  </div>
  <div class="hero-dots" id="heroDots"></div>
</section>

<div class="page">
  <!-- INICIO -->
  <div class="view" id="view-inicio">
    <section class="section continue-section" data-continue-filter="all" style="<?= empty($continuarCatalogo) ? 'display:none' : '' ?>">
      <div class="section-head"><h2>Continuar viendo</h2><span class="section-sub">Sigue donde lo dejaste</span></div>
      <div class="media-row premium-scroll" id="continueRow"></div>
    </section>

    <div id="catalogo-inicio-actualizable">
      <?php echo renderFilasInicioCatalogoModerno(); ?>
    </div>
  </div>

  <!-- PELÍCULAS -->
  <div class="view" id="view-peliculas" style="display:none">
    <section class="section continue-section" data-continue-filter="pelicula" style="<?= empty(array_filter($continuarCatalogo, static fn($item) => ($item['type'] ?? '') === 'pelicula')) ? 'display:none' : '' ?>">
      <div class="section-head"><h2>Continuar viendo</h2><span class="section-sub">Solo películas</span></div>
      <div class="media-row premium-scroll" id="continueMoviesRow"></div>
    </section>
    <div class="catalogo-menu-actualizable" data-menu="peliculas">
      <?php echo renderFilasPeliculasCatalogoModerno(); ?>
    </div>
  </div>

  <!-- ADULTO: solo se muestra cuando el usuario activa Contenido adulto -->
  <div class="view" id="view-adulto" style="display:none">
    <div class="catalogo-menu-actualizable" data-menu="adulto">
      <?php echo renderFilaAdultoCatalogoModerno(); ?>
    </div>
  </div>

  <!-- SERIES -->
  <div class="view" id="view-series" style="display:none">
    <section class="section continue-section" data-continue-filter="serie" style="<?= empty(array_filter($continuarCatalogo, static fn($item) => ($item['type'] ?? '') === 'serie')) ? 'display:none' : '' ?>">
      <div class="section-head"><h2>Continuar viendo</h2><span class="section-sub">Solo series</span></div>
      <div class="media-row premium-scroll" id="continueSeriesRow"></div>
    </section>
    <div class="catalogo-menu-actualizable" data-menu="series">
      <?php echo renderFilasSeriesCatalogoModerno(); ?>
    </div>
  </div>
</div>
</main>

<nav class="mobile-nav">
  <button class="active" data-view="inicio"><svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"/></svg>Inicio</button>
  <button data-view="peliculas"><svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 3v18M16 3v18M4 8h4M16 8h4M4 16h4M16 16h4"/></svg>Películas</button>
  <button data-view="series"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3Z"/></svg>Series</button>
  <button data-view="adulto" id="adultMobileNavBtn" class="adult-nav-item"<?= ($adultoActivo && !$esPerfilKids) ? '' : ' hidden' ?>><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8M12 8v8"/></svg>Adulto</button>
  <button onclick="location.href='sugerencias.php'"><svg viewBox="0 0 24 24"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M8.3 14.5A6 6 0 1 1 15.7 14c-.9.7-1.7 1.5-1.7 2.5h-4c0-1-.8-1.8-1.7-2.5Z"/><path d="M12 2v1.2"/></svg>Sugerencias</button>
  <button class="icon-btn settings-icon" id="mobileSettingsBtn" type="button" title="Cuenta y ajustes" aria-label="Cuenta y ajustes"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20c.7-3.5 2.8-5.2 6.5-5.2s5.8 1.7 6.5 5.2"/></svg>Ajustes</button>
</nav>

<script>
const data={
 continuar: <?= json_encode($continuarCatalogo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};

const heroSets = <?= json_encode($carruselHeroSets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function card(item, continueCard=false){
  let t='',m='',img='',p=0,type='',id='',url='';
  if(Array.isArray(item)){[t,m,img]=item;}else{({t,m,img,p,type,id,url}=item);}
  const body=`<div class="poster"><img src="${img}" alt="${t}" loading="lazy">${continueCard?`<button class="continue-remove" type="button" aria-label="Eliminar ${t} de Continuar viendo" title="Eliminar de Continuar viendo"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button><span class="play-center"><svg viewBox="0 0 24 24"><path d="m8 5 11 7-11 7Z"/></svg></span><span class="progress"><i style="width:${p||0}%"></i></span><span class="continue-percent">${Math.round(Number(p)||0)}%</span>`:''}</div><div class="card-info"><div class="card-title">${t}</div><div class="card-meta">${m}</div></div>`;
  return `<article class="card${continueCard?' continue-card':''}" ${continueCard?`data-continue-id="${id}" data-continue-type="${type}" data-continue-url="${url}"`:''}>${body}</article>`;
}

function fill(id,items,cont=false){
  const el=document.getElementById(id);
  if(!el) return;
  el.innerHTML=items.map(item=>card(item,cont)).join('');
}

function getContinueItems(type=null){
  return data.continuar.filter(item => !type || item.type===type);
}

function updateContinueSectionsVisibility(){
  const groups = [
    {section:'#view-inicio .continue-section', type:null},
    {section:'#view-peliculas .continue-section', type:'pelicula'},
    {section:'#view-series .continue-section', type:'serie'}
  ];
  groups.forEach(group=>{
    const section=document.querySelector(group.section);
    if(!section) return;
    section.style.display=getContinueItems(group.type).length ? '' : 'none';
  });
}

function renderContinue(){
  fill('continueRow',getContinueItems(),true);
  fill('continueMoviesRow',getContinueItems('pelicula'),true);
  fill('continueSeriesRow',getContinueItems('serie'),true);
  updateContinueSectionsVisibility();
}

/*
 * La eliminación debe guardarse en la base de datos, no en localStorage:
 * así se sincroniza entre dispositivos y el contenido puede volver a
 * aparecer cuando el usuario lo reproduce nuevamente.
 */
async function removeContinue(id, type, button){
  if(!id || !type || (button && button.dataset.busy === '1')) return;
  if(button){
    button.dataset.busy='1';
    button.disabled=true;
  }

  try{
    const response=await fetch('View-Peliculas/eliminar_historial.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body:'movie_id='+encodeURIComponent(id)+'&tipo='+encodeURIComponent(type),
      cache:'no-store',
      credentials:'same-origin'
    });
    const result=await response.json();

    if(!response.ok || result.status!=='success'){
      throw new Error(result.msg || 'No se pudo eliminar el contenido.');
    }

    // Quitar el elemento de la lista en memoria y volver a pintar las tres vistas.
    data.continuar=data.continuar.filter(item=>
      !(String(item.id)===String(id) && String(item.type).toLowerCase()===String(type).toLowerCase())
    );
    renderContinue();
  }catch(error){
    console.error('Error al eliminar de Continuar viendo:',error);
    if(button){
      button.dataset.busy='0';
      button.disabled=false;
    }
    alert('No se pudo eliminar de Continuar viendo. Comprueba tu conexión e inténtalo de nuevo.');
  }
}

document.addEventListener('click',event=>{
  const removeButton=event.target.closest('.continue-remove');
  if(removeButton){
    event.preventDefault();
    event.stopPropagation();
    const cardElement=removeButton.closest('.continue-card');
    if(cardElement){
      removeContinue(cardElement.dataset.continueId,cardElement.dataset.continueType,removeButton);
    }
    return;
  }
  const continueCard=event.target.closest('.continue-card');
  if(continueCard){
    event.preventDefault();
    const url=continueCard.dataset.continueUrl||'';
    if(url) window.location.href=url;
  }
});

/* =========================================================
   CATÁLOGO
   ---------------------------------------------------------
   Las filas de Tendencias, Populares y demás se generan en
   PHP mediante funciones_catalogo.php + contenido.php.
   ========================================================= */

renderContinue();

/* =========================================================
   ACTUALIZACIÓN AUTOMÁTICA DEL CATÁLOGO REAL
   ========================================================= */
(function(){
  'use strict';

  const catalogo = document.getElementById('catalogo-inicio-actualizable');
  if (!catalogo) return;

  let versionCatalogo = '';
  let actualizacionEnCurso = false;
  const INTERVALO = 5000;

  async function actualizarCatalogo(){
    if (actualizacionEnCurso || document.hidden) return;

    actualizacionEnCurso = true;

    try {
      const datos = new FormData();
      datos.append('actualizar_catalogo_inicio', '1');

      const respuesta = await fetch(window.location.href, {
        method: 'POST',
        body: datos,
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Cache-Control': 'no-cache'
        }
      });

      if (!respuesta.ok) throw new Error('HTTP ' + respuesta.status);

      const dataCatalogo = await respuesta.json();

      if (!dataCatalogo || !dataCatalogo.success) return;

      const nuevaVersion = String(dataCatalogo.version || '');

      if (!versionCatalogo) {
        versionCatalogo = nuevaVersion;
        return;
      }

      if (nuevaVersion === versionCatalogo) return;

      versionCatalogo = nuevaVersion;
      catalogo.innerHTML = dataCatalogo.html || '';
    } catch (error) {
      /* Una falla de actualización no debe romper el resto de la página. */
    } finally {
      actualizacionEnCurso = false;
    }
  }

  window.setTimeout(actualizarCatalogo, 800);
  window.setInterval(actualizarCatalogo, INTERVALO);

  document.addEventListener('visibilitychange', function(){
    if (!document.hidden) actualizarCatalogo();
  });
})();

let currentView='inicio',heroIndex=0,heroTimer;
function setHero(view,index=0){
 const set=Array.isArray(heroSets[view]) ? heroSets[view] : [];
 if(!set.length){ clearInterval(heroTimer); document.getElementById('heroDots').innerHTML=''; return; }
 heroIndex=((index%set.length)+set.length)%set.length;
 const h=set[heroIndex];
 document.getElementById('heroBg').style.backgroundImage=h[4]?`url("${h[4]}")`:'none';
 document.getElementById('heroTitle').textContent=h[0]||'';
 document.getElementById('heroYear').textContent=h[1]||'';
 document.getElementById('heroGenre').textContent=h[2]||'';
 document.getElementById('heroDesc').textContent=h[3]||'';
 document.getElementById('heroType').textContent=h[8] || (h[7]==='serie'?'SERIE DESTACADA':'PELÍCULA DESTACADA');
 document.getElementById('heroQuality').textContent=h[9] || 'HD';
 document.getElementById('heroDots').innerHTML=set.map((_,i)=>`<button class="${i===heroIndex?'active':''}" onclick="setHero('${view}',${i})"></button>`).join('');
 clearInterval(heroTimer); heroTimer=setInterval(()=>setHero(view,heroIndex+1),6500);
}
function switchView(view){
 if(view==='adulto' && !adultEnabledServer) return;
 currentView=view;
 document.querySelectorAll('.view').forEach(v=>v.style.display='none');
 document.getElementById('view-'+view).style.display='block';
 document.querySelectorAll('[data-view]').forEach(b=>b.classList.toggle('active',b.dataset.view===view));
 setHero(view,0);window.scrollTo({top:0,behavior:'smooth'});
}
document.querySelectorAll('[data-view]').forEach(b=>b.addEventListener('click',()=>switchView(b.dataset.view)));



/* ===== CONTENIDO ADULTO ===== */
const adultEnabledServer = <?= ($adultoActivo && !$esPerfilKids) ? 'true' : 'false' ?>;
const adultNavBtn=document.getElementById('adultNavBtn');
const adultMobileNavBtn=document.getElementById('adultMobileNavBtn');
function applyAdultVisibility(){const enabled=adultEnabledServer;[adultNavBtn,adultMobileNavBtn].forEach(btn=>{if(btn)btn.hidden=!enabled;});if(!enabled&&currentView==='adulto')switchView('inicio');}
applyAdultVisibility();


const notificationBtn=document.getElementById('notificationBtn');
const notificationPanel=document.getElementById('notificationPanel');
const notificationClose=document.getElementById('notificationClose');
const notificationBackdrop=document.getElementById('notificationBackdrop');

function setNotifications(open){
 notificationPanel.classList.toggle('open',open);
 notificationBackdrop.classList.toggle('open',open);
 document.body.classList.toggle('modal-open',open);
 notificationBackdrop.setAttribute('aria-hidden',open?'false':'true');
 if(open){ document.getElementById('settingsWrap').classList.remove('open'); document.body.classList.remove('settings-open'); loadUserNotifications(); }
}
notificationBtn.addEventListener('click',e=>{e.stopPropagation();setNotifications(!notificationPanel.classList.contains('open'));});
notificationClose.addEventListener('click',()=>setNotifications(false));
notificationBackdrop.addEventListener('click',()=>setNotifications(false));
notificationPanel.addEventListener('click',e=>e.stopPropagation());

let userNotifications=[];let notificationsLoading=false;
function escNotif(v){return String(v??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));}
function updateNotificationBadge(unread,total){
 const count=document.getElementById('notificationCount');
 const totalCount=document.getElementById('notificationTotalCount');

 if(totalCount) totalCount.textContent=String(total);

 /*
  * El HTML del indicador rojo NO se crea cuando no hay pendientes.
  * Si llegan notificaciones nuevas, se crea aquí; si se leen todas,
  * se elimina completamente del DOM.
  */
 if(unread>0){
   let badge=count;

   if(!badge){
     const button=document.getElementById('notificationBtn');
     if(button){
       badge=document.createElement('span');
       badge.className='notification-count';
       badge.id='notificationCount';
       badge.setAttribute('aria-label','Número de notificaciones');
       button.appendChild(badge);
     }
   }

   if(badge){
     badge.textContent=unread>99?'99+':String(unread);
     badge.style.display='flex';
   }
 }else if(count){
   count.remove();
 }
}

function renderUserNotifications(){
 const list=document.getElementById('notificationList');
 if(!list)return;

 const unread=userNotifications.filter(n=>Number(n.leida)===0).length;
 updateNotificationBadge(unread,userNotifications.length);

 const allTab=document.querySelector('[data-notification-filter="all"] b');
 if(allTab)allTab.textContent=String(userNotifications.length);

 if(!userNotifications.length){
   list.innerHTML='<div class="notification-empty">No tienes notificaciones.</div>';
   return;
 }

 const filter=document.querySelector('[data-notification-filter].active')?.dataset.notificationFilter||'all';
 const rows=filter==='new' ? userNotifications.filter(n=>Number(n.leida)===0) : userNotifications;

 if(!rows.length){
   list.innerHTML='<div class="notification-empty">No tienes notificaciones.</div>';
   return;
 }

 list.innerHTML=rows.map(n=>{
   const isContent=String(n.tipo||'').toLowerCase()==='contenido_nuevo';
   const image=n.imagen ? `<img class="notice-img" src="${escNotif(n.imagen)}" alt="" loading="lazy">` : '';
   const shortMessage=String(n.mensaje||'').replace(/\s+/g,' ').trim();
   const hint=isContent ? 'Toca para ver la ficha y reproducir' : 'Toca para leer el mensaje completo';

   return `<article class="notice${Number(n.leida)===0?' notice-new':''}${isContent?' notice-content':''}" data-notification-id="${Number(n.id)}" data-notification="${Number(n.leida)===0?'new':'old'}">${image}<div class="notice-body"><div class="notice-top"><span class="notice-title">${escNotif(n.titulo)}</span><span class="notice-time">${escNotif(n.creado_at)}</span></div><div class="notice-text">${escNotif(shortMessage)}</div><div class="notice-hint">${hint}</div></div></article>`;
 }).join('');
}
async function loadUserNotifications(){
 if(notificationsLoading)return;
 notificationsLoading=true;

 try{
   const fd=new FormData();
   fd.append('get_user_notifications','1');

   const r=await fetch(window.location.href,{
     method:'POST',
     body:fd,
     cache:'no-store',
     credentials:'same-origin',
     headers:{
       'X-Requested-With':'XMLHttpRequest',
       'Cache-Control':'no-cache'
     }
   });

   if(!r.ok)throw new Error('HTTP '+r.status);

   const d=await r.json();

   if(d&&d.success){
     userNotifications=Array.isArray(d.items)?d.items:[];
     renderUserNotifications();
   }
 }catch(e){
   /*
    * El error no muestra una insignia falsa.
    * Solo se informa dentro del panel si el usuario lo abrió.
    */
   const x=document.getElementById('notificationEmpty');
   if(x)x.textContent='No se pudieron cargar las notificaciones.';
 }finally{
   notificationsLoading=false;
 }
}
async function markNotificationRead(id){if(!id)return;const fd=new FormData();fd.append('mark_user_notification_read','1');fd.append('notification_id',String(id));try{await fetch(window.location.href,{method:'POST',body:fd,cache:'no-store',credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});}catch(e){}}

/*
 * Comprueba periódicamente la base de datos aunque el panel esté cerrado.
 * Así el indicador rojo aparece solamente cuando llega una notificación real.
 */
window.setInterval(()=>{
  if(!document.hidden && !notificationsLoading) loadUserNotifications();
},15000);

const notificationDetailBackdrop=document.getElementById('notificationDetailBackdrop');
const notificationDetailModal=document.getElementById('notificationDetailModal');
const notificationDetailClose=document.getElementById('notificationDetailClose');
const notificationDetailTitle=document.getElementById('notificationDetailTitle');
const notificationDetailKicker=document.getElementById('notificationDetailKicker');
const notificationDetailMessage=document.getElementById('notificationDetailMessage');
const notificationDetailMedia=document.getElementById('notificationDetailMedia');
const notificationDetailPlay=document.getElementById('notificationDetailPlay');
let notificationDetailHref='';

function closeNotificationDetail(){
 notificationDetailModal.classList.remove('open');
 notificationDetailBackdrop.classList.remove('open');
 notificationDetailModal.setAttribute('aria-hidden','true');
 notificationDetailBackdrop.setAttribute('aria-hidden','true');
 document.body.classList.remove('notification-detail-open');
 notificationDetailHref='';
}

function openNotificationDetail(item){
 if(!item)return;
 const isContent=String(item.tipo||'').toLowerCase()==='contenido_nuevo';
 notificationDetailTitle.textContent=String(item.titulo||'Notificación');
 notificationDetailKicker.textContent=isContent ? 'Nuevo contenido' : 'Notificación';
 notificationDetailMessage.textContent=String(item.mensaje||'');
 notificationDetailMedia.innerHTML='';
 notificationDetailMedia.hidden=true;
 notificationDetailPlay.hidden=true;
 notificationDetailHref='';

 if(isContent && item.imagen){
   const img=document.createElement('img');
   img.src=String(item.imagen);
   img.alt=String(item.titulo||'');
   img.loading='eager';
   img.onerror=()=>{notificationDetailMedia.hidden=true;};
   notificationDetailMedia.appendChild(img);
   notificationDetailMedia.hidden=false;
 }

 if(isContent && item.enlace){
   notificationDetailHref=String(item.enlace);
   notificationDetailPlay.hidden=false;
 }

 notificationDetailModal.classList.add('open');
 notificationDetailBackdrop.classList.add('open');
 notificationDetailModal.setAttribute('aria-hidden','false');
 notificationDetailBackdrop.setAttribute('aria-hidden','false');
 document.body.classList.add('notification-detail-open');
 document.getElementById('notificationDetailScroll').scrollTop=0;
}

notificationDetailClose?.addEventListener('click',closeNotificationDetail);
notificationDetailBackdrop?.addEventListener('click',closeNotificationDetail);
notificationDetailPlay?.addEventListener('click',()=>{
 if(notificationDetailHref) window.location.href=notificationDetailHref;
});
document.addEventListener('keydown',e=>{
 if(e.key==='Escape' && notificationDetailModal?.classList.contains('open')) closeNotificationDetail();
});

document.getElementById('notificationList')?.addEventListener('click',async e=>{
 const article=e.target.closest('[data-notification-id]');
 if(!article)return;
 const id=Number(article.dataset.notificationId);
 const item=userNotifications.find(n=>Number(n.id)===id);
 if(!item)return;

 if(Number(item.leida)===0){
   item.leida=1;
   await markNotificationRead(id);
   renderUserNotifications();
 }

 setNotifications(false);
 openNotificationDetail(item);
});

function toggleSettings(e){
 if(e) { e.preventDefault(); e.stopPropagation(); }
 setNotifications(false);
 const settingsWrap=document.getElementById('settingsWrap');
 const settingsOpen=settingsWrap.classList.toggle('open');
 document.body.classList.toggle('settings-open',settingsOpen);
}
document.getElementById('settingsBtn').addEventListener('click',toggleSettings);
const mobileSettingsBtn=document.getElementById('mobileSettingsBtn');
if(mobileSettingsBtn) mobileSettingsBtn.addEventListener('click',toggleSettings);
document.addEventListener('click',e=>{
 if(!notificationPanel.contains(e.target) && e.target!==notificationBtn) setNotifications(false);
 const settingsWrap=document.getElementById('settingsWrap');
 if(!settingsWrap.contains(e.target) && e.target!==mobileSettingsBtn){
   settingsWrap.classList.remove('open');
   document.body.classList.remove('settings-open');
 }
});
let headerTick=false;
window.addEventListener('scroll',()=>{
  if(headerTick) return;
  headerTick=true;
  requestAnimationFrame(()=>{
    document.getElementById('header').classList.toggle('scrolled',window.scrollY>30);
    headerTick=false;
  });
},{passive:true});
setHero('inicio',0);

/* =========================================================
   CARRUSEL PRINCIPAL — DESLIZAMIENTO TÁCTIL
   Android/iPhone: deslizar izquierda/derecha con el dedo.
   ========================================================= */
(function(){
  const hero = document.getElementById('hero');
  if(!hero) return;

  let startX = 0;
  let startY = 0;
  let tracking = false;

  hero.addEventListener('touchstart', function(e){
    if(!e.touches || !e.touches.length) return;
    startX = e.touches[0].clientX;
    startY = e.touches[0].clientY;
    tracking = true;
  }, {passive:true});

  hero.addEventListener('touchend', function(e){
    if(!tracking || !e.changedTouches || !e.changedTouches.length) return;
    tracking = false;

    const endX = e.changedTouches[0].clientX;
    const endY = e.changedTouches[0].clientY;
    const dx = endX - startX;
    const dy = endY - startY;

    /* Solo cuenta un gesto claramente horizontal. */
    if(Math.abs(dx) < 45 || Math.abs(dx) <= Math.abs(dy) * 1.15) return;

    const set = Array.isArray(heroSets[currentView]) ? heroSets[currentView] : [];
    if(!set.length) return;

    if(dx < 0){
      setHero(currentView, heroIndex + 1);
    }else{
      setHero(currentView, heroIndex - 1);
    }
  }, {passive:true});
})();

/* =========================================================
   ACTUALIZACIÓN AUTOMÁTICA DEL HERO
   Si agregas una película/serie nueva en el archivo del
   carrusel correspondiente, el hero la detecta sin recargar.
========================================================= */
(function(){
  'use strict';

  let heroVersion = '';
  let heroRefreshBusy = false;
  let heroDevice = '';

  function esAndroidIphonePantalla(){
    const ua = navigator.userAgent || '';
    const movilApple = /iPhone|iPod/i.test(ua);
    const movilAndroid = /Android/i.test(ua) &&
      Math.min(window.screen.width || window.innerWidth, window.screen.height || window.innerHeight) <= 768;
    return movilApple || movilAndroid;
  }

  function obtenerDispositivoHero(){
    return esAndroidIphonePantalla() ? 'movil' : 'desktop';
  }

  async function actualizarHeroDesdeCarrusel(forzar=false){
    if(heroRefreshBusy || document.hidden) return;

    const dispositivo = obtenerDispositivoHero();
    heroRefreshBusy = true;

    try{
      /*
       * GET con una URL única para impedir que navegador, CDN, proxy o
       * servidor intermedio entregue una respuesta anterior.
       */
      const url = new URL(window.location.href);
      url.searchParams.set('actualizar_carrusel_hero','1');
      url.searchParams.set('dispositivo',dispositivo);
      url.searchParams.set('_mtx',String(Date.now()));

      const respuesta = await fetch(url.toString(),{
        method:'GET',
        credentials:'same-origin',
        cache:'no-store',
        headers:{
          'X-Requested-With':'XMLHttpRequest',
          'Cache-Control':'no-cache, no-store, max-age=0'
        }
      });

      if(!respuesta.ok) throw new Error('HTTP '+respuesta.status);

      const data = await respuesta.json();
      if(!data || !data.success || !data.sets) return;

      const nuevaVersion = String(data.version || '');
      const fuenteCambio = heroDevice !== dispositivo;

      if(heroVersion === ''){
        heroVersion = nuevaVersion;
        heroDevice = dispositivo;
        return;
      }

      if(nuevaVersion === heroVersion && !fuenteCambio && !forzar) return;

      /* Si forzamos y no hubo cambio real, no reiniciamos el slide. */
      if(nuevaVersion === heroVersion && !fuenteCambio){
        return;
      }

      const inicioAnterior = Array.isArray(heroSets.inicio) ? heroSets.inicio : [];
      const inicioNuevo = Array.isArray(data.sets.inicio) ? data.sets.inicio : [];
      const idsAnteriores = new Set(
        inicioAnterior.map(item => String(item?.[5] || item?.[0] || ''))
      );

      let indiceContenidoNuevo = -1;
      for(let i=0;i<inicioNuevo.length;i++){
        const idNuevo = String(inicioNuevo[i]?.[5] || inicioNuevo[i]?.[0] || '');
        if(idNuevo && !idsAnteriores.has(idNuevo)){
          indiceContenidoNuevo = i;
          break;
        }
      }

      heroSets.inicio = inicioNuevo;
      heroSets.peliculas = Array.isArray(data.sets.peliculas) ? data.sets.peliculas : [];
      heroSets.series = Array.isArray(data.sets.series) ? data.sets.series : [];
      heroSets.adulto = Array.isArray(data.sets.adulto) ? data.sets.adulto : [];
      heroVersion = nuevaVersion;
      heroDevice = dispositivo;

      const vistaActual = currentView || 'inicio';
      const indice = indiceContenidoNuevo >= 0 ? indiceContenidoNuevo : 0;
      setHero(vistaActual, indice);
    }catch(error){
      /* No interrumpimos el funcionamiento del resto de Inicio. */
    }finally{
      heroRefreshBusy = false;
    }
  }

  /* Primera lectura inmediata y comprobación periódica. */
  actualizarHeroDesdeCarrusel(true);
  window.setInterval(()=>actualizarHeroDesdeCarrusel(false),2000);

  document.addEventListener('visibilitychange',function(){
    if(!document.hidden) actualizarHeroDesdeCarrusel(true);
  });

  window.addEventListener('resize',function(){
    actualizarHeroDesdeCarrusel(true);
  },{passive:true});
})();

/* ===== ACCIONES DEL HERO ===== */
const favoriteKey='movietx_favorites';
function getFavorites(){
  try{return JSON.parse(localStorage.getItem(favoriteKey)||'[]')}catch(e){return []}
}
function saveFavorites(list){localStorage.setItem(favoriteKey,JSON.stringify(list))}
function addToMyList(){
  const set=heroSets[currentView] || heroSets.inicio;
  const h=set[heroIndex];
  if(!h) return;
  const list=getFavorites();
  if(!list.some(x=>x[0]===(h[5] || h[0]))) list.push([h[5] || h[0], `${h[1]} · ${h[2]}`, h[4]]);
  saveFavorites(list);
  // La lista queda guardada para Favoritos y disponible desde Mi lista.
  // La vista Inicio ahora está enfocada en descubrimiento, por eso no se inserta una sección Favoritos aquí.
  const favoriteItems=list;
  const btn=document.getElementById('heroListBtn');
  if(btn){
    btn.dataset.savedCount=String(favoriteItems.length);
    btn.textContent='✓ Mi lista';
    setTimeout(()=>btn.textContent='＋ Mi lista',1200);
  }
}
function playCurrent(){
  const set=heroSets[currentView] || heroSets.inicio || [];
  const h=set[heroIndex];
  if(!h) return;
  const destino=h[6] || '';
  if(destino && destino !== '#'){
    window.location.href=destino;
    return;
  }
  const base=currentView==='series'?'Reproductor-Universal-Series.php':'Reproductor-Universal.php';
  const id=h[5] || h[0];
  window.location.href=base+'?id='+encodeURIComponent(id);
}

document.getElementById('heroPlayBtn').addEventListener('click',playCurrent);
document.getElementById('heroListBtn').addEventListener('click',addToMyList);

/* Carrusel táctil: el navegador conserva la inercia nativa al soltar.
   Esta configuración evita que un gesto horizontal sea cortado por la página. */
document.querySelectorAll('.media-row.premium-scroll').forEach(row=>{
  row.style.webkitOverflowScrolling='touch';
  row.style.touchAction='pan-x';
  row.addEventListener('touchstart',()=>{row.style.scrollBehavior='auto'},{passive:true});
  row.addEventListener('touchend',()=>{row.style.scrollBehavior='smooth'},{passive:true});
});
</script>


<script>loadUserNotifications();window.setInterval(loadUserNotifications,15000);</script>

<script>
/* =========================================================
   BLOQUEAR TARJETAS DEL CATÁLOGO SIN ENLACE
   - data-href vacío => no navega.
   - Se mantiene visible la película/serie.
   - No toca "Continuar viendo".
   ========================================================= */
(function(){
  function actualizarTarjetasSinEnlace(){
    document.querySelectorAll('.catalogo-row-modern .card-link').forEach(function(card){
      var destino = (card.getAttribute('data-href') || '').trim();

      if(destino === '' || destino === '#'){
        card.classList.add('sin-enlace');
        card.removeAttribute('href');
        card.setAttribute('aria-disabled','true');
        card.setAttribute('tabindex','-1');
      }else{
        card.classList.remove('sin-enlace');
        card.setAttribute('href',destino);
        card.removeAttribute('aria-disabled');
        card.removeAttribute('tabindex');
      }
    });
  }

  actualizarTarjetasSinEnlace();

  document.addEventListener('click',function(e){
    var card = e.target.closest('.catalogo-row-modern .card-link.sin-enlace');
    if(card){
      e.preventDefault();
      e.stopPropagation();
    }
  },true);

  var observer = new MutationObserver(function(){
    actualizarTarjetasSinEnlace();
  });

  observer.observe(document.body,{childList:true,subtree:true});
})();
</script>

</body>
</html>