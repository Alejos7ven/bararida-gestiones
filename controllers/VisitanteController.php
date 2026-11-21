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
require_once __DIR__ . "/../config/Validator.php";
require_once __DIR__ . "/../models/Visitante.php";
require_once __DIR__ . "/../models/Permiso.php";

$currentUser = $_SESSION['personal'];
$userRolId   = isset($currentUser['rol_id']) ? (int)$currentUser['rol_id'] : 0;
$userRol     = $currentUser['rol'] ?? '';

$permisoModel = new Permiso();
if ($userRolId <= 0 && !empty($userRol)) {
    $permisoModel->setRolNombre($userRol);
    $userRolId = $permisoModel->obtenerRolIdPorNombre() ?? 0;
    if ($userRolId > 0) {
        $_SESSION['personal']['rol_id'] = $userRolId;
    }
}

if (!$permisoModel->tieneAccesoModulo($userRolId, 'visitantes', $userRol)) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Acceso denegado. No posee permisos para acceder al módulo de visitantes."
    ]);
    exit;
}

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
 * Lista todos los visitantes en el sistema (Soporta Server-Side Processing de DataTables)
 */
function listarVisitantesAction() {
    try {
        $visitanteModel = new Visitante();

        if (isset($_GET['draw'])) {
            $draw = (int)$_GET['draw'];
            $start = isset($_GET['start']) && is_numeric($_GET['start']) ? max(0, (int)$_GET['start']) : 0;
            $length = isset($_GET['length']) && is_numeric($_GET['length']) ? (int)$_GET['length'] : 10;
            $search = isset($_GET['search']['value']) ? trim((string)$_GET['search']['value']) : '';

            $columnsMap = [
                0 => 'v.id',
                1 => 'v.cedula',
                2 => 'v.nombres',
                3 => 'v.apellidos',
                4 => 'v.fecha_nacimiento',
                5 => 'e.nombre_completo'
            ];

            $orderColIdx = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0;
            $orderColumn = $columnsMap[$orderColIdx] ?? 'v.id';
            $orderDirRaw = isset($_GET['order'][0]['dir']) ? strtoupper(trim((string)$_GET['order'][0]['dir'])) : 'DESC';
            $orderDir = ($orderDirRaw === 'ASC') ? 'ASC' : 'DESC';

            $visitanteModel->setDtStart($start);
            $visitanteModel->setDtLength($length);
            $visitanteModel->setDtSearch($search);
            $visitanteModel->setDtOrderColumn($orderColumn);
            $visitanteModel->setDtOrderDir($orderDir);

            $resultado = $visitanteModel->listarPaginado();

            http_response_code(200);
            echo json_encode([
                "status"          => "success",
                "draw"            => $draw,
                "recordsTotal"    => $resultado['recordsTotal'],
                "recordsFiltered" => $resultado['recordsFiltered'],
                "data"            => $resultado['data']
            ]);
            return;
        }

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
    $validator = Validator::make($_POST, [
        'nombres'          => ['required' => true, 'minlength' => 2, 'maxlength' => 25, 'soloLetras' => true],
        'apellidos'        => ['required' => true, 'minlength' => 2, 'maxlength' => 25, 'soloLetras' => true],
        'cedula'           => ['required' => false, 'digits' => true, 'minlength' => 5, 'maxlength' => 15],
        'fecha_nacimiento' => ['required' => true, 'date' => true, 'fechaNoFutura' => true]
    ], [
        'nombres' => [
            'required'   => 'Los nombres son obligatorios.',
            'minlength'  => 'Los nombres deben tener al menos 2 caracteres.',
            'maxlength'  => 'Los nombres no pueden exceder los 25 caracteres.',
            'soloLetras' => 'Los nombres solo deben contener letras y no pueden iniciar con espacios ni estar vacíos.'
        ],
        'apellidos' => [
            'required'   => 'Los apellidos son obligatorios.',
            'minlength'  => 'Los apellidos deben tener al menos 2 caracteres.',
            'maxlength'  => 'Los apellidos no pueden exceder los 25 caracteres.',
            'soloLetras' => 'Los apellidos solo deben contener letras y no pueden iniciar con espacios ni estar vacíos.'
        ],
        'cedula' => [
            'digits'    => 'La cédula debe contener exclusivamente dígitos numéricos.',
            'minlength' => 'La cédula debe contener entre 5 y 15 dígitos numéricos.',
            'maxlength' => 'La cédula no puede exceder los 15 dígitos numéricos.'
        ],
        'fecha_nacimiento' => [
            'required'      => 'La fecha de nacimiento es obligatoria.',
            'date'          => 'La fecha de nacimiento debe tener el formato AAAA-MM-DD.',
            'fechaNoFutura' => 'La fecha de nacimiento no puede ser una fecha futura (máximo hoy: ' . date('d/m/Y') . ').'
        ]
    ]);

    if ($validator->fails()) {
        $validator->sendJsonResponse(400);
        return;
    }

    $nombres          = $validator->getValue('nombres');
    $apellidos        = $validator->getValue('apellidos');
    $cedula           = $validator->getValue('cedula');
    $fecha_nacimiento = $validator->getValue('fecha_nacimiento');

    if (!empty($cedula)) {
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
        error_log("Error en crearVisitanteAction: " . $e->getMessage());
        $isDuplicate = (stripos($e->getMessage(), 'Duplicate entry') !== false || stripos($e->getMessage(), '23000') !== false || stripos($e->getMessage(), '1062') !== false);
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $isDuplicate 
                ? "La cédula ingresada ya se encuentra registrada para otro visitante en el sistema." 
                : "No fue posible registrar al visitante debido a un inconveniente en el servidor.",
            "field" => $isDuplicate ? "cedula" : null
        ]);
    }
}

/**
 * Actualiza los datos de un visitante existente
 */
function actualizarVisitanteAction() {
    $validator = Validator::make($_POST, [
        'id'               => ['required' => true, 'integer' => true, 'min' => 1],
        'nombres'          => ['required' => true, 'minlength' => 2, 'maxlength' => 25, 'soloLetras' => true],
        'apellidos'        => ['required' => true, 'minlength' => 2, 'maxlength' => 25, 'soloLetras' => true],
        'cedula'           => ['required' => false, 'digits' => true, 'minlength' => 5, 'maxlength' => 15],
        'fecha_nacimiento' => ['required' => true, 'date' => true, 'fechaNoFutura' => true]
    ], [
        'id' => [
            'required' => 'El ID del visitante es obligatorio.',
            'integer'  => 'El ID del visitante es inválido.'
        ],
        'nombres' => [
            'required'   => 'Los nombres son obligatorios.',
            'minlength'  => 'Los nombres deben tener al menos 2 caracteres.',
            'maxlength'  => 'Los nombres no pueden exceder los 25 caracteres.',
            'soloLetras' => 'Los nombres solo deben contener letras y no pueden iniciar con espacios ni estar vacíos.'
        ],
        'apellidos' => [
            'required'   => 'Los apellidos son obligatorios.',
            'minlength'  => 'Los apellidos deben tener al menos 2 caracteres.',
            'maxlength'  => 'Los apellidos no pueden exceder los 25 caracteres.',
            'soloLetras' => 'Los apellidos solo deben contener letras y no pueden iniciar con espacios ni estar vacíos.'
        ],
        'cedula' => [
            'digits'    => 'La cédula debe contener exclusivamente dígitos numéricos.',
            'minlength' => 'La cédula debe contener entre 5 y 15 dígitos numéricos.',
            'maxlength' => 'La cédula no puede exceder los 15 dígitos numéricos.'
        ],
        'fecha_nacimiento' => [
            'required'      => 'La fecha de nacimiento es obligatoria.',
            'date'          => 'La fecha de nacimiento debe tener el formato AAAA-MM-DD.',
            'fechaNoFutura' => 'La fecha de nacimiento no puede ser una fecha futura (máximo hoy: ' . date('d/m/Y') . ').'
        ]
    ]);

    if ($validator->fails()) {
        $validator->sendJsonResponse(400);
        return;
    }

    $id               = (int)$validator->getValue('id');
    $nombres          = $validator->getValue('nombres');
    $apellidos        = $validator->getValue('apellidos');
    $cedula           = $validator->getValue('cedula');
    $fecha_nacimiento = $validator->getValue('fecha_nacimiento');

    if (!empty($cedula)) {
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
        error_log("Error en actualizarVisitanteAction: " . $e->getMessage());
        $isDuplicate = (stripos($e->getMessage(), 'Duplicate entry') !== false || stripos($e->getMessage(), '23000') !== false || stripos($e->getMessage(), '1062') !== false);
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $isDuplicate 
                ? "La cédula ingresada ya se encuentra registrada para otro visitante en el sistema." 
                : "No fue posible actualizar los datos del visitante debido a un inconveniente en el servidor.",
            "field" => $isDuplicate ? "cedula" : null
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
