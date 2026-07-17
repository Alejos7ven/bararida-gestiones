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
    private $nombre;
    private $apellido;
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
    // GETTERS Y SETTERS CON VALIDACIÓN ESTRICTA
    // =========================================================================

    public function getLoginError() {
        return $this->loginError;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = filter_var($id, FILTER_VALIDATE_INT) ? (int)$id : null;
    }

    public function getTipoDocumento() {
        return $this->tipo_documento;
    }

    public function setTipoDocumento($tipo_documento) {
        $this->tipo_documento = strtoupper(trim($tipo_documento));
    }

    public function getCedula() {
        return $this->cedula;
    }

    /**
     * Asigna la cédula de identidad
     */
    public function setCedula($cedula) {
        $this->cedula = preg_replace('/[^0-9]/', '', trim($cedula));
    }

    public function getNombre() {
        return $this->nombre;
    }

    /**
     * Asigna el nombre
     */
    public function setNombre($nombre) {
        $this->nombre = strip_tags(trim($nombre));
    }

    public function getApellido() {
        return $this->apellido;
    }

    /**
     * Asigna el apellido
     */
    public function setApellido($apellido) {
        $this->apellido = strip_tags(trim($apellido));
    }

    public function getRol() {
        return $this->rol;
    }

    /**
     * Asigna el rol
     */
    public function setRol($rol) {
        $this->rol = strtolower(trim($rol));
    }

    public function getRolId() {
        return $this->rol_id;
    }

    public function setRolId($rol_id) {
        $this->rol_id = filter_var($rol_id, FILTER_VALIDATE_INT) ? (int)$rol_id : null;
    }

    public function getPassword() {
        return $this->password;
    }

    /**
     * Asigna el hash de la contraseña directamente
     */
    public function setPassword($passwordHash) {
        $this->password = $passwordHash;
    }

    /**
     * Hashea y asigna una contraseña en texto plano
     */
    public function setPlainPassword($plainPassword) {
        $this->password = password_hash(trim($plainPassword), PASSWORD_BCRYPT);
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS Y LÓGICA DE NEGOCIO
    // =========================================================================

    /**
     * Método seguro de verificación de credenciales (Login)
     * Utiliza consultas preparadas PDO para evitar inyecciones SQL
     * 
     * @param string $tipo_documento Tipo de documento (V, E, J, G, P)
     * @param string $cedula Cédula ingresada
     * @param string $password Contraseña en texto plano ingresada
     * @return bool True si las credenciales son válidas y carga el usuario, False en caso contrario
     */
    public function login($tipo_documento, $cedula, $password) {
        if (!$this->db) {
            error_log("Error en Personal::login() - Instancia PDO no configurada.");
            $this->loginError = "Instancia de base de datos no configurada.";
            return false;
        }

        try {
            // Sanitización previa de la cédula para la consulta
            $cleanCedula = preg_replace('/[^0-9]/', '', trim($cedula));
            $cleanTipoDoc = strtoupper(trim($tipo_documento));
            
            if (empty($cleanTipoDoc) || empty($cleanCedula) || empty($password)) {
                $this->loginError = "El tipo de documento, documento y contraseña son requeridos.";
                return false;
            }

            // Consulta preparada con INNER JOIN para entidades y roles (insensible a mayúsculas/minúsculas en el tipo)
            $query = "SELECT p.id, e.tipo_documento, e.documento AS cedula, e.nombre, e.apellido, r.id AS rol_id, r.nombre AS rol, p.password 
                      FROM " . $this->table_name . " p
                      INNER JOIN entidades e ON p.entidad_id = e.id
                      INNER JOIN roles r ON p.rol_id = r.id
                      WHERE UPPER(e.tipo_documento) = :tipo_documento AND e.documento = :cedula LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":tipo_documento", $cleanTipoDoc, PDO::PARAM_STR);
            $stmt->bindParam(":cedula", $cleanCedula, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch();

            if (!$row) {
                $this->loginError = "El tipo de documento o número de documento ingresado es incorrecto.";
                return false;
            }

            // Verificación segura de contraseña mediante password_verify
            if (password_verify($password, $row['password'])) {
                // Mapeamos los datos obtenidos en las propiedades privadas del objeto
                $this->setId($row['id']);
                $this->setTipoDocumento($row['tipo_documento']);
                $this->setCedula($row['cedula']);
                $this->setNombre($row['nombre']);
                $this->setApellido($row['apellido']);
                $this->setRolId($row['rol_id']);
                $this->setRol($row['rol']);
                $this->setPassword($row['password']);
                $this->loginError = null; // Limpiar cualquier error previo
                
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
     * @return array Listado de personal
     */
    public function listarTodos() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }
        $query = "SELECT p.id, e.tipo_documento, e.documento AS cedula, e.nombre, e.apellido, r.nombre AS rol, p.created_at, p.updated_at 
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
     * @param PDO $db Instancia de conexión PDO
     * @param int $id ID del personal
     * @return array|null Datos del personal o null si no se encuentra
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) {
            return null;
        }
        $query = "SELECT p.id, e.tipo_documento, e.documento AS cedula, e.nombre, e.apellido, r.nombre AS rol, p.created_at, p.updated_at 
                  FROM personal p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  INNER JOIN roles r ON p.rol_id = r.id
                  WHERE p.id = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * Verifica de forma segura si un documento ya existe en la base de datos.
     * Permite excluir un ID específico (útil en actualizaciones).
     * 
     * @param PDO $db Instancia de conexión PDO
     * @param string $tipo_documento Tipo de documento
     * @param string $cedula Cédula a verificar
     * @param int|null $excluirId ID opcional a excluir de la verificación
     * @return bool True si el documento ya existe, False de lo contrario
     */
    public static function existeDocumento($db, $tipo_documento, $cedula, $excluirId = null) {
        if (!$db) {
            return false;
        }
        $cleanCedula = preg_replace('/[^0-9]/', '', trim($cedula));
        $cleanTipoDoc = strtoupper(trim($tipo_documento));
        $query = "SELECT COUNT(*) 
                  FROM personal p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  WHERE UPPER(e.tipo_documento) = :tipo_documento AND e.documento = :cedula";
        if ($excluirId !== null) {
            $query .= " AND p.id != :excluirId";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tipo_documento", $cleanTipoDoc, PDO::PARAM_STR);
        $stmt->bindParam(":cedula", $cleanCedula, PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindParam(":excluirId", $excluirId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Registra un nuevo personal en la base de datos aplicando reglas de seguridad estrictas.
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function crear() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        // Validación de documento único
        if (self::existeDocumento($this->db, $this->tipo_documento, $this->cedula)) {
            throw new InvalidArgumentException("El documento de identidad ya se encuentra registrado en el sistema.");
        }

        // Restricción estricta: sólo se pueden crear admins y guias, no supers
        if ($this->rol === 'superadmin') {
            throw new InvalidArgumentException("No está permitido registrar nuevo personal con rol de Super Administrador.");
        }

        try {
            $this->db->beginTransaction();

            // Obtener rol_id a partir del nombre del rol
            $queryRol = "SELECT id FROM roles WHERE nombre = :rol LIMIT 1";
            $stmtRol = $this->db->prepare($queryRol);
            $stmtRol->bindParam(":rol", $this->rol, PDO::PARAM_STR);
            $stmtRol->execute();
            $rolId = $stmtRol->fetchColumn();
            if (!$rolId) {
                throw new InvalidArgumentException("El rol proporcionado no es válido.");
            }

            // Verificar si la entidad ya existe
            $queryEntidad = "SELECT id FROM entidades WHERE tipo_documento = :tipo_documento AND documento = :cedula LIMIT 1";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
            $stmtEntidad->execute();
            $entidadId = $stmtEntidad->fetchColumn();

            if (!$entidadId) {
                // Crear nueva entidad
                $queryInsertEntidad = "INSERT INTO entidades (tipo_documento, documento, nombre, apellido, tipo_entidad) 
                                       VALUES (:tipo_documento, :cedula, :nombre, :apellido, 'persona')";
                $stmtInsertEntidad = $this->db->prepare($queryInsertEntidad);
                $stmtInsertEntidad->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
                $stmtInsertEntidad->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
                $stmtInsertEntidad->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
                $stmtInsertEntidad->bindParam(":apellido", $this->apellido, PDO::PARAM_STR);
                $stmtInsertEntidad->execute();
                $entidadId = $this->db->lastInsertId();
            } else {
                // Si ya existe la entidad, actualizamos sus datos (nombre, apellido)
                $queryUpdateEntidad = "UPDATE entidades SET nombre = :nombre, apellido = :apellido, tipo_entidad = 'persona' 
                                       WHERE id = :entidadId";
                $stmtUpdateEntidad = $this->db->prepare($queryUpdateEntidad);
                $stmtUpdateEntidad->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
                $stmtUpdateEntidad->bindParam(":apellido", $this->apellido, PDO::PARAM_STR);
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
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza la información de un personal existente aplicando políticas RBAC estrictas.
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function actualizar() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        if (empty($this->id)) {
            throw new InvalidArgumentException("Se requiere el ID del personal para poder actualizar.");
        }

        // Validación de documento único (excluyendo el usuario actual)
        if (self::existeDocumento($this->db, $this->tipo_documento, $this->cedula, $this->id)) {
            throw new InvalidArgumentException("El documento de identidad ingresado ya está siendo utilizado por otro miembro del personal.");
        }

        // Obtener datos actuales del personal en base de datos para validar el cambio de rol
        $currentInDb = self::obtenerPorId($this->db, $this->id);
        if (!$currentInDb) {
            throw new InvalidArgumentException("El personal a actualizar no existe.");
        }

        $oldRol = $currentInDb['rol'];

        // Restricción: No degradar a un superadmin existente
        if ($oldRol === 'superadmin' && $this->rol !== 'superadmin') {
            throw new InvalidArgumentException("No está permitido degradar o cambiar el rol de un Super Administrador.");
        }

        // Restricción: No promover a superadmin a un usuario común
        if ($oldRol !== 'superadmin' && $this->rol === 'superadmin') {
            throw new InvalidArgumentException("No está permitido promover personal al rol de Super Administrador.");
        }

        try {
            $this->db->beginTransaction();

            // Obtener entidadId del personal actual
            $queryEntidadId = "SELECT entidad_id FROM personal WHERE id = :id LIMIT 1";
            $stmtEntidadId = $this->db->prepare($queryEntidadId);
            $stmtEntidadId->bindParam(":id", $this->id, PDO::PARAM_INT);
            $stmtEntidadId->execute();
            $entidadId = $stmtEntidadId->fetchColumn();

            if (!$entidadId) {
                throw new Exception("No se encontró la entidad asociada al personal.");
            }

            // Actualizar la tabla entidades
            $queryEntidad = "UPDATE entidades SET tipo_documento = :tipo_documento, documento = :cedula, nombre = :nombre, apellido = :apellido WHERE id = :entidadId";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":apellido", $this->apellido, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":entidadId", $entidadId, PDO::PARAM_INT);
            $stmtEntidad->execute();

            // Obtener el nuevo rolId
            $queryRol = "SELECT id FROM roles WHERE nombre = :rol LIMIT 1";
            $stmtRol = $this->db->prepare($queryRol);
            $stmtRol->bindParam(":rol", $this->rol, PDO::PARAM_STR);
            $stmtRol->execute();
            $rolId = $stmtRol->fetchColumn();
            if (!$rolId) {
                throw new InvalidArgumentException("El rol proporcionado no es válido.");
            }

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
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina un personal por ID. Impide eliminar cuentas con rol Super Administrador.
     * @param int $id ID del personal a eliminar
     * @return bool True si se eliminó con éxito, False de lo contrario
     */
    public function eliminar($id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $cleanId = filter_var($id, FILTER_VALIDATE_INT) ? (int)$id : null;
        if (!$cleanId) {
            throw new InvalidArgumentException("ID de personal inválido.");
        }

        // Verificar el rol del personal antes de borrar
        $userInDb = self::obtenerPorId($this->db, $cleanId);
        if (!$userInDb) {
            throw new InvalidArgumentException("El personal a eliminar no existe.");
        }

        if ($userInDb['rol'] === 'superadmin') {
            throw new InvalidArgumentException("Un Super Administrador no puede ser eliminado del sistema.");
        }

        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $cleanId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Obtiene todos los nombres de los roles que no son 'superadmin' para poblar selectores
     * @param PDO $db Instancia de conexión PDO
     * @return array Listado de nombres de roles
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

