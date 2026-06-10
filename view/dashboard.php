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
$userRol = $currentUser['rol'];
$userName = htmlspecialchars($currentUser['nombre'] . ' ' . $currentUser['apellido']);
$userCedula = htmlspecialchars(($currentUser['tipo_documento'] ?? 'V') . '-' . $currentUser['cedula']);

// Definición de la Matriz RBAC para el Prototipo
// true = Acceso Concedido, false = Acceso Restringido
$permissions = [
    'superadmin' => [
        'personal' => true,
        'instituciones' => true,
        'citas' => true,
        'recorridos' => true,
        'visitantes' => true
    ],
    'admin' => [
        'personal' => false, // Asistente no gestiona personal
        'instituciones' => true,
        'citas' => true,
        'recorridos' => true,
        'visitantes' => true
    ],
    'guia' => [
        'personal' => false,
        'instituciones' => false,
        'citas' => true, // Acceso único a su cronograma de visitas
        'recorridos' => false,
        'visitantes' => false
    ]
];

// Obtener permisos para el rol actual
$userPermissions = isset($permissions[$userRol]) ? $permissions[$userRol] : [
    'personal' => false, 'instituciones' => false, 'citas' => false, 'recorridos' => false, 'visitantes' => false
];

// Obtener la vista solicitada (Enrutador Interno)
$currentView = isset($_GET['view']) ? trim($_GET['view']) : 'dashboard';
$validViews = ['dashboard', 'citas', 'instituciones', 'personal', 'recorridos', 'visitantes'];
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
    <link rel="stylesheet" href="view/assets/css/dashboard.css">
    
    <!-- Estilos Premium del Módulo de Recorridos y Zonas -->
    <link rel="stylesheet" href="view/assets/css/recorridos.css?v=1.1">

    <!-- Cargar estilos específicos para CRUDs y Acceso Restringido si no estamos en el Dashboard de inicio -->
    <?php if ($currentView !== 'dashboard' && $currentView !== 'recorridos'): ?>
        <link rel="stylesheet" href="view/assets/css/personal.css">
    <?php endif; ?>
    <?php if ($currentView === 'citas' || $currentView === 'instituciones' || $currentView === 'visitantes'): ?>
        <link rel="stylesheet" href="view/assets/css/citas.css">
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
                    <li class="<?php echo ($currentView === 'citas') ? 'active' : ''; ?>">
                        <a href="index.php?view=citas">
                            <!-- SVG Calendar Icon -->
                            <svg viewBox="0 0 24 24"><path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V10H19V20ZM19 8H5V6H19V8Z"/></svg>
                            Citas
                        </a>
                    </li>

                    <li class="<?php echo ($currentView === 'instituciones') ? 'active' : ''; ?>">
                        <a href="index.php?view=instituciones">
                            <!-- SVG Institution Icon -->
                            <svg viewBox="0 0 24 24"><path d="M12 3L1 9V11H3V20H5V11H19V20H21V11H23V9L12 3ZM12 7.5C12.83 7.5 13.5 8.17 13.5 9C13.5 9.83 12.83 10.5 12 10.5C11.17 10.5 10.5 9.83 10.5 9C10.5 8.17 11.17 7.5 12 7.5ZM7 20V13H9V20H7ZM15 20V13H17V20H15Z"/></svg>
                            Instituciones
                        </a>
                    </li>
                    <?php if ($userRol === 'superadmin' || $userRol === 'admin'): // Administradores ven la pestaña de Recorridos ?>
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
                    <?php if ($userRol === 'superadmin'): // Solo el superadmin ve la pestaña de Personal ?>
                    <li class="<?php echo ($currentView === 'personal') ? 'active' : ''; ?>">
                        <a href="index.php?view=personal">
                            <!-- SVG Settings Icon -->
                            <svg viewBox="0 0 24 24"><path d="M19.14 12.94C19.14 12.63 19.17 12.32 19.17 12C19.17 11.68 19.14 11.37 19.09 11.06L21.26 9.37C21.45 9.22 21.5 8.95 21.39 8.74L19.33 5.17C19.22 4.96 18.96 4.88 18.74 4.96L16.18 5.99C15.65 5.58 15.07 5.24 14.44 4.99L14.05 2.27C14.01 2.03 13.8 1.85 13.56 1.85H9.44C9.2 1.85 8.99 2.03 8.95 2.27L8.56 4.99C7.93 5.24 7.35 5.58 6.82 5.99L4.26 4.96C4.04 4.88 3.78 4.96 3.67 5.17L1.61 8.74C1.5 8.95 1.55 9.22 1.74 9.37L3.91 11.06C3.86 11.37 3.83 11.69 3.83 12C3.83 12.31 3.86 12.63 3.91 12.94L1.74 14.63C1.55 14.78 1.5 15.05 1.61 15.26L3.67 18.83C3.78 19.04 4.04 19.12 4.26 19.04L6.82 18.01C7.35 18.42 7.93 18.76 8.56 19.01L8.95 21.73C8.99 21.97 9.2 22.15 9.44 22.15H13.56C13.8 22.15 14.01 21.97 14.05 21.73L14.44 19.01C15.07 18.76 15.65 18.42 16.18 18.01L18.74 19.04C18.96 19.12 19.22 19.04 19.33 18.83L21.39 15.26C21.5 15.05 21.45 14.78 21.26 14.63L19.14 12.94ZM12 15.6C10.01 15.6 8.4 13.99 8.4 12C8.4 10.01 10.01 8.4 12 8.4C13.99 8.4 15.6 10.01 15.6 12C15.6 13.99 13.99 15.6 12 15.6Z"/></svg>
                            Personal
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
                        <?php echo strtoupper(substr($currentUser['nombre'], 0, 1) . substr($currentUser['apellido'], 0, 1)); ?>
                    </div>
                </div>
            </div>

            <!-- Global Alert feedback toast style -->
            <div id="alertFeedbackGlobal" class="alert alert-toast alert-toast-success alert-dismissible" role="alert" style="display:none; position: fixed; top: 20px; right: 20px; z-index: 9999; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width:400px; width: 100%;"></div>

            <!-- Área scrollable del módulo -->
            <div class="content-area">

            <!-- RUTEADOR INTERNO DE VISTAS -->
            <?php switch ($currentView):
                
                // =====================================================================
                // CASE 1: DASHBOARD PRINCIPAL (GRID DE TARJETAS DE MÓDULOS)
                // =====================================================================
                case 'dashboard': ?>
                    <div class="row g-4">
                        
                        <!-- 1. Citas y Visitas -->
                        <!-- <div class="col-md-6 col-lg-3">
                            <div class="module-card">
                                <div class="module-icon-container bg-icon-citas">
                                    <svg viewBox="0 0 24 24"><path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V10H19V20Z"/></svg>
                                </div>
                                <h4 class="module-title">Citas y Visitas</h4>
                                <p class="module-desc">
                                    <?php if ($userRol === 'guia'): ?>
                                        Visualiza la agenda y cronograma detallado de las visitas guiadas asignadas para el día de hoy.
                                    <?php else: ?>
                                        Agendación, aprobación, reprogramación y seguimiento de visitas guiadas de instituciones externas.
                                    <?php endif; ?>
                                </p>
                                <a href="index.php?view=citas" class="btn-module btn-module-active">Entrar al Módulo</a>
                            </div>
                        </div> -->


 

                    </div>

                    <!-- WIDGET PREMIUM: MAPA INTERACTIVO Y CALENDARIO -->
                    <div class="row mt-4 g-4">
                        <!-- Columna Izquierda: Mapa con Recorridos -->
                        <div class="col-12 col-lg-6">
                            <div class="dashboard-map-card h-100 shadow-sm">
                                <div class="dashboard-map-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h3 style="font-size: 1.15rem; font-family: 'Outfit'; font-weight: 700; color: var(--park-dark);">
                                        <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: var(--park-primary); margin-right: 6px;"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>
                                        Itinerarios y Zonas
                                    </h3>
                                    <div class="tour-selector-box">
                                        <label for="selectRecorridoDashboard" style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 2px;">Visualizar Recorrido</label>
                                        <select id="selectRecorridoDashboard" class="form-select tour-select-custom" style="min-width: 200px; padding: 4px 8px; font-size: 0.85rem;">
                                            <option value="">Cargando recorridos...</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Mapa responsivo con overlay SVG -->
                                <div class="map-wrapper shadow-sm mt-3">
                                    <img src="view/assets/img/map.jpg" alt="Mapa del Parque Zoológico Bararida" id="mapaParque" class="map-image">
                                    <svg id="mapSvgOverlay" class="map-svg-overlay" viewBox="0 0 100 100" preserveAspectRatio="none"></svg>
                                    <div id="mapPinsContainer"></div>
                                </div>
                                
                                <!-- Timeline de paradas (Secuencia del recorrido) -->
                                <div id="dashboardTimelineWrapper" class="active-tour-indicator-list mt-3" style="display: none; animation: slideDownFade 0.3s ease; padding: 12px; border-radius: 10px;">
                                    <h5 style="font-size: 0.88rem; font-weight: 700;">
                                        <span>Itinerario Activo:</span> <strong id="nombreRecorridoActivoSpan" class="text-success"></strong>
                                    </h5>
                                    <div id="timelineDashboardSteps" class="tour-steps-timeline mt-2">
                                        <!-- Se poblará vía JS -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Columna Derecha: Calendario Premium de Citas Próximas -->
                        <div class="col-12 col-lg-6">
                            <div class="dashboard-map-card h-100 shadow-sm d-flex flex-column">
                                <div class="dashboard-map-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <h3 style="font-size: 1.15rem; font-family: 'Outfit'; font-weight: 700; color: var(--park-dark);">
                                        <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: var(--park-primary); margin-right: 6px;"><path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V10H19V20ZM9 14H7V12H9V14ZM13 14H11V12H13V14ZM17 14H15V12H15v2h2zm-8 4H7v-2h2v2zm4 0h-11v-2h2v2zm4 0h-15v-2h2v2z"/></svg>
                                        Cronograma de Citas
                                    </h3>
                                    <div class="d-flex align-items-center gap-2">
                                        <button id="btnPrevMonth" class="btn btn-sm btn-outline-success" style="border-radius: 50%; width: 28px; height: 28px; padding:0; display: inline-flex; align-items:center; justify-content:center; font-weight: bold;">&lt;</button>
                                        <span id="calendarMonthYear" style="font-weight: 700; font-size: 0.9rem; min-width: 120px; text-align: center; color: var(--park-dark);">Junio 2026</span>
                                        <button id="btnNextMonth" class="btn btn-sm btn-outline-success" style="border-radius: 50%; width: 28px; height: 28px; padding:0; display: inline-flex; align-items:center; justify-content:center; font-weight: bold;">&gt;</button>
                                    </div>
                                </div>
                                
                                <div id="calendarContainer" class="flex-grow-1 d-flex flex-column mt-3">
                                    <div class="calendar-grid-header" style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 0.72rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px;">
                                        <div>Dom</div><div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div>
                                    </div>
                                    <div id="calendarGridBody" class="calendar-grid-body flex-grow-1" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; min-height: 280px; align-content: stretch;">
                                        <!-- Generado dinámicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PANEL DE ESTADÍSTICAS (VISITANTES POR DÍA CON GRÁFICA) -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="dashboard-map-card shadow-sm">
                                <div class="dashboard-map-header mb-3">
                                    <h3 style="font-size: 1.15rem; font-family: 'Outfit'; font-weight: 700; color: var(--park-dark);">
                                        <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: var(--park-primary); margin-right: 6px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                                        Estadísticas de Afluencia de Visitantes
                                    </h3>
                                </div>
                                <div class="chart-container" style="position: relative; height:320px; width:100%;">
                                    <canvas id="chartVisitantes"></canvas>
                                    <div id="chartNoDataPlaceholder" style="display: none; height:100%; width:100%; align-items:center; justify-content:center; flex-direction:column; color:#64748b; text-align: center;">
                                        <svg viewBox="0 0 24 24" style="width:48px; height:48px; fill:#cbd5e1; margin-bottom:12px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 6h2v4h-2z"/></svg>
                                        <p style="font-family:'Outfit'; font-weight:600; font-size:0.95rem; margin:0;">No hay datos de visitantes registrados aún</p>
                                        <p style="font-size:0.8rem; margin:4px 0 0 0;">Los datos aparecerán una vez que se importen visitantes a las citas agendadas.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php break;

                // =====================================================================
                // CASE 2: CITAS Y VISITAS GUIADAS (DUMMY CRUD)
                // =====================================================================
                case 'citas':
                    if ($userPermissions['citas']): 
                        // Cargar recorridos de la base de datos de forma dinámica a través del modelo
                        require_once __DIR__ . "/../config/Database.php";
                        require_once __DIR__ . "/../models/Recorrido.php";
                        $database = new Database();
                        $db = $database->getConnection();
                        $recorridosList = [];
                        if ($db) {
                            $recorridosList = Recorrido::obtenerListadoSimple($db);
                        }
                        ?>
                        <div class="panel-container animate__animated animate__fadeIn">
                            <div class="panel-header-section">
                                <div class="panel-header-title">
                                    <h3>Gestión de Citas y Visitas Guiadas</h3>
                                    <p>Control de solicitudes de visitas guiadas, registro de instituciones y carga de visitantes</p>
                                </div>
                                <div>
                                    <button class="btn-premium-primary" data-bs-toggle="modal" data-bs-target="#modalCrearCita">
                                        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                        Agendar Cita
                                    </button>
                                </div>
                            </div>

                            <!-- Barra de Búsqueda y Filtrado -->
                            <div class="search-filter-row">
                                <div class="search-input-group">
                                    <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                    <input type="text" id="searchCitas" placeholder="Buscar citas por código, institución o RIF...">
                                </div>
                            </div>

                            <!-- Loader Animado -->
                            <div id="loaderCitas" class="text-center py-5">
                                <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="text-muted mt-2">Cargando cronograma de citas del parque...</p>
                            </div>

                            <!-- Tabla de Citas Agendadas -->
                            <div class="table-responsive-premium">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Institución</th>
                                            <th>Fecha y Hora</th>
                                            <th>Visitantes</th>
                                            <th>Guía Asignado</th>
                                            <th>Estado Cita</th>
                                            <th>Estado Pago</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaCitasBody" style="display: none;">
                                        <!-- Se poblará vía AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- MODAL: REGISTRAR CITA (WIZARD MULTI-PASO) -->
                        <div class="modal fade modal-premium" id="modalCrearCita" tabindex="-1" aria-labelledby="modalCrearCitaLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalCrearCitaLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Agendar Nueva Visita Guiada</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <form id="formCrearCita" novalidate>
                                        <input type="hidden" name="institucion_id" id="final_institucion_id">
                                        <input type="hidden" name="tipo_documento" id="final_tipo_documento">
                                        <input type="hidden" name="documento" id="final_documento">

                                        <div class="modal-body" style="background:#ffffff; padding: 30px;">
                                            <!-- Indicadores de Pasos -->
                                            <div class="step-wizard">
                                                <div class="step-wizard-progress"></div>
                                                <div class="step-wizard-item active">
                                                    <div class="step-wizard-circle">1</div>
                                                    <span class="step-wizard-label">Comprobar RIF</span>
                                                </div>
                                                <div class="step-wizard-item">
                                                    <div class="step-wizard-circle">2</div>
                                                    <span class="step-wizard-label">Datos Institución</span>
                                                </div>
                                                <div class="step-wizard-item">
                                                    <div class="step-wizard-circle">3</div>
                                                    <span class="step-wizard-label">Agendar Cita</span>
                                                </div>
                                            </div>

                                            <!-- PASO 1: VERIFICAR INSTITUCIÓN -->
                                            <div class="wizard-step-panel active" id="wizardStep1">
                                                <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Verificación de la Institución</h5>
                                                <p class="text-muted mb-4" style="font-size:0.85rem;">Ingrese el Registro de Información Fiscal (RIF) de la institución externa para comprobar si ya se encuentra registrada en el sistema de citas.</p>
                                                
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <label for="tipo_documento_check" class="form-premium-label">Tipo de RIF</label>
                                                        <select class="form-select form-premium-control" id="tipo_documento_check">
                                                            <option value="J" selected>J</option>
                                                            <option value="G">G</option>
                                                            <option value="V">V</option>
                                                            <option value="E">E</option>
                                                            <option value="P">P</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <label for="cedula_check" class="form-premium-label">Número RIF / Cédula</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control form-premium-control" id="cedula_check" placeholder="Ej. 009090123" required>
                                                            <button type="button" class="btn btn-success" id="btnVerificarRif" style="background: var(--park-primary); border-color: var(--park-primary); font-weight:600; font-size:0.85rem; padding: 10px 20px; border-top-right-radius:10px; border-bottom-right-radius:10px;">Verificar RIF</button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="rif-status-box alert alert-success mt-4 shadow-sm" id="rifCheckStatus">
                                                    <!-- Info de verificación dinámica -->
                                                </div>
                                            </div>

                                            <!-- PASO 2: DATOS DE LA INSTITUCIÓN -->
                                            <div class="wizard-step-panel" id="wizardStep2">
                                                <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Información de la Institución</h5>
                                                <p class="text-muted mb-4" style="font-size:0.85rem;">Si la institución es nueva, registre sus datos fiscales, dirección geográfica e información de contacto.</p>

                                                <div class="row g-3">
                                                    <div class="col-md-7">
                                                        <label for="nombre_institucion" class="form-premium-label">Razón Social / Nombre Oficial</label>
                                                        <input type="text" class="form-control form-premium-control" id="nombre_institucion" name="nombre" placeholder="Ej. Colegio La Salle" required>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <label for="tipo_organizacion" class="form-premium-label">Tipo de Organización</label>
                                                        <select class="form-select form-premium-control" id="tipo_organizacion" name="tipo_organizacion" required>
                                                            <option value="">Seleccione Tipo</option>
                                                            <option value="Colegio Privado">Colegio Privado</option>
                                                            <option value="Escuela Pública">Escuela Pública</option>
                                                            <option value="Liceo Público">Liceo Público</option>
                                                            <option value="Liceo Privado">Liceo Privado</option>
                                                            <option value="Fundación / ONG">Fundación / ONG</option>
                                                            <option value="Institución Gubernamental">Institución Gubernamental</option>
                                                            <option value="Empresa Privada">Empresa Privada</option>
                                                            <option value="Comunidad Organizada">Comunidad Organizada</option>
                                                            <option value="Otro">Otro</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mt-2">
                                                    <div class="col-md-3">
                                                        <label for="estado_id" class="form-premium-label">Estado</label>
                                                        <select class="form-select form-premium-control" id="estado_id" required>
                                                            <option value="">Cargando estados...</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="municipio_id" class="form-premium-label">Municipio</label>
                                                        <select class="form-select form-premium-control" id="municipio_id" required disabled>
                                                            <option value="">Seleccione Municipio</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="parroquia_id" class="form-premium-label">Parroquia</label>
                                                        <select class="form-select form-premium-control" id="parroquia_id" name="parroquia_id" required disabled>
                                                            <option value="">Seleccione Parroquia</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="ciudad_id" class="form-premium-label">Ciudad</label>
                                                        <select class="form-select form-premium-control" id="ciudad_id" name="ciudad_id" required disabled>
                                                            <option value="">Seleccione Ciudad</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="mb-3 mt-3">
                                                    <label for="detalles_direccion" class="form-premium-label">Detalles de la Dirección (Calle, Av, Sector)</label>
                                                    <input type="text" class="form-control form-premium-control" id="detalles_direccion" name="detalles_direccion" placeholder="Ej. Calle 12 entre carreras 3 y 4, Galpón Nro 20">
                                                </div>

                                                <!-- Contactos del Directorio -->
                                                <div class="mt-4">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <label class="form-premium-label mb-0" style="font-weight:700;">Directorio de Contactos</label>
                                                        <button type="button" class="btn-add-contact" id="btnAddNewContact">
                                                            <span>+</span> Agregar Otro Contacto
                                                        </button>
                                                    </div>
                                                    <div id="contactsContainer">
                                                        <!-- Filas dinámicas vía JS -->
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- PASO 3: DETALLES DE LA CITA -->
                                            <div class="wizard-step-panel" id="wizardStep3">
                                                <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Agendación de la Visita Guiada</h5>
                                                <p class="text-muted mb-4" style="font-size:0.85rem;">Defina la fecha, hora, recorrido y estado de facturación de la visita guiada agendada.</p>

                                                <div class="row g-3">
                                                    <div class="col-md-12">
                                                        <label for="recorrido_id" class="form-premium-label">Recorrido del Parque Asignado (Opcional)</label>
                                                        <select class="form-select form-premium-control" id="recorrido_id" name="recorrido_id">
                                                            <option value="">Ninguno / Recorrido Libre</option>
                                                            <?php foreach ($recorridosList as $rec): ?>
                                                                <option value="<?php echo $rec['id']; ?>"><?php echo htmlspecialchars($rec['nombre']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mt-2">
                                                    <div class="col-md-6">
                                                        <label for="fecha_visita" class="form-premium-label">Fecha Planificada de Visita</label>
                                                        <input type="date" class="form-control form-premium-control" id="fecha_visita" name="fecha_visita" min="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="hora_inicio" class="form-premium-label">Hora de Inicio</label>
                                                        <select class="form-select form-premium-control" id="hora_inicio" name="hora_inicio" required>
                                                            <option value="">Seleccione Hora</option>
                                                            <option value="09:00">09:00 AM</option>
                                                            <option value="09:30">09:30 AM</option>
                                                            <option value="10:00">10:00 AM</option>
                                                            <option value="10:30">10:30 AM</option>
                                                            <option value="11:00">11:00 AM</option>
                                                            <option value="11:30">11:30 AM</option>
                                                            <option value="12:00">12:00 PM</option>
                                                            <option value="12:30">12:30 PM</option>
                                                            <option value="13:00">01:00 PM</option>
                                                            <option value="13:30">01:30 PM</option>
                                                            <option value="14:00">02:00 PM</option>
                                                            <option value="14:30">02:30 PM</option>
                                                            <option value="15:00">03:00 PM</option>
                                                            <option value="15:30">03:30 PM</option>
                                                            <option value="16:00">04:00 PM</option>
                                                            <option value="16:30">04:30 PM</option>
                                                            <option value="17:00">05:00 PM</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mt-2">
                                                    <div class="col-md-6">
                                                        <label for="metodo_pago" class="form-premium-label">Método de Facturación / Pago</label>
                                                        <select class="form-select form-premium-control" id="metodo_pago" name="metodo_pago" required>
                                                            <option value="">Seleccione Método</option>
                                                            <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                                                            <option value="Pago Móvil">Pago Móvil</option>
                                                            <option value="Efectivo Divisas">Efectivo Divisas (USD)</option>
                                                            <option value="Efectivo Bolívares">Efectivo Bolívares (VES)</option>
                                                            <option value="Exento / Donativo">Exento / Donativo del Parque</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="pago_estado" class="form-premium-label">Estado del Pago</label>
                                                        <select class="form-select form-premium-control" id="pago_estado" name="pago_estado" required>
                                                            <option value="pendiente" selected>Pendiente</option>
                                                            <option value="pagado">Pagado / Verificado</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 18px 30px;">
                                            <button type="button" class="btn-step-prev" id="btnWizardPrev" style="display: none;">Atrás</button>
                                            <button type="button" class="btn-step-next" id="btnWizardNext">Siguiente</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL: ELIMINAR CITA -->
                        <div class="modal fade modal-premium" id="modalEliminarCita" tabindex="-1" aria-labelledby="modalEliminarCitaLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: #ef4444; color: #ffffff; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalEliminarCitaLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Confirmar Eliminación de Cita</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 24px;">
                                        <input type="hidden" id="delete_cita_id">
                                        <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar la cita <strong id="deleteCitaNameSpan" class="text-danger"></strong> del sistema?</p>
                                        <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción es irreversible y revocaría el agendamiento y los registros de visitantes asociados.</p>
                                    </div>
                                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                        <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarCita" style="border-radius:10px; font-weight:600; font-size:0.9rem; background:#ef4444; border-color:#ef4444;">Eliminar Cita</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL: IMPORTAR VISITANTES (CSV) -->
                        <div class="modal fade modal-premium" id="modalImportarVisitantes" tabindex="-1" aria-labelledby="modalImportarVisitantesLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-lg modal-dialog-centered" id="importModalDialog">
                                <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalImportarVisitantesLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Importar Listado de Visitantes</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <form id="formImportarVisitantes" enctype="multipart/form-data" novalidate>
                                        <input type="hidden" name="cita_id" id="import_cita_id">
                                        <div class="modal-body" style="padding: 24px;">
                                            <!-- Panel 1: Carga de Archivo -->
                                            <div id="importUploadPanel">
                                                <p class="text-muted" style="font-size:0.85rem; line-height:1.45;">Cargue la plantilla CSV con el listado de visitantes de la cita: <strong id="importCitaNombreSpan" class="text-success"></strong>.</p>
                                                
                                                <!-- Zona de Drag & Drop -->
                                                <div class="csv-upload-zone" id="csvDropZone">
                                                    <span class="csv-upload-icon">📥</span>
                                                    <p class="csv-upload-text">Arrastra el archivo CSV aquí o <strong>haz clic para examinar</strong></p>
                                                    <input type="file" id="csvFileInput" accept=".csv" style="display: none;" required>
                                                    <div class="csv-upload-file-info" id="csvFileInfo"></div>
                                                </div>

                                                <div class="alert alert-info mt-3 p-2.5 mb-0" style="border-radius:10px; font-size:0.75rem; line-height:1.35;">
                                                    <strong>Instrucciones:</strong> El archivo debe poseer el formato del descargable con cabecera separada por punto y coma (;) o comas (,). Las columnas obligatorias son Cédula, Nombres, Apellidos y Fecha Nacimiento (AAAA-MM-DD).
                                                </div>
                                            </div>

                                            <!-- Panel 2: Previsualización y Edición -->
                                            <div id="importPreviewPanel" style="display: none;">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h6 class="mb-0" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Previsualización de Visitantes a Registrar</h6>
                                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnAgregarVisitantePreview" style="border-radius:8px; font-weight:600; font-size:0.8rem;">＋ Añadir Fila</button>
                                                </div>
                                                <div class="table-responsive" style="max-height: 300px; border: 1px solid var(--park-gray); border-radius: 12px; overflow-y: auto;">
                                                    <table class="table table-sm align-middle mb-0 text-center" style="font-size: 0.82rem;">
                                                        <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                                                            <tr>
                                                                <th style="width: 25%; text-align: left; padding-left: 10px;">Cédula (Opcional)</th>
                                                                <th style="width: 25%; text-align: left;">Nombres *</th>
                                                                <th style="width: 25%; text-align: left;">Apellidos *</th>
                                                                <th style="width: 20%; text-align: left;">F. Nacimiento *</th>
                                                                <th style="width: 5%;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="importPreviewTableBody">
                                                            <!-- Filas dinámicas -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <p class="text-muted mt-2 mb-0" style="font-size:0.75rem;">Puedes editar los datos directamente en las cajas de texto de la tabla o remover registros antes de confirmar.</p>
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                                            <!-- Botones para Panel 1 -->
                                            <div id="importUploadFooter" class="d-flex w-100 justify-content-end gap-2">
                                                <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                                <button type="button" class="btn btn-premium-primary" id="btnSiguientePrevisualizar" style="background: var(--park-primary); border-color: var(--park-primary);" disabled>Cargar y Previsualizar</button>
                                            </div>
                                            <!-- Botones para Panel 2 -->
                                            <div id="importPreviewFooter" class="d-flex w-100 justify-content-between gap-2" style="display: none !important;">
                                                <button type="button" class="btn btn-outline-secondary py-2 px-3" id="btnAtrasUpload" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Atrás</button>
                                                <button type="submit" class="btn btn-premium-primary" id="btnConfirmarImportarCSV" style="background: var(--park-primary); border-color: var(--park-primary);">Confirmar y Guardar</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL: VER VISITANTES DE CITA -->
                        <div class="modal fade modal-premium" id="modalVerVisitantes" tabindex="-1" aria-labelledby="modalVerVisitantesLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalVerVisitantesLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Visitantes de la Cita</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body" style="padding: 24px;">
                                        <p class="text-muted" style="font-size:0.85rem; line-height:1.45;">Listado de visitantes registrados para la cita: <strong id="verVisitantesCitaNombreSpan" class="text-success"></strong>.</p>
                                        
                                        <div class="table-responsive" style="max-height: 350px; border: 1px solid var(--park-gray); border-radius: 12px; overflow-y: auto;">
                                            <table class="table table-sm table-striped align-middle mb-0" style="font-size: 0.85rem;">
                                                <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                                                    <tr>
                                                        <th class="text-center" style="width: 10%;">#</th>
                                                        <th style="width: 30%;">Cédula</th>
                                                        <th style="width: 40%;">Nombre Completo</th>
                                                        <th class="text-center" style="width: 20%;">F. Nacimiento</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tablaVerVisitantesBody">
                                                    <!-- Inyectado por JS -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                                        <button type="button" class="btn btn-secondary py-2 px-4" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cerrar</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Escudo de acceso denegado premium -->
                        <div class="restricted-access-container">
                            <div class="restricted-card">
                                <div class="restricted-shield-wrapper">
                                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                                </div>
                                <h4>Acceso Restringido</h4>
                                <p>Su rol actual no cuenta con permisos autorizados para interactuar con este módulo. Si requiere visualizar esta sección, solicítelo al Super Administrador.</p>
                                <a href="index.php?view=dashboard" class="btn-premium-danger">Volver al Dashboard</a>
                            </div>
                        </div>
                    <?php endif;
                    break;



                // =====================================================================
                // CASE 4: REGISTRO DE INSTITUCIONES (DUMMY CRUD)
                // =====================================================================
                case 'instituciones':
                    if ($userPermissions['instituciones']): ?>
                        <div class="panel-container animate__animated animate__fadeIn">
                            <div class="panel-header-section">
                                <div class="panel-header-title">
                                    <h3>Registro de Instituciones</h3>
                                    <p>Historial y registro de colegios, liceos y empresas externas</p>
                                </div>
                                <div>
                                    <button class="btn-premium-primary" data-bs-toggle="modal" data-bs-target="#modalCrearInstitucion">
                                        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                        Registrar Institución
                                    </button>
                                </div>
                            </div>

                            <div class="search-filter-row">
                                <div class="search-input-group">
                                    <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                    <input type="text" id="searchInstituciones" placeholder="Filtrar por RIF, Razón Social o Tipo...">
                                </div>
                            </div>

                            <div id="loaderInstituciones" class="text-center py-5">
                                <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="text-muted mt-2">Cargando instituciones registradas...</p>
                            </div>

                            <div class="table-responsive-premium">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th>Código RIF</th>
                                            <th>Razón Social</th>
                                            <th>Tipo Org.</th>
                                            <th>Contactos</th>
                                            <th>Dirección Física</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaInstitucionesBody" style="display: none;">
                                        <!-- Se poblará vía AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- MODAL: REGISTRAR INSTITUCIÓN STANDALONE -->
                        <div class="modal fade modal-premium" id="modalCrearInstitucion" tabindex="-1" aria-labelledby="modalCrearInstitucionLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalCrearInstitucionLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Registrar Nueva Institución</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <form id="formCrearInstitucion" novalidate>
                                        <div class="modal-body" style="background:#ffffff; padding: 30px;">
                                            <div id="alertCrearInstFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                                            
                                            <!-- Sección 1: Datos Básicos -->
                                            <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Datos Identificativos</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <label for="create_tipo_documento" class="form-premium-label">Tipo RIF</label>
                                                    <select class="form-select form-premium-control" id="create_tipo_documento" name="tipo_documento" required>
                                                        <option value="J" selected>J</option>
                                                        <option value="G">G</option>
                                                        <option value="V">V</option>
                                                        <option value="E">E</option>
                                                        <option value="P">P</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="create_documento" class="form-premium-label">Número RIF / Cédula</label>
                                                    <input type="text" class="form-control form-premium-control" id="create_documento" name="documento" placeholder="Ej. 123456789 (solo números)" required>
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="create_nombre" class="form-premium-label">Razón Social</label>
                                                    <input type="text" class="form-control form-premium-control" id="create_nombre" name="nombre" placeholder="Ej. Colegio San Vicente" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="create_tipo_organizacion" class="form-premium-label">Tipo de Organización</label>
                                                    <input type="text" class="form-control form-premium-control" id="create_tipo_organizacion" name="tipo_organizacion" placeholder="Ej. educativa, empresarial" required>
                                                </div>
                                            </div>

                                            <!-- Sección 2: Ubicación Geográfica -->
                                            <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Ubicación Geográfica</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-6">
                                                    <label for="create_estado_id" class="form-premium-label">Estado</label>
                                                    <select class="form-select form-premium-control" id="create_estado_id">
                                                        <option value="">Seleccione Estado</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="create_municipio_id" class="form-premium-label">Municipio</label>
                                                    <select class="form-select form-premium-control" id="create_municipio_id" disabled>
                                                        <option value="">Seleccione Municipio</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="create_ciudad_id" class="form-premium-label">Ciudad</label>
                                                    <select class="form-select form-premium-control" id="create_ciudad_id" name="ciudad_id" disabled>
                                                        <option value="">Seleccione Ciudad</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="create_parroquia_id" class="form-premium-label">Parroquia</label>
                                                    <select class="form-select form-premium-control" id="create_parroquia_id" name="parroquia_id" disabled>
                                                        <option value="">Seleccione Parroquia</option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label for="create_detalles_direccion" class="form-premium-label">Dirección Específica (Punto de referencia, calle, local)</label>
                                                    <textarea class="form-control form-premium-control" id="create_detalles_direccion" name="detalles_direccion" rows="2" placeholder="Ej. Calle 10 con Av. Libertador" style="border-radius:12px;"></textarea>
                                                </div>
                                            </div>

                                            <!-- Sección 3: Directorio de Contactos -->
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 style="font-family:'Outfit'; font-weight:700; color:var(--park-dark); margin:0;">Directorio de Contactos</h5>
                                                <button type="button" class="btn btn-sm btn-outline-success btn-rounded-premium" id="btnCreateAddNewContact">+ Añadir Contacto</button>
                                            </div>
                                            <div id="createContactsContainer" class="d-flex flex-column gap-2 mb-2">
                                                <!-- Contactos dinámicos -->
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background: #fdfdfd; border-top: 1px solid #eeeeee; padding: 20px 24px;">
                                            <button type="button" class="btn btn-secondary btn-premium-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-success btn-premium-primary" id="btnGuardarNuevaInst">Registrar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL: EDITAR INSTITUCIÓN -->
                        <div class="modal fade modal-premium" id="modalEditarInstitucion" tabindex="-1" aria-labelledby="modalEditarInstitucionLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalEditarInstitucionLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Editar Información de Institución</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <form id="formEditarInstitucion" novalidate>
                                        <input type="hidden" id="edit_id" name="id">
                                        <input type="hidden" id="edit_entidad_id" name="entidad_id">
                                        
                                        <div class="modal-body" style="background:#ffffff; padding: 30px;">
                                            <div id="alertEditarInstFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                                            
                                            <!-- Sección 1: Datos Básicos -->
                                            <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Datos Identificativos</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <label for="edit_tipo_documento" class="form-premium-label">Tipo RIF</label>
                                                    <select class="form-select form-premium-control" id="edit_tipo_documento" name="tipo_documento" required>
                                                        <option value="J">J</option>
                                                        <option value="G">G</option>
                                                        <option value="V">V</option>
                                                        <option value="E">E</option>
                                                        <option value="P">P</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="edit_documento" class="form-premium-label">Número RIF / Cédula</label>
                                                    <input type="text" class="form-control form-premium-control" id="edit_documento" name="documento" placeholder="Ej. 123456789" required>
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="edit_nombre" class="form-premium-label">Razón Social</label>
                                                    <input type="text" class="form-control form-premium-control" id="edit_nombre" name="nombre" placeholder="Ej. Colegio San Vicente" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="edit_tipo_organizacion" class="form-premium-label">Tipo de Organización</label>
                                                    <input type="text" class="form-control form-premium-control" id="edit_tipo_organizacion" name="tipo_organizacion" placeholder="Ej. educativa, empresarial" required>
                                                </div>
                                            </div>

                                            <!-- Sección 2: Ubicación Geográfica -->
                                            <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Ubicación Geográfica</h5>
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-6">
                                                    <label for="edit_estado_id" class="form-premium-label">Estado</label>
                                                    <select class="form-select form-premium-control" id="edit_estado_id">
                                                        <option value="">Seleccione Estado</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="edit_municipio_id" class="form-premium-label">Municipio</label>
                                                    <select class="form-select form-premium-control" id="edit_municipio_id">
                                                        <option value="">Seleccione Municipio</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="edit_ciudad_id" class="form-premium-label">Ciudad</label>
                                                    <select class="form-select form-premium-control" id="edit_ciudad_id" name="ciudad_id">
                                                        <option value="">Seleccione Ciudad</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="edit_parroquia_id" class="form-premium-label">Parroquia</label>
                                                    <select class="form-select form-premium-control" id="edit_parroquia_id" name="parroquia_id">
                                                        <option value="">Seleccione Parroquia</option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label for="edit_detalles_direccion" class="form-premium-label">Dirección Específica (Punto de referencia, calle, local)</label>
                                                    <textarea class="form-control form-premium-control" id="edit_detalles_direccion" name="detalles_direccion" rows="2" placeholder="Ej. Calle 10 con Av. Libertador" style="border-radius:12px;"></textarea>
                                                </div>
                                            </div>

                                            <!-- Sección 3: Directorio de Contactos -->
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 style="font-family:'Outfit'; font-weight:700; color:var(--park-dark); margin:0;">Directorio de Contactos</h5>
                                                <button type="button" class="btn btn-sm btn-outline-success btn-rounded-premium" id="btnEditAddNewContact">+ Añadir Contacto</button>
                                            </div>
                                            <div id="editContactsContainer" class="d-flex flex-column gap-2 mb-2">
                                                <!-- Contactos dinámicos -->
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background: #fdfdfd; border-top: 1px solid #eeeeee; padding: 20px 24px;">
                                            <button type="button" class="btn btn-secondary btn-premium-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-success btn-premium-primary" id="btnGuardarEditInst">Guardar Cambios</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL: ELIMINAR INSTITUCIÓN -->
                        <div class="modal fade modal-premium" id="modalEliminarInstitucion" tabindex="-1" aria-labelledby="modalEliminarInstitucionLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
                                <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                                    <div class="modal-body text-center style-delete-modal" style="padding: 40px 30px;">
                                        <input type="hidden" id="delete_institucion_id">
                                        <div class="delete-icon-wrapper" style="width: 70px; height: 70px; background: #ffebeb; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: var(--park-danger);">
                                            <!-- SVG Warning Shield -->
                                            <svg viewBox="0 0 24 24" style="width: 36px; height: 36px; fill: currentColor;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                        </div>
                                        <h4 class="modal-title mb-2" style="font-family:'Outfit'; font-weight:700; color: var(--park-dark);">¿Eliminar Institución?</h4>
                                        <p class="text-muted" style="font-size: 0.9rem; line-height: 1.5; margin-bottom: 30px;">
                                            ¿Está seguro de que desea eliminar la institución <strong id="deleteInstNameSpan"></strong>? Esta acción no se puede deshacer y borrará permanentemente sus direcciones y contactos.
                                        </p>
                                        <div class="d-flex gap-3 justify-content-center">
                                            <button type="button" class="btn btn-secondary btn-premium-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius:12px; font-weight:600;">Cancelar</button>
                                            <button type="button" class="btn btn-danger btn-premium-danger px-4 py-2" id="btnConfirmarEliminarInstitucion" style="border-radius:12px; font-weight:600; background: var(--park-danger); border-color: var(--park-danger);">Eliminar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif;
                    break;

                // =====================================================================
                // CASE 5: CONTROL DE ACCESOS Y USUARIOS (100% FUNCIONAL PARA SUPERADMIN)
                // =====================================================================
                case 'personal':
                    if ($userPermissions['personal']): 
                        // Cargar roles desde la base de datos de forma dinámica a través del modelo
                        require_once __DIR__ . "/../config/Database.php";
                        require_once __DIR__ . "/../models/Personal.php";
                        $database = new Database();
                        $db = $database->getConnection();
                        $roles = [];
                        if ($db) {
                            $roles = Personal::obtenerRolesList($db);
                        }
                        if (empty($roles)) {
                            $roles = ['admin', 'guia'];
                        }
                        ?>
                        <script>
                            const DB_ROLES = <?php echo json_encode($roles); ?>;
                        </script>
                        <div class="panel-container">
                            <div class="panel-header-section">
                                <div class="panel-header-title">
                                    <h3>Gestión y Control de Personal</h3>
                                    <p>Administración del personal administrativo y guías autorizados para el sistema</p>
                                </div>
                                <div>
                                    <button class="btn-premium-primary" data-bs-toggle="modal" data-bs-target="#modalCrearPersonal">
                                        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                        Registrar Personal
                                    </button>
                                </div>
                            </div>

                            <!-- Barra de búsqueda dinámico real-time en front -->
                            <div class="search-filter-row">
                                <div class="search-input-group">
                                    <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                    <input type="text" id="searchUser" placeholder="Buscar por cédula, nombre o apellido...">
                                </div>
                            </div>

                            <!-- Cargador Asíncrono AJAX -->
                            <div id="loaderPersonal" class="loader-wrapper">
                                <div class="spinner-border spinner-premium" role="status">
                                    <span class="visually-hidden">Cargando registros...</span>
                                </div>
                            </div>

                            <!-- Tabla de Personal -->
                            <div class="table-responsive-premium">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cédula</th>
                                            <th>Nombre Completo</th>
                                            <th>Rol de Acceso</th>
                                            <th>Fecha Registro</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaPersonalBody" style="display:none;">
                                        <!-- Cargado vía AJAX por personal.js -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =========================================================================
                             MODALES DEL CRUD (CREAR, EDITAR, ELIMINAR)
                             ========================================================================= -->

                        <!-- Modal Crear Personal -->
                        <div class="modal fade modal-premium" id="modalCrearPersonal" tabindex="-1" aria-labelledby="modalCrearPersonalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalCrearPersonalLabel">Registrar Nuevo Personal</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form id="formCrearPersonal">
                                        <div class="modal-body">
                                            <div id="alertCrearFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                                            
                                            <div class="mb-3">
                                                <label for="cedula" class="form-premium-label">Documento de Identidad</label>
                                                <div class="input-group">
                                                    <select class="form-select form-premium-control" id="tipo_documento" name="tipo_documento" style="max-width: 90px; border-top-right-radius: 0; border-bottom-right-radius: 0;" required>
                                                        <option value="V" selected>V</option>
                                                        <option value="E">E</option>
                                                        <option value="J">J</option>
                                                        <option value="G">G</option>
                                                        <option value="P">P</option>
                                                    </select>
                                                    <input type="text" class="form-control form-premium-control" id="cedula" name="cedula" placeholder="Ej. 25123456" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="nombre" class="form-premium-label">Nombre</label>
                                                <input type="text" class="form-control form-premium-control" id="nombre" name="nombre" placeholder="Ej. Juan" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="apellido" class="form-premium-label">Apellido</label>
                                                <input type="text" class="form-control form-premium-control" id="apellido" name="apellido" placeholder="Ej. Pérez" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="rol" class="form-premium-label">Rol del Personal</label>
                                                <select class="form-select form-premium-control" id="rol" name="rol" required>
                                                    <option value="" disabled selected>Seleccione un rol...</option>
                                                    <?php foreach ($roles as $r): ?>
                                                        <option value="<?php echo htmlspecialchars($r); ?>">
                                                            <?php 
                                                            if ($r === 'admin') echo 'Administrador';
                                                            else if ($r === 'guia') echo 'Guía de Campo';
                                                            else echo htmlspecialchars(ucfirst($r));
                                                            ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="password" class="form-premium-label">Contraseña</label>
                                                <input type="password" class="form-control form-premium-control" id="password" name="password" placeholder="Mínimo 6 caracteres" required autocomplete="new-password">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                            <button type="submit" class="btn btn-premium-primary" id="btnGuardarNuevo">Registrar Personal</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Editar Personal -->
                        <div class="modal fade modal-premium" id="modalEditarPersonal" tabindex="-1" aria-labelledby="modalEditarPersonalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalEditarPersonalLabel">Modificar Personal</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form id="formEditarPersonal">
                                        <input type="hidden" id="edit_id" name="id">
                                        <div class="modal-body">
                                            <div id="alertEditarFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                                            
                                            <div class="mb-3">
                                                <label for="edit_cedula" class="form-premium-label">Documento de Identidad</label>
                                                <div class="input-group">
                                                    <select class="form-select form-premium-control" id="edit_tipo_documento" name="tipo_documento" style="max-width: 90px; border-top-right-radius: 0; border-bottom-right-radius: 0;" required>
                                                        <option value="V">V</option>
                                                        <option value="E">E</option>
                                                        <option value="J">J</option>
                                                        <option value="G">G</option>
                                                        <option value="P">P</option>
                                                    </select>
                                                    <input type="text" class="form-control form-premium-control" id="edit_cedula" name="cedula" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_nombre" class="form-premium-label">Nombre</label>
                                                <input type="text" class="form-control form-premium-control" id="edit_nombre" name="nombre" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_apellido" class="form-premium-label">Apellido</label>
                                                <input type="text" class="form-control form-premium-control" id="edit_apellido" name="apellido" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_rol" class="form-premium-label">Rol del Personal</label>
                                                <select class="form-select form-premium-control" id="edit_rol" name="rol" required>
                                                    <!-- Se inyectará dinámicamente por JS según el personal -->
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_password" class="form-premium-label">Nueva Contraseña (Opcional)</label>
                                                <input type="password" class="form-control form-premium-control" id="edit_password" name="password" placeholder="Dejar en blanco para mantener la actual" autocomplete="new-password">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                            <button type="submit" class="btn btn-premium-primary" id="btnGuardarEditar">Guardar Cambios</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Confirmar Eliminación -->
                        <div class="modal fade modal-premium" id="modalEliminarPersonal" tabindex="-1" aria-labelledby="modalEliminarPersonalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalEliminarPersonalLabel">Confirmar Eliminación</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" id="delete_id">
                                        <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar al personal <strong id="deleteUserNameSpan"></strong> del sistema?</p>
                                        <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción es irreversible y revocaría todo acceso inmediato del personal al sistema.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                        <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminar" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Eliminar Personal</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="restricted-access-container">
                            <div class="restricted-card">
                                <div class="restricted-shield-wrapper">
                                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                                </div>
                                <h4>Acceso Restringido</h4>
                                <p>Su rol actual no cuenta con permisos autorizados para interactuar con este módulo. Si requiere visualizar esta sección, solicítelo al Super Administrador.</p>
                                <a href="index.php?view=dashboard" class="btn-premium-danger">Volver al Dashboard</a>
                            </div>
                        </div>
                    <?php endif;
                    break;

                // =====================================================================
                // CASE 5: MÓDULO DE VISITANTES (ADMINISTRACIÓN CRUD)
                // =====================================================================
                case 'visitantes':
                    if ($userPermissions['visitantes']): ?>
                        <div class="panel-container animate__animated animate__fadeIn">
                            <div class="panel-header-section">
                                <div class="panel-header-title">
                                    <h3>Gestión de Visitantes</h3>
                                    <p>Historial, registro y control de visitantes en el sistema administrativo</p>
                                </div>
                                <div>
                                    <button class="btn-premium-primary" data-bs-toggle="modal" data-bs-target="#modalCrearVisitante">
                                        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                        Registrar Visitante
                                    </button>
                                </div>
                            </div>

                            <div class="search-filter-row">
                                <div class="search-input-group">
                                    <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                    <input type="text" id="searchVisitantes" placeholder="Filtrar por Cédula, Nombres o Apellidos...">
                                </div>
                            </div>

                            <div id="loaderVisitantes" class="text-center py-5">
                                <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="text-muted mt-2">Cargando listado de visitantes...</p>
                            </div>

                            <div class="table-responsive-premium">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th># ID</th>
                                            <th>Cédula / Documento</th>
                                            <th>Nombres</th>
                                            <th>Apellidos</th>
                                            <th>Fecha de Nacimiento</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaVisitantesBody" style="display: none;">
                                        <!-- Se poblará vía AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- MODAL: REGISTRAR VISITANTE MANUALMENTE -->
                        <div class="modal fade modal-premium" id="modalCrearVisitante" tabindex="-1" aria-labelledby="modalCrearVisitanteLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalCrearVisitanteLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Registrar Visitante</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <form id="formCrearVisitante" novalidate>
                                        <div class="modal-body" style="background:#ffffff; padding: 24px;">
                                            <div id="alertCrearVisitanteFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                                            <div class="mb-3">
                                                <label for="visitante_cedula" class="form-premium-label">Cédula / Documento <span class="text-muted" style="font-size:0.75rem;">(Dejar vacío para menor sin cédula)</span></label>
                                                <input type="text" class="form-control form-premium-control" id="visitante_cedula" name="cedula" placeholder="Ej. 28123456">
                                            </div>
                                            <div class="mb-3">
                                                <label for="visitante_nombres" class="form-premium-label">Nombres *</label>
                                                <input type="text" class="form-control form-premium-control" id="visitante_nombres" name="nombres" placeholder="Nombres del visitante" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="visitante_apellidos" class="form-premium-label">Apellidos *</label>
                                                <input type="text" class="form-control form-premium-control" id="visitante_apellidos" name="apellidos" placeholder="Apellidos del visitante" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="visitante_fecha_nacimiento" class="form-premium-label">Fecha de Nacimiento *</label>
                                                <input type="date" class="form-control form-premium-control" id="visitante_fecha_nacimiento" name="fecha_nacimiento" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                                            <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                            <button type="submit" class="btn btn-premium-primary" id="btnConfirmarCrearVisitante">Registrar Visitante</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL: EDITAR VISITANTE -->
                        <div class="modal fade modal-premium" id="modalEditarVisitante" tabindex="-1" aria-labelledby="modalEditarVisitanteLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalEditarVisitanteLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Editar Visitante</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <form id="formEditarVisitante" novalidate>
                                        <input type="hidden" name="id" id="edit_visitante_id">
                                        <div class="modal-body" style="background:#ffffff; padding: 24px;">
                                            <div id="alertEditarVisitanteFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                                            <div class="mb-3">
                                                <label for="edit_visitante_cedula" class="form-premium-label">Cédula / Documento <span class="text-muted" style="font-size:0.75rem;">(Dejar vacío para menor sin cédula)</span></label>
                                                <input type="text" class="form-control form-premium-control" id="edit_visitante_cedula" name="cedula">
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_visitante_nombres" class="form-premium-label">Nombres *</label>
                                                <input type="text" class="form-control form-premium-control" id="edit_visitante_nombres" name="nombres" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_visitante_apellidos" class="form-premium-label">Apellidos *</label>
                                                <input type="text" class="form-control form-premium-control" id="edit_visitante_apellidos" name="apellidos" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="edit_visitante_fecha_nacimiento" class="form-premium-label">Fecha de Nacimiento *</label>
                                                <input type="date" class="form-control form-premium-control" id="edit_visitante_fecha_nacimiento" name="fecha_nacimiento" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                                            <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                            <button type="submit" class="btn btn-premium-primary" id="btnConfirmarEditarVisitante">Guardar Cambios</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL: CONFIRMAR ELIMINAR VISITANTE -->
                        <div class="modal fade modal-premium" id="modalEliminarVisitante" tabindex="-1" aria-labelledby="modalEliminarVisitanteLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: #ef4444; color: #ffffff; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalEliminarVisitanteLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Confirmar Eliminación</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body" style="background:#ffffff; padding: 24px;">
                                        <input type="hidden" id="delete_visitante_id">
                                        <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar al visitante <strong id="deleteVisitanteNombreSpan" class="text-danger"></strong> del sistema?</p>
                                        <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción eliminará al visitante y lo desvinculará de cualquier visita guiada asociada en el cronograma.</p>
                                    </div>
                                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                        <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarVisitante" style="border-radius:10px; font-weight:600; font-size:0.9rem; background:#ef4444; border-color:#ef4444;">Eliminar Visitante</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="restricted-access-container">
                            <div class="restricted-card">
                                <div class="restricted-shield-wrapper">
                                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                                </div>
                                <h4>Acceso Restringido</h4>
                                <p>Su rol actual no cuenta con permisos autorizados para interactuar con este módulo. Si requiere visualizar esta sección, solicítelo al Super Administrador.</p>
                                <a href="index.php?view=dashboard" class="btn-premium-danger">Volver al Dashboard</a>
                            </div>
                        </div>
                    <?php endif;
                    break;

                // =====================================================================
                // CASE 6: MÓDULO DE RECORRIDOS Y ZONAS (ADMINISTRACIÓN COMPLETA)
                // =====================================================================
                case 'recorridos':
                    if ($userPermissions['recorridos']): ?>
                        <div class="recorridos-panel animate__animated animate__fadeIn">
                            <div class="recorridos-title-bar">
                                <div>
                                    <h3>Gestión de Recorridos y Zonas en el Mapa</h3>
                                    <p>Establece pines en el mapa para delimitar zonas y agrúpalos para definir recorridos</p>
                                </div>
                                <div class="d-flex gap-2 flex-nowrap">
                                    <button class="btn-park-action" id="btnModoZona">
                                        <svg viewBox="0 0 24 24" style="width:20px; height:20px; fill:currentColor; margin-right:4px;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                        Agregar Zona
                                    </button>
                                    <button class="btn-park-action btn-park-secondary" id="btnModoRecorrido">
                                        <svg viewBox="0 0 24 24" style="width:20px; height:20px; fill:currentColor; margin-right:4px;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                        Crear Recorrido
                                    </button>
                                </div>
                            </div>

                            <!-- Indicador de Modo Agregar Activo -->
                            <div class="admin-mode-indicator animate__animated animate__slideInDown" id="indicadorModoZona" style="display: none;">
                                <span>
                                    <span class="spinner-grow spinner-grow-sm text-danger" role="status" style="width: 12px; height: 12px;"></span>
                                    <strong>MODO AGREGAR ZONA ACTIVO:</strong> Haz clic en cualquier parte del mapa para marcar y registrar una zona.
                                </span>
                                <button class="btn btn-sm btn-cancel-modo" id="btnCancelarModoZona">Cancelar Modo</button>
                            </div>

                            <!-- Formulario para Crear Recorrido (Inicialmente Oculto, arriba del mapa) -->
                            <div id="panelCrearRecorridoForm" class="card mb-4 animate__animated animate__slideInDown" style="display: none; border-radius: 16px; border: 1.5px dashed var(--park-primary); box-shadow: 0 4px 12px rgba(0,0,0,0.02); overflow: hidden;">
                                <div class="card-body" style="background: #ffffff; padding: 20px;">
                                    <h5 class="card-title font-weight-bold" style="font-family:'Outfit'; font-weight: 700; color: var(--park-primary);">Nuevo Recorrido</h5>
                                    <p class="text-muted" style="font-size:0.8rem; line-height: 1.35;">Escribe el nombre del recorrido y haz clic en orden sobre las zonas del mapa para crear la secuencia.</p>
                                    
                                    <form id="formCrearRecorrido">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="nombreRecorrido" class="form-label" style="font-weight: 600; font-size: 0.85rem; color: var(--park-dark);">Nombre del Recorrido</label>
                                                <input type="text" class="form-control form-premium-control" id="nombreRecorrido" name="nombre" placeholder="Ej. Ruta de los Primates" required style="border-radius:10px;">
                                            </div>
                                            <div class="col-md-8 mb-3">
                                                <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: var(--park-dark);">Ruta Trazada</label>
                                                <div id="timelineCreacionWrapper" style="background:#f8f9fa; border-radius:10px; padding:10px; border: 1px solid var(--park-gray); min-height: 50px;">
                                                    <!-- Timeline dinámico -->
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 justify-content-end mt-2">
                                            <button type="button" class="btn btn-secondary btn-sm px-3 py-2" id="btnCancelarCrearRecorrido" style="border-radius:8px; font-weight:600;">Cancelar</button>
                                            <button type="submit" class="btn btn-success btn-sm px-3 py-2" id="btnGuardarRecorrido" style="border-radius:8px; font-weight:600; background: var(--park-primary); border-color: var(--park-primary);">Guardar Recorrido</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="row g-4">
                                <!-- Columna Izquierda: El Mapa Interactivo Responsivo -->
                                <div class="col-lg-8">
                                    <div class="map-wrapper shadow-sm" id="mapWrapper">
                                        <img src="view/assets/img/map.jpg" alt="Mapa del Parque Zoológico Bararida" id="mapaParque" class="map-image">
                                        <!-- Overlay de SVG -->
                                        <svg id="mapSvgOverlay" class="map-svg-overlay" viewBox="0 0 100 100" preserveAspectRatio="none"></svg>
                                        <!-- Contenedor de Pines -->
                                        <div id="mapPinsContainer"></div>
                                        <!-- Tooltip Flotante de Edición Activa (Premium) -->
                                        <div class="map-edit-tooltip" id="mapEditTooltip" style="display: none;">
                                            <span class="pulse-indicator"></span>
                                            <span class="tooltip-text" id="mapEditTooltipText">📍 Modo Edición Activo</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Columna Derecha: Paneles de Control Lateral -->
                                <div class="col-lg-4">
                                    
                                    <!-- Lista de Recorridos Existentes -->
                                    <div class="card mb-4" style="border-radius: 16px; border:1px solid var(--park-gray); overflow: hidden;">
                                        <div class="card-body" style="padding: 20px;">
                                            <h5 class="card-title font-weight-bold mb-3" style="font-family:'Outfit'; font-weight: 700; color: var(--park-dark);">Recorridos Existentes</h5>
                                            <div class="sidebar-panel-list" id="adminListaRecorridos">
                                                <!-- Se poblará vía AJAX -->
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Lista de Zonas Pineadas -->
                                    <div class="card mb-4" style="border-radius: 16px; border:1px solid var(--park-gray); overflow: hidden;">
                                        <div class="card-body" style="padding: 20px;">
                                            <h5 class="card-title font-weight-bold mb-3" style="font-family:'Outfit'; font-weight: 700; color: var(--park-dark);">Zonas Registradas</h5>
                                            <div class="sidebar-panel-list" id="adminListaZonas">
                                                <!-- Se poblará vía AJAX -->
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Modal para Crear Nueva Zona -->
                        <div class="modal fade modal-premium" id="modalCrearZona" tabindex="-1" aria-labelledby="modalCrearZonaLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: var(--park-primary); color: #ffffff; border-bottom: none; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalCrearZonaLabel" style="font-family:'Outfit'; font-weight:600; margin: 0;">Nueva Zona en el Mapa</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <form id="formCrearZona">
                                        <div class="modal-body" style="background:#ffffff; padding: 24px;">
                                            <div class="mb-3">
                                                <label for="nombreZona" class="form-premium-label" style="font-weight: 600; color: var(--park-dark);">Nombre de la Zona</label>
                                                <input type="text" class="form-control form-premium-control" id="nombreZona" name="nombre" placeholder="Ej. Área de Serpentario" required style="border-radius:10px;">
                                            </div>
                                            <div class="mb-3">
                                                <label for="nuevaZonaCoords" class="form-premium-label" style="font-weight: 600; color: var(--park-dark);">Coordenadas del Pin (%)</label>
                                                <input type="text" class="form-control form-premium-control" id="nuevaZonaCoords" name="coordenadas" readonly required style="background:#f8f9fa; border-radius:10px; cursor:not-allowed;">
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                                            <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                            <button type="submit" class="btn btn-premium-primary" id="btnGuardarZona" style="background: var(--park-primary); border-color: var(--park-primary);">Registrar Zona</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Confirmar Eliminación de Zona -->
                        <div class="modal fade modal-premium" id="modalConfirmarEliminarZona" tabindex="-1" aria-labelledby="modalConfirmarEliminarZonaLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: #ef4444; color: #ffffff; border-bottom: none; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalConfirmarEliminarZonaLabel" style="font-family:'Outfit'; font-weight:600; margin: 0;">Confirmar Eliminación de Zona</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body" style="background:#ffffff; padding: 24px;">
                                        <input type="hidden" id="delete_zona_id_modal">
                                        <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar la zona <strong id="deleteZonaNombreSpan" class="text-danger"></strong> del mapa?</p>
                                        <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción es irreversible y también eliminará las paradas asociadas de cualquier recorrido activo.</p>
                                    </div>
                                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                        <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarZonaModal" style="border-radius:10px; font-weight:600; font-size:0.9rem; background: #ef4444; border-color: #ef4444;">Eliminar Zona</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Confirmar Eliminación de Recorrido -->
                        <div class="modal fade modal-premium" id="modalConfirmarEliminarRecorrido" tabindex="-1" aria-labelledby="modalConfirmarEliminarRecorridoLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                                    <div class="modal-header" style="background: #ef4444; color: #ffffff; border-bottom: none; padding: 20px 24px;">
                                        <h5 class="modal-title" id="modalConfirmarEliminarRecorridoLabel" style="font-family:'Outfit'; font-weight:600; margin: 0;">Confirmar Eliminación de Recorrido</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body" style="background:#ffffff; padding: 24px;">
                                        <input type="hidden" id="delete_recorrido_id_modal">
                                        <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar el recorrido <strong id="deleteRecorridoNombreSpan" class="text-danger"></strong> del sistema?</p>
                                        <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción es irreversible y revocaría el itinerario de forma permanente.</p>
                                    </div>
                                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                                        <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarRecorridoModal" style="border-radius:10px; font-weight:600; font-size:0.9rem; background: #ef4444; border-color: #ef4444;">Eliminar Recorrido</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="restricted-access-container">
                            <div class="restricted-card">
                                <div class="restricted-shield-wrapper">
                                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
                                </div>
                                <h4>Acceso Restringido</h4>
                                <p>Su rol actual no cuenta con permisos autorizados para interactuar con este módulo. Si requiere visualizar esta sección, solicítelo al Super Administrador.</p>
                                <a href="index.php?view=dashboard" class="btn-premium-danger">Volver al Dashboard</a>
                            </div>
                        </div>
                    <?php endif;
                    break;

            endswitch; ?>

            </div><!-- /.content-area -->
        </main>
    </div><!-- /.wrapper -->

    <!-- Huella de oso decorativa gigante en marca de agua para el fondo del contenido central -->
    <div class="content-bg-paw"></div>

    <!-- Carga de jQuery Local -->
    <script src="view/assets/js/jquery.min.js"></script>
    <!-- Bootstrap JS Bundle Local -->
    <script src="view/assets/js/bootstrap.bundle.min.js"></script>

    <!-- Vincular el script del CRUD de forma asíncrona solo si estamos en la vista de personal -->
    <?php if ($currentView === 'personal' && $userPermissions['personal']): ?>
        <script src="view/assets/js/personal.js"></script>
    <?php endif; ?>

    <!-- Vincular la librería Chart.js de forma local para las estadísticas del dashboard -->
    <?php if ($currentView === 'dashboard'): ?>
        <script src="view/assets/js/chart.min.js"></script>
    <?php endif; ?>

    <!-- Vincular el script de recorridos dinámicamente si corresponde -->
    <?php if ($currentView === 'dashboard' || ($currentView === 'recorridos' && $userPermissions['recorridos'])): ?>
        <script src="view/assets/js/recorridos.js?v=1.1"></script>
    <?php endif; ?>

    <!-- Vincular el script de citas dinámicamente si corresponde -->
    <?php if ($currentView === 'citas' && $userPermissions['citas']): ?>
        <script src="view/assets/js/citas.js"></script>
    <?php endif; ?>

    <!-- Vincular el script de instituciones dinámicamente si corresponde -->
    <?php if ($currentView === 'instituciones' && $userPermissions['instituciones']): ?>
        <script src="view/assets/js/instituciones.js"></script>
    <?php endif; ?>

    <!-- Vincular el script de visitantes dinámicamente si corresponde -->
    <?php if ($currentView === 'visitantes' && $userPermissions['visitantes']): ?>
        <script src="view/assets/js/visitantes.js"></script>
    <?php endif; ?>

    <!-- Lógica del Dashboard (sidebar responsivo) -->
    <script src="view/assets/js/dashboard.js"></script>
</body>
</html>