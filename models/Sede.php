<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo de Sede (Sede)
 * Representa la entidad Sede de una institución y gestiona su persistencia de forma segura.
 * Hereda de Database para gestionar internamente la conexión.
 * Vincula exclusivamente el ID de la Parroquia (parroquia_id), desde el cual se reconstruye
 * toda la jerarquía geográfica: Parroquia -> Municipio -> Estado.
 */
class Sede extends Database {
    private string $table_name = "sedes";

    private ?int $id = null;
    private ?int $institucion_id = null;
    private ?int $parroquia_id = null;
    private ?string $detalles = null;
    private ?int $activo = 1;

    public function __construct() {
        parent::__construct();
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id !== null ? (int)$id : null; }

    public function getInstitucionId(): ?int { return $this->institucion_id; }
    public function setInstitucionId(?int $institucion_id): void { $this->institucion_id = $institucion_id !== null ? (int)$institucion_id : null; }

    public function getParroquiaId(): ?int { return $this->parroquia_id; }
    public function setParroquiaId(?int $parroquia_id): void { $this->parroquia_id = $parroquia_id !== null ? (int)$parroquia_id : null; }

    public function getDetalles(): ?string { return $this->detalles; }
    public function setDetalles(?string $detalles): void { $this->detalles = $detalles ? trim($detalles) : null; }

    public function getActivo(): ?int { return $this->activo; }
    public function setActivo(?int $activo): void { $this->activo = $activo !== null ? (int)$activo : 1; }

    // Compatibilidad opcional para llamadas legacy si alguna existiera
    public function getCiudadId(): ?int { return null; }
    public function setCiudadId(?int $ciudad_id): void { /* Ignorado por nueva arquitectura territorial */ }

    // =========================================================================
    // PERSISTENCIA Y CONSULTA
    // =========================================================================

    /**
     * Registra una nueva sede para una institución utilizando $this->institucion_id y $this->parroquia_id
     */
    public function crear(): bool {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "INSERT INTO " . $this->table_name . " (institucion_id, parroquia_id, detalles) 
                  VALUES (:institucion_id, :parroquia_id, :detalles)";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(":institucion_id", (int)$this->institucion_id, PDO::PARAM_INT);
        $stmt->bindValue(":parroquia_id", (int)$this->parroquia_id, PDO::PARAM_INT);
        $stmt->bindValue(":detalles", $this->detalles, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $this->id = (int)$this->db->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Actualiza una sede existente utilizando $this->id y $this->parroquia_id
     */
    public function actualizar(): bool {
        if (!$this->db || !$this->id) {
            throw new Exception("Parámetros insuficientes para actualizar la sede.");
        }

        $query = "UPDATE " . $this->table_name . " 
                  SET parroquia_id = :parroquia_id, detalles = :detalles 
                  WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(":parroquia_id", (int)$this->parroquia_id, PDO::PARAM_INT);
        $stmt->bindValue(":detalles", $this->detalles, PDO::PARAM_STR);
        $stmt->bindValue(":id", (int)$this->id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Elimina una sede por su ID (Borrado Lógico) usando $this->id
     */
    public function eliminar(?int $id = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return false;
        }

        $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(":id", (int)$this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Obtiene una sede por su ID reconstruyendo toda la jerarquía geográfica:
     * Parroquia -> Municipio -> Estado
     */
    public function obtenerPorId(?int $id = null): ?array {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return null;
        }

        try {
            $query = "SELECT s.id, s.institucion_id, s.parroquia_id, s.detalles,
                             p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                      FROM " . $this->table_name . " s
                      INNER JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                      INNER JOIN municipios m ON p.id_municipio = m.id_municipio
                      INNER JOIN estados est ON m.id_estado = est.id_estado
                      WHERE s.id = :id AND s.activo = 1 LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":id", (int)$this->id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->institucion_id = (int)$row['institucion_id'];
                $this->parroquia_id = (int)$row['parroquia_id'];
                $this->detalles = $row['detalles'];
                return $row;
            }
            return null;
        } catch (PDOException $e) {
            error_log("Error en Sede::obtenerPorId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene todas las sedes asociadas a una institución usando $this->institucion_id,
     * reconstruyendo toda la información geográfica desde parroquias
     */
    public function listarPorInstitucion(?int $institucion_id = null): array {
        if ($institucion_id !== null) {
            $this->institucion_id = $institucion_id;
        }

        if (!$this->db || !$this->institucion_id) {
            return [];
        }

        try {
            $query = "SELECT s.id, s.institucion_id, s.parroquia_id, s.detalles,
                             p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                      FROM " . $this->table_name . " s
                      LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                      LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                      LEFT JOIN estados est ON m.id_estado = est.id_estado
                      WHERE s.institucion_id = :institucion_id AND s.activo = 1
                      ORDER BY s.id ASC";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":institucion_id", (int)$this->institucion_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Sede::listarPorInstitucion: " . $e->getMessage());
            return [];
        }
    }
}
