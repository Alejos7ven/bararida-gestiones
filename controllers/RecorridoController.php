<?php
/**
 * Controlador de Recorridos y Zonas (RecorridoController)
 * Procesa de forma segura las operaciones de gestión de mapas, zonas y rutas.
 * Responde con estados HTTP óptimos y formato JSON estándar.
 * Acceso de lectura permitido a todo el personal; escritura restringida a 'superadmin' y 'admin'.
 * Respeta el patrón MVC operando mediante los modelos Zona y Recorrido con setters/getters.
 */

// Asegurar que la sesión esté activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar encabezado para responder en formato JSON
header("Content-Type: application/json; charset=UTF-8");

// Incluir dependencias necesarias
require_once __DIR__ . "/../config/Validator.php";
require_once __DIR__ . "/../models/Zona.php";
require_once __DIR__ . "/../models/Recorrido.php";
require_once __DIR__ . "/../models/Ruta.php";
require_once __DIR__ . "/../models/Permiso.php";

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
$userRolId   = isset($currentUser['rol_id']) ? (int)$currentUser['rol_id'] : 0;
$userRol     = $currentUser['rol'] ?? '';

$permisoModel = new Permiso();
if ($userRolId <= 0 && !empty($userRol)) {
    $userRolId = $permisoModel->obtenerRolIdPorNombre($userRol) ?? 0;
    if ($userRolId > 0) {
        $_SESSION['personal']['rol_id'] = $userRolId;
    }
}

// Capturar la acción solicitada
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// =========================================================================
// RUTEADOR DE ACCIONES
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar permiso dinámico de recorridos según la BD
    if (!$permisoModel->tieneAccesoModulo($userRolId, 'recorridos', $userRol)) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Acceso denegado. No posee permisos de edición para este módulo."
        ]);
        exit;
    }

    switch ($action) {
        case 'crear_zona':
            crearZonaAction();
            break;
        case 'eliminar_zona':
            eliminarZonaAction();
            break;
        case 'actualizar_zona_coords':
            actualizarZonaCoordsAction();
            break;
        case 'crear_recorrido':
            crearRecorridoAction();
            break;
        case 'eliminar_recorrido':
            eliminarRecorridoAction();
            break;
        case 'actualizar_recorrido_orden':
            actualizarRecorridoOrdenAction();
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
            listarZonasAction();
            break;
        case 'listar_recorridos':
            listarRecorridosAction();
            break;
        case 'listar_recorridos_simple':
            listarRecorridosSimpleAction();
            break;
        case 'obtener_recorrido':
            obtenerRecorridoAction();
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
function crearZonaAction() {
    $validator = Validator::make($_POST, [
        'nombre'      => ['required' => true, 'minlength' => 3, 'maxlength' => 50],
        'coordenadas' => ['required' => true, 'coordenadas' => true, 'maxlength' => 255]
    ], [
        'nombre' => [
            'required'  => 'El nombre de la zona es obligatorio.',
            'minlength' => 'El nombre de la zona debe tener al menos 3 caracteres.',
            'maxlength' => 'El nombre de la zona no puede exceder los 50 caracteres.'
        ],
        'coordenadas' => [
            'required'    => 'Debe hacer clic en el mapa para marcar las coordenadas de la zona.',
            'coordenadas' => "El formato de las coordenadas no es válido. Debe ser 'X,Y' (ej: 45.2,60.8).",
            'maxlength'   => 'Las coordenadas no pueden superar los 255 caracteres.'
        ]
    ]);

    if ($validator->fails()) {
        $validator->sendJsonResponse(400);
        return;
    }

    $nombre      = $validator->getValue('nombre');
    $coordenadas = $validator->getValue('coordenadas');

    try {
        $zona = new Zona();
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
            "message" => "Error inesperado al crear la zona: " . $e->getMessage()
        ]);
    }
}

/**
 * Elimina una zona del mapa
 */
function eliminarZonaAction() {
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
        $zona = new Zona();
        $zona->setId($id);
        if ($zona->eliminar()) {
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
function crearRecorridoAction() {
    $validator = Validator::make($_POST, [
        'nombre' => ['required' => true, 'minlength' => 3, 'maxlength' => 50]
    ], [
        'nombre' => [
            'required'  => 'El nombre del recorrido es obligatorio.',
            'minlength' => 'El nombre del recorrido debe tener al menos 3 caracteres.',
            'maxlength' => 'El nombre del recorrido no puede exceder los 50 caracteres.'
        ]
    ]);

    if ($validator->fails()) {
        $validator->sendJsonResponse(400);
        return;
    }

    $nombre = $validator->getValue('nombre');
    $zonas  = isset($_POST['zonas']) ? $_POST['zonas'] : null;

    if (empty($zonas) || !is_array($zonas) || count($zonas) < 2) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe seleccionar al menos dos zonas en el mapa para constituir la ruta del recorrido.",
            "field" => "zonas"
        ]);
        return;
    }

    // Sanitizar y validar los IDs de las zonas recibidas en el controlador
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
        $recorrido = new Recorrido();
        $recorrido->setNombre($nombre);
        $recorrido->setZonas($cleanZonas);

        if ($recorrido->crear()) {
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
function eliminarRecorridoAction() {
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
        $recorrido = new Recorrido();
        $recorrido->setId($id);
        if ($recorrido->eliminar()) {
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
function actualizarZonaCoordsAction() {
    $validator = Validator::make($_POST, [
        'id'          => ['required' => true, 'integer' => true, 'min' => 1],
        'coordenadas' => ['required' => true, 'coordenadas' => true, 'maxlength' => 255],
        'nombre'      => ['required' => false, 'minlength' => 3, 'maxlength' => 50]
    ], [
        'id' => [
            'required' => 'El ID de la zona es requerido.',
            'integer'  => 'El ID de la zona es inválido.'
        ],
        'coordenadas' => [
            'required'    => 'Las coordenadas son requeridas.',
            'coordenadas' => "El formato de las coordenadas no es válido. Debe ser 'X,Y' (ej: 45.2,60.8).",
            'maxlength'   => 'Las coordenadas no pueden superar los 255 caracteres.'
        ],
        'nombre' => [
            'minlength' => 'El nombre de la zona debe tener al menos 3 caracteres.',
            'maxlength' => 'El nombre de la zona no puede superar los 50 caracteres.'
        ]
    ]);

    if ($validator->fails()) {
        $validator->sendJsonResponse(400);
        return;
    }

    $id          = (int)$validator->getValue('id');
    $coordenadas = $validator->getValue('coordenadas');
    $nombre      = $validator->getValue('nombre', '');

    try {
        $zona = new Zona();
        $zona->setId($id);
        $zona->setCoordenadas($coordenadas);

        $result = false;
        if (!empty($nombre)) {
            $zona->setNombre($nombre);
            $result = $zona->actualizarNombreYCoordenadas();
        } else {
            $result = $zona->actualizarCoordenadas();
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
function actualizarRecorridoOrdenAction() {
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
        $recorrido = new Recorrido();
        $recorrido->setId($id);
        $recorrido->setZonas($cleanZonas);

        $result = false;
        if (!empty($nombre)) {
            $recorrido->setNombre($nombre);
            $result = $recorrido->actualizarNombreYListadoZonas();
        } else {
            $result = $recorrido->actualizarRuta();
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
function listarZonasAction() {
    try {
        $zona = new Zona();
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
function listarRecorridosAction() {
    try {
        $recorrido = new Recorrido();
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
function obtenerRecorridoAction() {
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
        $recorridoModel = new Recorrido();
        $recorridoModel->setId($id);
        $data = $recorridoModel->obtenerPorId();
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
            "message" => "Ocurrió un error al consultar los detalles del recorrido: " . $e->getMessage()
        ]);
    }
}

/**
 * Obtiene el listado simple (id y nombre) de todos los recorridos
 */
function listarRecorridosSimpleAction() {
    try {
        $recorridoModel = new Recorrido();
        $listado = $recorridoModel->obtenerListadoSimple();
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
