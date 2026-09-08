<?php
/**
 * Modelo de Personal
 * Representa la entidad Personal y gestiona su persistencia y autenticación de forma segura.
 */
class Personal {
    // Conexión de base de datos (PDO)
    private $db;
    private $table_name = "personal";

    // Propiedades privadas de la entidad
    private $id;
    private $tipo_documento;
    private $cedula;
    private $nombre_completo;
    private $rol;
    private $rol_id;
    private $password;
    private $loginError;

    /**
     * Constructor del Modelo Personal
     * @param PDO $db Instancia de conexión PDO
     */
    public function __construct($db = null) {
        $this->db = $db;
        $this->tipo_documento = 'V'; // Valor por defecto
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getLoginError() { return $this->loginError; }

    public function getId() { return $this->id; }
    public function setId($id) { $this->id = $id; }

    public function getTipoDocumento() { return $this->tipo_documento; }
    public function setTipoDocumento($tipo_documento) { $this->tipo_documento = $tipo_documento; }

    public function getCedula() { return $this->cedula; }
    public function setCedula($cedula) { $this->cedula = $cedula; }

    public function getNombreCompleto() { return $this->nombre_completo; }
    public function setNombreCompleto($nombre_completo) { $this->nombre_completo = $nombre_completo; }

    // Compatibilidad
    public function getNombre() { return $this->nombre_completo; }
    public function setNombre($nombre) { $this->nombre_completo = $nombre; }

    public function getRol() { return $this->rol; }
    public function setRol($rol) { $this->rol = $rol; }

    public function getRolId() { return $this->rol_id; }
    public function setRolId($rol_id) { $this->rol_id = $rol_id; }

    public function getPassword() { return $this->password; }
    public function setPassword($passwordHash) { $this->password = $passwordHash; }

    public function setPlainPassword($plainPassword) {
        $this->password = password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS Y LÓGICA DE NEGOCIO
    // =========================================================================

    /**
     * Método seguro de verificación de credenciales (Login)
     * Utiliza consultas preparadas PDO para evitar inyecciones SQL
     */
    public function login($tipo_documento, $cedula, $password) {
        if (!$this->db) {
            error_log("Error en Personal::login() - Instancia PDO no configurada.");
            $this->loginError = "Instancia de base de datos no configurada.";
            return false;
        }

        try {
            $query = "SELECT p.id, e.tipo_documento, e.documento AS cedula, e.nombre_completo, r.id AS rol_id, r.nombre AS rol, p.password 
                      FROM " . $this->table_name . " p
                      INNER JOIN entidades e ON p.entidad_id = e.id
                      INNER JOIN roles r ON p.rol_id = r.id
                      WHERE UPPER(e.tipo_documento) = UPPER(:tipo_documento) AND e.documento = :cedula LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":tipo_documento", $tipo_documento, PDO::PARAM_STR);
            $stmt->bindParam(":cedula", $cedula, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->loginError = "El tipo de documento o número de documento ingresado es incorrecto.";
                return false;
            }

            if (password_verify($password, $row['password'])) {
                $this->setId($row['id']);
                $this->setTipoDocumento($row['tipo_documento']);
                $this->setCedula($row['cedula']);
                $this->setNombreCompleto($row['nombre_completo']);
                $this->setRolId($row['rol_id']);
                $this->setRol($row['rol']);
                $this->setPassword($row['password']);
                $this->loginError = null;
                
                return true;
            } else {
                $this->loginError = "La contraseña ingresada es incorrecta.";
                return false;
            }
        } catch (PDOException $e) {
            error_log("Excepción en Personal::login(): " . $e->getMessage());
            $this->loginError = "Error en la consulta de base de datos.";
        }

        return false;
    }

    /**
     * Obtiene todo el personal ordenado por ID de forma descendente.
     */
    public function listarTodos() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }
        $query = "SELECT p.id, e.tipo_documento, e.documento AS cedula, e.nombre_completo, r.nombre AS rol, p.created_at, p.updated_at 
                  FROM " . $this->table_name . " p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  INNER JOIN roles r ON p.rol_id = r.id
                  ORDER BY p.id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la información de un personal específico por su ID.
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) {
            return null;
        }
        $query = "SELECT p.id, e.tipo_documento, e.documento AS cedula, e.nombre_completo, r.nombre AS rol, p.created_at, p.updated_at 
                  FROM personal p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  INNER JOIN roles r ON p.rol_id = r.id
                  WHERE p.id = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $cleanId = (int)$id;
        $stmt->bindParam(":id", $cleanId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * Verifica de forma segura si un documento ya existe en la base de datos.
     */
    public static function existeDocumento($db, $tipo_documento, $cedula, $excluirId = null) {
        if (!$db) {
            return false;
        }
        $query = "SELECT COUNT(*) 
                  FROM personal p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  WHERE UPPER(e.tipo_documento) = UPPER(:tipo_documento) AND e.documento = :cedula";
        if ($excluirId !== null) {
            $query .= " AND p.id != :excluirId";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tipo_documento", $tipo_documento, PDO::PARAM_STR);
        $stmt->bindParam(":cedula", $cedula, PDO::PARAM_STR);
        if ($excluirId !== null) {
            $cleanExcluir = (int)$excluirId;
            $stmt->bindParam(":excluirId", $cleanExcluir, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Registra un nuevo personal en la base de datos de forma transaccional.
     */
    public function crear() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $this->db->beginTransaction();

            // Obtener rol_id a partir del nombre del rol
            $queryRol = "SELECT id FROM roles WHERE nombre = :rol LIMIT 1";
            $stmtRol = $this->db->prepare($queryRol);
            $stmtRol->bindParam(":rol", $this->rol, PDO::PARAM_STR);
            $stmtRol->execute();
            $rolId = $stmtRol->fetchColumn();

            // Verificar si la entidad ya existe
            $queryEntidad = "SELECT id FROM entidades WHERE tipo_documento = :tipo_documento AND documento = :cedula LIMIT 1";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
            $stmtEntidad->execute();
            $entidadId = $stmtEntidad->fetchColumn();

            if ($entidadId === false || $entidadId === null) {
                // Crear nueva entidad
                $queryInsertEntidad = "INSERT INTO entidades (tipo_documento, documento, nombre_completo, tipo_entidad) 
                                       VALUES (:tipo_documento, :cedula, :nombre_completo, 'persona')";
                $stmtInsertEntidad = $this->db->prepare($queryInsertEntidad);
                $stmtInsertEntidad->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
                $stmtInsertEntidad->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
                $stmtInsertEntidad->bindParam(":nombre_completo", $this->nombre_completo, PDO::PARAM_STR);
                $stmtInsertEntidad->execute();
                $entidadId = $this->db->lastInsertId();
            } else {
                // Si ya existe la entidad, actualizamos su nombre_completo
                $queryUpdateEntidad = "UPDATE entidades SET nombre_completo = :nombre_completo, tipo_entidad = 'persona' 
                                       WHERE id = :entidadId";
                $stmtUpdateEntidad = $this->db->prepare($queryUpdateEntidad);
                $stmtUpdateEntidad->bindParam(":nombre_completo", $this->nombre_completo, PDO::PARAM_STR);
                $stmtUpdateEntidad->bindParam(":entidadId", $entidadId, PDO::PARAM_INT);
                $stmtUpdateEntidad->execute();
            }

            // Insertar registro en la tabla personal
            $query = "INSERT INTO " . $this->table_name . " (entidad_id, rol_id, password) 
                      VALUES (:entidad_id, :rol_id, :password)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":entidad_id", $entidadId, PDO::PARAM_INT);
            $stmt->bindParam(":rol_id", $rolId, PDO::PARAM_INT);
            $stmt->bindParam(":password", $this->password, PDO::PARAM_STR);
            $stmt->execute();

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Actualiza la información de un personal existente.
     */
    public function actualizar() {
        if (!$this->db || $this->id === null || $this->id === '') {
            throw new Exception("Conexión de base de datos o ID de personal no configurados.");
        }

        try {
            $this->db->beginTransaction();

            // Obtener entidadId del personal actual
            $queryEntidadId = "SELECT entidad_id FROM personal WHERE id = :id LIMIT 1";
            $stmtEntidadId = $this->db->prepare($queryEntidadId);
            $stmtEntidadId->bindParam(":id", $this->id, PDO::PARAM_INT);
            $stmtEntidadId->execute();
            $entidadId = $stmtEntidadId->fetchColumn();

            if ($entidadId === false || $entidadId === null) {
                throw new Exception("No se encontró la entidad asociada al personal.");
            }

            // Actualizar la tabla entidades
            $queryEntidad = "UPDATE entidades SET tipo_documento = :tipo_documento, documento = :cedula, nombre_completo = :nombre_completo WHERE id = :entidadId";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":nombre_completo", $this->nombre_completo, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":entidadId", $entidadId, PDO::PARAM_INT);
            $stmtEntidad->execute();

            // Obtener el nuevo rolId
            $queryRol = "SELECT id FROM roles WHERE nombre = :rol LIMIT 1";
            $stmtRol = $this->db->prepare($queryRol);
            $stmtRol->bindParam(":rol", $this->rol, PDO::PARAM_STR);
            $stmtRol->execute();
            $rolId = $stmtRol->fetchColumn();

            // Actualizar la tabla personal
            if (!empty($this->password)) {
                $queryPersonal = "UPDATE personal SET rol_id = :rolId, password = :password WHERE id = :id";
                $stmtPersonal = $this->db->prepare($queryPersonal);
                $stmtPersonal->bindParam(":rolId", $rolId, PDO::PARAM_INT);
                $stmtPersonal->bindParam(":password", $this->password, PDO::PARAM_STR);
                $stmtPersonal->bindParam(":id", $this->id, PDO::PARAM_INT);
            } else {
                $queryPersonal = "UPDATE personal SET rol_id = :rolId WHERE id = :id";
                $stmtPersonal = $this->db->prepare($queryPersonal);
                $stmtPersonal->bindParam(":rolId", $rolId, PDO::PARAM_INT);
                $stmtPersonal->bindParam(":id", $this->id, PDO::PARAM_INT);
            }
            $stmtPersonal->execute();

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Elimina un personal por ID.
     */
    public function eliminar($id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $cleanId = (int)$id;
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $cleanId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Obtiene todos los nombres de los roles para poblar selectores
     */
    public static function obtenerRolesList($db) {
        if (!$db) return ['admin', 'guia'];
        try {
            $query = "SELECT DISTINCT nombre FROM roles WHERE nombre != 'superadmin' ORDER BY nombre ASC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return !empty($roles) ? $roles : ['admin', 'guia'];
        } catch (PDOException $e) {
            error_log("Error en Personal::obtenerRolesList(): " . $e->getMessage());
            return ['admin', 'guia'];
        }
    }
}
