<?php
/**
 * Migración de Base de Datos: Adición de columna 'activo' para Borrado Lógico
 * Parque Zoológico y Botánico Bararida
 */

require_once __DIR__ . '/../config/Database.php';

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new Exception("No se pudo establecer conexión con la base de datos.");
    }

    $tablas = [
        'citas',
        'instituciones',
        'sedes',
        'personal',
        'visitantes',
        'recorrido',
        'zonas'
    ];

    echo "--- Iniciando migración de borrado lógico ---" . PHP_EOL;

    foreach ($tablas as $tabla) {
        // Verificar si la columna 'activo' ya existe en la tabla
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$tabla}` LIKE 'activo'");
        $stmt->execute();
        $existe = $stmt->fetch();

        if (!$existe) {
            $alterSql = "ALTER TABLE `{$tabla}` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1";
            $db->exec($alterSql);
            echo "✓ Columna 'activo' agregada exitosamente a la tabla: {$tabla}" . PHP_EOL;
        } else {
            echo "ℹ La tabla '{$tabla}' ya cuenta con la columna 'activo'." . PHP_EOL;
        }
    }

    echo "--- Migración completada exitosamente ---" . PHP_EOL;

} catch (Exception $e) {
    echo "ERROR en la migración: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
