<?php
/**
 * Controlador de Instituciones (InstitucionController)
 * Gestiona el listado, creación, edición, sedes y eliminación de instituciones externas.
 * Acceso restringido a personal autenticado.
 * Respeta el patrón MVC operando mediante los modelos Institucion y Sede con setters/getters.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

// Verificar autenticación
if (!isset($_SESSION['personal'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Sesión no activa. Por favor inicie sesión de nuevo."
    ]);
    exit;
}

// Incluir dependencias
require_once __DIR__ . "/../models/Institucion.php";
require_once __DIR__ . "/../models/Sede.php";

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'crear':
            crearInstitucionAction();
            break;
        case 'actualizar':
            actualizarInstitucionAction();
            break;
        case 'eliminar':
            eliminarInstitucionAction();
            break;
        case 'agregar_sede':
            agregarSedeAction();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción POST no válida para Instituciones."
            ]);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'listar':
            listarInstitucionesAction();
            break;
        case 'obtener':
            obtenerInstitucionAction();
            break;
        case 'obtener_sedes':
            obtenerSedesAction();
            break;
        case 'buscar_autocomplete':
            buscarAutocompleteAction();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => "Acción GET no válida para Instituciones."
            ]);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Método HTTP no soportado."
    ]);
}

// =========================================================================
// ACCIONES DEL CONTROLADOR
// =========================================================================

function listarInstitucionesAction() {
    try {
        $instModel = new Institucion();
        $instituciones = $instModel->listarTodas();
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $instituciones
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al obtener instituciones: " . $e->getMessage()
        ]);
    }
}

function obtenerInstitucionAction() {
    $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de la institución inválido o no suministrado."
        ]);
        return;
    }

    $instModel = new Institucion();
    $instModel->setId($id);
    $institucion = $instModel->obtenerPorId();
    if ($institucion) {
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $institucion
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "La institución indicada no existe en el sistema."
        ]);
    }
}

function obtenerSedesAction() {
    $institucion_id = isset($_GET['institucion_id']) ? filter_var($_GET['institucion_id'], FILTER_VALIDATE_INT) : (isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null);
    if (!$institucion_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de institución inválido."
        ]);
        return;
    }

    $sedeModel = new Sede();
    $sedeModel->setInstitucionId($institucion_id);
    $sedes = $sedeModel->listarPorInstitucion();
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "data" => $sedes
    ]);
}

function crearInstitucionAction() {
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'J';
    $documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';
    $nombre_completo = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : (isset($_POST['nombre']) ? trim($_POST['nombre']) : '');
    $tipo_organizacion = isset($_POST['tipo_organizacion']) ? trim($_POST['tipo_organizacion']) : '';
    $tipo_gestion = isset($_POST['tipo_gestion']) ? trim($_POST['tipo_gestion']) : 'publica';
    $sedesRaw = isset($_POST['sedes']) ? $_POST['sedes'] : [];
    $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

    // Fallback: si no viene el array de sedes pero viene parroquia_id directo
    if (empty($sedesRaw) && !empty($_POST['parroquia_id'])) {
        $sedesRaw[] = [
            'parroquia_id' => $_POST['parroquia_id'],
            'detalles' => $_POST['detalles_direccion'] ?? ''
        ];
    }

    // Validaciones en Controller
    if (empty($documento) || empty($nombre_completo) || empty($tipo_organizacion) || empty($tipo_gestion)) {
        $missingField = empty($documento) ? "documento" : (empty($nombre_completo) ? "nombre_completo" : (empty($tipo_organizacion) ? "tipo_organizacion" : "tipo_gestion"));
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los datos identificativos de la institución son obligatorios (Tipo, RIF, Razón Social, Tipo de Org. y Gestión).",
            "field" => $missingField
        ]);
        return;
    }

    if (!in_array($tipo_documento, ['J', 'G', 'V', 'E', 'P'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Tipo de documento/RIF no válido.",
            "field" => "tipo_documento"
        ]);
        return;
    }

    if (strlen($documento) < 3 || strlen($documento) > 50) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El número de documento/RIF no es válido.",
            "field" => "documento"
        ]);
        return;
    }

    if (strlen($nombre_completo) < 3 || strlen($nombre_completo) > 150) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La razón social debe tener entre 3 y 150 caracteres.",
            "field" => "nombre_completo"
        ]);
        return;
    }

    if (!in_array($tipo_gestion, ['publica', 'privada'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El tipo de gestión proporcionado no es válido.",
            "field" => "tipo_gestion"
        ]);
        return;
    }

    // Validar sedes
    $sedesValidadas = [];
    if (is_array($sedesRaw)) {
        foreach ($sedesRaw as $idx => $s) {
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
            "message" => "Debe agregar al menos una sede válida con su respectiva Parroquia.",
            "field" => "sedes"
        ]);
        return;
    }

    // Validar contactos
    $contactos = [];
    if (is_array($contactosRaw)) {
        foreach ($contactosRaw as $c) {
            $contVal = strip_tags(trim($c['contacto'] ?? ''));
            $tipo = trim($c['tipo'] ?? 'telefono');
            if (!empty($contVal)) {
                if ($tipo === 'telefono') {
                    $cleanDigits = preg_replace('/[^0-9]/', '', $contVal);
                    if (preg_match('/[a-zA-Z]/', $contVal) || strlen($cleanDigits) < 7 || strlen($cleanDigits) > 20) {
                        http_response_code(400);
                        echo json_encode([
                            "status" => "error",
                            "message" => "El número de teléfono '{$contVal}' es inválido. No debe contener letras y debe tener al menos 7 dígitos.",
                            "field" => "telefono"
                        ]);
                        return;
                    }
                } elseif ($tipo === 'correo') {
                    if (!filter_var($contVal, FILTER_VALIDATE_EMAIL)) {
                        http_response_code(400);
                        echo json_encode([
                            "status" => "error",
                            "message" => "El correo electrónico '{$contVal}' no tiene un formato válido.",
                            "field" => "correo"
                        ]);
                        return;
                    }
                }
                $contactos[] = [
                    'contacto' => substr($contVal, 0, 50),
                    'tipo' => $tipo
                ];
            }
        }
    }

    if (empty($contactos)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe ingresar al menos un número de teléfono o correo de contacto válido.",
            "field" => "contacto"
        ]);
        return;
    }

    // Validar unicidad del RIF/documento mediante el modelo Institucion
    $instModel = new Institucion();
    $instModel->setTipoDocumento($tipo_documento);
    $instModel->setDocumento($documento);
    $existente = $instModel->buscarPorDocumento();
    if ($existente) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El RIF/documento {$tipo_documento}-{$documento} ya se encuentra registrado con la institución: " . $existente['nombre_completo'],
            "field" => "documento"
        ]);
        return;
    }

    try {
        $instModel->setNombreCompleto($nombre_completo);
        $instModel->setTipoOrganizacion($tipo_organizacion);
        $instModel->setTipoGestion($tipo_gestion);
        $instModel->setSedes($sedesValidadas);
        $instModel->setContactos($contactos);

        if ($instModel->crear()) {
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Institución registrada exitosamente con " . count($sedesValidadas) . " sede(s).",
                "data" => [
                    "id" => $instModel->getId(),
                    "nombre" => $instModel->getNombreCompleto()
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar la institución en la base de datos."
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
            "message" => "Ocurrió un error inesperado al registrar la institución: " . $e->getMessage()
        ]);
    }
}

function actualizarInstitucionAction() {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    $entidad_id = isset($_POST['entidad_id']) ? filter_var($_POST['entidad_id'], FILTER_VALIDATE_INT) : null;
    $tipo_documento = isset($_POST['tipo_documento']) ? strtoupper(trim($_POST['tipo_documento'])) : 'J';
    $documento = isset($_POST['documento']) ? trim($_POST['documento']) : '';
    $nombre_completo = isset($_POST['nombre_completo']) ? trim($_POST['nombre_completo']) : (isset($_POST['nombre']) ? trim($_POST['nombre']) : '');
    $tipo_organizacion = isset($_POST['tipo_organizacion']) ? trim($_POST['tipo_organizacion']) : '';
    $tipo_gestion = isset($_POST['tipo_gestion']) ? trim($_POST['tipo_gestion']) : 'publica';
    $sedesRaw = isset($_POST['sedes']) ? $_POST['sedes'] : [];
    $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

    if (!$id || !$entidad_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de la institución o de la entidad inválido o no suministrado."
        ]);
        return;
    }

    // Fallback: si no viene el array de sedes pero viene parroquia_id directo
    if (empty($sedesRaw) && !empty($_POST['parroquia_id'])) {
        $sedesRaw[] = [
            'id' => $_POST['sede_id'] ?? null,
            'parroquia_id' => $_POST['parroquia_id'],
            'detalles' => $_POST['detalles_direccion'] ?? ''
        ];
    }

    if (empty($documento) || empty($nombre_completo) || empty($tipo_organizacion) || empty($tipo_gestion)) {
        $missingField = empty($documento) ? "documento" : (empty($nombre_completo) ? "nombre_completo" : (empty($tipo_organizacion) ? "tipo_organizacion" : "tipo_gestion"));
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Todos los datos identificativos de la institución son obligatorios.",
            "field" => $missingField
        ]);
        return;
    }

    if (!in_array($tipo_documento, ['J', 'G', 'V', 'E', 'P'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Tipo de documento/RIF no válido.",
            "field" => "tipo_documento"
        ]);
        return;
    }

    if (strlen($documento) < 3 || strlen($documento) > 50) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El número de documento/RIF no es válido.",
            "field" => "documento"
        ]);
        return;
    }

    if (strlen($nombre_completo) < 3 || strlen($nombre_completo) > 150) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "La razón social debe tener entre 3 y 150 caracteres.",
            "field" => "nombre_completo"
        ]);
        return;
    }

    if (!in_array($tipo_gestion, ['publica', 'privada'], true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El tipo de gestión proporcionado no es válido.",
            "field" => "tipo_gestion"
        ]);
        return;
    }

    $sedesValidadas = [];
    if (is_array($sedesRaw)) {
        foreach ($sedesRaw as $idx => $s) {
            $sId = !empty($s['id']) ? filter_var($s['id'], FILTER_VALIDATE_INT) : null;
            $pId = filter_var($s['parroquia_id'] ?? 0, FILTER_VALIDATE_INT);
            $det = strip_tags(trim($s['detalles'] ?? ''));

            if ($pId) {
                $sedesValidadas[] = [
                    'id' => $sId,
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
            "message" => "Debe registrar al menos una sede válida para la institución.",
            "field" => "sedes"
        ]);
        return;
    }

    $contactos = [];
    if (is_array($contactosRaw)) {
        foreach ($contactosRaw as $c) {
            $contVal = strip_tags(trim($c['contacto'] ?? ''));
            $tipo = trim($c['tipo'] ?? 'telefono');
            if (!empty($contVal)) {
                if ($tipo === 'telefono') {
                    $cleanDigits = preg_replace('/[^0-9]/', '', $contVal);
                    if (preg_match('/[a-zA-Z]/', $contVal) || strlen($cleanDigits) < 7 || strlen($cleanDigits) > 20) {
                        http_response_code(400);
                        echo json_encode([
                            "status" => "error",
                            "message" => "El número de teléfono '{$contVal}' es inválido.",
                            "field" => "telefono"
                        ]);
                        return;
                    }
                } elseif ($tipo === 'correo') {
                    if (!filter_var($contVal, FILTER_VALIDATE_EMAIL)) {
                        http_response_code(400);
                        echo json_encode([
                            "status" => "error",
                            "message" => "El correo electrónico '{$contVal}' no tiene un formato válido.",
                            "field" => "correo"
                        ]);
                        return;
                    }
                }
                $contactos[] = [
                    'contacto' => substr($contVal, 0, 50),
                    'tipo' => $tipo
                ];
            }
        }
    }

    if (empty($contactos)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Debe ingresar al menos un número de teléfono o correo de contacto válido.",
            "field" => "contacto"
        ]);
        return;
    }

    // Validar si el RIF/documento ya pertenece a otra institución mediante el modelo
    $instModel = new Institucion();
    $instModel->setTipoDocumento($tipo_documento);
    $instModel->setDocumento($documento);
    $instModel->setEntidadId($entidad_id);
    if ($instModel->existeDocumentoEnOtraEntidad()) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "El RIF/documento ingresado ya pertenece a otra entidad o institución.",
            "field" => "documento"
        ]);
        return;
    }

    try {
        $instModel->setId($id);
        $instModel->setNombreCompleto($nombre_completo);
        $instModel->setTipoOrganizacion($tipo_organizacion);
        $instModel->setTipoGestion($tipo_gestion);
        $instModel->setSedes($sedesValidadas);
        $instModel->setContactos($contactos);

        if ($instModel->actualizar()) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Institución y sedes actualizadas correctamente."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo actualizar la información de la institución."
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
            "message" => "Ocurrió un error inesperado al actualizar la institución: " . $e->getMessage()
        ]);
    }
}

function agregarSedeAction() {
    $institucion_id = isset($_POST['institucion_id']) ? filter_var($_POST['institucion_id'], FILTER_VALIDATE_INT) : null;
    $parroquia_id = isset($_POST['parroquia_id']) ? filter_var($_POST['parroquia_id'], FILTER_VALIDATE_INT) : null;
    $detalles = isset($_POST['detalles']) ? strip_tags(trim($_POST['detalles'])) : (isset($_POST['detalles_direccion']) ? strip_tags(trim($_POST['detalles_direccion'])) : '');

    if (!$institucion_id || !$parroquia_id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Institución y Parroquia son obligatorios para registrar la sede."
        ]);
        return;
    }

    try {
        $sedeModel = new Sede();
        $sedeModel->setInstitucionId($institucion_id);
        $sedeModel->setParroquiaId($parroquia_id);
        $sedeModel->setDetalles(substr($detalles, 0, 150));

        if ($sedeModel->crear()) {
            $sedeCompletaModel = new Sede();
            $sedeCompletaModel->setId($sedeModel->getId());
            $sedeCompleta = $sedeCompletaModel->obtenerPorId();
            http_response_code(201);
            echo json_encode([
                "status" => "success",
                "message" => "Sede registrada con éxito.",
                "data" => $sedeCompleta
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo registrar la nueva sede."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al agregar sede: " . $e->getMessage()
        ]);
    }
}

function eliminarInstitucionAction() {
    $id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "ID de institución inválido o no suministrado."
        ]);
        return;
    }

    // Regla de negocio en el Controller: no permitir eliminar instituciones con citas
    $instModel = new Institucion();
    $instModel->setId($id);
    $totalCitas = $instModel->contarCitas();
    if ($totalCitas > 0) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "No se puede eliminar la institución porque posee citas registradas (pasadas, activas o futuras)."
        ]);
        return;
    }

    try {
        if ($instModel->eliminar()) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "message" => "Institución eliminada correctamente del sistema."
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "No se pudo eliminar la institución de la base de datos."
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
 * Busca instituciones para sugerencias de autocompletado en tiempo real
 */
function buscarAutocompleteAction() {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (strlen($q) < 2) {
        echo json_encode([
            "status" => "success",
            "data" => []
        ]);
        return;
    }

    try {
        $instModel = new Institucion();
        $instModel->setTerminoBusqueda($q);
        $resultados = $instModel->buscarPorTermino();
        echo json_encode([
            "status" => "success",
            "data" => $resultados
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error al buscar instituciones: " . $e->getMessage()
        ]);
    }
}