<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo Modulo
 * Representa la entidad 'modulos' de la base de datos.
 * Maneja el catálogo de módulos del sistema conforme al patrón MVC.
 */
class Modulo extends Database {
    private ?string $table = 'modulos';

    // Propiedades de la entidad
    private ?int $id = null;
    private ?string $nombre = null;
    private ?string $label = null;

    /**
     * Constructor del modelo Modulo
     * Hereda la conexión PDO desde Database
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
        $this->nombre = $nombre;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(?string $label): void {
        $this->label = $label;
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS
    // =========================================================================

    /**
     * Obtiene todos los módulos ordenados por su ID ascendente.
     * @return array Array con todos los módulos registrados
     */
    public function getAll(): array {
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
     * @return array Array de strings con los nombres válidos
     */
    public function getNombresValidos(): array {
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
     * Obtiene un módulo por su ID primario utilizando la propiedad $this->id.
     * @param int|null $id Opcional para retrocompatibilidad
     * @return array|false
     */
    public function getById(?int $id = null) {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return false;
        }

        try {
            $query = "SELECT id, nombre, label FROM " . $this->table . " WHERE id = :id LIMIT 1";
            $stmt  = $this->db->prepare($query);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                $this->nombre = $res['nombre'];
                $this->label = $res['label'];
            }
            return $res;
        } catch (PDOException $e) {
            error_log("Error en Modulo::getById(): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene un módulo por su nombre identificador (slug) utilizando la propiedad $this->nombre.
     * @param string|null $nombre Opcional para retrocompatibilidad
     * @return array|false
     */
    public function getByNombre(?string $nombre = null) {
        if ($nombre !== null) {
            $this->nombre = $nombre;
        }

        if (!$this->db || !$this->nombre) {
            return false;
        }

        try {
            $query = "SELECT id, nombre, label FROM " . $this->table . " WHERE nombre = :nombre LIMIT 1";
            $stmt  = $this->db->prepare($query);
            $stmt->bindParam(':nombre', $this->nombre, PDO::PARAM_STR);
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res) {
                $this->id = (int)$res['id'];
                $this->label = $res['label'];
            }
            return $res;
        } catch (PDOException $e) {
            error_log("Error en Modulo::getByNombre(): " . $e->getMessage());
            return false;
        }
    }
}
