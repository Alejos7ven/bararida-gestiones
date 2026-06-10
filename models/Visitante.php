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
    public function setId($id) { $this->id = (int)$id; }

    public function getCedula() { return $this->cedula; }
    public function setCedula($cedula) {
        $clean = preg_replace('/[^0-9]/', '', trim($cedula));
        $this->cedula = !empty($clean) ? $clean : null;
    }

    public function getNombres() { return $this->nombres; }
    public function setNombres($nombres) {
        $clean = strip_tags(trim($nombres));
        if (!empty($clean) && strlen($clean) <= 100) {
            $this->nombres = $clean;
        } else {
            throw new InvalidArgumentException("El nombre del visitante es requerido y debe tener menos de 100 caracteres.");
        }
    }

    public function getApellidos() { return $this->apellidos; }
    public function setApellidos($apellidos) {
        $clean = strip_tags(trim($apellidos));
        if (!empty($clean) && strlen($clean) <= 100) {
            $this->apellidos = $clean;
        } else {
            throw new InvalidArgumentException("El apellido del visitante es requerido y debe tener menos de 100 caracteres.");
        }
    }

    public function getFechaNacimiento() { return $this->fecha_nacimiento; }
    public function setFechaNacimiento($fecha) {
        $clean = trim($fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
            $this->fecha_nacimiento = $clean;
        } else {
            throw new InvalidArgumentException("La fecha de nacimiento debe tener el formato AAAA-MM-DD.");
        }
    }

    /**
     * Importa de forma masiva y atómica una lista de visitantes vinculándolos a una cita.
     * Reutiliza visitantes existentes por cédula para evitar redundancias de datos.
     */
    public static function importarVisitantesCita($db, $citaId, $visitantesArray) {
        if (!$db) return false;

        $cleanCitaId = filter_var($citaId, FILTER_VALIDATE_INT) ? (int)$citaId : null;
        if (!$cleanCitaId) {
            throw new InvalidArgumentException("ID de cita inválido para importar visitantes.");
        }

        try {
            $db->beginTransaction();

            // Preparar consultas repetitivas para optimizar velocidad
            $stmtBuscarCedula = $db->prepare("SELECT id FROM visitantes WHERE cedula = :cedula LIMIT 1");
            $stmtBuscarDatos = $db->prepare("SELECT id FROM visitantes WHERE nombres = :nombres AND apellidos = :apellidos AND fecha_nacimiento = :fecha_nacimiento AND cedula IS NULL LIMIT 1");
            $stmtInsertar = $db->prepare("INSERT INTO visitantes (cedula, nombres, apellidos, fecha_nacimiento) 
                                          VALUES (:cedula, :nombres, :apellidos, :fecha_nacimiento)");
            $stmtVerificarLink = $db->prepare("SELECT id FROM asistencia_cita WHERE cita_id = :cita_id AND visitante_id = :visitante_id LIMIT 1");
            $stmtInsertarLink = $db->prepare("INSERT INTO asistencia_cita (cita_id, visitante_id) VALUES (:cita_id, :visitante_id)");

            foreach ($visitantesArray as $index => $v) {
                $rawCedula = preg_replace('/[^0-9]/', '', trim($v['cedula'] ?? ''));
                $cedula = !empty($rawCedula) ? $rawCedula : null;
                $nombres = strip_tags(trim($v['nombres'] ?? ''));
                $apellidos = strip_tags(trim($v['apellidos'] ?? ''));
                $fechaNac = trim($v['fecha_nacimiento'] ?? '');

                if (empty($nombres) || empty($apellidos) || empty($fechaNac)) {
                    throw new InvalidArgumentException("Fila #" . ($index + 1) . ": Los campos Nombres, Apellidos y Fecha Nacimiento son obligatorios.");
                }

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNac)) {
                    throw new InvalidArgumentException("Fila #" . ($index + 1) . ": La fecha de nacimiento del visitante '{$nombres}' debe tener formato AAAA-MM-DD.");
                }

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
                $stmtVerificarLink->bindParam(":cita_id", $cleanCitaId, PDO::PARAM_INT);
                $stmtVerificarLink->bindParam(":visitante_id", $visitanteId, PDO::PARAM_INT);
                $stmtVerificarLink->execute();
                $linkExists = $stmtVerificarLink->fetchColumn();

                // 4. Si no está vinculado, insertamos la fila en la tabla pivote
                if (!$linkExists) {
                    $stmtInsertarLink->bindParam(":cita_id", $cleanCitaId, PDO::PARAM_INT);
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
            error_log("Error al importar visitantes en Cita ID {$cleanCitaId}: " . $e->getMessage());
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
     * Registra un nuevo visitante de forma manual
     */
    public function registrar($db) {
        if (!$db) return false;
        
        $rawCedula = preg_replace('/[^0-9]/', '', trim($this->cedula ?? ''));
        $cedula = !empty($rawCedula) ? $rawCedula : null;
        
        $query = "INSERT INTO visitantes (cedula, nombres, apellidos, fecha_nacimiento) 
                  VALUES (:cedula, :nombres, :apellidos, :fecha_nacimiento)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":cedula", $cedula, $cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
        
        $rawCedula = preg_replace('/[^0-9]/', '', trim($this->cedula ?? ''));
        $cedula = !empty($rawCedula) ? $rawCedula : null;
        
        $query = "UPDATE visitantes 
                  SET cedula = :cedula, nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento 
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":cedula", $cedula, $cedula === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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

