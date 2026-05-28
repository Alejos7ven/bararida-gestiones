<?php
/**
 * Modelo de Ruta
 * Representa la entidad Ruta (puntos y orden de un recorrido) y gestiona su persistencia.
 */
class Ruta {
    private $db;
    private $table_name = "rutas";

    // Propiedades de la entidad
    private $id;
    private $recorrido_id;
    private $zona_id;
    private $orden;

    /**
     * Constructor del Modelo Ruta
     * @param PDO $db Instancia de conexión PDO
     */
    public function __construct($db = null) {
        $this->db = $db;
    }

    // =========================================================================
    // GETTERS Y SETTERS CON VALIDACIÓN
    // =========================================================================

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = filter_var($id, FILTER_VALIDATE_INT) ? (int)$id : null;
    }

    public function getRecorridoId() {
        return $this->recorrido_id;
    }

    public function setRecorridoId($recorrido_id) {
        $this->recorrido_id = filter_var($recorrido_id, FILTER_VALIDATE_INT) ? (int)$recorrido_id : null;
        if ($this->recorrido_id === null) {
            throw new InvalidArgumentException("ID de recorrido inválido.");
        }
    }

    public function getZonaId() {
        return $this->zona_id;
    }

    public function setZonaId($zona_id) {
        $this->zona_id = filter_var($zona_id, FILTER_VALIDATE_INT) ? (int)$zona_id : null;
        if ($this->zona_id === null) {
            throw new InvalidArgumentException("ID de zona inválido.");
        }
    }

    public function getOrden() {
        return $this->orden;
    }

    public function setOrden($orden) {
        $this->orden = (filter_var($orden, FILTER_VALIDATE_INT) !== false) ? (int)$orden : null;
        if ($this->orden === null || $this->orden < 0) {
            throw new InvalidArgumentException("El orden debe ser un número entero no negativo.");
        }
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS
    // =========================================================================

    /**
     * Inserta una parada de recorrido en la base de datos de forma segura.
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function crear() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        if (empty($this->recorrido_id) || empty($this->zona_id) || $this->orden === null) {
            throw new InvalidArgumentException("Los campos recorrido_id, zona_id y orden son requeridos.");
        }

        $query = "INSERT INTO " . $this->table_name . " (recorrido_id, zona_id, orden) VALUES (:recorrido_id, :zona_id, :orden)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(":recorrido_id", $this->recorrido_id, PDO::PARAM_INT);
        $stmt->bindParam(":zona_id", $this->zona_id, PDO::PARAM_INT);
        $stmt->bindParam(":orden", $this->orden, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Obtiene el listado de paradas (rutas) asociadas a un recorrido específico en orden.
     * @param int $recorrido_id ID del recorrido
     * @return array Listado de zonas en la ruta
     */
    public function obtenerPorRecorrido($recorrido_id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "SELECT r.id, r.recorrido_id, r.zona_id, r.orden, z.nombre as zona_nombre, z.coordenadas 
                  FROM " . $this->table_name . " r
                  INNER JOIN zonas z ON r.zona_id = z.id
                  WHERE r.recorrido_id = :recorrido_id 
                  ORDER BY r.orden ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":recorrido_id", $recorrido_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina todas las paradas (rutas) de un recorrido.
     * (Generalmente manejado por ON DELETE CASCADE en la BD, pero útil como respaldo).
     * @param int $recorrido_id ID del recorrido
     * @return bool True si se eliminó con éxito
     */
    public function eliminarPorRecorrido($recorrido_id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "DELETE FROM " . $this->table_name . " WHERE recorrido_id = :recorrido_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":recorrido_id", $recorrido_id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
