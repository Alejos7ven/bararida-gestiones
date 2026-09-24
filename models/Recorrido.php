<?php
require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/Ruta.php";

/**
 * Modelo de Recorrido
 * Representa la entidad Recorrido y gestiona su persistencia con transacciones atómicas.
 * Hereda de Database para gestionar internamente la conexión.
 */
class Recorrido extends Database {
    private string $table_name = "recorrido";

    // Propiedades de la entidad
    private ?int $id = null;
    private ?string $nombre = null;
    private ?int $activo = 1;
    private array $zonas = [];

    /**
     * Constructor del Modelo Recorrido
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

    public function getActivo(): ?int {
        return $this->activo;
    }

    public function setActivo(?int $activo): void {
        $this->activo = $activo;
    }

    public function getZonas(): array {
        return $this->zonas;
    }

    public function setZonas(array $zonas): void {
        $this->zonas = $zonas;
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS Y LÓGICA DE TRANSACCIONES
    // =========================================================================

    /**
     * Registra un recorrido y todas sus paradas de forma atómica usando Transacciones PDO.
     * Utiliza $this->nombre y $this->zonas.
     * @param array|null $zonasOrdenadas Opcional para retrocompatibilidad
     * @return bool True en caso de éxito, False de lo contrario
     * @throws Exception Si ocurre un fallo en la base de datos
     */
    public function crear(?array $zonasOrdenadas = null): bool {
        if ($zonasOrdenadas !== null) {
            $this->zonas = $zonasOrdenadas;
        }

        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            // Iniciamos la transacción atómica
            $this->db->beginTransaction();

            // 1. Insertar el Recorrido
            $query = "INSERT INTO " . $this->table_name . " (nombre) VALUES (:nombre)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al insertar el registro de recorrido.");
            }

            $recorridoId = (int)$this->db->lastInsertId();
            $this->id = $recorridoId;

            // 2. Insertar cada parada en la tabla 'rutas' con su orden correspondiente
            foreach ($this->zonas as $orden => $zonaId) {
                $ruta = new Ruta();
                $ruta->setRecorridoId($recorridoId);
                $ruta->setZonaId((int)$zonaId);
                $ruta->setOrden((int)$orden);

                if (!$ruta->crear()) {
                    throw new Exception("Error al insertar la parada de zona con ID $zonaId en la ruta.");
                }
            }

            // Confirmamos la transacción
            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en Recorrido::crear(): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lista todos los recorridos con sus zonas de forma anidada y eficiente.
     * @return array Listado de recorridos
     */
    public function listarTodos(): array {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        // Obtener todos los recorridos activos que tengan al menos una parada asignada en la ruta
        $query = "SELECT r.id, r.nombre FROM " . $this->table_name . " r 
                  WHERE r.activo = 1 AND EXISTS (SELECT 1 FROM rutas ru WHERE ru.recorrido_id = r.id)
                  ORDER BY r.id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $recorridos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Para cada recorrido, cargar sus paradas (rutas y nombres de zonas) en orden
        $rutaModel = new Ruta();

        foreach ($recorridos as $key => $r) {
            $rutaModel->setRecorridoId((int)$r['id']);
            $recorridos[$key]['paradas'] = $rutaModel->obtenerPorRecorrido();
        }

        return $recorridos;
    }

    /**
     * Obtiene los datos de un recorrido con sus paradas por su ID utilizando $this->id.
     * @param int|null $id Opcional para retrocompatibilidad
     * @return array|null Datos del recorrido con paradas, o null si no existe
     */
    public function obtenerPorId(?int $id = null): ?array {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return null;
        }

        $query = "SELECT id, nombre FROM " . $this->table_name . " WHERE id = :id AND activo = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $recorrido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($recorrido) {
            $this->nombre = $recorrido['nombre'];
            $rutaModel = new Ruta();
            $rutaModel->setRecorridoId($this->id);
            $recorrido['paradas'] = $rutaModel->obtenerPorRecorrido();
            return $recorrido;
        }

        return null;
    }

    /**
     * Obtiene el nombre del recorrido por su ID configurado en $this->id.
     */
    public function obtenerNombre(?int $id = null): ?string {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT nombre FROM " . $this->table_name . " WHERE id = :id AND activo = 1 LIMIT 1");
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $nombre = $stmt->fetchColumn();
        if ($nombre) {
            $this->nombre = (string)$nombre;
            return (string)$nombre;
        }
        return null;
    }

    /**
     * Elimina un recorrido por ID de forma lógica (Soft Delete) usando $this->id.
     * Preserva las paradas en 'rutas' para no corromper citas históricas.
     * 
     * @param int|null $id Opcional para retrocompatibilidad
     * @return bool True si se eliminó correctamente
     */
    public function eliminar(?int $id = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de recorrido no inicializado.");
        }

        try {
            $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error en Recorrido::eliminar() lógico: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza la ruta (secuencia de paradas) de un recorrido en una transacción atómica.
     * Utiliza $this->id y $this->zonas.
     * @param int|null $recorridoId Opcional para retrocompatibilidad
     * @param array|null $zonasOrdenadas Opcional para retrocompatibilidad
     * @return bool True en caso de éxito, False de lo contrario
     * @throws Exception Si ocurre un fallo en la base de datos
     */
    public function actualizarRuta(?int $recorridoId = null, ?array $zonasOrdenadas = null): bool {
        if ($recorridoId !== null) {
            $this->id = $recorridoId;
        }
        if ($zonasOrdenadas !== null) {
            $this->zonas = $zonasOrdenadas;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de recorrido no inicializado.");
        }

        try {
            $this->db->beginTransaction();

            // 1. Eliminar las paradas asociadas en la tabla 'rutas' primero
            $ruta = new Ruta();
            $ruta->setRecorridoId($this->id);
            $ruta->eliminarPorRecorrido();

            // 2. Insertar cada parada en la tabla 'rutas' con el nuevo orden
            foreach ($this->zonas as $orden => $zonaId) {
                $nuevaRuta = new Ruta();
                $nuevaRuta->setRecorridoId($this->id);
                $nuevaRuta->setZonaId((int)$zonaId);
                $nuevaRuta->setOrden((int)$orden);

                if (!$nuevaRuta->crear()) {
                    throw new Exception("Error al insertar la parada de zona con ID $zonaId en la ruta.");
                }
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en Recorrido::actualizarRuta(): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza el nombre y la secuencia de paradas de un recorrido en una transacción atómica.
     * Utiliza $this->id, $this->nombre y $this->zonas.
     * @param int|null $recorridoId Opcional para retrocompatibilidad
     * @param string|null $nombre Opcional para retrocompatibilidad
     * @param array|null $zonasOrdenadas Opcional para retrocompatibilidad
     * @return bool True en caso de éxito, False de lo contrario
     * @throws Exception Si ocurre un fallo en la base de datos
     */
    public function actualizarNombreYListadoZonas(?int $recorridoId = null, ?string $nombre = null, ?array $zonasOrdenadas = null): bool {
        if ($recorridoId !== null) {
            $this->id = $recorridoId;
        }
        if ($nombre !== null) {
            $this->nombre = $nombre;
        }
        if ($zonasOrdenadas !== null) {
            $this->zonas = $zonasOrdenadas;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de recorrido no inicializado.");
        }

        try {
            $this->db->beginTransaction();

            // 1. Actualizar el nombre en la tabla 'recorrido'
            $queryRec = "UPDATE " . $this->table_name . " SET nombre = :nombre WHERE id = :id";
            $stmtRec = $this->db->prepare($queryRec);
            $stmtRec->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
            $stmtRec->bindParam(":id", $this->id, PDO::PARAM_INT);
            $stmtRec->execute();

            // 2. Eliminar las paradas antiguas
            $ruta = new Ruta();
            $ruta->setRecorridoId($this->id);
            $ruta->eliminarPorRecorrido();

            // 3. Insertar las nuevas paradas en el nuevo orden
            foreach ($this->zonas as $orden => $zonaId) {
                $nuevaRuta = new Ruta();
                $nuevaRuta->setRecorridoId($this->id);
                $nuevaRuta->setZonaId((int)$zonaId);
                $nuevaRuta->setOrden((int)$orden);

                if (!$nuevaRuta->crear()) {
                    throw new Exception("Error al insertar la parada de zona con ID $zonaId en la ruta.");
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en Recorrido::actualizarNombreYListadoZonas(): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene una lista simple (id y nombre) ordenada alfabéticamente para selectores
     * @return array Listado simple de recorridos
     */
    public function obtenerListadoSimple(): array {
        if (!$this->db) return [];
        $query = "SELECT r.id, r.nombre FROM " . $this->table_name . " r 
                  WHERE r.activo = 1 AND EXISTS (SELECT 1 FROM rutas ru WHERE ru.recorrido_id = r.id)
                  ORDER BY r.nombre ASC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
