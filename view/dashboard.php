<?php
/**
 * Vista de Dashboard - dashboard.php
 * Panel de administración con barra lateral adaptativa y control de roles (RBAC) dinámico.
 * Actúa como contenedor de vistas SPA internas con ruteo y permisos validados desde la base de datos.
 */

// Seguridad básica: Si no hay sesión activa de personal, redirigir al Front Controller
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['personal'])) {
    header("Location: index.php");
    exit;
}

// Datos del personal logueado
$currentUser = $_SESSION['personal'];
$userRol     = $currentUser['rol'];
$userRolId   = isset($currentUser['rol_id']) ? (int)$currentUser['rol_id'] : 0;
$userName    = htmlspecialchars($currentUser['nombre_completo'] ?? ($currentUser['nombre'] ?? 'Usuario'));
$userCedula  = htmlspecialchars(($currentUser['tipo_documento'] ?? 'V') . '-' . $currentUser['cedula']);

// Cargar Controlador del Dashboard para RBAC y Enrutador dinámico
require_once __DIR__ . "/../controllers/DashboardController.php";
$userPermissions = cargarPermisosUsuario($userRol, $userRolId);

// Enrutador y resolución dinámica de la vista según BD y permisos
$requestedView = $_GET['view'] ?? 'dashboard';
$currentView   = resolverVistaPermitida($requestedView, $userRol, $userPermissions);
$viewAssets    = obtenerAssetsVista($currentView, $userPermissions, $userRol);
$menuItems     = obtenerMenuUsuario($currentView, $userRol, $userPermissions);
$viewPath      = resolverRutaVista($currentView);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel de Administración - Zoológico y Botánico Bararida</title>
    <!-- Bootstrap Local -->
    <link rel="stylesheet" href="view/assets/css/bootstrap.min.css">
    <!-- Google Fonts - Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Estilos Premium Personalizados del Dashboard -->
    <link rel="stylesheet" href="view/assets/css/dashboard.css?v=<?php echo time(); ?>">
    
    <!-- Estilos Premium del Módulo de Recorridos y Zonas -->
    <link rel="stylesheet" href="view/assets/css/recorridos.css?v=<?php echo time(); ?>">

    <!-- Carga dinámica modular de estilos según la vista activa -->
    <?php foreach ($viewAssets['css'] as $cssFile): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($cssFile); ?>?v=<?php echo time(); ?>">
    <?php endforeach; ?>
</head>
<body>

    <!-- Overlay para cerrar sidebar en mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Input oculto para que el JS sepa quién es el personal logueado -->
    <input type="hidden" id="currentLoggedUserId" value="<?php echo $currentUser['id']; ?>">

    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <!-- Huellas de oso decorativas en el menú lateral -->
            <div class="sidebar-paw spaw-1"></div>
            <div class="sidebar-paw spaw-2"></div>
            <div class="sidebar-paw spaw-3"></div>
            <div class="sidebar-paw spaw-4"></div>

            <div class="sidebar-header">
                <!-- Logo real del Parque Bararida -->
                <a href="index.php?view=dashboard" style="text-decoration: none;">
                    <img src="view/assets/img/logo.png" alt="Logo Parque Bararida" class="sidebar-logo">
                    <h3 class="sidebar-title">Bararida</h3>
                    <p class="sidebar-subtitle">GESTIÓN ADMINISTRATIVA</p>
                </a>
            </div>

            <!-- Menú de Navegación Lateral Generado Dinámicamente desde la BD -->
            <div class="sidebar-menu">
                <ul>
                    <?php foreach ($menuItems as $item): ?>
                        <li class="<?php echo $item['active'] ? 'active' : ''; ?>">
                            <a href="<?php echo htmlspecialchars($item['url']); ?>">
                                <?php echo $item['icon_svg']; ?>
                                <?php echo htmlspecialchars($item['label']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="sidebar-footer">
                <a href="index.php?action=logout" class="btn-logout">
                    <!-- SVG Logout Icon -->
                    <svg viewBox="0 0 24 24"><path d="M17 7L15.59 8.41L18.17 11H8V13H18.17L15.59 15.59L17 17L22 12L17 7ZM4 5H12V3H4C2.9 3 2 3.9 2 5V19C2 20.1 2.9 21 4 21H12V19H4V5Z"/></svg>
                    Cerrar Sesión
                </a>
            </div>
        </nav>

        <!-- Contenido Central -->
        <main id="content">
            <!-- Barra Superior de Bienvenida -->
            <div class="topbar">
                <!-- Botón hamburguesa (solo mobile) -->
                <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Abrir menú">
                    <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
                </button>
                <div class="topbar-title">
                    <h2>Panel de Control</h2>
                    <p>Bienvenido al Sistema de Gestión del Parque Bararida.</p>
                </div>
                
                <div class="user-profile">
                    <div class="user-info">
                        <p class="name"><?php echo $userName; ?></p>
                        <span class="role-badge role-<?php echo htmlspecialchars($userRol); ?>">
                            <?php echo htmlspecialchars(obtenerEtiquetaRol($userRol)); ?>
                        </span>
                    </div>
                    <div class="avatar-circle">
                        <?php 
                        $initials = 'US';
                        if (!empty($currentUser['nombre_completo'])) {
                            $parts = preg_split('/\s+/', trim($currentUser['nombre_completo']));
                            $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                        } else if (!empty($currentUser['nombre'])) {
                            $initials = strtoupper(substr($currentUser['nombre'], 0, 1) . substr($currentUser['apellido'] ?? '', 0, 1));
                        }
                        echo $initials;
                        ?>
                    </div>
                </div>
            </div>

            <!-- Global Alert feedback toast style -->
            <div id="alertFeedbackGlobal" class="alert alert-toast alert-toast-success alert-dismissible" role="alert" style="display:none; position: fixed; top: 20px; right: 20px; z-index: 9999; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width:400px; width: 100%;"></div>

            <!-- Área scrollable del módulo -->
            <div class="content-area">

            <!-- RUTEADOR DINÁMICO DE VISTAS (Validado por el Controller) -->
            <?php
            require_once $viewPath;
            ?>

            </div><!-- /.content-area -->
        </main>
    </div><!-- /.wrapper -->

    <!-- Huella de oso decorativa gigante en marca de agua para el fondo del contenido central -->
    <div class="content-bg-paw"></div>

    <!-- Carga de jQuery Local -->
    <script src="view/assets/js/jquery.min.js"></script>
    <!-- Carga de jQuery Validate Local -->
    <script src="view/assets/js/jquery.validate.min.js"></script>
    <script src="view/assets/js/messages_es.min.js"></script>
    <script src="view/assets/js/validations/global-val-setup.js?v=<?php echo time(); ?>"></script>
    <!-- Bootstrap JS Bundle Local -->
    <script src="view/assets/js/bootstrap.bundle.min.js"></script>

    <!-- Librerías y Scripts específicos resueltos dinámicamente según la vista activa -->
    <?php foreach ($viewAssets['js'] as $jsFile): ?>
        <script src="<?php echo htmlspecialchars($jsFile); ?>?v=<?php echo time(); ?>"></script>
    <?php endforeach; ?>

    <!-- Lógica del Dashboard (sidebar responsivo y utilidades) -->
    <script src="view/assets/js/dashboard.js"></script>
</body>
</html>