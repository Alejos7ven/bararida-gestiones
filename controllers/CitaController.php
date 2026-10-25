<?php
/**
 * Controlador de Citas (CitaController)
 * Gestiona el agendamiento de citas, registro de instituciones asociadas y la importación/exportación de visitantes.
 * Acceso restringido exclusivamente a personal autenticado.
 * Respeta estrictamente el patrón MVC operando mediante los modelos Cita, Institucion, Sede, Visitante, Recorrido y Personal.
 * Toda la gestión de base de datos se delega a los modelos a través de getters y setters.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar encabezados
// Para la acción de descargar plantilla, el encabezado se cambiará a descarga binaria, de lo contrario será application/json
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($action !== 'descargar_plantilla') {
    header("Content-Type: application/json; charset=UTF-8");
}

// Verificar autenticación
if (!isset($_SESSION['personal'])) {
    if ($action === 'descargar_plantilla') {
        header("Location: ../index.php");
    } else {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Sesión no activa. Por favor inicie sesión nuevamente."
        ]);
    }
    exit;
}

// Incluir dependencias del modelo
require_once __DIR__ . "/../config/ExcelHelper.php";
require_once __DIR__ . "/../models/Institucion.php";
require_once __DIR__ . "/../models/Sede.php";
require_once __DIR__ . "/../models/Cita.php";
require_once __DIR__ . "/../models/Visitante.php";
require_once __DIR__ . "/../models/Permiso.php";
require_once __DIR__ . "/../models/Personal.php";
require_once __DIR__ . "/../models/Recorrido.php";

/**
 * Verifica si el usuario autenticado tiene permiso para acceder al módulo de citas según el RBAC
 * @return bool
 */
function usuarioTienePermisoCitas(): bool {
    $personal = $_SESSION['personal'] ?? null;
    if (!$personal) {
        return false;
    }

    $rol = strtolower(trim((string)($personal['rol'] ?? '')));
    if ($rol === 'superadmin') {
        return true;
    }

    $rolId = isset($personal['rol_id']) ? (int)$personal['rol_id'] : 0;
    if ($rolId <= 0 && $rol !== '') {
        try {
            $permisoModel = new Permiso();
            $rolId = $permisoModel->obtenerRolIdPorNombre($rol) ?? 0;
            if ($rolId > 0) {
                $_SESSION['personal']['rol_id'] = $rolId;
            }
        } catch (Exception $e) {
            error_log("Error al consultar rol_id en usuarioTienePermisoCitas: " . $e->getMessage());
        }
    }

    if ($rolId > 0) {
        try {
            $permisoModel = new Permiso();
            $permisos = $permisoModel->getPermisosPorRol($rolId);
            if (isset($permisos['citas'])) {
                return (bool)$permisos['citas'];
            }
        } catch (Exception $e) {
            error_log("Error al consultar permisos en usuarioTienePermisoCitas: " . $e->getMessage());
        }
    }

    // Fallback estándar según rol
    return ($rol === 'admin' || $rol === 'guia' || $rol === 'guía');
}

// Procesar según método HTTP y acción
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'crear':
            crearCitaAction();
            break;
        case 'editar':
            editarCitaAction();
            break;
        case 'eliminar':
            eliminarCitaAction();
            break;
        case 'importar_visitantes':
            importarVisitantesAction();
            break;
        case 'vincular_visitantes':
            vincularVisitantesAction();
            break;
        case 'desvincular_visitante':
            desvincularVisitanteAction();
            break;
        case 'asignar_guia':
            asignarGuiaAction();
            break;
        case 'desasignar_guia':
            desasignarGuiaAction();
            break;
        case 'empezar_recorrido':
            empezarRecorridoAction();
            break;
        case 'terminar_recorrido':
            terminarRecorridoAction();
            break;
        case 'asignar_recorrido':
            asignarRecorridoAction();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción POST no válida para Citas."
            ]);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'listar':
            listarCitasAction();
            break;
        case 'obtener':
            obtenerCitaAction();
            break;
        case 'comprobar_institucion':
            comprobarInstitucionAction();
            break;
        case 'descargar_plantilla':
            descargarPlantillaAction();
            break;
        case 'listar_visitantes':
            listarVisitantesAction();
            break;
        case 'obtener_datos_dashboard':
            obtenerDatosDashboardAction();
            break;
        case 'citas_hoy':
            citasHoyAction();
            break;
        case 'listar_guias':
            listarGuiasAction();
            break;
        case 'guias_cita':
            guiasCitaAction();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción GET no válida para Citas."
            ]);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Método HTTP no permitido."
    ]);
}

// =========================================================================
// ACCIONES DEL CONTROLADOR
// =========================================================================

/**
 * Comprueba si la institución existe por su tipo y número de documento
 */
function comprobarInstitucionAction() {
    $tipo_documento = isset($_GET['tipo_documento']) ? strtoupper(trim($_GET['tipo_documento'])) : 'J';
    $documento = isset($_GET['documento']) ? trim($_GET['documento']) : '';

    if (empty($documento)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El número de documento de la institución es requerido."
        ]);
        return;
    }

    $instModel = new Institucion();
    $instModel->setTipoDocumento($tipo_documento);
    $instModel->setDocumento($documento);
    $institucionData = $instModel->buscarPorDocumento();

    http_response_code(200);
    if ($institucionData) {
        echo json_encode([
            "status" => "success",
            "exists" => true,
            "data" => $institucionData
        ]);
    } else {
        echo json_encode([
            "status" => "success",
            "exists" => false
        ]);
    }
}

/**
 * Lista todas las citas activas
 */
function listarCitasAction() {
    if (!usuarioTienePermisoCitas()) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Acceso no autorizado para consultar citas."
        ]);
        return;
    }

    try {
        $citaModel = new Cita();
        $citas = $citaModel->listarTodas();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $citas
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener listado de citas: " . $e->getMessage()
        ]);
    }
}

/**
 * Registra institución (si aplica) y la cita
 */
function crearCitaAction() {
    if (!usuarioTienePermisoCitas()) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Acceso no autorizado para crear citas."
        ]);
        return;
    }

    $institucion_id = (!empty($_POST['institucion_id']) && filter_var($_POST['institucion_id'], FILTER_VALIDATE_INT)) ? (int)$_POST['institucion_id'] : null;
    $sede_id = (!empty($_POST['sede_id']) && filter_var($_POST['sede_id'], FILTER_VALIDATE_INT)) ? (int)$_POST['sede_id'] : null;
    
    // Datos de la Cita
    $recorrido_id = (isset($_POST['recorrido_id']) && trim($_POST['recorrido_id']) !== '') ? trim($_POST['recorrido_id']) : null;
    $fecha_visita = isset($_POST['fecha_visita']) ? trim($_POST['fecha_visita']) : '';
    $hora_inicio = isset($_POST['hora_inicio']) ? trim($_POST['hora_inicio']) : '';
    $tipo = isset($_POST['tipo']) ? trim(strtolower($_POST['tipo'])) : '';
    $metodo_pago = isset($_POST['metodo_pago']) ? trim($_POST['metodo_pago']) : '';
    $pago_estado = isset($_POST['pago_estado']) ? trim($_POST['pago_estado']) : 'pendiente';

    $tiposValidos = ['campamento nocturno', 'corporativo', 'evento', 'institucional', 'otro', 'particular'];

    if (empty($fecha_visita) || empty($hora_inicio) || empty($tipo) || empty($metodo_pago)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los campos fecha de visita, hora de inicio, tipo de cita y método de pago son requeridos.",
            "field" => empty($fecha_visita) ? "fecha_visita" : (empty($hora_inicio) ? "hora_inicio" : (empty($tipo) ? "tipo" : "metodo_pago"))
        ]);
        return;
    }

    if (strlen($tipo) > 40 || !in_array($tipo, $tiposValidos, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El tipo de cita seleccionado no es válido.",
            "field" => "tipo"
        ]);
        return;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_visita)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La fecha de la visita debe tener el formato AAAA-MM-DD.",
            "field" => "fecha_visita"
        ]);
        return;
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_inicio)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La hora de inicio de la visita no es válida.",
            "field" => "hora_inicio"
        ]);
        return;
    }

    if (strlen($metodo_pago) > 50) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El método de pago no es válido.",
            "field" => "metodo_pago"
        ]);
        return;
    }

    if (!in_array($pago_estado, ['pendiente', 'pagado'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El estado de pago no es válido.",
            "field" => "pago_estado"
        ]);
        return;
    }

    if ($recorrido_id !== null) {
        if (!filter_var($recorrido_id, FILTER_VALIDATE_INT)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "El recorrido seleccionado no es válido.",
                "field" => "recorrido_id"
            ]);
            return;
        }
        $recorrido_id = (int)$recorrido_id;
    }

    try {
        // 1. Si no existe ID de institución, registrarla primero con sus sedes
        if (!$institucion_id) {
            $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'J';
            $documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';
            $nombre_completo = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : (isset($_POST['nombre']) ? trim($_POST['nombre']) : '');
            $tipo_organizacion = isset($_POST['tipo_organizacion']) ? trim($_POST['tipo_organizacion']) : '';
            $tipo_gestion = isset($_POST['tipo_gestion']) ? trim($_POST['tipo_gestion']) : 'publica';
            $sedesRaw = isset($_POST['sedes']) ? $_POST['sedes'] : [];
            $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

            // Fallback: si se envió parroquia_id plano
            if (empty($sedesRaw) && !empty($_POST['parroquia_id'])) {
                $sedesRaw[] = [
                    'parroquia_id' => $_POST['parroquia_id'],
                    'detalles' => $_POST['detalles_direccion'] ?? ''
                ];
            }

            if (empty($documento) || empty($nombre_completo) || empty($tipo_organizacion) || empty($tipo_gestion)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Para registrar la institución se requiere RIF, Razón Social, Tipo de Organización y Tipo de Gestión."
                ]);
                return;
            }

            if (!in_array($tipo_documento, ['J', 'G', 'V', 'E', 'P'], true)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Tipo de documento inválido para la institución."
                ]);
                return;
            }

            // Validar sedes
            $sedesValidadas = [];
            if (is_array($sedesRaw)) {
                foreach ($sedesRaw as $s) {
                    $pId = filter_var($s['parroquia_id'] ?? 0, FILTER_VALIDATE_INT);
                    $det = strip_tags(trim($s['detalles'] ?? ''));
                    if ($pId) {
                        $sedesValidadas[] = [
                            'parroquia_id' => $pId,
                            'detalles' => substr($det, 0, 150)
                        ];
                    }
                }
            }

            if (empty($sedesValidadas)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Debe agregar al menos una sede válida para registrar la institución."
                ]);
                return;
            }

            // Mapear contactos
            $contactos = [];
            if (is_array($contactosRaw)) {
                foreach ($contactosRaw as $c) {
                    $contVal = strip_tags(trim($c['contacto'] ?? ''));
                    if (!empty($contVal)) {
                        $contactos[] = [
                            'contacto' => substr($contVal, 0, 50),
                            'tipo' => trim($c['tipo'] ?? 'principal')
                        ];
                    }
                }
            }

            if (empty($contactos)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Debe ingresar al menos un número de contacto válido para la institución."
                ]);
                return;
            }

            $instModel = new Institucion();
            $instModel->setTipoDocumento($tipo_documento);
            $instModel->setDocumento($documento);
            $instModel->setNombreCompleto($nombre_completo);
            $instModel->setTipoOrganizacion($tipo_organizacion);
            $instModel->setTipoGestion($tipo_gestion);
            $instModel->setSedes($sedesValidadas);
            $instModel->setContactos($contactos);

            // Guardar institución
            if ($instModel->crear()) {
                $institucion_id = $instModel->getId();
                // Obtener el ID de la primera sede creada
                $sedeModel = new Sede();
                $sedeModel->setInstitucionId($institucion_id);
                $sedesCreadas = $sedeModel->listarPorInstitucion();
                if (!empty($sedesCreadas)) {
                    $sede_id = (int)$sedesCreadas[0]['id'];
                }
            } else {
                throw new Exception("No se pudo registrar la institución.");
            }
        } else {
            // Institución existente: si el usuario solicita registrar una nueva sede al vuelo
            $crearNuevaSede = isset($_POST['crear_nueva_sede']) && $_POST['crear_nueva_sede'] == '1';
            $nuevaParroquiaId = isset($_POST['nueva_sede_parroquia_id']) ? filter_var($_POST['nueva_sede_parroquia_id'], FILTER_VALIDATE_INT) : null;
            $nuevaDetalles = isset($_POST['nueva_sede_detalles']) ? strip_tags(trim($_POST['nueva_sede_detalles'])) : '';

            if ($crearNuevaSede && $nuevaParroquiaId) {
                $sedeModel = new Sede();
                $sedeModel->setInstitucionId($institucion_id);
                $sedeModel->setParroquiaId($nuevaParroquiaId);
                $sedeModel->setDetalles(substr($nuevaDetalles, 0, 150));
                if ($sedeModel->crear()) {
                    $sede_id = $sedeModel->getId();
                }
            }
        }

        // 2. Registrar la cita
        $citaModel = new Cita();
        $citaModel->setInstitucionId($institucion_id);
        $citaModel->setSedeId($sede_id);
        $citaModel->setRecorridoId($recorrido_id);
        $citaModel->setFechaVisita($fecha_visita);
        $citaModel->setHoraInicio($hora_inicio);
        $citaModel->setTipo($tipo);
        $citaModel->setMetodoPago($metodo_pago);
        $citaModel->setPagoEstado($pago_estado);

        if ($citaModel->crear()) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Cita registrada con éxito en el sistema.",
                "cita_id" => $citaModel->getId()
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar la cita en la base de datos."
            ]);
        }
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
}

/**
 * Elimina una cita
 */
function eliminarCitaAction() {
    if (!usuarioTienePermisoCitas()) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Acceso no autorizado para eliminar citas."
        ]);
        return;
    }

    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de cita inválido o no suministrado."
        ]);
        return;
    }

    try {
        $citaModel = new Cita();
        $citaModel->setId($id);
        if ($citaModel->eliminar()) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Cita eliminada correctamente del sistema."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo eliminar la cita."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
}

/**
 * Genera y fuerza la descarga de la plantilla Excel de visitantes
 */
function descargarPlantillaAction() {
    $cita_id = isset($_GET['cita_id']) ? filter_var($_GET['cita_id'], FILTER_VALIDATE_INT) : null;

    if (!$cita_id) {
        die("ID de cita inválido.");
    }

    $citaModel = new Cita();
    $citaModel->setId($cita_id);
    $cita = $citaModel->obtenerPorId();
    if (!$cita) {
        die("La cita no existe en el sistema.");
    }

    $writer = new ExcelWriter();
    $writer->setSheetName('Visitantes');
    $writer->setHeaders(
        ['Cédula (Opcional)', 'Nombres', 'Apellidos', 'Día de Nacimiento (1-31)', 'Mes de Nacimiento (1-12)', 'Año de Nacimiento (ej. 2010)'],
        [22, 28, 28, 28, 28, 28]
    );
    $writer->addRow(['12345678', 'Pedro', 'Pérez', '15', '5', '2010']);

    $filename = "plantilla_visitantes_cita_{$cita_id}.xlsx";
    $writer->download($filename);
}

/**
 * Procesa la importación de visitantes de la cita
 */
function importarVisitantesAction() {
    $cita_id = isset($_POST['cita_id']) ? filter_var($_POST['cita_id'], FILTER_VALIDATE_INT) : null;

    if (!$cita_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de la cita es requerido para importar visitantes."
        ]);
        return;
    }

    $visitantes = [];

    // Prioridad 1: Si se envían los datos previsualizados en formato JSON desde el frontend
    if (isset($_POST['visitantes_json']) && trim((string)$_POST['visitantes_json']) !== '') {
        $rawJson = is_string($_POST['visitantes_json']) ? trim($_POST['visitantes_json']) : '';
        $decoded = json_decode($rawJson, true);

        if (!is_array($decoded)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "El formato de los datos de visitantes enviados no es un JSON válido."
            ]);
            return;
        }

        $todayStr = date('Y-m-d');
        foreach ($decoded as $index => $item) {
            $cedula = isset($item['cedula']) ? trim((string)$item['cedula']) : null;
            $nombres = isset($item['nombres']) ? trim((string)$item['nombres']) : '';
            $apellidos = isset($item['apellidos']) ? trim((string)$item['apellidos']) : '';
            $fechaNac = isset($item['fecha_nacimiento']) ? trim((string)$item['fecha_nacimiento']) : null;

            // Omitir filas totalmente vacías
            if (empty($nombres) && empty($apellidos) && empty($cedula)) {
                continue;
            }

            $rowNum = $index + 1;

            if (empty($nombres)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "El nombre es obligatorio para todos los visitantes (fila $rowNum)."
                ]);
                return;
            }

            if (empty($apellidos)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "El apellido es obligatorio para todos los visitantes (fila $rowNum)."
                ]);
                return;
            }

            // Validar cédula: opcional en menores, pero si se especifica debe ser numérica de 5 a 12 dígitos
            $cedulaVal = null;
            if (!empty($cedula)) {
                if (!preg_match('/^[0-9]{5,12}$/', $cedula)) {
                    http_response_code(400);
                    echo json_encode([
                        "status" => "error",
                        "message" => "La cédula '$cedula' no es válida (debe tener entre 5 y 12 dígitos numéricos, fila $rowNum)."
                    ]);
                    return;
                }
                $cedulaVal = $cedula;
            }

            // Validar fecha de nacimiento: formato AAAA-MM-DD y no futura
            $fechaNacVal = null;
            if (!empty($fechaNac)) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNac)) {
                    http_response_code(400);
                    echo json_encode([
                        "status" => "error",
                        "message" => "La fecha de nacimiento '$fechaNac' no tiene un formato válido (AAAA-MM-DD, fila $rowNum)."
                    ]);
                    return;
                }
                if ($fechaNac > $todayStr) {
                    http_response_code(400);
                    echo json_encode([
                        "status" => "error",
                        "message" => "La fecha de nacimiento no puede ser una fecha futura (fila $rowNum)."
                    ]);
                    return;
                }
                $fechaNacVal = $fechaNac;
            }

            $visitantes[] = [
                'cedula' => $cedulaVal,
                'nombres' => substr($nombres, 0, 100),
                'apellidos' => substr($apellidos, 0, 100),
                'fecha_nacimiento' => $fechaNacVal
            ];
        }
    } elseif (isset($_FILES['archivo_visitantes']) && $_FILES['archivo_visitantes']['error'] === UPLOAD_ERR_OK) {
        // Fallback: Si se envía directamente un archivo físico XLSX o CSV
        $fileTmp = $_FILES['archivo_visitantes']['tmp_name'];
        $fileName = $_FILES['archivo_visitantes']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, ['xlsx', 'csv'])) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Solo se permiten archivos en formato XLSX o CSV."
            ]);
            return;
        }

        if ($fileExt === 'csv') {
            if (($handle = fopen($fileTmp, "r")) !== FALSE) {
                // Descartar BOM si existe
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                // Omitir cabecera
                $firstRow = fgetcsv($handle, 1000, ",");
                if ($firstRow === false || count($firstRow) < 2) {
                    rewind($handle);
                    $firstRow = fgetcsv($handle, 1000, ";");
                    $delimiter = ";";
                } else {
                    $delimiter = ",";
                }

                while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                    if (empty($data) || (count($data) === 1 && trim($data[0]) === '')) {
                        continue;
                    }

                    $cedula = isset($data[0]) ? trim($data[0]) : null;
                    $nombres = isset($data[1]) ? trim($data[1]) : '';
                    $apellidos = isset($data[2]) ? trim($data[2]) : '';
                    $diaNac = isset($data[3]) ? trim($data[3]) : '';
                    $mesNac = isset($data[4]) ? trim($data[4]) : '';
                    $anoNac = isset($data[5]) ? trim($data[5]) : '';

                    if (empty($nombres) && empty($apellidos) && empty($cedula)) {
                        continue;
                    }

                    $cedulaVal = (!empty($cedula) && preg_match('/^[0-9]{5,12}$/', $cedula)) ? $cedula : null;
                    $fechaNac = resolveBirthDatePhp($diaNac, $mesNac, $anoNac);

                    $visitantes[] = [
                        'cedula' => $cedulaVal,
                        'nombres' => substr($nombres, 0, 100),
                        'apellidos' => substr($apellidos, 0, 100),
                        'fecha_nacimiento' => !empty($fechaNac) ? $fechaNac : null
                    ];
                }
                fclose($handle);
            }
        } else {
            // Parsear archivo XLSX nativamente sin dependencias pesadas
            $zip = new ZipArchive();
            if ($zip->open($fileTmp) === TRUE) {
                // 1. Cargar strings compartidos
                $sharedStrings = [];
                $sstXml = $zip->getFromName('xl/sharedStrings.xml');
                if ($sstXml) {
                    $xml = simplexml_load_string($sstXml);
                    if ($xml) {
                        foreach ($xml->si as $si) {
                            if (isset($si->t)) {
                                $sharedStrings[] = (string)$si->t;
                            } elseif (isset($si->r)) {
                                $text = '';
                                foreach ($si->r as $r) {
                                    $text .= (string)$r->t;
                                }
                                $sharedStrings[] = $text;
                            } else {
                                $sharedStrings[] = '';
                            }
                        }
                    }
                }

                // 2. Cargar hoja de cálculo 1
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                if ($sheetXml) {
                    $sheet = simplexml_load_string($sheetXml);
                    if ($sheet && isset($sheet->sheetData->row)) {
                        $isFirst = true;
                        foreach ($sheet->sheetData->row as $row) {
                            if ($isFirst) {
                                $isFirst = false;
                                continue; // Omitir cabecera
                            }

                            $cols = [];
                            foreach ($row->c as $c) {
                                $ref = (string)$c['r'];
                                $colLetter = preg_replace('/[0-9]/', '', $ref);
                                $t = (string)$c['t'];
                                $val = isset($c->v) ? (string)$c->v : '';

                                if ($t === 's' && isset($sharedStrings[(int)$val])) {
                                    $val = $sharedStrings[(int)$val];
                                }

                                $cols[$colLetter] = trim($val);
                            }

                            // Columna A = Cédula, B = Nombres, C = Apellidos, D = Día, E = Mes, F = Año
                            $cedula = $cols['A'] ?? null;
                            $nombres = $cols['B'] ?? '';
                            $apellidos = $cols['C'] ?? '';
                            $diaNac = $cols['D'] ?? '';
                            $mesNac = $cols['E'] ?? '';
                            $anoNac = $cols['F'] ?? '';

                            if (empty($nombres) && empty($apellidos) && empty($cedula)) {
                                continue;
                            }

                            $cedulaVal = (!empty($cedula) && preg_match('/^[0-9]{5,12}$/', $cedula)) ? $cedula : null;
                            $fechaNac = resolveBirthDatePhp($diaNac, $mesNac, $anoNac);

                            $visitantes[] = [
                                'cedula' => $cedulaVal,
                                'nombres' => substr($nombres, 0, 100),
                                'apellidos' => substr($apellidos, 0, 100),
                                'fecha_nacimiento' => !empty($fechaNac) ? $fechaNac : null
                            ];
                        }
                    }
                }
                $zip->close();
            } else {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "No se pudo leer el archivo XLSX cargado."
                ]);
                return;
            }
        }
    } else {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "No se subió ningún archivo ni se enviaron datos de visitantes para procesar."
        ]);
        return;
    }

    if (empty($visitantes)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "No se encontraron filas de visitantes válidas para importar."
        ]);
        return;
    }

    try {
        $visitanteModel = new Visitante();
        $visitanteModel->setCitaId($cita_id);
        if ($visitanteModel->importarVisitantesCita($visitantes)) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Se han importado " . count($visitantes) . " visitantes exitosamente a la cita."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Ocurrió un error al procesar el registro de los visitantes."
            ]);
        }
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno al importar visitantes: " . $e->getMessage()
        ]);
    }
}

/**
 * Utilidades para resolución y validación flexible de fechas de nacimiento en PHP
 */
function parseMonthValPhp($val) {
    if ($val === null || $val === '') return 0;
    $s = mb_strtolower(trim((string)$val));
    $meses = [
        'enero' => 1, 'ene' => 1, 'january' => 1, 'jan' => 1,
        'febrero' => 2, 'feb' => 2, 'febr' => 2, 'february' => 2,
        'marzo' => 3, 'mar' => 3, 'march' => 3,
        'abril' => 4, 'abr' => 4, 'april' => 4, 'apr' => 4,
        'mayo' => 5, 'may' => 5,
        'junio' => 6, 'jun' => 6, 'june' => 6,
        'julio' => 7, 'jul' => 7, 'july' => 7,
        'agosto' => 8, 'ago' => 8, 'august' => 8, 'aug' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'sep' => 9, 'sept' => 9, 'september' => 9,
        'octubre' => 10, 'oct' => 10, 'october' => 10,
        'noviembre' => 11, 'nov' => 11, 'november' => 11,
        'diciembre' => 12, 'dic' => 12, 'december' => 12, 'dec' => 12
    ];
    if (isset($meses[$s])) return $meses[$s];
    $num = (int)preg_replace('/[^0-9]/', '', $s);
    if ($num >= 1 && $num <= 31) return $num;
    return 0;
}

function parseYearValPhp($val) {
    if ($val === null || $val === '') return 0;
    $clean = preg_replace('/[^0-9]/', '', trim((string)$val));
    if (empty($clean)) return 0;
    $num = (int)$clean;

    if ($num < 100) {
        $num = $num <= 30 ? 2000 + $num : 1900 + $num;
    } elseif ($num > 2100) {
        $str = (string)$num;
        if (str_starts_with($str, '19') || str_starts_with($str, '20')) {
            $num = (int)substr($str, 0, 4);
        }
    }
    return ($num >= 1900 && $num <= 2100) ? $num : 0;
}

function parseDayValPhp($val) {
    if ($val === null || $val === '') return 0;
    $num = (int)preg_replace('/[^0-9]/', '', trim((string)$val));
    return ($num >= 1 && $num <= 31) ? $num : 0;
}

function resolveBirthDatePhp($valCol3, $valCol4, $valCol5) {
    $s3 = trim((string)$valCol3);
    $s4 = trim((string)$valCol4);
    $s5 = trim((string)$valCol5);

    // 1. Columnas separadas Día, Mes, Año
    if ($s3 !== '' && ($s4 !== '' || $s5 !== '')) {
        $d = parseDayValPhp($s3);
        $m = parseMonthValPhp($s4);
        $y = parseYearValPhp($s5);

        if ($m > 12 && $m <= 31 && $d >= 1 && $d <= 12) {
            $temp = $d;
            $d = $m;
            $m = $temp;
        }

        if ($d >= 1 && $d <= 31 && $m >= 1 && $m <= 12 && $y >= 1900 && $y <= 2100 && checkdate($m, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
        return '';
    }

    // 2. Fecha combinada en una sola celda
    $testStr = !empty($s3) ? $s3 : (!empty($s5) ? $s5 : $s4);
    if (empty($testStr)) return '';

    if (preg_match('/^(\d{4,5})-(\d{1,2})-(\d{1,2})$/', $testStr, $mIso)) {
        $y = parseYearValPhp($mIso[1]);
        $m = (int)$mIso[2];
        $d = (int)$mIso[3];
        if ($m > 12 && $m <= 31 && $d >= 1 && $d <= 12) {
            $temp = $d; $d = $m; $m = $temp;
        }
        if ($y && checkdate($m, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
    }

    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4,5})$/', $testStr, $mSlash)) {
        $d = parseDayValPhp($mSlash[1]);
        $m = parseMonthValPhp($mSlash[2]);
        $y = parseYearValPhp($mSlash[3]);
        if ($m > 12 && $m <= 31 && $d >= 1 && $d <= 12) {
            $temp = $d; $d = $m; $m = $temp;
        }
        if ($y && checkdate($m, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
    }

    if (preg_match('/^(\d{4,5})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $testStr, $mSlashY)) {
        $y = parseYearValPhp($mSlashY[1]);
        $m = parseMonthValPhp($mSlashY[2]);
        $d = parseDayValPhp($mSlashY[3]);
        if ($m > 12 && $m <= 31 && $d >= 1 && $d <= 12) {
            $temp = $d; $d = $m; $m = $temp;
        }
        if ($y && checkdate($m, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }
    }

    return '';
}

/**
 * Obtiene el listado de visitantes de una cita específica
 */
function listarVisitantesAction() {
    $cita_id = isset($_GET['cita_id']) ? filter_var($_GET['cita_id'], FILTER_VALIDATE_INT) : 0;
    if ($cita_id <= 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de la cita es requerido y debe ser un entero válido."
        ]);
        exit;
    }
    
    try {
        $visitanteModel = new Visitante();
        $visitanteModel->setCitaId($cita_id);
        $visitantes = $visitanteModel->obtenerPorCitaId();
        echo json_encode([
            "status" => "success",
            "data" => $visitantes
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener visitantes de la cita: " . $e->getMessage()
        ]);
    }
    exit;
}

/**
 * Retorna la información de calendario de visitas y estadísticas de afluencia para el dashboard
 */
function obtenerDatosDashboardAction() {
    try {
        $citaModel = new Cita();
        $calendario = $citaModel->obtenerCronogramaCalendario();
        $estadisticas = $citaModel->obtenerEstadisticasAfluencia();
        
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "calendario" => $calendario,
            "estadisticas" => $estadisticas
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al cargar la información del panel del dashboard: " . $e->getMessage()
        ]);
    }
}

/**
 * Retorna las citas programadas para el día de hoy, según el rol y sesión del usuario
 */
function citasHoyAction() {
    try {
        $personal = $_SESSION['personal'];
        $rol = $personal['rol'];
        $personalId = (int)$personal['id'];
        $tienePermisoCitas = usuarioTienePermisoCitas();

        $citaModel = new Cita();
        $citaModel->setFiltroRol($rol);
        $citaModel->setFiltroPersonalId($personalId);
        $citaModel->setTienePermisoCitas($tienePermisoCitas);
        $citas = $citaModel->obtenerCitasHoy();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $citas
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener las citas de hoy: " . $e->getMessage()
        ]);
    }
}

/**
 * Obtiene los detalles de una cita específica por su ID
 */
function obtenerCitaAction() {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de cita inválido o no suministrado."
        ]);
        return;
    }

    $citaModel = new Cita();
    $citaModel->setId($id);
    $cita = $citaModel->obtenerPorId();

    if ($cita) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $cita
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "La cita no existe en el sistema."
        ]);
    }
}

/**
 * Modifica una cita existente
 */
function editarCitaAction() {
    if (!usuarioTienePermisoCitas()) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Acceso no autorizado para editar citas."
        ]);
        return;
    }

    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de la cita es requerido para editar."
        ]);
        return;
    }

    $institucion_id = (!empty($_POST['institucion_id']) && filter_var($_POST['institucion_id'], FILTER_VALIDATE_INT)) ? (int)$_POST['institucion_id'] : null;
    $sede_id = (!empty($_POST['sede_id']) && filter_var($_POST['sede_id'], FILTER_VALIDATE_INT)) ? (int)$_POST['sede_id'] : null;
    $recorrido_id = (isset($_POST['recorrido_id']) && trim($_POST['recorrido_id']) !== '') ? trim($_POST['recorrido_id']) : null;
    $fecha_visita = isset($_POST['fecha_visita']) ? trim($_POST['fecha_visita']) : '';
    $hora_inicio = isset($_POST['hora_inicio']) ? trim($_POST['hora_inicio']) : '';
    $tipo = isset($_POST['tipo']) ? trim(strtolower($_POST['tipo'])) : '';
    $metodo_pago = isset($_POST['metodo_pago']) ? trim($_POST['metodo_pago']) : '';
    $pago_estado = isset($_POST['pago_estado']) ? trim($_POST['pago_estado']) : 'pendiente';
    $estatus = isset($_POST['estatus']) ? trim($_POST['estatus']) : 'pendiente';

    $tiposValidos = ['campamento nocturno', 'corporativo', 'evento', 'institucional', 'otro', 'particular'];

    if (empty($fecha_visita) || empty($hora_inicio) || empty($tipo) || empty($metodo_pago) || empty($estatus)) {
        $missingField = empty($fecha_visita) ? "fecha_visita" : (empty($hora_inicio) ? "hora_inicio" : (empty($tipo) ? "tipo" : (empty($metodo_pago) ? "metodo_pago" : "estatus")));
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los campos fecha de visita, hora de inicio, tipo de cita, método de pago y estado de la cita son requeridos.",
            "field" => $missingField
        ]);
        return;
    }

    if (strlen($tipo) > 40 || !in_array($tipo, $tiposValidos, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El tipo de cita seleccionado no es válido.",
            "field" => "tipo"
        ]);
        return;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_visita)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La fecha de la visita debe tener el formato AAAA-MM-DD.",
            "field" => "fecha_visita"
        ]);
        return;
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_inicio)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La hora de inicio de la visita no es válida.",
            "field" => "hora_inicio"
        ]);
        return;
    }

    if (strlen($metodo_pago) > 50) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El método de pago no es válido.",
            "field" => "metodo_pago"
        ]);
        return;
    }

    if (!in_array($pago_estado, ['pendiente', 'pagado'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El estado de pago no es válido.",
            "field" => "pago_estado"
        ]);
        return;
    }

    if ($recorrido_id !== null) {
        if (!filter_var($recorrido_id, FILTER_VALIDATE_INT)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "El recorrido seleccionado no es válido.",
                "field" => "recorrido_id"
            ]);
            return;
        }
        $recorrido_id = (int)$recorrido_id;
    }

    if (!in_array($estatus, ['pendiente', 'aprobado', 'completado', 'cancelado'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El estado de la cita proporcionado no es válido.",
            "field" => "estatus"
        ]);
        return;
    }

    try {
        $citaModel = new Cita();
        $citaModel->setId($id);
        $citaActual = $citaModel->obtenerPorId();
        if (!$citaActual) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "La cita seleccionada no existe en el sistema."
            ]);
            return;
        }

        $finalInstId = $institucion_id ? $institucion_id : $citaActual['institucion_id'];
        $finalSedeId = $sede_id ? $sede_id : ($citaActual['sede_id'] ?? null);

        $citaModel->setInstitucionId($finalInstId);
        $citaModel->setSedeId($finalSedeId);
        $citaModel->setRecorridoId($recorrido_id);
        $citaModel->setFechaVisita($fecha_visita);
        $citaModel->setHoraInicio($hora_inicio);
        $citaModel->setTipo($tipo);
        $citaModel->setMetodoPago($metodo_pago);
        $citaModel->setPagoEstado($pago_estado);
        $citaModel->setEstatus($estatus);

        if ($citaModel->actualizar()) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "La cita ha sido modificada con éxito."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo actualizar la cita en la base de datos."
            ]);
        }
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno al actualizar la cita: " . $e->getMessage()
        ]);
    }
}

/**
 * Vincula una lista de visitantes existentes a una cita
 */
function vincularVisitantesAction() {
    $cita_id = isset($_POST['cita_id']) ? filter_var($_POST['cita_id'], FILTER_VALIDATE_INT) : null;
    $visitante_ids = isset($_POST['visitante_ids']) ? $_POST['visitante_ids'] : [];

    if (!$cita_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de la cita es requerido."
        ]);
        return;
    }

    if (!is_array($visitante_ids) || empty($visitante_ids)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe seleccionar al menos un visitante para vincular."
        ]);
        return;
    }

    try {
        $citaModel = new Cita();
        $citaModel->setId($cita_id);
        $vinculados = $citaModel->vincularVisitantes($visitante_ids);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Se han vinculado {$vinculados} visitantes a la cita exitosamente."
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno al vincular visitantes: " . $e->getMessage()
        ]);
    }
}

/**
 * Remueve un visitante de la asistencia a una cita
 */
function desvincularVisitanteAction() {
    $cita_id = isset($_POST['cita_id']) ? filter_var($_POST['cita_id'], FILTER_VALIDATE_INT) : null;
    $visitante_id = isset($_POST['visitante_id']) ? filter_var($_POST['visitante_id'], FILTER_VALIDATE_INT) : null;

    if (!$cita_id || !$visitante_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de la cita y del visitante son requeridos."
        ]);
        return;
    }

    try {
        $citaModel = new Cita();
        $citaModel->setId($cita_id);
        if ($citaModel->desvincularVisitante($visitante_id)) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Visitante removido de la asistencia con éxito."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo remover al visitante de la asistencia."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno al remover visitante: " . $e->getMessage()
        ]);
    }
}

/**
 * Lista todos los guías de campo activos en el sistema
 */
function listarGuiasAction() {
    try {
        $citaId = isset($_GET['cita_id']) ? filter_var($_GET['cita_id'], FILTER_VALIDATE_INT) : null;
        $fechaVisita = null;
        if ($citaId) {
            $citaModel = new Cita();
            $citaModel->setId($citaId);
            $fechaVisita = $citaModel->obtenerFechaVisita();
        }

        $personalModel = new Personal();
        $guias = $personalModel->listarGuias();

        // Si se especificó cita, calcular disponibilidad para esa fecha
        $citaCheck = new Cita();
        foreach ($guias as &$g) {
            if ($fechaVisita) {
                $citaCheck->setPersonalId((int)$g['id']);
                $citaCheck->setFechaVisita((string)$fechaVisita);
                $citaCheck->setCitaIdExcluir((int)$citaId);
                $tieneActiva = $citaCheck->guiaTieneCitaActivaEnFecha();
                $g['disponible'] = !$tieneActiva;
            } else {
                $g['disponible'] = true;
            }
        }
        unset($g);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $guias
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener el listado de guías: " . $e->getMessage()
        ]);
    }
}

/**
 * Asigna un guía a una cita específica
 */
function asignarGuiaAction() {
    $cita_id = isset($_POST['cita_id']) ? filter_var($_POST['cita_id'], FILTER_VALIDATE_INT) : null;
    $personal_id = isset($_POST['personal_id']) ? filter_var($_POST['personal_id'], FILTER_VALIDATE_INT) : null;
    $rol_guia = isset($_POST['rol_guia']) ? trim($_POST['rol_guia']) : 'principal';

    if (!$cita_id || !$personal_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de cita y el ID de personal son obligatorios."
        ]);
        return;
    }

    // Regla de Negocio: No permitir asignar a un guía dos veces en el mismo día si tiene una cita ya asignada;
    // sólo se podrá reasignar cuando ya termine la cita que le fue otorgada (estatus 'completado').
    $citaModel = new Cita();
    $citaModel->setId($cita_id);
    $fechaVisita = $citaModel->obtenerFechaVisita();

    if ($fechaVisita) {
        $citaCheck = new Cita();
        $citaCheck->setPersonalId($personal_id);
        $citaCheck->setFechaVisita($fechaVisita);
        $citaCheck->setCitaIdExcluir($cita_id);
        if ($citaCheck->guiaTieneCitaActivaEnFecha()) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "No se puede asignar este guía: ya tiene una visita guiada asignada para este mismo día que aún no ha finalizado. Solo podrá reasignarse una vez que complete la cita otorgada."
            ]);
            return;
        }
    }

    $citaModel->setPersonalId($personal_id);
    $citaModel->setRolGuia($rol_guia);
    $success = $citaModel->asignarGuia();

    if ($success) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Guía asignado exitosamente."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "No se pudo asignar el guía a la cita."
        ]);
    }
}

/**
 * Elimina una asignación de guía de una cita
 */
function desasignarGuiaAction() {
    $cita_id = isset($_POST['cita_id']) ? filter_var($_POST['cita_id'], FILTER_VALIDATE_INT) : null;
    $personal_id = isset($_POST['personal_id']) ? filter_var($_POST['personal_id'], FILTER_VALIDATE_INT) : null;

    if (!$cita_id || !$personal_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de cita y el ID de personal son obligatorios."
        ]);
        return;
    }

    $citaModel = new Cita();
    $citaModel->setId($cita_id);
    $citaModel->setPersonalId($personal_id);
    $success = $citaModel->desasignarGuia();

    if ($success) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Guía desasignado exitosamente."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "No se pudo desasignar el guía de la cita."
        ]);
    }
}

/**
 * Lista los guías asignados a una cita específica
 */
function guiasCitaAction() {
    $cita_id = isset($_GET['cita_id']) ? filter_var($_GET['cita_id'], FILTER_VALIDATE_INT) : null;

    if (!$cita_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de la cita es obligatorio."
        ]);
        return;
    }

    try {
        $citaModel = new Cita();
        $citaModel->setId($cita_id);
        $asignados = $citaModel->obtenerGuiasAsignados();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $asignados
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener los guías de la cita: " . $e->getMessage()
        ]);
    }
}

/**
 * Inicia el recorrido de una cita (cambia estatus a 'en_recorrido' y fija hora de inicio)
 */
function empezarRecorridoAction() {
    $cita_id = isset($_POST['cita_id']) ? filter_var($_POST['cita_id'], FILTER_VALIDATE_INT) : null;

    if (!$cita_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de cita no válido o ausente."
        ]);
        return;
    }

    $personal = $_SESSION['personal'];
    $rolNorm = strtolower(trim((string)$personal['rol']));
    $personalId = (int)$personal['id'];

    $citaModel = new Cita();
    $citaModel->setId($cita_id);
    $citaModel->setPersonalId($personalId);

    // Si es guía, comprobar que esté asignado a la cita
    if ($rolNorm === 'guia' || $rolNorm === 'guía') {
        if (!$citaModel->esGuiaAsignado()) {
            http_response_code(403);
            echo json_encode([
                "status" => "error",
                "message" => "No tienes permisos para iniciar este recorrido ya que no eres un guía asignado a esta cita."
            ]);
            return;
        }
    }

    $success = $citaModel->empezarRecorrido();

    if ($success) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "¡El recorrido ha comenzado exitosamente! Duración estimada: 2 horas."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "No se pudo iniciar el recorrido. Intente nuevamente."
        ]);
    }
}

/**
 * Finaliza manualmente el recorrido de una cita (cambia estatus a 'completado')
 */
function terminarRecorridoAction() {
    $cita_id = isset($_POST['cita_id']) ? filter_var($_POST['cita_id'], FILTER_VALIDATE_INT) : null;

    if (!$cita_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de cita no válido o ausente."
        ]);
        return;
    }

    $personal = $_SESSION['personal'];
    $rolNorm = strtolower(trim((string)$personal['rol']));
    $personalId = (int)$personal['id'];

    $citaModel = new Cita();
    $citaModel->setId($cita_id);
    $citaModel->setPersonalId($personalId);

    // Si es guía, comprobar que esté asignado a la cita
    if ($rolNorm === 'guia' || $rolNorm === 'guía') {
        if (!$citaModel->esGuiaAsignado()) {
            http_response_code(403);
            echo json_encode([
                "status" => "error",
                "message" => "No tienes permisos para finalizar este recorrido ya que no eres un guía asignado a esta cita."
            ]);
            return;
        }
    }

    $success = $citaModel->terminarRecorrido();

    if ($success) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "El recorrido ha sido finalizado con éxito."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "No se pudo finalizar el recorrido. Intente nuevamente."
        ]);
    }
}

/**
 * Asigna o modifica el recorrido asignado a una cita.
 * Permitido tanto para el Administrador como para el Guía asignado a dicha cita.
 */
function asignarRecorridoAction() {
    $cita_id = isset($_POST['cita_id']) ? filter_var($_POST['cita_id'], FILTER_VALIDATE_INT) : null;
    $recorrido_id = (isset($_POST['recorrido_id']) && $_POST['recorrido_id'] !== '') 
        ? filter_var($_POST['recorrido_id'], FILTER_VALIDATE_INT) 
        : null;

    if (!$cita_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de cita es obligatorio."
        ]);
        return;
    }

    $personal = $_SESSION['personal'] ?? null;
    if (!$personal) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Sesión no válida."
        ]);
        return;
    }

    $rolNorm = strtolower(trim((string)$personal['rol']));
    $personalId = (int)$personal['id'];

    $citaModel = new Cita();
    $citaModel->setId($cita_id);
    $citaModel->setPersonalId($personalId);

    // Permisos: SuperAdmin, Admin o Guía asignado a esta cita
    $esAdmin = ($rolNorm === 'superadmin' || $rolNorm === 'admin');
    if (!$esAdmin) {
        if ($rolNorm === 'guia' || $rolNorm === 'guía') {
            if (!$citaModel->esGuiaAsignado()) {
                http_response_code(403);
                echo json_encode([
                    "status" => "error",
                    "message" => "No tienes permisos para asignar el recorrido a esta cita ya que no eres un guía asignado a ella."
                ]);
                return;
            }
        } else {
            http_response_code(403);
            echo json_encode([
                "status" => "error",
                "message" => "Acceso no autorizado para asignar recorridos."
            ]);
            return;
        }
    }

    // Validar existencia del recorrido si se suministró un ID
    $nombreRecorrido = 'Ninguno / Libre';
    if ($recorrido_id !== null && $recorrido_id > 0) {
        $recorridoModel = new Recorrido();
        $recorridoModel->setId($recorrido_id);
        $nombreRecorrido = $recorridoModel->obtenerNombre();
        if (!$nombreRecorrido) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "El recorrido seleccionado no existe en el sistema."
            ]);
            return;
        }
    } else {
        $recorrido_id = null;
    }

    $citaModel->setRecorridoId($recorrido_id);
    $success = $citaModel->actualizarRecorrido();

    if ($success) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Recorrido asignado exitosamente.",
            "recorrido_id" => $recorrido_id,
            "recorrido_nombre" => $nombreRecorrido
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "No se pudo actualizar el recorrido de la cita."
        ]);
    }
}
