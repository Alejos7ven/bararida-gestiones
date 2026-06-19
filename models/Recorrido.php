<?php
/**
 * Modelo de Recorrido
 * Representa la entidad Recorrido y gestiona su persistencia con transacciones atómicas.
 */
class Recorrido {
    private $db;
    private $table_name = "recorrido";

    // Propiedades de la entidad
    private $id;
    private $nombre;

    /**
     * Constructor del Modelo Recorrido
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

    public function getNombre() {
        return $this->nombre;
    }

    public function setNombre($nombre) {
        $cleanNombre = strip_tags(trim($nombre));
        if (!empty($cleanNombre) && strlen($cleanNombre) <= 100) {
            $this->nombre = $cleanNombre;
        } else {
            throw new InvalidArgumentException("El nombre del recorrido es inválido o supera los 100 caracteres.");
        }
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS Y LÓGICA DE TRANSACCIONES
    // =========================================================================

    /**
     * Registra un recorrido y todas sus paradas de forma atómica usando Transacciones PDO.
     * @param array $zonasOrdenadas Listado secuencial de IDs de zonas
     * @return bool True en caso de éxito, False de lo contrario
     * @throws Exception Si ocurre un fallo en la base de datos
     */
    public function crear($zonasOrdenadas) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        if (empty($this->nombre)) {
            throw new InvalidArgumentException("El nombre del recorrido es obligatorio.");
        }

        if (empty($zonasOrdenadas) || !is_array($zonasOrdenadas)) {
            throw new InvalidArgumentException("Debe seleccionar al menos una zona para crear un recorrido.");
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

            $recorridoId = $this->db->lastInsertId();
            $this->id = $recorridoId;

            // 2. Insertar cada parada en la tabla 'rutas' con su orden correspondiente
            require_once __DIR__ . "/Ruta.php";
            foreach ($zonasOrdenadas as $orden => $zonaId) {
                $ruta = new Ruta($this->db);
                $ruta->setRecorridoId($recorridoId);
                $ruta->setZonaId($zonaId);
                $ruta->setOrden($orden);

                if (!$ruta->crear()) {
                    throw new Exception("Error al insertar la parada de zona con ID $zonaId en la ruta.");
                }
            }

            // Confirmamos la transacción
            $this->db->commit();
            return true;

        } catch (Exception $e) {
            // Si algo falla, revertimos todos los cambios para evitar inconsistencias
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
    public function listarTodos() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        // Obtener todos los recorridos que tengan al menos una parada asignada en la ruta
        $query = "SELECT r.id, r.nombre FROM " . $this->table_name . " r 
                  WHERE EXISTS (SELECT 1 FROM rutas ru WHERE ru.recorrido_id = r.id)
                  ORDER BY r.id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $recorridos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Para cada recorrido, cargar sus paradas (rutas y nombres de zonas) en orden
        require_once __DIR__ . "/Ruta.php";
        $rutaModel = new Ruta($this->db);

        foreach ($recorridos as $key => $r) {
            $recorridos[$key]['paradas'] = $rutaModel->obtenerPorRecorrido($r['id']);
        }

        return $recorridos;
    }

    /**
     * Obtiene los datos de un recorrido con sus paradas por su ID.
     * @param PDO $db Instancia de conexión PDO
     * @param int $id ID del recorrido
     * @return array|null Datos del recorrido con paradas, o null si no existe
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) {
            return null;
        }

        $query = "SELECT id, nombre FROM recorrido WHERE id = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $recorrido = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($recorrido) {
            require_once __DIR__ . "/Ruta.php";
            $rutaModel = new Ruta($db);
            $recorrido['paradas'] = $rutaModel->obtenerPorRecorrido($id);
            return $recorrido;
        }

        return null;
    }

    /**
     * Elimina un recorrido y sus paradas asociadas por ID.
     * (El borrado en cascada configurado en las llaves foráneas de la base de datos
     * eliminará automáticamente los registros en la tabla 'rutas').
     * 
     * @param int $id ID del recorrido a eliminar
     * @return bool True si se eliminó correctamente
     */
    public function eliminar($id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $cleanId = filter_var($id, FILTER_VALIDATE_INT) ? (int)$id : null;
        if (!$cleanId) {
            throw new InvalidArgumentException("ID de recorrido inválido.");
        }

        try {
            $this->db->beginTransaction();

            // 1. Eliminar las paradas asociadas en la tabla 'rutas' primero
            require_once __DIR__ . "/Ruta.php";
            $ruta = new Ruta($this->db);
            $ruta->eliminarPorRecorrido($cleanId);

            // 2. Eliminar el recorrido principal
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":id", $cleanId, PDO::PARAM_INT);
            $stmt->execute();

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en Recorrido::eliminar(): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza la ruta (secuencia de paradas) de un recorrido en una transacción atómica.
     * @param int $recorridoId ID del recorrido
     * @param array $zonasOrdenadas Listado ordenado de IDs de zonas
     * @return bool True en caso de éxito, False de lo contrario
     * @throws Exception Si ocurre un fallo en la base de datos
     */
    public function actualizarRuta($recorridoId, $zonasOrdenadas) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $cleanRecorridoId = filter_var($recorridoId, FILTER_VALIDATE_INT) ? (int)$recorridoId : null;
        if (!$cleanRecorridoId) {
            throw new InvalidArgumentException("ID de recorrido inválido.");
        }

        if (empty($zonasOrdenadas) || !is_array($zonasOrdenadas)) {
            throw new InvalidArgumentException("Debe seleccionar al menos una zona para actualizar el recorrido.");
        }

        try {
            // Iniciar la transacción atómica
            $this->db->beginTransaction();

            // 1. Eliminar las paradas asociadas en la tabla 'rutas' primero
            require_once __DIR__ . "/Ruta.php";
            $ruta = new Ruta($this->db);
            $ruta->eliminarPorRecorrido($cleanRecorridoId);

            // 2. Insertar cada parada en la tabla 'rutas' con el nuevo orden
            foreach ($zonasOrdenadas as $orden => $zonaId) {
                $cleanZonaId = filter_var($zonaId, FILTER_VALIDATE_INT) ? (int)$zonaId : null;
                if (!$cleanZonaId) {
                    throw new InvalidArgumentException("ID de zona inválido en la lista de paradas.");
                }

                $nuevaRuta = new Ruta($this->db);
                $nuevaRuta->setRecorridoId($cleanRecorridoId);
                $nuevaRuta->setZonaId($cleanZonaId);
                $nuevaRuta->setOrden($orden);

                if (!$nuevaRuta->crear()) {
                    throw new Exception("Error al insertar la parada de zona con ID $cleanZonaId en la ruta.");
                }
            }

            // Confirmamos la transacción
            $this->db->commit();
            return true;

        } catch (Exception $e) {
            // Si algo falla, revertimos todos los cambios para evitar inconsistencias
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en Recorrido::actualizarRuta(): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza el nombre y la secuencia de paradas de un recorrido en una transacción atómica.
     * @param int $recorridoId ID del recorrido
     * @param string $nombre Nuevo nombre del recorrido
     * @param array $zonasOrdenadas Listado ordenado de IDs de zonas
     * @return bool True en caso de éxito, False de lo contrario
     * @throws Exception Si ocurre un fallo en la base de datos
     */
    public function actualizarNombreYListadoZonas($recorridoId, $nombre, $zonasOrdenadas) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $cleanRecorridoId = filter_var($recorridoId, FILTER_VALIDATE_INT) ? (int)$recorridoId : null;
        if (!$cleanRecorridoId) {
            throw new InvalidArgumentException("ID de recorrido inválido.");
        }

        $cleanNombre = strip_tags(trim($nombre));
        if (empty($cleanNombre) || strlen($cleanNombre) > 100) {
            throw new InvalidArgumentException("El nombre del recorrido es inválido o supera los 100 caracteres.");
        }

        if (empty($zonasOrdenadas) || !is_array($zonasOrdenadas)) {
            throw new InvalidArgumentException("Debe seleccionar al menos una zona para el recorrido.");
        }

        try {
            $this->db->beginTransaction();

            // 1. Actualizar el nombre en la tabla 'recorrido'
            $queryRec = "UPDATE " . $this->table_name . " SET nombre = :nombre WHERE id = :id";
            $stmtRec = $this->db->prepare($queryRec);
            $stmtRec->bindParam(":nombre", $cleanNombre, PDO::PARAM_STR);
            $stmtRec->bindParam(":id", $cleanRecorridoId, PDO::PARAM_INT);
            $stmtRec->execute();

            // 2. Eliminar las paradas antiguas
            require_once __DIR__ . "/Ruta.php";
            $ruta = new Ruta($this->db);
            $ruta->eliminarPorRecorrido($cleanRecorridoId);

            // 3. Insertar las nuevas paradas en el nuevo orden
            foreach ($zonasOrdenadas as $orden => $zonaId) {
                $cleanZonaId = filter_var($zonaId, FILTER_VALIDATE_INT) ? (int)$zonaId : null;
                if (!$cleanZonaId) {
                    throw new InvalidArgumentException("ID de zona inválido en la lista de paradas.");
                }

                $nuevaRuta = new Ruta($this->db);
                $nuevaRuta->setRecorridoId($cleanRecorridoId);
                $nuevaRuta->setZonaId($cleanZonaId);
                $nuevaRuta->setOrden($orden);

                if (!$nuevaRuta->crear()) {
                    throw new Exception("Error al insertar la parada de zona con ID $cleanZonaId en la ruta.");
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
     * @param PDO $db Instancia de conexión PDO
     * @return array Listado simple de recorridos
     */
    public static function obtenerListadoSimple($db) {
        if (!$db) return [];
        $query = "SELECT r.id, r.nombre FROM recorrido r 
                  WHERE EXISTS (SELECT 1 FROM rutas ru WHERE ru.recorrido_id = r.id)
                  ORDER BY r.nombre ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

