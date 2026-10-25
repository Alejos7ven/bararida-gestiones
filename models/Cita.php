<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo de Citas (Cita)
 * Procesa y gestiona la lógica de persistencia de las citas y sus relaciones en la base de datos.
 * Hereda de Database para gestionar internamente la conexión.
 * Respeta el patrón MVC operando mediante propiedades internas configuradas por Setters y Getters.
 */
class Cita extends Database {
    private string $table_name = "citas";

    // Propiedades de la entidad
    private ?int $id = null;
    private ?int $institucion_id = null;
    private ?int $sede_id = null;
    private ?int $recorrido_id = null;
    private ?string $fecha_solicitud = null;
    private ?string $fecha_visita = null;
    private ?string $hora_inicio = null;
    private ?string $tipo = 'institucional'; // 'campamento nocturno', 'corporativo', 'evento', 'institucional', 'otro', 'particular'
    private ?string $estatus = 'pendiente'; // 'pendiente', 'aprobado', 'en_recorrido', 'completado', 'cancelada'
    private ?string $metodo_pago = null;
    private ?string $pago_estado = 'pendiente'; // 'pendiente', 'pagado'

    // Propiedades para operaciones de asignación, filtros y relaciones
    private ?int $personal_id = null;
    private ?string $rol_guia = 'principal';
    private ?int $cita_id_excluir = null;
    private ?string $filtro_rol = null;
    private ?int $filtro_personal_id = null;
    private bool $tiene_permiso_citas = true;

    /**
     * Constructor del modelo Cita
     */
    public function __construct() {
        parent::__construct();
        $this->fecha_solicitud = date('Y-m-d');
        $this->tipo = 'institucional';
        $this->estatus = 'pendiente';
        $this->pago_estado = 'pendiente';
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getId(): ?int {
        return $this->id;
    }

    public function setId(?int $id): void {
        $this->id = $id !== null ? (int)$id : null;
    }

    public function getInstitucionId(): ?int {
        return $this->institucion_id;
    }

    public function setInstitucionId(?int $inst_id): void {
        $this->institucion_id = $inst_id !== null ? (int)$inst_id : null;
    }

    public function getSedeId(): ?int {
        return $this->sede_id;
    }

    public function setSedeId(?int $sede_id): void {
        $this->sede_id = ($sede_id !== null && $sede_id !== '' && is_numeric($sede_id)) ? (int)$sede_id : null;
    }

    public function getRecorridoId(): ?int {
        return $this->recorrido_id;
    }

    public function setRecorridoId(?int $rec_id): void {
        $this->recorrido_id = ($rec_id !== null && $rec_id !== '' && is_numeric($rec_id)) ? (int)$rec_id : null;
    }

    public function getFechaSolicitud(): ?string {
        return $this->fecha_solicitud;
    }

    public function setFechaSolicitud(?string $fecha): void {
        $this->fecha_solicitud = $fecha !== null ? trim($fecha) : null;
    }

    public function getFechaVisita(): ?string {
        return $this->fecha_visita;
    }

    public function setFechaVisita(?string $fecha): void {
        $this->fecha_visita = $fecha !== null ? trim($fecha) : null;
    }

    public function getHoraInicio(): ?string {
        return $this->hora_inicio;
    }

    public function setHoraInicio(?string $hora): void {
        $this->hora_inicio = $hora !== null ? trim($hora) : null;
    }

    public function getEstatus(): ?string {
        return $this->estatus;
    }

    public function setEstatus(?string $estatus): void {
        $this->estatus = $estatus !== null ? trim($estatus) : 'pendiente';
    }

    public function getMetodoPago(): ?string {
        return $this->metodo_pago;
    }

    public function setMetodoPago(?string $metodo): void {
        $this->metodo_pago = $metodo !== null ? trim($metodo) : null;
    }

    public function getPagoEstado(): ?string {
        return $this->pago_estado;
    }

    public function setPagoEstado(?string $estado): void {
        $this->pago_estado = $estado !== null ? trim($estado) : 'pendiente';
    }

    public function getTipo(): ?string {
        return $this->tipo;
    }

    public function setTipo(?string $tipo): void {
        $this->tipo = $tipo !== null ? trim($tipo) : 'institucional';
    }

    public function getPersonalId(): ?int {
        return $this->personal_id;
    }

    public function setPersonalId(?int $personal_id): void {
        $this->personal_id = $personal_id !== null ? (int)$personal_id : null;
    }

    public function getRolGuia(): ?string {
        return $this->rol_guia;
    }

    public function setRolGuia(?string $rol_guia): void {
        $this->rol_guia = $rol_guia !== null ? trim($rol_guia) : 'principal';
    }

    public function getCitaIdExcluir(): ?int {
        return $this->cita_id_excluir;
    }

    public function setCitaIdExcluir(?int $cita_id_excluir): void {
        $this->cita_id_excluir = $cita_id_excluir !== null ? (int)$cita_id_excluir : null;
    }

    public function getFiltroRol(): ?string {
        return $this->filtro_rol;
    }

    public function setFiltroRol(?string $rol): void {
        $this->filtro_rol = $rol !== null ? trim($rol) : null;
    }

    public function getFiltroPersonalId(): ?int {
        return $this->filtro_personal_id;
    }

    public function setFiltroPersonalId(?int $id): void {
        $this->filtro_personal_id = $id !== null ? (int)$id : null;
    }

    public function getTienePermisoCitas(): bool {
        return $this->tiene_permiso_citas;
    }

    public function setTienePermisoCitas(bool $tienePermiso): void {
        $this->tiene_permiso_citas = $tienePermiso;
    }

    // =========================================================================
    // OPERACIONES DE PERSISTENCIA Y CONSULTA
    // =========================================================================

    /**
     * Registra una nueva cita en la base de datos utilizando las propiedades internas
     * @return bool True si se insertó con éxito
     */
    public function crear(): bool {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "INSERT INTO " . $this->table_name . " 
                      (institucion_id, sede_id, recorrido_id, tipo, fecha_solicitud, fecha_visita, hora_inicio, estatus, metodo_pago, pago_estado) 
                      VALUES (:institucion_id, :sede_id, :recorrido_id, :tipo, :fecha_solicitud, :fecha_visita, :hora_inicio, :estatus, :metodo_pago, :pago_estado)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":institucion_id", (int)$this->institucion_id, PDO::PARAM_INT);
            if ($this->sede_id === null) {
                $stmt->bindValue(":sede_id", null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(":sede_id", (int)$this->sede_id, PDO::PARAM_INT);
            }
            if ($this->recorrido_id === null) {
                $stmt->bindValue(":recorrido_id", null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(":recorrido_id", (int)$this->recorrido_id, PDO::PARAM_INT);
            }
            $stmt->bindValue(":tipo", $this->tipo ?: 'institucional', PDO::PARAM_STR);
            $stmt->bindValue(":fecha_solicitud", $this->fecha_solicitud, PDO::PARAM_STR);
            $stmt->bindValue(":fecha_visita", $this->fecha_visita, PDO::PARAM_STR);
            $stmt->bindValue(":hora_inicio", $this->hora_inicio, PDO::PARAM_STR);
            $stmt->bindValue(":estatus", $this->estatus, PDO::PARAM_STR);
            $stmt->bindValue(":metodo_pago", $this->metodo_pago, PDO::PARAM_STR);
            $stmt->bindValue(":pago_estado", $this->pago_estado, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $this->setId((int)$this->db->lastInsertId());
                return true;
            }
        } catch (PDOException $e) {
            error_log("Error al crear cita: " . $e->getMessage());
            throw new Exception("Error al guardar la cita en la base de datos.");
        }
        return false;
    }

    /**
     * Actualiza una cita existente utilizando las propiedades internas
     * @return bool True si la actualización fue exitosa
     */
    public function actualizar(): bool {
        if (!$this->db || empty($this->id)) {
            throw new Exception("Conexión de base de datos o ID de cita no configurados.");
        }

        try {
            $query = "UPDATE " . $this->table_name . " SET 
                      institucion_id = :institucion_id,
                      sede_id = :sede_id,
                      recorrido_id = :recorrido_id, 
                      tipo = :tipo,
                      fecha_visita = :fecha_visita, 
                      hora_inicio = :hora_inicio, 
                      estatus = :estatus, 
                      metodo_pago = :metodo_pago, 
                      pago_estado = :pago_estado 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":institucion_id", (int)$this->institucion_id, PDO::PARAM_INT);
            if ($this->sede_id === null) {
                $stmt->bindValue(":sede_id", null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(":sede_id", (int)$this->sede_id, PDO::PARAM_INT);
            }
            if ($this->recorrido_id === null) {
                $stmt->bindValue(":recorrido_id", null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(":recorrido_id", (int)$this->recorrido_id, PDO::PARAM_INT);
            }
            $stmt->bindValue(":tipo", $this->tipo ?: 'institucional', PDO::PARAM_STR);
            $stmt->bindValue(":fecha_visita", $this->fecha_visita, PDO::PARAM_STR);
            $stmt->bindValue(":hora_inicio", $this->hora_inicio, PDO::PARAM_STR);
            $stmt->bindValue(":estatus", $this->estatus, PDO::PARAM_STR);
            $stmt->bindValue(":metodo_pago", $this->metodo_pago, PDO::PARAM_STR);
            $stmt->bindValue(":pago_estado", $this->pago_estado, PDO::PARAM_STR);
            $stmt->bindValue(":id", (int)$this->id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al actualizar cita: " . $e->getMessage());
            throw new Exception("Error al modificar la cita en la base de datos.");
        }
    }

    /**
     * Elimina una cita de forma segura (borrado lógico) mediante $this->id
     * @param int|null $id Opcional para retrocompatibilidad
     * @return bool
     */
    public function eliminar(?int $id = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de cita no configurado.");
        }

        try {
            $queryCita = "UPDATE " . $this->table_name . " SET activo = 0, estatus = 'cancelada' WHERE id = :id";
            $stmtCita = $this->db->prepare($queryCita);
            $stmtCita->bindValue(":id", (int)$this->id, PDO::PARAM_INT);
            return $stmtCita->execute();
        } catch (Exception $e) {
            error_log("Error al eliminar lógicamente la cita: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lista todas las citas activas con sus joins de instituciones, entidades, sedes y recorridos
     * @return array
     */
    public function listarTodas(): array {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $query = "SELECT c.id, c.institucion_id, c.sede_id, c.recorrido_id, c.tipo, c.fecha_solicitud, c.fecha_visita, c.hora_inicio, c.estatus, c.metodo_pago, c.pago_estado,
                             e.tipo_documento, e.documento AS RIF, e.nombre_completo AS razon_social, e.nombre_completo,
                             r.nombre AS recorrido_nombre,
                             s.detalles AS sede_detalles, par.parroquia AS sede_parroquia,
                             mun.municipio AS sede_municipio, est.estado AS sede_estado,
                             (SELECT COUNT(*) FROM asistencia_cita ac WHERE ac.cita_id = c.id) AS total_visitantes
                      FROM " . $this->table_name . " c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      LEFT JOIN sedes s ON c.sede_id = s.id
                      LEFT JOIN parroquias par ON s.parroquia_id = par.id_parroquia
                      LEFT JOIN municipios mun ON par.id_municipio = mun.id_municipio
                      LEFT JOIN estados est ON mun.id_estado = est.id_estado
                      LEFT JOIN recorrido r ON c.recorrido_id = r.id
                      WHERE c.activo = 1
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
     * Obtiene los detalles de una cita por su ID utilizando $this->id
     * Actualiza también las propiedades internas del objeto si se encuentra
     * @param mixed $arg1 Opcional: ID de la cita o conexión para retrocompatibilidad
     * @param mixed $arg2 Opcional: ID de la cita para retrocompatibilidad
     * @return array|null
     */
    public function obtenerPorId($arg1 = null, $arg2 = null): ?array {
        if ($arg1 !== null && is_numeric($arg1)) {
            $this->id = (int)$arg1;
        } elseif ($arg2 !== null && is_numeric($arg2)) {
            $this->id = (int)$arg2;
        }

        if (!$this->db || !$this->id) {
            return null;
        }

        try {
            $query = "SELECT c.id, c.institucion_id, c.sede_id, c.recorrido_id, c.tipo, c.fecha_solicitud, c.fecha_visita, c.hora_inicio, c.estatus, c.metodo_pago, c.pago_estado,
                             e.tipo_documento, e.documento AS RIF, e.nombre_completo AS razon_social, e.nombre_completo,
                             r.nombre AS recorrido_nombre,
                             s.detalles AS sede_detalles, s.parroquia_id AS sede_parroquia_id,
                             par.parroquia AS sede_parroquia,
                             mun.id_municipio AS sede_id_municipio, mun.municipio AS sede_municipio,
                             est.id_estado AS sede_id_estado, est.estado AS sede_estado
                      FROM " . $this->table_name . " c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      LEFT JOIN sedes s ON c.sede_id = s.id
                      LEFT JOIN parroquias par ON s.parroquia_id = par.id_parroquia
                      LEFT JOIN municipios mun ON par.id_municipio = mun.id_municipio
                      LEFT JOIN estados est ON mun.id_estado = est.id_estado
                      LEFT JOIN recorrido r ON c.recorrido_id = r.id
                      WHERE c.id = :id AND c.activo = 1 LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":id", (int)$this->id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $this->institucion_id = (int)$row['institucion_id'];
                $this->sede_id = $row['sede_id'] ? (int)$row['sede_id'] : null;
                $this->recorrido_id = $row['recorrido_id'] ? (int)$row['recorrido_id'] : null;
                $this->tipo = $row['tipo'] ?? 'institucional';
                $this->fecha_solicitud = $row['fecha_solicitud'];
                $this->fecha_visita = $row['fecha_visita'];
                $this->hora_inicio = $row['hora_inicio'];
                $this->estatus = $row['estatus'];
                $this->metodo_pago = $row['metodo_pago'];
                $this->pago_estado = $row['pago_estado'];
                return $row;
            }
            return null;
        } catch (PDOException $e) {
            error_log("Error al obtener cita por ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene la fecha de visita de la cita configurada en $this->id
     * @param int|null $id Opcional para retrocompatibilidad
     * @return string|null
     */
    public function obtenerFechaVisita(?int $id = null): ?string {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("SELECT fecha_visita FROM " . $this->table_name . " WHERE id = :id AND activo = 1 LIMIT 1");
            $stmt->bindValue(':id', (int)$this->id, PDO::PARAM_INT);
            $stmt->execute();
            $fecha = $stmt->fetchColumn();
            return $fecha ?: null;
        } catch (PDOException $e) {
            error_log("Error en Cita::obtenerFechaVisita(): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el listado de fechas de visita con la cantidad de instituciones registradas
     * @return array
     */
    public function obtenerCronogramaCalendario(): array {
        if (!$this->db) {
            return [];
        }

        try {
            $query = "SELECT c.fecha_visita, COUNT(DISTINCT c.institucion_id) AS total_instituciones,
                             GROUP_CONCAT(DISTINCT e.nombre_completo SEPARATOR '||') AS instituciones_nombres
                      FROM " . $this->table_name . " c
                      INNER JOIN instituciones i ON c.institucion_id = i.id
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      WHERE c.activo = 1
                      GROUP BY c.fecha_visita 
                      ORDER BY c.fecha_visita ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Cita::obtenerCronogramaCalendario(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la cantidad total de visitantes agrupado por día de visita
     * @return array
     */
    public function obtenerEstadisticasAfluencia(): array {
        if (!$this->db) {
            return [];
        }

        try {
            $query = "SELECT c.fecha_visita, COUNT(ac.visitante_id) AS total_visitantes
                      FROM " . $this->table_name . " c
                      LEFT JOIN asistencia_cita ac ON c.id = ac.cita_id
                      WHERE c.activo = 1
                      GROUP BY c.fecha_visita
                      ORDER BY c.fecha_visita ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Cita::obtenerEstadisticasAfluencia(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene las citas programadas para el día de hoy con control de recorridos y guías
     * Opera mediante propiedades internas o parámetros opcionales para retrocompatibilidad
     * @param string|null $rol
     * @param int|null $personalId
     * @param bool|null $tienePermisoCitas
     * @return array
     */
    public function obtenerCitasHoy(?string $rol = null, ?int $personalId = null, ?bool $tienePermisoCitas = null): array {
        if (!$this->db) {
            return [];
        }

        if ($rol !== null) {
            $this->filtro_rol = $rol;
        }
        if ($personalId !== null) {
            $this->filtro_personal_id = $personalId;
        }
        if ($tienePermisoCitas !== null) {
            $this->tiene_permiso_citas = $tienePermisoCitas;
        }

        try {
            // AUTO-TERMINACIÓN EN 2 HORAS (120 MINUTOS):
            // Cualquier recorrido que haya iniciado hace 2 horas o más, o en días anteriores,
            // se finaliza automáticamente como 'completado'.
            $this->db->exec("UPDATE " . $this->table_name . " 
                             SET estatus = 'completado' 
                             WHERE estatus = 'en_recorrido' 
                               AND (
                                 fecha_visita < CURDATE() 
                                 OR (fecha_visita = CURDATE() AND TIMESTAMPDIFF(MINUTE, hora_inicio, CURTIME()) >= 120)
                               )");

            $loggedId = $this->filtro_personal_id !== null ? (int)$this->filtro_personal_id : 0;

            $base = "SELECT c.id, c.hora_inicio, c.estatus, c.pago_estado, c.tipo, c.fecha_visita, c.recorrido_id,
                            e.nombre_completo AS razon_social, e.tipo_documento, e.documento AS rif,
                            r.nombre AS recorrido_nombre,
                            s.detalles AS sede_detalles, par.parroquia AS sede_parroquia, mun.municipio AS sede_municipio, est.estado AS sede_estado,
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
                     FROM " . $this->table_name . " c
                     INNER JOIN instituciones i ON c.institucion_id = i.id
                     INNER JOIN entidades e ON i.entidad_id = e.id
                     LEFT JOIN sedes s ON c.sede_id = s.id
                     LEFT JOIN parroquias par ON s.parroquia_id = par.id_parroquia
                     LEFT JOIN municipios mun ON par.id_municipio = mun.id_municipio
                     LEFT JOIN estados est ON mun.id_estado = est.id_estado
                     LEFT JOIN recorrido r ON c.recorrido_id = r.id
                     LEFT JOIN guia_asignado ga ON ga.cita_id = c.id
                     LEFT JOIN personal p ON p.id = ga.personal_id
                     LEFT JOIN entidades e2 ON p.entidad_id = e2.id
                     WHERE c.activo = 1 AND DATE(c.fecha_visita) = CURDATE()";

            $rolNorm = strtolower(trim((string)$this->filtro_rol));
            if (!$this->tiene_permiso_citas && ($rolNorm === 'guia' || $rolNorm === 'guía') && $this->filtro_personal_id !== null) {
                // Si el usuario no tiene permiso general a citas en el RBAC, solo ve las citas asignadas a él
                $base .= " AND EXISTS (
                            SELECT 1 FROM guia_asignado ga2
                            WHERE ga2.cita_id = c.id AND ga2.personal_id = :filter_personal_id
                          )";
                $base .= " GROUP BY c.id ORDER BY c.hora_inicio ASC";
                $stmt = $this->db->prepare($base);
                $stmt->bindValue(':logged_personal_id',  $loggedId,   PDO::PARAM_INT);
                $stmt->bindValue(':logged_personal_id2', $loggedId,   PDO::PARAM_INT);
                $stmt->bindValue(':filter_personal_id',  $this->filtro_personal_id, PDO::PARAM_INT);
            } else {
                // Con permiso de citas o rol administrativo: ve todas las citas del día con normalidad
                $base .= " GROUP BY c.id ORDER BY c.hora_inicio ASC";
                $stmt = $this->db->prepare($base);
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
     * Inicia un recorrido para la cita especificada mediante $this->id,
     * actualizando estatus a 'en_recorrido' y hora_inicio a la hora actual.
     * @param int|null $id Opcional para retrocompatibilidad
     * @return bool
     */
    public function empezarRecorrido(?int $id = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table_name . " SET estatus = 'en_recorrido', hora_inicio = CURTIME() WHERE id = :id");
            $stmt->bindValue(':id', (int)$this->id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::empezarRecorrido(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finaliza un recorrido manualmente mediante $this->id, cambiando su estatus a 'completado'.
     * @param int|null $id Opcional para retrocompatibilidad
     * @return bool
     */
    public function terminarRecorrido(?int $id = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table_name . " SET estatus = 'completado' WHERE id = :id");
            $stmt->bindValue(':id', (int)$this->id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::terminarRecorrido(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un personal específico está asignado como guía a la cita ($this->id y $this->personal_id)
     * @param int|null $personalId
     * @param int|null $citaId
     * @return bool
     */
    public function esGuiaAsignado(?int $personalId = null, ?int $citaId = null): bool {
        if ($citaId !== null) {
            $this->id = $citaId;
        }
        if ($personalId !== null) {
            $this->personal_id = $personalId;
        }

        if (!$this->db || !$this->id || !$this->personal_id) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("SELECT 1 FROM guia_asignado WHERE cita_id = :cita_id AND personal_id = :personal_id LIMIT 1");
            $stmt->bindValue(':cita_id', (int)$this->id, PDO::PARAM_INT);
            $stmt->bindValue(':personal_id', (int)$this->personal_id, PDO::PARAM_INT);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error en Cita::esGuiaAsignado(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un guía ya tiene una cita activa (no completada ni cancelada) en una fecha determinada.
     * Si ya tiene una cita ese día en estatus 'pendiente', 'aprobado' o 'en_recorrido', no puede ser asignado.
     * Solo podrá reasignarse cuando la cita otorgada sea 'completado'.
     * @param int|null $personalId
     * @param string|null $fechaVisita
     * @param int|null $citaIdExcluir
     * @return bool
     */
    public function guiaTieneCitaActivaEnFecha(?int $personalId = null, ?string $fechaVisita = null, ?int $citaIdExcluir = null): bool {
        if ($personalId !== null) {
            $this->personal_id = $personalId;
        }
        if ($fechaVisita !== null) {
            $this->fecha_visita = $fechaVisita;
        }
        if ($citaIdExcluir !== null) {
            $this->cita_id_excluir = $citaIdExcluir;
        }

        if (!$this->db || !$this->personal_id || empty($this->fecha_visita)) {
            return false;
        }

        try {
            $sql = "SELECT c.id 
                    FROM guia_asignado ga
                    INNER JOIN " . $this->table_name . " c ON ga.cita_id = c.id
                    WHERE c.activo = 1
                      AND ga.personal_id = :personal_id
                      AND c.fecha_visita = :fecha_visita
                      AND c.estatus IN ('pendiente', 'aprobado', 'en_recorrido')";
            if ($this->cita_id_excluir !== null) {
                $sql .= " AND c.id != :cita_id_excluir";
            }
            $sql .= " LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':personal_id', (int)$this->personal_id, PDO::PARAM_INT);
            $stmt->bindValue(':fecha_visita', $this->fecha_visita, PDO::PARAM_STR);
            if ($this->cita_id_excluir !== null) {
                $stmt->bindValue(':cita_id_excluir', (int)$this->cita_id_excluir, PDO::PARAM_INT);
            }
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error en Cita::guiaTieneCitaActivaEnFecha(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Asigna un guía a la cita configurada en $this->id
     * @param int|null $personalId
     * @param string|null $rolGuia
     * @param int|null $citaId
     * @return bool
     */
    public function asignarGuia(?int $personalId = null, ?string $rolGuia = null, ?int $citaId = null): bool {
        if ($citaId !== null) {
            $this->id = $citaId;
        }
        if ($personalId !== null) {
            $this->personal_id = $personalId;
        }
        if ($rolGuia !== null) {
            $this->rol_guia = $rolGuia;
        }

        if (!$this->db || !$this->id || !$this->personal_id) {
            return false;
        }

        try {
            $delQuery = "DELETE FROM guia_asignado WHERE cita_id = :cita_id AND personal_id = :personal_id";
            $stmtDel = $this->db->prepare($delQuery);
            $stmtDel->bindValue(':cita_id', (int)$this->id, PDO::PARAM_INT);
            $stmtDel->bindValue(':personal_id', (int)$this->personal_id, PDO::PARAM_INT);
            $stmtDel->execute();

            $query = "INSERT INTO guia_asignado (cita_id, personal_id, rol_guia) VALUES (:cita_id, :personal_id, :rol_guia)";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':cita_id', (int)$this->id, PDO::PARAM_INT);
            $stmt->bindValue(':personal_id', (int)$this->personal_id, PDO::PARAM_INT);
            $stmt->bindValue(':rol_guia', $this->rol_guia ?: 'principal', PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::asignarGuia(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Desasigna un guía de la cita configurada en $this->id
     * @param int|null $personalId
     * @param int|null $citaId
     * @return bool
     */
    public function desasignarGuia(?int $personalId = null, ?int $citaId = null): bool {
        if ($citaId !== null) {
            $this->id = $citaId;
        }
        if ($personalId !== null) {
            $this->personal_id = $personalId;
        }

        if (!$this->db || !$this->id || !$this->personal_id) {
            return false;
        }

        try {
            $query = "DELETE FROM guia_asignado WHERE cita_id = :cita_id AND personal_id = :personal_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':cita_id', (int)$this->id, PDO::PARAM_INT);
            $stmt->bindValue(':personal_id', (int)$this->personal_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::desasignarGuia(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza el recorrido asociado a la cita configurada en $this->id
     * @param int|null $recorridoId
     * @param int|null $citaId
     * @return bool
     */
    public function actualizarRecorrido(?int $recorridoId = null, ?int $citaId = null): bool {
        if ($citaId !== null) {
            $this->id = $citaId;
        }
        if ($recorridoId !== null) {
            $this->recorrido_id = $recorridoId;
        }

        if (!$this->db || !$this->id) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table_name . " SET recorrido_id = :recorrido_id WHERE id = :id");
            if ($this->recorrido_id !== null && $this->recorrido_id > 0) {
                $stmt->bindValue(':recorrido_id', (int)$this->recorrido_id, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':recorrido_id', null, PDO::PARAM_NULL);
            }
            $stmt->bindValue(':id', (int)$this->id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::actualizarRecorrido(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vincula una lista de visitantes a la cita ($this->id).
     * Evita duplicados en la tabla de asistencia_cita mediante verificación previa.
     * @param array $visitanteIds Listado de IDs de visitantes
     * @param int|null $citaId Opcional para retrocompatibilidad
     * @return int Cantidad de visitantes vinculados exitosamente
     */
    public function vincularVisitantes(array $visitanteIds, ?int $citaId = null): int {
        if ($citaId !== null) {
            $this->id = $citaId;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de cita no configurado.");
        }

        try {
            $this->db->beginTransaction();

            $stmtVerificarLink = $this->db->prepare("SELECT id FROM asistencia_cita WHERE cita_id = :cita_id AND visitante_id = :visitante_id LIMIT 1");
            $stmtInsertarLink = $this->db->prepare("INSERT INTO asistencia_cita (cita_id, visitante_id) VALUES (:cita_id, :visitante_id)");

            $vinculados = 0;
            foreach ($visitanteIds as $vid) {
                $vidClean = filter_var($vid, FILTER_VALIDATE_INT);
                if (!$vidClean) continue;

                $stmtVerificarLink->bindValue(":cita_id", (int)$this->id, PDO::PARAM_INT);
                $stmtVerificarLink->bindValue(":visitante_id", $vidClean, PDO::PARAM_INT);
                $stmtVerificarLink->execute();
                $linkExists = $stmtVerificarLink->fetchColumn();

                if (!$linkExists) {
                    $stmtInsertarLink->bindValue(":cita_id", (int)$this->id, PDO::PARAM_INT);
                    $stmtInsertarLink->bindValue(":visitante_id", $vidClean, PDO::PARAM_INT);
                    $stmtInsertarLink->execute();
                    $vinculados++;
                }
            }

            $this->db->commit();
            return $vinculados;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en Cita::vincularVisitantes(): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Remueve un visitante de la asistencia a la cita configurada en $this->id
     * @param int $visitanteId ID del visitante a desvincular
     * @param int|null $citaId Opcional para retrocompatibilidad
     * @return bool
     */
    public function desvincularVisitante(int $visitanteId, ?int $citaId = null): bool {
        if ($citaId !== null) {
            $this->id = $citaId;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de cita no configurado.");
        }

        try {
            $query = "DELETE FROM asistencia_cita WHERE cita_id = :cita_id AND visitante_id = :visitante_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":cita_id", (int)$this->id, PDO::PARAM_INT);
            $stmt->bindValue(":visitante_id", $visitanteId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en Cita::desvincularVisitante(): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene el listado de guías asignados a la cita configurada en $this->id
     * @param int|null $citaId Opcional para retrocompatibilidad
     * @return array
     */
    public function obtenerGuiasAsignados(?int $citaId = null): array {
        if ($citaId !== null) {
            $this->id = $citaId;
        }

        if (!$this->db || !$this->id) {
            return [];
        }

        try {
            $query = "SELECT ga.personal_id, e.nombre_completo, e.nombre_completo AS nombre, '' AS apellido, ga.rol_guia 
                      FROM guia_asignado ga
                      INNER JOIN personal p ON ga.personal_id = p.id
                      INNER JOIN entidades e ON p.entidad_id = e.id
                      WHERE ga.cita_id = :cita_id
                      ORDER BY ga.rol_guia ASC";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':cita_id', (int)$this->id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Cita::obtenerGuiasAsignados(): " . $e->getMessage());
            return [];
        }
    }
}
