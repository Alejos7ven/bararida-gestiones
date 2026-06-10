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
        case 'eliminar':
            eliminarCitaAction($db);
            break;
        case 'importar_visitantes':
            importarVisitantesAction($db);
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

    try {
        // 1. Si no existe ID de institución, registrarla primero
        if (!$institucion_id) {
            $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'J';
            $documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';
            $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
            $tipo_organizacion = isset($_POST['tipo_organizacion']) ? trim($_POST['tipo_organizacion']) : '';
            $ciudad_id = isset($_POST['ciudad_id']) ? $_POST['ciudad_id'] : '';
            $parroquia_id = isset($_POST['parroquia_id']) ? $_POST['parroquia_id'] : '';
            $detalles_direccion = isset($_POST['detalles_direccion']) ? trim($_POST['detalles_direccion']) : '';
            $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

            if (empty($documento) || empty($nombre) || empty($tipo_organizacion) || empty($ciudad_id) || empty($parroquia_id)) {
                http_response_code(400);
                echo json_encode([
                    "status" => "error",
                    "message" => "Para registrar la institución se requiere RIF, Razón Social, Tipo de Organización, Ciudad y Parroquia."
                ]);
                return;
            }

            $instModel = new Institucion($db);
            $instModel->setTipoDocumento($tipo_documento);
            $instModel->setDocumento($documento);
            $instModel->setNombre($nombre);
            $instModel->setTipoOrganizacion($tipo_organizacion);
            $instModel->setCiudadId($ciudad_id);
            $instModel->setParroquiaId($parroquia_id);
            $instModel->setDetallesDireccion($detalles_direccion);
            
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

    // Configurar cabeceras de descarga de archivo CSV
    $filename = "plantilla_visitantes.csv";
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Imprimir el BOM de UTF-8 para garantizar que Excel abra el archivo con acentos/eñes correctos
    echo "\xEF\xBB\xBF";

    // Cabeceras de las columnas (Utilizamos punto y coma ';' como delimitador estándar para Excel en español)
    echo "Cédula;Nombres;Apellidos;Fecha Nacimiento (AAAA-MM-DD)\r\n";
    // Fila de ejemplo
    echo "12345678;Pedro;Pérez;2010-05-15\r\n";
    exit;
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

