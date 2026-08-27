<?php
/**
 * Modelo de Sede (Sede)
 * Representa la entidad Sede de una institución y gestiona su persistencia de forma segura.
 */
class Sede {
    private $db;
    private $table_name = "sedes";

    private $id;
    private $institucion_id;
    private $ciudad_id;
    private $parroquia_id;
    private $detalles;

    public function __construct($db = null) {
        $this->db = $db;
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getId() { return $this->id; }
    public function setId($id) { $this->id = $id !== null ? (int)$id : null; }

    public function getInstitucionId() { return $this->institucion_id; }
    public function setInstitucionId($institucion_id) { $this->institucion_id = (int)$institucion_id; }

    public function getCiudadId() { return $this->ciudad_id; }
    public function setCiudadId($ciudad_id) { $this->ciudad_id = (int)$ciudad_id; }

    public function getParroquiaId() { return $this->parroquia_id; }
    public function setParroquiaId($parroquia_id) { $this->parroquia_id = (int)$parroquia_id; }

    public function getDetalles() { return $this->detalles; }
    public function setDetalles($detalles) { $this->detalles = $detalles !== null ? trim($detalles) : null; }

    // =========================================================================
    // PERSISTENCIA Y CONSULTA
    // =========================================================================

    /**
     * Registra una nueva sede para una institución
     */
    public function crear() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "INSERT INTO " . $this->table_name . " (institucion_id, ciudad_id, parroquia_id, detalles) 
                  VALUES (:institucion_id, :ciudad_id, :parroquia_id, :detalles)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":institucion_id", $this->institucion_id, PDO::PARAM_INT);
        $stmt->bindParam(":ciudad_id", $this->ciudad_id, PDO::PARAM_INT);
        $stmt->bindParam(":parroquia_id", $this->parroquia_id, PDO::PARAM_INT);
        $stmt->bindParam(":detalles", $this->detalles, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $this->id = (int)$this->db->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Actualiza una sede existente
     */
    public function actualizar() {
        if (!$this->db || !$this->id) {
            throw new Exception("Parámetros insuficientes para actualizar la sede.");
        }

        $query = "UPDATE " . $this->table_name . " 
                  SET ciudad_id = :ciudad_id, parroquia_id = :parroquia_id, detalles = :detalles 
                  WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":ciudad_id", $this->ciudad_id, PDO::PARAM_INT);
        $stmt->bindParam(":parroquia_id", $this->parroquia_id, PDO::PARAM_INT);
        $stmt->bindParam(":detalles", $this->detalles, PDO::PARAM_STR);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Elimina una sede por su ID
     */
    public static function eliminar($db, $id) {
        if (!$db) return false;
        $cleanId = (int)$id;

        $query = "DELETE FROM sedes WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $cleanId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Obtiene una sede por su ID con información geográfica completa
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) return null;
        $cleanId = (int)$id;

        try {
            $query = "SELECT s.id, s.institucion_id, s.ciudad_id, s.parroquia_id, s.detalles,
                             c.ciudad, p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                      FROM sedes s
                      INNER JOIN ciudades c ON s.ciudad_id = c.id_ciudad
                      INNER JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                      INNER JOIN municipios m ON p.id_municipio = m.id_municipio
                      INNER JOIN estados est ON m.id_estado = est.id_estado
                      WHERE s.id = :id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $cleanId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : null;
        } catch (PDOException $e) {
            error_log("Error en Sede::obtenerPorId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene todas las sedes asociadas a una institución con nombres de estado, municipio, parroquia y ciudad
     */
    public static function listarPorInstitucion($db, $institucion_id) {
        if (!$db) return [];
        $cleanInstId = (int)$institucion_id;

        try {
            $query = "SELECT s.id, s.institucion_id, s.ciudad_id, s.parroquia_id, s.detalles,
                             c.ciudad, p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                      FROM sedes s
                      LEFT JOIN ciudades c ON s.ciudad_id = c.id_ciudad
                      LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                      LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                      LEFT JOIN estados est ON m.id_estado = est.id_estado
                      WHERE s.institucion_id = :institucion_id
                      ORDER BY s.id ASC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":institucion_id", $cleanInstId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Sede::listarPorInstitucion: " . $e->getMessage());
            return [];
        }
    }
}
