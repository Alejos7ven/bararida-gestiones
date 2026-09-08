<?php
/**
 * Controlador de Recorridos y Zonas (RecorridoController)
 * Procesa de forma segura las operaciones de gestión de mapas, zonas y rutas.
 * Responde con estados HTTP óptimos y formato JSON estándar.
 * Acceso de lectura permitido a todo el personal; escritura restringida a 'superadmin' y 'admin'.
 */

// Asegurar que la sesión esté activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar encabezado para responder en formato JSON
header("Content-Type: application/json; charset=UTF-8");

// Incluir dependencias necesarias
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../models/Zona.php";
require_once __DIR__ . "/../models/Recorrido.php";
require_once __DIR__ . "/../models/Ruta.php";

// =========================================================================
// SEGURIDAD Y CONTROL DE ACCESO (RBAC)
// =========================================================================

// 1. Verificar que el personal esté autenticado
if (!isset($_SESSION['personal'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Sesión no activa. Por favor, inicie sesión nuevamente."
    ]);
    exit;
}

$currentUser = $_SESSION['personal'];
$userRol = $currentUser['rol'];

// Inicializar conexión a la base de datos
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

// Capturar la acción solicitada
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// =========================================================================
// RUTEADOR DE ACCIONES
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Las operaciones de escritura están estrictamente reservadas para superadmin y admin
    if ($userRol !== 'superadmin' && $userRol !== 'admin') {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Acceso denegado. No posee permisos de edición para este módulo."
        ]);
        exit;
    }

    switch ($action) {
        case 'crear_zona':
            crearZonaAction($db);
            break;
        case 'eliminar_zona':
            eliminarZonaAction($db);
            break;
        case 'actualizar_zona_coords':
            actualizarZonaCoordsAction($db);
            break;
        case 'crear_recorrido':
            crearRecorridoAction($db);
            break;
        case 'eliminar_recorrido':
            eliminarRecorridoAction($db);
            break;
        case 'actualizar_recorrido_orden':
            actualizarRecorridoOrdenAction($db);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción de escritura POST no válida."
            ]);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Todo el personal autenticado (incluidos guías) puede leer la información
    switch ($action) {
        case 'listar_zonas':
            listarZonasAction($db);
            break;
        case 'listar_recorridos':
            listarRecorridosAction($db);
            break;
        case 'listar_recorridos_simple':
            listarRecorridosSimpleAction($db);
            break;
        case 'obtener_recorrido':
            obtenerRecorridoAction($db);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción de lectura GET no válida."
            ]);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Método HTTP no permitido."
    ]);
}

// =========================================================================
// ACCIONES DE ESCRITURA (ADMINISTRATIVAS)
// =========================================================================

/**
 * Crea una nueva zona en el mapa
 */
function crearZonaAction($db) {
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $coordenadas = isset($_POST['coordenadas']) ? trim($_POST['coordenadas']) : '';

    if (empty($nombre) || empty($coordenadas)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre de la zona y sus coordenadas son requeridos.",
            "field" => empty($nombre) ? "nombre" : "coordenadas"
        ]);
        return;
    }

    if (strlen($nombre) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre de la zona supera los 100 caracteres.",
            "field" => "nombre"
        ]);
        return;
    }

    if (!preg_match('/^[0-9]+(\.[0-9]+)?,[0-9]+(\.[0-9]+)?$/', $coordenadas)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El formato de las coordenadas no es válido. Debe ser 'X,Y' (ej: 45.2,60.8).",
            "field" => "coordenadas"
        ]);
        return;
    }

    try {
        $zona = new Zona($db);
        $zona->setNombre($nombre);
        $zona->setCoordenadas($coordenadas);

        if ($zona->crear()) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Zona registrada correctamente con coordenadas: " . $coordenadas,
                "data" => [
                    "id" => $zona->getId(),
                    "nombre" => $zona->getNombre(),
                    "coordenadas" => $zona->getCoordenadas()
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Error al guardar el pin de la zona en la base de datos."
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
            "message" => "Error inesperado al crear la zona."
        ]);
    }
}

/**
 * Elimina una zona del mapa
 */
function eliminarZonaAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de zona no suministrado o inválido."
        ]);
        return;
    }

    try {
        $zona = new Zona($db);
        if ($zona->eliminar($id)) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Zona eliminada correctamente del mapa."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo eliminar el registro de la base de datos."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al eliminar la zona seleccionada: " . $e->getMessage()
        ]);
    }
}

/**
 * Registra un recorrido y sus paradas en orden usando transacciones seguras
 */
function crearRecorridoAction($db) {
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $zonas = isset($_POST['zonas']) ? $_POST['zonas'] : null;

    if (empty($nombre)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre del recorrido es obligatorio.",
            "field" => "nombre"
        ]);
        return;
    }

    if (strlen($nombre) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre del recorrido no es válido o supera los 100 caracteres.",
            "field" => "nombre"
        ]);
        return;
    }

    if (empty($zonas) || !is_array($zonas) || count($zonas) < 2) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe seleccionar al menos dos zonas en el mapa para constituir la ruta del recorrido.",
            "field" => "zonas"
        ]);
        return;
    }

    // Sanitizar y validar los IDs de las zonas recibidas
    $cleanZonas = [];
    foreach ($zonas as $zId) {
        $cleanId = filter_var($zId, FILTER_VALIDATE_INT);
        if ($cleanId !== false) {
            $cleanZonas[] = (int)$cleanId;
        }
    }

    if (count($cleanZonas) < 2) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe seleccionar al menos dos zonas válidas en el mapa para constituir la ruta del recorrido.",
            "field" => "zonas"
        ]);
        return;
    }

    try {
        $recorrido = new Recorrido($db);
        $recorrido->setNombre($nombre);

        if ($recorrido->crear($cleanZonas)) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Recorrido y ruta creados correctamente.",
                "data" => [
                    "id" => $recorrido->getId(),
                    "nombre" => $recorrido->getNombre()
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Error al almacenar el recorrido."
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
            "message" => "Excepción durante la creación atómica del recorrido: " . $e->getMessage()
        ]);
    }
}

/**
 * Elimina un recorrido y sus paradas en cascada
 */
function eliminarRecorridoAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de recorrido no suministrado o inválido."
        ]);
        return;
    }

    try {
        $recorrido = new Recorrido($db);
        if ($recorrido->eliminar($id)) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Recorrido y su ruta de paradas eliminados correctamente."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo eliminar el recorrido de la base de datos."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al eliminar el recorrido: " . $e->getMessage()
        ]);
    }
}

/**
 * Actualiza las coordenadas y/o nombre de una zona
 */
function actualizarZonaCoordsAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    $coordenadas = isset($_POST['coordenadas']) ? trim($_POST['coordenadas']) : '';
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';

    if (!$id || empty($coordenadas)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de la zona y las coordenadas son requeridos."
        ]);
        return;
    }

    if (!empty($nombre) && strlen($nombre) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre de la zona supera los 100 caracteres."
        ]);
        return;
    }

    if (!preg_match('/^[0-9]+(\.[0-9]+)?,[0-9]+(\.[0-9]+)?$/', $coordenadas)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El formato de las coordenadas no es válido. Debe ser 'X,Y' (ej: 45.2,60.8)."
        ]);
        return;
    }

    try {
        $zona = new Zona($db);
        $result = false;
        if (!empty($nombre)) {
            $result = $zona->actualizarNombreYCoordenadas($id, $nombre, $coordenadas);
        } else {
            $result = $zona->actualizarCoordenadas($id, $coordenadas);
        }

        if ($result) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Zona actualizada con éxito."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Error interno al guardar los cambios en la base de datos."
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
            "message" => "Error inesperado al actualizar la zona: " . $e->getMessage()
        ]);
    }
}

/**
 * Actualiza el orden secuencial de paradas y/o el nombre de un recorrido
 */
function actualizarRecorridoOrdenAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    $zonas = isset($_POST['zonas']) ? $_POST['zonas'] : null;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID del recorrido es requerido."
        ]);
        return;
    }

    if (!empty($nombre) && strlen($nombre) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre del recorrido no es válido o supera los 100 caracteres."
        ]);
        return;
    }

    if (empty($zonas) || !is_array($zonas) || count($zonas) < 2) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El recorrido debe incluir al menos dos zonas en su ruta."
        ]);
        return;
    }

    $cleanZonas = [];
    foreach ($zonas as $zId) {
        $cleanId = filter_var($zId, FILTER_VALIDATE_INT);
        if ($cleanId !== false) {
            $cleanZonas[] = (int)$cleanId;
        }
    }

    if (count($cleanZonas) < 2) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El recorrido debe incluir al menos dos zonas válidas en su ruta."
        ]);
        return;
    }

    try {
        $recorrido = new Recorrido($db);
        $result = false;
        if (!empty($nombre)) {
            $result = $recorrido->actualizarNombreYListadoZonas($id, $nombre, $cleanZonas);
        } else {
            $result = $recorrido->actualizarRuta($id, $cleanZonas);
        }

        if ($result) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "El recorrido ha sido actualizado correctamente."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Error interno al actualizar el recorrido."
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
            "message" => "Error inesperado al reordenar el recorrido: " . $e->getMessage()
        ]);
    }
}

// =========================================================================
// ACCIONES DE LECTURA (PARA TODOS LOS ROLES)
// =========================================================================

/**
 * Obtiene el listado completo de zonas
 */
function listarZonasAction($db) {
    try {
        $zona = new Zona($db);
        $listado = $zona->listarTodas();
        
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $listado
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al listar las zonas del parque: " . $e->getMessage()
        ]);
    }
}

/**
 * Obtiene el listado completo de recorridos con sus rutas de zonas ordenadas
 */
function listarRecorridosAction($db) {
    try {
        $recorrido = new Recorrido($db);
        $listado = $recorrido->listarTodos();
        
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $listado
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener el listado de recorridos: " . $e->getMessage()
        ]);
    }
}

/**
 * Obtiene los detalles de un recorrido y sus paradas
 */
function obtenerRecorridoAction($db) {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de recorrido inválido o no suministrado."
        ]);
        return;
    }

    try {
        $data = Recorrido::obtenerPorId($db, $id);
        if ($data) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data" => $data
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Recorrido no encontrado."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Ocurrió un error al consultar los detalles del recorrido."
        ]);
    }
}

/**
 * Obtiene el listado simple (id y nombre) de todos los recorridos
 */
function listarRecorridosSimpleAction($db) {
    try {
        $listado = Recorrido::obtenerListadoSimple($db);
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $listado
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener el listado simple de recorridos: " . $e->getMessage()
        ]);
    }
}

