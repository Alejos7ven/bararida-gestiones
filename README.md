# Sistema de Gestión de Citas y Visitas Guiadas - Bararida

Este es un sistema administrativo web para la programación, registro y control de visitas escolares/institucionales al **Parque Zoológico y Botánico Bararida**.

## Características Principales
- **Dashboard Dinámico:** Vista adaptada al rol de usuario (Super Administrador, Administrador, Guía de Campo). Estadísticas en tiempo real de visitas, guías y agenda.
- **Gestión de Citas:** Modulo de agendamiento secuencial asistido (Step Wizard), control de estatus de aprobación y estado de pago de las visitas.
- **Carga de Visitantes vía Excel:** Permite descargar una plantilla en formato nativo Excel (`.xlsx`), rellenar y subir los datos de los visitantes de la institución de forma masiva.
- **Paginación Dinámica Avanzada:** Tablas de datos en todos los módulos paginadas localmente en bloques limpios de 10 ítems.
- **Asignación de Guías:** Capacidad de asignar guías de campo a citas específicas y cambiar sus estados de asistencia.

## Stack Tecnológico Usado
- **Backend:** PHP Vanilla (MVC nativo, consultas preparadas seguras).
- **Base de Datos:** MySQL (con soporte transaccional y relaciones estrictas).
- **Frontend:** HTML5, CSS Premium con Bootstrap y JavaScript (jQuery para asincronía y peticiones AJAX robustas).
- **Librerías Locales Incluidas:**
  - `xlsx.min.js` (Lectura de planillas de Excel en el cliente).
  - `jquery.validate.min.js` (Validaciones robustas del lado del cliente).

## Requisitos de Ejecución Local
1. **Servidor Web:** Apache 2.4 o superior / PHP 8.0 o superior.
2. **Base de Datos:** MySQL 5.7 o superior / MariaDB 10.4 o superior.

## Instalación y Configuración Rápida
1. Clona el repositorio dentro de tu directorio público del servidor (e.g. `/var/www/html/` o `htdocs/`).
2. Importa el archivo de base de datos [database_setup.sql](file:///database_setup.sql) en tu servidor MySQL. Esto creará la base de datos `bararida_gestiones` e insertará los registros de configuración iniciales.
3. Asegúrate de configurar las credenciales correctas en `config/Database.php`.
4. Abre la URL local en tu navegador (ej. `http://localhost/bararida-gestiones`).

### Credenciales de Prueba Iniciales
- **Super Administrador:**
  - **Cédula/Documento:** `0000` (Tipo V)
  - **Contraseña:** `admin123`
- **Guía de Campo:**
  - **Cédula/Documento:** `1111` (Tipo V)
  - **Contraseña:** `admin123`
