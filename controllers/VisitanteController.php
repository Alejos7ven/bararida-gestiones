<?php
/**
 * Controlador de Visitantes (VisitanteController)
 * Gestiona operaciones CRUD individuales e independientes para visitantes.
 * Acceso restringido exclusivamente a personal autenticado con los permisos adecuados.
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
        "message" => "Sesión no activa. Por favor inicie sesión nuevamente."
    ]);
    exit;
}

// Incluir dependencias
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../models/Visitante.php";

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Inicializar base de datos
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

// Procesar peticiones según método HTTP y acción
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'crear':
            crearVisitanteAction($db);
            break;
        case 'actualizar':
            actualizarVisitanteAction($db);
            break;
        case 'eliminar':
            eliminarVisitanteAction($db);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción POST no válida para Visitantes."
            ]);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'listar':
            listarVisitantesAction($db);
            break;
        case 'obtener':
            obtenerVisitanteAction($db);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción GET no válida para Visitantes."
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
// ACCIONES DEL CONTROLADOR
// =========================================================================

/**
 * Lista todos los visitantes en el sistema
 */
function listarVisitantesAction($db) {
    try {
        $visitantes = Visitante::listarTodos($db);
        echo json_encode([
            "status" => "success",
            "data" => $visitantes
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al listar visitantes: " . $e->getMessage()
        ]);
    }
}

/**
 * Obtiene los detalles de un visitante específico
 */
function obtenerVisitanteAction($db) {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de visitante es inválido."
        ]);
        exit;
    }

    try {
        $visitante = Visitante::obtenerPorId($db, $id);
        if ($visitante) {
            echo json_encode([
                "status" => "success",
                "data" => $visitante
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Visitante no encontrado."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener visitante: " . $e->getMessage()
        ]);
    }
}

/**
 * Crea un nuevo visitante manualmente
 */
function crearVisitanteAction($db) {
    $nombres = isset($_POST['nombres']) ? trim($_POST['nombres']) : '';
    $apellidos = isset($_POST['apellidos']) ? trim($_POST['apellidos']) : '';
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';

    if (empty($nombres) || empty($apellidos) || empty($fecha_nacimiento)) {
        $missingField = empty($nombres) ? "nombres" : (empty($apellidos) ? "apellidos" : "fecha_nacimiento");
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los nombres, apellidos y fecha de nacimiento son obligatorios.",
            "field" => $missingField
        ]);
        exit;
    }

    if (strlen($nombres) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre del visitante no es válido o supera los 100 caracteres.",
            "field" => "nombres"
        ]);
        exit;
    }

    if (strlen($apellidos) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El apellido del visitante no es válido o supera los 100 caracteres.",
            "field" => "apellidos"
        ]);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La fecha de nacimiento debe tener el formato AAAA-MM-DD.",
            "field" => "fecha_nacimiento"
        ]);
        exit;
    }

    $rawCedula = preg_replace('/[^0-9]/', '', $cedula);
    if (!empty($rawCedula)) {
        if (strlen($rawCedula) < 6 || strlen($rawCedula) > 12) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "La cédula debe contener entre 6 y 12 dígitos numéricos.",
                "field" => "cedula"
            ]);
            exit;
        }

        $existente = Visitante::buscarPorCedula($db, $rawCedula);
        if ($existente) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Ya existe un visitante registrado con la cédula " . $rawCedula . " (" . $existente['nombres'] . " " . $existente['apellidos'] . ").",
                "field" => "cedula"
            ]);
            exit;
        }
    }

    $cedulaFinal = !empty($rawCedula) ? $rawCedula : null;

    try {
        $visitante = new Visitante($db);
        $visitante->setNombres($nombres);
        $visitante->setApellidos($apellidos);
        $visitante->setCedula($cedulaFinal);
        $visitante->setFechaNacimiento($fecha_nacimiento);

        if ($visitante->registrar($db)) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Visitante registrado exitosamente.",
                "data" => [
                    "id" => $visitante->getId()
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar al visitante."
            ]);
        }
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        $field = (stripos($e->getMessage(), 'cédula') !== false || stripos($e->getMessage(), 'cedula') !== false) ? 'cedula' : null;
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage(),
            "field" => $field
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno al crear el visitante: " . $e->getMessage()
        ]);
    }
}

/**
 * Actualiza los datos de un visitante existente
 */
function actualizarVisitanteAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : 0;
    $nombres = isset($_POST['nombres']) ? trim($_POST['nombres']) : '';
    $apellidos = isset($_POST['apellidos']) ? trim($_POST['apellidos']) : '';
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';

    if ($id <= 0 || empty($nombres) || empty($apellidos) || empty($fecha_nacimiento)) {
        $missingField = empty($nombres) ? "nombres" : (empty($apellidos) ? "apellidos" : "fecha_nacimiento");
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los campos obligatorios (*) son requeridos.",
            "field" => $missingField
        ]);
        exit;
    }

    if (strlen($nombres) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre del visitante no es válido o supera los 100 caracteres.",
            "field" => "nombres"
        ]);
        exit;
    }

    if (strlen($apellidos) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El apellido del visitante no es válido o supera los 100 caracteres.",
            "field" => "apellidos"
        ]);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La fecha de nacimiento debe tener el formato AAAA-MM-DD.",
            "field" => "fecha_nacimiento"
        ]);
        exit;
    }

    $rawCedula = preg_replace('/[^0-9]/', '', $cedula);
    if (!empty($rawCedula)) {
        if (strlen($rawCedula) < 6 || strlen($rawCedula) > 12) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "La cédula debe contener entre 6 y 12 dígitos numéricos.",
                "field" => "cedula"
            ]);
            exit;
        }

        $existente = Visitante::buscarPorCedula($db, $rawCedula, $id);
        if ($existente) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "La cédula " . $rawCedula . " ya pertenece a otro visitante registrado (" . $existente['nombres'] . " " . $existente['apellidos'] . ").",
                "field" => "cedula"
            ]);
            exit;
        }
    }

    $cedulaFinal = !empty($rawCedula) ? $rawCedula : null;

    try {
        $visitante = new Visitante($db);
        $visitante->setId($id);
        $visitante->setNombres($nombres);
        $visitante->setApellidos($apellidos);
        $visitante->setCedula($cedulaFinal);
        $visitante->setFechaNacimiento($fecha_nacimiento);

        if ($visitante->actualizar($db)) {
            echo json_encode([
                "status" => "success",
                "message" => "Datos del visitante actualizados correctamente."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudieron actualizar los datos del visitante."
            ]);
        }
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        $field = (stripos($e->getMessage(), 'cédula') !== false || stripos($e->getMessage(), 'cedula') !== false) ? 'cedula' : null;
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage(),
            "field" => $field
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno al actualizar visitante: " . $e->getMessage()
        ]);
    }
}

/**
 * Elimina un visitante del sistema
 */
function eliminarVisitanteAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de visitante inválido para eliminación."
        ]);
        exit;
    }

    try {
        if (Visitante::eliminar($db, $id)) {
            echo json_encode([
                "status" => "success",
                "message" => "Visitante eliminado correctamente del sistema."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo completar la eliminación del visitante."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno al eliminar visitante: " . $e->getMessage()
        ]);
    }
}
