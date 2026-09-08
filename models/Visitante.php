<?php
/**
 * Modelo de Visitantes (Visitante)
 * Gestiona de forma segura los datos de los visitantes y el proceso de importación y enlace a citas.
 */
class Visitante {
    private $db;
    private $table_name = "visitantes";

    private $id;
    private $cedula;
    private $nombres;
    private $apellidos;
    private $fecha_nacimiento;

    public function __construct($db = null) {
        $this->db = $db;
    }

    // Getters y Setters
    public function getId() { return $this->id; }
    public function setId($id) { $this->id = $id; }

    public function getCedula() { return $this->cedula; }
    public function setCedula($cedula) { $this->cedula = $cedula; }

    public function getNombres() { return $this->nombres; }
    public function setNombres($nombres) { $this->nombres = $nombres; }

    public function getApellidos() { return $this->apellidos; }
    public function setApellidos($apellidos) { $this->apellidos = $apellidos; }

    public function getFechaNacimiento() { return $this->fecha_nacimiento; }
    public function setFechaNacimiento($fecha) { $this->fecha_nacimiento = $fecha; }

    /**
     * Importa una lista de visitantes vinculándolos a una cita.
     * Reutiliza visitantes existentes por cédula para evitar redundancias de datos.
     */
    public static function importarVisitantesCita($db, $citaId, $visitantesArray) {
        if (!$db) return false;

        try {
            $db->beginTransaction();

            // Preparar consultas repetitivas para optimizar velocidad
            $stmtBuscarCedula = $db->prepare("SELECT id FROM visitantes WHERE cedula = :cedula LIMIT 1");
            $stmtBuscarDatos = $db->prepare("SELECT id FROM visitantes WHERE nombres = :nombres AND apellidos = :apellidos AND fecha_nacimiento = :fecha_nacimiento AND cedula IS NULL LIMIT 1");
            $stmtInsertar = $db->prepare("INSERT INTO visitantes (cedula, nombres, apellidos, fecha_nacimiento) 
                                          VALUES (:cedula, :nombres, :apellidos, :fecha_nacimiento)");
            $stmtVerificarLink = $db->prepare("SELECT id FROM asistencia_cita WHERE cita_id = :cita_id AND visitante_id = :visitante_id LIMIT 1");
            $stmtInsertarLink = $db->prepare("INSERT INTO asistencia_cita (cita_id, visitante_id) VALUES (:cita_id, :visitante_id)");

            foreach ($visitantesArray as $v) {
                $cedula = $v['cedula'] ?? null;
                $nombres = $v['nombres'] ?? '';
                $apellidos = $v['apellidos'] ?? '';
                $fechaNac = $v['fecha_nacimiento'] ?? '';

                $visitanteId = null;

                // 1. Buscar registro previo para evitar redundancias de datos
                if ($cedula !== null) {
                    $stmtBuscarCedula->bindParam(":cedula", $cedula, PDO::PARAM_STR);
                    $stmtBuscarCedula->execute();
                    $visitanteId = $stmtBuscarCedula->fetchColumn();
                } else {
                    $stmtBuscarDatos->bindParam(":nombres", $nombres, PDO::PARAM_STR);
                    $stmtBuscarDatos->bindParam(":apellidos", $apellidos, PDO::PARAM_STR);
                    $stmtBuscarDatos->bindParam(":fecha_nacimiento", $fechaNac, PDO::PARAM_STR);
                    $stmtBuscarDatos->execute();
                    $visitanteId = $stmtBuscarDatos->fetchColumn();
                }

                // 2. Si no existe en la base de datos, registrarlo
                if (!$visitanteId) {
                    $stmtInsertar->bindParam(":cedula", $cedula, $cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $stmtInsertar->bindParam(":nombres", $nombres, PDO::PARAM_STR);
                    $stmtInsertar->bindParam(":apellidos", $apellidos, PDO::PARAM_STR);
                    $stmtInsertar->bindParam(":fecha_nacimiento", $fechaNac, PDO::PARAM_STR);
                    $stmtInsertar->execute();
                    $visitanteId = $db->lastInsertId();
                }

                // 3. Verificar si el visitante ya está agendado en la cita actual
                $stmtVerificarLink->bindParam(":cita_id", $citaId, PDO::PARAM_INT);
                $stmtVerificarLink->bindParam(":visitante_id", $visitanteId, PDO::PARAM_INT);
                $stmtVerificarLink->execute();
                $linkExists = $stmtVerificarLink->fetchColumn();

                // 4. Si no está vinculado, insertamos la fila en la tabla pivote
                if (!$linkExists) {
                    $stmtInsertarLink->bindParam(":cita_id", $citaId, PDO::PARAM_INT);
                    $stmtInsertarLink->bindParam(":visitante_id", $visitanteId, PDO::PARAM_INT);
                    $stmtInsertarLink->execute();
                }
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al importar visitantes en Cita ID {$citaId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene el listado completo de todos los visitantes registrados
     */
    public static function listarTodos($db) {
        if (!$db) return [];
        $query = "SELECT id, cedula, nombres, apellidos, fecha_nacimiento FROM visitantes ORDER BY id DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un visitante específico por su ID
     */
    public static function obtenerPorId($db, $id) {
        if (!$db) return null;
        $query = "SELECT id, cedula, nombres, apellidos, fecha_nacimiento FROM visitantes WHERE id = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los visitantes asignados a una cita específica
     */
    public static function obtenerPorCitaId($db, $citaId) {
        if (!$db) return [];
        $query = "SELECT v.id, v.cedula, v.nombres, v.apellidos, v.fecha_nacimiento 
                  FROM visitantes v
                  INNER JOIN asistencia_cita ac ON v.id = ac.visitante_id
                  WHERE ac.cita_id = :cita_id
                  ORDER BY v.id ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":cita_id", $citaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca un visitante por su cédula, opcionalmente excluyendo un ID específico (para updates)
     */
    public static function buscarPorCedula($db, $cedula, $excludeId = null) {
        if (!$db) return null;

        $query = "SELECT id, cedula, nombres, apellidos, fecha_nacimiento FROM visitantes WHERE cedula = :cedula";
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }
        $query .= " LIMIT 1";

        $stmt = $db->prepare($query);
        $stmt->bindValue(":cedula", $cedula, PDO::PARAM_STR);
        if ($excludeId) {
            $stmt->bindValue(":exclude_id", $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Registra un nuevo visitante de forma manual
     */
    public function registrar($db) {
        if (!$db) return false;
        
        $query = "INSERT INTO visitantes (cedula, nombres, apellidos, fecha_nacimiento) 
                  VALUES (:cedula, :nombres, :apellidos, :fecha_nacimiento)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":cedula", $this->cedula, $this->cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(":nombres", $this->nombres, PDO::PARAM_STR);
        $stmt->bindParam(":apellidos", $this->apellidos, PDO::PARAM_STR);
        $stmt->bindParam(":fecha_nacimiento", $this->fecha_nacimiento, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $this->id = $db->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Actualiza los datos de un visitante existente
     */
    public function actualizar($db) {
        if (!$db) return false;
        
        $query = "UPDATE visitantes 
                  SET cedula = :cedula, nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento 
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":cedula", $this->cedula, $this->cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(":nombres", $this->nombres, PDO::PARAM_STR);
        $stmt->bindParam(":apellidos", $this->apellidos, PDO::PARAM_STR);
        $stmt->bindParam(":fecha_nacimiento", $this->fecha_nacimiento, PDO::PARAM_STR);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Elimina un visitante y sus vínculos de forma segura
     */
    public static function eliminar($db, $id) {
        if (!$db) return false;
        try {
            $db->beginTransaction();
            
            // Eliminar vínculos en asistencia_cita
            $queryLink = "DELETE FROM asistencia_cita WHERE visitante_id = :id";
            $stmtLink = $db->prepare($queryLink);
            $stmtLink->bindParam(":id", $id, PDO::PARAM_INT);
            $stmtLink->execute();
            
            // Eliminar visitante
            $queryVis = "DELETE FROM visitantes WHERE id = :id";
            $stmtVis = $db->prepare($queryVis);
            $stmtVis->bindParam(":id", $id, PDO::PARAM_INT);
            $stmtVis->execute();
            
            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al eliminar visitante ID {$id}: " . $e->getMessage());
            return false;
        }
    }
}

