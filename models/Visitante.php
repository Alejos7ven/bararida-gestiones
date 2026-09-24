<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo de Visitantes (Visitante)
 * Gestiona de forma segura los datos de los visitantes y el proceso de importación y enlace a citas.
 * Hereda de Database para gestionar internamente la conexión.
 */
class Visitante extends Database {
    private string $table_name = "visitantes";

    // Propiedades de la entidad
    private ?int $id = null;
    private ?string $cedula = null;
    private ?string $nombres = null;
    private ?string $apellidos = null;
    private ?string $fecha_nacimiento = null;
    private ?int $cita_id = null;
    private ?int $excluirId = null;

    /**
     * Constructor del Modelo Visitante
     */
    public function __construct() {
        parent::__construct();
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getId(): ?int {
        return $this->id;
    }

    public function setId(?int $id): void {
        $this->id = $id;
    }

    public function getCedula(): ?string {
        return $this->cedula;
    }

    public function setCedula(?string $cedula): void {
        $this->cedula = $cedula ? trim($cedula) : null;
    }

    public function getNombres(): ?string {
        return $this->nombres;
    }

    public function setNombres(?string $nombres): void {
        $this->nombres = $nombres ? trim($nombres) : null;
    }

    public function getApellidos(): ?string {
        return $this->apellidos;
    }

    public function setApellidos(?string $apellidos): void {
        $this->apellidos = $apellidos ? trim($apellidos) : null;
    }

    public function getFechaNacimiento(): ?string {
        return $this->fecha_nacimiento;
    }

    public function setFechaNacimiento(?string $fecha): void {
        $this->fecha_nacimiento = $fecha ? trim($fecha) : null;
    }

    public function getCitaId(): ?int {
        return $this->cita_id;
    }

    public function setCitaId(?int $citaId): void {
        $this->cita_id = $citaId;
    }

    public function getExcluirId(): ?int {
        return $this->excluirId;
    }

    public function setExcluirId(?int $excluirId): void {
        $this->excluirId = $excluirId;
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS
    // =========================================================================

    /**
     * Registra un nuevo visitante de forma manual utilizando las propiedades internas
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function registrar(): bool {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }
        
        if (!empty($this->cedula)) {
            $stmtCheck = $this->db->prepare("SELECT id, activo FROM " . $this->table_name . " WHERE cedula = :cedula LIMIT 1");
            $stmtCheck->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
            $stmtCheck->execute();
            $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($existente) {
                if ((int)$existente['activo'] === 0) {
                    $this->id = (int)$existente['id'];
                    $query = "UPDATE " . $this->table_name . " SET activo = 1, nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento WHERE id = :id";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(":nombres", $this->nombres, PDO::PARAM_STR);
                    $stmt->bindParam(":apellidos", $this->apellidos, PDO::PARAM_STR);
                    $stmt->bindParam(":fecha_nacimiento", $this->fecha_nacimiento, PDO::PARAM_STR);
                    $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
                    return $stmt->execute();
                } else {
                    return false;
                }
            }
        }

        $query = "INSERT INTO " . $this->table_name . " (cedula, nombres, apellidos, fecha_nacimiento) 
                  VALUES (:cedula, :nombres, :apellidos, :fecha_nacimiento)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":cedula", $this->cedula, $this->cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(":nombres", $this->nombres, PDO::PARAM_STR);
        $stmt->bindParam(":apellidos", $this->apellidos, PDO::PARAM_STR);
        $stmt->bindParam(":fecha_nacimiento", $this->fecha_nacimiento, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $this->id = (int)$this->db->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Actualiza los datos de un visitante existente utilizando $this->id y propiedades internas
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function actualizar(): bool {
        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de visitante no inicializado.");
        }
        
        $query = "UPDATE " . $this->table_name . " 
                  SET cedula = :cedula, nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento 
                  WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":cedula", $this->cedula, $this->cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(":nombres", $this->nombres, PDO::PARAM_STR);
        $stmt->bindParam(":apellidos", $this->apellidos, PDO::PARAM_STR);
        $stmt->bindParam(":fecha_nacimiento", $this->fecha_nacimiento, PDO::PARAM_STR);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Elimina un visitante por ID de forma lógica (Soft Delete) usando $this->id
     * Preserva los registros históricos en asistencia_cita para mantener la integridad.
     * @param int|null $id Opcional para retrocompatibilidad
     * @return bool True si se desactivó con éxito, False de lo contrario
     */
    public function eliminar(?int $id = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return false;
        }

        try {
            $queryVis = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
            $stmtVis = $this->db->prepare($queryVis);
            $stmtVis->bindParam(":id", $this->id, PDO::PARAM_INT);
            return $stmtVis->execute();
        } catch (Exception $e) {
            error_log("Error al eliminar lógicamente visitante ID {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el listado completo de todos los visitantes registrados activos
     * @return array Listado de visitantes
     */
    public function listarTodos(): array {
        if (!$this->db) {
            return [];
        }
        $query = "SELECT id, cedula, nombres, apellidos, fecha_nacimiento FROM " . $this->table_name . " WHERE activo = 1 ORDER BY id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un visitante específico por su ID utilizando $this->id
     * @param int|null $id Opcional para retrocompatibilidad
     * @return array|null Datos del visitante o null si no se encuentra
     */
    public function obtenerPorId(?int $id = null): ?array {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return null;
        }

        $query = "SELECT id, cedula, nombres, apellidos, fecha_nacimiento FROM " . $this->table_name . " WHERE id = :id AND activo = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->cedula = $row['cedula'];
            $this->nombres = $row['nombres'];
            $this->apellidos = $row['apellidos'];
            $this->fecha_nacimiento = $row['fecha_nacimiento'];
            return $row;
        }
        return null;
    }

    /**
     * Obtiene todos los visitantes asignados a una cita específica usando $this->cita_id
     * @param int|null $citaId Opcional para retrocompatibilidad
     * @return array Listado de visitantes
     */
    public function obtenerPorCitaId(?int $citaId = null): array {
        if ($citaId !== null) {
            $this->cita_id = $citaId;
        }

        if (!$this->db || !$this->cita_id) {
            return [];
        }

        $query = "SELECT v.id, v.cedula, v.nombres, v.apellidos, v.fecha_nacimiento 
                  FROM " . $this->table_name . " v
                  INNER JOIN asistencia_cita ac ON v.id = ac.visitante_id
                  WHERE ac.cita_id = :cita_id AND v.activo = 1
                  ORDER BY v.id ASC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":cita_id", $this->cita_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca un visitante por su cédula utilizando $this->cedula y opcionalmente $this->excluirId
     * @param string|null $cedula Opcional para retrocompatibilidad
     * @param int|null $excludeId Opcional para retrocompatibilidad
     * @return array|null
     */
    public function buscarPorCedula(?string $cedula = null, ?int $excludeId = null): ?array {
        if ($cedula !== null) {
            $this->cedula = $cedula;
        }
        $exclude = $excludeId !== null ? $excludeId : $this->excluirId;

        if (!$this->db || empty($this->cedula)) {
            return null;
        }

        $query = "SELECT id, cedula, nombres, apellidos, fecha_nacimiento FROM " . $this->table_name . " WHERE cedula = :cedula AND activo = 1";
        if ($exclude) {
            $query .= " AND id != :exclude_id";
        }
        $query .= " LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(":cedula", $this->cedula, PDO::PARAM_STR);
        if ($exclude) {
            $stmt->bindValue(":exclude_id", $exclude, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Importa una lista de visitantes vinculándolos a una cita ($this->cita_id).
     * Reutiliza visitantes existentes por cédula para evitar redundancias de datos.
     * @param array $visitantesArray Listado de visitantes a importar
     * @param int|null $citaId Opcional para retrocompatibilidad
     * @return bool True en caso de éxito
     */
    public function importarVisitantesCita(array $visitantesArray, ?int $citaId = null): bool {
        if ($citaId !== null) {
            $this->cita_id = $citaId;
        }

        if (!$this->db || !$this->cita_id) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            $stmtBuscarDatos = $this->db->prepare("SELECT id FROM " . $this->table_name . " WHERE nombres = :nombres AND apellidos = :apellidos AND fecha_nacimiento = :fecha_nacimiento AND cedula IS NULL LIMIT 1");
            $stmtInsertar = $this->db->prepare("INSERT INTO " . $this->table_name . " (cedula, nombres, apellidos, fecha_nacimiento) 
                                          VALUES (:cedula, :nombres, :apellidos, :fecha_nacimiento)");
            $stmtVerificarLink = $this->db->prepare("SELECT id FROM asistencia_cita WHERE cita_id = :cita_id AND visitante_id = :visitante_id LIMIT 1");
            $stmtInsertarLink = $this->db->prepare("INSERT INTO asistencia_cita (cita_id, visitante_id) VALUES (:cita_id, :visitante_id)");

            foreach ($visitantesArray as $v) {
                $cedula = $v['cedula'] ?? null;
                $nombres = $v['nombres'] ?? '';
                $apellidos = $v['apellidos'] ?? '';
                $fechaNac = $v['fecha_nacimiento'] ?? '';

                $visitanteId = null;

                // 1. Buscar registro previo para evitar redundancias de datos
                if ($cedula !== null) {
                    $stmtBuscarCedula = $this->db->prepare("SELECT id, activo FROM " . $this->table_name . " WHERE cedula = :cedula LIMIT 1");
                    $stmtBuscarCedula->bindParam(":cedula", $cedula, PDO::PARAM_STR);
                    $stmtBuscarCedula->execute();
                    $prevRow = $stmtBuscarCedula->fetch(PDO::FETCH_ASSOC);
                    if ($prevRow) {
                        $visitanteId = (int)$prevRow['id'];
                        if ((int)$prevRow['activo'] === 0) {
                            $stmtReactivar = $this->db->prepare("UPDATE " . $this->table_name . " SET activo = 1, nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento WHERE id = :id");
                            $stmtReactivar->bindParam(":nombres", $nombres, PDO::PARAM_STR);
                            $stmtReactivar->bindParam(":apellidos", $apellidos, PDO::PARAM_STR);
                            $stmtReactivar->bindParam(":fecha_nacimiento", $fechaNac, PDO::PARAM_STR);
                            $stmtReactivar->bindParam(":id", $visitanteId, PDO::PARAM_INT);
                            $stmtReactivar->execute();
                        }
                    }
                } else {
                    $stmtBuscarDatos->bindParam(":nombres", $nombres, PDO::PARAM_STR);
                    $stmtBuscarDatos->bindParam(":apellidos", $apellidos, PDO::PARAM_STR);
                    $stmtBuscarDatos->bindParam(":fecha_nacimiento", $fechaNac, PDO::PARAM_STR);
                    $stmtBuscarDatos->execute();
                    $visitanteId = $stmtBuscarDatos->fetchColumn();
                }

                // 2. Si no existe en la base de datos, registrarlo
                if (!$visitanteId) {
                    $stmtInsertar->bindParam(":cedula", $cedula, $cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $stmtInsertar->bindParam(":nombres", $nombres, PDO::PARAM_STR);
                    $stmtInsertar->bindParam(":apellidos", $apellidos, PDO::PARAM_STR);
                    $stmtInsertar->bindParam(":fecha_nacimiento", $fechaNac, PDO::PARAM_STR);
                    $stmtInsertar->execute();
                    $visitanteId = (int)$this->db->lastInsertId();
                }

                // 3. Verificar si el visitante ya está agendado en la cita actual
                $stmtVerificarLink->bindParam(":cita_id", $this->cita_id, PDO::PARAM_INT);
                $stmtVerificarLink->bindParam(":visitante_id", $visitanteId, PDO::PARAM_INT);
                $stmtVerificarLink->execute();
                $linkExists = $stmtVerificarLink->fetchColumn();

                // 4. Si no está vinculado, insertamos la fila en la tabla pivote
                if (!$linkExists) {
                    $stmtInsertarLink->bindParam(":cita_id", $this->cita_id, PDO::PARAM_INT);
                    $stmtInsertarLink->bindParam(":visitante_id", $visitanteId, PDO::PARAM_INT);
                    $stmtInsertarLink->execute();
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error al importar visitantes en Cita ID {$this->cita_id}: " . $e->getMessage());
            throw $e;
        }
    }
}
