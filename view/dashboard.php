<?php
/**
 * Vista de Dashboard - dashboard.php
 * Panel de administración con barra lateral adaptativa y control de roles (RBAC) dinámico.
 * Actúa como contenedor de vistas SPA internas usando parámetros $_GET['view'].
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

require_once __DIR__ . "/../controllers/DashboardController.php";
$userPermissions = cargarPermisosUsuario($userRol, $userRolId);

// Obtener la vista solicitada (Enrutador Interno)
$currentView  = isset($_GET['view']) ? trim($_GET['view']) : 'dashboard';
$validViews   = ['dashboard', 'citas', 'instituciones', 'personal', 'recorridos', 'visitantes', 'app'];
// El módulo 'app' solo es accesible por el superadmin
if ($currentView === 'app' && $userRol !== 'superadmin') {
    $currentView = 'dashboard';
}
if (!in_array($currentView, $validViews)) {
    $currentView = 'dashboard';
}
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

    <!-- Cargar estilos específicos para CRUDs y Acceso Restringido si no estamos en el Dashboard de inicio -->
    <?php if ($currentView !== 'dashboard' && $currentView !== 'recorridos'): ?>
        <link rel="stylesheet" href="view/assets/css/personal.css?v=<?php echo time(); ?>">
    <?php endif; ?>
    <?php if ($currentView === 'citas' || $currentView === 'instituciones' || $currentView === 'visitantes'): ?>
        <link rel="stylesheet" href="view/assets/css/citas.css?v=<?php echo time(); ?>">
    <?php endif; ?>
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

            <div class="sidebar-menu">
                <ul>
                    <li class="<?php echo ($currentView === 'dashboard') ? 'active' : ''; ?>">
                        <a href="index.php?view=dashboard">
                            <!-- SVG Dashboard Icon -->
                            <svg viewBox="0 0 24 24"><path d="M10 20H5V4H10V20ZM19 20H14V12H19V20ZM19 10H14V4H19V10Z"/></svg>
                            Dashboard
                        </a>
                    </li>
                    <?php if (!empty($userPermissions['citas'])): ?>
                    <li class="<?php echo ($currentView === 'citas') ? 'active' : ''; ?>">
                        <a href="index.php?view=citas">
                            <!-- SVG Calendar Icon -->
                            <svg viewBox="0 0 24 24"><path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V10H19V20ZM19 8H5V6H19V8Z"/></svg>
                            Citas
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (!empty($userPermissions['instituciones'])): ?>
                    <li class="<?php echo ($currentView === 'instituciones') ? 'active' : ''; ?>">
                        <a href="index.php?view=instituciones">
                            <!-- SVG Institution Icon -->
                            <svg viewBox="0 0 24 24"><path d="M12 3L1 9V11H3V20H5V11H19V20H21V11H23V9L12 3ZM12 7.5C12.83 7.5 13.5 8.17 13.5 9C13.5 9.83 12.83 10.5 12 10.5C11.17 10.5 10.5 9.83 10.5 9C10.5 8.17 11.17 7.5 12 7.5ZM7 20V13H9V20H7ZM15 20V13H17V20H15Z"/></svg>
                            Instituciones
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($userPermissions['recorridos'])): // Permiso de recorridos desde BD ?>
                    <li class="<?php echo ($currentView === 'recorridos') ? 'active' : ''; ?>">
                        <a href="index.php?view=recorridos">
                            <!-- SVG Compass / Path Icon -->
                            <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                            Recorridos
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($userPermissions['visitantes']): ?>
                    <li class="<?php echo ($currentView === 'visitantes') ? 'active' : ''; ?>">
                        <a href="index.php?view=visitantes">
                            <!-- SVG Group/Visitors Icon -->
                            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                            Visitantes
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (!empty($userPermissions['personal'])): // Permiso de personal desde BD ?>
                    <li class="<?php echo ($currentView === 'personal') ? 'active' : ''; ?>">
                        <a href="index.php?view=personal">
                            <!-- SVG Settings Icon -->
                            <svg viewBox="0 0 24 24"><path d="M19.14 12.94C19.14 12.63 19.17 12.32 19.17 12C19.17 11.68 19.14 11.37 19.09 11.06L21.26 9.37C21.45 9.22 21.5 8.95 21.39 8.74L19.33 5.17C19.22 4.96 18.96 4.88 18.74 4.96L16.18 5.99C15.65 5.58 15.07 5.24 14.44 4.99L14.05 2.27C14.01 2.03 13.8 1.85 13.56 1.85H9.44C9.2 1.85 8.99 2.03 8.95 2.27L8.56 4.99C7.93 5.24 7.35 5.58 6.82 5.99L4.26 4.96C4.04 4.88 3.78 4.96 3.67 5.17L1.61 8.74C1.5 8.95 1.55 9.22 1.74 9.37L3.91 11.06C3.86 11.37 3.83 11.69 3.83 12C3.83 12.31 3.86 12.63 3.91 12.94L1.74 14.63C1.55 14.78 1.5 15.05 1.61 15.26L3.67 18.83C3.78 19.04 4.04 19.12 4.26 19.04L6.82 18.01C7.35 18.42 7.93 18.76 8.56 19.01L8.95 21.73C8.99 21.97 9.2 22.15 9.44 22.15H13.56C13.8 22.15 14.01 21.97 14.05 21.73L14.44 19.01C15.07 18.76 15.65 18.42 16.18 18.01L18.74 19.04C18.96 19.12 19.22 19.04 19.33 18.83L21.39 15.26C21.5 15.05 21.45 14.78 21.26 14.63L19.14 12.94ZM12 15.6C10.01 15.6 8.4 13.99 8.4 12C8.4 10.01 10.01 8.4 12 8.4C13.99 8.4 15.6 10.01 15.6 12C15.6 13.99 13.99 15.6 12 15.6Z"/></svg>
                            Personal
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($userRol === 'superadmin'): // Módulo App exclusivo del superadmin ?>
                    <li class="<?php echo ($currentView === 'app') ? 'active' : ''; ?>">
                        <a href="index.php?view=app">
                            <!-- SVG Gear/Config Icon -->
                            <svg viewBox="0 0 24 24"><path d="M12 15.516c-1.93 0-3.516-1.586-3.516-3.516S10.07 8.484 12 8.484 15.516 10.07 15.516 12 13.93 15.516 12 15.516zm7.488-3.047c.047-.313.075-.633.075-.961 0-.328-.028-.648-.075-.969l2.086-1.617a.496.496 0 0 0 .12-.633l-1.97-3.422a.494.494 0 0 0-.601-.211l-2.461.984a7.25 7.25 0 0 0-.836-.484l-.375-2.625A.48.48 0 0 0 15 3h-3.938a.48.48 0 0 0-.476.531l-.375 2.625c-.3.141-.586.305-.836.484l-2.461-.984a.493.493 0 0 0-.602.211L4.344 9.29a.485.485 0 0 0 .12.633l2.086 1.617c-.047.321-.075.641-.075.969 0 .328.028.648.075.961L4.465 15.09a.496.496 0 0 0-.12.633l1.969 3.422a.494.494 0 0 0 .601.211l2.461-.984c.25.18.536.344.836.485l.375 2.625c.047.29.305.516.594.516H15a.48.48 0 0 0 .477-.516l.375-2.625c.3-.141.585-.305.836-.485l2.46.984a.493.493 0 0 0 .602-.21l1.968-3.423a.485.485 0 0 0-.12-.632z"/></svg>
                            Configuración
                        </a>
                    </li>
                    <?php endif; ?>
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
                        <span class="role-badge role-<?php echo $userRol; ?>">
                            <?php 
                            if ($userRol === 'superadmin') echo 'Super Admin';
                            else if ($userRol === 'admin') echo 'Administrador';
                            else echo 'Guía de Campo';
                            ?>
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

            <!-- RUTEADOR INTERNO DE VISTAS -->
            <?php
            switch ($currentView) {
                case 'dashboard':
                    require_once __DIR__ . '/dashboard/dashboard_main.php';
                    break;
                case 'citas':
                    require_once __DIR__ . '/dashboard/citas.php';
                    break;
                case 'instituciones':
                    require_once __DIR__ . '/dashboard/instituciones.php';
                    break;
                case 'personal':
                    require_once __DIR__ . '/dashboard/personal.php';
                    break;
                case 'visitantes':
                    require_once __DIR__ . '/dashboard/visitantes.php';
                    break;
                case 'recorridos':
                    require_once __DIR__ . '/dashboard/recorridos.php';
                    break;
                case 'app':
                    require_once __DIR__ . '/dashboard/app.php';
                    break;
                default:
                    require_once __DIR__ . '/dashboard/dashboard_main.php';
                    break;
            }
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
    <!-- Carga de FullCalendar Local -->
    <?php if ($currentView === 'dashboard' || ($currentView === 'citas' && $userPermissions['citas'])): ?>
        <script src="view/assets/js/fullcalendar.min.js"></script>
        <script src="view/assets/js/fullcalendar.es.js"></script>
    <?php endif; ?>

    <!-- Vincular el script del CRUD de forma asíncrona solo si estamos en la vista de personal -->
    <?php if ($currentView === 'personal' && $userPermissions['personal']): ?>
        <script src="view/assets/js/personal.js?v=<?php echo time(); ?>"></script>
        <script src="view/assets/js/validations/personal-val.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>

    <!-- Vincular la librería Chart.js de forma local para las estadísticas del dashboard -->
    <?php if ($currentView === 'dashboard'): ?>
        <script src="view/assets/js/chart.min.js"></script>
    <?php endif; ?>

    <!-- Vincular el script de recorridos dinámicamente si corresponde -->
    <?php if ($currentView === 'dashboard' || ($currentView === 'recorridos' && $userPermissions['recorridos'])): ?>
        <script src="view/assets/js/recorridos.js?v=<?php echo time(); ?>"></script>
        <script src="view/assets/js/validations/recorridos-val.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>

    <!-- Vincular el script de citas dinámicamente si corresponde -->
    <?php if ($currentView === 'citas' && $userPermissions['citas']): ?>
        <!-- SheetJS: lectura de archivos Excel (.xlsx) en el frontend -->
        <script src="view/assets/js/xlsx.min.js"></script>
        <script src="view/assets/js/citas.js?v=<?php echo time(); ?>"></script>
        <script src="view/assets/js/validations/citas-val.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>

    <!-- Vincular el script de instituciones dinámicamente si corresponde -->
    <?php if ($currentView === 'instituciones' && $userPermissions['instituciones']): ?>
        <script src="view/assets/js/instituciones.js?v=<?php echo time(); ?>"></script>
        <script src="view/assets/js/validations/instituciones-val.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>

    <!-- Vincular el script de visitantes dinámicamente si corresponde -->
    <?php if ($currentView === 'visitantes' && $userPermissions['visitantes']): ?>
        <script src="view/assets/js/visitantes.js?v=<?php echo time(); ?>"></script>
        <script src="view/assets/js/validations/visitantes-val.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>

    <!-- Vincular el script del módulo App (Configuración) solo si corresponde y es superadmin -->
    <?php if ($currentView === 'app' && $userRol === 'superadmin'): ?>
        <script src="view/assets/js/app.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>

    <!-- Lógica del Dashboard (sidebar responsivo) -->
    <script src="view/assets/js/dashboard.js"></script>
</body>
</html>