<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo de Ruta
 * Representa la entidad Ruta (puntos y orden de un recorrido) y gestiona su persistencia.
 * Hereda de Database para gestionar internamente la conexión.
 */
class Ruta extends Database {
    private string $table_name = "rutas";

    // Propiedades de la entidad
    private ?int $id = null;
    private ?int $recorrido_id = null;
    private ?int $zona_id = null;
    private ?int $orden = null;

    /**
     * Constructor del Modelo Ruta
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

    public function getRecorridoId(): ?int {
        return $this->recorrido_id;
    }

    public function setRecorridoId(?int $recorrido_id): void {
        $this->recorrido_id = $recorrido_id;
    }

    public function getZonaId(): ?int {
        return $this->zona_id;
    }

    public function setZonaId(?int $zona_id): void {
        $this->zona_id = $zona_id;
    }

    public function getOrden(): ?int {
        return $this->orden;
    }

    public function setOrden(?int $orden): void {
        $this->orden = $orden;
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS
    // =========================================================================

    /**
     * Inserta una parada de recorrido en la base de datos de forma segura.
     * Utiliza $this->recorrido_id, $this->zona_id y $this->orden.
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function crear(): bool {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "INSERT INTO " . $this->table_name . " (recorrido_id, zona_id, orden) VALUES (:recorrido_id, :zona_id, :orden)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(":recorrido_id", $this->recorrido_id, PDO::PARAM_INT);
        $stmt->bindParam(":zona_id", $this->zona_id, PDO::PARAM_INT);
        $stmt->bindParam(":orden", $this->orden, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $this->id = (int)$this->db->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Obtiene el listado de paradas (rutas) asociadas a un recorrido específico en orden.
     * Utiliza la propiedad $this->recorrido_id.
     * @param int|null $recorrido_id Opcional para retrocompatibilidad
     * @return array Listado de zonas en la ruta
     */
    public function obtenerPorRecorrido(?int $recorrido_id = null): array {
        if ($recorrido_id !== null) {
            $this->recorrido_id = $recorrido_id;
        }

        if (!$this->db || !$this->recorrido_id) {
            return [];
        }

        $query = "SELECT r.id, r.recorrido_id, r.zona_id, r.orden, z.nombre as zona_nombre, z.coordenadas 
                  FROM " . $this->table_name . " r
                  INNER JOIN zonas z ON r.zona_id = z.id
                  WHERE r.recorrido_id = :recorrido_id 
                  ORDER BY r.orden ASC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":recorrido_id", $this->recorrido_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina todas las paradas (rutas) de un recorrido utilizando $this->recorrido_id.
     * @param int|null $recorrido_id Opcional para retrocompatibilidad
     * @return bool True si se eliminó con éxito
     */
    public function eliminarPorRecorrido(?int $recorrido_id = null): bool {
        if ($recorrido_id !== null) {
            $this->recorrido_id = $recorrido_id;
        }

        if (!$this->db || !$this->recorrido_id) {
            return false;
        }

        $query = "DELETE FROM " . $this->table_name . " WHERE recorrido_id = :recorrido_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":recorrido_id", $this->recorrido_id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
