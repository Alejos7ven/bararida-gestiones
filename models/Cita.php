<?php
/**
 * Modelo de Citas (Cita)
 * Procesa y gestiona la lógica de persistencia de las citas en la base de datos.
 */
class Cita {
    private $db;
    private $table_name = "citas";

    // Propiedades de la entidad
    private $id;
    private $institucion_id;
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
    // GETTERS Y SETTERS CON VALIDACIÓN
    // =========================================================================

    public function getId() { return $this->id; }
    public function setId($id) { $this->id = (int)$id; }

    public function getInstitucionId() { return $this->institucion_id; }
    public function setInstitucionId($inst_id) {
        $this->institucion_id = (int)$inst_id;
    }

    public function getRecorridoId() { return $this->recorrido_id; }
    public function setRecorridoId($rec_id) {
        $this->recorrido_id = !empty($rec_id) ? (int)$rec_id : null;
    }

    public function getFechaSolicitud() { return $this->fecha_solicitud; }
    public function setFechaSolicitud($fecha) { $this->fecha_solicitud = trim($fecha); }

    public function getFechaVisita() { return $this->fecha_visita; }
    public function setFechaVisita($fecha) {
        $this->fecha_visita = trim($fecha);
    }

    public function getHoraInicio() { return $this->hora_inicio; }
    public function setHoraInicio($hora) {
        $this->hora_inicio = trim($hora);
    }

    public function getEstatus() { return $this->estatus; }
    public function setEstatus($estatus) {
        $this->estatus = strtolower(trim($estatus));
    }

    public function getMetodoPago() { return $this->metodo_pago; }
    public function setMetodoPago($metodo) {
        $this->metodo_pago = strip_tags(trim($metodo));
    }

    public function getPagoEstado() { return $this->pago_estado; }
    public function setPagoEstado($estado) {
        $this->pago_estado = strtolower(trim($estado));
    }

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
                      (institucion_id, recorrido_id, fecha_solicitud, fecha_visita, hora_inicio, estatus, metodo_pago, pago_estado) 
                      VALUES (:institucion_id, :recorrido_id, :fecha_solicitud, :fecha_visita, :hora_inicio, :estatus, :metodo_pago, :pago_estado)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":institucion_id", $this->institucion_id, PDO::PARAM_INT);
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
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        if (empty($this->id)) {
            throw new InvalidArgumentException("ID de cita no especificado para actualizar.");
        }

        try {
            $query = "UPDATE " . $this->table_name . " SET 
                      recorrido_id = :recorrido_id, 
                      fecha_visita = :fecha_visita, 
                      hora_inicio = :hora_inicio, 
                      estatus = :estatus, 
                      metodo_pago = :metodo_pago, 
                      pago_estado = :pago_estado 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
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

        $cleanId = filter_var($id, FILTER_VALIDATE_INT) ? (int)$id : null;
        if (!$cleanId) {
            throw new InvalidArgumentException("ID de cita no suministrado o inválido.");
        }

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
     * Lista todas las citas con joins de instituciones, entidades y recorridos
     */
    public function listarTodas() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "SELECT c.id, c.institucion_id, c.recorrido_id, c.fecha_solicitud, c.fecha_visita, c.hora_inicio, c.estatus, c.metodo_pago, c.pago_estado,
                             e.tipo_documento, e.documento AS RIF, e.nombre AS razon_social,
                             r.nombre AS recorrido_nombre,
                             (SELECT COUNT(*) FROM asistencia_cita ac WHERE ac.cita_id = c.id) AS total_visitantes
                      FROM " . $this->table_name . " c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
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

        $cleanId = filter_var($id, FILTER_VALIDATE_INT) ? (int)$id : null;
        if (!$cleanId) return null;

        try {
            $query = "SELECT c.id, c.institucion_id, c.recorrido_id, c.fecha_solicitud, c.fecha_visita, c.hora_inicio, c.estatus, c.metodo_pago, c.pago_estado,
                             e.tipo_documento, e.documento AS RIF, e.nombre AS razon_social,
                             r.nombre AS recorrido_nombre
                      FROM citas c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
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
     * y sus nombres concatenados para poblar el calendario.
     * @param PDO $db Instancia de conexión PDO
     * @return array Listado de fechas y cantidades
     */
    public static function obtenerCronogramaCalendario($db) {
        if (!$db) return [];
        try {
            $query = "SELECT c.fecha_visita, COUNT(DISTINCT c.institucion_id) AS total_instituciones,
                             GROUP_CONCAT(DISTINCT e.nombre SEPARATOR '||') AS instituciones_nombres
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
     * para pintar la gráfica de estadísticas.
     * @param PDO $db Instancia de conexión PDO
     * @return array Listado de fechas y conteo de visitantes
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
     * Si el rol es 'guia', solo devuelve las citas a las que ese personal está asignado.
     * @param PDO $db
     * @param string $rol  Rol del usuario logueado ('superadmin', 'admin', 'guia')
     * @param int|null $personalId  ID del personal (requerido si rol == 'guia')
     * @return array
     */
    public static function obtenerCitasHoy(PDO $db, string $rol, ?int $personalId = null): array {
        try {
            $base = "SELECT c.id, c.hora_inicio, c.estatus, c.pago_estado,
                            e.nombre AS razon_social, e.tipo_documento, e.documento AS rif,
                            r.nombre AS recorrido_nombre,
                            (SELECT COUNT(*) FROM asistencia_cita ac WHERE ac.cita_id = c.id) AS total_visitantes,
                            GROUP_CONCAT(
                                CONCAT(e2.nombre, ' ', e2.apellido)
                                ORDER BY ga.rol_guia ASC SEPARATOR ', '
                            ) AS guias_asignados
                     FROM citas c
                     INNER JOIN instituciones i ON c.institucion_id = i.id
                     INNER JOIN entidades e ON i.entidad_id = e.id
                     LEFT JOIN recorrido r ON c.recorrido_id = r.id
                     LEFT JOIN guia_asignado ga ON ga.cita_id = c.id
                     LEFT JOIN personal p ON p.id = ga.personal_id
                     LEFT JOIN entidades e2 ON p.entidad_id = e2.id
                     WHERE DATE(c.fecha_visita) = CURDATE()";

            if ($rol === 'guia' && $personalId !== null) {
                // Guia: solo citas donde está asignado (usando EXISTS para garantizar que el WHERE sea por esa cita)
                $base .= " AND EXISTS (
                            SELECT 1 FROM guia_asignado ga2
                            WHERE ga2.cita_id = c.id AND ga2.personal_id = :personal_id
                          )";
                $base .= " GROUP BY c.id ORDER BY c.hora_inicio ASC";
                $stmt = $db->prepare($base);
                $stmt->bindParam(':personal_id', $personalId, PDO::PARAM_INT);
            } else {
                $base .= " GROUP BY c.id ORDER BY c.hora_inicio ASC";
                $stmt = $db->prepare($base);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Cita::obtenerCitasHoy(): " . $e->getMessage());
            return [];
        }
    }

    public static function asignarGuia($db, $citaId, $personalId, $rolGuia = 'principal') {
        try {
            // Eliminar asignación previa de este guía en esta cita para evitar duplicados
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

