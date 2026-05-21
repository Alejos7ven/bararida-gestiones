-- Script de creación de base de datos para el Parque Bararida
-- Tabla de Usuarios con control de accesos basado en roles (RBAC)

CREATE DATABASE IF NOT EXISTS bararida_gestiones DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bararida_gestiones;

-- -----------------------------------------------------
-- Tabla `usuarios`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cedula` VARCHAR(15) NOT NULL UNIQUE,
  `nombre` VARCHAR(50) NOT NULL,
  `apellido` VARCHAR(50) NOT NULL,
  `rol` ENUM('superadmin', 'admin', 'guia') NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Registros Semilla (Usuarios de prueba)
-- Contraseñas hasheadas de forma segura con Bcrypt
-- -----------------------------------------------------

-- 1. Superadmin (Jefe de Departamento) -> Cédula: 12345678, Password: super123
INSERT INTO `usuarios` (`cedula`, `nombre`, `apellido`, `rol`, `password`)
VALUES ('12345678', 'Super', 'Admin', 'superadmin', '$2y$10$nR1oEfcIOHrMu8Ccxli.mumVXMxyXPJxi29S9HZABzVZ5fsWYCt5G')
ON DUPLICATE KEY UPDATE `rol` = 'superadmin';

