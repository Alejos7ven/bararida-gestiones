<?php
/**
 * Controlador de Visitantes (VisitanteController)
 * Gestiona operaciones CRUD individuales e independientes para visitantes.
 * Acceso restringido exclusivamente a personal autenticado con los permisos adecuados.
 * Respeta el patrón MVC operando mediante el modelo Visitante con setters/getters.
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
require_once __DIR__ . "/../models/Visitante.php";

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Procesar peticiones según método HTTP y acción
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'crear':
            crearVisitanteAction();
            break;
        case 'actualizar':
            actualizarVisitanteAction();
            break;
        case 'eliminar':
            eliminarVisitanteAction();
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
            listarVisitantesAction();
            break;
        case 'obtener':
            obtenerVisitanteAction();
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
function listarVisitantesAction() {
    try {
        $visitanteModel = new Visitante();
        $visitantes = $visitanteModel->listarTodos();
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
function obtenerVisitanteAction() {
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
        $visitanteModel = new Visitante();
        $visitanteModel->setId($id);
        $visitante = $visitanteModel->obtenerPorId();
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
function crearVisitanteAction() {
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

    if (mb_strlen($nombres) < 2 || mb_strlen($nombres) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre del visitante debe tener entre 2 y 100 caracteres.",
            "field" => "nombres"
        ]);
        exit;
    }

    if (preg_match('/\d/', $nombres)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los nombres no pueden contener números.",
            "field" => "nombres"
        ]);
        exit;
    }

    if (!preg_match('/^[\p{L}\s\.\'\-]+$/u', $nombres)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los nombres solo deben contener letras.",
            "field" => "nombres"
        ]);
        exit;
    }

    if (mb_strlen($apellidos) < 2 || mb_strlen($apellidos) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El apellido del visitante debe tener entre 2 y 100 caracteres.",
            "field" => "apellidos"
        ]);
        exit;
    }

    if (preg_match('/\d/', $apellidos)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los apellidos no pueden contener números.",
            "field" => "apellidos"
        ]);
        exit;
    }

    if (!preg_match('/^[\p{L}\s\.\'\-]+$/u', $apellidos)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los apellidos solo deben contener letras.",
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

    $hoy = date('Y-m-d');
    if ($fecha_nacimiento > $hoy) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La fecha de nacimiento no puede ser una fecha futura (máximo hoy: " . date('d/m/Y') . ").",
            "field" => "fecha_nacimiento"
        ]);
        exit;
    }

    if (!empty($cedula)) {
        if (!ctype_digit($cedula)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "La cédula debe contener exclusivamente dígitos numéricos.",
                "field" => "cedula"
            ]);
            exit;
        }

        if (strlen($cedula) < 5 || strlen($cedula) > 12) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "La cédula debe contener entre 5 y 12 dígitos numéricos.",
                "field" => "cedula"
            ]);
            exit;
        }

        $visitanteCheck = new Visitante();
        $visitanteCheck->setCedula($cedula);
        $existente = $visitanteCheck->buscarPorCedula();
        if ($existente) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Ya existe un visitante registrado con la cédula " . $cedula . " (" . $existente['nombres'] . " " . $existente['apellidos'] . ").",
                "field" => "cedula"
            ]);
            exit;
        }
    }

    $cedulaFinal = !empty($cedula) ? $cedula : null;

    try {
        $visitante = new Visitante();
        $visitante->setNombres($nombres);
        $visitante->setApellidos($apellidos);
        $visitante->setCedula($cedulaFinal);
        $visitante->setFechaNacimiento($fecha_nacimiento);

        if ($visitante->registrar()) {
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
            "message" => "Error interno al crear visitante: " . $e->getMessage()
        ]);
    }
}

/**
 * Actualiza los datos de un visitante existente
 */
function actualizarVisitanteAction() {
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

    if (mb_strlen($nombres) < 2 || mb_strlen($nombres) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre del visitante debe tener entre 2 y 100 caracteres.",
            "field" => "nombres"
        ]);
        exit;
    }

    if (preg_match('/\d/', $nombres)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los nombres no pueden contener números.",
            "field" => "nombres"
        ]);
        exit;
    }

    if (!preg_match('/^[\p{L}\s\.\'\-]+$/u', $nombres)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los nombres solo deben contener letras.",
            "field" => "nombres"
        ]);
        exit;
    }

    if (mb_strlen($apellidos) < 2 || mb_strlen($apellidos) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El apellido del visitante debe tener entre 2 y 100 caracteres.",
            "field" => "apellidos"
        ]);
        exit;
    }

    if (preg_match('/\d/', $apellidos)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los apellidos no pueden contener números.",
            "field" => "apellidos"
        ]);
        exit;
    }

    if (!preg_match('/^[\p{L}\s\.\'\-]+$/u', $apellidos)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los apellidos solo deben contener letras.",
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

    $hoy = date('Y-m-d');
    if ($fecha_nacimiento > $hoy) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La fecha de nacimiento no puede ser una fecha futura (máximo hoy: " . date('d/m/Y') . ").",
            "field" => "fecha_nacimiento"
        ]);
        exit;
    }

    if (!empty($cedula)) {
        if (!ctype_digit($cedula)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "La cédula debe contener exclusivamente dígitos numéricos.",
                "field" => "cedula"
            ]);
            exit;
        }

        if (strlen($cedula) < 5 || strlen($cedula) > 12) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "La cédula debe contener entre 5 y 12 dígitos numéricos.",
                "field" => "cedula"
            ]);
            exit;
        }

        $visitanteCheck = new Visitante();
        $visitanteCheck->setCedula($cedula);
        $visitanteCheck->setExcluirId($id);
        $existente = $visitanteCheck->buscarPorCedula();
        if ($existente) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "La cédula " . $cedula . " ya pertenece a otro visitante registrado (" . $existente['nombres'] . " " . $existente['apellidos'] . ").",
                "field" => "cedula"
            ]);
            exit;
        }
    }

    $cedulaFinal = !empty($cedula) ? $cedula : null;

    try {
        $visitante = new Visitante();
        $visitante->setId($id);
        $visitante->setNombres($nombres);
        $visitante->setApellidos($apellidos);
        $visitante->setCedula($cedulaFinal);
        $visitante->setFechaNacimiento($fecha_nacimiento);

        if ($visitante->actualizar()) {
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
function eliminarVisitanteAction() {
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
        $visitante = new Visitante();
        $visitante->setId($id);
        if ($visitante->eliminar()) {
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
