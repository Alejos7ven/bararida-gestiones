<?php
/**
 * Modelo de Citas (Cita)
 * Procesa y gestiona la lógica de persistencia de las citas y sus sedes asociadas en la base de datos.
 */
class Cita {
    private $db;
    private $table_name = "citas";

    // Propiedades de la entidad
    private $id;
    private $institucion_id;
    private $sede_id;
    private $recorrido_id;
    private $fecha_solicitud;
    private $fecha_visita;
    private $hora_inicio;
    private $estatus; // 'pendiente', 'aprobado', 'completado', 'cancelado'
    private $metodo_pago;
    private $pago_estado; // 'pendiente', 'pagado'

    public function __construct($db = null) {
        $this->db = $db;
        $this->fecha_solicitud = date('Y-m-d');
        $this->estatus = 'pendiente';
        $this->pago_estado = 'pendiente';
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getId() { return $this->id; }
    public function setId($id) { $this->id = $id; }

    public function getInstitucionId() { return $this->institucion_id; }
    public function setInstitucionId($inst_id) { $this->institucion_id = $inst_id; }

    public function getSedeId() { return $this->sede_id; }
    public function setSedeId($sede_id) { $this->sede_id = $sede_id; }

    public function getRecorridoId() { return $this->recorrido_id; }
    public function setRecorridoId($rec_id) { $this->recorrido_id = $rec_id; }

    public function getFechaSolicitud() { return $this->fecha_solicitud; }
    public function setFechaSolicitud($fecha) { $this->fecha_solicitud = $fecha; }

    public function getFechaVisita() { return $this->fecha_visita; }
    public function setFechaVisita($fecha) { $this->fecha_visita = $fecha; }

    public function getHoraInicio() { return $this->hora_inicio; }
    public function setHoraInicio($hora) { $this->hora_inicio = $hora; }

    public function getEstatus() { return $this->estatus; }
    public function setEstatus($estatus) { $this->estatus = $estatus; }

    public function getMetodoPago() { return $this->metodo_pago; }
    public function setMetodoPago($metodo) { $this->metodo_pago = $metodo; }

    public function getPagoEstado() { return $this->pago_estado; }
    public function setPagoEstado($estado) { $this->pago_estado = $estado; }

    // =========================================================================
    // OPERACIONES DE PERSISTENCIA Y CONSULTA
    // =========================================================================

    /**
     * Registra una nueva cita en la base de datos
     */
    public function crear() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      (institucion_id, sede_id, recorrido_id, fecha_solicitud, fecha_visita, hora_inicio, estatus, metodo_pago, pago_estado) 
                      VALUES (:institucion_id, :sede_id, :recorrido_id, :fecha_solicitud, :fecha_visita, :hora_inicio, :estatus, :metodo_pago, :pago_estado)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":institucion_id", $this->institucion_id, PDO::PARAM_INT);
            $stmt->bindParam(":sede_id", $this->sede_id, $this->sede_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(":recorrido_id", $this->recorrido_id, $this->recorrido_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(":fecha_solicitud", $this->fecha_solicitud, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_visita", $this->fecha_visita, PDO::PARAM_STR);
            $stmt->bindParam(":hora_inicio", $this->hora_inicio, PDO::PARAM_STR);
            $stmt->bindParam(":estatus", $this->estatus, PDO::PARAM_STR);
            $stmt->bindParam(":metodo_pago", $this->metodo_pago, PDO::PARAM_STR);
            $stmt->bindParam(":pago_estado", $this->pago_estado, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $this->setId($this->db->lastInsertId());
                return true;
            }
        } catch (PDOException $e) {
            error_log("Error al crear cita: " . $e->getMessage());
            throw new Exception("Error al guardar la cita en la base de datos.");
        }
        return false;
    }

    /**
     * Actualiza una cita existente
     */
    public function actualizar() {
        if (!$this->db || empty($this->id)) {
            throw new Exception("Conexión de base de datos o ID de cita no configurados.");
        }

        try {
            $query = "UPDATE " . $this->table_name . " SET 
                      institucion_id = :institucion_id,
                      sede_id = :sede_id,
                      recorrido_id = :recorrido_id, 
                      fecha_visita = :fecha_visita, 
                      hora_inicio = :hora_inicio, 
                      estatus = :estatus, 
                      metodo_pago = :metodo_pago, 
                      pago_estado = :pago_estado 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":institucion_id", $this->institucion_id, PDO::PARAM_INT);
            $stmt->bindParam(":sede_id", $this->sede_id, $this->sede_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(":recorrido_id", $this->recorrido_id, $this->recorrido_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(":fecha_visita", $this->fecha_visita, PDO::PARAM_STR);
            $stmt->bindParam(":hora_inicio", $this->hora_inicio, PDO::PARAM_STR);
            $stmt->bindParam(":estatus", $this->estatus, PDO::PARAM_STR);
            $stmt->bindParam(":metodo_pago", $this->metodo_pago, PDO::PARAM_STR);
            $stmt->bindParam(":pago_estado", $this->pago_estado, PDO::PARAM_STR);
            $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al actualizar cita: " . $e->getMessage());
            throw new Exception("Error al modificar la cita en la base de datos.");
        }
    }

    /**
     * Elimina una cita de forma segura junto a sus vínculos de visitantes
     */
    public function eliminar($id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $cleanId = (int)$id;

        try {
            $this->db->beginTransaction();

            // 1. Eliminar asistencia vinculada
            $queryAsist = "DELETE FROM asistencia_cita WHERE cita_id = :cita_id";
            $stmtAsist = $this->db->prepare($queryAsist);
            $stmtAsist->bindParam(":cita_id", $cleanId, PDO::PARAM_INT);
            $stmtAsist->execute();

            // 2. Eliminar asignación de guías
            $queryGuia = "DELETE FROM guia_asignado WHERE cita_id = :cita_id";
            $stmtGuia = $this->db->prepare($queryGuia);
            $stmtGuia->bindParam(":cita_id", $cleanId, PDO::PARAM_INT);
            $stmtGuia->execute();

            // 3. Eliminar la cita
            $queryCita = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmtCita = $this->db->prepare($queryCita);
            $stmtCita->bindParam(":id", $cleanId, PDO::PARAM_INT);
            $stmtCita->execute();

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error al eliminar cita: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lista todas las citas con joins de instituciones, entidades, sedes y recorridos
     */
    public function listarTodas() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "SELECT c.id, c.institucion_id, c.sede_id, c.recorrido_id, c.fecha_solicitud, c.fecha_visita, c.hora_inicio, c.estatus, c.metodo_pago, c.pago_estado,
                             e.tipo_documento, e.documento AS RIF, e.nombre_completo AS razon_social, e.nombre_completo,
                             r.nombre AS recorrido_nombre,
                             s.detalles AS sede_detalles, ciu.ciudad AS sede_ciudad, par.parroquia AS sede_parroquia,
                             mun.municipio AS sede_municipio, est.estado AS sede_estado,
                             (SELECT COUNT(*) FROM asistencia_cita ac WHERE ac.cita_id = c.id) AS total_visitantes
                      FROM " . $this->table_name . " c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      LEFT JOIN sedes s ON c.sede_id = s.id
                      LEFT JOIN ciudades ciu ON s.ciudad_id = ciu.id_ciudad
                      LEFT JOIN parroquias par ON s.parroquia_id = par.id_parroquia
                      LEFT JOIN municipios mun ON par.id_municipio = mun.id_municipio
                      LEFT JOIN estados est ON mun.id_estado = est.id_estado
                      LEFT JOIN recorrido r ON c.recorrido_id = r.id
                      ORDER BY c.id DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al listar citas: " . $e->getMessage());
            throw new Exception("Error al consultar el listado de citas.");
        }
    }

    /**
     * Obtiene los detalles de una cita por su ID
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) return null;

        $cleanId = (int)$id;
        if ($cleanId <= 0) return null;

        try {
            $query = "SELECT c.id, c.institucion_id, c.sede_id, c.recorrido_id, c.fecha_solicitud, c.fecha_visita, c.hora_inicio, c.estatus, c.metodo_pago, c.pago_estado,
                             e.tipo_documento, e.documento AS RIF, e.nombre_completo AS razon_social, e.nombre_completo,
                             r.nombre AS recorrido_nombre,
                             s.detalles AS sede_detalles, s.ciudad_id AS sede_ciudad_id, s.parroquia_id AS sede_parroquia_id,
                             ciu.ciudad AS sede_ciudad, par.parroquia AS sede_parroquia,
                             mun.id_municipio AS sede_id_municipio, mun.municipio AS sede_municipio,
                             est.id_estado AS sede_id_estado, est.estado AS sede_estado
                      FROM citas c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      LEFT JOIN sedes s ON c.sede_id = s.id
                      LEFT JOIN ciudades ciu ON s.ciudad_id = ciu.id_ciudad
                      LEFT JOIN parroquias par ON s.parroquia_id = par.id_parroquia
                      LEFT JOIN municipios mun ON par.id_municipio = mun.id_municipio
                      LEFT JOIN estados est ON mun.id_estado = est.id_estado
                      LEFT JOIN recorrido r ON c.recorrido_id = r.id
                      WHERE c.id = :id LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $cleanId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : null;
        } catch (PDOException $e) {
            error_log("Error al obtener cita por ID: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Obtiene el listado de fechas de visita con la cantidad de instituciones registradas
     */
    public static function obtenerCronogramaCalendario($db) {
        if (!$db) return [];
        try {
            $query = "SELECT c.fecha_visita, COUNT(DISTINCT c.institucion_id) AS total_instituciones,
                             GROUP_CONCAT(DISTINCT e.nombre_completo SEPARATOR '||') AS instituciones_nombres
                      FROM citas c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      GROUP BY c.fecha_visita 
                      ORDER BY c.fecha_visita ASC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Cita::obtenerCronogramaCalendario(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la cantidad total de visitantes (del listado de asistencia) agrupado por día de visita
     */
    public static function obtenerEstadisticasAfluencia($db) {
        if (!$db) return [];
        try {
            $query = "SELECT c.fecha_visita, COUNT(ac.visitante_id) AS total_visitantes
                      FROM citas c
                      LEFT JOIN asistencia_cita ac ON c.id = ac.cita_id
                      GROUP BY c.fecha_visita
                      ORDER BY c.fecha_visita ASC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Cita::obtenerEstadisticasAfluencia(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene las citas programadas para el día de hoy.
    /**
     * Obtiene las citas programadas para el día de hoy con control de recorridos y guías.
     */
    public static function obtenerCitasHoy(PDO $db, string $rol, ?int $personalId = null): array {
        try {
            // AUTO-TERMINACIÓN EN 2 HORAS (120 MINUTOS):
            // Cualquier recorrido que haya iniciado hace 2 horas o más, o en días anteriores,
            // se finaliza automáticamente como 'completado'.
            $db->exec("UPDATE citas 
                       SET estatus = 'completado' 
                       WHERE estatus = 'en_recorrido' 
                         AND (
                           fecha_visita < CURDATE() 
                           OR (fecha_visita = CURDATE() AND TIMESTAMPDIFF(MINUTE, hora_inicio, CURTIME()) >= 120)
                         )");

            $loggedId = ($personalId !== null) ? (int)$personalId : 0;

            $base = "SELECT c.id, c.hora_inicio, c.estatus, c.pago_estado, c.fecha_visita,
                            e.nombre_completo AS razon_social, e.tipo_documento, e.documento AS rif,
                            r.nombre AS recorrido_nombre,
                            s.detalles AS sede_detalles, ciu.ciudad AS sede_ciudad,
                            (SELECT COUNT(*) FROM asistencia_cita ac WHERE ac.cita_id = c.id) AS total_visitantes,
                            GROUP_CONCAT(
                                e2.nombre_completo
                                ORDER BY ga.rol_guia ASC SEPARATOR ', '
                            ) AS guias_asignados,
                            CASE 
                                WHEN :logged_personal_id > 0 AND EXISTS (
                                    SELECT 1 FROM guia_asignado ga_me 
                                    WHERE ga_me.cita_id = c.id AND ga_me.personal_id = :logged_personal_id2
                                ) THEN 1 
                                ELSE 0 
                            END AS es_mi_asignacion,
                            CASE 
                                WHEN c.estatus = 'en_recorrido' THEN TIMESTAMPDIFF(SECOND, CONCAT(c.fecha_visita, ' ', c.hora_inicio), NOW())
                                ELSE 0 
                            END AS segundos_transcurridos,
                            CASE 
                                WHEN c.estatus = 'en_recorrido' THEN GREATEST(0, 7200 - TIMESTAMPDIFF(SECOND, CONCAT(c.fecha_visita, ' ', c.hora_inicio), NOW()))
                                ELSE 0 
                            END AS segundos_restantes
                     FROM citas c
                     INNER JOIN instituciones i ON c.institucion_id = i.id
                     INNER JOIN entidades e ON i.entidad_id = e.id
                     LEFT JOIN sedes s ON c.sede_id = s.id
                     LEFT JOIN ciudades ciu ON s.ciudad_id = ciu.id_ciudad
                     LEFT JOIN recorrido r ON c.recorrido_id = r.id
                     LEFT JOIN guia_asignado ga ON ga.cita_id = c.id
                     LEFT JOIN personal p ON p.id = ga.personal_id
                     LEFT JOIN entidades e2 ON p.entidad_id = e2.id
                     WHERE DATE(c.fecha_visita) = CURDATE()";

            $rolNorm = strtolower(trim((string)$rol));
            if (($rolNorm === 'guia' || $rolNorm === 'guía') && $personalId !== null) {
                // El guía ve las citas asignadas a él
                $base .= " AND EXISTS (
                            SELECT 1 FROM guia_asignado ga2
                            WHERE ga2.cita_id = c.id AND ga2.personal_id = :filter_personal_id
                          )";
                $base .= " GROUP BY c.id ORDER BY c.hora_inicio ASC";
                $stmt = $db->prepare($base);
                $stmt->bindValue(':logged_personal_id',  $loggedId,   PDO::PARAM_INT);
                $stmt->bindValue(':logged_personal_id2', $loggedId,   PDO::PARAM_INT);
                $stmt->bindValue(':filter_personal_id',  $personalId, PDO::PARAM_INT);
            } else {
                $base .= " GROUP BY c.id ORDER BY c.hora_inicio ASC";
                $stmt = $db->prepare($base);
                $stmt->bindValue(':logged_personal_id',  $loggedId, PDO::PARAM_INT);
                $stmt->bindValue(':logged_personal_id2', $loggedId, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Cita::obtenerCitasHoy(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Inicia un recorrido para la cita especificada, actualizando estatus a 'en_recorrido' y hora_inicio a la hora actual.
     */
    public static function empezarRecorrido(PDO $db, int $citaId): bool {
        try {
            $stmt = $db->prepare("UPDATE citas SET estatus = 'en_recorrido', hora_inicio = CURTIME() WHERE id = :id");
            $stmt->bindParam(':id', $citaId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::empezarRecorrido(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finaliza un recorrido manualmente, cambiando su estatus a 'completado'.
     */
    public static function terminarRecorrido(PDO $db, int $citaId): bool {
        try {
            $stmt = $db->prepare("UPDATE citas SET estatus = 'completado' WHERE id = :id");
            $stmt->bindParam(':id', $citaId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::terminarRecorrido(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un personal específico está asignado como guía a una cita.
     */
    public static function esGuiaAsignado(PDO $db, int $citaId, int $personalId): bool {
        try {
            $stmt = $db->prepare("SELECT 1 FROM guia_asignado WHERE cita_id = :cita_id AND personal_id = :personal_id LIMIT 1");
            $stmt->bindParam(':cita_id', $citaId, PDO::PARAM_INT);
            $stmt->bindParam(':personal_id', $personalId, PDO::PARAM_INT);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error en Cita::esGuiaAsignado(): " . $e->getMessage());
            return false;
        }
    }

    public static function asignarGuia($db, $citaId, $personalId, $rolGuia = 'principal') {
        try {
            $delQuery = "DELETE FROM guia_asignado WHERE cita_id = :cita_id AND personal_id = :personal_id";
            $stmtDel = $db->prepare($delQuery);
            $stmtDel->bindParam(':cita_id', $citaId, PDO::PARAM_INT);
            $stmtDel->bindParam(':personal_id', $personalId, PDO::PARAM_INT);
            $stmtDel->execute();

            $query = "INSERT INTO guia_asignado (cita_id, personal_id, rol_guia) VALUES (:cita_id, :personal_id, :rol_guia)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':cita_id', $citaId, PDO::PARAM_INT);
            $stmt->bindParam(':personal_id', $personalId, PDO::PARAM_INT);
            $stmt->bindParam(':rol_guia', $rolGuia, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::asignarGuia(): " . $e->getMessage());
            return false;
        }
    }

    public static function desasignarGuia($db, $citaId, $personalId) {
        try {
            $query = "DELETE FROM guia_asignado WHERE cita_id = :cita_id AND personal_id = :personal_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':cita_id', $citaId, PDO::PARAM_INT);
            $stmt->bindParam(':personal_id', $personalId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::desasignarGuia(): " . $e->getMessage());
            return false;
        }
    }
}
