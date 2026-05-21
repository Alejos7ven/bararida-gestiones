<?php
/**
 * Script de Pruebas Unitarias e Integración para el CRUD de Usuarios
 * Ejecuta validaciones estrictas del CRUD de Usuario usando SQLite en memoria.
 */

// Requerimos el archivo del Modelo Usuario
require_once __DIR__ . "/../models/Usuario.php";

echo "=================================================================\n";
echo " INICIANDO PRUEBAS DEL CRUD DE USUARIOS - MODELO USUARIO\n";
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // =========================================================================
    // SEMILLAS DE PRUEBA
    // =========================================================================
    // 1. Superadmin
    $stmt = $db->prepare("INSERT INTO usuarios (cedula, nombre, apellido, rol, password) VALUES (:cedula, :nombre, :apellido, :rol, :password)");
    $stmt->execute([
        ':cedula' => '12345678',
        ':nombre' => 'Alejandro',
        ':apellido' => 'Mendoza',
        ':rol' => 'superadmin',
        ':password' => password_hash('super123', PASSWORD_BCRYPT)
    ]);

    // 2. Admin
    $stmt->execute([
        ':cedula' => '87654321',
        ':nombre' => 'María',
        ':apellido' => 'Gómez',
        ':rol' => 'admin',
        ':password' => password_hash('admin123', PASSWORD_BCRYPT)
    ]);

    $usuarioModel = new Usuario($db);

    // =========================================================================
    // PRUEBAS DE LECTURA (Listar y Obtener)
    // =========================================================================
    echo "--- 1. Pruebas de Lectura (Listar y Obtener) ---\n";
    
    $lista = $usuarioModel->listarTodos();
    assertTest("Listar todos retorna un array con los usuarios sembrados", is_array($lista) && count($lista) === 2);
    
    $superadminData = Usuario::obtenerPorId($db, 1);
    assertTest("obtenerPorId retorna el usuario correcto (Super Admin)", $superadminData !== null && $superadminData['rol'] === 'superadmin' && $superadminData['nombre'] === 'Alejandro');

    $adminData = Usuario::obtenerPorId($db, 2);
    assertTest("obtenerPorId retorna el usuario correcto (Admin)", $adminData !== null && $adminData['rol'] === 'admin' && $adminData['nombre'] === 'María');

    $noExisteData = Usuario::obtenerPorId($db, 999);
    assertTest("obtenerPorId con ID inexistente retorna NULL", $noExisteData === null);

    assertTest("existeCedula reporta true para cédula sembrada (12345678)", Usuario::existeCedula($db, '12345678') === true);
    assertTest("existeCedula reporta false para cédula inexistente (99999999)", Usuario::existeCedula($db, '99999999') === false);
    
    echo "\n";

    // =========================================================================
    // PRUEBAS DE CREACIÓN Y RESTRICCIONES
    // =========================================================================
    echo "--- 2. Pruebas de Registro/Creación y Restricciones ---\n";

    // Registrar guía válido
    $nuevoGuia = new Usuario($db);
    $nuevoGuia->setCedula('11223344');
    $nuevoGuia->setNombre('Carlos');
    $nuevoGuia->setApellido('Rodríguez');
    $nuevoGuia->setRol('guia');
    $nuevoGuia->setPlainPassword('guia123');

    assertTest("Crear nuevo Guía válido retorna TRUE", $nuevoGuia->crear() === true);
    assertTest("El nuevo Guía se registró en la base de datos", Usuario::existeCedula($db, '11223344') === true);

    // Intentar crear un Superadmin (Debe fallar)
    try {
        $nuevoSuper = new Usuario($db);
        $nuevoSuper->setCedula('55667788');
        $nuevoSuper->setNombre('Super');
        $nuevoSuper->setApellido('Dos');
        $nuevoSuper->setRol('superadmin');
        $nuevoSuper->setPlainPassword('super321');
        
        $nuevoSuper->crear();
        assertTest("Creación de Super Administrador no lanzó excepción", false);
    } catch (InvalidArgumentException $e) {
        assertTest("Creación de Super Administrador bloqueada correctamente (Lanzó: " . $e->getMessage() . ")", true);
    }

    // Intentar crear un usuario con cédula existente (Debe fallar)
    try {
        $duplicado = new Usuario($db);
        $duplicado->setCedula('12345678'); // Cédula del superadmin sembrado
        $duplicado->setNombre('Clon');
        $duplicado->setApellido('Mendoza');
        $duplicado->setRol('admin');
        $duplicado->setPlainPassword('admin321');
        
        $duplicado->crear();
        assertTest("Creación de cédula duplicada no lanzó excepción", false);
    } catch (InvalidArgumentException $e) {
        assertTest("Creación de cédula duplicada bloqueada correctamente (Lanzó: " . $e->getMessage() . ")", true);
    }

    echo "\n";

    // =========================================================================
    // PRUEBAS DE ACTUALIZACIÓN Y POLÍTICAS RBAC
    // =========================================================================
    echo "--- 3. Pruebas de Modificación/Actualización y Políticas RBAC ---\n";

    // Modificar un usuario normal
    $guiaAEditar = new Usuario($db);
    $guiaAEditar->setId(3); // El guía creado en el test anterior
    $guiaAEditar->setCedula('11223344');
    $guiaAEditar->setNombre('Carlos Modificado');
    $guiaAEditar->setApellido('Rodríguez Modificado');
    $guiaAEditar->setRol('admin'); // Ascendido a admin
    // Sin clave nueva (debería conservar la anterior)

    assertTest("Actualizar usuario normal retorna TRUE", $guiaAEditar->actualizar() === true);
    
    $guiaVerificacion = Usuario::obtenerPorId($db, 3);
    assertTest("Cambios de nombre persistidos en base de datos", $guiaVerificacion['nombre'] === 'Carlos Modificado');
    assertTest("Cambio de rol (Guía -> Admin) persistido en base de datos", $guiaVerificacion['rol'] === 'admin');

    // Intentar degradar al Superadmin principal (Debe fallar)
    try {
        $superDegradado = new Usuario($db);
        $superDegradado->setId(1); // El superadmin sembrado
        $superDegradado->setCedula('12345678');
        $superDegradado->setNombre('Alejandro');
        $superDegradado->setApellido('Mendoza');
        $superDegradado->setRol('admin'); // Intentando degradar a admin
        
        $superDegradado->actualizar();
        assertTest("Degradación de Super Administrador no lanzó excepción", false);
    } catch (InvalidArgumentException $e) {
        assertTest("Degradación de Super Administrador bloqueada correctamente (Lanzó: " . $e->getMessage() . ")", true);
    }

    // Intentar promover a alguien al rol de Superadmin (Debe fallar)
    try {
        $adminPromovido = new Usuario($db);
        $adminPromovido->setId(2); // El admin sembrado
        $adminPromovido->setCedula('87654321');
        $adminPromovido->setNombre('María');
        $adminPromovido->setApellido('Gómez');
        $adminPromovido->setRol('superadmin'); // Intentando promover a superadmin
        
        $adminPromovido->actualizar();
        assertTest("Promoción a Super Administrador no lanzó excepción", false);
    } catch (InvalidArgumentException $e) {
        assertTest("Promoción a Super Administrador bloqueada correctamente (Lanzó: " . $e->getMessage() . ")", true);
    }

    echo "\n";

    // =========================================================================
    // PRUEBAS DE ELIMINACIÓN Y RESTRICCIONES CRÍTICAS
    // =========================================================================
    echo "--- 4. Pruebas de Eliminación y Restricciones Críticas ---\n";

    // Intentar eliminar al Superadmin (Debe fallar estrictamente)
    try {
        $usuarioModel->eliminar(1);
        assertTest("Eliminación de Super Administrador no lanzó excepción", false);
    } catch (InvalidArgumentException $e) {
        assertTest("Eliminación de Super Administrador bloqueada correctamente (Lanzó: " . $e->getMessage() . ")", true);
    }

    // Eliminar un administrador normal (Debe pasar)
    assertTest("Eliminación de administrador normal retorna TRUE", $usuarioModel->eliminar(2) === true);
    assertTest("El administrador ya no existe en la base de datos", Usuario::obtenerPorId($db, 2) === null);

} catch (Exception $e) {
    echo "❌ Error excepcional durante las pruebas del CRUD: " . $e->getMessage() . "\n";
    $failedTests++;
}

echo "\n=================================================================\n";
echo " RESUMEN DE PRUEBAS DEL CRUD:\n";
echo " Pasadas: $passedTests / Falladas: $failedTests\n";
echo "=================================================================\n";

if ($failedTests > 0) {
    exit(1);
} else {
    exit(0);
}
