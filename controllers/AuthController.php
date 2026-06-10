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
require_once __DIR__ . "/../models/Personal.php";

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
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'V';
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : null;
    $password = isset($_POST['password']) ? trim($_POST['password']) : null;

    // Validación independiente de campos
    $errors = [];

    if (empty($tipo_documento)) {
        $errors[] = "El tipo de documento es requerido.";
    } elseif (!in_array($tipo_documento, ['V', 'E', 'J', 'G', 'P'], true)) {
        $errors[] = "El tipo de documento seleccionado no es válido (debe ser V, E, J, G o P).";
    }

    if (empty($cedula)) {
        $errors[] = "El número de documento es requerido.";
    } elseif (!ctype_digit($cedula)) {
        $errors[] = "El número de documento debe contener solo dígitos.";
    }

    if (empty($password)) {
        $errors[] = "La contraseña es requerida.";
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => implode(" ", $errors)
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
        // Instanciamos el modelo de Personal
        $personal = new Personal($db);

        // Intentamos el login
        if ($personal->login($tipo_documento, $cedula, $password)) {
            // Guardamos la información del personal en la sesión para RBAC
            $_SESSION['personal'] = [
                "id" => $personal->getId(),
                "tipo_documento" => $personal->getTipoDocumento(),
                "cedula" => $personal->getCedula(),
                "nombre" => $personal->getNombre(),
                "apellido" => $personal->getApellido(),
                "rol" => $personal->getRol()
            ];

            // Retornamos respuesta exitosa
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Inicio de sesión exitoso.",
                "user" => $_SESSION['personal']
            ]);
        } else {
            // Credenciales incorrectas con mensaje detallado
            $errorMsg = $personal->getLoginError();
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
