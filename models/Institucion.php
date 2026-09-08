<?php
/**
 * Modelo de Institución (Institucion)
 * Gestiona de forma segura los datos y persistencia de instituciones y sus sedes en la base de datos.
 */
class Institucion {
    private $db;

    // Propiedades de la entidad
    private $id;
    private $entidad_id;
    private $tipo_documento;
    private $documento;
    private $nombre_completo; // Razón Social
    private $tipo_organizacion; // e.g. Educativa, Empresarial, etc.
    private $tipo_gestion; // 'publica' | 'privada'

    // Colección de Sedes (Array de arrays con ciudad_id, parroquia_id, detalles, id opcional)
    private $sedes = [];

    // Contactos (directorio)
    private $contactos = []; // Array de arrays [ 'contacto' => '...', 'tipo' => '...' ]

    public function __construct($db = null) {
        $this->db = $db;
    }

    // =========================================================================
    // GETTERS Y SETTERS
    // =========================================================================

    public function getId() { return $this->id; }
    public function setId($id) { $this->id = $id; }

    public function getEntidadId() { return $this->entidad_id; }
    public function setEntidadId($entidad_id) { $this->entidad_id = $entidad_id; }

    public function getTipoDocumento() { return $this->tipo_documento; }
    public function setTipoDocumento($tipo) { $this->tipo_documento = $tipo; }

    public function getDocumento() { return $this->documento; }
    public function setDocumento($doc) { $this->documento = $doc; }

    public function getNombreCompleto() { return $this->nombre_completo; }
    public function setNombreCompleto($nombre) { $this->nombre_completo = $nombre; }

    // Alias para compatibilidad
    public function getNombre() { return $this->nombre_completo; }
    public function setNombre($nombre) { $this->nombre_completo = $nombre; }

    public function getTipoOrganizacion() { return $this->tipo_organizacion; }
    public function setTipoOrganizacion($tipo) { $this->tipo_organizacion = $tipo; }

    public function getTipoGestion() { return $this->tipo_gestion; }
    public function setTipoGestion($tipo) { $this->tipo_gestion = $tipo; }

    public function getSedes() { return $this->sedes; }
    public function setSedes($sedes) { $this->sedes = $sedes; }

    public function getContactos() { return $this->contactos; }
    public function setContactos($contactos) { $this->contactos = $contactos; }

    // =========================================================================
    // MÉTODOS DE BASE DE DATOS Y LÓGICA DE NEGOCIO
    // =========================================================================

    /**
     * Busca una institución registrada por su RIF/documento de forma segura
     */
    public static function buscarPorDocumento($db, $tipo_documento, $documento) {
        if (!$db) return null;

        try {
            $query = "SELECT e.id AS entidad_id, i.id AS institucion_id, e.tipo_documento, e.documento, e.nombre_completo, 
                             i.tipo_organizacion, i.tipo_gestion
                      FROM entidades e
                      INNER JOIN instituciones i ON i.entidad_id = e.id
                      WHERE UPPER(e.tipo_documento) = UPPER(:tipo_documento) AND e.documento = :documento 
                      LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tipo_documento", $tipo_documento, PDO::PARAM_STR);
            $stmt->bindParam(":documento", $documento, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Nombre alias para retrocompatibilidad
                $row['nombre'] = $row['nombre_completo'];

                // Buscar todas las sedes de la institución
                $querySedes = "SELECT s.id, s.institucion_id, s.ciudad_id, s.parroquia_id, s.detalles,
                                      c.ciudad, p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                               FROM sedes s
                               LEFT JOIN ciudades c ON s.ciudad_id = c.id_ciudad
                               LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                               LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                               LEFT JOIN estados est ON m.id_estado = est.id_estado
                               WHERE s.institucion_id = :institucion_id
                               ORDER BY s.id ASC";
                $stmtSedes = $db->prepare($querySedes);
                $stmtSedes->bindParam(":institucion_id", $row['institucion_id'], PDO::PARAM_INT);
                $stmtSedes->execute();
                $row['sedes'] = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

                // Asignar primera sede como fallback para compatibilidad
                if (!empty($row['sedes'])) {
                    $firstSede = $row['sedes'][0];
                    $row['ciudad_id'] = $firstSede['ciudad_id'];
                    $row['parroquia_id'] = $firstSede['parroquia_id'];
                    $row['detalles_direccion'] = $firstSede['detalles'];
                    $row['id_estado'] = $firstSede['id_estado'];
                    $row['id_municipio'] = $firstSede['id_municipio'];
                    $row['estado'] = $firstSede['estado'];
                    $row['municipio'] = $firstSede['municipio'];
                    $row['parroquia'] = $firstSede['parroquia'];
                    $row['ciudad'] = $firstSede['ciudad'];
                }

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
     * Soporta registro de una o más sedes
     */
    public function crear() {
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
            $querySede = "INSERT INTO sedes (institucion_id, ciudad_id, parroquia_id, detalles) 
                          VALUES (:institucion_id, :ciudad_id, :parroquia_id, :detalles)";
            $stmtSede = $this->db->prepare($querySede);

            foreach ($this->sedes as $s) {
                $ciudadId = (int)($s['ciudad_id'] ?? 0);
                $parroquiaId = (int)($s['parroquia_id'] ?? 0);
                $detalles = $s['detalles'] ?? null;

                if ($ciudadId > 0 && $parroquiaId > 0) {
                    $stmtSede->bindValue(":institucion_id", $institucionId, PDO::PARAM_INT);
                    $stmtSede->bindValue(":ciudad_id", $ciudadId, PDO::PARAM_INT);
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
    public static function listarTodas($db) {
        if (!$db) return [];
        try {
            $query = "SELECT i.id, e.id AS entidad_id, e.tipo_documento, e.documento, e.nombre_completo, 
                             e.nombre_completo AS nombre, i.tipo_organizacion, i.tipo_gestion,
                             (SELECT COUNT(*) FROM citas c WHERE c.institucion_id = i.id) AS total_citas
                      FROM instituciones i
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      ORDER BY i.id DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $instituciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($instituciones)) {
                return [];
            }

            // Cargar todas las sedes agrupadas
            $querySedes = "SELECT s.id, s.institucion_id, s.ciudad_id, s.parroquia_id, s.detalles,
                                  c.ciudad, p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                           FROM sedes s
                           LEFT JOIN ciudades c ON s.ciudad_id = c.id_ciudad
                           LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                           LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                           LEFT JOIN estados est ON m.id_estado = est.id_estado
                           ORDER BY s.id ASC";
            $stmtSedes = $db->prepare($querySedes);
            $stmtSedes->execute();
            $allSedes = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

            $sedesMap = [];
            foreach ($allSedes as $s) {
                $sedesMap[$s['institucion_id']][] = $s;
            }

            // Cargar todos los contactos agrupados
            $queryContacts = "SELECT entidad_id, contacto, tipo FROM directorio";
            $stmtContacts = $db->prepare($queryContacts);
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

                // Datos de la primera sede para compatibilidad
                if (!empty($inst['sedes'])) {
                    $first = $inst['sedes'][0];
                    $inst['ciudad_id'] = $first['ciudad_id'];
                    $inst['parroquia_id'] = $first['parroquia_id'];
                    $inst['detalles_direccion'] = $first['detalles'];
                    $inst['ciudad'] = $first['ciudad'];
                    $inst['parroquia'] = $first['parroquia'];
                    $inst['municipio'] = $first['municipio'];
                    $inst['estado'] = $first['estado'];
                    $inst['id_estado'] = $first['id_estado'];
                    $inst['id_municipio'] = $first['id_municipio'];
                } else {
                    $inst['detalles_direccion'] = '';
                    $inst['ciudad'] = '';
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
     */
    public static function buscarPorTermino($db, $term) {
        if (!$db) return [];
        try {
            $query = "SELECT i.id, e.id AS entidad_id, e.tipo_documento, e.documento, e.nombre_completo,
                             e.nombre_completo AS nombre, i.tipo_organizacion, i.tipo_gestion,
                             (SELECT COUNT(*) FROM citas c WHERE c.institucion_id = i.id) AS total_citas
                      FROM instituciones i
                      INNER JOIN entidades e ON i.entidad_id = e.id
                      WHERE e.nombre_completo LIKE :term1 
                         OR e.documento LIKE :term2 
                         OR CONCAT(e.tipo_documento, '-', e.documento) LIKE :term3
                      ORDER BY e.nombre_completo ASC
                      LIMIT 10";
            $stmt = $db->prepare($query);
            $likeTerm = '%' . $term . '%';
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
            $querySedes = "SELECT s.id, s.institucion_id, s.ciudad_id, s.parroquia_id, s.detalles,
                                  c.ciudad, p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                           FROM sedes s
                           LEFT JOIN ciudades c ON s.ciudad_id = c.id_ciudad
                           LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                           LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                           LEFT JOIN estados est ON m.id_estado = est.id_estado
                           WHERE s.institucion_id IN ($placeholdersInst)
                           ORDER BY s.id ASC";
            $stmtSedes = $db->prepare($querySedes);
            $stmtSedes->execute($instIds);
            $allSedes = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

            $sedesMap = [];
            foreach ($allSedes as $s) {
                $sedesMap[$s['institucion_id']][] = $s;
            }

            // Cargar contactos
            $placeholdersEnt = implode(',', array_fill(0, count($entidadIds), '?'));
            $queryContacts = "SELECT entidad_id, contacto, tipo FROM directorio WHERE entidad_id IN ($placeholdersEnt)";
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
                $inst['total_citas'] = (int)($inst['total_citas'] ?? 0);
                $inst['sedes'] = $sedesMap[$inst['id']] ?? [];
                $inst['contactos'] = $contactsMap[$inst['entidad_id']] ?? [];

                if (!empty($inst['sedes'])) {
                    $first = $inst['sedes'][0];
                    $inst['ciudad_id'] = $first['ciudad_id'];
                    $inst['parroquia_id'] = $first['parroquia_id'];
                    $inst['detalles_direccion'] = $first['detalles'];
                    $inst['ciudad'] = $first['ciudad'];
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
     * Obtiene la información detallada de una institución por su ID con todas sus sedes y contactos
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) return null;
        try {
            $query = "SELECT e.id AS entidad_id, i.id AS institucion_id, e.tipo_documento, e.documento, e.nombre_completo,
                             e.nombre_completo AS nombre, i.tipo_organizacion, i.tipo_gestion,
                             (SELECT COUNT(*) FROM citas c WHERE c.institucion_id = i.id) AS total_citas
                      FROM entidades e
                      INNER JOIN instituciones i ON i.entidad_id = e.id
                      WHERE i.id = :id 
                      LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $row['total_citas'] = (int)($row['total_citas'] ?? 0);
                // Cargar sedes
                $querySedes = "SELECT s.id, s.institucion_id, s.ciudad_id, s.parroquia_id, s.detalles,
                                      c.ciudad, p.parroquia, m.id_municipio, m.municipio, est.id_estado, est.estado
                               FROM sedes s
                               LEFT JOIN ciudades c ON s.ciudad_id = c.id_ciudad
                               LEFT JOIN parroquias p ON s.parroquia_id = p.id_parroquia
                               LEFT JOIN municipios m ON p.id_municipio = m.id_municipio
                               LEFT JOIN estados est ON m.id_estado = est.id_estado
                               WHERE s.institucion_id = :id
                               ORDER BY s.id ASC";
                $stmtSedes = $db->prepare($querySedes);
                $stmtSedes->bindParam(":id", $id, PDO::PARAM_INT);
                $stmtSedes->execute();
                $row['sedes'] = $stmtSedes->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($row['sedes'])) {
                    $first = $row['sedes'][0];
                    $row['ciudad_id'] = $first['ciudad_id'];
                    $row['parroquia_id'] = $first['parroquia_id'];
                    $row['detalles_direccion'] = $first['detalles'];
                    $row['id_estado'] = $first['id_estado'];
                    $row['id_municipio'] = $first['id_municipio'];
                    $row['estado'] = $first['estado'];
                    $row['municipio'] = $first['municipio'];
                    $row['parroquia'] = $first['parroquia'];
                    $row['ciudad'] = $first['ciudad'];
                }

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
     * Sincroniza las sedes (agrega, modifica o reemplaza) y contactos
     */
    public function actualizar() {
        if (!$this->db || !$this->id) {
            throw new Exception("Parámetros insuficientes para actualizar la institución.");
        }

        try {
            $this->db->beginTransaction();

            // 2. Actualizar la tabla entidades
            $queryEntidad = "UPDATE entidades 
                             SET tipo_documento = :tipo_documento, documento = :documento, nombre_completo = :nombre_completo 
                             WHERE id = :entidad_id";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindValue(":tipo_documento", $this->tipo_documento, PDO::PARAM_STR);
            $stmtEntidad->bindValue(":documento", $this->documento, PDO::PARAM_STR);
            $stmtEntidad->bindValue(":nombre_completo", $this->nombre_completo, PDO::PARAM_STR);
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

            // 4. Actualizar sedes
            // Obtenemos sedes actuales asociadas a citas para no violar FKs
            $queryCitasSedes = "SELECT DISTINCT sede_id FROM citas WHERE institucion_id = :id AND sede_id IS NOT NULL";
            $stmtCitasSedes = $this->db->prepare($queryCitasSedes);
            $stmtCitasSedes->bindValue(":id", $this->id, PDO::PARAM_INT);
            $stmtCitasSedes->execute();
            $sedesEnCitas = $stmtCitasSedes->fetchAll(PDO::FETCH_COLUMN);

            // Obtenemos los IDs de las sedes enviadas
            $incomingSedeIds = [];
            foreach ($this->sedes as $s) {
                if (!empty($s['id']) && (int)$s['id'] > 0) {
                    $incomingSedeIds[] = (int)$s['id'];
                }
            }

            // Eliminar sedes que ya no existen (excepto si están asociadas a citas, o si no están en incoming)
            if (!empty($incomingSedeIds)) {
                $placeholders = implode(',', array_fill(0, count($incomingSedeIds), '?'));
                $queryDeleteSedes = "DELETE FROM sedes WHERE institucion_id = ? AND id NOT IN ($placeholders)";
                $params = array_merge([$this->id], $incomingSedeIds);
                $stmtDelSedes = $this->db->prepare($queryDeleteSedes);
                $stmtDelSedes->execute($params);
            } else {
                // Si no se pasaron IDs existentes, borrar las que no estén en citas
                $queryDeleteSedes = "DELETE FROM sedes WHERE institucion_id = :id";
                $stmtDelSedes = $this->db->prepare($queryDeleteSedes);
                $stmtDelSedes->bindValue(":id", $this->id, PDO::PARAM_INT);
                $stmtDelSedes->execute();
            }

            // Insertar o actualizar sedes
            $queryInsertSede = "INSERT INTO sedes (institucion_id, ciudad_id, parroquia_id, detalles) 
                                VALUES (:institucion_id, :ciudad_id, :parroquia_id, :detalles)";
            $stmtInsertSede = $this->db->prepare($queryInsertSede);

            $queryUpdateSede = "UPDATE sedes 
                                SET ciudad_id = :ciudad_id, parroquia_id = :parroquia_id, detalles = :detalles 
                                WHERE id = :id AND institucion_id = :institucion_id";
            $stmtUpdateSede = $this->db->prepare($queryUpdateSede);

            foreach ($this->sedes as $s) {
                $sedeId = !empty($s['id']) ? (int)$s['id'] : null;
                $ciudadId = (int)($s['ciudad_id'] ?? 0);
                $parroquiaId = (int)($s['parroquia_id'] ?? 0);
                $detalles = $s['detalles'] ?? null;

                if ($ciudadId > 0 && $parroquiaId > 0) {
                    if ($sedeId) {
                        // Actualizar sede existente
                        $stmtUpdateSede->bindValue(":ciudad_id", $ciudadId, PDO::PARAM_INT);
                        $stmtUpdateSede->bindValue(":parroquia_id", $parroquiaId, PDO::PARAM_INT);
                        $stmtUpdateSede->bindValue(":detalles", $detalles, PDO::PARAM_STR);
                        $stmtUpdateSede->bindValue(":id", $sedeId, PDO::PARAM_INT);
                        $stmtUpdateSede->bindValue(":institucion_id", $this->id, PDO::PARAM_INT);
                        $stmtUpdateSede->execute();
                    } else {
                        // Insertar nueva sede
                        $stmtInsertSede->bindValue(":institucion_id", $this->id, PDO::PARAM_INT);
                        $stmtInsertSede->bindValue(":ciudad_id", $ciudadId, PDO::PARAM_INT);
                        $stmtInsertSede->bindValue(":parroquia_id", $parroquiaId, PDO::PARAM_INT);
                        $stmtInsertSede->bindValue(":detalles", $detalles, PDO::PARAM_STR);
                        $stmtInsertSede->execute();
                    }
                }
            }

            // 5. Actualizar contactos (eliminar anteriores y guardar nuevos)
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
     * Cuenta el número de citas asociadas a una institución (pasadas, activas o futuras).
     */
    public static function contarCitas($db, $id) {
        if (!$db) return 0;
        $cleanId = (int)$id;
        if ($cleanId <= 0) return 0;
        try {
            $query = "SELECT COUNT(*) FROM citas WHERE institucion_id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(":id", $cleanId, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error en Institucion::contarCitas: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Elimina una institución del sistema si no posee citas asociadas
     */
    public function eliminar($id) {
        if (!$this->db) {
            throw new Exception("Conexión de base de datos no inicializada.");
        }

        try {
            // Obtener el entidad_id de la institución a eliminar
            $queryEntidad = "SELECT entidad_id FROM instituciones WHERE id = :id LIMIT 1";
            $stmtEntidad = $this->db->prepare($queryEntidad);
            $stmtEntidad->bindValue(":id", $id, PDO::PARAM_INT);
            $stmtEntidad->execute();
            $entidadId = $stmtEntidad->fetchColumn();

            if (!$entidadId) {
                return false;
            }

            $this->db->beginTransaction();

            // 1. Eliminar sedes
            $queryDeleteSedes = "DELETE FROM sedes WHERE institucion_id = :id";
            $stmtDeleteSedes = $this->db->prepare($queryDeleteSedes);
            $stmtDeleteSedes->bindValue(":id", $id, PDO::PARAM_INT);
            $stmtDeleteSedes->execute();

            // 3. Eliminar directorio de contactos
            $queryDeleteDirContacts = "DELETE FROM directorio WHERE entidad_id = :entidad_id";
            $stmtDeleteDirContacts = $this->db->prepare($queryDeleteDirContacts);
            $stmtDeleteDirContacts->bindValue(":entidad_id", $entidadId, PDO::PARAM_INT);
            $stmtDeleteDirContacts->execute();

            // 3. Eliminar institución
            $queryDeleteInst = "DELETE FROM instituciones WHERE id = :id";
            $stmtDeleteInst = $this->db->prepare($queryDeleteInst);
            $stmtDeleteInst->bindValue(":id", $id, PDO::PARAM_INT);
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
