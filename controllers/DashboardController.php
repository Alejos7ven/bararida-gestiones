<?php
/**
 * DashboardController
 * Controlador del Dashboard — gestiona la carga dinámica de módulos, permisos RBAC
 * desde la BD, validación segura de vistas permitidas, generación de menús y ruteo.
 * Se incluye mediante require_once desde view/dashboard.php.
 */

require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../models/Modulo.php";
require_once __DIR__ . "/../models/Permiso.php";

/**
 * Resuelve y retorna el array de permisos del usuario autenticado consultando la BD.
 * Si el rol_id no está en sesión (sesión antigua), lo busca en la BD por nombre de rol
 * y actualiza la sesión para las próximas peticiones.
 *
 * @param string $userRol   Nombre del rol (de la sesión)
 * @param int    $userRolId ID del rol (de la sesión, puede ser 0 si es sesión antigua)
 * @return array Array asociativo ['modulo' => bool, ...]
 */
function cargarPermisosUsuario($userRol, &$userRolId) {
    $userPermissions = [];
    $modulosRegistrados = [];

    try {
        $database     = new Database();
        $db           = $database->getConnection();
        $moduloModel  = new Modulo($db);
        $permisoModel = new Permiso($db);

        // Obtener nombres de módulos registrados dinámicamente desde la BD
        $modulosRegistrados = $moduloModel->getNombresValidos();

        if ($userRolId > 0) {
            // Sesión actualizada: consultar directamente por rol_id
            $userPermissions = $permisoModel->getPermisosPorRol($userRolId);
        } else {
            // Sesión antigua sin rol_id: resolver el id desde la BD por nombre de rol
            $stmtRol = $db->prepare("SELECT id FROM roles WHERE nombre = :nombre LIMIT 1");
            $stmtRol->bindParam(":nombre", $userRol, PDO::PARAM_STR);
            $stmtRol->execute();
            $rolRow = $stmtRol->fetch(PDO::FETCH_ASSOC);

            if ($rolRow) {
                $userRolId = (int)$rolRow['id'];
                $_SESSION['personal']['rol_id'] = $userRolId; // Actualizar sesión
                $userPermissions = $permisoModel->getPermisosPorRol($userRolId);
            }
        }
    } catch (Exception $e) {
        error_log("Error en DashboardController::cargarPermisosUsuario(): " . $e->getMessage());
    }

    // Fallback dinámico si la BD falló al traer módulos
    if (empty($modulosRegistrados)) {
        $modulosRegistrados = ['dashboard', 'citas', 'instituciones', 'recorridos', 'visitantes', 'personal', 'app', 'reportes'];
    }

    // Si el array de permisos sigue vacío, aplicar políticas predeterminadas
    if (empty($userPermissions)) {
        $fallback = [
            'superadmin' => array_fill_keys($modulosRegistrados, true),
            'admin'      => ['dashboard'=>true,'citas'=>true,'instituciones'=>true,'recorridos'=>true,'visitantes'=>true,'personal'=>false,'app'=>false,'reportes'=>true],
            'guia'       => ['dashboard'=>true,'citas'=>true,'instituciones'=>false,'recorridos'=>false,'visitantes'=>false,'personal'=>false,'app'=>false,'reportes'=>false],
        ];
        $userPermissions = isset($fallback[$userRol])
            ? $fallback[$userRol]
            : array_fill_keys($modulosRegistrados, false);
    }

    // Garantizar que todas las claves de módulos registrados existan
    foreach ($modulosRegistrados as $modulo) {
        if (!isset($userPermissions[$modulo])) {
            $userPermissions[$modulo] = false;
        }
    }

    return $userPermissions;
}

/**
 * Resuelve y valida la vista solicitada contra los módulos registrados en la BD
 * y los permisos RBAC del usuario autenticado.
 *
 * @param string $vistaSolicitada Nombre de la vista recibida vía $_GET['view']
 * @param string $userRol         Nombre del rol del usuario
 * @param array  $userPermissions Array asociativo de permisos del usuario
 * @return string Nombre seguro y autorizado de la vista ('dashboard' por defecto)
 */
function resolverVistaPermitida($vistaSolicitada, $userRol, array $userPermissions) {
    $vista = trim(strtolower((string)$vistaSolicitada));
    if ($vista === '' || $vista === 'dashboard') {
        return 'dashboard';
    }

    // Obtener catálogo de módulos desde la BD
    try {
        $database    = new Database();
        $db          = $database->getConnection();
        $moduloModel = new Modulo($db);
        $modulosBD   = $moduloModel->getNombresValidos();
    } catch (Exception $e) {
        error_log("Error en resolverVistaPermitida(): " . $e->getMessage());
        $modulosBD = ['dashboard', 'citas', 'instituciones', 'recorridos', 'visitantes', 'personal', 'app', 'reportes'];
    }

    // 1. Validar que el módulo solicitado exista en la BD
    if (!in_array($vista, $modulosBD, true)) {
        return 'acceso_denegado';
    }

    // 2. Control específico para el módulo de configuración 'app' (solo superadmin)
    if ($vista === 'app' && $userRol !== 'superadmin') {
        return 'acceso_denegado';
    }

    // 3. Validar permisos RBAC asignados al usuario para este módulo
    if ($vista !== 'app' && empty($userPermissions[$vista])) {
        return 'acceso_denegado';
    }

    // 4. Validar existencia física del archivo de vista para evitar inclusión errónea
    $viewFile = ($vista === 'dashboard')
        ? __DIR__ . '/../view/dashboard/dashboard_main.php'
        : __DIR__ . '/../view/dashboard/' . $vista . '.php';

    if (!file_exists($viewFile)) {
        return 'acceso_denegado';
    }

    return $vista;
}

/**
 * Retorna la ruta física absoluta de la vista a incluir en view/dashboard.php.
 *
 * @param string $vista Nombre de la vista validada
 * @return string Ruta absoluta del archivo PHP
 */
function resolverRutaVista($vista) {
    if ($vista === 'dashboard') {
        return __DIR__ . '/../view/dashboard/dashboard_main.php';
    }
    if ($vista === 'acceso_denegado') {
        return __DIR__ . '/../view/dashboard/acceso_denegado.php';
    }
    return __DIR__ . '/../view/dashboard/' . $vista . '.php';
}

/**
 * Despacha y prepara todo el contexto del Dashboard (control de sesión,
 * permisos RBAC, ruteo de vistas y assets) antes de renderizar la vista.
 * Centraliza las validaciones de acceso y sesión en el Controlador.
 *
 * @return array Contexto con variables del dashboard
 */
function despacharDashboard() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['personal'])) {
        header("Location: index.php");
        exit;
    }

    $currentUser = $_SESSION['personal'];
    $userRol     = $currentUser['rol'] ?? 'guia';
    $userRolId   = isset($currentUser['rol_id']) ? (int)$currentUser['rol_id'] : 0;
    $userName    = htmlspecialchars($currentUser['nombre_completo'] ?? ($currentUser['nombre'] ?? 'Usuario'));
    $userCedula  = htmlspecialchars(($currentUser['tipo_documento'] ?? 'V') . '-' . ($currentUser['cedula'] ?? ''));

    $userPermissions   = cargarPermisosUsuario($userRol, $userRolId);
    $requestedView     = $_GET['view'] ?? 'dashboard';
    $currentView       = resolverVistaPermitida($requestedView, $userRol, $userPermissions);
    $viewAssets        = obtenerAssetsVista($currentView, $userPermissions, $userRol);
    $menuItems         = obtenerMenuUsuario($currentView, $userRol, $userPermissions);
    $viewPath          = resolverRutaVista($currentView);
    $tienePermisoCitas = !empty($userPermissions['citas']);

    return compact(
        'currentUser',
        'userRol',
        'userRolId',
        'userName',
        'userCedula',
        'userPermissions',
        'currentView',
        'viewAssets',
        'menuItems',
        'viewPath',
        'tienePermisoCitas'
    );
}

/**
 * Genera dinámicamente los elementos de navegación del menú lateral (Sidebar),
 * consultando los módulos registrados en la BD y filtrando por permisos RBAC del usuario.
 *
 * @param string $currentView     Vista actualmente activa
 * @param string $userRol         Rol del usuario en sesión
 * @param array  $userPermissions Permisos del usuario
 * @return array Array de items del menú con url, label, icon_svg y estado active
 */
function obtenerMenuUsuario($currentView, $userRol, array $userPermissions) {
    // Mapa de iconos SVG para los módulos estándar del parque
    $iconMap = [
        'dashboard'     => '<svg viewBox="0 0 24 24"><path d="M10 20H5V4H10V20ZM19 20H14V12H19V20ZM19 10H14V4H19V10Z"/></svg>',
        'citas'         => '<svg viewBox="0 0 24 24"><path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V10H19V20ZM19 8H5V6H19V8Z"/></svg>',
        'instituciones' => '<svg viewBox="0 0 24 24"><path d="M12 3L1 9V11H3V20H5V11H19V20H21V11H23V9L12 3ZM12 7.5C12.83 7.5 13.5 8.17 13.5 9C13.5 9.83 12.83 10.5 12 10.5C11.17 10.5 10.5 9.83 10.5 9C10.5 8.17 11.17 7.5 12 7.5ZM7 20V13H9V20H7ZM15 20V13H17V20H15Z"/></svg>',
        'recorridos'    => '<svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
        'visitantes'    => '<svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>',
        'reportes'      => '<svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>',
        'personal'      => '<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
        'app'           => '<svg viewBox="0 0 24 24"><path d="M12 15.516c-1.93 0-3.516-1.586-3.516-3.516S10.07 8.484 12 8.484 15.516 10.07 15.516 12 13.93 15.516 12 15.516zm7.488-3.047c.047-.313.075-.633.075-.961 0-.328-.028-.648-.075-.969l2.086-1.617a.496.496 0 0 0 .12-.633l-1.97-3.422a.494.494 0 0 0-.601-.211l-2.461.984a7.25 7.25 0 0 0-.836-.484l-.375-2.625A.48.48 0 0 0 15 3h-3.938a.48.48 0 0 0-.476.531l-.375 2.625c-.3.141-.586.305-.836.484l-2.461-.984a.493.493 0 0 0-.602.211L4.344 9.29a.485.485 0 0 0 .12.633l2.086 1.617c-.047.321-.075.641-.075.969 0 .328.028.648.075.961L4.465 15.09a.496.496 0 0 0-.12.633l1.969 3.422a.494.494 0 0 0 .601.211l2.461-.984c.25.18.536.344.836.485l.375 2.625c.047.29.305.516.594.516H15a.48.48 0 0 0 .477-.516l.375-2.625c.3-.141.585-.305.836-.485l2.46.984a.493.493 0 0 0 .602-.21l1.968-3.423a.485.485 0 0 0-.12-.632z"/></svg>'
    ];

    $dashboardItem = null;
    $otrosItems    = [];

    try {
        $database    = new Database();
        $db          = $database->getConnection();
        $moduloModel = new Modulo($db);
        $modulos     = $moduloModel->getAll();
    } catch (Exception $e) {
        error_log("Error en obtenerMenuUsuario(): " . $e->getMessage());
        $modulos = [
            ['id' => 1, 'nombre' => 'dashboard',     'label' => 'Dashboard'],
            ['id' => 2, 'nombre' => 'citas',         'label' => 'Citas'],
            ['id' => 3, 'nombre' => 'instituciones', 'label' => 'Instituciones'],
            ['id' => 4, 'nombre' => 'recorridos',    'label' => 'Recorridos'],
            ['id' => 5, 'nombre' => 'visitantes',    'label' => 'Visitantes'],
            ['id' => 6, 'nombre' => 'personal',      'label' => 'Personal'],
            ['id' => 7, 'nombre' => 'app',           'label' => 'Configuración'],
            ['id' => 8, 'nombre' => 'reportes',      'label' => 'Reportes'],
        ];
    }

    foreach ($modulos as $mod) {
        $nombre = $mod['nombre'];
        $label  = $mod['label'];

        // Determinar visibilidad según rol y permisos
        $puedeVer = false;
        if ($nombre === 'dashboard') {
            $puedeVer = true;
        } elseif ($nombre === 'app') {
            $puedeVer = ($userRol === 'superadmin');
        } else {
            $puedeVer = !empty($userPermissions[$nombre]);
        }

        if ($puedeVer) {
            $item = [
                'key'      => $nombre,
                'label'    => $label,
                'url'      => 'index.php?view=' . urlencode($nombre),
                'icon_svg' => $iconMap[$nombre] ?? '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/></svg>',
                'active'   => ($currentView === $nombre)
            ];

            if ($nombre === 'dashboard') {
                $dashboardItem = $item;
            } else {
                $otrosItems[] = $item;
            }
        }
    }

    // Ordenar los demás módulos alfabéticamente por su etiqueta visible (label)
    usort($otrosItems, function ($a, $b) {
        return strcoll(mb_strtolower($a['label'], 'UTF-8'), mb_strtolower($b['label'], 'UTF-8'));
    });

    // Construir menú final: Dashboard fijo arriba, seguido de los demás ordenados alfabéticamente
    $menu = [];
    if ($dashboardItem !== null) {
        $menu[] = $dashboardItem;
    }
    foreach ($otrosItems as $item) {
        $menu[] = $item;
    }

    return $menu;
}

/**
 * Retorna la lista de recursos (CSS y JS) necesarios para la vista activa.
 *
 * @param string $currentView     Vista actual validada
 * @param array  $userPermissions Permisos del usuario
 * @param string $userRol         Rol del usuario
 * @return array ['css' => string[], 'js' => string[]]
 */
function obtenerAssetsVista($currentView, array $userPermissions, $userRol) {
    $css = [];
    $js  = [];

    // Estilos modulares específicos
    if ($currentView !== 'dashboard' && $currentView !== 'recorridos') {
        $css[] = 'view/assets/css/personal.css';
    }
    if (in_array($currentView, ['citas', 'instituciones', 'visitantes'], true)) {
        $css[] = 'view/assets/css/citas.css';
    }
    if ($currentView === 'reportes') {
        $css[] = 'view/assets/css/reportes.css';
    }

    // Scripts y librerías por módulo
    if ($currentView === 'dashboard' || ($currentView === 'citas' && !empty($userPermissions['citas']))) {
        $js[] = 'view/assets/js/fullcalendar.min.js';
        $js[] = 'view/assets/js/fullcalendar.es.js';
    }

    if ($currentView === 'personal' && !empty($userPermissions['personal'])) {
        $js[] = 'view/assets/js/personal.js';
        $js[] = 'view/assets/js/validations/personal-val.js';
    }

    if ($currentView === 'dashboard' || $currentView === 'reportes') {
        $js[] = 'view/assets/js/chart.min.js';
    }

    if ($currentView === 'dashboard' || ($currentView === 'recorridos' && !empty($userPermissions['recorridos']))) {
        $js[] = 'view/assets/js/recorridos.js';
        $js[] = 'view/assets/js/validations/recorridos-val.js';
    }

    if ($currentView === 'citas' && !empty($userPermissions['citas'])) {
        $js[] = 'view/assets/js/xlsx.min.js';
        $js[] = 'view/assets/js/citas.js';
        $js[] = 'view/assets/js/validations/citas-val.js';
    }

    if ($currentView === 'instituciones' && !empty($userPermissions['instituciones'])) {
        $js[] = 'view/assets/js/instituciones.js';
        $js[] = 'view/assets/js/validations/instituciones-val.js';
    }

    if ($currentView === 'visitantes' && !empty($userPermissions['visitantes'])) {
        $js[] = 'view/assets/js/visitantes.js';
        $js[] = 'view/assets/js/validations/visitantes-val.js';
    }

    if ($currentView === 'reportes' && !empty($userPermissions['reportes'])) {
        $js[] = 'view/assets/js/html2pdf.bundle.min.js';
        $js[] = 'view/assets/js/reportes.js';
        $js[] = 'view/assets/js/validations/reportes-val.js';
    }

    if ($currentView === 'app' && $userRol === 'superadmin') {
        $js[] = 'view/assets/js/app.js';
    }

    return ['css' => $css, 'js' => $js];
}

/**
 * Retorna la etiqueta legible y formateada del rol de usuario.
 *
 * @param string $userRol
 * @return string
 */
function obtenerEtiquetaRol($userRol) {
    switch (strtolower(trim((string)$userRol))) {
        case 'superadmin':
            return 'Super Admin';
        case 'admin':
            return 'Administrador';
        case 'guia':
            return 'Guía de Campo';
        default:
            return ucfirst($userRol);
    }
}
