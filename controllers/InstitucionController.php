<?php
/**
 * Controlador de Instituciones (InstitucionController)
 * Gestiona el listado, creación, edición, sedes y eliminación de instituciones externas.
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
require_once __DIR__ . "/../models/Sede.php";

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
        case 'agregar_sede':
            agregarSedeAction($db);
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
        case 'obtener_sedes':
            obtenerSedesAction($db);
            break;
        case 'buscar_autocomplete':
            buscarAutocompleteAction($db);
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

function obtenerSedesAction($db) {
    $institucion_id = isset($_GET['institucion_id']) ? filter_var($_GET['institucion_id'], FILTER_VALIDATE_INT) : (isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null);
    if (!$institucion_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de institución inválido."
        ]);
        return;
    }

    $sedes = Sede::listarPorInstitucion($db, $institucion_id);
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data" => $sedes
    ]);
}

function crearInstitucionAction($db) {
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'J';
    $documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';
    $nombre_completo = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : (isset($_POST['nombre']) ? trim($_POST['nombre']) : '');
    $tipo_organizacion = isset($_POST['tipo_organizacion']) ? trim($_POST['tipo_organizacion']) : '';
    $tipo_gestion = isset($_POST['tipo_gestion']) ? trim($_POST['tipo_gestion']) : 'publica';
    $sedesRaw = isset($_POST['sedes']) ? $_POST['sedes'] : [];
    $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

    // Fallback: si no viene el array de sedes pero vienen ciudad_id y parroquia_id directos
    if (empty($sedesRaw) && !empty($_POST['ciudad_id']) && !empty($_POST['parroquia_id'])) {
        $sedesRaw[] = [
            'ciudad_id' => $_POST['ciudad_id'],
            'parroquia_id' => $_POST['parroquia_id'],
            'detalles' => $_POST['detalles_direccion'] ?? ''
        ];
    }

    // Validaciones en Controller
    if (empty($documento) || empty($nombre_completo) || empty($tipo_organizacion) || empty($tipo_gestion)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los datos identificativos de la institución son obligatorios (Tipo, RIF, Razón Social, Tipo de Org. y Gestión)."
        ]);
        return;
    }

    if (!in_array($tipo_documento, ['J', 'G', 'V', 'E', 'P'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Tipo de documento/RIF no válido."
        ]);
        return;
    }

    if (strlen($documento) < 3 || strlen($documento) > 50) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El número de documento/RIF no es válido."
        ]);
        return;
    }

    if (strlen($nombre_completo) < 3 || strlen($nombre_completo) > 150) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La razón social debe tener entre 3 y 150 caracteres."
        ]);
        return;
    }

    if (!in_array($tipo_gestion, ['publica', 'privada'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El tipo de gestión proporcionado no es válido."
        ]);
        return;
    }

    // Validar sedes
    $sedesValidadas = [];
    if (is_array($sedesRaw)) {
        foreach ($sedesRaw as $idx => $s) {
            $cId = filter_var($s['ciudad_id'] ?? 0, FILTER_VALIDATE_INT);
            $pId = filter_var($s['parroquia_id'] ?? 0, FILTER_VALIDATE_INT);
            $det = strip_tags(trim($s['detalles'] ?? ''));

            if ($cId && $pId) {
                $sedesValidadas[] = [
                    'ciudad_id' => $cId,
                    'parroquia_id' => $pId,
                    'detalles' => substr($det, 0, 150)
                ];
            }
        }
    }

    if (empty($sedesValidadas)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe agregar al menos una sede válida con su respectivo Estado, Municipio, Parroquia y Ciudad."
        ]);
        return;
    }

    // Validar contactos
    $contactos = [];
    if (is_array($contactosRaw)) {
        foreach ($contactosRaw as $c) {
            $contVal = strip_tags(trim($c['contacto'] ?? ''));
            if (!empty($contVal)) {
                $contactos[] = [
                    'contacto' => substr($contVal, 0, 50),
                    'tipo' => trim($c['tipo'] ?? 'principal')
                ];
            }
        }
    }

    if (empty($contactos)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe ingresar al menos un número de contacto válido."
        ]);
        return;
    }

    try {
        $instModel = new Institucion($db);
        $instModel->setTipoDocumento($tipo_documento);
        $instModel->setDocumento($documento);
        $instModel->setNombreCompleto($nombre_completo);
        $instModel->setTipoOrganizacion($tipo_organizacion);
        $instModel->setTipoGestion($tipo_gestion);
        $instModel->setSedes($sedesValidadas);
        $instModel->setContactos($contactos);

        if ($instModel->crear()) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Institución registrada con éxito en el sistema.",
                "id" => $instModel->getId(),
                "institucion_id" => $instModel->getId()
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
    $nombre_completo = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : (isset($_POST['nombre']) ? trim($_POST['nombre']) : '');
    $tipo_organizacion = isset($_POST['tipo_organizacion']) ? trim($_POST['tipo_organizacion']) : '';
    $tipo_gestion = isset($_POST['tipo_gestion']) ? trim($_POST['tipo_gestion']) : 'publica';
    $sedesRaw = isset($_POST['sedes']) ? $_POST['sedes'] : [];
    $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

    if (!$id || !$entidad_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Se requiere el ID y el Entidad ID para actualizar la institución."
        ]);
        return;
    }

    if (empty($documento) || empty($nombre_completo) || empty($tipo_organizacion) || empty($tipo_gestion)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los campos de la institución son obligatorios."
        ]);
        return;
    }

    if (!in_array($tipo_documento, ['J', 'G', 'V', 'E', 'P'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Tipo de documento inválido para la institución."
        ]);
        return;
    }

    if (strlen($documento) < 3 || strlen($documento) > 50) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El número de documento/RIF no es válido."
        ]);
        return;
    }

    if (strlen($nombre_completo) < 3 || strlen($nombre_completo) > 150) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La razón social debe tener entre 3 y 150 caracteres."
        ]);
        return;
    }

    // Fallback de sedes si vienen campos directos
    if (empty($sedesRaw) && !empty($_POST['ciudad_id']) && !empty($_POST['parroquia_id'])) {
        $sedesRaw[] = [
            'id' => $_POST['sede_id'] ?? null,
            'ciudad_id' => $_POST['ciudad_id'],
            'parroquia_id' => $_POST['parroquia_id'],
            'detalles' => $_POST['detalles_direccion'] ?? ''
        ];
    }

    $sedesValidadas = [];
    if (is_array($sedesRaw)) {
        foreach ($sedesRaw as $s) {
            $cId = filter_var($s['ciudad_id'] ?? 0, FILTER_VALIDATE_INT);
            $pId = filter_var($s['parroquia_id'] ?? 0, FILTER_VALIDATE_INT);
            $det = strip_tags(trim($s['detalles'] ?? ''));
            $sId = !empty($s['id']) ? filter_var($s['id'], FILTER_VALIDATE_INT) : null;

            if ($cId && $pId) {
                $sedesValidadas[] = [
                    'id' => $sId,
                    'ciudad_id' => $cId,
                    'parroquia_id' => $pId,
                    'detalles' => substr($det, 0, 150)
                ];
            }
        }
    }

    if (empty($sedesValidadas)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe especificar al menos una sede válida para la institución."
        ]);
        return;
    }

    $contactos = [];
    if (is_array($contactosRaw)) {
        foreach ($contactosRaw as $c) {
            $contVal = strip_tags(trim($c['contacto'] ?? ''));
            if (!empty($contVal)) {
                $contactos[] = [
                    'contacto' => substr($contVal, 0, 50),
                    'tipo' => trim($c['tipo'] ?? 'principal')
                ];
            }
        }
    }

    if (empty($contactos)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe ingresar al menos un número de contacto válido."
        ]);
        return;
    }

    try {
        $instModel = new Institucion($db);
        $instModel->setId($id);
        $instModel->setEntidadId($entidad_id);
        $instModel->setTipoDocumento($tipo_documento);
        $instModel->setDocumento($documento);
        $instModel->setNombreCompleto($nombre_completo);
        $instModel->setTipoOrganizacion($tipo_organizacion);
        $instModel->setTipoGestion($tipo_gestion);
        $instModel->setSedes($sedesValidadas);
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

/**
 * Agrega una nueva sede a una institución existente
 */
function agregarSedeAction($db) {
    $institucion_id = isset($_POST['institucion_id']) ? filter_var($_POST['institucion_id'], FILTER_VALIDATE_INT) : null;
    $ciudad_id = isset($_POST['ciudad_id']) ? filter_var($_POST['ciudad_id'], FILTER_VALIDATE_INT) : null;
    $parroquia_id = isset($_POST['parroquia_id']) ? filter_var($_POST['parroquia_id'], FILTER_VALIDATE_INT) : null;
    $detalles = isset($_POST['detalles']) ? strip_tags(trim($_POST['detalles'])) : (isset($_POST['detalles_direccion']) ? strip_tags(trim($_POST['detalles_direccion'])) : '');

    if (!$institucion_id || !$ciudad_id || !$parroquia_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Institución, Estado, Municipio, Parroquia y Ciudad son obligatorios para registrar la sede."
        ]);
        return;
    }

    try {
        $sedeModel = new Sede($db);
        $sedeModel->setInstitucionId($institucion_id);
        $sedeModel->setCiudadId($ciudad_id);
        $sedeModel->setParroquiaId($parroquia_id);
        $sedeModel->setDetalles(substr($detalles, 0, 150));

        if ($sedeModel->crear()) {
            $sedeCompleta = Sede::obtenerPorId($db, $sedeModel->getId());
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Sede registrada con éxito.",
                "data" => $sedeCompleta
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar la nueva sede."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al agregar sede: " . $e->getMessage()
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

/**
 * Busca instituciones para sugerencias de autocompletado en tiempo real
 */
function buscarAutocompleteAction($db) {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (strlen($q) < 2) {
        echo json_encode([
            "status" => "success",
            "data" => []
        ]);
        return;
    }

    try {
        $resultados = Institucion::buscarPorTermino($db, $q);
        echo json_encode([
            "status" => "success",
            "data" => $resultados
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al buscar instituciones: " . $e->getMessage()
        ]);
    }
}
