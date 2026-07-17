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
    $institucion_id = isset($_POST['institucion_id']) ? filter_var($_POST['institucion_id'], FILTER_VALIDATE_INT) : null;
    
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
            "message" => "Los campos fecha de visita, hora de inicio y método de pago son requeridos."
        ]);
        return;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_visita)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La fecha de la visita debe tener el formato AAAA-MM-DD."
        ]);
        return;
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_inicio)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La hora de inicio de la visita no es válida."
        ]);
        return;
    }

    if (strlen($metodo_pago) > 50) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El método de pago no es válido."
        ]);
        return;
    }

    if (!in_array($pago_estado, ['pendiente', 'pagado'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El estado de pago no es válido."
        ]);
        return;
    }

    if (!empty($recorrido_id) && !filter_var($recorrido_id, FILTER_VALIDATE_INT)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El recorrido seleccionado no es válido."
        ]);
        return;
    }

    try {
        // 1. Si no existe ID de institución, registrarla primero
        if (!$institucion_id) {
            $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'J';
            $documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';
            $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
            $tipo_organizacion = isset($_POST['tipo_organizacion']) ? trim($_POST['tipo_organizacion']) : '';
            $tipo_gestion = isset($_POST['tipo_gestion']) ? trim($_POST['tipo_gestion']) : 'publica';
            $ciudad_id = isset($_POST['ciudad_id']) ? $_POST['ciudad_id'] : '';
            $parroquia_id = isset($_POST['parroquia_id']) ? $_POST['parroquia_id'] : '';
            $detalles_direccion = isset($_POST['detalles_direccion']) ? trim($_POST['detalles_direccion']) : '';
            $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

            if (empty($documento) || empty($nombre) || empty($tipo_organizacion) || empty($tipo_gestion) || empty($ciudad_id) || empty($parroquia_id)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Para registrar la institución se requiere RIF, Razón Social, Tipo, Gestión, Ciudad y Parroquia."
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

            if (strlen($documento) > 50) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "El número de documento/RIF no es válido."
                ]);
                return;
            }

            if (strlen($nombre) > 150) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "La razón social de la institución no es válida."
                ]);
                return;
            }

            if (strlen($tipo_organizacion) > 50) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "El tipo de organización no es válido."
                ]);
                return;
            }

            if (!in_array($tipo_gestion, ['publica', 'privada'], true)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "El tipo de gestión proporcionado no es válido."
                ]);
                return;
            }

            if (!filter_var($ciudad_id, FILTER_VALIDATE_INT)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Ciudad seleccionada inválida."
                ]);
                return;
            }

            if (!filter_var($parroquia_id, FILTER_VALIDATE_INT)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Parroquia seleccionada inválida."
                ]);
                return;
            }

            if (strlen($detalles_direccion) > 150) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Los detalles de la dirección superan el límite permitido de 150 caracteres."
                ]);
                return;
            }

            // Mapear contactos
            $contactos = [];
            if (is_array($contactosRaw)) {
                foreach ($contactosRaw as $c) {
                    if (!empty(trim($c['contacto'] ?? ''))) {
                        $contactos[] = [
                            'contacto' => trim($c['contacto']),
                            'tipo' => trim($c['tipo'] ?? 'principal')
                        ];
                    }
                }
            }

            if (empty($contactos)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Debe ingresar al menos un número de contacto válido."
                ]);
                return;
            }

            $instModel = new Institucion($db);
            $instModel->setTipoDocumento($tipo_documento);
            $instModel->setDocumento($documento);
            $instModel->setNombre($nombre);
            $instModel->setTipoOrganizacion($tipo_organizacion);
            $instModel->setTipoGestion($tipo_gestion);
            $instModel->setCiudadId($ciudad_id);
            $instModel->setParroquiaId($parroquia_id);
            $instModel->setDetallesDireccion($detalles_direccion);
            $instModel->setContactos($contactos);

            // Guardar institución
            if ($instModel->crear()) {
                $institucion_id = $instModel->getId();
            } else {
                throw new Exception("No se pudo registrar la institución.");
            }
        }

        // 2. Registrar la cita
        $citaModel = new Cita($db);
        $citaModel->setInstitucionId($institucion_id);
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

    // Generar archivo Excel (.xlsx) con cabecera verde institucional y fila de ejemplo
    $writer = new ExcelWriter();
    $writer->setSheetName('Visitantes');
    $writer->setHeaders(
        ['Cédula', 'Nombres', 'Apellidos', 'Fecha Nacimiento (AAAA-MM-DD)'],
        [18, 28, 28, 26]   // anchos de columna
    );
    // Fila de ejemplo (será ignorada automáticamente al importar)
    $writer->addRow(['12345678', 'Pedro', 'Pérez', '2010-05-15']);

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
        $visitantes = json_decode($visitantesJson, true);
        if (!is_array($visitantes)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "El listado de visitantes tiene un formato JSON inválido."
            ]);
            return;
        }
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

        // Comprobar y saltar BOM de UTF-8 si está presente
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Leer cabecera para autodetectar el delimitador (punto y coma o coma)
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

        // Leer filas del CSV
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            $rowIndex++;
            // Omitir filas vacías o incompletas
            if (empty($data) || count($data) < 3) continue;
            
            $ced = trim($data[0] ?? '');
            $nom = trim($data[1] ?? '');
            $ape = trim($data[2] ?? '');
            $fNac = trim($data[3] ?? '');

            // Saltar la fila de ejemplo si el usuario no la borró
            if ($ced === '12345678' && $nom === 'Pedro' && $ape === 'Pérez') {
                continue;
            }

            // Si nombres y apellidos están vacíos, omitimos
            if (empty($nom) && empty($ape)) {
                continue;
            }

            $visitantes[] = [
                'cedula' => $ced,
                'nombres' => $nom,
                'apellidos' => $ape,
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
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El ID de la cita es requerido para editar."
        ]);
        return;
    }

    $recorrido_id = isset($_POST['recorrido_id']) ? $_POST['recorrido_id'] : '';
    $fecha_visita = isset($_POST['fecha_visita']) ? trim($_POST['fecha_visita']) : '';
    $hora_inicio = isset($_POST['hora_inicio']) ? trim($_POST['hora_inicio']) : '';
    $metodo_pago = isset($_POST['metodo_pago']) ? trim($_POST['metodo_pago']) : '';
    $pago_estado = isset($_POST['pago_estado']) ? trim($_POST['pago_estado']) : 'pendiente';
    $estatus = isset($_POST['estatus']) ? trim($_POST['estatus']) : 'pendiente';

    if (empty($fecha_visita) || empty($hora_inicio) || empty($metodo_pago) || empty($estatus)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Los campos fecha de visita, hora de inicio, método de pago y estado de la cita son requeridos."
        ]);
        return;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_visita)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La fecha de la visita debe tener el formato AAAA-MM-DD."
        ]);
        return;
    }

    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_inicio)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La hora de inicio de la visita no es válida."
        ]);
        return;
    }

    if (strlen($metodo_pago) > 50) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El método de pago no es válido."
        ]);
        return;
    }

    if (!in_array($pago_estado, ['pendiente', 'pagado'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El estado de pago no es válido."
        ]);
        return;
    }

    if (!empty($recorrido_id) && !filter_var($recorrido_id, FILTER_VALIDATE_INT)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El recorrido seleccionado no es válido."
        ]);
        return;
    }

    if (!in_array($estatus, ['pendiente', 'aprobado', 'completado', 'cancelado'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El estado de la cita proporcionado no es válido."
        ]);
        return;
    }

    try {
        // Obtener la cita actual para verificar existencia
        $citaActual = Cita::obtenerPorId($db, $id);
        if (!$citaActual) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "La cita seleccionada no existe en el sistema."
            ]);
            return;
        }

        $citaModel = new Cita($db);
        $citaModel->setId($id);
        $citaModel->setInstitucionId($citaActual['institucion_id']); // mantener institución asociada
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
        $query = "SELECT p.id, e.nombre, e.apellido 
                  FROM personal p
                  INNER JOIN entidades e ON p.entidad_id = e.id
                  INNER JOIN roles r ON p.rol_id = r.id
                  WHERE r.nombre = 'guia'
                  ORDER BY e.nombre ASC, e.apellido ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $guias = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $query = "SELECT ga.personal_id, e.nombre, e.apellido, ga.rol_guia 
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




