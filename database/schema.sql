

-- CREATE DATABASE IF NOT EXISTS bararida_gestiones DEFAULT CHARACTER SET utf8mb4;
-- USE bararida_gestiones;
CREATE TABLE IF NOT EXISTS entidades (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tipo_documento` varchar(20) NOT NULL,
  `documento` varchar(50) NOT NULL UNIQUE,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) DEFAULT NULL,
  `tipo_entidad` varchar(20) NOT NULL DEFAULT 'persona'
);

CREATE TABLE IF NOT EXISTS roles (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(30) NOT NULL
);

CREATE TABLE IF NOT EXISTS personal (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `entidad_id` int NOT NULL,
  `rol_id` int NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
  CONSTRAINT fk_entidad_personal 
    FOREIGN KEY (`entidad_id`) REFERENCES `entidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_rol_personal 
    FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
); 

CREATE TABLE IF NOT EXISTS recorrido (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS zonas (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(100) NOT NULL,
  `coordenadas` varchar(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS rutas (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `recorrido_id` int NOT NULL,
  `zona_id` int NOT NULL,
  `orden` int NOT NULL,
  CONSTRAINT fk_recorrido_rutas 
    FOREIGN KEY (recorrido_id) REFERENCES recorrido(id),
  CONSTRAINT fk_zona_rutas 
    FOREIGN KEY (zona_id) REFERENCES zonas(id)
);

CREATE TABLE IF NOT EXISTS instituciones (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `entidad_id` int NOT NULL,
  `tipo_organizacion` varchar(50) NOT NULL,
  `tipo_gestion` varchar(50) NOT NULL DEFAULT 'publica',
  CONSTRAINT fk_entidad_institucion 
    FOREIGN KEY (`entidad_id`) REFERENCES `entidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
); 

CREATE TABLE IF NOT EXISTS citas (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `institucion_id` int NOT NULL,
  `recorrido_id` int DEFAULT NULL,
  `fecha_solicitud` date NOT NULL,
  `fecha_visita` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `estatus` varchar(20) NOT NULL DEFAULT 'pendiente',
  `metodo_pago` varchar(50) NOT NULL,
  `pago_estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  CONSTRAINT fk_instituciones_citas
    FOREIGN KEY (`institucion_id`) REFERENCES `instituciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_recorrido_citas
    FOREIGN KEY (`recorrido_id`) REFERENCES `recorrido` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS visitantes (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `cedula` varchar(30) DEFAULT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL
);

CREATE TABLE IF NOT EXISTS asistencia_cita (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `cita_id` int NOT NULL,
  `visitante_id` int NOT NULL, 
  CONSTRAINT fk_asistencia_citas
    FOREIGN KEY (`cita_id`) REFERENCES `citas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_visitante_asistencia
    FOREIGN KEY (`visitante_id`) REFERENCES `visitantes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS guia_asignado (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `cita_id` int NOT NULL,
  `personal_id` int NOT NULL,
  `rol_guia` varchar(30) NOT NULL DEFAULT 'principal',
  CONSTRAINT FK_cita_guia 
    FOREIGN KEY (cita_id) REFERENCES citas(id),
  CONSTRAINT FK_personal 
    FOREIGN KEY (personal_id) REFERENCES personal(id)
);

CREATE TABLE IF NOT EXISTS estados (
  `id_estado` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `estado` varchar(250) NOT NULL,
  `iso_3166-2` varchar(4) NOT NULL
); 

CREATE TABLE IF NOT EXISTS ciudades (
  `id_ciudad` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_estado` int NOT NULL,
  `ciudad` varchar(200) NOT NULL,
  `capital` tinyint(1) NOT NULL DEFAULT '0',
  CONSTRAINT fk_ciudades_estados
    FOREIGN KEY (`id_estado`) REFERENCES `estados` (`id_estado`) ON DELETE RESTRICT ON UPDATE CASCADE
); 

CREATE TABLE IF NOT EXISTS municipios (
  `id_municipio` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_estado` int NOT NULL,
  `municipio` varchar(100) NOT NULL,
  CONSTRAINT fk_municipios_estados
    FOREIGN KEY (`id_estado`) REFERENCES `estados` (`id_estado`) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS parroquias (
  `id_parroquia` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_municipio` int NOT NULL,
  `parroquia` varchar(250) NOT NULL,
  CONSTRAINT fk_parroquias_municipios
    FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id_municipio`) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS direccion (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `institucion_id` int NOT NULL,
  `ciudad_id` int NOT NULL,
  `parroquia_id` int NOT NULL,
  `detalles` varchar(150) DEFAULT NULL,
  CONSTRAINT fk_institucion_dir 
    FOREIGN KEY (`institucion_id`) REFERENCES `instituciones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ciudad 
    FOREIGN KEY (`ciudad_id`) REFERENCES `ciudades` (`id_ciudad`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_parroquia 
    FOREIGN KEY (`parroquia_id`) REFERENCES `parroquias` (`id_parroquia`) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS `directorio` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `entidad_id` int NOT NULL,
  `contacto` varchar(30) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'principal',
  CONSTRAINT fk_dir_entidad 
    FOREIGN KEY (`entidad_id`) REFERENCES `entidades` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS modulos (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` varchar(50) NOT NULL UNIQUE,
  `label` varchar(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS permisos (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `rol_id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `puede_acceder` tinyint(1) NOT NULL DEFAULT '0',
  CONSTRAINT fk_permisos_rol
    FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_permisos_modulo
    FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT uq_permiso UNIQUE (`rol_id`, `modulo_id`)
);


-- Carga de datos
INSERT INTO `recorrido` (`nombre`) VALUES
('Abogados I'),
('Abogados II'),
('Libertador I');

INSERT INTO `zonas` (`nombre`, `coordenadas`) VALUES
('Pabellón de pequeños carnívoros', '45.32,67.66'),
('Zona Manatí', '54.04,78.72'),
('Zona Criolla', '60.97,89.49'),
('Zona Andina', '81.60,75.99'),
('Botánica', '88.96,64.03'),
('Isla Central', '47.17,43.07'),
('Zoológico de Contacto', '48.08,26.85'),
('Zona Africana', '21.69,38.83'),
('Nutria', '26.24,65.52'),
('Entrada Libertador', '24.42,16.23'),
('Entrada Los Abogados', '17.29,90.70'),
('Entrada Morán', '82.54,50.90');

INSERT INTO `entidades` (`tipo_documento`, `documento`, `nombre`, `apellido`, `tipo_entidad`) VALUES
('V', '0000', 'Admin', 'Maestro', 'persona');

INSERT INTO `roles` (`nombre`) VALUES
('superadmin'),
('admin'),
('guia'); 

INSERT INTO `personal` (`entidad_id`, `rol_id`, `password`, `created_at`, `updated_at`) VALUES
(1, 1, '$2y$10$nR1oEfcIOHrMu8Ccxli.mumVXMxyXPJxi29S9HZABzVZ5fsWYCt5G', '2026-06-09 21:10:57', '2026-06-09 21:10:57');

INSERT INTO `instituciones` (`entidad_id`, `tipo_organizacion`) VALUES
(2, 'personal'); 

-- Catálogo de módulos del sistema
INSERT INTO `modulos` (`nombre`, `label`) VALUES
('dashboard', 'Dashboard'),
('citas', 'Citas'),
('instituciones', 'Instituciones'),
('recorridos', 'Recorridos'),
('visitantes', 'Visitantes'),
('personal', 'Personal'),
('app', 'Configuración');

-- Permisos por rol (rol_id: 1=superadmin, 2=admin, 3=guia | modulo_id según orden de INSERT)
-- superadmin: acceso total
INSERT INTO `permisos` (`rol_id`, `modulo_id`, `puede_acceder`) VALUES
(1, 1, 1), -- superadmin > dashboard
(1, 2, 1), -- superadmin > citas
(1, 3, 1), -- superadmin > instituciones
(1, 4, 1), -- superadmin > recorridos
(1, 5, 1), -- superadmin > visitantes
(1, 6, 1); -- superadmin > personal

-- admin: sin acceso a personal
INSERT INTO `permisos` (`rol_id`, `modulo_id`, `puede_acceder`) VALUES
(2, 1, 1), -- admin > dashboard
(2, 2, 1), -- admin > citas
(2, 3, 1), -- admin > instituciones
(2, 4, 1), -- admin > recorridos
(2, 5, 1), -- admin > visitantes
(2, 6, 0); -- admin > personal (denegado)

-- guia: solo citas
INSERT INTO `permisos` (`rol_id`, `modulo_id`, `puede_acceder`) VALUES
(3, 1, 1), -- guia > dashboard
(3, 2, 1), -- guia > citas
(3, 3, 0), -- guia > instituciones (denegado)
(3, 4, 0), -- guia > recorridos (denegado)
(3, 5, 0), -- guia > visitantes (denegado)
(3, 6, 0); -- guia > personal (denegado)

COMMIT; 