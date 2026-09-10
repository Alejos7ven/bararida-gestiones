<?php
/**
 * Modelo de Zona
 * Representa la entidad Zona y gestiona su persistencia.
 */
class Zona {
    private $db;
    private $table_name = "zonas";

    // Propiedades de la entidad
    private $id;
    private $nombre;
    private $coordenadas;

    /**
     * Constructor del Modelo Zona
     * @param PDO $db Instancia de conexión PDO
     */
    public function __construct($db = null) {
        $this->db = $db;
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getNombre() {
        return $this->nombre;
    }

    public function setNombre($nombre) {
        $this->nombre = $nombre;
    }

    public function getCoordenadas() {
        return $this->coordenadas;
    }

    public function setCoordenadas($coordenadas) {
        $this->coordenadas = $coordenadas;
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS (CRUD)
    // =========================================================================

    /**
     * Registra una nueva zona en la base de datos de forma segura.
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function crear() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "INSERT INTO " . $this->table_name . " (nombre, coordenadas) VALUES (:nombre, :coordenadas)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
        $stmt->bindParam(":coordenadas", $this->coordenadas, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $this->id = $this->db->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Obtiene todas las zonas del sistema.
     * @return array Listado de zonas
     */
    public function listarTodas() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "SELECT id, nombre, coordenadas FROM " . $this->table_name . " WHERE activo = 1 ORDER BY id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los datos de una zona por su ID.
     * @param PDO $db Instancia de conexión PDO
     * @param int $id ID de la zona
     * @return array|null Datos de la zona o null si no se encuentra
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) {
            return null;
        }

        $query = "SELECT id, nombre, coordenadas FROM zonas WHERE id = :id AND activo = 1 LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * Elimina lógicamente una zona por su ID (soft delete).
     * @param int $id ID de la zona a eliminar
     * @return bool True si se desactivó con éxito, False de lo contrario
     */
    public function eliminar($id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Actualiza las coordenadas de una zona por su ID.
     * @param int $id ID de la zona
     * @param string $coordenadas Nuevas coordenadas en formato "X,Y"
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function actualizarCoordenadas($id, $coordenadas) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "UPDATE " . $this->table_name . " SET coordenadas = :coordenadas WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":coordenadas", $coordenadas, PDO::PARAM_STR);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Actualiza el nombre y las coordenadas de una zona por su ID.
     * @param int $id ID de la zona
     * @param string $nombre Nuevo nombre de la zona
     * @param string $coordenadas Nuevas coordenadas en formato "X,Y"
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function actualizarNombreYCoordenadas($id, $nombre, $coordenadas) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $query = "UPDATE " . $this->table_name . " SET nombre = :nombre, coordenadas = :coordenadas WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":nombre", $nombre, PDO::PARAM_STR);
        $stmt->bindParam(":coordenadas", $coordenadas, PDO::PARAM_STR);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

