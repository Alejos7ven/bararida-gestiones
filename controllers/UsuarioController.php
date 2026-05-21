<?php
/**
 * Controlador de Usuarios (UsuarioController)
 * Procesa de forma segura las operaciones CRUD de usuarios, respondiendo con estados HTTP y JSON.
 * Acceso restringido exclusivamente a usuarios con rol 'superadmin'.
 */

// Aseguramos que la sesión esté activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Habilitar respuestas en formato JSON
header("Content-Type: application/json; charset=UTF-8");

// Incluimos las dependencias necesarias
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../models/Usuario.php";

// =========================================================================
// SEGURIDAD Y CONTROL DE ACCESO ESTRICTO (RBAC)
// =========================================================================

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Sesión no activa. Inicie sesión nuevamente."
    ]);
    exit;
}

// Verificar que el usuario posea el rol de Super Administrador
if ($_SESSION['usuario']['rol'] !== 'superadmin') {
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
 * Obtiene y retorna el listado de todos los usuarios
 */
function listarAction($db) {
    try {
        $usuarioModel = new Usuario($db);
        $usuarios = $usuarioModel->listarTodos();
        
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $usuarios
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener el listado de usuarios: " . $e->getMessage()
        ]);
    }
}

/**
 * Obtiene y retorna los detalles de un usuario por su ID
 */
function obtenerAction($db) {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de usuario inválido o no suministrado."
        ]);
        return;
    }

    try {
        $userData = Usuario::obtenerPorId($db, $id);
        
        if ($userData) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data" => $userData
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Usuario no encontrado."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Ocurrió un error al consultar el usuario."
        ]);
    }
}

/**
 * Registra un nuevo usuario en el sistema
 */
function crearAction($db) {
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $apellido = isset($_POST['apellido']) ? trim($_POST['apellido']) : '';
    $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validar campos requeridos
    if (empty($cedula) || empty($nombre) || empty($apellido) || empty($rol) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los campos son obligatorios para el registro."
        ]);
        return;
    }

    // Estricta validación del rol: sólo se pueden crear admins y guias
    if ($rol === 'superadmin' || !in_array($rol, ['admin', 'guia'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Rol no permitido. Sólo se pueden crear usuarios de tipo Administrador o Guía."
        ]);
        return;
    }

    try {
        // Inicializar entidad y aplicar lógica de validación del modelo
        $usuario = new Usuario($db);
        $usuario->setCedula($cedula);
        $usuario->setNombre($nombre);
        $usuario->setApellido($apellido);
        $usuario->setRol($rol);
        $usuario->setPlainPassword($password); // Valida que tenga al menos 6 caracteres

        if ($usuario->crear()) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Usuario registrado correctamente en el sistema."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar el usuario por un fallo en la base de datos."
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
            "message" => "Ocurrió un error inesperado al registrar el usuario."
        ]);
    }
}

/**
 * Modifica un usuario existente
 */
function actualizarAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $apellido = isset($_POST['apellido']) ? trim($_POST['apellido']) : '';
    $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : ''; // Opcional

    if (!$id || empty($cedula) || empty($nombre) || empty($apellido) || empty($rol)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los campos ID, cédula, nombre, apellido y rol son requeridos."
        ]);
        return;
    }

    try {
        // Cargar información actual en la BD para verificar políticas de roles
        $usuarioActual = Usuario::obtenerPorId($db, $id);
        if (!$usuarioActual) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "El usuario a modificar no existe."
            ]);
            return;
        }

        // Restricción: Impedir promover a superadmin a cuentas existentes
        if ($usuarioActual['rol'] !== 'superadmin' && $rol === 'superadmin') {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "No está permitido promover usuarios al rol de Super Administrador."
            ]);
            return;
        }

        // Restricción: Impedir degradar a un superadmin a admin o guia
        if ($usuarioActual['rol'] === 'superadmin' && $rol !== 'superadmin') {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "No está permitido degradar o cambiar el rol de un Super Administrador."
            ]);
            return;
        }

        // Instanciar modelo para guardar cambios
        $usuario = new Usuario($db);
        $usuario->setId($id);
        $usuario->setCedula($cedula);
        $usuario->setNombre($nombre);
        $usuario->setApellido($apellido);
        $usuario->setRol($rol);

        // Si se suministró una nueva contraseña, se valida y hashea
        if (!empty($password)) {
            $usuario->setPlainPassword($password);
        }

        if ($usuario->actualizar()) {
            // Si el propio superadmin se editó a sí mismo, actualizamos los datos en su sesión activa
            if ($_SESSION['usuario']['id'] == $id) {
                $_SESSION['usuario']['cedula'] = $usuario->getCedula();
                $_SESSION['usuario']['nombre'] = $usuario->getNombre();
                $_SESSION['usuario']['apellido'] = $usuario->getApellido();
            }

            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Usuario actualizado con éxito."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo actualizar la información del usuario."
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
            "message" => "Ocurrió un error inesperado al actualizar el usuario."
        ]);
    }
}

/**
 * Elimina un usuario por ID (Previene eliminar superadmins)
 */
function eliminarAction($db) {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de usuario no suministrado o inválido."
        ]);
        return;
    }

    try {
        $usuario = new Usuario($db);
        
        if ($usuario->eliminar($id)) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Usuario eliminado del sistema correctamente."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo eliminar el usuario de la base de datos."
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
            "message" => "Ocurrió un error inesperado al intentar eliminar el usuario."
        ]);
    }
}
