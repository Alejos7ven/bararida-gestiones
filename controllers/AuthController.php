<?php
/**
 * Controlador de Autenticación (AuthController)
 * Gestiona el login, logout y validación de sesiones, respondiendo con estados HTTP y JSON.
 */

// Aseguramos que la sesión esté activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Habilitar reporte de errores adecuado
header("Content-Type: application/json; charset=UTF-8");

// Incluimos las dependencias necesarias
// En una aplicación real, se podría utilizar un autoloader, pero aquí incluimos de forma directa por portabilidad
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../models/Usuario.php";

// Obtenemos la acción del request
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        loginAction();
    } else {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Acción no válida."
        ]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'logout') {
        logoutAction();
    } else {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Método HTTP no soportado para esta acción."
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Método no permitido."
    ]);
}

/**
 * Procesa la acción de inicio de sesión
 */
function loginAction() {
    // Obtenemos los parámetros enviados vía POST (o desde el body JSON si es el caso)
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : null;
    $password = isset($_POST['password']) ? trim($_POST['password']) : null;

    // Validación básica inicial de campos vacíos
    if (empty($cedula) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La cédula y la contraseña son campos requeridos."
        ]);
        return;
    }

    // Inicializamos la conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "No se pudo establecer conexión con la base de datos."
        ]);
        return;
    }

    try {
        // Instanciamos el modelo de Usuario
        $usuario = new Usuario($db);

        // Intentamos el login
        if ($usuario->login($cedula, $password)) {
            // Guardamos la información del usuario en la sesión para RBAC
            $_SESSION['usuario'] = [
                "id" => $usuario->getId(),
                "cedula" => $usuario->getCedula(),
                "nombre" => $usuario->getNombre(),
                "apellido" => $usuario->getApellido(),
                "rol" => $usuario->getRol()
            ];

            // Retornamos respuesta exitosa
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Inicio de sesión exitoso.",
                "user" => $_SESSION['usuario']
            ]);
        } else {
            // Credenciales incorrectas con mensaje detallado
            $errorMsg = $usuario->getLoginError();
            if (empty($errorMsg)) {
                $errorMsg = "Cédula o contraseña incorrectas.";
            }
            http_response_code(401);
            echo json_encode([
                "status" => "error",
                "message" => $errorMsg
            ]);
        }
    } catch (InvalidArgumentException $e) {
        // Errores de validación de datos
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    } catch (Exception $e) {
        // Errores imprevistos
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Ocurrió un error inesperado al procesar la solicitud."
        ]);
    }
}

/**
 * Procesa la acción de cierre de sesión
 */
function logoutAction() {
    // Destruimos la sesión
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    // Redirección simple al login o retorno JSON
    if (isset($_GET['ajax']) && $_GET['ajax'] == 'true') {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Sesión cerrada correctamente."
        ]);
    } else {
        header("Location: ../index.php");
        exit;
    }
}
