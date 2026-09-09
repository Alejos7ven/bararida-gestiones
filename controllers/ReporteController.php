<?php
/**
 * Controlador de Reportes (ReporteController)
 * Gestiona la extracción, procesamiento y validación de reportes analíticos y estadísticos.
 * Responde siempre en formato JSON estándar a solicitudes AJAX.
 * Acceso restringido exclusivamente a personal autorizado.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header("Content-Type: application/json; charset=UTF-8");
}

// 1. Verificar sesión activa
if (!isset($_SESSION['personal'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Sesión no activa. Por favor inicie sesión nuevamente."
    ]);
    exit;
}

// 2. Incluir dependencias
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../models/Permiso.php";
require_once __DIR__ . "/../models/Reporte.php";

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "No se pudo conectar a la base de datos."
    ]);
    exit;
}

// 3. Verificar permisos de módulo RBAC
$currentUser = $_SESSION['personal'];
$userRolId   = isset($currentUser['rol_id']) ? (int)$currentUser['rol_id'] : 0;
$userRol     = $currentUser['rol'] ?? '';

$permisoModel = new Permiso($db);
$permisos = [];
if ($userRolId > 0) {
    $permisos = $permisoModel->getPermisosPorRol($userRolId);
}
// Si el rol es guía o no tiene permiso explícito para 'reportes'
if ($userRol === 'guia' && empty($permisos['reportes'])) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Acceso denegado. No posee permisos para consultar reportes."
    ]);
    exit;
}

/**
 * Valida parámetros de tiempo y sección, ejecuta el modelo y responde en JSON
 */
if (!function_exists('procesarConsultaReporte')) {
    function procesarConsultaReporte(Reporte $reporteModel) {
        // Parámetros de entrada
        $seccion = isset($_REQUEST['seccion']) ? trim(strtolower($_REQUEST['seccion'])) : 'general';
        $filtroTiempo = isset($_REQUEST['filtro_tiempo']) ? trim(strtolower($_REQUEST['filtro_tiempo'])) : 'mes';
        $estatus = isset($_REQUEST['estatus']) ? trim(strtolower($_REQUEST['estatus'])) : 'todos';

        // Validar sección
        $seccionesValidas = ['general', 'citas', 'zonas', 'exoneraciones', 'visitantes', 'instituciones'];
        if (!in_array($seccion, $seccionesValidas)) {
            http_response_code(422);
            echo json_encode([
                "status" => "error",
                "message" => "Sección de reporte inválida. Secciones válidas: " . implode(', ', $seccionesValidas)
            ]);
            return;
        }

        // Validar filtro de tiempo
        $filtrosValidos = ['dia', 'mes', 'anio'];
        if (!in_array($filtroTiempo, $filtrosValidos)) {
            http_response_code(422);
            echo json_encode([
                "status" => "error",
                "message" => "Filtro de tiempo inválido. Valores aceptados: dia, mes, anio."
            ]);
            return;
        }

        // Resolver rango de fechas y etiqueta humana
        $fechaInicio = '';
        $fechaFin = '';
        $etiquetaPeriodo = '';

        $mesesEs = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        if ($filtroTiempo === 'dia') {
            $fechaDia = isset($_REQUEST['fecha_dia']) ? trim($_REQUEST['fecha_dia']) : date('Y-m-d');
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fechaDia, $matches)) {
                http_response_code(422);
                echo json_encode([
                    "status" => "error",
                    "message" => "Formato de fecha de día inválido. Debe ser YYYY-MM-DD."
                ]);
                return;
            }

            $y = (int)$matches[1];
            $m = (int)$matches[2];
            $d = (int)$matches[3];
            if (!checkdate($m, $d, $y)) {
                http_response_code(422);
                echo json_encode([
                    "status" => "error",
                    "message" => "La fecha especificada para el día no es una fecha válida en el calendario."
                ]);
                return;
            }

            $fechaInicio = $fechaDia;
            $fechaFin = $fechaDia;
            $etiquetaPeriodo = "$d de " . ($mesesEs[$m] ?? $m) . " de $y";

        } else if ($filtroTiempo === 'mes') {
            if (isset($_REQUEST['mes']) && isset($_REQUEST['anio'])) {
                $m = (int)$_REQUEST['mes'];
                $y = (int)$_REQUEST['anio'];
            } else if (!empty($_REQUEST['fecha_mes']) && preg_match('/^(\d{4})-(\d{2})$/', trim($_REQUEST['fecha_mes']), $matches)) {
                $y = (int)$matches[1];
                $m = (int)$matches[2];
            } else {
                $m = isset($_REQUEST['mes']) ? (int)$_REQUEST['mes'] : (int)date('m');
                $y = isset($_REQUEST['anio']) ? (int)$_REQUEST['anio'] : (int)date('Y');
            }

            if ($m < 1 || $m > 12 || $y < 1900 || $y > 2100) {
                http_response_code(422);
                echo json_encode([
                    "status" => "error",
                    "message" => "Mes o año inválidos para el filtro de mes."
                ]);
                return;
            }

            $mPad = str_pad($m, 2, '0', STR_PAD_LEFT);
            $fechaInicio = "$y-$mPad-01";
            $ultimoDia = date('t', strtotime($fechaInicio));
            $fechaFin = "$y-$mPad-$ultimoDia";
            $etiquetaPeriodo = ($mesesEs[$m] ?? $m) . " de $y";

        } else if ($filtroTiempo === 'anio') {
            $anio = isset($_REQUEST['fecha_anio']) ? (int)$_REQUEST['fecha_anio'] : (int)date('Y');
            if ($anio < 1900 || $anio > 2100) {
                http_response_code(422);
                echo json_encode([
                    "status" => "error",
                    "message" => "Año inválido. Debe estar entre 1900 y 2100."
                ]);
                return;
            }

            $fechaInicio = "$anio-01-01";
            $fechaFin = "$anio-12-31";
            $etiquetaPeriodo = "Año $anio";
        }

        // Ejecutar consulta según sección solicitada
        $resultado = [];
        $estadisticasGenerales = $reporteModel->obtenerEstadisticasGenerales($fechaInicio, $fechaFin);

        switch ($seccion) {
            case 'general':
                $resultado = [
                    'resumen' => $estadisticasGenerales,
                    'citas_recientes' => $reporteModel->obtenerReporteCitas($fechaInicio, $fechaFin)['datos'] ?? [],
                    'zonas_destacadas' => $reporteModel->obtenerReporteZonas($fechaInicio, $fechaFin)['datos'] ?? []
                ];
                break;

            case 'citas':
                $resultado = $reporteModel->obtenerReporteCitas($fechaInicio, $fechaFin, $estatus);
                break;

            case 'zonas':
                $resultado = $reporteModel->obtenerReporteZonas($fechaInicio, $fechaFin);
                break;

            case 'exoneraciones':
                $resultado = $reporteModel->obtenerReporteExoneraciones($fechaInicio, $fechaFin);
                break;

            case 'visitantes':
                $resultado = $reporteModel->obtenerReporteVisitantes($fechaInicio, $fechaFin);
                break;

            case 'instituciones':
                $resultado = $reporteModel->obtenerReporteInstituciones($fechaInicio, $fechaFin);
                break;
        }

        // Responder con éxito
        echo json_encode([
            "status" => "success",
            "seccion" => $seccion,
            "periodo" => [
                "tipo" => $filtroTiempo,
                "inicio" => $fechaInicio,
                "fin" => $fechaFin,
                "etiqueta" => $etiquetaPeriodo
            ],
            "kpis_globales" => $estadisticasGenerales,
            "reporte" => $resultado
        ]);
    }
}

// 4. Procesar acción
$action = isset($_GET['action']) ? trim($_GET['action']) : 'consultar';

try {
    $reporteModel = new Reporte($db);

    switch ($action) {
        case 'consultar':
            procesarConsultaReporte($reporteModel);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción no válida solicitada al módulo de reportes."
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
