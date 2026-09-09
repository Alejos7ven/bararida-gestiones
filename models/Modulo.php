<?php
/**
 * Modelo Modulo
 * Representa la entidad 'modulos' de la base de datos.
 * Maneja el catálogo de módulos del sistema conforme al patrón MVC.
 */
class Modulo {
    private $db;
    private $table = 'modulos';

    // Propiedades de la entidad
    public $id;
    public $nombre;
    public $label;

    /**
     * Constructor del modelo Modulo
     * @param PDO $db Instancia de conexión PDO
     */
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Obtiene todos los módulos ordenados por su ID ascendente.
     * @return array Array con todos los módulos registrados
     */
    public function getAll() {
        if (!$this->db) {
            return [];
        }

        try {
            $query = "SELECT id, nombre, label FROM " . $this->table . " ORDER BY id ASC";
            $stmt  = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Modulo::getAll(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene únicamente la lista de nombres (slugs) de módulos válidos registrados en BD.
     * Ejemplo: ['dashboard', 'citas', 'instituciones', 'recorridos', 'visitantes', 'personal', 'app', 'reportes']
     * @return array Array de strings con los nombres válidos
     */
    public function getNombresValidos() {
        if (!$this->db) {
            return [];
        }

        try {
            $query = "SELECT nombre FROM " . $this->table . " ORDER BY id ASC";
            $stmt  = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error en Modulo::getNombresValidos(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene un módulo por su ID primario.
     * @param int $id
     * @return array|false
     */
    public function getById($id) {
        if (!$this->db) {
            return false;
        }

        try {
            $query = "SELECT id, nombre, label FROM " . $this->table . " WHERE id = :id LIMIT 1";
            $stmt  = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Modulo::getById(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene un módulo por su nombre identificador (slug).
     * @param string $nombre
     * @return array|false
     */
    public function getByNombre($nombre) {
        if (!$this->db) {
            return false;
        }

        try {
            $query = "SELECT id, nombre, label FROM " . $this->table . " WHERE nombre = :nombre LIMIT 1";
            $stmt  = $this->db->prepare($query);
            $stmt->bindParam(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Modulo::getByNombre(): " . $e->getMessage());
            return false;
        }
    }
}
