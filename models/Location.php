<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo de Localización Geográfica (Location)
 * Representa las entidades geográficas (estados, municipios, parroquias, ciudades)
 * y gestiona su consulta en la base de datos conforme al patrón MVC.
 */
class Location extends Database {
    private ?int $estadoId = null;
    private ?int $municipioId = null;

    public function __construct() {
        parent::__construct();
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getEstadoId(): ?int {
        return $this->estadoId;
    }

    public function setEstadoId(?int $estadoId): void {
        $this->estadoId = $estadoId;
    }

    public function getMunicipioId(): ?int {
        return $this->municipioId;
    }

    public function setMunicipioId(?int $municipioId): void {
        $this->municipioId = $municipioId;
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS
    // =========================================================================

    /**
     * Obtiene el listado de todos los estados ordenados alfabéticamente.
     */
    public function obtenerEstados(): array {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }
        $stmt = $this->db->prepare("SELECT id_estado, estado FROM estados ORDER BY estado ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los municipios correspondientes al estado configurado en $this->estadoId.
     */
    public function obtenerMunicipios(): array {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }
        if (!$this->estadoId) {
            throw new InvalidArgumentException("ID de estado no configurado.");
        }

        $stmt = $this->db->prepare("SELECT id_municipio, municipio FROM municipios WHERE id_estado = :estado_id ORDER BY municipio ASC");
        $stmt->execute([':estado_id' => $this->estadoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las parroquias correspondientes al municipio configurado en $this->municipioId.
     */
    public function obtenerParroquias(): array {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }
        if (!$this->municipioId) {
            throw new InvalidArgumentException("ID de municipio no configurado.");
        }

        $stmt = $this->db->prepare("SELECT id_parroquia, parroquia FROM parroquias WHERE id_municipio = :municipio_id ORDER BY parroquia ASC");
        $stmt->execute([':municipio_id' => $this->municipioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las ciudades correspondientes al estado configurado en $this->estadoId.
     */
    public function obtenerCiudades(): array {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }
        if (!$this->estadoId) {
            throw new InvalidArgumentException("ID de estado no configurado.");
        }

        $stmt = $this->db->prepare("SELECT id_ciudad, ciudad FROM ciudades WHERE id_estado = :estado_id ORDER BY ciudad ASC");
        $stmt->execute([':estado_id' => $this->estadoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
