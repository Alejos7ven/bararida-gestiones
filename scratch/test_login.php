<?php
/**
 * Script de Pruebas Unitarias/Integración para el Modelo Usuario
 * Ejecuta validaciones estrictas y comprueba el método de login usando SQLite en memoria.
 */

// Requerimos el archivo del Modelo Usuario
require_once __DIR__ . "/../models/Usuario.php";

echo "=================================================================\n";
echo " INICIANDO PRUEBAS UNITARIAS E INTEGRACIÓN - MODELO USUARIO\n";
echo "=================================================================\n\n";

$failedTests = 0;
$passedTests = 0;

/**
 * Función auxiliar para reportar resultados de tests
 */
function assertTest($description, $assertion) {
    global $passedTests, $failedTests;
    if ($assertion) {
        echo "✅ [PASÓ] - $description\n";
        $passedTests++;
    } else {
        echo "❌ [FALLÓ] - $description\n";
        $failedTests++;
    }
}

// =========================================================================
// TEST 1: Validaciones de Getters y Setters (Sanitización y Excepciones)
// =========================================================================
echo "--- 1. Probando Validaciones y Sanitización (Setters) ---\n";

$usuarioTest = new Usuario();

// Cédula válida e inválida
try {
    $usuarioTest->setCedula("V-12.345.678 ");
    assertTest("Cédula sanitizada correctamente (remueve no-numéricos y espacios)", $usuarioTest->getCedula() === "12345678");
} catch (Exception $e) {
    assertTest("Cédula sanitizada correctamente", false);
}

try {
    $usuarioTest->setCedula("123"); // Cédula muy corta
    assertTest("Validación de cédula corta no lanzó excepción", false);
} catch (InvalidArgumentException $e) {
    assertTest("Validación de cédula corta capturada correctamente (Lanzó: " . $e->getMessage() . ")", true);
}

// Nombre y Apellido sanitizados
try {
    $usuarioTest->setNombre("<b>Juan</b> ");
    assertTest("Nombre remueve etiquetas HTML y espacios", $usuarioTest->getNombre() === "Juan");
} catch (Exception $e) {
    assertTest("Nombre sanitizado", false);
}

// Rol válido e inválido
try {
    $usuarioTest->setRol(" SUPERADMIN ");
    assertTest("Rol convierte a minúsculas y limpia espacios", $usuarioTest->getRol() === "superadmin");
} catch (Exception $e) {
    assertTest("Rol sanitizado", false);
}

try {
    $usuarioTest->setRol("visitante"); // Rol inválido
    assertTest("Validación de rol inválido no lanzó excepción", false);
} catch (InvalidArgumentException $e) {
    assertTest("Validación de rol inválido capturada correctamente (Lanzó: " . $e->getMessage() . ")", true);
}

// Contraseña plana
try {
    $usuarioTest->setPlainPassword("123456");
    assertTest("Contraseña hasheada correctamente con longitud mínima", password_verify("123456", $usuarioTest->getPassword()));
} catch (Exception $e) {
    assertTest("Contraseña plana", false);
}

try {
    $usuarioTest->setPlainPassword("123");
    assertTest("Contraseña corta no lanzó excepción", false);
} catch (InvalidArgumentException $e) {
    assertTest("Contraseña corta capturada correctamente (Lanzó: " . $e->getMessage() . ")", true);
}

echo "\n";

// =========================================================================
// TEST 2: Prueba de Login con Base de Datos (SQLite en memoria)
// =========================================================================
echo "--- 2. Probando Autenticación Segura (Login con PDO) ---\n";

try {
    // Creamos conexión SQLite en memoria para tests
    $db = new PDO("sqlite::memory:");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear tabla idéntica en SQLite (adaptando sintaxis)
    $db->exec("CREATE TABLE usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cedula TEXT NOT NULL UNIQUE,
        nombre TEXT NOT NULL,
        apellido TEXT NOT NULL,
        rol TEXT NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Creamos usuario semilla
    $plainPassword = "supersecretpassword123";
    $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("INSERT INTO usuarios (cedula, nombre, apellido, rol, password) VALUES (:cedula, :nombre, :apellido, :rol, :password)");
    $stmt->execute([
        ':cedula' => '22334455',
        ':nombre' => 'Test',
        ':apellido' => 'User',
        ':rol' => 'superadmin',
        ':password' => $hashedPassword
    ]);

    // Instanciamos el modelo inyectando la conexión SQLite de pruebas
    $usuarioModel = new Usuario($db);

    // Intentamos Login con credenciales CORRECTAS
    $loginExitoso = $usuarioModel->login('22334455', 'supersecretpassword123');
    assertTest("Login exitoso con credenciales correctas retorna TRUE", $loginExitoso === true);
    assertTest("Propiedades del objeto mapeadas correctamente en el Login (ID)", $usuarioModel->getId() === 1);
    assertTest("Propiedades del objeto mapeadas correctamente en el Login (Nombre)", $usuarioModel->getNombre() === 'Test');
    assertTest("Propiedades del objeto mapeadas correctamente en el Login (Rol)", $usuarioModel->getRol() === 'superadmin');

    // Intentamos Login con contraseña INCORRECTA
    $loginFallidoPassword = $usuarioModel->login('22334455', 'contrasenia_incorrecta');
    assertTest("Login fallido con contraseña incorrecta retorna FALSE", $loginFallidoPassword === false);
    assertTest("getLoginError() reporta contraseña incorrecta", $usuarioModel->getLoginError() === "La contraseña ingresada es incorrecta.");

    // Intentamos Login con cédula NO EXISTENTE
    $loginFallidoCedula = $usuarioModel->login('99999999', 'supersecretpassword123');
    assertTest("Login fallido con cédula inexistente retorna FALSE", $loginFallidoCedula === false);
    assertTest("getLoginError() reporta usuario inexistente", $usuarioModel->getLoginError() === "El usuario con la cédula ingresada no existe.");

} catch (Exception $e) {
    echo "❌ Error excepcional durante los tests de base de datos: " . $e->getMessage() . "\n";
    $failedTests++;
}

echo "\n=================================================================\n";
echo " RESUMEN DE PRUEBAS:\n";
echo " Pasadas: $passedTests / Falladas: $failedTests\n";
echo "=================================================================\n";

if ($failedTests > 0) {
    exit(1);
} else {
    exit(0);
}
