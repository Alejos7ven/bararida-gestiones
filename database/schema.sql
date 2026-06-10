

CREATE DATABASE IF NOT EXISTS bararida_gestiones DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bararida_gestiones;

CREATE TABLE asistencia_cita (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `cita_id` int NOT NULL,
  `visitante_id` int NOT NULL
);

CREATE TABLE `citas` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `institucion_id` int NOT NULL,
  `recorrido_id` int DEFAULT NULL,
  `fecha_solicitud` date NOT NULL,
  `fecha_visita` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `estatus` varchar(20) NOT NULL DEFAULT 'pendiente',
  `metodo_pago` varchar(50) NOT NULL,
  `pago_estado` varchar(20) NOT NULL DEFAULT 'pendiente'
);

CREATE TABLE ciudades (
  `id_ciudad` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_estado` int NOT NULL,
  `ciudad` varchar(200) NOT NULL,
  `capital` tinyint(1) NOT NULL DEFAULT '0'
);

CREATE TABLE `direccion` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `institucion_id` int NOT NULL,
  `ciudad_id` int NOT NULL,
  `parroquia_id` int NOT NULL,
  `detalles` varchar(150) DEFAULT NULL
);

CREATE TABLE `directorio` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `entidad_id` int NOT NULL,
  `contacto` varchar(30) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'principal'
);

CREATE TABLE `entidades` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tipo_documento` varchar(20) NOT NULL,
  `documento` varchar(50) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) DEFAULT NULL,
  `tipo_entidad` varchar(20) NOT NULL DEFAULT 'persona'
);

INSERT INTO `entidades` (`id`, `tipo_documento`, `documento`, `nombre`, `apellido`, `tipo_entidad`) VALUES
(1, 'V', '28286982', 'Jose', 'De Marzo', 'persona'),
(2, 'J', '009090', 's7ven', NULL, 'institucion');

CREATE TABLE estados (
  `id_estado` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `estado` varchar(250) NOT NULL,
  `iso_3166-2` varchar(4) NOT NULL
);

CREATE TABLE guia_asignado (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `cita_id` int NOT NULL,
  `personal_id` int NOT NULL,
  `rol_guia` varchar(30) NOT NULL DEFAULT 'principal'
);

CREATE TABLE `instituciones` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `entidad_id` int NOT NULL,
  `tipo_organizacion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
);

INSERT INTO `instituciones` (`id`, `entidad_id`, `tipo_organizacion`) VALUES
(2, 2, 'personal');

CREATE TABLE municipios (
  `id_municipio` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_estado` int NOT NULL,
  `municipio` varchar(100) NOT NULL
);

CREATE TABLE parroquias (
  `id_parroquia` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_municipio` int NOT NULL,
  `parroquia` varchar(250) NOT NULL
);

CREATE TABLE `personal` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `entidad_id` int NOT NULL,
  `rol_id` int NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO `personal` (`id`, `entidad_id`, `rol_id`, `password`, `created_at`, `updated_at`) VALUES
(4, 1, 1, '$2y$10$nR1oEfcIOHrMu8Ccxli.mumVXMxyXPJxi29S9HZABzVZ5fsWYCt5G', '2026-06-09 21:10:57', '2026-06-09 21:10:57');

CREATE TABLE recorrido (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100) NOT NULL
);

INSERT INTO `recorrido` (`id`, `nombre`) VALUES
(10, 'Abogados I'),
(11, 'Abogados II'),
(12, 'Libertador I');

CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(30) NOT NULL
);

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'superadmin'),
(2, 'admin'),
(3, 'guia');


CREATE TABLE `rutas` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `recorrido_id` int NOT NULL,
  `zona_id` int NOT NULL,
  `orden` int NOT NULL
);

INSERT INTO `rutas` (`id`, `recorrido_id`, `zona_id`, `orden`) VALUES
(29, 10, 34, 0),
(30, 10, 26, 1),
(31, 10, 25, 2),
(32, 10, 24, 3),
(33, 10, 35, 4),
(34, 10, 22, 5),
(35, 10, 21, 6),
(36, 10, 20, 7),
(37, 10, 18, 8),
(38, 10, 17, 9),
(39, 10, 23, 10),
(40, 11, 34, 0),
(41, 11, 17, 1),
(42, 11, 18, 2),
(43, 11, 20, 3),
(44, 11, 21, 4),
(45, 11, 22, 5),
(46, 11, 24, 6),
(47, 11, 25, 7),
(48, 11, 26, 8),
(72, 12, 33, 0),
(73, 12, 25, 1),
(74, 12, 26, 2),
(75, 12, 17, 3),
(76, 12, 18, 4),
(77, 12, 20, 5),
(78, 12, 21, 6),
(79, 12, 22, 7),
(80, 12, 35, 8),
(81, 12, 24, 9),
(82, 12, 23, 10);

CREATE TABLE visitantes (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `cedula` varchar(30) DEFAULT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL
);

CREATE TABLE zonas (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100) NOT NULL,
  `coordenadas` varchar(255) NOT NULL
);

INSERT INTO `zonas` (`id`, `nombre`, `coordenadas`) VALUES
(17, 'Pabellón de pequeños carnívoros', '45.32,67.66'),
(18, 'Zona Manatí', '54.04,78.72'),
(20, 'Zona Criolla', '60.97,89.49'),
(21, 'Zona Andina', '81.60,75.99'),
(22, 'Botánica', '88.96,64.03'),
(23, 'Isla Central', '47.17,43.07'),
(24, 'Zoológico de Contacto', '48.08,26.85'),
(25, 'Zona Africana', '21.69,38.83'),
(26, 'Nutria', '26.24,65.52'),
(33, 'Entrada Libertador', '24.42,16.23'),
(34, 'Entrada Los Abogados', '17.29,90.70'),
(35, 'Entrada Morán', '82.54,50.90');

ALTER TABLE `asistencia_cita`
  ADD KEY `fk_asistencia_citas` (`cita_id`),
  ADD KEY `fk_visitante_asistencia` (`visitante_id`);
  
ALTER TABLE `citas`
  ADD KEY `fk_instituciones_citas` (`institucion_id`),
  ADD KEY `fk_recorrido_citas` (`recorrido_id`);
  
ALTER TABLE `ciudades`
  ADD KEY `id_estado` (`id_estado`);

ALTER TABLE `direccion`
  ADD KEY `fk_institucion_dir` (`institucion_id`),
  ADD KEY `fk_ciudad` (`ciudad_id`),
  ADD KEY `fk_parroquia` (`parroquia_id`);
  
ALTER TABLE `directorio`
  ADD KEY `fk_dir_entidad` (`entidad_id`);

ALTER TABLE `entidades`
  ADD UNIQUE KEY `documento` (`documento`);
  
ALTER TABLE `guia_asignado`
  ADD KEY `fk_guia_citas` (`cita_id`),
  ADD KEY `fk_guia_personal` (`personal_id`);
  
ALTER TABLE `instituciones`
  ADD UNIQUE KEY `entidad_id` (`entidad_id`);
  
ALTER TABLE `municipios`
  ADD KEY `id_estado` (`id_estado`);
  
ALTER TABLE `parroquias`
  ADD KEY `id_municipio` (`id_municipio`);
  
ALTER TABLE `personal`
  ADD KEY `fk_entidad_personal` (`entidad_id`),
  ADD KEY `fk_rol` (`rol_id`);

ALTER TABLE `rutas`
  ADD KEY `FK_recorrido` (`recorrido_id`),
  ADD KEY `FK_zona` (`zona_id`);

ALTER TABLE `asistencia_cita`
  ADD CONSTRAINT `FK_citas` FOREIGN KEY (`cita_id`) REFERENCES `citas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_visitantes` FOREIGN KEY (`visitante_id`) REFERENCES `visitantes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `citas`
  ADD CONSTRAINT `FK_instituciones` FOREIGN KEY (`institucion_id`) REFERENCES `instituciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_rec` FOREIGN KEY (`recorrido_id`) REFERENCES `recorrido` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `ciudades`
  ADD CONSTRAINT `ciudades_ibfk_1` FOREIGN KEY (`id_estado`) REFERENCES `estados` (`id_estado`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `direccion`
  ADD CONSTRAINT `FK_ciudad_dir` FOREIGN KEY (`ciudad_id`) REFERENCES `ciudades` (`id_ciudad`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_institucion_dir` FOREIGN KEY (`institucion_id`) REFERENCES `instituciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_parroquia_dir` FOREIGN KEY (`parroquia_id`) REFERENCES `parroquias` (`id_parroquia`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `guia_asignado`
  ADD CONSTRAINT `FK_cita_guia` FOREIGN KEY (`cita_id`) REFERENCES `citas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_personal` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `municipios`
  ADD CONSTRAINT `municipios_ibfk_1` FOREIGN KEY (`id_estado`) REFERENCES `estados` (`id_estado`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `parroquias`
  ADD CONSTRAINT `parroquias_ibfk_1` FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id_municipio`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `rutas`
  ADD CONSTRAINT `FK_recorrido` FOREIGN KEY (`recorrido_id`) REFERENCES `recorrido` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_zona` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `directorio`
  ADD CONSTRAINT `FK_dir_entidad` FOREIGN KEY (`entidad_id`) REFERENCES `entidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `instituciones`
  ADD CONSTRAINT `fk_entidad_institucion` FOREIGN KEY (`entidad_id`) REFERENCES `entidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `personal`
  ADD CONSTRAINT `fk_entidad_personal` FOREIGN KEY (`entidad_id`) REFERENCES `entidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;