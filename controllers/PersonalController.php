<?php
/**
 * Controlador de Personal (PersonalController)
 * Procesa de forma segura las operaciones CRUD de personal, respondiendo con estados HTTP y JSON.
 * Acceso restringido exclusivamente a personal con rol 'superadmin'.
 * Respeta el patrón MVC delegando la gestión de base de datos y SQL al modelo Personal.
 */

// Aseguramos que la sesión esté activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Habilitar respuestas en formato JSON
header("Content-Type: application/json; charset=UTF-8");

// Incluimos las dependencias necesarias
require_once __DIR__ . "/../models/Personal.php";

// =========================================================================
// SEGURIDAD Y CONTROL DE ACCESO ESTRICTO (RBAC)
// =========================================================================

// Verificar que el personal esté autenticado
if (!isset($_SESSION['personal'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Sesión no activa. Inicie sesión nuevamente."
    ]);
    exit;
}

// Verificar que el personal posea el rol de Super Administrador
if ($_SESSION['personal']['rol'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Acceso denegado. Se requieren permisos de Super Administrador para este módulo."
    ]);
    exit;
}

// Capturar la acción del request
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Procesar según método HTTP y acción solicitada
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'crear':
            crearAction();
            break;
        case 'actualizar':
            actualizarAction();
            break;
        case 'eliminar':
            eliminarAction();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción POST no válida."
            ]);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'listar':
            listarAction();
            break;
        case 'listar_roles':
            listarRolesAction();
            break;
        case 'obtener':
            obtenerAction();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción GET no válida."
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
 * Obtiene y retorna el listado de todo el personal
 */
function listarAction() {
    try {
        $personalModel = new Personal();
        $listaPersonal = $personalModel->listarTodos();
        
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $listaPersonal
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener el listado de personal: " . $e->getMessage()
        ]);
    }
}

/**
 * Obtiene y retorna los detalles de un personal por su ID
 */
function obtenerAction() {
    $id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
    
    if ($id === null || $id < 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de personal inválido o no suministrado."
        ]);
        return;
    }

    try {
        $personalModel = new Personal();
        $personalModel->setId($id);
        $personalData = $personalModel->obtenerPorId();
        
        if ($personalData) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data" => $personalData
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Personal no encontrado."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Ocurrió un error al consultar el personal: " . $e->getMessage()
        ]);
    }
}

/**
 * Registra un nuevo personal en el sistema
 */
function crearAction() {
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'V';
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $nombre_completo = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : '';
    if (empty($nombre_completo) && isset($_POST['nombre'])) {
        $nombre_completo = trim($_POST['nombre'] . ' ' . ($_POST['apellido'] ?? ''));
    }
    $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validar campos requeridos en el controlador
    if (empty($tipo_documento) || empty($cedula) || empty($nombre_completo) || empty($rol) || empty($password)) {
        $missingField = empty($tipo_documento) ? "tipo_documento" : (empty($cedula) ? "cedula" : (empty($nombre_completo) ? "nombre_completo" : (empty($rol) ? "rol" : "password")));
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los campos son obligatorios para el registro (Tipo, Documento, Nombre Completo, Rol y Contraseña).",
            "field" => $missingField
        ]);
        return;
    }

    // Validar formato de tipo de documento
    $validTypes = ['V', 'E', 'J', 'G', 'P'];
    if (!in_array($tipo_documento, $validTypes, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Tipo de documento no válido. Valores permitidos: V, E, J, G, P.",
            "field" => "tipo_documento"
        ]);
        return;
    }

    // Validar cédula numérica
    if (!ctype_digit($cedula)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El número de documento debe contener exclusivamente dígitos.",
            "field" => "cedula"
        ]);
        return;
    }

    if (strlen($cedula) < 6 || strlen($cedula) > 10) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El documento debe contener entre 6 y 10 dígitos.",
            "field" => "cedula"
        ]);
        return;
    }

    // Validar longitud y formato de nombre_completo
    if (mb_strlen($nombre_completo) < 3 || mb_strlen($nombre_completo) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre completo debe tener entre 3 y 100 caracteres.",
            "field" => "nombre_completo"
        ]);
        return;
    }

    if (preg_match('/\d/', $nombre_completo)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre no puede contener números.",
            "field" => "nombre_completo"
        ]);
        return;
    }

    // Validar contraseña
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La contraseña debe tener al menos 6 caracteres.",
            "field" => "password"
        ]);
        return;
    }

    // Obtener roles válidos desde el modelo Personal
    $personalModel = new Personal();
    $validRoles = $personalModel->obtenerRolesList();
    if (empty($validRoles)) {
        $validRoles = ['admin', 'guia'];
    }

    if ($rol === 'superadmin' || !in_array($rol, $validRoles, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Rol no permitido. Roles válidos: " . implode(', ', array_map(function($r) {
                if ($r === 'admin') return 'Administrador';
                if ($r === 'guia') return 'Guía de Campo';
                return ucfirst($r);
            }, $validRoles)),
            "field" => "rol"
        ]);
        return;
    }

    // Validar unicidad del documento en la BD mediante el modelo
    $personalModel->setTipoDocumento($tipo_documento);
    $personalModel->setCedula($cedula);
    if ($personalModel->existeDocumento()) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El documento {$tipo_documento}-{$cedula} ya se encuentra registrado en el sistema.",
            "field" => "cedula"
        ]);
        return;
    }

    try {
        $personal = new Personal();
        $personal->setTipoDocumento($tipo_documento);
        $personal->setCedula($cedula);
        $personal->setNombreCompleto($nombre_completo);
        $personal->setRol($rol);
        $personal->setPlainPassword($password);

        if ($personal->crear()) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Personal registrado en el sistema exitosamente.",
                "data" => [
                    "id" => $personal->getId()
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar al personal en la base de datos."
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
            "message" => "Ocurrió un error inesperado al registrar el personal: " . $e->getMessage()
        ]);
    }
}

/**
 * Actualiza la información de un personal existente
 */
function actualizarAction() {
    $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'V';
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $nombre_completo = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : '';
    if (empty($nombre_completo) && isset($_POST['nombre'])) {
        $nombre_completo = trim($_POST['nombre'] . ' ' . ($_POST['apellido'] ?? ''));
    }
    $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($id === null || $id < 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de personal inválido para la actualización."
        ]);
        return;
    }

    // Validar campos obligatorios
    if (empty($tipo_documento) || empty($cedula) || empty($nombre_completo) || empty($rol)) {
        $missingField = empty($tipo_documento) ? "tipo_documento" : (empty($cedula) ? "cedula" : (empty($nombre_completo) ? "nombre_completo" : "rol"));
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los campos Tipo, Documento, Nombre Completo y Rol son obligatorios.",
            "field" => $missingField
        ]);
        return;
    }

    $validTypes = ['V', 'E', 'J', 'G', 'P'];
    if (!in_array($tipo_documento, $validTypes, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Tipo de documento no válido.",
            "field" => "tipo_documento"
        ]);
        return;
    }

    if (!ctype_digit($cedula)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El número de documento debe contener exclusivamente dígitos.",
            "field" => "cedula"
        ]);
        return;
    }

    if (strlen($cedula) < 6 || strlen($cedula) > 10) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El documento debe contener entre 6 y 10 dígitos.",
            "field" => "cedula"
        ]);
        return;
    }

    if (mb_strlen($nombre_completo) < 3 || mb_strlen($nombre_completo) > 100) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre completo debe tener entre 3 y 100 caracteres.",
            "field" => "nombre_completo"
        ]);
        return;
    }

    if (preg_match('/\d/', $nombre_completo)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre no puede contener números.",
            "field" => "nombre_completo"
        ]);
        return;
    }

    if (!empty($password) && strlen($password) < 6) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La contraseña debe tener al menos 6 caracteres.",
            "field" => "password"
        ]);
        return;
    }

    // Verificar si el personal actual existe
    $personalModel = new Personal();
    $personalModel->setId($id);
    $personalActual = $personalModel->obtenerPorId();
    if (!$personalActual) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "El registro de personal que intenta actualizar no existe."
        ]);
        return;
    }

    // Proteger el rol de superadmin de degradaciones accidentales
    if ($personalActual['rol'] === 'superadmin' && $rol !== 'superadmin') {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El rol de un Super Administrador no puede ser degradado.",
            "field" => "rol"
        ]);
        return;
    }

    // Si no es superadmin, validar el rol contra la lista de roles permitidos desde el modelo
    if ($personalActual['rol'] !== 'superadmin') {
        $validRoles = $personalModel->obtenerRolesList();
        if (empty($validRoles)) {
            $validRoles = ['admin', 'guia'];
        }

        if ($rol === 'superadmin' || !in_array($rol, $validRoles, true)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Rol no permitido. Roles válidos: " . implode(', ', array_map(function($r) {
                    if ($r === 'admin') return 'Administrador';
                    if ($r === 'guia') return 'Guía de Campo';
                    return ucfirst($r);
                }, $validRoles)),
                "field" => "rol"
            ]);
            return;
        }
    }

    // Validar unicidad del documento excluyendo el ID actual mediante el modelo
    $personalModel->setTipoDocumento($tipo_documento);
    $personalModel->setCedula($cedula);
    $personalModel->setExcluirId($id);
    if ($personalModel->existeDocumento()) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El documento {$tipo_documento}-{$cedula} ya se encuentra registrado por otro usuario.",
            "field" => "cedula"
        ]);
        return;
    }

    try {
        $personal = new Personal();
        $personal->setId($id);
        $personal->setTipoDocumento($tipo_documento);
        $personal->setCedula($cedula);
        $personal->setNombreCompleto($nombre_completo);
        $personal->setRol($rol);
        if (!empty($password)) {
            $personal->setPlainPassword($password);
        }

        if ($personal->actualizar()) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Información del personal actualizada exitosamente."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo actualizar la información del personal."
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
            "message" => "Ocurrió un error inesperado al actualizar el personal: " . $e->getMessage()
        ]);
    }
}

/**
 * Elimina un personal por ID (Previene eliminar superadmins)
 */
function eliminarAction() {
    $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;

    if ($id === null || $id < 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de personal no suministrado o inválido."
        ]);
        return;
    }

    try {
        $personalModel = new Personal();
        $personalModel->setId($id);
        $personalActual = $personalModel->obtenerPorId();
        if (!$personalActual) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "El personal a eliminar no existe."
            ]);
            return;
        }

        if ($personalActual['rol'] === 'superadmin') {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Un Super Administrador no puede ser eliminado del sistema."
            ]);
            return;
        }

        if ($personalModel->eliminar()) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Personal eliminado del sistema correctamente."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo eliminar el personal de la base de datos."
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
            "message" => "Ocurrió un error inesperado al intentar eliminar el personal: " . $e->getMessage()
        ]);
    }
}

/**
 * Obtiene y retorna los roles del personal
 */
function listarRolesAction() {
    try {
        $personalModel = new Personal();
        $roles = $personalModel->obtenerRolesList();
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $roles
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener el listado de roles: " . $e->getMessage()
        ]);
    }
}
