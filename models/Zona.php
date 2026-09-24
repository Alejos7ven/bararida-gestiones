<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo de Zona
 * Representa la entidad Zona y gestiona su persistencia.
 * Hereda de Database para gestionar internamente la conexión.
 */
class Zona extends Database {
    private string $table_name = "zonas";

    // Propiedades de la entidad
    private ?int $id = null;
    private ?string $nombre = null;
    private ?string $coordenadas = null;
    private ?int $activo = 1;

    /**
     * Constructor del Modelo Zona
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

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): void {
        $this->nombre = $nombre ? trim($nombre) : null;
    }

    public function getCoordenadas(): ?string {
        return $this->coordenadas;
    }

    public function setCoordenadas(?string $coordenadas): void {
        $this->coordenadas = $coordenadas ? trim($coordenadas) : null;
    }

    public function getActivo(): ?int {
        return $this->activo;
    }

    public function setActivo(?int $activo): void {
        $this->activo = $activo;
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS (CRUD)
    // =========================================================================

    /**
     * Registra una nueva zona en la base de datos de forma segura.
     * Utiliza las propiedades $this->nombre y $this->coordenadas.
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function crear(): bool {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "INSERT INTO " . $this->table_name . " (nombre, coordenadas) VALUES (:nombre, :coordenadas)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
        $stmt->bindParam(":coordenadas", $this->coordenadas, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $this->id = (int)$this->db->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Obtiene todas las zonas del sistema.
     * @return array Listado de zonas
     */
    public function listarTodas(): array {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "SELECT id, nombre, coordenadas FROM " . $this->table_name . " WHERE activo = 1 ORDER BY id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los datos de una zona por su ID utilizando la propiedad $this->id.
     * @param int|null $id Opcional para retrocompatibilidad
     * @return array|null Datos de la zona o null si no se encuentra
     */
    public function obtenerPorId(?int $id = null): ?array {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return null;
        }

        $query = "SELECT id, nombre, coordenadas FROM " . $this->table_name . " WHERE id = :id AND activo = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->nombre = $row['nombre'];
            $this->coordenadas = $row['coordenadas'];
            return $row;
        }
        return null;
    }

    /**
     * Elimina lógicamente una zona por su ID (soft delete) usando $this->id.
     * @param int|null $id Opcional para retrocompatibilidad
     * @return bool True si se desactivó con éxito, False de lo contrario
     */
    public function eliminar(?int $id = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de zona no inicializado.");
        }

        $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Actualiza las coordenadas de una zona usando $this->id y $this->coordenadas.
     * @param int|null $id Opcional para retrocompatibilidad
     * @param string|null $coordenadas Opcional para retrocompatibilidad
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function actualizarCoordenadas(?int $id = null, ?string $coordenadas = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }
        if ($coordenadas !== null) {
            $this->coordenadas = $coordenadas;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de zona no inicializado.");
        }

        $query = "UPDATE " . $this->table_name . " SET coordenadas = :coordenadas WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":coordenadas", $this->coordenadas, PDO::PARAM_STR);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Actualiza el nombre y las coordenadas de una zona usando $this->id, $this->nombre y $this->coordenadas.
     * @param int|null $id Opcional para retrocompatibilidad
     * @param string|null $nombre Opcional para retrocompatibilidad
     * @param string|null $coordenadas Opcional para retrocompatibilidad
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function actualizarNombreYCoordenadas(?int $id = null, ?string $nombre = null, ?string $coordenadas = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }
        if ($nombre !== null) {
            $this->nombre = $nombre;
        }
        if ($coordenadas !== null) {
            $this->coordenadas = $coordenadas;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de zona no inicializado.");
        }

        $query = "UPDATE " . $this->table_name . " SET nombre = :nombre, coordenadas = :coordenadas WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
        $stmt->bindParam(":coordenadas", $this->coordenadas, PDO::PARAM_STR);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
