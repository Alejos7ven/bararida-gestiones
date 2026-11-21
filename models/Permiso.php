<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo Permiso
 * Consulta y actualiza los permisos de acceso por módulo asignados a un rol desde la base de datos.
 */
class Permiso extends Database {
    private ?int $rolId = null;
    private ?int $moduloId = null;
    private ?int $puedeAcceder = null;
    private ?string $rolNombre = null;

    /**
     * Constructor del Modelo Permiso
     * Hereda la conexión PDO desde Database
     */
    public function __construct() {
        parent::__construct();
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getRolId(): ?int {
        return $this->rolId;
    }

    public function setRolId(?int $rolId): void {
        $this->rolId = $rolId;
    }

    public function getModuloId(): ?int {
        return $this->moduloId;
    }

    public function setModuloId(?int $moduloId): void {
        $this->moduloId = $moduloId;
    }

    public function getPuedeAcceder(): ?int {
        return $this->puedeAcceder;
    }

    public function setPuedeAcceder(?int $puedeAcceder): void {
        $this->puedeAcceder = $puedeAcceder;
    }

    public function getRolNombre(): ?string {
        return $this->rolNombre;
    }

    public function setRolNombre(?string $rolNombre): void {
        $this->rolNombre = $rolNombre;
    }

    // Cache en memoria por petición para evitar consultas redundantes de permisos
    private static array $cachePermisos = [];

    /**
     * Limpia la cache estática en memoria de permisos
     */
    public static function limpiarCache(): void {
        self::$cachePermisos = [];
    }

    /**
     * Verifica de forma dinámica y sincronizada con la BD si un rol tiene acceso a un módulo.
     * El superadmin siempre posee acceso total a todos los módulos.
     * Para cualquier otro rol, consulta la tabla de permisos en la base de datos.
     *
     * @param int|null    $rolId     ID del rol
     * @param string      $modulo    Nombre del módulo a validar
     * @param string|null $rolNombre Nombre del rol (opcional para fallback)
     * @return bool True si tiene permiso, False si está denegado
     */
    public function tieneAccesoModulo(?int $rolId, string $modulo, ?string $rolNombre = null): bool {
        $modulo = strtolower(trim($modulo));
        if ($modulo === '') {
            return false;
        }

        if (!$this->db) {
            return false;
        }

        // Si no se tiene rolId pero sí rolNombre, resolverlo dinámicamente desde la BD
        if (($rolId === null || $rolId <= 0) && !empty($rolNombre)) {
            $rolId = $this->obtenerRolIdPorNombre($rolNombre);
        }

        if ($rolId === null || $rolId <= 0) {
            return false;
        }

        // Consultar nombre del rol si no fue suministrado
        if (empty($rolNombre)) {
            try {
                $stmtRol = $this->db->prepare("SELECT LOWER(nombre) FROM roles WHERE id = :rol_id LIMIT 1");
                $stmtRol->bindParam(":rol_id", $rolId, PDO::PARAM_INT);
                $stmtRol->execute();
                $rolNombre = (string)$stmtRol->fetchColumn();
            } catch (PDOException $e) {
                error_log("Error al consultar nombre de rol en tieneAccesoModulo: " . $e->getMessage());
            }
        }

        // Superadmin siempre tiene acceso irrestricto
        if (strtolower((string)$rolNombre) === 'superadmin' || $rolId === 1) {
            return true;
        }

        // Módulo 'dashboard' siempre está permitido a cualquier usuario autenticado
        if ($modulo === 'dashboard') {
            return true;
        }

        // Módulo de configuración 'app' es exclusivo para superadmin
        if ($modulo === 'app') {
            return false;
        }

        // Cargar y almacenar en cache de la petición los permisos del rol
        if (!isset(self::$cachePermisos[$rolId])) {
            try {
                self::$cachePermisos[$rolId] = $this->getPermisosPorRol($rolId);
            } catch (Exception $e) {
                error_log("Error al cargar permisos en tieneAccesoModulo: " . $e->getMessage());
                return false;
            }
        }

        return !empty(self::$cachePermisos[$rolId][$modulo]);
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS
    // =========================================================================

    /**
     * Obtiene el ID de un rol a partir de su nombre.
     * @param string|null $rolNombre Opcional para retrocompatibilidad
     * @return int|null
     */
    public function obtenerRolIdPorNombre(?string $rolNombre = null): ?int {
        if ($rolNombre !== null) {
            $this->rolNombre = $rolNombre;
        }

        if (!$this->db || empty($this->rolNombre)) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
            $stmt->bindParam(":nombre", $this->rolNombre, PDO::PARAM_STR);
            $stmt->execute();
            $id = $stmt->fetchColumn();
            if ($id !== false && $id !== null) {
                $this->rolId = (int)$id;
                return (int)$id;
            }
            return null;
        } catch (PDOException $e) {
            error_log("Error en Permiso::obtenerRolIdPorNombre(): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene todos los permisos de acceso para el rol configurado en $this->rolId.
     * Retorna un array asociativo donde la clave es el nombre del módulo
     * y el valor es un booleano indicando si puede acceder.
     *
     * @param int|null $rolId Opcional para retrocompatibilidad
     * @return array Array asociativo de permisos por nombre de módulo
     * @throws Exception Si la conexión no está disponible
     */
    public function getPermisosPorRol(?int $rolId = null): array {
        if ($rolId !== null) {
            $this->rolId = $rolId;
        }

        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada en Permiso.");
        }

        if (!$this->rolId) {
            return [];
        }

        $resultado = [];

        try {
            $query = "SELECT m.nombre, p.puede_acceder
                      FROM permisos p
                      INNER JOIN modulos m ON p.modulo_id = m.id
                      WHERE p.rol_id = :rol_id
                      ORDER BY m.id ASC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":rol_id", $this->rolId, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $resultado[$row['nombre']] = (bool)$row['puede_acceder'];
            }
        } catch (PDOException $e) {
            error_log("Excepción en Permiso::getPermisosPorRol(): " . $e->getMessage());
            throw new Exception("Error al consultar los permisos del usuario.");
        }

        return $resultado;
    }

    /**
     * Obtiene la matriz completa de roles × módulos para la vista de gestión de permisos.
     * Excluye los módulos internos (app, dashboard) de la lista editable.
     *
     * @param array $excluir Array de nombres de módulos a excluir de la tabla editable
     * @return array Matriz completa de permisos
     * @throws Exception Si la conexión no está disponible
     */
    public function getMatrizCompleta(array $excluir = ['app', 'dashboard']): array {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada en Permiso.");
        }

        try {
            // Obtener todos los roles
            $stmtRoles = $this->db->prepare("SELECT id, nombre FROM roles ORDER BY id ASC");
            $stmtRoles->execute();
            $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

            // Construir cláusula de exclusión de módulos
            $placeholders = implode(',', array_fill(0, count($excluir), '?'));
            $stmtModulos  = $this->db->prepare(
                "SELECT id, nombre, label FROM modulos WHERE nombre NOT IN ($placeholders) ORDER BY id ASC"
            );
            $stmtModulos->execute($excluir);
            $modulos = $stmtModulos->fetchAll(PDO::FETCH_ASSOC);

            // Obtener todos los permisos existentes
            $stmtPermisos = $this->db->prepare(
                "SELECT p.rol_id, p.modulo_id, p.puede_acceder
                 FROM permisos p
                 INNER JOIN modulos m ON p.modulo_id = m.id
                 WHERE m.nombre NOT IN ($placeholders)"
            );
            $stmtPermisos->execute($excluir);
            $rows = $stmtPermisos->fetchAll(PDO::FETCH_ASSOC);

            // Indexar permisos como [rol_id][modulo_id] => bool
            $permisos = [];
            foreach ($rows as $row) {
                $permisos[(int)$row['rol_id']][(int)$row['modulo_id']] = (bool)$row['puede_acceder'];
            }

            return compact('roles', 'modulos', 'permisos');
        } catch (PDOException $e) {
            error_log("Excepción en Permiso::getMatrizCompleta(): " . $e->getMessage());
            throw new Exception("Error al construir la matriz de permisos.");
        }
    }

    /**
     * Inserta o actualiza un permiso individual para un rol y módulo dados.
     * Utiliza las propiedades $this->rolId, $this->moduloId y $this->puedeAcceder.
     *
     * @param int|null  $rol_id        Opcional para retrocompatibilidad
     * @param int|null  $modulo_id     Opcional para retrocompatibilidad
     * @param bool|int|null $puede_acceder Opcional para retrocompatibilidad
     * @return bool True si la operación fue exitosa
     * @throws Exception Si la conexión no está disponible o hay error de BD
     */
    public function actualizarPermiso(?int $rol_id = null, ?int $modulo_id = null, $puede_acceder = null): bool {
        if ($rol_id !== null) {
            $this->rolId = $rol_id;
        }
        if ($modulo_id !== null) {
            $this->moduloId = $modulo_id;
        }
        if ($puede_acceder !== null) {
            $this->puedeAcceder = (int)$puede_acceder;
        }

        if (!$this->db || $this->rolId === null || $this->moduloId === null || $this->puedeAcceder === null) {
            return false;
        }

        try {
            $query = "INSERT INTO permisos (rol_id, modulo_id, puede_acceder)
                      VALUES (:rol_id, :modulo_id, :puede_acceder)
                      ON DUPLICATE KEY UPDATE puede_acceder = :puede_acceder_upd";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":rol_id",            $this->rolId,        PDO::PARAM_INT);
            $stmt->bindValue(":modulo_id",         $this->moduloId,     PDO::PARAM_INT);
            $stmt->bindValue(":puede_acceder",     $this->puedeAcceder, PDO::PARAM_INT);
            $stmt->bindValue(":puede_acceder_upd", $this->puedeAcceder, PDO::PARAM_INT);
            $resultado = $stmt->execute();
            self::limpiarCache();
            return $resultado;
        } catch (PDOException $e) {
            error_log("Excepción en Permiso::actualizarPermiso(): " . $e->getMessage());
            throw new Exception("Error al actualizar el permiso.");
        }
    }
}
