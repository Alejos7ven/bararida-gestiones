<?php
/**
 * Modelo de Institución (Institucion)
 * Gestiona de forma segura los datos y persistencia de instituciones en la base de datos.
 */
class Institucion {
    private $db;

    // Propiedades de la entidad
    private $id;
    private $entidad_id;
    private $tipo_documento;
    private $documento;
    private $nombre; // Razón Social
    private $tipo_organizacion; // e.g. Colegio Privado, Escuela Pública, etc.
    
    // Dirección
    private $ciudad_id;
    private $parroquia_id;
    private $detalles_direccion;
    private $tipo_gestion;

    // Contactos (directorio)
    private $contactos = []; // Array de arrays [ 'contacto' => '...', 'tipo' => '...' ]

    public function __construct($db = null) {
        $this->db = $db;
    }

    // =========================================================================
    // GETTERS Y SETTERS CON VALIDACIÓN
    // =========================================================================

    public function getId() { return $this->id; }
    public function setId($id) { $this->id = (int)$id; }

    public function getEntidadId() { return $this->entidad_id; }
    public function setEntidadId($entidad_id) { $this->entidad_id = (int)$entidad_id; }

    public function getTipoDocumento() { return $this->tipo_documento; }
    public function setTipoDocumento($tipo) {
        $this->tipo_documento = strtoupper(trim($tipo));
    }

    public function getDocumento() { return $this->documento; }
    public function setDocumento($doc) {
        $this->documento = preg_replace('/[^0-9A-Za-z-]/', '', trim($doc));
    }

    public function getNombre() { return $this->nombre; }
    public function setNombre($nombre) {
        $this->nombre = strip_tags(trim($nombre));
    }

    public function getTipoOrganizacion() { return $this->tipo_organizacion; }
    public function setTipoOrganizacion($tipo) {
        $this->tipo_organizacion = strip_tags(trim($tipo));
    }

    public function getCiudadId() { return $this->ciudad_id; }
    public function setCiudadId($ciudad_id) {
        $this->ciudad_id = (int)$ciudad_id;
    }

    public function getParroquiaId() { return $this->parroquia_id; }
    public function setParroquiaId($parroquia_id) {
        $this->parroquia_id = (int)$parroquia_id;
    }

    public function getDetallesDireccion() { return $this->detalles_direccion; }
    public function setDetallesDireccion($detalles) {
        $this->detalles_direccion = strip_tags(trim($detalles));
    }

    public function getTipoGestion() { return $this->tipo_gestion; }
    public function setTipoGestion($tipo) {
        $this->tipo_gestion = strtolower(trim($tipo));
    }

    public function getContactos() { return $this->contactos; }
    public function setContactos($contactos) {
        $this->contactos = [];
        if (is_array($contactos)) {
            foreach ($contactos as $c) {
                $contactoVal = strip_tags(trim($c['contacto'] ?? ''));
                $tipoVal = strip_tags(trim($c['tipo'] ?? 'principal'));
                if (!empty($contactoVal)) {
                    $this->contactos[] = [
                        'contacto' => $contactoVal,
                        'tipo' => $tipoVal
                    ];
                }
            }
        }
    }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS Y LÓGICA DE NEGOCIO
    // =========================================================================

    /**
     * Busca una institución registrada por su RIF/documento de forma segura
     */
    public static function buscarPorDocumento($db, $tipo_documento, $documento) {
        if (!$db) return null;

        $cleanTipo = strtoupper(trim($tipo_documento));
        $cleanDoc = preg_replace('/[^0-9A-Za-z-]/', '', trim($documento));

        try {
            $query = "SELECT e.id AS entidad_id, i.id AS institucion_id, e.tipo_documento, e.documento, e.nombre, i.tipo_organizacion, i.tipo_gestion,
                             d.ciudad_id, d.parroquia_id, d.detalles AS detalles_direccion,
                             est.id_estado, mun.id_municipio, p.id_parroquia,
                             est.estado, mun.municipio, p.parroquia, c.ciudad
                      FROM entidades e
                      INNER JOIN instituciones i ON i.entidad_id = e.id
                      LEFT JOIN direccion d ON d.institucion_id = i.id
                      LEFT JOIN parroquias p ON d.parroquia_id = p.id_parroquia
                      LEFT JOIN municipios mun ON p.id_municipio = mun.id_municipio
                      LEFT JOIN estados est ON mun.id_estado = est.id_estado
                      LEFT JOIN ciudades c ON d.ciudad_id = c.id_ciudad
                      WHERE UPPER(e.tipo_documento) = :tipo_documento AND e.documento = :documento 
                      LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tipo_documento", $cleanTipo, PDO::PARAM_STR);
            $stmt->bindParam(":documento", $cleanDoc, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Buscar contactos del directorio
                $queryContacts = "SELECT contacto, tipo FROM directorio WHERE entidad_id = :entidad_id";
                $stmtContacts = $db->prepare($queryContacts);
                $stmtContacts->bindParam(":entidad_id", $row['entidad_id'], PDO::PARAM_INT);
                $stmtContacts->execute();
                $row['contactos'] = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

                return $row;
            }
        } catch (PDOException $e) {
            error_log("Error en Institucion::buscarPorDocumento: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Crea una nueva institución en el sistema de manera atómica (Transaccional)
     */
    public function crear() {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        // Comprobar existencia previa
        $exists = self::buscarPorDocumento($this->db, $this->tipo_documento, $this->documento);
        if ($exists) {
            throw new InvalidArgumentException("La institución con el documento indicado ya se encuentra registrada.");
        }

        try {
            $this->db->beginTransaction();

            // 1. Insertar en entidades
            $queryEntidad = "INSERT INTO entidades (tipo_documento, documento, nombre, apellido, tipo_entidad) 
                             VALUES (:tipo_documento, :documento, :nombre, NULL, 'institucion')";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":documento", $this->documento, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":nombre", $this->nombre, PDO::PARAM_STR);
            $stmtEntidad->execute();
            
            $entidadId = $this->db->lastInsertId();
            $this->setEntidadId($entidadId);

            // 2. Insertar en instituciones
            $queryInst = "INSERT INTO instituciones (entidad_id, tipo_organizacion, tipo_gestion) 
                          VALUES (:entidad_id, :tipo_organizacion, :tipo_gestion)";
            $stmtInst = $this->db->prepare($queryInst);
            $stmtInst->bindParam(":entidad_id", $entidadId, PDO::PARAM_INT);
            $stmtInst->bindParam(":tipo_organizacion", $this->tipo_organizacion, PDO::PARAM_STR);
            $stmtInst->bindParam(":tipo_gestion", $this->tipo_gestion, PDO::PARAM_STR);
            $stmtInst->execute();

            $institucionId = $this->db->lastInsertId();
            $this->setId($institucionId);

            // 3. Insertar en direccion
            $queryDir = "INSERT INTO direccion (institucion_id, ciudad_id, parroquia_id, detalles) 
                         VALUES (:institucion_id, :ciudad_id, :parroquia_id, :detalles)";
            $stmtDir = $this->db->prepare($queryDir);
            $stmtDir->bindParam(":institucion_id", $institucionId, PDO::PARAM_INT);
            $stmtDir->bindParam(":ciudad_id", $this->ciudad_id, PDO::PARAM_INT);
            $stmtDir->bindParam(":parroquia_id", $this->parroquia_id, PDO::PARAM_INT);
            $stmtDir->bindParam(":detalles", $this->detalles_direccion, PDO::PARAM_STR);
            $stmtDir->execute();

            // 4. Insertar en directorio (contactos)
            $queryContact = "INSERT INTO directorio (entidad_id, contacto, tipo) 
                             VALUES (:entidad_id, :contacto, :tipo)";
            $stmtContact = $this->db->prepare($queryContact);

            foreach ($this->contactos as $c) {
                $stmtContact->bindValue(":entidad_id", $entidadId, PDO::PARAM_INT);
                $stmtContact->bindValue(":contacto", $c['contacto'], PDO::PARAM_STR);
                $stmtContact->bindValue(":tipo", $c['tipo'], PDO::PARAM_STR);
                $stmtContact->execute();
            }

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
     * Obtiene el listado de todas las instituciones registradas con su ubicación y contactos.
     */
    public static function listarTodas($db) {
        if (!$db) return [];
        try {
            $query = "SELECT i.id, e.id AS entidad_id, e.tipo_documento, e.documento, e.nombre, i.tipo_organizacion, i.tipo_gestion,
                             d.ciudad_id, d.parroquia_id, d.detalles AS detalles_direccion,
                             c.ciudad, p.parroquia, m.municipio, est.estado, est.id_estado, m.id_municipio
                      FROM instituciones i
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      LEFT JOIN direccion d ON d.institucion_id = i.id
                      LEFT JOIN ciudades c ON d.ciudad_id = c.id_ciudad
                      LEFT JOIN parroquias p ON d.parroquia_id = p.id_parroquia
                      LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                      LEFT JOIN estados est ON m.id_estado = est.id_estado
                      ORDER BY i.id DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch all contacts to attach them in memory
            $queryContacts = "SELECT entidad_id, contacto, tipo FROM directorio";
            $stmtContacts = $db->prepare($queryContacts);
            $stmtContacts->execute();
            $allContacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

            // Group contacts by entidad_id
            $contactsMap = [];
            foreach ($allContacts as $c) {
                $contactsMap[$c['entidad_id']][] = [
                    'contacto' => $c['contacto'],
                    'tipo' => $c['tipo']
                ];
            }

            // Attach contacts to each institution
            foreach ($instituciones as &$inst) {
                $inst['contactos'] = $contactsMap[$inst['entidad_id']] ?? [];
            }

            return $instituciones;
        } catch (PDOException $e) {
            error_log("Error en Institucion::listarTodas: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca instituciones por coincidencia parcial de su nombre o documento (RIF).
     */
    public static function buscarPorTermino($db, $term) {
        if (!$db) return [];
        try {
            $query = "SELECT i.id, e.id AS entidad_id, e.tipo_documento, e.documento, e.nombre, i.tipo_organizacion, i.tipo_gestion,
                             d.ciudad_id, d.parroquia_id, d.detalles AS detalles_direccion,
                             c.ciudad, p.parroquia, m.municipio, est.estado, est.id_estado, m.id_municipio
                      FROM instituciones i
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      LEFT JOIN direccion d ON d.institucion_id = i.id
                      LEFT JOIN ciudades c ON d.ciudad_id = c.id_ciudad
                      LEFT JOIN parroquias p ON d.parroquia_id = p.id_parroquia
                      LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                      LEFT JOIN estados est ON m.id_estado = est.id_estado
                      WHERE e.nombre LIKE :term1 
                         OR e.documento LIKE :term2 
                         OR CONCAT(e.tipo_documento, '-', e.documento) LIKE :term3
                      ORDER BY e.nombre ASC
                      LIMIT 10";
            $stmt = $db->prepare($query);
            $likeTerm = '%' . $term . '%';
            $stmt->bindValue(':term1', $likeTerm, PDO::PARAM_STR);
            $stmt->bindValue(':term2', $likeTerm, PDO::PARAM_STR);
            $stmt->bindValue(':term3', $likeTerm, PDO::PARAM_STR);
            $stmt->execute();
            $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cargar contactos
            if (!empty($instituciones)) {
                $entidadIds = array_column($instituciones, 'entidad_id');
                $placeholders = implode(',', array_fill(0, count($entidadIds), '?'));
                $queryContacts = "SELECT entidad_id, contacto, tipo FROM directorio WHERE entidad_id IN ($placeholders)";
                $stmtContacts = $db->prepare($queryContacts);
                $stmtContacts->execute($entidadIds);
                $allContacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

                $contactsMap = [];
                foreach ($allContacts as $c) {
                    $contactsMap[$c['entidad_id']][] = [
                        'contacto' => $c['contacto'],
                        'tipo' => $c['tipo']
                    ];
                }

                foreach ($instituciones as &$inst) {
                    $inst['contactos'] = $contactsMap[$inst['entidad_id']] ?? [];
                }
            }

            return $instituciones;
        } catch (PDOException $e) {
            error_log("Error en buscarPorTermino: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la información detallada de una institución por su ID
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) return null;
        try {
            $query = "SELECT e.id AS entidad_id, i.id AS institucion_id, e.tipo_documento, e.documento, e.nombre, i.tipo_organizacion, i.tipo_gestion,
                             d.ciudad_id, d.parroquia_id, d.detalles AS detalles_direccion,
                             est.id_estado, mun.id_municipio, p.id_parroquia,
                             c.ciudad, p.parroquia, mun.municipio, est.estado
                      FROM entidades e
                      INNER JOIN instituciones i ON i.entidad_id = e.id
                      LEFT JOIN direccion d ON d.institucion_id = i.id
                      LEFT JOIN parroquias p ON d.parroquia_id = p.id_parroquia
                      LEFT JOIN municipios mun ON p.id_municipio = mun.id_municipio
                      LEFT JOIN estados est ON mun.id_estado = est.id_estado
                      LEFT JOIN ciudades c ON d.ciudad_id = c.id_ciudad
                      WHERE i.id = :id 
                      LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Buscar contactos del directorio
                $queryContacts = "SELECT contacto, tipo FROM directorio WHERE entidad_id = :entidad_id";
                $stmtContacts = $db->prepare($queryContacts);
                $stmtContacts->bindParam(":entidad_id", $row['entidad_id'], PDO::PARAM_INT);
                $stmtContacts->execute();
                $row['contactos'] = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

                return $row;
            }
        } catch (PDOException $e) {
            error_log("Error en Institucion::obtenerPorId: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Actualiza la información de una institución existente en el sistema de manera transaccional
     */
    public function actualizar() {
        if (!$this->db || !$this->id) {
            throw new Exception("Parámetros insuficientes para actualizar la institución.");
        }

        // 1. Verificar si el documento está en uso por otra entidad
        $queryCheck = "SELECT COUNT(*) FROM entidades WHERE tipo_documento = :tipo_documento AND documento = :documento AND id != :entidad_id";
        $stmtCheck = $this->db->prepare($queryCheck);
        $stmtCheck->bindValue(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
        $stmtCheck->bindValue(":documento", $this->documento, PDO::PARAM_STR);
        $stmtCheck->bindValue(":entidad_id", $this->entidad_id, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() > 0) {
            throw new InvalidArgumentException("El RIF/documento ingresado ya pertenece a otra entidad o institución.");
        }

        try {
            $this->db->beginTransaction();

            // 2. Actualizar la tabla entidades
            $queryEntidad = "UPDATE entidades 
                             SET tipo_documento = :tipo_documento, documento = :documento, nombre = :nombre 
                             WHERE id = :entidad_id";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindValue(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmtEntidad->bindValue(":documento", $this->documento, PDO::PARAM_STR);
            $stmtEntidad->bindValue(":nombre", $this->nombre, PDO::PARAM_STR);
            $stmtEntidad->bindValue(":entidad_id", $this->entidad_id, PDO::PARAM_INT);
            $stmtEntidad->execute();

            // 3. Actualizar la tabla instituciones
            $queryInst = "UPDATE instituciones 
                          SET tipo_organizacion = :tipo_organizacion, tipo_gestion = :tipo_gestion 
                          WHERE id = :id";
            $stmtInst = $this->db->prepare($queryInst);
            $stmtInst->bindValue(":tipo_organizacion", $this->tipo_organizacion, PDO::PARAM_STR);
            $stmtInst->bindValue(":tipo_gestion", $this->tipo_gestion, PDO::PARAM_STR);
            $stmtInst->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmtInst->execute();

            // 4. Actualizar la tabla direccion (verificar primero si existe, si no insertar)
            $queryCheckDir = "SELECT COUNT(*) FROM direccion WHERE institucion_id = :id";
            $stmtCheckDir = $this->db->prepare($queryCheckDir);
            $stmtCheckDir->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmtCheckDir->execute();

            if ($stmtCheckDir->fetchColumn() > 0) {
                $queryDir = "UPDATE direccion 
                             SET ciudad_id = :ciudad_id, parroquia_id = :parroquia_id, detalles = :detalles 
                             WHERE institucion_id = :id";
            } else {
                $queryDir = "INSERT INTO direccion (institucion_id, ciudad_id, parroquia_id, detalles) 
                             VALUES (:id, :ciudad_id, :parroquia_id, :detalles)";
            }
            $stmtDir = $this->db->prepare($queryDir);
            $stmtDir->bindValue(":ciudad_id", $this->ciudad_id, PDO::PARAM_INT);
            $stmtDir->bindValue(":parroquia_id", $this->parroquia_id, PDO::PARAM_INT);
            $stmtDir->bindValue(":detalles", $this->detalles_direccion, PDO::PARAM_STR);
            $stmtDir->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmtDir->execute();

            // 5. Actualizar contactos (eliminar anteriores y guardar nuevos)
            $queryDeleteContacts = "DELETE FROM directorio WHERE entidad_id = :entidad_id";
            $stmtDeleteContacts = $this->db->prepare($queryDeleteContacts);
            $stmtDeleteContacts->bindValue(":entidad_id", $this->entidad_id, PDO::PARAM_INT);
            $stmtDeleteContacts->execute();

            $queryContact = "INSERT INTO directorio (entidad_id, contacto, tipo) VALUES (:entidad_id, :contacto, :tipo)";
            $stmtContact = $this->db->prepare($queryContact);

            foreach ($this->contactos as $c) {
                $stmtContact->bindValue(":entidad_id", $this->entidad_id, PDO::PARAM_INT);
                $stmtContact->bindValue(":contacto", $c['contacto'], PDO::PARAM_STR);
                $stmtContact->bindValue(":tipo", $c['tipo'], PDO::PARAM_STR);
                $stmtContact->execute();
            }

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
     * Elimina una institución del sistema si no posee citas asociadas
     */
    public function eliminar($id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        $cleanId = filter_var($id, FILTER_VALIDATE_INT) ? (int)$id : null;
        if (!$cleanId) {
            throw new InvalidArgumentException("ID de institución no válido.");
        }

        // 1. Verificar si posee citas agendadas
        $queryCheckCitas = "SELECT COUNT(*) FROM citas WHERE institucion_id = :id";
        $stmtCheckCitas = $this->db->prepare($queryCheckCitas);
        $stmtCheckCitas->bindValue(":id", $cleanId, PDO::PARAM_INT);
        $stmtCheckCitas->execute();
        if ($stmtCheckCitas->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar la institución porque posee citas agendadas en el cronograma.");
        }

        try {
            // Obtener el entidad_id de la institución a eliminar
            $queryEntidad = "SELECT entidad_id FROM instituciones WHERE id = :id LIMIT 1";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindValue(":id", $cleanId, PDO::PARAM_INT);
            $stmtEntidad->execute();
            $entidadId = $stmtEntidad->fetchColumn();

            if (!$entidadId) {
                throw new InvalidArgumentException("La institución indicada no existe.");
            }

            $this->db->beginTransaction();

            // 2. Eliminar dirección
            $queryDeleteDir = "DELETE FROM direccion WHERE institucion_id = :id";
            $stmtDeleteDir = $this->db->prepare($queryDeleteDir);
            $stmtDeleteDir->bindValue(":id", $cleanId, PDO::PARAM_INT);
            $stmtDeleteDir->execute();

            // 3. Eliminar directorio de contactos
            $queryDeleteDirContacts = "DELETE FROM directorio WHERE entidad_id = :entidad_id";
            $stmtDeleteDirContacts = $this->db->prepare($queryDeleteDirContacts);
            $stmtDeleteDirContacts->bindValue(":entidad_id", $entidadId, PDO::PARAM_INT);
            $stmtDeleteDirContacts->execute();

            // 4. Eliminar institución
            $queryDeleteInst = "DELETE FROM instituciones WHERE id = :id";
            $stmtDeleteInst = $this->db->prepare($queryDeleteInst);
            $stmtDeleteInst->bindValue(":id", $cleanId, PDO::PARAM_INT);
            $stmtDeleteInst->execute();

            // 5. Eliminar entidad
            $queryDeleteEntidad = "DELETE FROM entidades WHERE id = :entidad_id";
            $stmtDeleteEntidad = $this->db->prepare($queryDeleteEntidad);
            $stmtDeleteEntidad->bindValue(":entidad_id", $entidadId, PDO::PARAM_INT);
            $stmtDeleteEntidad->execute();

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
