<?php
/**
 * Controlador de Instituciones (InstitucionController)
 * Gestiona el listado, creación, edición y eliminación de instituciones externas.
 * Acceso restringido a personal autenticado.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

// Verificar autenticación
if (!isset($_SESSION['personal'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Sesión no activa. Por favor inicie sesión de nuevo."
    ]);
    exit;
}

// Incluir dependencias
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../models/Institucion.php";

// Inicializar conexión
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'crear':
            crearInstitucionAction($db);
            break;
        case 'actualizar':
            actualizarInstitucionAction($db);
            break;
        case 'eliminar':
            eliminarInstitucionAction($db);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción POST no válida para Instituciones."
            ]);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'listar':
            listarInstitucionesAction($db);
            break;
        case 'obtener':
            obtenerInstitucionAction($db);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción GET no válida para Instituciones."
            ]);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Método HTTP no soportado."
    ]);
}

// =========================================================================
// ACCIONES DEL CONTROLADOR
// =========================================================================

function listarInstitucionesAction($db) {
    try {
        $instituciones = Institucion::listarTodas($db);
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $instituciones
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener instituciones: " . $e->getMessage()
        ]);
    }
}

function obtenerInstitucionAction($db) {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de la institución inválido o no suministrado."
        ]);
        return;
    }

    $institucion = Institucion::obtenerPorId($db, $id);
    if ($institucion) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $institucion
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "La institución indicada no existe en el sistema."
        ]);
    }
}

function crearInstitucionAction($db) {
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'J';
    $documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $tipo_organizacion = isset($_POST['tipo_organizacion']) ? trim($_POST['tipo_organizacion']) : '';
    $ciudad_id = isset($_POST['ciudad_id']) ? $_POST['ciudad_id'] : '';
    $parroquia_id = isset($_POST['parroquia_id']) ? $_POST['parroquia_id'] : '';
    $detalles_direccion = isset($_POST['detalles_direccion']) ? trim($_POST['detalles_direccion']) : '';
    $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

    if (empty($documento) || empty($nombre) || empty($tipo_organizacion) || empty($ciudad_id) || empty($parroquia_id)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los campos de la institución son obligatorios (RIF, Razón Social, Tipo, Ciudad y Parroquia)."
        ]);
        return;
    }

    try {
        $instModel = new Institucion($db);
        $instModel->setTipoDocumento($tipo_documento);
        $instModel->setDocumento($documento);
        $instModel->setNombre($nombre);
        $instModel->setTipoOrganizacion($tipo_organizacion);
        $instModel->setCiudadId($ciudad_id);
        $instModel->setParroquiaId($parroquia_id);
        $instModel->setDetallesDireccion($detalles_direccion);

        $contactos = [];
        if (is_array($contactosRaw)) {
            foreach ($contactosRaw as $c) {
                if (!empty(trim($c['contacto'] ?? ''))) {
                    $contactos[] = [
                        'contacto' => trim($c['contacto']),
                        'tipo' => trim($c['tipo'] ?? 'principal')
                    ];
                }
            }
        }
        $instModel->setContactos($contactos);

        if ($instModel->crear()) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Institución registrada con éxito en el sistema.",
                "id" => $instModel->getId()
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar la institución en la base de datos."
            ]);
        }
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno del servidor: " . $e->getMessage()
        ]);
    }
}

function actualizarInstitucionAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    $entidad_id = isset($_POST['entidad_id']) ? filter_var($_POST['entidad_id'], FILTER_VALIDATE_INT) : null;
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'J';
    $documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $tipo_organizacion = isset($_POST['tipo_organizacion']) ? trim($_POST['tipo_organizacion']) : '';
    $ciudad_id = isset($_POST['ciudad_id']) ? $_POST['ciudad_id'] : '';
    $parroquia_id = isset($_POST['parroquia_id']) ? $_POST['parroquia_id'] : '';
    $detalles_direccion = isset($_POST['detalles_direccion']) ? trim($_POST['detalles_direccion']) : '';
    $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

    if (!$id || !$entidad_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Se requiere el ID y el Entidad ID para actualizar la institución."
        ]);
        return;
    }

    if (empty($documento) || empty($nombre) || empty($tipo_organizacion) || empty($ciudad_id) || empty($parroquia_id)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los campos de la institución son obligatorios (RIF, Razón Social, Tipo, Ciudad y Parroquia)."
        ]);
        return;
    }

    try {
        $instModel = new Institucion($db);
        $instModel->setId($id);
        $instModel->setEntidadId($entidad_id);
        $instModel->setTipoDocumento($tipo_documento);
        $instModel->setDocumento($documento);
        $instModel->setNombre($nombre);
        $instModel->setTipoOrganizacion($tipo_organizacion);
        $instModel->setCiudadId($ciudad_id);
        $instModel->setParroquiaId($parroquia_id);
        $instModel->setDetallesDireccion($detalles_direccion);

        $contactos = [];
        if (is_array($contactosRaw)) {
            foreach ($contactosRaw as $c) {
                if (!empty(trim($c['contacto'] ?? ''))) {
                    $contactos[] = [
                        'contacto' => trim($c['contacto']),
                        'tipo' => trim($c['tipo'] ?? 'principal')
                    ];
                }
            }
        }
        $instModel->setContactos($contactos);

        if ($instModel->actualizar()) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Información de la institución actualizada con éxito."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo actualizar la institución en la base de datos."
            ]);
        }
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno del servidor: " . $e->getMessage()
        ]);
    }
}

function eliminarInstitucionAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de institución inválido o no suministrado."
        ]);
        return;
    }

    try {
        $instModel = new Institucion($db);
        if ($instModel->eliminar($id)) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Institución eliminada correctamente del sistema."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo eliminar la institución de la base de datos."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
}
