<?php
require_once __DIR__ . "/Database.php";

/**
 * Modelo de Institución (Institucion)
 * Gestiona de forma segura los datos y persistencia de instituciones y sus sedes en la base de datos.
 * Hereda de Database para gestionar internamente la conexión.
 */
class Institucion extends Database {
    // Propiedades de la entidad
    private ?int $id = null;
    private ?int $entidad_id = null;
    private ?string $tipo_documento = null;
    private ?string $documento = null;
    private ?string $nombre_completo = null; // Razón Social
    private ?string $tipo_organizacion = null; // e.g. Educativa, Empresarial, etc.
    private ?string $tipo_gestion = null; // 'publica' | 'privada'
    private array $sedes = [];
    private array $contactos = [];
    private ?string $terminoBusqueda = null;

    public function __construct() {
        parent::__construct();
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getEntidadId(): ?int { return $this->entidad_id; }
    public function setEntidadId(?int $entidad_id): void { $this->entidad_id = $entidad_id; }

    public function getTipoDocumento(): ?string { return $this->tipo_documento; }
    public function setTipoDocumento(?string $tipo): void { $this->tipo_documento = $tipo ? strtoupper(trim($tipo)) : null; }

    public function getDocumento(): ?string { return $this->documento; }
    public function setDocumento(?string $doc): void { $this->documento = $doc ? trim($doc) : null; }

    public function getNombreCompleto(): ?string { return $this->nombre_completo; }
    public function setNombreCompleto(?string $nombre): void { $this->nombre_completo = $nombre ? trim($nombre) : null; }

    // Alias para compatibilidad
    public function getNombre(): ?string { return $this->nombre_completo; }
    public function setNombre(?string $nombre): void { $this->nombre_completo = $nombre ? trim($nombre) : null; }

    public function getTipoOrganizacion(): ?string { return $this->tipo_organizacion; }
    public function setTipoOrganizacion(?string $tipo): void { $this->tipo_organizacion = $tipo ? trim($tipo) : null; }

    public function getTipoGestion(): ?string { return $this->tipo_gestion; }
    public function setTipoGestion(?string $tipo): void { $this->tipo_gestion = $tipo ? trim($tipo) : null; }

    public function getSedes(): array { return $this->sedes; }
    public function setSedes(array $sedes): void { $this->sedes = $sedes; }

    public function getContactos(): array { return $this->contactos; }
    public function setContactos(array $contactos): void { $this->contactos = $contactos; }

    public function getTerminoBusqueda(): ?string { return $this->terminoBusqueda; }
    public function setTerminoBusqueda(?string $term): void { $this->terminoBusqueda = $term ? trim($term) : null; }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS Y LÓGICA DE NEGOCIO
    // =========================================================================

    /**
     * Busca una institución registrada por su RIF/documento de forma segura
     */
    public function buscarPorDocumento(?string $tipo_documento = null, ?string $documento = null): ?array {
        if ($tipo_documento !== null) {
            $this->tipo_documento = strtoupper(trim($tipo_documento));
        }
        if ($documento !== null) {
            $this->documento = trim($documento);
        }

        if (!$this->db || empty($this->tipo_documento) || empty($this->documento)) {
            return null;
        }

        try {
            $query = "SELECT e.id AS entidad_id, i.id AS institucion_id, e.tipo_documento, e.documento, e.nombre_completo, 
                             i.tipo_organizacion, i.tipo_gestion
                      FROM entidades e
                      INNER JOIN instituciones i ON i.entidad_id = e.id
                      WHERE UPPER(e.tipo_documento) = UPPER(:tipo_documento) AND e.documento = :documento AND i.activo = 1
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmt->bindParam(":documento", $this->documento, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $row['nombre'] = $row['nombre_completo'];

                // Buscar todas las sedes de la institución
                $querySedes = "SELECT s.id, s.institucion_id, s.parroquia_id, s.detalles,
                                      p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                               FROM sedes s
                               LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                               LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                               LEFT JOIN estados est ON m.id_estado = est.id_estado
                               WHERE s.institucion_id = :institucion_id AND s.activo = 1
                               ORDER BY s.id ASC";
                $stmtSedes = $this->db->prepare($querySedes);
                $stmtSedes->bindParam(":institucion_id", $row['institucion_id'], PDO::PARAM_INT);
                $stmtSedes->execute();
                $row['sedes'] = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($row['sedes'])) {
                    $firstSede = $row['sedes'][0];
                    $row['parroquia_id'] = $firstSede['parroquia_id'];
                    $row['detalles_direccion'] = $firstSede['detalles'];
                    $row['id_estado'] = $firstSede['id_estado'];
                    $row['id_municipio'] = $firstSede['id_municipio'];
                    $row['estado'] = $firstSede['estado'];
                    $row['municipio'] = $firstSede['municipio'];
                    $row['parroquia'] = $firstSede['parroquia'];
                }

                // Buscar contactos del directorio
                $queryContacts = "SELECT contacto, tipo FROM directorio WHERE entidad_id = :entidad_id";
                $stmtContacts = $this->db->prepare($queryContacts);
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
     * Valida si un documento ya pertenece a otra entidad distinta de $this->entidad_id
     */
    public function existeDocumentoEnOtraEntidad(?string $tipo_documento = null, ?string $documento = null, ?int $entidad_id = null): bool {
        if ($tipo_documento !== null) $this->tipo_documento = strtoupper(trim($tipo_documento));
        if ($documento !== null) $this->documento = trim($documento);
        if ($entidad_id !== null) $this->entidad_id = $entidad_id;

        if (!$this->db || empty($this->tipo_documento) || empty($this->documento)) {
            return false;
        }

        $query = "SELECT COUNT(*) FROM entidades WHERE tipo_documento = :tipo_documento AND documento = :documento";
        if ($this->entidad_id !== null && $this->entidad_id > 0) {
            $query .= " AND id != :entidad_id";
        }

        $stmtCheck = $this->db->prepare($query);
        $stmtCheck->bindValue(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
        $stmtCheck->bindValue(":documento", $this->documento, PDO::PARAM_STR);
        if ($this->entidad_id !== null && $this->entidad_id > 0) {
            $stmtCheck->bindValue(":entidad_id", $this->entidad_id, PDO::PARAM_INT);
        }
        $stmtCheck->execute();
        return (int)$stmtCheck->fetchColumn() > 0;
    }

    /**
     * Crea una nueva institución en el sistema de manera atómica (Transaccional)
     * Soporta registro de una o más sedes
     */
    public function crear(): bool {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            $this->db->beginTransaction();

            // 1. Insertar en entidades
            $queryEntidad = "INSERT INTO entidades (tipo_documento, documento, nombre_completo, tipo_entidad) 
                             VALUES (:tipo_documento, :documento, :nombre_completo, 'institucion')";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindParam(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":documento", $this->documento, PDO::PARAM_STR);
            $stmtEntidad->bindParam(":nombre_completo", $this->nombre_completo, PDO::PARAM_STR);
            $stmtEntidad->execute();
            
            $entidadId = (int)$this->db->lastInsertId();
            $this->setEntidadId($entidadId);

            // 2. Insertar en instituciones
            $queryInst = "INSERT INTO instituciones (entidad_id, tipo_organizacion, tipo_gestion) 
                          VALUES (:entidad_id, :tipo_organizacion, :tipo_gestion)";
            $stmtInst = $this->db->prepare($queryInst);
            $stmtInst->bindParam(":entidad_id", $entidadId, PDO::PARAM_INT);
            $stmtInst->bindParam(":tipo_organizacion", $this->tipo_organizacion, PDO::PARAM_STR);
            $stmtInst->bindParam(":tipo_gestion", $this->tipo_gestion, PDO::PARAM_STR);
            $stmtInst->execute();

            $institucionId = (int)$this->db->lastInsertId();
            $this->setId($institucionId);

            // 3. Insertar las sedes (1 o más)
            $querySede = "INSERT INTO sedes (institucion_id, parroquia_id, detalles) 
                          VALUES (:institucion_id, :parroquia_id, :detalles)";
            $stmtSede = $this->db->prepare($querySede);

            foreach ($this->sedes as $s) {
                $parroquiaId = (int)($s['parroquia_id'] ?? 0);
                $detalles = $s['detalles'] ?? null;

                if ($parroquiaId > 0) {
                    $stmtSede->bindValue(":institucion_id", $institucionId, PDO::PARAM_INT);
                    $stmtSede->bindValue(":parroquia_id", $parroquiaId, PDO::PARAM_INT);
                    $stmtSede->bindValue(":detalles", $detalles, PDO::PARAM_STR);
                    $stmtSede->execute();
                }
            }

            // 4. Insertar en directorio (contactos)
            $queryContact = "INSERT INTO directorio (entidad_id, contacto, tipo) 
                             VALUES (:entidad_id, :contacto, :tipo)";
            $stmtContact = $this->db->prepare($queryContact);

            foreach ($this->contactos as $c) {
                $contactoVal = $c['contacto'] ?? '';
                $tipoVal = $c['tipo'] ?? 'telefono';
                if (!empty($contactoVal)) {
                    $stmtContact->bindValue(":entidad_id", $entidadId, PDO::PARAM_INT);
                    $stmtContact->bindValue(":contacto", $contactoVal, PDO::PARAM_STR);
                    $stmtContact->bindValue(":tipo", $tipoVal, PDO::PARAM_STR);
                    $stmtContact->execute();
                }
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
     * Obtiene el listado de todas las instituciones registradas con sus sedes y contactos.
     */
    public function listarTodas(): array {
        if (!$this->db) return [];
        try {
            $query = "SELECT i.id, e.id AS entidad_id, e.tipo_documento, e.documento, e.nombre_completo, 
                             e.nombre_completo AS nombre, i.tipo_organizacion, i.tipo_gestion,
                             (SELECT COUNT(*) FROM citas c WHERE c.institucion_id = i.id) AS total_citas
                      FROM instituciones i
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      WHERE i.activo = 1
                      ORDER BY i.id DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($instituciones)) {
                return [];
            }

            // Cargar todas las sedes agrupadas
            $querySedes = "SELECT s.id, s.institucion_id, s.parroquia_id, s.detalles,
                                  p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                           FROM sedes s
                           LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                           LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                           LEFT JOIN estados est ON m.id_estado = est.id_estado
                           WHERE s.activo = 1
                           ORDER BY s.id ASC";
            $stmtSedes = $this->db->prepare($querySedes);
            $stmtSedes->execute();
            $allSedes = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

            $sedesMap = [];
            foreach ($allSedes as $s) {
                $sedesMap[$s['institucion_id']][] = $s;
            }

            // Cargar todos los contactos agrupados
            $queryContacts = "SELECT entidad_id, contacto, tipo FROM directorio";
            $stmtContacts = $this->db->prepare($queryContacts);
            $stmtContacts->execute();
            $allContacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

            $contactsMap = [];
            foreach ($allContacts as $c) {
                $contactsMap[$c['entidad_id']][] = [
                    'contacto' => $c['contacto'],
                    'tipo' => $c['tipo']
                ];
            }

            // Adjuntar sedes y contactos a cada institución
            foreach ($instituciones as &$inst) {
                $inst['total_citas'] = (int)($inst['total_citas'] ?? 0);
                $inst['sedes'] = $sedesMap[$inst['id']] ?? [];
                $inst['contactos'] = $contactsMap[$inst['entidad_id']] ?? [];

                if (!empty($inst['sedes'])) {
                    $first = $inst['sedes'][0];
                    $inst['parroquia_id'] = $first['parroquia_id'];
                    $inst['detalles_direccion'] = $first['detalles'];
                    $inst['parroquia'] = $first['parroquia'];
                    $inst['municipio'] = $first['municipio'];
                    $inst['estado'] = $first['estado'];
                    $inst['id_estado'] = $first['id_estado'];
                    $inst['id_municipio'] = $first['id_municipio'];
                } else {
                    $inst['detalles_direccion'] = '';
                    $inst['parroquia'] = '';
                    $inst['municipio'] = '';
                    $inst['estado'] = '';
                }
            }

            return $instituciones;
        } catch (PDOException $e) {
            error_log("Error en Institucion::listarTodas: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca instituciones por coincidencia parcial de su nombre o documento (RIF).
     * Utiliza $this->terminoBusqueda.
     */
    public function buscarPorTermino(?string $term = null): array {
        if ($term !== null) {
            $this->terminoBusqueda = trim($term);
        }

        if (!$this->db || empty($this->terminoBusqueda)) {
            return [];
        }

        try {
            $query = "SELECT i.id, e.id AS entidad_id, e.tipo_documento, e.documento, e.nombre_completo,
                             e.nombre_completo AS nombre, i.tipo_organizacion, i.tipo_gestion,
                             (SELECT COUNT(*) FROM citas c WHERE c.institucion_id = i.id) AS total_citas
                      FROM instituciones i
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      WHERE i.activo = 1 AND (
                            e.nombre_completo LIKE :term1 
                         OR e.documento LIKE :term2 
                         OR CONCAT(e.tipo_documento, '-', e.documento) LIKE :term3
                      )
                      ORDER BY e.nombre_completo ASC
                      LIMIT 10";
            $stmt = $this->db->prepare($query);
            $likeTerm = '%' . $this->terminoBusqueda . '%';
            $stmt->bindValue(':term1', $likeTerm, PDO::PARAM_STR);
            $stmt->bindValue(':term2', $likeTerm, PDO::PARAM_STR);
            $stmt->bindValue(':term3', $likeTerm, PDO::PARAM_STR);
            $stmt->execute();
            $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($instituciones)) {
                return [];
            }

            $instIds = array_column($instituciones, 'id');
            $entidadIds = array_column($instituciones, 'entidad_id');

            // Cargar sedes para las instituciones encontradas
            $placeholdersInst = implode(',', array_fill(0, count($instIds), '?'));
            $querySedes = "SELECT s.id, s.institucion_id, s.parroquia_id, s.detalles,
                                  p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                           FROM sedes s
                           LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                           LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                           LEFT JOIN estados est ON m.id_estado = est.id_estado
                           WHERE s.institucion_id IN ($placeholdersInst) AND s.activo = 1
                           ORDER BY s.id ASC";
            $stmtSedes = $this->db->prepare($querySedes);
            $stmtSedes->execute($instIds);
            $allSedes = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

            $sedesMap = [];
            foreach ($allSedes as $s) {
                $sedesMap[$s['institucion_id']][] = $s;
            }

            // Cargar contactos
            $placeholdersEnt = implode(',', array_fill(0, count($entidadIds), '?'));
            $queryContacts = "SELECT entidad_id, contacto, tipo FROM directorio WHERE entidad_id IN ($placeholdersEnt)";
            $stmtContacts = $this->db->prepare($queryContacts);
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
                $inst['total_citas'] = (int)($inst['total_citas'] ?? 0);
                $inst['sedes'] = $sedesMap[$inst['id']] ?? [];
                $inst['contactos'] = $contactsMap[$inst['entidad_id']] ?? [];

                if (!empty($inst['sedes'])) {
                    $first = $inst['sedes'][0];
                    $inst['parroquia_id'] = $first['parroquia_id'];
                    $inst['detalles_direccion'] = $first['detalles'];
                    $inst['parroquia'] = $first['parroquia'];
                    $inst['municipio'] = $first['municipio'];
                    $inst['estado'] = $first['estado'];
                    $inst['id_estado'] = $first['id_estado'];
                    $inst['id_municipio'] = $first['id_municipio'];
                }
            }

            return $instituciones;
        } catch (PDOException $e) {
            error_log("Error en buscarPorTermino: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la información detallada de una institución por su ID utilizando $this->id
     */
    public function obtenerPorId(?int $id = null): ?array {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return null;
        }

        try {
            $query = "SELECT e.id AS entidad_id, i.id AS institucion_id, e.tipo_documento, e.documento, e.nombre_completo,
                             e.nombre_completo AS nombre, i.tipo_organizacion, i.tipo_gestion,
                             (SELECT COUNT(*) FROM citas c WHERE c.institucion_id = i.id) AS total_citas
                      FROM entidades e
                      INNER JOIN instituciones i ON i.entidad_id = e.id
                      WHERE i.id = :id AND i.activo = 1
                      LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $this->entidad_id = (int)$row['entidad_id'];
                $this->tipo_documento = $row['tipo_documento'];
                $this->documento = $row['documento'];
                $this->nombre_completo = $row['nombre_completo'];
                $this->tipo_organizacion = $row['tipo_organizacion'];
                $this->tipo_gestion = $row['tipo_gestion'];

                $row['total_citas'] = (int)($row['total_citas'] ?? 0);

                // Cargar sedes
                $querySedes = "SELECT s.id, s.institucion_id, s.parroquia_id, s.detalles,
                                      p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                               FROM sedes s
                               LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                               LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                               LEFT JOIN estados est ON m.id_estado = est.id_estado
                               WHERE s.institucion_id = :id AND s.activo = 1
                               ORDER BY s.id ASC";
                $stmtSedes = $this->db->prepare($querySedes);
                $stmtSedes->bindParam(":id", $this->id, PDO::PARAM_INT);
                $stmtSedes->execute();
                $row['sedes'] = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);
                $this->sedes = $row['sedes'];

                if (!empty($row['sedes'])) {
                    $first = $row['sedes'][0];
                    $row['parroquia_id'] = $first['parroquia_id'];
                    $row['detalles_direccion'] = $first['detalles'];
                    $row['id_estado'] = $first['id_estado'];
                    $row['id_municipio'] = $first['id_municipio'];
                    $row['estado'] = $first['estado'];
                    $row['municipio'] = $first['municipio'];
                    $row['parroquia'] = $first['parroquia'];
                }

                // Buscar contactos del directorio
                $queryContacts = "SELECT contacto, tipo FROM directorio WHERE entidad_id = :entidad_id";
                $stmtContacts = $this->db->prepare($queryContacts);
                $stmtContacts->bindParam(":entidad_id", $row['entidad_id'], PDO::PARAM_INT);
                $stmtContacts->execute();
                $row['contactos'] = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);
                $this->contactos = $row['contactos'];

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
    public function actualizar(): bool {
        if (!$this->db || !$this->id) {
            throw new Exception("Parámetros insuficientes para actualizar la institución.");
        }

        try {
            $this->db->beginTransaction();

            // 1. Actualizar la tabla entidades
            $queryEntidad = "UPDATE entidades 
                             SET tipo_documento = :tipo_documento, documento = :documento, nombre_completo = :nombre_completo 
                             WHERE id = :entidad_id";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindValue(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmtEntidad->bindValue(":documento", $this->documento, PDO::PARAM_STR);
            $stmtEntidad->bindValue(":nombre_completo", $this->nombre_completo, PDO::PARAM_STR);
            $stmtEntidad->bindValue(":entidad_id", $this->entidad_id, PDO::PARAM_INT);
            $stmtEntidad->execute();

            // 2. Actualizar la tabla instituciones
            $queryInst = "UPDATE instituciones 
                          SET tipo_organizacion = :tipo_organizacion, tipo_gestion = :tipo_gestion 
                          WHERE id = :id";
            $stmtInst = $this->db->prepare($queryInst);
            $stmtInst->bindValue(":tipo_organizacion", $this->tipo_organizacion, PDO::PARAM_STR);
            $stmtInst->bindValue(":tipo_gestion", $this->tipo_gestion, PDO::PARAM_STR);
            $stmtInst->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmtInst->execute();

            // 3. Actualizar sedes
            $incomingSedeIds = [];
            foreach ($this->sedes as $s) {
                if (!empty($s['id']) && (int)$s['id'] > 0) {
                    $incomingSedeIds[] = (int)$s['id'];
                }
            }

            if (!empty($incomingSedeIds)) {
                $placeholders = implode(',', array_fill(0, count($incomingSedeIds), '?'));
                $queryDeleteSedes = "UPDATE sedes SET activo = 0 WHERE institucion_id = ? AND id NOT IN ($placeholders)";
                $params = array_merge([$this->id], $incomingSedeIds);
                $stmtDelSedes = $this->db->prepare($queryDeleteSedes);
                $stmtDelSedes->execute($params);
            } else {
                $queryDeleteSedes = "UPDATE sedes SET activo = 0 WHERE institucion_id = :id";
                $stmtDelSedes = $this->db->prepare($queryDeleteSedes);
                $stmtDelSedes->bindValue(":id", $this->id, PDO::PARAM_INT);
                $stmtDelSedes->execute();
            }

            $queryInsertSede = "INSERT INTO sedes (institucion_id, parroquia_id, detalles) 
                                VALUES (:institucion_id, :parroquia_id, :detalles)";
            $stmtInsertSede = $this->db->prepare($queryInsertSede);

            $queryUpdateSede = "UPDATE sedes 
                                SET parroquia_id = :parroquia_id, detalles = :detalles 
                                WHERE id = :id AND institucion_id = :institucion_id";
            $stmtUpdateSede = $this->db->prepare($queryUpdateSede);

            foreach ($this->sedes as $s) {
                $sedeId = !empty($s['id']) ? (int)$s['id'] : null;
                $parroquiaId = (int)($s['parroquia_id'] ?? 0);
                $detalles = $s['detalles'] ?? null;

                if ($parroquiaId > 0) {
                    if ($sedeId) {
                        $stmtUpdateSede->bindValue(":parroquia_id", $parroquiaId, PDO::PARAM_INT);
                        $stmtUpdateSede->bindValue(":detalles", $detalles, PDO::PARAM_STR);
                        $stmtUpdateSede->bindValue(":id", $sedeId, PDO::PARAM_INT);
                        $stmtUpdateSede->bindValue(":institucion_id", $this->id, PDO::PARAM_INT);
                        $stmtUpdateSede->execute();
                    } else {
                        $stmtInsertSede->bindValue(":institucion_id", $this->id, PDO::PARAM_INT);
                        $stmtInsertSede->bindValue(":parroquia_id", $parroquiaId, PDO::PARAM_INT);
                        $stmtInsertSede->bindValue(":detalles", $detalles, PDO::PARAM_STR);
                        $stmtInsertSede->execute();
                    }
                }
            }

            // 4. Actualizar contactos
            $queryDeleteContacts = "DELETE FROM directorio WHERE entidad_id = :entidad_id";
            $stmtDeleteContacts = $this->db->prepare($queryDeleteContacts);
            $stmtDeleteContacts->bindValue(":entidad_id", $this->entidad_id, PDO::PARAM_INT);
            $stmtDeleteContacts->execute();

            $queryContact = "INSERT INTO directorio (entidad_id, contacto, tipo) VALUES (:entidad_id, :contacto, :tipo)";
            $stmtContact = $this->db->prepare($queryContact);

            foreach ($this->contactos as $c) {
                $contactoVal = $c['contacto'] ?? '';
                $tipoVal = $c['tipo'] ?? 'telefono';
                if (!empty($contactoVal)) {
                    $stmtContact->bindValue(":entidad_id", $this->entidad_id, PDO::PARAM_INT);
                    $stmtContact->bindValue(":contacto", $contactoVal, PDO::PARAM_STR);
                    $stmtContact->bindValue(":tipo", $tipoVal, PDO::PARAM_STR);
                    $stmtContact->execute();
                }
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
     * Cuenta el número de citas asociadas a una institución usando $this->id
     */
    public function contarCitas(?int $id = null): int {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            return 0;
        }

        try {
            $query = "SELECT COUNT(*) FROM citas WHERE institucion_id = :id AND activo = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error en Institucion::contarCitas: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Elimina una institución del sistema si no posee citas asociadas usando $this->id
     */
    public function eliminar(?int $id = null): bool {
        if ($id !== null) {
            $this->id = $id;
        }

        if (!$this->db || !$this->id) {
            throw new Exception("Conexión de base de datos o ID de institución no inicializado.");
        }

        try {
            $this->db->beginTransaction();

            $queryInst = "UPDATE instituciones SET activo = 0 WHERE id = :id";
            $stmtInst = $this->db->prepare($queryInst);
            $stmtInst->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmtInst->execute();

            $querySedes = "UPDATE sedes SET activo = 0 WHERE institucion_id = :id";
            $stmtSedes = $this->db->prepare($querySedes);
            $stmtSedes->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmtSedes->execute();

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error al eliminar lógicamente la institución: " . $e->getMessage());
            throw $e;
        }
    }
}
