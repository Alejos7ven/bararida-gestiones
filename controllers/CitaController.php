<?php
/**
 * Controlador de Citas (CitaController)
 * Gestiona el agendamiento de citas, registro de instituciones asociadas y la importación/exportación de visitantes.
 * Acceso restringido exclusivamente a personal autenticado.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar encabezados
// Para la acción de descargar plantilla, el encabezado se cambiará a texto/csv, de lo contrario será application/json
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

// Incluir dependencias
require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../config/ExcelHelper.php";
require_once __DIR__ . "/../models/Institucion.php";
require_once __DIR__ . "/../models/Sede.php";
require_once __DIR__ . "/../models/Cita.php";
require_once __DIR__ . "/../models/Visitante.php";

// Inicializar base de datos
$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    if ($action === 'descargar_plantilla') {
        die("Error de conexión a la base de datos.");
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "No se pudo establecer conexión con la base de datos."
        ]);
    }
    exit;
}

// Procesar según método HTTP y acción
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'crear':
            crearCitaAction($db);
            break;
        case 'editar':
            editarCitaAction($db);
            break;
        case 'eliminar':
            eliminarCitaAction($db);
            break;
        case 'importar_visitantes':
            importarVisitantesAction($db);
            break;
        case 'vincular_visitantes':
            vincularVisitantesAction($db);
            break;
        case 'desvincular_visitante':
            desvincularVisitanteAction($db);
            break;
        case 'asignar_guia':
            asignarGuiaAction($db);
            break;
        case 'desasignar_guia':
            desasignarGuiaAction($db);
            break;
        case 'empezar_recorrido':
            empezarRecorridoAction($db);
            break;
        case 'terminar_recorrido':
            terminarRecorridoAction($db);
            break;
        case 'asignar_recorrido':
            asignarRecorridoAction($db);
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
            listarCitasAction($db);
            break;
        case 'obtener':
            obtenerCitaAction($db);
            break;
        case 'comprobar_institucion':
            comprobarInstitucionAction($db);
            break;
        case 'descargar_plantilla':
            descargarPlantillaAction($db);
            break;
        case 'listar_visitantes':
            listarVisitantesAction($db);
            break;
        case 'obtener_datos_dashboard':
            obtenerDatosDashboardAction($db);
            break;
        case 'citas_hoy':
            citasHoyAction($db);
            break;
        case 'listar_guias':
            listarGuiasAction($db);
            break;
        case 'guias_cita':
            guiasCitaAction($db);
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
 * Comprueba si la institución existe por su RIF
 */
function comprobarInstitucionAction($db) {
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

    $institucionData = Institucion::buscarPorDocumento($db, $tipo_documento, $documento);

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
 * Lista todas las citas
 */
function listarCitasAction($db) {
    $personal = $_SESSION['personal'] ?? null;
    $rolNorm = strtolower(trim((string)($personal['rol'] ?? '')));
    if ($rolNorm === 'guia' || $rolNorm === 'guía') {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Acceso restringido. Los guías gestionan sus visitas asignadas desde el Dashboard."
        ]);
        return;
    }

    try {
        $citaModel = new Cita($db);
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
function crearCitaAction($db) {
    $personal = $_SESSION['personal'] ?? null;
    $rolNorm = strtolower(trim((string)($personal['rol'] ?? '')));
    if ($rolNorm === 'guia' || $rolNorm === 'guía') {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Acceso no autorizado para crear citas."
        ]);
        return;
    }

    $institucion_id = isset($_POST['institucion_id']) ? filter_var($_POST['institucion_id'], FILTER_VALIDATE_INT) : null;
    $sede_id = isset($_POST['sede_id']) ? filter_var($_POST['sede_id'], FILTER_VALIDATE_INT) : null;
    
    // Datos de la Cita
    $recorrido_id = isset($_POST['recorrido_id']) ? $_POST['recorrido_id'] : '';
    $fecha_visita = isset($_POST['fecha_visita']) ? trim($_POST['fecha_visita']) : '';
    $hora_inicio = isset($_POST['hora_inicio']) ? trim($_POST['hora_inicio']) : '';
    $metodo_pago = isset($_POST['metodo_pago']) ? trim($_POST['metodo_pago']) : '';
    $pago_estado = isset($_POST['pago_estado']) ? trim($_POST['pago_estado']) : 'pendiente';

    if (empty($fecha_visita) || empty($hora_inicio) || empty($metodo_pago)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los campos fecha de visita, hora de inicio y método de pago son requeridos.",
            "field" => empty($fecha_visita) ? "fecha_visita" : (empty($hora_inicio) ? "hora_inicio" : "metodo_pago")
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

    if (!empty($recorrido_id) && !filter_var($recorrido_id, FILTER_VALIDATE_INT)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El recorrido seleccionado no es válido.",
            "field" => "recorrido_id"
        ]);
        return;
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

            // Fallback: si se enviaron ciudad_id y parroquia_id planos
            if (empty($sedesRaw) && !empty($_POST['ciudad_id']) && !empty($_POST['parroquia_id'])) {
                $sedesRaw[] = [
                    'ciudad_id' => $_POST['ciudad_id'],
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
                    $cId = filter_var($s['ciudad_id'] ?? 0, FILTER_VALIDATE_INT);
                    $pId = filter_var($s['parroquia_id'] ?? 0, FILTER_VALIDATE_INT);
                    $det = strip_tags(trim($s['detalles'] ?? ''));
                    if ($cId && $pId) {
                        $sedesValidadas[] = [
                            'ciudad_id' => $cId,
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

            $instModel = new Institucion($db);
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
                $sedesCreadas = Sede::listarPorInstitucion($db, $institucion_id);
                if (!empty($sedesCreadas)) {
                    $sede_id = (int)$sedesCreadas[0]['id'];
                }
            } else {
                throw new Exception("No se pudo registrar la institución.");
            }
        } else {
            // Institución existente: si el usuario solicita registrar una nueva sede al vuelo
            $crearNuevaSede = isset($_POST['crear_nueva_sede']) && $_POST['crear_nueva_sede'] == '1';
            $nuevaCiudadId = isset($_POST['nueva_sede_ciudad_id']) ? filter_var($_POST['nueva_sede_ciudad_id'], FILTER_VALIDATE_INT) : null;
            $nuevaParroquiaId = isset($_POST['nueva_sede_parroquia_id']) ? filter_var($_POST['nueva_sede_parroquia_id'], FILTER_VALIDATE_INT) : null;
            $nuevaDetalles = isset($_POST['nueva_sede_detalles']) ? strip_tags(trim($_POST['nueva_sede_detalles'])) : '';

            if ($crearNuevaSede && $nuevaCiudadId && $nuevaParroquiaId) {
                $sedeModel = new Sede($db);
                $sedeModel->setInstitucionId($institucion_id);
                $sedeModel->setCiudadId($nuevaCiudadId);
                $sedeModel->setParroquiaId($nuevaParroquiaId);
                $sedeModel->setDetalles(substr($nuevaDetalles, 0, 150));
                if ($sedeModel->crear()) {
                    $sede_id = $sedeModel->getId();
                }
            }
        }

        // 2. Registrar la cita
        $citaModel = new Cita($db);
        $citaModel->setInstitucionId($institucion_id);
        $citaModel->setSedeId($sede_id);
        $citaModel->setRecorridoId($recorrido_id);
        $citaModel->setFechaVisita($fecha_visita);
        $citaModel->setHoraInicio($hora_inicio);
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
function eliminarCitaAction($db) {
    $personal = $_SESSION['personal'] ?? null;
    $rolNorm = strtolower(trim((string)($personal['rol'] ?? '')));
    if ($rolNorm === 'guia' || $rolNorm === 'guía') {
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
        $citaModel = new Cita($db);
        if ($citaModel->eliminar($id)) {
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
 * Genera y fuerza la descarga de la plantilla CSV de visitantes compatible con Excel
 */
function descargarPlantillaAction($db) {
    $cita_id = isset($_GET['cita_id']) ? filter_var($_GET['cita_id'], FILTER_VALIDATE_INT) : null;

    if (!$cita_id) {
        die("ID de cita inválido.");
    }

    $cita = Cita::obtenerPorId($db, $cita_id);
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
function importarVisitantesAction($db) {
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
    $visitantesJson = isset($_POST['visitantes_json']) ? trim($_POST['visitantes_json']) : '';

    if (!empty($visitantesJson)) {
        $rawVisitantes = json_decode($visitantesJson, true);
        if (!is_array($rawVisitantes)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "El listado de visitantes tiene un formato JSON inválido."
            ]);
            return;
        }

        $validatedList = [];
        $rowNum = 0;
        foreach ($rawVisitantes as $v) {
            $rowNum++;
            $nom = trim($v['nombres'] ?? '');
            $ape = trim($v['apellidos'] ?? '');
            $ced = trim($v['cedula'] ?? '');
            $fNac = trim($v['fecha_nacimiento'] ?? '');

            // Validaciones estrictas en el Controller para cada visitante
            if (empty($nom) || mb_strlen($nom) < 2) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: El nombre del visitante es obligatorio y debe tener al menos 2 caracteres."
                ]);
                return;
            }

            if (preg_match('/\d/', $nom)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: El nombre '{$nom}' no puede contener números."
                ]);
                return;
            }

            if (!preg_match('/^[\p{L}\s\.\'\-]+$/u', $nom)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: El nombre '{$nom}' contiene caracteres no permitidos (solo letras)."
                ]);
                return;
            }

            if (empty($ape) || mb_strlen($ape) < 2) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: El apellido del visitante es obligatorio y debe tener al menos 2 caracteres."
                ]);
                return;
            }

            if (preg_match('/\d/', $ape)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: El apellido '{$ape}' no puede contener números."
                ]);
                return;
            }

            if (!preg_match('/^[\p{L}\s\.\'\-]+$/u', $ape)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: El apellido '{$ape}' contiene caracteres no permitidos (solo letras)."
                ]);
                return;
            }

            if (empty($fNac)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: La fecha de nacimiento del visitante '{$nom}' es obligatoria."
                ]);
                return;
            }

            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fNac, $matches)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: La fecha de nacimiento del visitante '{$nom}' no tiene un formato de fecha válido (AAAA-MM-DD)."
                ]);
                return;
            }

            $y = (int)$matches[1];
            $m = (int)$matches[2];
            $d = (int)$matches[3];

            if (!checkdate($m, $d, $y)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: La fecha de nacimiento '{$fNac}' para '{$nom}' no es una fecha válida en el calendario."
                ]);
                return;
            }

            $hoy = date('Y-m-d');
            if ($fNac > $hoy) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowNum}: La fecha de nacimiento del visitante '{$nom}' ({$fNac}) no puede ser una fecha futura (máximo hoy)."
                ]);
                return;
            }

            if (!empty($ced)) {
                if (!ctype_digit($ced)) {
                    http_response_code(400);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Fila #{$rowNum}: La cédula '{$ced}' del visitante '{$nom}' contiene caracteres no permitidos (solo números)."
                    ]);
                    return;
                }
                if (strlen($ced) < 5 || strlen($ced) > 12) {
                    http_response_code(400);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Fila #{$rowNum}: La cédula '{$ced}' del visitante '{$nom}' debe contener entre 5 y 12 dígitos numéricos."
                    ]);
                    return;
                }
                $cedulaFinal = $ced;
            } else {
                $cedulaFinal = null;
            }

            $validatedList[] = [
                'cedula' => $cedulaFinal,
                'nombres' => strip_tags($nom),
                'apellidos' => strip_tags($ape),
                'fecha_nacimiento' => $fNac
            ];
        }
        $visitantes = $validatedList;
    } else {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Debe subir un archivo CSV válido o enviar un listado en formato JSON."
            ]);
            return;
        }

        $fileTmp = $_FILES['file']['tmp_name'];
        $handle = fopen($fileTmp, "r");

        if (!$handle) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo leer el archivo cargado."
            ]);
            return;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 1000, ";");
        $delimiter = ";";

        if ($header === false || count($header) < 2) {
            rewind($handle);
            if ($bom === "\xEF\xBB\xBF") {
                fread($handle, 3);
            }
            $header = fgetcsv($handle, 1000, ",");
            $delimiter = ",";
        }

        $rowIndex = 1;

        while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            $rowIndex++;
            if (empty($data) || count($data) < 3) continue;
            
            $ced = trim($data[0] ?? '');
            $nom = trim($data[1] ?? '');
            $ape = trim($data[2] ?? '');

            if (mb_strtolower($nom) === 'nombres' || mb_strtolower($nom) === 'nombre') {
                continue;
            }

            if ($ced === '12345678' && $nom === 'Pedro' && $ape === 'Pérez') {
                continue;
            }

            if (empty($nom) && empty($ape)) {
                continue;
            }

            $col3 = trim($data[3] ?? '');
            $col4 = trim($data[4] ?? '');
            $col5 = trim($data[5] ?? '');
            $fNac = resolveBirthDatePhp($col3, $col4, $col5);

            // Validaciones en el Controller
            if (empty($nom) || mb_strlen($nom) < 2) {
                fclose($handle);
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowIndex}: El nombre del visitante es obligatorio y debe tener al menos 2 caracteres."
                ]);
                return;
            }

            if (preg_match('/\d/', $nom)) {
                fclose($handle);
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowIndex}: El nombre '{$nom}' no puede contener números."
                ]);
                return;
            }

            if (!preg_match('/^[\p{L}\s\.\'\-]+$/u', $nom)) {
                fclose($handle);
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowIndex}: El nombre '{$nom}' contiene caracteres no permitidos (solo letras)."
                ]);
                return;
            }

            if (empty($ape) || mb_strlen($ape) < 2) {
                fclose($handle);
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowIndex}: El apellido del visitante es obligatorio y debe tener al menos 2 caracteres."
                ]);
                return;
            }

            if (preg_match('/\d/', $ape)) {
                fclose($handle);
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowIndex}: El apellido '{$ape}' no puede contener números."
                ]);
                return;
            }

            if (!preg_match('/^[\p{L}\s\.\'\-]+$/u', $ape)) {
                fclose($handle);
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowIndex}: El apellido '{$ape}' contiene caracteres no permitidos (solo letras)."
                ]);
                return;
            }

            if (empty($fNac)) {
                fclose($handle);
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowIndex}: La fecha de nacimiento del visitante '{$nom}' no es válida o está incompleta."
                ]);
                return;
            }

            $hoy = date('Y-m-d');
            if ($fNac > $hoy) {
                fclose($handle);
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Fila #{$rowIndex}: La fecha de nacimiento del visitante '{$nom}' ({$fNac}) no puede ser una fecha futura (máximo hoy)."
                ]);
                return;
            }

            if (!empty($ced)) {
                if (!ctype_digit($ced)) {
                    fclose($handle);
                    http_response_code(400);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Fila #{$rowIndex}: La cédula '{$ced}' del visitante '{$nom}' contiene caracteres no permitidos (solo números)."
                    ]);
                    return;
                }
                if (strlen($ced) < 5 || strlen($ced) > 12) {
                    fclose($handle);
                    http_response_code(400);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Fila #{$rowIndex}: La cédula '{$ced}' del visitante '{$nom}' debe contener entre 5 y 12 dígitos numéricos."
                    ]);
                    return;
                }
                $cedulaFinal = $ced;
            } else {
                $cedulaFinal = null;
            }

            $visitantes[] = [
                'cedula' => $cedulaFinal,
                'nombres' => strip_tags($nom),
                'apellidos' => strip_tags($ape),
                'fecha_nacimiento' => $fNac
            ];
        }
        fclose($handle);
    }

    if (empty($visitantes)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El archivo cargado no contiene filas de visitantes válidas para importar."
        ]);
        return;
    }

    try {
        if (Visitante::importarVisitantesCita($db, $cita_id, $visitantes)) {
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

    // 1. Columnas separadas Día, Mes, Año (cuando hay al menos 2 columnas o las 3 con datos)
    if ($s3 !== '' && ($s4 !== '' || $s5 !== '')) {
        $d = parseDayValPhp($s3);
        $m = parseMonthValPhp($s4);
        $y = parseYearValPhp($s5);

        // Si día está en rango de mes (1-12) y mes está en rango de día (>12 y <=31), se detecta inversión
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
function listarVisitantesAction($db) {
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
        $visitantes = Visitante::obtenerPorCitaId($db, $cita_id);
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
function obtenerDatosDashboardAction($db) {
    try {
        $calendario = Cita::obtenerCronogramaCalendario($db);
        $estadisticas = Cita::obtenerEstadisticasAfluencia($db);
        
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
function citasHoyAction($db) {
    try {
        $personal = $_SESSION['personal'];
        $rol = $personal['rol'];
        $personalId = (int)$personal['id'];

        $citas = Cita::obtenerCitasHoy($db, $rol, $personalId);

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
function obtenerCitaAction($db) {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de cita inválido o no suministrado."
        ]);
        return;
    }

    $cita = Cita::obtenerPorId($db, $id);

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
function editarCitaAction($db) {
    $personal = $_SESSION['personal'] ?? null;
    $rolNorm = strtolower(trim((string)($personal['rol'] ?? '')));
    if ($rolNorm === 'guia' || $rolNorm === 'guía') {
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

    $institucion_id = isset($_POST['institucion_id']) ? filter_var($_POST['institucion_id'], FILTER_VALIDATE_INT) : null;
    $sede_id = isset($_POST['sede_id']) ? filter_var($_POST['sede_id'], FILTER_VALIDATE_INT) : null;
    $recorrido_id = isset($_POST['recorrido_id']) ? $_POST['recorrido_id'] : '';
    $fecha_visita = isset($_POST['fecha_visita']) ? trim($_POST['fecha_visita']) : '';
    $hora_inicio = isset($_POST['hora_inicio']) ? trim($_POST['hora_inicio']) : '';
    $metodo_pago = isset($_POST['metodo_pago']) ? trim($_POST['metodo_pago']) : '';
    $pago_estado = isset($_POST['pago_estado']) ? trim($_POST['pago_estado']) : 'pendiente';
    $estatus = isset($_POST['estatus']) ? trim($_POST['estatus']) : 'pendiente';

    if (empty($fecha_visita) || empty($hora_inicio) || empty($metodo_pago) || empty($estatus)) {
        $missingField = empty($fecha_visita) ? "fecha_visita" : (empty($hora_inicio) ? "hora_inicio" : (empty($metodo_pago) ? "metodo_pago" : "estatus"));
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los campos fecha de visita, hora de inicio, método de pago y estado de la cita son requeridos.",
            "field" => $missingField
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

    if (!empty($recorrido_id) && !filter_var($recorrido_id, FILTER_VALIDATE_INT)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El recorrido seleccionado no es válido.",
            "field" => "recorrido_id"
        ]);
        return;
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
        $citaActual = Cita::obtenerPorId($db, $id);
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

        $citaModel = new Cita($db);
        $citaModel->setId($id);
        $citaModel->setInstitucionId($finalInstId);
        $citaModel->setSedeId($finalSedeId);
        $citaModel->setRecorridoId($recorrido_id);
        $citaModel->setFechaVisita($fecha_visita);
        $citaModel->setHoraInicio($hora_inicio);
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
function vincularVisitantesAction($db) {
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
        $db->beginTransaction();

        $stmtVerificarLink = $db->prepare("SELECT id FROM asistencia_cita WHERE cita_id = :cita_id AND visitante_id = :visitante_id LIMIT 1");
        $stmtInsertarLink = $db->prepare("INSERT INTO asistencia_cita (cita_id, visitante_id) VALUES (:cita_id, :visitante_id)");

        $vinculados = 0;
        foreach ($visitante_ids as $vid) {
            $vidClean = filter_var($vid, FILTER_VALIDATE_INT);
            if (!$vidClean) continue;

            // Verificar si ya está vinculado
            $stmtVerificarLink->bindParam(":cita_id", $cita_id, PDO::PARAM_INT);
            $stmtVerificarLink->bindParam(":visitante_id", $vidClean, PDO::PARAM_INT);
            $stmtVerificarLink->execute();
            $linkExists = $stmtVerificarLink->fetchColumn();

            if (!$linkExists) {
                $stmtInsertarLink->bindParam(":cita_id", $cita_id, PDO::PARAM_INT);
                $stmtInsertarLink->bindParam(":visitante_id", $vidClean, PDO::PARAM_INT);
                $stmtInsertarLink->execute();
                $vinculados++;
            }
        }

        $db->commit();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Se han vinculado {$vinculados} visitantes a la cita exitosamente."
        ]);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
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
function desvincularVisitanteAction($db) {
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
        $query = "DELETE FROM asistencia_cita WHERE cita_id = :cita_id AND visitante_id = :visitante_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":cita_id", $cita_id, PDO::PARAM_INT);
        $stmt->bindParam(":visitante_id", $visitante_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
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
function listarGuiasAction($db) {
    try {
        $citaId = isset($_GET['cita_id']) ? filter_var($_GET['cita_id'], FILTER_VALIDATE_INT) : null;
        $fechaVisita = null;
        if ($citaId) {
            $stmtC = $db->prepare("SELECT fecha_visita FROM citas WHERE id = :id LIMIT 1");
            $stmtC->bindParam(':id', $citaId, PDO::PARAM_INT);
            $stmtC->execute();
            $fechaVisita = $stmtC->fetchColumn();
        }

        $query = "SELECT p.id, e.nombre_completo, e.nombre_completo AS nombre, '' AS apellido 
                  FROM personal p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  INNER JOIN roles r ON p.rol_id = r.id
                  WHERE r.nombre = 'guia'
                  ORDER BY e.nombre_completo ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $guias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si se especificó cita, calcular disponibilidad para esa fecha
        foreach ($guias as &$g) {
            if ($fechaVisita) {
                $tieneActiva = Cita::guiaTieneCitaActivaEnFecha($db, (int)$g['id'], (string)$fechaVisita, (int)$citaId);
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
function asignarGuiaAction($db) {
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
    $stmtCita = $db->prepare("SELECT fecha_visita FROM citas WHERE id = :id LIMIT 1");
    $stmtCita->bindParam(':id', $cita_id, PDO::PARAM_INT);
    $stmtCita->execute();
    $fechaVisita = $stmtCita->fetchColumn();

    if ($fechaVisita) {
        if (Cita::guiaTieneCitaActivaEnFecha($db, $personal_id, $fechaVisita, $cita_id)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "No se puede asignar este guía: ya tiene una visita guiada asignada para este mismo día que aún no ha finalizado. Solo podrá reasignarse una vez que complete la cita otorgada."
            ]);
            return;
        }
    }

    $success = Cita::asignarGuia($db, $cita_id, $personal_id, $rol_guia);

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
function desasignarGuiaAction($db) {
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

    $success = Cita::desasignarGuia($db, $cita_id, $personal_id);

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
function guiasCitaAction($db) {
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
        $query = "SELECT ga.personal_id, e.nombre_completo, e.nombre_completo AS nombre, '' AS apellido, ga.rol_guia 
                  FROM guia_asignado ga
                  INNER JOIN personal p ON ga.personal_id = p.id
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  WHERE ga.cita_id = :cita_id
                  ORDER BY ga.rol_guia ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':cita_id', $cita_id, PDO::PARAM_INT);
        $stmt->execute();
        $asignados = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
function empezarRecorridoAction($db) {
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

    // Si es guía, comprobar que esté asignado a la cita
    if ($rolNorm === 'guia' || $rolNorm === 'guía') {
        if (!Cita::esGuiaAsignado($db, $cita_id, $personalId)) {
            http_response_code(403);
            echo json_encode([
                "status" => "error",
                "message" => "No tienes permisos para iniciar este recorrido ya que no eres un guía asignado a esta cita."
            ]);
            return;
        }
    }

    $success = Cita::empezarRecorrido($db, $cita_id);

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
function terminarRecorridoAction($db) {
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

    // Si es guía, comprobar que esté asignado a la cita
    if ($rolNorm === 'guia' || $rolNorm === 'guía') {
        if (!Cita::esGuiaAsignado($db, $cita_id, $personalId)) {
            http_response_code(403);
            echo json_encode([
                "status" => "error",
                "message" => "No tienes permisos para finalizar este recorrido ya que no eres un guía asignado a esta cita."
            ]);
            return;
        }
    }

    $success = Cita::terminarRecorrido($db, $cita_id);

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
function asignarRecorridoAction($db) {
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

    // Permisos: SuperAdmin, Admin o Guía asignado a esta cita
    $esAdmin = ($rolNorm === 'superadmin' || $rolNorm === 'admin');
    if (!$esAdmin) {
        if ($rolNorm === 'guia' || $rolNorm === 'guía') {
            if (!Cita::esGuiaAsignado($db, $cita_id, $personalId)) {
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
        $stmtR = $db->prepare("SELECT nombre FROM recorrido WHERE id = :id LIMIT 1");
        $stmtR->bindParam(':id', $recorrido_id, PDO::PARAM_INT);
        $stmtR->execute();
        $nombreRecorrido = $stmtR->fetchColumn();
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

    $success = Cita::actualizarRecorrido($db, $cita_id, $recorrido_id);

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
