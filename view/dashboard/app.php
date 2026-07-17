<?php
// Evitar acceso directo sin el contexto del dashboard
if (!isset($userPermissions) || !isset($userRol)) {
    exit;
}
// Guard de seguridad: solo superadmin puede acceder a este módulo
if ($userRol !== 'superadmin') {
    echo '<div class="panel-container"><div class="alert alert-danger mt-4">No tienes permiso para acceder a este módulo.</div></div>';
    exit;
}
?>

<div class="panel-container">

    <!-- Encabezado del módulo -->
    <div class="panel-header-section">
        <div class="panel-header-title">
            <h3>Configuración del Sistema</h3>
            <p>Gestión de permisos de acceso por rol a cada módulo de la aplicación.</p>
        </div>
    </div>

    <!-- Tarjeta informativa -->
    <div class="app-info-card">
        <svg viewBox="0 0 24 24" class="app-info-icon">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
        </svg>
        <div>
            <strong>Matriz de Acceso por Rol</strong>
            <p>Activa o desactiva el acceso a cada módulo usando los toggles. Los cambios se guardan automáticamente en tiempo real. El módulo <em>Dashboard</em> y <em>Configuración</em> no son editables. El <em>Super Admin</em> siempre tiene acceso total.</p>
        </div>
    </div>

    <!-- Loader mientras carga la tabla -->
    <div id="permisos-loader" class="loader-wrapper">
        <div class="spinner-border spinner-premium" role="status">
            <span class="visually-hidden">Cargando permisos...</span>
        </div>
    </div>

    <!-- Tabla de permisos (dinámica vía AJAX) -->
    <div id="permisos-table" class="table-responsive-premium" style="display:none;">
        <table class="table-premium permisos-matrix-table">
            <thead>
                <tr id="permisos-thead-row">
                    <!-- Cabeceras inyectadas por app.js -->
                </tr>
            </thead>
            <tbody id="permisos-tbody">
                <!-- Filas inyectadas por app.js -->
            </tbody>
        </table>
    </div>

</div>
