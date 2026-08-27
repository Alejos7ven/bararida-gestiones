<?php
/**
 * Controlador de Personal (PersonalController)
 * Procesa de forma segura las operaciones CRUD de personal, respondiendo con estados HTTP y JSON.
 * Acceso restringido exclusivamente a personal con rol 'superadmin'.
 */

// Aseguramos que la sesión esté activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Habilitar respuestas en formato JSON
header("Content-Type: application/json; charset=UTF-8");

// Incluimos las dependencias necesarias
require_once __DIR__ . "/../config/Database.php";
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

// Capturar la acción del request
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Procesar según método HTTP y acción solicitada
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'crear':
            crearAction($db);
            break;
        case 'actualizar':
            actualizarAction($db);
            break;
        case 'eliminar':
            eliminarAction($db);
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
            listarAction($db);
            break;
        case 'listar_roles':
            listarRolesAction($db);
            break;
        case 'obtener':
            obtenerAction($db);
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
function listarAction($db) {
    try {
        $personalModel = new Personal($db);
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
function obtenerAction($db) {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de personal inválido o no suministrado."
        ]);
        return;
    }

    try {
        $personalData = Personal::obtenerPorId($db, $id);
        
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
            "message" => "Ocurrió un error al consultar el personal."
        ]);
    }
}

/**
 * Registra un nuevo personal en el sistema
 */
function crearAction($db) {
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'V';
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $nombre_completo = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : '';
    if (empty($nombre_completo) && isset($_POST['nombre'])) {
        $nombre_completo = trim($_POST['nombre'] . ' ' . ($_POST['apellido'] ?? ''));
    }
    $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validar campos requeridos
    if (empty($tipo_documento) || empty($cedula) || empty($nombre_completo) || empty($rol) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los campos son obligatorios para el registro (Tipo, Documento, Nombre Completo, Rol y Contraseña)."
        ]);
        return;
    }

    // Validar formato de tipo de documento
    $validTypes = ['V', 'E', 'J', 'G', 'P'];
    if (!in_array($tipo_documento, $validTypes, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El tipo de documento proporcionado no es válido."
        ]);
        return;
    }

    // Validar formato de cédula
    $cleanCedula = preg_replace('/[^0-9]/', '', $cedula);
    if (strlen($cleanCedula) < 6 || strlen($cleanCedula) > 15) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La cédula debe contener solo números y tener entre 6 y 15 dígitos."
        ]);
        return;
    }

    // Validar longitud de nombre_completo
    $cleanNombre = strip_tags(trim($nombre_completo));
    if (empty($cleanNombre) || strlen($cleanNombre) < 3 || strlen($cleanNombre) > 150) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre completo debe tener entre 3 y 150 caracteres."
        ]);
        return;
    }

    // Validar longitud de contraseña
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La contraseña debe tener al menos 6 caracteres."
        ]);
        return;
    }

    // Obtener roles válidos desde la base de datos (excluyendo superadmin)
    $validRoles = [];
    try {
        $stmtRoles = $db->prepare("SELECT nombre FROM roles WHERE nombre != 'superadmin'");
        $stmtRoles->execute();
        $validRoles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error al cargar roles válidos en PersonalController: " . $e->getMessage());
    }
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
            }, $validRoles))
        ]);
        return;
    }

    try {
        $personal = new Personal($db);
        $personal->setTipoDocumento($tipo_documento);
        $personal->setCedula($cleanCedula);
        $personal->setNombreCompleto($cleanNombre);
        $personal->setRol($rol);
        $personal->setPlainPassword($password);

        if ($personal->crear()) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Personal registrado correctamente en el sistema."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar el personal por un fallo en la base de datos."
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
 * Modifica un personal existente
 */
function actualizarAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'V';
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $nombre_completo = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : '';
    if (empty($nombre_completo) && isset($_POST['nombre'])) {
        $nombre_completo = trim($_POST['nombre'] . ' ' . ($_POST['apellido'] ?? ''));
    }
    $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (!$id || empty($tipo_documento) || empty($cedula) || empty($nombre_completo) || empty($rol)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los campos ID, tipo de documento, documento, nombre completo y rol son requeridos."
        ]);
        return;
    }

    $validTypes = ['V', 'E', 'J', 'G', 'P'];
    if (!in_array($tipo_documento, $validTypes, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El tipo de documento proporcionado no es válido."
        ]);
        return;
    }

    $cleanCedula = preg_replace('/[^0-9]/', '', $cedula);
    if (strlen($cleanCedula) < 6 || strlen($cleanCedula) > 15) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La cédula debe contener solo números y tener entre 6 y 15 dígitos."
        ]);
        return;
    }

    $cleanNombre = strip_tags(trim($nombre_completo));
    if (empty($cleanNombre) || strlen($cleanNombre) < 3 || strlen($cleanNombre) > 150) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El nombre completo debe tener entre 3 y 150 caracteres."
        ]);
        return;
    }

    if (!empty($password) && strlen($password) < 6) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La contraseña debe tener al menos 6 caracteres."
        ]);
        return;
    }

    try {
        $personalActual = Personal::obtenerPorId($db, $id);
        if (!$personalActual) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "El personal a modificar no existe."
            ]);
            return;
        }

        if ($personalActual['rol'] !== 'superadmin' && $rol === 'superadmin') {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "No está permitido promover personal al rol de Super Administrador."
            ]);
            return;
        }

        if ($personalActual['rol'] === 'superadmin' && $rol !== 'superadmin') {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "No está permitido degradar o cambiar el rol de un Super Administrador."
            ]);
            return;
        }

        $validRoles = [];
        try {
            $stmtRoles = $db->prepare("SELECT nombre FROM roles WHERE nombre != 'superadmin'");
            $stmtRoles->execute();
            $validRoles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error al cargar roles válidos en PersonalController: " . $e->getMessage());
        }
        if (empty($validRoles)) {
            $validRoles = ['admin', 'guia'];
        }

        if ($rol !== 'superadmin' && !in_array($rol, $validRoles, true)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "El rol especificado no es válido."
            ]);
            return;
        }

        $personal = new Personal($db);
        $personal->setId($id);
        $personal->setTipoDocumento($tipo_documento);
        $personal->setCedula($cleanCedula);
        $personal->setNombreCompleto($cleanNombre);
        $personal->setRol($rol);

        if (!empty($password)) {
            $personal->setPlainPassword($password);
        }

        if ($personal->actualizar()) {
            if ($_SESSION['personal']['id'] == $id) {
                $_SESSION['personal']['tipo_documento'] = $personal->getTipoDocumento();
                $_SESSION['personal']['cedula'] = $personal->getCedula();
                $_SESSION['personal']['nombre_completo'] = $personal->getNombreCompleto();
                $_SESSION['personal']['nombre'] = $personal->getNombreCompleto();
            }

            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Personal actualizado con éxito."
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
function eliminarAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de personal no suministrado o inválido."
        ]);
        return;
    }

    try {
        $personalActual = Personal::obtenerPorId($db, $id);
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

        $personal = new Personal($db);
        if ($personal->eliminar($id)) {
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
            "message" => "Ocurrió un error inesperado al intentar eliminar el personal."
        ]);
    }
}

/**
 * Obtiene y retorna los roles del personal
 */
function listarRolesAction($db) {
    try {
        $roles = Personal::obtenerRolesList($db);
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
