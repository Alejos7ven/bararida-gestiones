<?php
/**
 * Vista de Dashboard - dashboard.php
 * Panel de administración con barra lateral adaptativa y control de roles (RBAC) dinámico.
 * Actúa como contenedor de vistas SPA internas usando parámetros $_GET['view'].
 */

// Seguridad básica: Si no hay sesión activa de personal, redirigir al Front Controller
if (!session_status() === PHP_SESSION_NONE) {
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
$userCedula = htmlspecialchars($currentUser['cedula']);

// Definición de la Matriz RBAC para el Prototipo
// true = Acceso Concedido, false = Acceso Restringido
$permissions = [
    'superadmin' => [
        'personal' => true,
        'guias' => true,
        'instituciones' => true,
        'citas' => true,
        'recorridos' => true
    ],
    'admin' => [
        'personal' => false, // Asistente no gestiona personals
        'guias' => true,
        'instituciones' => true,
        'citas' => true,
        'recorridos' => true
    ],
    'guia' => [
        'personal' => false,
        'guias' => false,
        'instituciones' => false,
        'citas' => true, // Acceso único a su cronograma de visitas
        'recorridos' => false
    ]
];

// Obtener permisos para el rol actual
$userPermissions = isset($permissions[$userRol]) ? $permissions[$userRol] : [
    'personal' => false, 'guias' => false, 'instituciones' => false, 'citas' => false, 'recorridos' => false
];

// Obtener la vista solicitada (Enrutador Interno)
$currentView = isset($_GET['view']) ? trim($_GET['view']) : 'dashboard';
$validViews = ['dashboard', 'citas', 'guias', 'instituciones', 'personal', 'recorridos'];
if (!in_array($currentView, $validViews)) {
    $currentView = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <link rel="stylesheet" href="view/assets/css/recorridos.css">

    <!-- Cargar estilos específicos para CRUDs y Acceso Restringido si no estamos en el Dashboard de inicio -->
    <?php if ($currentView !== 'dashboard' && $currentView !== 'recorridos'): ?>
        <link rel="stylesheet" href="view/assets/css/personal.css">
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
                    <li class="<?php echo ($currentView === 'guias') ? 'active' : ''; ?>">
                        <a href="index.php?view=guias">
                            <!-- SVG Guides Icon -->
                            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 5C13.66 5 15 6.34 15 8C15 9.66 13.66 11 12 11C10.34 11 9 9.66 9 8C9 6.34 10.34 5 12 5ZM12 19.2C9.5 19.2 7.29 17.92 6 15.98C6.03 13.99 10 12.9 12 12.9C13.99 12.9 17.97 13.99 18 15.98C16.71 17.92 14.5 19.2 12 19.2Z"/></svg>
                            Guías
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
                    <?php if ($userRol === 'superadmin'): // Solo el superadmin ve la pestaña de Personals ?>
                    <li class="<?php echo ($currentView === 'personal') ? 'active' : ''; ?>">
                        <a href="index.php?view=personal">
                            <!-- SVG Settings Icon -->
                            <svg viewBox="0 0 24 24"><path d="M19.14 12.94C19.14 12.63 19.17 12.32 19.17 12C19.17 11.68 19.14 11.37 19.09 11.06L21.26 9.37C21.45 9.22 21.5 8.95 21.39 8.74L19.33 5.17C19.22 4.96 18.96 4.88 18.74 4.96L16.18 5.99C15.65 5.58 15.07 5.24 14.44 4.99L14.05 2.27C14.01 2.03 13.8 1.85 13.56 1.85H9.44C9.2 1.85 8.99 2.03 8.95 2.27L8.56 4.99C7.93 5.24 7.35 5.58 6.82 5.99L4.26 4.96C4.04 4.88 3.78 4.96 3.67 5.17L1.61 8.74C1.5 8.95 1.55 9.22 1.74 9.37L3.91 11.06C3.86 11.37 3.83 11.69 3.83 12C3.83 12.31 3.86 12.63 3.91 12.94L1.74 14.63C1.55 14.78 1.5 15.05 1.61 15.26L3.67 18.83C3.78 19.04 4.04 19.12 4.26 19.04L6.82 18.01C7.35 18.42 7.93 18.76 8.56 19.01L8.95 21.73C8.99 21.97 9.2 22.15 9.44 22.15H13.56C13.8 22.15 14.01 21.97 14.05 21.73L14.44 19.01C15.07 18.76 15.65 18.42 16.18 18.01L18.74 19.04C18.96 19.12 19.22 19.04 19.33 18.83L21.39 15.26C21.5 15.05 21.45 14.78 21.26 14.63L19.14 12.94ZM12 15.6C10.01 15.6 8.4 13.99 8.4 12C8.4 10.01 10.01 8.4 12 8.4C13.99 8.4 15.6 10.01 15.6 12C15.6 13.99 13.99 15.6 12 15.6Z"/></svg>
                            Personals
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

                        <!-- 2. Personal Guía -->
                        <!-- <div class="col-md-6 col-lg-3">
                            <div class="module-card">
                                <div class="module-icon-container bg-icon-guias">
                                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 5C13.66 5 15 6.34 15 8C15 9.66 13.66 11 12 11C10.34 11 9 9.66 9 8C9 6.34 10.34 5 12 5ZM12 19.2C9.5 19.2 7.29 17.92 6 15.98C6.03 13.99 10 12.9 12 12.9C13.99 12.9 17.97 13.99 18 15.98C16.71 17.92 14.5 19.2 12 19.2Z"/></svg>
                                </div>
                                <h4 class="module-title">Personal Guía</h4>
                                <p class="module-desc">
                                    Gestión de guías de campo, asignación de itinerarios diarios y control de disponibilidad de personal.
                                </p>
                                <a href="index.php?view=guias" class="btn-module btn-module-active">Entrar al Módulo</a>
                            </div>
                        </div> -->

                        <!-- 3. Instituciones -->
                        <!-- <div class="col-md-6 col-lg-3">
                            <div class="module-card">
                                <div class="module-icon-container bg-icon-instituciones">
                                    <svg viewBox="0 0 24 24"><path d="M12 3L1 9V11H3V20H5V11H19V20H21V11H23V9L12 3Z"/></svg>
                                </div>
                                <h4 class="module-title">Instituciones</h4>
                                <p class="module-desc">
                                    Registro e historial de colegios, organizaciones públicas o privadas que solicitan visitas.
                                </p>
                                <a href="index.php?view=instituciones" class="btn-module btn-module-active">Entrar al Módulo</a>
                            </div>
                        </div> -->

                        <!-- 4. Control Personals -->
                        <!-- <div class="col-md-6 col-lg-3">
                            <div class="module-card">
                                <div class="module-icon-container bg-icon-personal">
                                    <svg viewBox="0 0 24 24"><path d="M12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12ZM12 14C9.33 14 4 15.33 4 18V20H20V18C20 15.33 14.67 14 12 14Z"/></svg>
                                </div>
                                <h4 class="module-title">Control Personals</h4>
                                <p class="module-desc">
                                    Administración de cuentas del personal administrativo y guías, y auditoría general de accesos.
                                </p>
                                <a href="index.php?view=personal" class="btn-module btn-module-active">Entrar al Módulo</a>
                            </div>
                        </div> -->

                    </div>

                    <!-- WIDGET PREMIUM: MAPA INTERACTIVO Y RECORRIDOS (VISOR GENERAL PARA TODOS LOS ROLES) -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="dashboard-map-card">
                                <div class="dashboard-map-header">
                                    <h3>
                                        <svg viewBox="0 0 24 24"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>
                                        Itinerarios y Zonas del Parque Bararida
                                    </h3>
                                    <div class="tour-selector-box d-flex align-items-center gap-3">
                                        <div class="flex-grow-1">
                                            <label for="selectRecorridoDashboard">Visualizar Recorrido</label>
                                            <select id="selectRecorridoDashboard" class="form-select tour-select-custom" style="min-width: 280px;">
                                                <option value="">Cargando recorridos...</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Mapa responsivo con overlay SVG -->
                                <div class="map-wrapper shadow-sm">
                                    <img src="view/assets/img/map.jpg" alt="Mapa del Parque Zoológico Bararida" id="mapaParque" class="map-image">
                                    <svg id="mapSvgOverlay" class="map-svg-overlay" viewBox="0 0 100 100" preserveAspectRatio="none"></svg>
                                    <div id="mapPinsContainer"></div>
                                </div>
                                
                                <!-- Timeline de paradas (Secuencia del recorrido) -->
                                <div id="dashboardTimelineWrapper" class="active-tour-indicator-list" style="display: none; animation: slideDownFade 0.3s ease;">
                                    <h5>
                                        <span>Itinerario Activo:</span> <strong id="nombreRecorridoActivoSpan" class="text-success"></strong>
                                    </h5>
                                    <div id="timelineDashboardSteps" class="tour-steps-timeline mt-2">
                                        <!-- Se poblará vía JS -->
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
                    if ($userPermissions['citas']): ?>
                        <div class="panel-container">
                            <div class="panel-header-section">
                                <div class="panel-header-title">
                                    <h3>Gestión de Citas y Visitas Guiadas</h3>
                                    <p>Control de solicitudes y cronograma de itinerarios del zoológico</p>
                                </div>
                                <div>
                                    <button class="btn-premium-primary" onclick="alert('Esta es una maqueta (Dummy CRUD) para la Fase I. La base de datos y endpoints se integrarán en la siguiente fase.');">
                                        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                        Nueva Cita
                                    </button>
                                </div>
                            </div>

                            <!-- Tarjetas de Estadísticas Mockup -->
                            <div class="row stats-row g-3">
                                <div class="col-sm-4">
                                    <div class="stat-card-premium">
                                        <div class="stat-card-icon-wrapper bg-icon-citas">
                                            <svg viewBox="0 0 24 24" style="width:24px; height:24px; fill:currentColor;"><path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V10H19V20Z"/></svg>
                                        </div>
                                        <div class="stat-card-info">
                                            <h5>12</h5>
                                            <p>Total Citas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="stat-card-premium">
                                        <div class="stat-card-icon-wrapper bg-warning">
                                            <svg viewBox="0 0 24 24" style="width:24px; height:24px; fill:currentColor;"><path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z"/></svg>
                                        </div>
                                        <div class="stat-card-info">
                                            <h5>3</h5>
                                            <p>Pendientes</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="stat-card-premium">
                                        <div class="stat-card-icon-wrapper bg-success">
                                            <svg viewBox="0 0 24 24" style="width:24px; height:24px; fill:currentColor;"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                                        </div>
                                        <div class="stat-card-info">
                                            <h5>9</h5>
                                            <p>Completadas</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Barra de búsqueda dummy -->
                            <div class="search-filter-row">
                                <div class="search-input-group">
                                    <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                    <input type="text" placeholder="Filtrar citas por institución..." disabled style="cursor: not-allowed;">
                                </div>
                            </div>

                            <!-- Tabla Mockup de Citas -->
                            <div class="table-responsive-premium">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Institución</th>
                                            <th>Fecha y Hora</th>
                                            <th>Visitantes</th>
                                            <th>Guía Asignado</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>#CIT-001</strong></td>
                                            <td>Colegio La Salle Barquisimeto</td>
                                            <td>22/05/2026 09:00 AM</td>
                                            <td><span class="badge-premium badge-premium-info">45 alumnos</span></td>
                                            <td>Carlos Rodríguez</td>
                                            <td><span class="badge-premium badge-premium-admin">Aprobado</span></td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="btn-action btn-action-edit" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg></button>
                                                    <button class="btn-action btn-action-delete" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>#CIT-002</strong></td>
                                            <td>U.E. Colegio San Vicente</td>
                                            <td>25/05/2026 10:30 AM</td>
                                            <td><span class="badge-premium badge-premium-info">30 alumnos</span></td>
                                            <td class="text-muted italic">Pendiente de Asignación</td>
                                            <td><span class="badge-premium badge-premium-guia">Pendiente</span></td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="btn-action btn-action-edit" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg></button>
                                                    <button class="btn-action btn-action-delete" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>#CIT-003</strong></td>
                                            <td>Fundación Regional "Niño Simón"</td>
                                            <td>18/05/2026 02:00 PM</td>
                                            <td><span class="badge-premium badge-premium-info">50 niños</span></td>
                                            <td>Carlos Rodríguez</td>
                                            <td><span class="badge-premium badge-premium-superadmin">Completado</span></td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="btn-action btn-action-edit" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg></button>
                                                    <button class="btn-action btn-action-delete" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
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
                // CASE 3: PERSONAL GUÍA (DUMMY CRUD)
                // =====================================================================
                case 'guias':
                    if ($userPermissions['guias']): 
                        // Cargar base de datos y obtener personals con rol de guía de forma dinámica y segura
                        require_once __DIR__ . "/../config/Database.php";
                        $database = new Database();
                        $db = $database->getConnection();
                        $guias = [];
                        if ($db) {
                            try {
                                $query = "SELECT cedula, nombre, apellido FROM personals WHERE rol = 'guia' ORDER BY id DESC";
                                $stmt = $db->prepare($query);
                                $stmt->execute();
                                $guias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                error_log("Error al listar guías en dashboard: " . $e->getMessage());
                            }
                        }
                        ?>
                        <div class="panel-container">
                            <div class="panel-header-section">
                                <div class="panel-header-title">
                                    <h3>Control de Personal Guía</h3>
                                    <p>Asignación de guías de campo y disponibilidad del personal de visitas</p>
                                </div>
                                <div>
                                    <button class="btn-premium-primary" onclick="alert('Esta es una maqueta (Dummy CRUD) para la Fase I. La base de datos y endpoints se integrarán en la siguiente fase.');">
                                        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                        Registrar Guía
                                    </button>
                                </div>
                            </div>

                            <div class="search-filter-row">
                                <div class="search-input-group">
                                    <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                    <input type="text" placeholder="Filtrar guías por nombre..." disabled style="cursor: not-allowed;">
                                </div>
                            </div>

                            <div class="table-responsive-premium">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th>Cédula</th>
                                            <th>Nombre Completo</th>
                                            <th>Disponibilidad</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($guias)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">No hay guías registrados en el sistema.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($guias as $idx => $guia): 
                                                // Asignamos una disponibilidad dinámica mockeada para darle dinamismo visual
                                                $dispBadge = ($idx % 2 === 0) 
                                                    ? '<span class="badge-premium badge-premium-admin">Disponible</span>' 
                                                    : '<span class="badge-premium badge-premium-guia">En Recorrido</span>';
                                                
                                                $nombreCompleto = htmlspecialchars($guia['nombre'] . ' ' . $guia['apellido']);
                                                $cedula = htmlspecialchars($guia['cedula']);
                                            ?>
                                                <tr>
                                                    <td><strong><?php echo $cedula; ?></strong></td>
                                                    <td><?php echo $nombreCompleto; ?></td>
                                                    <td><?php echo $dispBadge; ?></td>
                                                    <td>
                                                        <div class="table-actions">
                                                            <button class="btn-action btn-action-edit" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg></button>
                                                            <button class="btn-action btn-action-delete" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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
                // CASE 4: REGISTRO DE INSTITUCIONES (DUMMY CRUD)
                // =====================================================================
                case 'instituciones':
                    if ($userPermissions['instituciones']): ?>
                        <div class="panel-container">
                            <div class="panel-header-section">
                                <div class="panel-header-title">
                                    <h3>Registro de Instituciones</h3>
                                    <p>Historial y registro de colegios, liceos y empresas externas</p>
                                </div>
                                <div>
                                    <button class="btn-premium-primary" onclick="alert('Esta es una maqueta (Dummy CRUD) para la Fase I. La base de datos y endpoints se integrarán en la siguiente fase.');">
                                        <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                        Registrar Institución
                                    </button>
                                </div>
                            </div>

                            <div class="search-filter-row">
                                <div class="search-input-group">
                                    <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                    <input type="text" placeholder="Filtrar por RIF o Razón Social..." disabled style="cursor: not-allowed;">
                                </div>
                            </div>

                            <div class="table-responsive-premium">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th>Código RIF</th>
                                            <th>Razón Social</th>
                                            <th>Tipo</th>
                                            <th>Teléfono</th>
                                            <th>Dirección Física</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>J-12345678-0</strong></td>
                                            <td>Colegio La Salle Barquisimeto</td>
                                            <td><span class="badge-premium badge-premium-admin">Colegio Privado</span></td>
                                            <td>0251-2514455</td>
                                            <td>Av. La Salle con Av. Florencio Jiménez</td>
                                            <td>
                                                <div class="table-actions">
                                                    <button class="btn-action btn-action-edit" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg></button>
                                                    <button class="btn-action btn-action-delete" onclick="alert('Funcionalidad de maqueta inactiva.');"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
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
                // CASE 5: CONTROL DE ACCESOS Y USUARIOS (100% FUNCIONAL PARA SUPERADMIN)
                // =====================================================================
                case 'personal':
                    if ($userPermissions['personal']): ?>
                        <div class="panel-container">
                            <div class="panel-header-section">
                                <div class="panel-header-title">
                                    <h3>Gestión y Control de Personals</h3>
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

                            <!-- Tabla de Personals -->
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
                                                <label for="cedula" class="form-premium-label">Cédula de Identidad</label>
                                                <input type="text" class="form-control form-premium-control" id="cedula" name="cedula" placeholder="Ej. 25123456" required>
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
                                                    <option value="admin">Administrador</option>
                                                    <option value="guia">Guía de Campo</option>
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
                                                <label for="edit_cedula" class="form-premium-label">Cédula de Identidad</label>
                                                <input type="text" class="form-control form-premium-control" id="edit_cedula" name="cedula" required>
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

    <!-- Vincular el script del CRUD de forma asíncrona solo si estamos en la vista de personals -->
    <?php if ($currentView === 'personal' && $userPermissions['personal']): ?>
        <script src="view/assets/js/personal.js"></script>
    <?php endif; ?>

    <!-- Vincular el script de recorridos dinámicamente si corresponde -->
    <?php if ($currentView === 'dashboard' || ($currentView === 'recorridos' && $userPermissions['recorridos'])): ?>
        <script src="view/assets/js/recorridos.js"></script>
    <?php endif; ?>

    <!-- Lógica del Dashboard (sidebar responsivo) -->
    <script src="view/assets/js/dashboard.js"></script>
</body>
</html>
