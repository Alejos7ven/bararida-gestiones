# Sistema de Gestión de Citas y Visitas Guiadas — Parque Zoológico y Botánico Bararida

Sistema administrativo web integral para la programación, registro, control operativo y generación de reportes estadísticos de visitas guiadas e instituciones en el **Parque Zoológico y Botánico Bararida** (Barquisimeto, Estado Lara, Venezuela).

---

## Índice
1. [Descripción General](#descripción-general)
2. [Características y Módulos](#características-y-módulos)
3. [Arquitectura y Patrones de Diseño](#arquitectura-y-patrones-de-diseño)
4. [Stack Tecnológico y Dependencias Locales](#stack-tecnológico-y-dependencias-locales)
5. [Estructura del Proyecto](#estructura-del-proyecto)
6. [Requisitos de Entorno](#requisitos-de-entorno)
7. [Instalación y Despliegue Local](#instalación-y-despliegue-local)
8. [Credenciales de Prueba](#credenciales-de-prueba)

---

## Descripción General

El **Parque Zoológico y Botánico Bararida** recibe anualmente a cientos de instituciones educativas, fundaciones, empresas y comunidades organizadas. Este software centraliza la administración de dichas solicitudes a través de un flujo modular que incluye:

- Agendamiento de citas guiadas con control de capacidad semanal y cupos por bloque horario.
- Carga masiva de nóminas de visitantes mediante plantillas Excel (`.xlsx`).
- Editor visual de rutas y zonas delimitadas en el mapa oficial del parque.
- Módulo analítico de métricas y generación de reportes con exportación en PDF.
- Matriz de permisos y control de acceso basado en roles (RBAC) dinámico.

---

## Características y Módulos

### 1. Panel de Control (Dashboard)
- **KPIs y Accesos Rápidos:** Métricas clave adaptadas según el rol del usuario en sesión (*Superadmin, Administrador, Guía de Campo*).
- **Agenda del Día:** Listado en tiempo real de visitas guiadas programadas para la jornada con asignación de guías.
- **Cronograma Mensual:** Calendario interactivo desarrollado sobre FullCalendar con detección de alertas de capacidad.
- **Visualizador del Parque:** Mapa interactivo con visualización de recorridos trazados y paradas secuenciales.
- **Gráfica de Afluencia:** Histórico interactivo de visitantes procesados mediante Chart.js.

### 2. Gestión de Citas y Visitas Guiadas
- **Wizard Multi-paso (Paso a Paso):**
  1. *Identificación de Institución:* Selección de institución existente o registro instantáneo con su sede física.
  2. *Parámetros de la Visita:* Selección de fecha, bloque horario y recorrido turístico asignado.
  3. *Condiciones de Pago:* Métodos (Transferencia, Pago Móvil, Efectivo USD/VES, Exoneración institucional) y control de estatus de pago (*Pendiente, Pagado, Exonerado*).
  4. *Importación de Visitantes:* Carga masiva vía Excel o adición manual de acompañantes.
- **Estados Operativos:** Control de flujo de vida de la cita (*Pendiente, Aprobada, Completada, Cancelada*).
- **Asignación de Guías:** Asignación de personal de campo en rol principal o de apoyo.

### 3. Carga Masiva de Visitantes (.xlsx)
- Generación y descarga directa en el navegador de la plantilla oficial de visitantes en formato `.xlsx`.
- Parseo en el cliente con **SheetJS** (`xlsx.min.js`), extrayendo nombres, apellidos, cédula y fecha de nacimiento.
- **Validación Estricta:** Detección de errores en filas defectuosas (cédulas con caracteres alfabéticos, nombres numéricos o fechas de nacimiento futuras/inválidas), alertando al usuario antes del guardado.

### 4. Directorio de Instituciones y Sedes
- Registro de colegios privados, escuelas públicas, liceos, universidades, fundaciones y empresas.
- **Gestión Jerárquica de Sedes:** Una institución puede poseer múltiples sedes físicas enlazadas a la división político-territorial venezolana (Estado, Municipio, Ciudad y Parroquia).

### 5. Editor de Recorridos y Zonas en el Mapa
- Editor interactivo sobre el mapa ilustrado oficial del parque.
- **Modo Zona:** Registro de pines georreferenciados con coordenadas porcentuales responsivas.
- **Modo Recorrido:** Trazado de rutas uniendo zonas en secuencias de visitas guiadas.

### 6. Padrón de Visitantes
- Catálogo unificado de visitantes registrados en el sistema.
- Clasificación automática por grupos etarios:
  - Infantes (0 a 5 años)
  - Niños (6 a 12 años)
  - Adolescentes (13 a 17 años)
  - Adultos (18 a 59 años)
  - Adultos Mayores (60+ años)

### 7. Módulo de Reportes y Estadísticas
- **Filtrado Flexible:** Informes configurables por **Día específico**, **Mes y Año independientes**, o **Año completo**.
- **Tipos de Informe Disponibles:**
  - *Resumen Ejecutivo General:* Consolidado de todos los indicadores clave.
  - *Citas Realizadas:* Total de citas, desglose por estado operativo y distribución por guía asignado.
  - *Visitas por Zona:* Frecuencia de tránsito por cada zona y recinto del parque.
  - *Exoneraciones Otorgadas:* Análisis de exoneraciones reales solicitadas (pago exonerado o donativo) cruzadas contra tipo de gestión pública/privada.
  - *Afluencia de Visitantes:* Volumen total de personas atendidas y pirámide por rangos de edad.
  - *Instituciones Atendidas:* Clasificación por tipo de organización y procedencia geográfica.
- **Visualización y Salida:** Widgets KPI responsivos con protección contra desbordamiento de texto, gráficos comparativos y **exportación a documento PDF en un solo clic** vía `html2pdf.bundle.min.js`.

### 8. Gestión de Personal y Seguridad
- Alta, modificación y control de estatus de usuarios del sistema.
- Cifrado unidireccional de contraseñas mediante `password_hash()` con algoritmo Bcrypt nativo.

### 9. Configuración del Sistema (Módulo App)
- Exclusivo para el rol *Super Administrador*.
- Matriz interactiva en tiempo real para activar o revocar permisos de acceso a cada módulo por rol.

---

## Arquitectura y Patrones de Diseño

El proyecto aplica el patrón arquitectónico **MVC (Modelo - Vista - Controlador)** nativo en PHP Vanilla:

- **Modelos (`models/`):** Representan cada entidad de la base de datos (`Modulo`, `Permiso`, `Personal`, `Cita`, `Institucion`, `Sede`, `Recorrido`, `Ruta`, `Zona`, `Visitante`, `Reporte`).
  - Utilizan PDO con consultas preparadas (`prepare` y `bindParam`) para prevenir inyecciones SQL.
  - No interactúan directamente con las vistas.
- **Controladores (`controllers/`):** Gestionan la lógica de negocio, validaciones de datos en servidor, autenticación y comunicación bidireccional vía JSON.
- **Vistas (`view/`):** Plantillas modulares organizadas en subdirectorios.
  - **Enrutador Dinámico:** Resuelve de forma segura la vista permitida (`resolverVistaPermitida()`) contra la base de datos y la sesión RBAC.
  - **Menú Lateral Dinámico:** La barra lateral (Sidebar) se construye automáticamente a partir de la tabla `modulos`, ordenada alfabéticamente y filtrada por permisos.
  - **Assets Modulares:** Inclusión bajo demanda de estilos (`.css`) y scripts (`.js`) específicos por vista.

---

## Stack Tecnológico y Dependencias Locales

El sistema está diseñado con una política **Zero-CDN**: todas las librerías frontend están descargadas localmente en el repositorio para garantizar portabilidad, funcionamiento en redes intranet/offline y alta disponibilidad.

| Componente | Tecnología | Versión / Detalle |
| :--- | :--- | :--- |
| **Lenguaje Backend** | PHP Vanilla | 8.0+ (orientado a objetos, PDO) |
| **Base de Datos** | MySQL / MariaDB | 5.7+ / 10.4+ (InnoDB, UTF-8mb4) |
| **Diseño / CSS** | Bootstrap & CSS3 Custom | 5.x adaptado con la paleta institucional (`#0D9039`, `#83D476`) |
| **Lógica Frontend** | JavaScript & jQuery | jQuery 3.7.1 |
| **Validaciones Cliente** | jQuery Validate | 1.19.5 con reglas y mensajes en español |
| **Agenda / Calendario** | FullCalendar | 3.10 con localización en español |
| **Gráficos Estadísticos**| Chart.js | 2.9.4 |
| **Lector de Hojas Excel**| SheetJS (xlsx) | 0.18.5 (parseo masivo en navegador) |
| **Generador de PDF** | HTML2PDF | 0.10.1 (renderizado HTML a PDF en cliente) |

---

## Estructura del Proyecto

```text
bararida-gestiones/
├── config/
│   └── Database.php                 # Conexión PDO singleton a MySQL
├── controllers/
│   ├── AuthController.php           # Autenticación e inicio de sesión
│   ├── CitaController.php           # Lógica y endpoints AJAX de citas y agenda
│   ├── DashboardController.php      # RBAC, enrutador dinámico y menú lateral
│   ├── InstitucionController.php    # Registro y control de instituciones y sedes
│   ├── LocationController.php       # Catálogo de estados, municipios y parroquias
│   ├── PermisoController.php        # Matriz de permisos por rol
│   ├── PersonalController.php       # Gestión de cuentas de usuario
│   ├── RecorridoController.php      # Operaciones de zonas y rutas del mapa
│   ├── ReporteController.php        # Procesamiento estadístico de reportes
│   └── VisitanteController.php      # Padrón de visitantes
├── database/
│   ├── schema.sql                   # Estructura de tablas y datos semilla
│   └── localizaciones.sql           # División territorial de Venezuela
├── models/
│   ├── Cita.php
│   ├── Institucion.php
│   ├── Modulo.php                   # Entidad para catálogo dinámico de módulos
│   ├── Permiso.php                  # Matriz de permisos RBAC
│   ├── Personal.php
│   ├── Recorrido.php
│   ├── Reporte.php                  # Consultas analíticas agrupadas
│   ├── Ruta.php
│   ├── Sede.php
│   ├── Visitante.php
│   └── Zona.php
├── view/
│   ├── assets/
│   │   ├── css/                     # Estilos modulares del sistema
│   │   ├── js/                      # Lógica AJAX y librerías cliente
│   │   │   └── validations/         # Reglas desacopladas de jQuery Validate
│   │   └── img/                     # Logotipos y mapa ilustrado del parque
│   ├── dashboard/                   # Subvistas SPA del panel
│   │   ├── app.php                  # Vista de permisos por rol
│   │   ├── citas.php                # Vista de citas y modal wizard
│   │   ├── dashboard_main.php       # Pantalla de inicio y widgets
│   │   ├── instituciones.php        # Directorio de instituciones
│   │   ├── personal.php             # Control de usuarios
│   │   ├── recorridos.php           # Editor interactivo del mapa
│   │   ├── reportes.php             # Módulo de reportes y exportación
│   │   └── visitantes.php           # Padrón de visitantes
│   ├── dashboard.php                # Contenedor principal y sidebar dinámico
│   └── login.php                    # Formulario de inicio de sesión
├── index.php                        # Front Controller y punto de entrada único
└── README.md
```

---

## Requisitos de Entorno

1. **Servidor Web:** Apache 2.4+ (habilitado módulo `mod_rewrite`).
2. **Intérprete PHP:** Versión 8.0 o superior con extensiones activas:
   - `pdo_mysql`
   - `json`
   - `mbstring`
3. **Servidor de Base de Datos:** MySQL 5.7+ o MariaDB 10.4+.

---

## Instalación y Despliegue Local

1. **Clonar el repositorio:**
   Ubica el proyecto en el directorio raíz de tu servidor web local (por ejemplo en XAMPP: `htdocs/bararida-gestiones`, o en macOS/Linux: `/var/www/html/bararida-gestiones`).
   ```bash
   git clone https://github.com/Alejos7ven/bararida-gestiones.git
   ```

2. **Crear e importar la base de datos:**
   Crea una base de datos llamada `bararida_gestiones` e importa los scripts SQL en el siguiente orden:
   ```bash
   mysql -u root -p -e "CREATE DATABASE bararida_gestiones CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p bararida_gestiones < database/schema.sql
   mysql -u root -p bararida_gestiones < database/localizaciones.sql
   ```

3. **Configurar la conexión a la base de datos:**
   Verifica los parámetros de conexión en `config/Database.php` (o variables de entorno en `.env`):
   ```php
   private $host     = "localhost";
   private $db_name  = "bararida_gestiones";
   private $username = "root";
   private $password = "";
   ```

4. **Ejecutar el sistema:**
   Inicia los servicios de Apache y MySQL, luego accede desde tu navegador web:
   ```text
   http://localhost/bararida-gestiones/
   ```

---

## Credenciales de Prueba

El script de instalación inserta cuentas de prueba listas para evaluar los diferentes niveles de acceso:

| Rol de Usuario | Documento | Contraseña | Alcance de Permisos |
| :--- | :--- | :--- | :--- |
| **Super Administrador** | `V-0000` | `admin123` | Acceso irrestricto a todos los módulos y matriz de configuración. |
| **Administrador** | `V-28286982` | *(creable en Personal)* | Acceso a Citas, Instituciones, Recorridos, Visitantes y Reportes. |
| **Guía de Campo** | `V-28356357` | *(creable en Personal)* | Acceso delimitado exclusivamente a Dashboard y Citas asignadas. |


---

*Parque Zoológico y Botánico Bararida — Sistema de Gestión Administrativa.*
