<?php
/**
 * Controlador de Localización (LocationController)
 * Retorna estados, ciudades, municipios y parroquias en formato JSON.
 * Acceso exclusivo a personal autenticado.
 * Respeta el patrón MVC delegando las consultas SQL al modelo Location.
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

// Incluir modelo de localización
require_once __DIR__ . "/../models/Location.php";

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {
    case 'estados':
        obtenerEstadosAction();
        break;
    case 'municipios':
        obtenerMunicipiosAction();
        break;
    case 'parroquias':
        obtenerParroquiasAction();
        break;
    case 'ciudades':
        obtenerCiudadesAction();
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
function obtenerEstadosAction() {
    try {
        $locationModel = new Location();
        $estados = $locationModel->obtenerEstados();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $estados
        ]);
    } catch (Exception $e) {
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
function obtenerMunicipiosAction() {
    $estado_id = isset($_GET['estado_id']) ? filter_var($_GET['estado_id'], FILTER_VALIDATE_INT) : null;

    if (!$estado_id || $estado_id <= 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de estado inválido o faltante."
        ]);
        return;
    }

    try {
        $locationModel = new Location();
        $locationModel->setEstadoId((int)$estado_id);
        $municipios = $locationModel->obtenerMunicipios();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $municipios
        ]);
    } catch (Exception $e) {
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
function obtenerParroquiasAction() {
    $municipio_id = isset($_GET['municipio_id']) ? filter_var($_GET['municipio_id'], FILTER_VALIDATE_INT) : null;

    if (!$municipio_id || $municipio_id <= 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de municipio inválido o faltante."
        ]);
        return;
    }

    try {
        $locationModel = new Location();
        $locationModel->setMunicipioId((int)$municipio_id);
        $parroquias = $locationModel->obtenerParroquias();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $parroquias
        ]);
    } catch (Exception $e) {
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
function obtenerCiudadesAction() {
    $estado_id = isset($_GET['estado_id']) ? filter_var($_GET['estado_id'], FILTER_VALIDATE_INT) : null;

    if (!$estado_id || $estado_id <= 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de estado inválido o faltante."
        ]);
        return;
    }

    try {
        $locationModel = new Location();
        $locationModel->setEstadoId((int)$estado_id);
        $ciudades = $locationModel->obtenerCiudades();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $ciudades
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener ciudades: " . $e->getMessage()
        ]);
    }
}
