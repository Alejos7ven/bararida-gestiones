<?php
/**
 * Controlador Frontal (Front Controller) - index.php
 * Punto de entrada de la aplicación. Gestiona el flujo de autenticación y redirecciones.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Procesar cierre de sesión de forma rápida si se solicita
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Destruimos la sesión
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    // Redirección limpia para limpiar la URL
    header("Location: index.php");
    exit;
}

// Lógica de enrutamiento basada en sesión
if (isset($_SESSION['personal'])) {
    // Si el personal ya está autenticado, despachar vía DashboardController
    require_once __DIR__ . "/controllers/DashboardController.php";
    extract(despacharDashboard());
    require_once __DIR__ . "/view/dashboard.php";
} else {
    // Si no está autenticado, cargamos la pantalla de Login
    require_once __DIR__ . "/view/login.php";
}
