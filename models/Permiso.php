<?php
/**
 * Modelo Permiso
 * Consulta los permisos de acceso por módulo asignados a un rol desde la base de datos.
 */
class Permiso {
    // Conexión de base de datos (PDO)
    private $db;

    /**
     * Constructor del Modelo Permiso
     * @param PDO $db Instancia de conexión PDO
     */
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Obtiene todos los permisos de acceso para un rol dado.
     * Retorna un array asociativo donde la clave es el nombre del módulo
     * y el valor es un booleano indicando si puede acceder.
     * 
     * Ejemplo de retorno:
     *   ['dashboard' => true, 'citas' => true, 'personal' => false, ...]
     *
     * @param int $rol_id ID del rol del usuario autenticado
     * @return array Array asociativo de permisos por nombre de módulo
     * @throws Exception Si la conexión no está disponible
     */
    public function getPermisosPorRol($rol_id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada en Permiso.");
        }

        // Valor predeterminado: todos los módulos denegados
        $resultado = [];

        try {
            $query = "SELECT m.nombre, p.puede_acceder
                      FROM permisos p
                      INNER JOIN modulos m ON p.modulo_id = m.id
                      WHERE p.rol_id = :rol_id
                      ORDER BY m.id ASC";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":rol_id", $rol_id, PDO::PARAM_INT);
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
     * Retorna:
     * [
     *   'roles'   => [['id' => 1, 'nombre' => 'superadmin'], ...],
     *   'modulos' => [['id' => 2, 'nombre' => 'citas', 'label' => 'Citas'], ...],
     *   'permisos' => [ rol_id => [ modulo_id => bool ], ... ]
     * ]
     *
     * @param array $excluir Array de nombres de módulos a excluir de la tabla editable
     * @return array Matriz completa de permisos
     * @throws Exception Si la conexión no está disponible
     */
    public function getMatrizCompleta($excluir = ['app', 'dashboard']) {
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
     * Usa INSERT ... ON DUPLICATE KEY UPDATE para evitar duplicados.
     *
     * @param int  $rol_id        ID del rol
     * @param int  $modulo_id     ID del módulo
     * @param bool $puede_acceder Si el rol puede acceder al módulo
     * @return bool True si la operación fue exitosa
     * @throws Exception Si la conexión no está disponible o hay error de BD
     */
    public function actualizarPermiso($rol_id, $modulo_id, $puede_acceder) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada en Permiso.");
        }

        try {
            $query = "INSERT INTO permisos (rol_id, modulo_id, puede_acceder)
                      VALUES (:rol_id, :modulo_id, :puede_acceder)
                      ON DUPLICATE KEY UPDATE puede_acceder = :puede_acceder";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":rol_id",        $rol_id,        PDO::PARAM_INT);
            $stmt->bindParam(":modulo_id",     $modulo_id,     PDO::PARAM_INT);
            $stmt->bindParam(":puede_acceder", $puede_acceder, PDO::PARAM_INT);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            error_log("Excepción en Permiso::actualizarPermiso(): " . $e->getMessage());
            throw new Exception("Error al actualizar el permiso.");
        }
    }
}
