-- =============================================================================
-- Migración de Actualización de Longitudes y Reglas de Esquema
-- Parque Zoológico y Botánico Bararida
-- =============================================================================

-- 1. Tabla entidades: tipo_documento (10), documento (15), nombre_completo (50)
ALTER TABLE `entidades` 
  MODIFY `tipo_documento` varchar(10) NOT NULL,
  MODIFY `documento` varchar(15) NOT NULL,
  MODIFY `nombre_completo` varchar(50) NOT NULL;

-- 2. Tabla recorrido: nombre (50)
ALTER TABLE `recorrido` 
  MODIFY `nombre` varchar(50) NOT NULL;

-- 3. Tabla zonas: nombre (50)
ALTER TABLE `zonas` 
  MODIFY `nombre` varchar(50) NOT NULL;

-- 4. Tabla visitantes: cedula (15), nombres (25), apellidos (25)
ALTER TABLE `visitantes` 
  MODIFY `cedula` varchar(15) DEFAULT NULL,
  MODIFY `nombres` varchar(25) NOT NULL,
  MODIFY `apellidos` varchar(25) NOT NULL;

-- 5. Tabla modulos: nombre (25)
ALTER TABLE `modulos` 
  MODIFY `nombre` varchar(25) NOT NULL;

-- 6. Tabla instituciones: tipo_organizacion (20), tipo_gestion (20)
ALTER TABLE `instituciones` 
  MODIFY `tipo_organizacion` varchar(20) NOT NULL,
  MODIFY `tipo_gestion` varchar(20) NOT NULL DEFAULT 'publica';

-- 7. Tabla citas: tipo (25), metodo_pago (25)
-- Asegurar columna tipo y longitud de metodo_pago
ALTER TABLE `citas` 
  MODIFY `metodo_pago` varchar(25) NOT NULL;

