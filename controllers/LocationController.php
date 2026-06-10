<?php
/**
 * Controlador de Localización (LocationController)
 * Retorna estados, ciudades, municipios y parroquias en formato JSON.
 * Acceso exclusivo a personal autenticado.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar encabezado para responder en formato JSON
header("Content-Type: application/json; charset=UTF-8");

// Verificar autenticación
if (!isset($_SESSION['personal'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Acceso no autorizado. Inicie sesión nuevamente."
    ]);
    exit;
}

// Incluir conexión a base de datos
require_once __DIR__ . "/../config/Database.php";

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "No se pudo establecer conexión con la base de datos."
    ]);
    exit;
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {
    case 'estados':
        obtenerEstados($db);
        break;
    case 'municipios':
        obtenerMunicipios($db);
        break;
    case 'parroquias':
        obtenerParroquias($db);
        break;
    case 'ciudades':
        obtenerCiudades($db);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Acción geográfica no válida."
        ]);
        break;
}

/**
 * Retorna todos los estados
 */
function obtenerEstados($db) {
    try {
        $stmt = $db->prepare("SELECT id_estado, estado FROM estados ORDER BY estado ASC");
        $stmt->execute();
        $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $estados
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener estados: " . $e->getMessage()
        ]);
    }
}

/**
 * Retorna los municipios de un estado específico
 */
function obtenerMunicipios($db) {
    $estado_id = isset($_GET['estado_id']) ? filter_var($_GET['estado_id'], FILTER_VALIDATE_INT) : null;

    if (!$estado_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de estado inválido o faltante."
        ]);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id_municipio, municipio FROM municipios WHERE id_estado = :estado_id ORDER BY municipio ASC");
        $stmt->execute([':estado_id' => $estado_id]);
        $municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $municipios
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener municipios: " . $e->getMessage()
        ]);
    }
}

/**
 * Retorna las parroquias de un municipio específico
 */
function obtenerParroquias($db) {
    $municipio_id = isset($_GET['municipio_id']) ? filter_var($_GET['municipio_id'], FILTER_VALIDATE_INT) : null;

    if (!$municipio_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de municipio inválido o faltante."
        ]);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id_parroquia, parroquia FROM parroquias WHERE id_municipio = :municipio_id ORDER BY parroquia ASC");
        $stmt->execute([':municipio_id' => $municipio_id]);
        $parroquias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $parroquias
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener parroquias: " . $e->getMessage()
        ]);
    }
}

/**
 * Retorna las ciudades de un estado específico
 */
function obtenerCiudades($db) {
    $estado_id = isset($_GET['estado_id']) ? filter_var($_GET['estado_id'], FILTER_VALIDATE_INT) : null;

    if (!$estado_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de estado inválido o faltante."
        ]);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id_ciudad, ciudad FROM ciudades WHERE id_estado = :estado_id ORDER BY ciudad ASC");
        $stmt->execute([':estado_id' => $estado_id]);
        $ciudades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $ciudades
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener ciudades: " . $e->getMessage()
        ]);
    }
}
