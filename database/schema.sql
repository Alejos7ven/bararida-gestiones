-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 28-05-2026 a las 01:27:17
-- Versión del servidor: 8.0.40
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS bararida_gestiones DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bararida_gestiones;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recorrido`
--

CREATE TABLE `recorrido` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recorrido`
--

INSERT INTO `recorrido` (`id`, `nombre`) VALUES
(10, 'Abogados I'),
(11, 'Abogados II'),
(12, 'Libertador I');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutas`
--

CREATE TABLE `rutas` (
  `id` int NOT NULL,
  `recorrido_id` int NOT NULL,
  `zona_id` int NOT NULL,
  `orden` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rutas`
--

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
(49, 12, 33, 0),
(50, 12, 25, 1),
(51, 12, 26, 2),
(52, 12, 17, 3),
(53, 12, 18, 4),
(54, 12, 20, 5),
(55, 12, 21, 6),
(56, 12, 22, 7),
(57, 12, 35, 8),
(58, 12, 24, 9);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `cedula` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('superadmin','admin','guia') COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `cedula`, `nombre`, `apellido`, `rol`, `password`, `created_at`, `updated_at`) VALUES
(1, '28286982', 'Jose', 'De Marzo', 'superadmin', '$2y$10$nR1oEfcIOHrMu8Ccxli.mumVXMxyXPJxi29S9HZABzVZ5fsWYCt5G', '2026-05-20 23:04:43', '2026-05-20 23:33:55'),
(2, '28338690', 'Benito', 'Uzcategui', 'admin', '$2y$10$979Ny9OGgGPGUsKsgBrbXumMBBWw3T30VMJBAYQHQ1K6D3qVFrK1O', '2026-05-20 23:04:43', '2026-05-20 23:34:37'),
(3, '12345678', 'Donald', 'Trump', 'guia', '$2y$10$fvVy0XvXWhpjvQsN4zZQteJQc7CtxqqlT/vpEpJBNojXprcBCLw62', '2026-05-20 23:04:43', '2026-05-20 23:35:22'),
(4, '87654321', 'Maria', 'Fernandez', 'guia', '$2y$10$BbBaLRUyE99qZyP/.vGFgexCujK2qC/.AaKBZ5SQPnxookwd.eA4O', '2026-05-20 23:36:27', '2026-05-20 23:36:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `zonas`
--

CREATE TABLE `zonas` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `coordenadas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `zonas`
--

INSERT INTO `zonas` (`id`, `nombre`, `coordenadas`) VALUES
(17, 'Pabellón de pequeños carnívoros', '45.32,67.66'),
(18, 'Zona Manatí', '54.04,78.72'),
(20, 'Zona Criolla', '60.97,89.49'),
(21, 'Zona Andina', '81.60,75.99'),
(22, 'Botánica', '84.93,60.21'),
(23, 'Isla Central', '47.17,43.07'),
(24, 'Zoológico de Contacto', '48.08,26.85'),
(25, 'Zona Africana', '21.69,38.83'),
(26, 'Nutria', '26.24,65.52'),
(33, 'Entrada Libertador', '24.42,16.23'),
(34, 'Entrada Los Abogados', '17.29,90.70'),
(35, 'Entrada Morán', '79.02,53.39');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `recorrido`
--
ALTER TABLE `recorrido`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_recorrido` (`recorrido_id`),
  ADD KEY `FK_zona` (`zona_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indices de la tabla `zonas`
--
ALTER TABLE `zonas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `recorrido`
--
ALTER TABLE `recorrido`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `rutas`
--
ALTER TABLE `rutas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `zonas`
--
ALTER TABLE `zonas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD CONSTRAINT `FK_recorrido` FOREIGN KEY (`recorrido_id`) REFERENCES `recorrido` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_zona` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
