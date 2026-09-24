<?php
/**
 * PermisoController
 * Gestiona las acciones AJAX del módulo App (Configuración de permisos por rol).
 * Solo accesible por el superadmin.
 * Respeta el patrón MVC operando mediante el modelo Permiso con setters/getters.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

// Guard de seguridad: solo superadmin puede usar este controlador
$isSuperAdmin = isset($_SESSION['personal']) && (
    (isset($_SESSION['personal']['rol']) && strtolower(trim($_SESSION['personal']['rol'])) === 'superadmin') ||
    (isset($_SESSION['personal']['rol_id']) && (int)$_SESSION['personal']['rol_id'] === 1)
);

if (!$isSuperAdmin) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Acceso no autorizado. Se requiere rol de superadmin."]);
    exit;
}

require_once __DIR__ . "/../models/Permiso.php";

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// =========================================================================
// Enrutador de Acciones
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'listar') {
        listarMatriz();
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Acción GET no reconocida."]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'actualizar') {
        actualizarPermiso();
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Acción POST no reconocida."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método HTTP no permitido."]);
}

// =========================================================================
// Funciones de Acción
// =========================================================================

/**
 * Retorna la matriz completa de roles × módulos para poblar la tabla de la vista App.
 * Los módulos 'app' y 'dashboard' se excluyen de la lista editable.
 */
function listarMatriz() {
    try {
        $permisoModel = new Permiso();
        $matriz = $permisoModel->getMatrizCompleta(['app', 'dashboard']);
        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "data"    => $matriz
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al obtener la matriz de permisos: " . $e->getMessage()]);
    }
}

/**
 * Actualiza un permiso individual (rol_id + modulo_id + puede_acceder).
 * Todas las validaciones se realizan aquí en el controlador.
 */
function actualizarPermiso() {
    // Leer y validar parámetros del body POST en el controlador
    $rol_id        = isset($_POST['rol_id'])        ? filter_var($_POST['rol_id'],    FILTER_VALIDATE_INT) : false;
    $modulo_id     = isset($_POST['modulo_id'])     ? filter_var($_POST['modulo_id'], FILTER_VALIDATE_INT) : false;
    $puede_acceder = isset($_POST['puede_acceder']) ? $_POST['puede_acceder'] : null;

    $errors = [];

    if ($rol_id === false || $rol_id <= 0) {
        $errors[] = "El campo 'rol_id' es inválido o requerido.";
    }
    if ($modulo_id === false || $modulo_id <= 0) {
        $errors[] = "El campo 'modulo_id' es inválido o requerido.";
    }
    if ($puede_acceder === null || !in_array((string)$puede_acceder, ['0', '1'], true)) {
        $errors[] = "El campo 'puede_acceder' debe ser 0 o 1.";
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => implode(" ", $errors)]);
        return;
    }

    try {
        $permisoModel = new Permiso();
        $permisoModel->setRolId((int)$rol_id);
        $permisoModel->setModuloId((int)$modulo_id);
        $permisoModel->setPuedeAcceder((int)$puede_acceder);
        $permisoModel->actualizarPermiso();

        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "message" => "Permiso actualizado correctamente."
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
