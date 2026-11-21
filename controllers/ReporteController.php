<?php
/**
 * Controlador de Reportes (ReporteController)
 * Gestiona la extracción, procesamiento y validación de reportes analíticos y estadísticos.
 * Responde siempre en formato JSON estándar a solicitudes AJAX.
 * Acceso restringido exclusivamente a personal autorizado.
 * Respeta el patrón MVC operando mediante el modelo Reporte con setters/getters.
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
require_once __DIR__ . "/../models/Permiso.php";
require_once __DIR__ . "/../models/Reporte.php";

// 3. Verificar permisos de módulo RBAC
$currentUser = $_SESSION['personal'];
$userRolId   = isset($currentUser['rol_id']) ? (int)$currentUser['rol_id'] : 0;
$userRol     = $currentUser['rol'] ?? '';

$permisoModel = new Permiso();
if ($userRolId <= 0 && !empty($userRol)) {
    $permisoModel->setRolNombre($userRol);
    $userRolId = $permisoModel->obtenerRolIdPorNombre() ?? 0;
    if ($userRolId > 0) {
        $_SESSION['personal']['rol_id'] = $userRolId;
    }
}

// Validar permiso dinámico para 'reportes' según la BD
if (!$permisoModel->tieneAccesoModulo($userRolId, 'reportes', $userRol)) {
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
        $tipoCita = isset($_REQUEST['tipo_cita']) ? trim(strtolower($_REQUEST['tipo_cita'])) : (isset($_REQUEST['tipo']) ? trim(strtolower($_REQUEST['tipo'])) : 'todos');

        // Validar tipo de cita
        $tiposCitaValidos = ['todos', 'campamento nocturno', 'corporativo', 'evento', 'institucional', 'otro', 'particular'];
        if (!in_array($tipoCita, $tiposCitaValidos, true)) {
            http_response_code(422);
            echo json_encode([
                "status" => "error",
                "message" => "Tipo de cita no válido para filtrar."
            ]);
            return;
        }

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

        // Asignar parámetros mediante setters en el modelo Reporte
        $reporteModel->setFechaInicio($fechaInicio);
        $reporteModel->setFechaFin($fechaFin);
        $reporteModel->setEstatus($estatus);
        $reporteModel->setTipoCita($tipoCita);
        $reporteModel->setSeccion($seccion);

        // Si es una petición Server-Side de DataTables para la tabla analítica
        if (isset($_REQUEST['draw'])) {
            $draw = (int)$_REQUEST['draw'];
            $start = isset($_REQUEST['start']) && is_numeric($_REQUEST['start']) ? max(0, (int)$_REQUEST['start']) : 0;
            $length = isset($_REQUEST['length']) && is_numeric($_REQUEST['length']) ? (int)$_REQUEST['length'] : 10;
            $search = isset($_REQUEST['search']['value']) ? trim((string)$_REQUEST['search']['value']) : '';

            $columnsBySection = [
                'general' => [
                    0 => 'sub.id',
                    1 => 'sub.fecha_visita',
                    2 => 'sub.institucion_nombre',
                    3 => 'sub.tipo',
                    4 => 'sub.tipo_organizacion',
                    5 => 'sub.recorrido_nombre',
                    6 => 'sub.total_visitantes',
                    7 => 'sub.estatus',
                    8 => 'sub.metodo_pago'
                ],
                'citas' => [
                    0 => 'sub.id',
                    1 => 'sub.fecha_visita',
                    2 => 'sub.institucion_nombre',
                    3 => 'sub.tipo',
                    4 => 'sub.tipo_organizacion',
                    5 => 'sub.recorrido_nombre',
                    6 => 'sub.total_visitantes',
                    7 => 'sub.estatus',
                    8 => 'sub.metodo_pago'
                ],
                'zonas' => [
                    0 => 'sub.zona_nombre',
                    1 => 'sub.total_visitas',
                    2 => 'sub.total_visitantes_zona',
                    3 => 'sub.recorridos_asociados',
                    4 => 'sub.total_visitas'
                ],
                'exoneraciones' => [
                    0 => 'sub.cita_id',
                    1 => 'sub.fecha_visita',
                    2 => 'sub.institucion_nombre',
                    3 => 'sub.tipo_gestion',
                    4 => 'sub.motivo_exoneracion',
                    5 => 'sub.total_visitantes_exonerados',
                    6 => 'sub.estatus'
                ],
                'visitantes' => [
                    0 => 'sub.cedula',
                    1 => 'sub.apellidos',
                    2 => 'sub.fecha_nacimiento',
                    3 => 'sub.edad_estimada',
                    4 => 'sub.grupo_etario',
                    5 => 'sub.fecha_visita',
                    6 => 'sub.institucion_nombre'
                ],
                'instituciones' => [
                    0 => 'sub.rif',
                    1 => 'sub.institucion_nombre',
                    2 => 'sub.tipo_organizacion',
                    3 => 'sub.tipo_gestion',
                    4 => 'sub.total_citas',
                    5 => 'sub.total_visitantes_atendidos',
                    6 => 'sub.ultima_visita'
                ]
            ];

            $defaultColumns = [
                'general'       => 'sub.fecha_visita',
                'citas'         => 'sub.fecha_visita',
                'zonas'         => 'sub.total_visitas',
                'exoneraciones' => 'sub.fecha_visita',
                'visitantes'    => 'sub.fecha_visita',
                'instituciones' => 'sub.total_visitantes_atendidos'
            ];

            $defaultDirs = [
                'general'       => 'ASC',
                'citas'         => 'ASC',
                'zonas'         => 'DESC',
                'exoneraciones' => 'ASC',
                'visitantes'    => 'ASC',
                'instituciones' => 'DESC'
            ];

            $sectionMap = $columnsBySection[$seccion] ?? $columnsBySection['general'];
            $orderColIdx = isset($_REQUEST['order'][0]['column']) ? (int)$_REQUEST['order'][0]['column'] : -1;
            $orderColumn = ($orderColIdx >= 0 && isset($sectionMap[$orderColIdx]))
                ? $sectionMap[$orderColIdx]
                : ($defaultColumns[$seccion] ?? 'sub.id');

            $orderDirRaw = isset($_REQUEST['order'][0]['dir']) ? strtoupper(trim((string)$_REQUEST['order'][0]['dir'])) : ($defaultDirs[$seccion] ?? 'ASC');
            $orderDir = ($orderDirRaw === 'DESC') ? 'DESC' : 'ASC';

            $reporteModel->setDtStart($start);
            $reporteModel->setDtLength($length);
            $reporteModel->setDtSearch($search);
            $reporteModel->setDtOrderColumn($orderColumn);
            $reporteModel->setDtOrderDir($orderDir);

            $tablaPaginada = $reporteModel->obtenerTablaPaginada();

            echo json_encode([
                "status"          => "success",
                "draw"            => $draw,
                "recordsTotal"    => $tablaPaginada['recordsTotal'],
                "recordsFiltered" => $tablaPaginada['recordsFiltered'],
                "data"            => $tablaPaginada['data']
            ]);
            return;
        }

        // Ejecutar consulta según sección solicitada
        $resultado = [];
        $estadisticasGenerales = $reporteModel->obtenerEstadisticasGenerales();

        switch ($seccion) {
            case 'general':
                $resultado = [
                    'resumen' => $estadisticasGenerales,
                    'citas_recientes' => $reporteModel->obtenerReporteCitas()['datos'] ?? [],
                    'zonas_destacadas' => $reporteModel->obtenerReporteZonas()['datos'] ?? []
                ];
                break;

            case 'citas':
                $resultado = $reporteModel->obtenerReporteCitas();
                break;

            case 'zonas':
                $resultado = $reporteModel->obtenerReporteZonas();
                break;

            case 'exoneraciones':
                $resultado = $reporteModel->obtenerReporteExoneraciones();
                break;

            case 'visitantes':
                $resultado = $reporteModel->obtenerReporteVisitantes();
                break;

            case 'instituciones':
                $resultado = $reporteModel->obtenerReporteInstituciones();
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
            "filtros" => [
                "tipo_cita" => $tipoCita,
                "estatus" => $estatus
            ],
            "kpis_globales" => $estadisticasGenerales,
            "reporte" => $resultado
        ]);
    }
}

// 4. Procesar acción
$action = isset($_GET['action']) ? trim($_GET['action']) : 'consultar';

try {
    $reporteModel = new Reporte();

    switch ($action) {
        case 'consultar':
        case 'listar_tabla':
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
