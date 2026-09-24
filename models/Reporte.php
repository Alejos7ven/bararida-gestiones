<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo de Reportes (Reporte)
 * Procesa y extrae la información analítica y estadística de citas, zonas,
 * exoneraciones, visitantes e instituciones en períodos determinados.
 * Hereda de Database para gestionar internamente la conexión.
 */
class Reporte extends Database {
    private ?string $fechaInicio = null;
    private ?string $fechaFin = null;
    private ?string $estatus = null;
    private ?string $tipoOrganizacion = null;
    private ?string $tipoGestion = null;
    private ?string $anio = null;

    public function __construct() {
        parent::__construct();
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getFechaInicio(): ?string { return $this->fechaInicio; }
    public function setFechaInicio(?string $inicio): void { $this->fechaInicio = $inicio ? trim($inicio) : null; }

    public function getFechaFin(): ?string { return $this->fechaFin; }
    public function setFechaFin(?string $fin): void { $this->fechaFin = $fin ? trim($fin) : null; }

    public function getEstatus(): ?string { return $this->estatus; }
    public function setEstatus(?string $estatus): void { $this->estatus = $estatus ? trim($estatus) : null; }

    public function getTipoOrganizacion(): ?string { return $this->tipoOrganizacion; }
    public function setTipoOrganizacion(?string $tipo): void { $this->tipoOrganizacion = $tipo ? trim($tipo) : null; }

    public function getTipoGestion(): ?string { return $this->tipoGestion; }
    public function setTipoGestion(?string $tipo): void { $this->tipoGestion = $tipo ? trim($tipo) : null; }

    public function getAnio(): ?string { return $this->anio; }
    public function setAnio(?string $anio): void { $this->anio = $anio ? trim($anio) : null; }

    // =========================================================================
    // MÉTODOS DE CONSULTA Y ANÁLISIS
    // =========================================================================

    /**
     * Obtiene el resumen ejecutivo general para el período especificado
     */
    public function obtenerEstadisticasGenerales(?string $fechaInicio = null, ?string $fechaFin = null): array {
        if ($fechaInicio !== null) $this->fechaInicio = $fechaInicio;
        if ($fechaFin !== null) $this->fechaFin = $fechaFin;

        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            // 1. Estadísticas de citas
            $sqlCitas = "SELECT 
                            COUNT(*) AS total_citas,
                            SUM(CASE WHEN estatus = 'aprobado' THEN 1 ELSE 0 END) AS citas_aprobadas,
                            SUM(CASE WHEN estatus = 'completado' THEN 1 ELSE 0 END) AS citas_completadas,
                            SUM(CASE WHEN estatus = 'pendiente' THEN 1 ELSE 0 END) AS citas_pendientes,
                            SUM(CASE WHEN estatus = 'cancelado' THEN 1 ELSE 0 END) AS citas_canceladas
                         FROM citas 
                         WHERE fecha_visita BETWEEN :inicio AND :fin AND activo = 1";
            $stmtCitas = $this->db->prepare($sqlCitas);
            $stmtCitas->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmtCitas->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmtCitas->execute();
            $statsCitas = $stmtCitas->fetch(PDO::FETCH_ASSOC) ?: [
                'total_citas' => 0, 'citas_aprobadas' => 0, 'citas_completadas' => 0,
                'citas_pendientes' => 0, 'citas_canceladas' => 0
            ];

            // 2. Total de visitantes atendidos
            $sqlVisitantes = "SELECT COUNT(ac.id) AS total_visitantes
                              FROM asistencia_cita ac
                              INNER JOIN citas c ON ac.cita_id = c.id
                              WHERE c.fecha_visita BETWEEN :inicio AND :fin AND c.activo = 1";
            $stmtVisitantes = $this->db->prepare($sqlVisitantes);
            $stmtVisitantes->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmtVisitantes->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmtVisitantes->execute();
            $totalVisitantes = (int)$stmtVisitantes->fetchColumn();

            // 3. Instituciones atendidas únicas
            $sqlInst = "SELECT COUNT(DISTINCT c.institucion_id) AS total_instituciones
                        FROM citas c
                        WHERE c.fecha_visita BETWEEN :inicio AND :fin AND c.activo = 1";
            $stmtInst = $this->db->prepare($sqlInst);
            $stmtInst->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmtInst->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmtInst->execute();
            $totalInstituciones = (int)$stmtInst->fetchColumn();

            // 4. Total de visitas a zonas realizadas en recorridos
            $sqlZonas = "SELECT 
                            COUNT(ru.id) AS total_visitas_zonas,
                            COUNT(DISTINCT ru.zona_id) AS zonas_distintas_visitadas
                         FROM citas c
                         INNER JOIN recorrido r ON c.recorrido_id = r.id
                         INNER JOIN rutas ru ON r.id = ru.recorrido_id
                         WHERE c.fecha_visita BETWEEN :inicio AND :fin AND c.activo = 1";
            $stmtZonas = $this->db->prepare($sqlZonas);
            $stmtZonas->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmtZonas->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmtZonas->execute();
            $statsZonas = $stmtZonas->fetch(PDO::FETCH_ASSOC) ?: ['total_visitas_zonas' => 0, 'zonas_distintas_visitadas' => 0];

            // 5. Exoneraciones
            $sqlExon = "SELECT 
                            COUNT(DISTINCT c.id) AS total_citas_exoneradas,
                            COUNT(DISTINCT c.institucion_id) AS total_instituciones_exoneradas,
                            COUNT(ac.id) AS total_visitantes_exonerados
                        FROM citas c
                        INNER JOIN instituciones i ON c.institucion_id = i.id
                        LEFT JOIN asistencia_cita ac ON c.id = ac.cita_id
                        WHERE c.fecha_visita BETWEEN :inicio AND :fin
                          AND c.activo = 1
                          AND (c.pago_estado = 'exonerado' 
                               OR LOWER(c.metodo_pago) LIKE '%exonera%' 
                               OR LOWER(c.metodo_pago) LIKE '%exen%')";
            $stmtExon = $this->db->prepare($sqlExon);
            $stmtExon->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmtExon->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmtExon->execute();
            $statsExon = $stmtExon->fetch(PDO::FETCH_ASSOC) ?: [
                'total_citas_exoneradas' => 0,
                'total_instituciones_exoneradas' => 0,
                'total_visitantes_exonerados' => 0
            ];

            return [
                'periodo' => [
                    'inicio' => $this->fechaInicio,
                    'fin' => $this->fechaFin
                ],
                'citas' => [
                    'total' => (int)$statsCitas['total_citas'],
                    'aprobadas' => (int)$statsCitas['citas_aprobadas'],
                    'completadas' => (int)$statsCitas['citas_completadas'],
                    'pendientes' => (int)$statsCitas['citas_pendientes'],
                    'canceladas' => (int)$statsCitas['citas_canceladas'],
                ],
                'visitantes' => [
                    'total' => $totalVisitantes,
                    'promedio_por_cita' => $statsCitas['total_citas'] > 0 ? round($totalVisitantes / $statsCitas['total_citas'], 1) : 0
                ],
                'instituciones' => [
                    'total' => $totalInstituciones
                ],
                'zonas' => [
                    'total_visitas' => (int)$statsZonas['total_visitas_zonas'],
                    'zonas_distintas' => (int)$statsZonas['zonas_distintas_visitadas']
                ],
                'exoneraciones' => [
                    'citas' => (int)$statsExon['total_citas_exoneradas'],
                    'instituciones' => (int)$statsExon['total_instituciones_exoneradas'],
                    'visitantes' => (int)$statsExon['total_visitantes_exonerados']
                ]
            ];
        } catch (PDOException $e) {
            error_log("Error en Reporte::obtenerEstadisticasGenerales: " . $e->getMessage());
            throw new Exception("Error al procesar el resumen general de reportes.");
        }
    }

    /**
     * Reporte detallado de citas realizadas en el período
     */
    public function obtenerReporteCitas(?string $fechaInicio = null, ?string $fechaFin = null, ?string $estatus = null): array {
        if ($fechaInicio !== null) $this->fechaInicio = $fechaInicio;
        if ($fechaFin !== null) $this->fechaFin = $fechaFin;
        if ($estatus !== null) $this->estatus = $estatus;

        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "SELECT 
                        c.id,
                        c.fecha_solicitud,
                        c.fecha_visita,
                        c.hora_inicio,
                        c.estatus,
                        c.metodo_pago,
                        c.pago_estado,
                        e.nombre_completo AS institucion_nombre,
                        e.tipo_documento,
                        e.documento AS institucion_rif,
                        i.tipo_organizacion,
                        i.tipo_gestion,
                        r.nombre AS recorrido_nombre,
                        s.detalles AS sede_detalles,
                        par.parroquia AS sede_parroquia,
                        mun.municipio AS sede_municipio,
                        est.estado AS sede_estado,
                        COUNT(ac.id) AS total_visitantes
                      FROM citas c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      LEFT JOIN sedes s ON c.sede_id = s.id
                      LEFT JOIN parroquias par ON s.parroquia_id = par.id_parroquia
                      LEFT JOIN municipios mun ON par.id_municipio = mun.id_municipio
                      LEFT JOIN estados est ON mun.id_estado = est.id_estado
                      LEFT JOIN recorrido r ON c.recorrido_id = r.id
                      LEFT JOIN asistencia_cita ac ON c.id = ac.cita_id
                      WHERE c.fecha_visita BETWEEN :inicio AND :fin AND c.activo = 1";

            if (!empty($this->estatus) && $this->estatus !== 'todos') {
                $query .= " AND c.estatus = :estatus";
            }

            $query .= " GROUP BY c.id ORDER BY c.fecha_visita ASC, c.hora_inicio ASC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);

            if (!empty($this->estatus) && $this->estatus !== 'todos') {
                $stmt->bindParam(":estatus", $this->estatus, PDO::PARAM_STR);
            }

            $stmt->execute();
            $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular KPIs de citas
            $totalCitas = count($citas);
            $totalVisitantes = 0;
            $estatusCounts = ['aprobado' => 0, 'completado' => 0, 'pendiente' => 0, 'cancelado' => 0];

            foreach ($citas as $c) {
                $totalVisitantes += (int)$c['total_visitantes'];
                $st = strtolower($c['estatus']);
                if (isset($estatusCounts[$st])) {
                    $estatusCounts[$st]++;
                }
            }

            return [
                'resumen' => [
                    'total_citas' => $totalCitas,
                    'total_visitantes' => $totalVisitantes,
                    'promedio_visitantes' => $totalCitas > 0 ? round($totalVisitantes / $totalCitas, 1) : 0,
                    'estatus' => $estatusCounts
                ],
                'datos' => $citas
            ];
        } catch (PDOException $e) {
            error_log("Error en Reporte::obtenerReporteCitas: " . $e->getMessage());
            throw new Exception("Error al generar el reporte de citas realizadas.");
        }
    }

    /**
     * Reporte de visitas por zona durante el período establecido
     */
    public function obtenerReporteZonas(?string $fechaInicio = null, ?string $fechaFin = null): array {
        if ($fechaInicio !== null) $this->fechaInicio = $fechaInicio;
        if ($fechaFin !== null) $this->fechaFin = $fechaFin;

        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "SELECT 
                        z.id AS zona_id,
                        z.nombre AS zona_nombre,
                        COUNT(DISTINCT c.id) AS total_visitas,
                        COALESCE(SUM(asistentes.total_asistentes), 0) AS total_visitantes_zona,
                        GROUP_CONCAT(DISTINCT r.nombre SEPARATOR ', ') AS recorridos_asociados
                      FROM zonas z
                      INNER JOIN rutas ru ON z.id = ru.zona_id
                      INNER JOIN recorrido r ON ru.recorrido_id = r.id
                      INNER JOIN citas c ON r.id = c.recorrido_id
                      LEFT JOIN (
                          SELECT cita_id, COUNT(*) AS total_asistentes
                          FROM asistencia_cita
                          GROUP BY cita_id
                      ) asistentes ON c.id = asistentes.cita_id
                      WHERE c.fecha_visita BETWEEN :inicio AND :fin AND c.activo = 1
                      GROUP BY z.id, z.nombre
                      ORDER BY total_visitas DESC, total_visitantes_zona DESC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmt->execute();
            $zonasVisitadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Zonas no visitadas en el período para reporte completo
            $queryNoVisitadas = "SELECT z.id AS zona_id, z.nombre AS zona_nombre, 0 AS total_visitas, 0 AS total_visitantes_zona
                                 FROM zonas z
                                 WHERE z.activo = 1 AND z.id NOT IN (
                                     SELECT DISTINCT ru2.zona_id 
                                     FROM citas c2 
                                     INNER JOIN rutas ru2 ON c2.recorrido_id = ru2.recorrido_id 
                                     WHERE c2.fecha_visita BETWEEN :inicio AND :fin AND c2.activo = 1
                                 )
                                 ORDER BY z.nombre ASC";
            $stmtNo = $this->db->prepare($queryNoVisitadas);
            $stmtNo->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmtNo->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmtNo->execute();
            $zonasNoVisitadas = $stmtNo->fetchAll(PDO::FETCH_ASSOC);

            $totalZonasVisitadasSum = 0;
            $totalVisitantesZonasSum = 0;
            foreach ($zonasVisitadas as $zv) {
                $totalZonasVisitadasSum += (int)$zv['total_visitas'];
                $totalVisitantesZonasSum += (int)$zv['total_visitantes_zona'];
            }

            return [
                'resumen' => [
                    'total_visitas_acumuladas' => $totalZonasVisitadasSum,
                    'zonas_distintas_visitadas' => count($zonasVisitadas),
                    'total_zonas_catalogo' => count($zonasVisitadas) + count($zonasNoVisitadas),
                    'zona_mas_popular' => !empty($zonasVisitadas) ? $zonasVisitadas[0]['zona_nombre'] : 'Ninguna',
                    'zona_mas_popular_visitas' => !empty($zonasVisitadas) ? (int)$zonasVisitadas[0]['total_visitas'] : 0
                ],
                'datos' => $zonasVisitadas,
                'no_visitadas' => $zonasNoVisitadas
            ];
        } catch (PDOException $e) {
            error_log("Error en Reporte::obtenerReporteZonas: " . $e->getMessage());
            throw new Exception("Error al calcular el reporte de visitas por zona.");
        }
    }

    /**
     * Reporte de exoneraciones en el período
     */
    public function obtenerReporteExoneraciones(?string $fechaInicio = null, ?string $fechaFin = null): array {
        if ($fechaInicio !== null) $this->fechaInicio = $fechaInicio;
        if ($fechaFin !== null) $this->fechaFin = $fechaFin;

        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "SELECT 
                        c.id AS cita_id,
                        c.fecha_visita,
                        c.estatus,
                        c.metodo_pago,
                        c.pago_estado,
                        e.tipo_documento,
                        e.documento AS institucion_rif,
                        e.nombre_completo AS institucion_nombre,
                        i.tipo_organizacion,
                        i.tipo_gestion,
                        CASE 
                            WHEN c.pago_estado = 'exonerado' AND (LOWER(c.metodo_pago) LIKE '%exonera%' OR LOWER(c.metodo_pago) LIKE '%exen%') THEN c.metodo_pago
                            WHEN c.pago_estado = 'exonerado' THEN 'Estado Exonerado'
                            WHEN LOWER(c.metodo_pago) LIKE '%exonera%' OR LOWER(c.metodo_pago) LIKE '%exen%' THEN c.metodo_pago
                            ELSE 'Exoneración Autorizada'
                        END AS motivo_exoneracion,
                        COUNT(ac.id) AS total_visitantes_exonerados
                      FROM citas c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      LEFT JOIN asistencia_cita ac ON c.id = ac.cita_id
                      WHERE c.fecha_visita BETWEEN :inicio AND :fin
                        AND c.activo = 1
                        AND (c.pago_estado = 'exonerado' 
                             OR LOWER(c.metodo_pago) LIKE '%exonera%' 
                             OR LOWER(c.metodo_pago) LIKE '%exen%')
                      GROUP BY c.id
                      ORDER BY c.fecha_visita ASC, e.nombre_completo ASC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmt->execute();
            $exoneraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalInstitucionesSet = [];
            $totalVisitantesExon = 0;
            $porTipoGestion = ['publica' => 0, 'privada' => 0];
            $porTipoOrganizacion = [];

            foreach ($exoneraciones as $ex) {
                $totalInstitucionesSet[$ex['institucion_rif']] = true;
                $visitantesCita = (int)$ex['total_visitantes_exonerados'];
                $totalVisitantesExon += $visitantesCita;

                $gestion = strtolower($ex['tipo_gestion']);
                if (isset($porTipoGestion[$gestion])) {
                    $porTipoGestion[$gestion] += $visitantesCita;
                } else {
                    $porTipoGestion[$gestion] = $visitantesCita;
                }

                $org = !empty($ex['tipo_organizacion']) ? ucfirst($ex['tipo_organizacion']) : 'Otras';
                $porTipoOrganizacion[$org] = ($porTipoOrganizacion[$org] ?? 0) + $visitantesCita;
            }

            return [
                'resumen' => [
                    'total_citas_exoneradas' => count($exoneraciones),
                    'total_instituciones_exoneradas' => count($totalInstitucionesSet),
                    'total_visitantes_exonerados' => $totalVisitantesExon,
                    'distribucion_gestion' => $porTipoGestion,
                    'distribucion_organizacion' => $porTipoOrganizacion
                ],
                'datos' => $exoneraciones
            ];
        } catch (PDOException $e) {
            error_log("Error en Reporte::obtenerReporteExoneraciones: " . $e->getMessage());
            throw new Exception("Error al calcular el reporte de exoneraciones.");
        }
    }

    /**
     * Reporte de cantidad de visitantes recibidos en el período
     */
    public function obtenerReporteVisitantes(?string $fechaInicio = null, ?string $fechaFin = null): array {
        if ($fechaInicio !== null) $this->fechaInicio = $fechaInicio;
        if ($fechaFin !== null) $this->fechaFin = $fechaFin;

        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "SELECT 
                        v.id AS visitante_id,
                        v.cedula,
                        v.nombres,
                        v.apellidos,
                        v.fecha_nacimiento,
                        c.fecha_visita,
                        c.id AS cita_id,
                        e.nombre_completo AS institucion_nombre,
                        CASE 
                            WHEN v.fecha_nacimiento IS NOT NULL AND v.fecha_nacimiento > '1900-01-01'
                            THEN TIMESTAMPDIFF(YEAR, v.fecha_nacimiento, c.fecha_visita)
                            ELSE NULL 
                        END AS edad_estimada,
                        CASE 
                            WHEN v.fecha_nacimiento IS NULL OR v.fecha_nacimiento <= '1900-01-01' THEN 'Sin especificar'
                            WHEN TIMESTAMPDIFF(YEAR, v.fecha_nacimiento, c.fecha_visita) < 13 THEN 'Niño (0-12)'
                            WHEN TIMESTAMPDIFF(YEAR, v.fecha_nacimiento, c.fecha_visita) BETWEEN 13 AND 17 THEN 'Adolescente (13-17)'
                            WHEN TIMESTAMPDIFF(YEAR, v.fecha_nacimiento, c.fecha_visita) BETWEEN 18 AND 59 THEN 'Adulto (18-59)'
                            ELSE 'Adulto Mayor (60+)'
                        END AS grupo_etario
                      FROM asistencia_cita ac
                      INNER JOIN citas c ON ac.cita_id = c.id
                      INNER JOIN visitantes v ON ac.visitante_id = v.id
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      WHERE c.fecha_visita BETWEEN :inicio AND :fin AND c.activo = 1
                      ORDER BY c.fecha_visita ASC, v.apellidos ASC, v.nombres ASC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmt->execute();
            $visitantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Distribución demográfica
            $gruposEtarios = [
                'Niño (0-12)' => 0,
                'Adolescente (13-17)' => 0,
                'Adulto (18-59)' => 0,
                'Adulto Mayor (60+)' => 0,
                'Sin especificar' => 0
            ];

            $totalEdades = 0;
            $conEdadValida = 0;

            foreach ($visitantes as $v) {
                $grupo = $v['grupo_etario'];
                if (isset($gruposEtarios[$grupo])) {
                    $gruposEtarios[$grupo]++;
                } else {
                    $gruposEtarios['Sin especificar']++;
                }

                if ($v['edad_estimada'] !== null && $v['edad_estimada'] >= 0 && $v['edad_estimada'] <= 120) {
                    $totalEdades += (int)$v['edad_estimada'];
                    $conEdadValida++;
                }
            }

            return [
                'resumen' => [
                    'total_visitantes' => count($visitantes),
                    'promedio_edad' => $conEdadValida > 0 ? round($totalEdades / $conEdadValida, 1) : 0,
                    'grupos_etarios' => $gruposEtarios
                ],
                'datos' => $visitantes
            ];
        } catch (PDOException $e) {
            error_log("Error en Reporte::obtenerReporteVisitantes: " . $e->getMessage());
            throw new Exception("Error al generar el reporte de visitantes.");
        }
    }

    /**
     * Reporte de instituciones atendidas en el período
     */
    public function obtenerReporteInstituciones(?string $fechaInicio = null, ?string $fechaFin = null): array {
        if ($fechaInicio !== null) $this->fechaInicio = $fechaInicio;
        if ($fechaFin !== null) $this->fechaFin = $fechaFin;

        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "SELECT 
                        i.id AS institucion_id,
                        e.tipo_documento,
                        e.documento AS rif,
                        e.nombre_completo AS institucion_nombre,
                        i.tipo_organizacion,
                        i.tipo_gestion,
                        COUNT(DISTINCT c.id) AS total_citas,
                        COUNT(ac.id) AS total_visitantes_atendidos,
                        MAX(c.fecha_visita) AS ultima_visita,
                        GROUP_CONCAT(DISTINCT c.estatus SEPARATOR ', ') AS estatus_citas
                      FROM instituciones i
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      INNER JOIN citas c ON i.id = c.institucion_id
                      LEFT JOIN asistencia_cita ac ON c.id = ac.cita_id
                      WHERE c.fecha_visita BETWEEN :inicio AND :fin AND c.activo = 1
                      GROUP BY i.id, e.tipo_documento, e.documento, e.nombre_completo, i.tipo_organizacion, i.tipo_gestion
                      ORDER BY total_visitantes_atendidos DESC, total_citas DESC, e.nombre_completo ASC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":inicio", $this->fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fin", $this->fechaFin, PDO::PARAM_STR);
            $stmt->execute();
            $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Resumen de distribución
            $porGestion = ['publica' => 0, 'privada' => 0];
            $porOrganizacion = [];
            $totalCitasSum = 0;
            $totalVisitantesSum = 0;

            foreach ($instituciones as $inst) {
                $totalCitasSum += (int)$inst['total_citas'];
                $totalVisitantesSum += (int)$inst['total_visitantes_atendidos'];

                $ges = strtolower($inst['tipo_gestion']);
                if (isset($porGestion[$ges])) {
                    $porGestion[$ges]++;
                } else {
                    $porGestion[$ges] = 1;
                }

                $org = !empty($inst['tipo_organizacion']) ? ucfirst($inst['tipo_organizacion']) : 'Otras';
                $porOrganizacion[$org] = ($porOrganizacion[$org] ?? 0) + 1;
            }

            return [
                'resumen' => [
                    'total_instituciones' => count($instituciones),
                    'total_citas_generadas' => $totalCitasSum,
                    'total_visitantes_aportados' => $totalVisitantesSum,
                    'distribucion_gestion' => $porGestion,
                    'distribucion_organizacion' => $porOrganizacion
                ],
                'datos' => $instituciones
            ];
        } catch (PDOException $e) {
            error_log("Error en Reporte::obtenerReporteInstituciones: " . $e->getMessage());
            throw new Exception("Error al calcular el reporte de instituciones atendidas.");
        }
    }
}
