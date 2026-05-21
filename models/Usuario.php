<?php
/**
 * Modelo de Usuario
 * Representa la entidad Usuario y gestiona su persistencia y autenticación de forma segura.
 */
class Usuario {
    // Conexión de base de datos (PDO)
    private $db;
    private $table_name = "usuarios";

    // Propiedades privadas de la entidad
    private $id;
    private $cedula;
    private $nombre;
    private $apellido;
    private $rol;
    private $password;
    private $loginError;

    /**
     * Constructor del Modelo Usuario
     * @param PDO $db Instancia de conexión PDO
     */
    public function __construct($db = null) {
        $this->db = $db;
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

    public function getCedula() {
        return $this->cedula;
    }

    /**
     * Asigna y valida la cédula de identidad
     * @param string|int $cedula Cédula del usuario (solo dígitos, longitud típica 6-15)
     */
    public function setCedula($cedula) {
        // Limpiamos espacios y caracteres no numéricos
        $cleanCedula = preg_replace('/[^0-9]/', '', trim($cedula));
        
        if (strlen($cleanCedula) >= 6 && strlen($cleanCedula) <= 15) {
            $this->cedula = $cleanCedula;
        } else {
            throw new InvalidArgumentException("La cédula debe contener solo números y tener entre 6 y 15 dígitos.");
        }
    }

    public function getNombre() {
        return $this->nombre;
    }

    /**
     * Asigna y sanitiza el nombre
     * @param string $nombre Nombre del usuario
     */
    public function setNombre($nombre) {
        $cleanNombre = strip_tags(trim($nombre));
        if (!empty($cleanNombre) && strlen($cleanNombre) <= 50) {
            $this->nombre = $cleanNombre;
        } else {
            throw new InvalidArgumentException("El nombre no es válido o supera los 50 caracteres.");
        }
    }

    public function getApellido() {
        return $this->apellido;
    }

    /**
     * Asigna y sanitiza el apellido
     * @param string $apellido Apellido del usuario
     */
    public function setApellido($apellido) {
        $cleanApellido = strip_tags(trim($apellido));
        if (!empty($cleanApellido) && strlen($cleanApellido) <= 50) {
            $this->apellido = $cleanApellido;
        } else {
            throw new InvalidArgumentException("El apellido no es válido o supera los 50 caracteres.");
        }
    }

    public function getRol() {
        return $this->rol;
    }

    /**
     * Asigna y valida el rol basado en RBAC
     * @param string $rol Rol del usuario (superadmin, admin, guia)
     */
    public function setRol($rol) {
        $validRoles = ['superadmin', 'admin', 'guia'];
        $cleanRol = strtolower(trim($rol));
        
        if (in_array($cleanRol, $validRoles, true)) {
            $this->rol = $cleanRol;
        } else {
            throw new InvalidArgumentException("El rol proporcionado no es válido.");
        }
    }

    public function getPassword() {
        return $this->password;
    }

    /**
     * Asigna el hash de la contraseña directamente
     * @param string $passwordHash Contraseña ya hasheada
     */
    public function setPassword($passwordHash) {
        $this->password = $passwordHash;
    }

    /**
     * Hashea y asigna una contraseña en texto plano
     * @param string $plainPassword Contraseña en texto plano a ser encriptada
     */
    public function setPlainPassword($plainPassword) {
        $trimmed = trim($plainPassword);
        if (strlen($trimmed) >= 6) {
            $this->password = password_hash($trimmed, PASSWORD_BCRYPT);
        } else {
            throw new InvalidArgumentException("La contraseña debe tener al menos 6 caracteres.");
        }
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS Y LÓGICA DE NEGOCIO
    // =========================================================================

    /**
     * Método seguro de verificación de credenciales (Login)
     * Utiliza consultas preparadas PDO para evitar inyecciones SQL
     * 
     * @param string $cedula Cédula ingresada
     * @param string $password Contraseña en texto plano ingresada
     * @return bool True si las credenciales son válidas y carga el usuario, False en caso contrario
     */
    public function login($cedula, $password) {
        if (!$this->db) {
            error_log("Error en Usuario::login() - Instancia PDO no configurada.");
            $this->loginError = "Instancia de base de datos no configurada.";
            return false;
        }

        try {
            // Sanitización previa de la cédula para la consulta
            $cleanCedula = preg_replace('/[^0-9]/', '', trim($cedula));
            
            if (empty($cleanCedula) || empty($password)) {
                $this->loginError = "La cédula y la contraseña son requeridas.";
                return false;
            }

            // Consulta preparada
            $query = "SELECT id, cedula, nombre, apellido, rol, password FROM " . $this->table_name . " WHERE cedula = :cedula LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":cedula", $cleanCedula, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch();

            if (!$row) {
                $this->loginError = "El usuario con la cédula ingresada no existe.";
                return false;
            }

            // Verificación segura de contraseña mediante password_verify
            if (password_verify($password, $row['password'])) {
                // Mapeamos los datos obtenidos en las propiedades privadas del objeto
                $this->setId($row['id']);
                $this->setCedula($row['cedula']);
                $this->setNombre($row['nombre']);
                $this->setApellido($row['apellido']);
                $this->setRol($row['rol']);
                $this->setPassword($row['password']);
                $this->loginError = null; // Limpiar cualquier error previo
                
                return true;
            } else {
                $this->loginError = "La contraseña ingresada es incorrecta.";
                return false;
            }
        } catch (PDOException $e) {
            error_log("Excepción en Usuario::login(): " . $e->getMessage());
            $this->loginError = "Error en la consulta de base de datos.";
        }

        return false;
    }

    /**
     * Obtiene todos los usuarios ordenados por ID de forma descendente.
     * @return array Listado de usuarios
     */
    public function listarTodos() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }
        $query = "SELECT id, cedula, nombre, apellido, rol, created_at, updated_at FROM " . $this->table_name . " ORDER BY id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la información de un usuario específico por su ID.
     * @param PDO $db Instancia de conexión PDO
     * @param int $id ID del usuario
     * @return array|null Datos del usuario o null si no se encuentra
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) {
            return null;
        }
        $query = "SELECT id, cedula, nombre, apellido, rol, created_at, updated_at FROM usuarios WHERE id = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * Verifica de forma segura si una cédula ya existe en la base de datos.
     * Permite excluir un ID específico (útil en actualizaciones).
     * 
     * @param PDO $db Instancia de conexión PDO
     * @param string $cedula Cédula a verificar
     * @param int|null $excluirId ID opcional a excluir de la verificación
     * @return bool True si la cédula ya existe, False de lo contrario
     */
    public static function existeCedula($db, $cedula, $excluirId = null) {
        if (!$db) {
            return false;
        }
        $cleanCedula = preg_replace('/[^0-9]/', '', trim($cedula));
        $query = "SELECT COUNT(*) FROM usuarios WHERE cedula = :cedula";
        if ($excluirId !== null) {
            $query .= " AND id != :excluirId";
        }
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":cedula", $cleanCedula, PDO::PARAM_STR);
        if ($excluirId !== null) {
            $stmt->bindParam(":excluirId", $excluirId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Registra un nuevo usuario en la base de datos aplicando reglas de seguridad estrictas.
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function crear() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        // Validación de cédula única
        if (self::existeCedula($this->db, $this->cedula)) {
            throw new InvalidArgumentException("La cédula de identidad ya se encuentra registrada en el sistema.");
        }

        // Restricción estricta: sólo se pueden crear admins y guias, no supers
        if ($this->rol === 'superadmin') {
            throw new InvalidArgumentException("No está permitido registrar nuevos usuarios con rol de Super Administrador.");
        }

        $query = "INSERT INTO " . $this->table_name . " (cedula, nombre, apellido, rol, password) 
                  VALUES (:cedula, :nombre, :apellido, :rol, :password)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
        $stmt->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
        $stmt->bindParam(":apellido", $this->apellido, PDO::PARAM_STR);
        $stmt->bindParam(":rol", $this->rol, PDO::PARAM_STR);
        $stmt->bindParam(":password", $this->password, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Actualiza la información de un usuario existente aplicando políticas RBAC estrictas.
     * @return bool True en caso de éxito, False de lo contrario
     */
    public function actualizar() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        if (empty($this->id)) {
            throw new InvalidArgumentException("Se requiere el ID del usuario para poder actualizar.");
        }

        // Validación de cédula única (excluyendo el usuario actual)
        if (self::existeCedula($this->db, $this->cedula, $this->id)) {
            throw new InvalidArgumentException("La cédula ingresada ya está siendo utilizada por otro usuario.");
        }

        // Obtener datos actuales del usuario en base de datos para validar el cambio de rol
        $currentInDb = self::obtenerPorId($this->db, $this->id);
        if (!$currentInDb) {
            throw new InvalidArgumentException("El usuario a actualizar no existe.");
        }

        $oldRol = $currentInDb['rol'];

        // Restricción: No degradar a un superadmin existente
        if ($oldRol === 'superadmin' && $this->rol !== 'superadmin') {
            throw new InvalidArgumentException("No está permitido degradar o cambiar el rol de un Super Administrador.");
        }

        // Restricción: No promover a superadmin a un usuario común
        if ($oldRol !== 'superadmin' && $this->rol === 'superadmin') {
            throw new InvalidArgumentException("No está permitido promover usuarios al rol de Super Administrador.");
        }

        // Construcción de la consulta dinámica para actualización de contraseña
        if (!empty($this->password)) {
            $query = "UPDATE " . $this->table_name . " 
                      SET cedula = :cedula, nombre = :nombre, apellido = :apellido, rol = :rol, password = :password 
                      WHERE id = :id";
        } else {
            $query = "UPDATE " . $this->table_name . " 
                      SET cedula = :cedula, nombre = :nombre, apellido = :apellido, rol = :rol 
                      WHERE id = :id";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
        $stmt->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
        $stmt->bindParam(":apellido", $this->apellido, PDO::PARAM_STR);
        $stmt->bindParam(":rol", $this->rol, PDO::PARAM_STR);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        
        if (!empty($this->password)) {
            $stmt->bindParam(":password", $this->password, PDO::PARAM_STR);
        }

        return $stmt->execute();
    }

    /**
     * Elimina un usuario por ID. Impide eliminar cuentas con rol Super Administrador.
     * @param int $id ID del usuario a eliminar
     * @return bool True si se eliminó con éxito, False de lo contrario
     */
    public function eliminar($id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $cleanId = filter_var($id, FILTER_VALIDATE_INT) ? (int)$id : null;
        if (!$cleanId) {
            throw new InvalidArgumentException("ID de usuario inválido.");
        }

        // Verificar el rol del usuario antes de borrar
        $userInDb = self::obtenerPorId($this->db, $cleanId);
        if (!$userInDb) {
            throw new InvalidArgumentException("El usuario a eliminar no existe.");
        }

        if ($userInDb['rol'] === 'superadmin') {
            throw new InvalidArgumentException("Un Super Administrador no puede ser eliminado del sistema.");
        }

        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $cleanId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}

