<?php
/**
 * PermisoController
 * Gestiona las acciones AJAX del módulo App (Configuración de permisos por rol).
 * Solo accesible por el superadmin.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

// Guard de seguridad: solo superadmin puede usar este controlador
if (!isset($_SESSION['personal']) || $_SESSION['personal']['rol'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Acceso no autorizado."]);
    exit;
}

require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../models/Permiso.php";

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Inicializar conexión a la BD
$database = new Database();
$db       = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "No se pudo establecer conexión con la base de datos."]);
    exit;
}

$permisoModel = new Permiso($db);

// =========================================================================
// Enrutador de Acciones
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'listar') {
        listarMatriz($permisoModel);
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Acción GET no reconocida."]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'actualizar') {
        actualizarPermiso($permisoModel);
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
function listarMatriz($permisoModel) {
    try {
        $matriz = $permisoModel->getMatrizCompleta(['app', 'dashboard']);
        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "data"    => $matriz
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al obtener la matriz de permisos."]);
    }
}

/**
 * Actualiza un permiso individual (rol_id + modulo_id + puede_acceder).
 * Todas las validaciones se realizan aquí en el controlador.
 */
function actualizarPermiso($permisoModel) {
    // Leer y validar parámetros del body POST
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

    $puede_acceder_int = (int)$puede_acceder;

    try {
        $permisoModel->actualizarPermiso((int)$rol_id, (int)$modulo_id, $puede_acceder_int);
        http_response_code(200);
        echo json_encode([
            "status"  => "success",
            "message" => "Permiso actualizado correctamente."
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al actualizar el permiso en la base de datos."]);
    }
}
