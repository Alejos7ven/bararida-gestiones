<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo de Personal
 * Representa la entidad Personal y gestiona su persistencia y autenticación de forma segura.
 * Hereda de Database para gestionar la conexión internamente.
 */
class Personal extends Database {
    private string $table_name = "personal";

    // Propiedades de la entidad
    private ?int $id = null;
    private ?string $tipo_documento = 'V';
    private ?string $cedula = null;
    private ?string $nombre_completo = null;
    private ?string $rol = null;
    private ?int $rol_id = null;
    private ?string $password = null;
    private ?string $plainPassword = null;
    private ?string $loginError = null;
    private ?int $excluirId = null;

    /**
     * Constructor del Modelo Personal
     */
    public function __construct() {
        parent::__construct();
        $this->tipo_documento = 'V';
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getLoginError(): ?string {
        return $this->loginError;
    }

    public function setLoginError(?string $loginError): void {
        $this->loginError = $loginError;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function setId(?int $id): void {
        $this->id = $id;
    }

    public function getTipoDocumento(): ?string {
        return $this->tipo_documento;
    }

    public function setTipoDocumento(?string $tipo_documento): void {
        $this->tipo_documento = $tipo_documento ? strtoupper(trim($tipo_documento)) : 'V';
    }

    public function getCedula(): ?string {
        return $this->cedula;
    }

    public function setCedula(?string $cedula): void {
        $this->cedula = $cedula ? trim($cedula) : null;
    }

    public function getNombreCompleto(): ?string {
        return $this->nombre_completo;
    }

    public function setNombreCompleto(?string $nombre_completo): void {
        $this->nombre_completo = $nombre_completo ? trim($nombre_completo) : null;
    }

    // Alias para compatibilidad
    public function getNombre(): ?string {
        return $this->nombre_completo;
    }

    public function setNombre(?string $nombre): void {
        $this->nombre_completo = $nombre ? trim($nombre) : null;
    }

    public function getRol(): ?string {
        return $this->rol;
    }

    public function setRol(?string $rol): void {
        $this->rol = $rol ? trim($rol) : null;
    }

    public function getRolId(): ?int {
        return $this->rol_id;
    }

    public function setRolId(?int $rol_id): void {
        $this->rol_id = $rol_id;
    }

    public function getPassword(): ?string {
        return $this->password;
    }

    public function setPassword(?string $passwordHash): void {
        $this->password = $passwordHash;
    }

    public function getPlainPassword(): ?string {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): void {
        $this->plainPassword = $plainPassword;
        if (!empty($plainPassword)) {
            $this->password = password_hash($plainPassword, PASSWORD_BCRYPT);
        }
    }

    public function getExcluirId(): ?int {
        return $this->excluirId;
    }

    public function setExcluirId(?int $excluirId): void {
        $this->excluirId = $excluirId;
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS Y LÓGICA DE NEGOCIO
    // =========================================================================

    /**
     * Método seguro de verificación de credenciales (Login)
     * Opera mediante las propiedades internas ($this->tipo_documento, $this->cedula, $this->plainPassword o $this->password)
     * Soporta argumentos opcionales por retrocompatibilidad
     */
    public function login(?string $tipo_documento = null, ?string $cedula = null, ?string $password = null): bool {
        if ($tipo_documento !== null) {
            $this->setTipoDocumento($tipo_documento);
        }
        if ($cedula !== null) {
            $this->setCedula($cedula);
        }
        $passToVerify = $password !== null ? $password : ($this->plainPassword ?? $this->password);

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
                      WHERE UPPER(e.tipo_documento) = UPPER(:tipo_documento) AND e.documento = :cedula AND p.activo = 1 LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmt->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->loginError = "El tipo de documento o número de documento ingresado es incorrecto.";
                return false;
            }

            if (password_verify($passToVerify, $row['password'])) {
                $this->setId((int)$row['id']);
                $this->setTipoDocumento($row['tipo_documento']);
                $this->setCedula($row['cedula']);
                $this->setNombreCompleto($row['nombre_completo']);
                $this->setRolId((int)$row['rol_id']);
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
    public function listarTodos(): array {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }
        $query = "SELECT p.id, e.tipo_documento, e.documento AS cedula, e.nombre_completo, r.nombre AS rol, p.created_at, p.updated_at 
                  FROM " . $this->table_name . " p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  INNER JOIN roles r ON p.rol_id = r.id
                  WHERE p.activo = 1
                  ORDER BY p.id DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la información de un personal específico por su ID.
     */
    public function obtenerPorId(?int $id = null): ?array {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return null;
        }

        $query = "SELECT p.id, e.tipo_documento, e.documento AS cedula, e.nombre_completo, r.nombre AS rol, p.created_at, p.updated_at 
                  FROM " . $this->table_name . " p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  INNER JOIN roles r ON p.rol_id = r.id
                  WHERE p.id = :id AND p.activo = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->setTipoDocumento($row['tipo_documento']);
            $this->setCedula($row['cedula']);
            $this->setNombreCompleto($row['nombre_completo']);
            $this->setRol($row['rol']);
            return $row;
        }
        return null;
    }

    /**
     * Verifica de forma segura si un documento ya existe en la base de datos.
     * Utiliza $this->tipo_documento, $this->cedula y $this->excluirId (o $this->id)
     */
    public function existeDocumento(?string $tipo_documento = null, ?string $cedula = null, ?int $excluirId = null): bool {
        if ($tipo_documento !== null) {
            $this->setTipoDocumento($tipo_documento);
        }
        if ($cedula !== null) {
            $this->setCedula($cedula);
        }
        $cleanExcluir = $excluirId !== null ? $excluirId : ($this->excluirId ?? $this->id);

        if (!$this->db || empty($this->cedula)) {
            return false;
        }

        $query = "SELECT COUNT(*) 
                  FROM personal p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  WHERE UPPER(e.tipo_documento) = UPPER(:tipo_documento) AND e.documento = :cedula AND p.activo = 1";
        if ($cleanExcluir !== null) {
            $query .= " AND p.id != :excluirId";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
        $stmt->bindParam(":cedula", $this->cedula, PDO::PARAM_STR);
        if ($cleanExcluir !== null) {
            $stmt->bindParam(":excluirId", $cleanExcluir, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Registra un nuevo personal en la base de datos de forma transaccional.
     */
    public function crear(): bool {
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

            if (!$rolId) {
                throw new Exception("Rol especificado no encontrado en el sistema.");
            }

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
                $entidadId = (int)$this->db->lastInsertId();
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

            $this->id = (int)$this->db->lastInsertId();

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
    public function actualizar(): bool {
        if (!$this->db || $this->id === null || $this->id === 0) {
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
     * Elimina un personal por ID de forma lógica (Soft Delete).
     */
    public function eliminar(?int $id = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID no inicializado.");
        }

        // Proteger usuario superadmin (ID 1 o 0)
        if ($this->id === 1) {
            throw new Exception("No es posible eliminar al Super Administrador principal.");
        }

        $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Obtiene todos los nombres de los roles para poblar selectores
     */
    public function obtenerRolesList(): array {
        if (!$this->db) {
            return ['admin', 'guia'];
        }
        try {
            $query = "SELECT DISTINCT nombre FROM roles WHERE nombre != 'superadmin' ORDER BY nombre ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return !empty($roles) ? $roles : ['admin', 'guia'];
        } catch (PDOException $e) {
            error_log("Error en Personal::obtenerRolesList(): " . $e->getMessage());
            return ['admin', 'guia'];
        }
    }

    /**
     * Lista todos los guías de campo registrados y activos en el sistema
     */
    public function listarGuias(): array {
        if (!$this->db) {
            return [];
        }
        try {
            $query = "SELECT p.id, e.nombre_completo, e.nombre_completo AS nombre, '' AS apellido 
                      FROM personal p
                      INNER JOIN entidades e ON p.entidad_id = e.id
                      INNER JOIN roles r ON p.rol_id = r.id
                      WHERE r.nombre = 'guia' AND p.activo = 1
                      ORDER BY e.nombre_completo ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en Personal::listarGuias(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el ID del rol dado su nombre
     */
    public function obtenerRolIdPorNombre(?string $rolNombre = null): ?int {
        $nombre = $rolNombre !== null ? $rolNombre : $this->rol;
        if (!$this->db || empty($nombre)) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
            $stmt->bindParam(":nombre", $nombre, PDO::PARAM_STR);
            $stmt->execute();
            $id = $stmt->fetchColumn();
            return ($id !== false && $id !== null) ? (int)$id : null;
        } catch (PDOException $e) {
            error_log("Error en Personal::obtenerRolIdPorNombre(): " . $e->getMessage());
            return null;
        }
    }
}
