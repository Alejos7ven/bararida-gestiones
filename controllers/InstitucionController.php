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
require_once __DIR__ . "/../config/Validator.php";
require_once __DIR__ . "/../models/Institucion.php";
require_once __DIR__ . "/../models/Sede.php";
require_once __DIR__ . "/../models/Permiso.php";

$currentUser = $_SESSION['personal'];
$userRolId   = isset($currentUser['rol_id']) ? (int)$currentUser['rol_id'] : 0;
$userRol     = $currentUser['rol'] ?? '';

$permisoModel = new Permiso();
if ($userRolId <= 0 && !empty($userRol)) {
    $permisoModel->setRolNombre($userRol);
    $userRolId = $permisoModel->obtenerRolIdPorNombre() ?? 0;
    if ($userRolId > 0) {
        $_SESSION['personal']['rol_id'] = $userRolId;
    }
}

if (!$permisoModel->tieneAccesoModulo($userRolId, 'instituciones', $userRol)) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Acceso denegado. No posee permisos para acceder al módulo de instituciones."
    ]);
    exit;
}

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

        if (isset($_GET['draw'])) {
            $draw = (int)$_GET['draw'];
            $start = isset($_GET['start']) && is_numeric($_GET['start']) ? max(0, (int)$_GET['start']) : 0;
            $length = isset($_GET['length']) && is_numeric($_GET['length']) ? (int)$_GET['length'] : 10;
            $search = isset($_GET['search']['value']) ? trim((string)$_GET['search']['value']) : '';

            $columnsMap = [
                0 => 'e.documento',
                1 => 'e.nombre_completo',
                2 => 'i.tipo_organizacion',
                3 => 'i.id',
                4 => 'i.id'
            ];

            $orderColIdx = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 1;
            $orderColumn = $columnsMap[$orderColIdx] ?? 'e.nombre_completo';
            $orderDirRaw = isset($_GET['order'][0]['dir']) ? strtoupper(trim((string)$_GET['order'][0]['dir'])) : 'ASC';
            $orderDir = ($orderDirRaw === 'DESC') ? 'DESC' : 'ASC';

            $instModel->setDtStart($start);
            $instModel->setDtLength($length);
            $instModel->setDtSearch($search);
            $instModel->setDtOrderColumn($orderColumn);
            $instModel->setDtOrderDir($orderDir);

            $resultado = $instModel->listarPaginado();

            http_response_code(200);
            echo json_encode([
                "status"          => "success",
                "draw"            => $draw,
                "recordsTotal"    => $resultado['recordsTotal'],
                "recordsFiltered" => $resultado['recordsFiltered'],
                "data"            => $resultado['data']
            ]);
            return;
        }

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
    if (empty($_POST['nombre_completo']) && isset($_POST['nombre'])) {
        $_POST['nombre_completo'] = trim($_POST['nombre']);
    }

    $sedesRaw = isset($_POST['sedes']) ? $_POST['sedes'] : [];
    $contactosRaw = isset($_POST['contactos']) ? $_POST['contactos'] : [];

    // Fallback: si no viene el array de sedes pero viene parroquia_id directo
    if (empty($sedesRaw) && !empty($_POST['parroquia_id'])) {
        $sedesRaw[] = [
            'parroquia_id' => $_POST['parroquia_id'],
            'detalles' => $_POST['detalles_direccion'] ?? ''
        ];
    }

    $validator = Validator::make($_POST, [
        'tipo_documento'    => ['required' => true, 'in' => ['J', 'G', 'V', 'E', 'P'], 'maxlength' => 10],
        'documento'         => ['required' => true, 'digits' => true, 'minlength' => 6, 'maxlength' => 15],
        'nombre_completo'   => ['required' => true, 'minlength' => 3, 'maxlength' => 50, 'noLeadingSpaces' => true],
        'tipo_organizacion' => ['required' => true, 'minlength' => 3, 'maxlength' => 20, 'noLeadingSpaces' => true],
        'tipo_gestion'      => ['required' => true, 'in' => ['publica', 'privada'], 'maxlength' => 20]
    ], [
        'documento' => [
            'required'  => 'El RIF/Cédula es requerido.',
            'digits'    => 'El RIF/Cédula debe contener solo números.',
            'minlength' => 'El RIF/Cédula debe tener al menos 6 dígitos.',
            'maxlength' => 'El RIF/Cédula no puede exceder los 15 dígitos.'
        ],
        'nombre_completo' => [
            'required'        => 'La Razón Social / Nombre Oficial es requerida.',
            'minlength'       => 'La Razón Social debe tener al menos 3 caracteres.',
            'maxlength'       => 'La Razón Social no puede superar los 50 caracteres.',
            'noLeadingSpaces' => 'La Razón Social no puede iniciar con espacios ni estar compuesta solo por espacios.'
        ],
        'tipo_organizacion' => [
            'required'  => 'Debe seleccionar o especificar un tipo de organización.',
            'maxlength' => 'El tipo de organización no puede exceder los 20 caracteres.'
        ],
        'tipo_gestion' => [
            'required'  => 'Debe seleccionar el tipo de gestión.',
            'in'        => 'El tipo de gestión proporcionado no es válido (pública o privada).',
            'maxlength' => 'El tipo de gestión no puede exceder los 20 caracteres.'
        ]
    ]);

    if ($validator->fails()) {
        $validator->sendJsonResponse(400);
        return;
    }

    $tipo_documento    = strtoupper($validator->getValue('tipo_documento'));
    $documento         = $validator->getValue('documento');
    $nombre_completo   = $validator->getValue('nombre_completo');
    $tipo_organizacion = $validator->getValue('tipo_organizacion');
    $tipo_gestion      = $validator->getValue('tipo_gestion');

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

    // Validar unicidad del RIF/documento en el sistema mediante el modelo Institucion
    $instModel = new Institucion();
    $instModel->setDocumento($documento);
    $entidadExistente = $instModel->buscarEntidadPorDocumento();
    if ($entidadExistente) {
        $tipoDocExistente = $entidadExistente['tipo_documento'] ?? $tipo_documento;
        $docCompleto = "{$tipoDocExistente}-{$documento}";
        $nombreEntidad = $entidadExistente['nombre_completo'] ?? '';

        if (!empty($entidadExistente['rol_personal'])) {
            $rolPersonal = ucfirst($entidadExistente['rol_personal']);
            $mensaje = "El documento {$docCompleto} ya se encuentra registrado por un personal del parque: \"{$nombreEntidad}\" ({$rolPersonal}). Por favor, ingrese un número de documento diferente.";
        } elseif (!empty($entidadExistente['institucion_activa_id'])) {
            $mensaje = "El RIF/documento {$docCompleto} ya se encuentra registrado con la institución: \"{$nombreEntidad}\".";
        } else {
            $mensaje = "El documento {$docCompleto} ya se encuentra registrado en el sistema a nombre de: \"{$nombreEntidad}\". Por favor, verifique el número ingresado.";
        }

        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $mensaje,
            "field" => "documento"
        ]);
        return;
    }

    try {
        $instModel->setTipoDocumento($tipo_documento);
        $instModel->setDocumento($documento);
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
            "message" => $e->getMessage(),
            "field" => "documento"
        ]);
    } catch (Exception $e) {
        error_log("Error en crearInstitucionAction: " . $e->getMessage());
        $isDuplicate = (stripos($e->getMessage(), 'Duplicate entry') !== false || stripos($e->getMessage(), '1062') !== false);
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $isDuplicate 
                ? "El documento ingresado ya se encuentra registrado en el sistema. Por favor verifique el número de RIF/Cédula." 
                : "No fue posible registrar la institución debido a un inconveniente en el servidor. Por favor intente más tarde.",
            "field" => $isDuplicate ? "documento" : null
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

    if (empty($_POST['nombre_completo']) && isset($_POST['nombre'])) {
        $_POST['nombre_completo'] = trim($_POST['nombre']);
    }

    // Fallback: si no viene el array de sedes pero viene parroquia_id directo
    if (empty($sedesRaw) && !empty($_POST['parroquia_id'])) {
        $sedesRaw[] = [
            'id' => $_POST['sede_id'] ?? null,
            'parroquia_id' => $_POST['parroquia_id'],
            'detalles' => $_POST['detalles_direccion'] ?? ''
        ];
    }

    $validator = Validator::make($_POST, [
        'id'                => ['required' => true, 'integer' => true, 'min' => 1],
        'entidad_id'        => ['required' => false, 'integer' => true, 'min' => 1],
        'tipo_documento'    => ['required' => true, 'in' => ['J', 'G', 'V', 'E', 'P'], 'maxlength' => 10],
        'documento'         => ['required' => true, 'digits' => true, 'minlength' => 6, 'maxlength' => 15],
        'nombre_completo'   => ['required' => true, 'minlength' => 3, 'maxlength' => 50, 'noLeadingSpaces' => true],
        'tipo_organizacion' => ['required' => true, 'minlength' => 3, 'maxlength' => 20, 'noLeadingSpaces' => true],
        'tipo_gestion'      => ['required' => true, 'in' => ['publica', 'privada'], 'maxlength' => 20]
    ], [
        'id' => [
            'required' => 'ID de la institución inválido o no suministrado.'
        ],
        'tipo_documento' => [
            'required' => 'El tipo de documento es obligatorio.',
            'in'       => 'El tipo de documento no es válido.'
        ],
        'documento' => [
            'required'  => 'El RIF/Cédula es requerido.',
            'digits'    => 'El RIF/Cédula debe contener solo números.',
            'minlength' => 'El RIF/Cédula debe tener al menos 6 dígitos.',
            'maxlength' => 'El RIF/Cédula no puede exceder los 15 dígitos.'
        ],
        'nombre_completo' => [
            'required'        => 'La Razón Social / Nombre Oficial es requerida.',
            'minlength'       => 'La Razón Social debe tener al menos 3 caracteres.',
            'maxlength'       => 'La Razón Social no puede superar los 50 caracteres.',
            'noLeadingSpaces' => 'La Razón Social no puede iniciar con espacios ni estar compuesta solo por espacios.'
        ],
        'tipo_organizacion' => [
            'required'  => 'Debe seleccionar o especificar un tipo de organización.',
            'maxlength' => 'El tipo de organización no puede exceder los 20 caracteres.'
        ],
        'tipo_gestion' => [
            'required'  => 'Debe seleccionar el tipo de gestión.',
            'in'        => 'El tipo de gestión proporcionado no es válido (pública o privada).',
            'maxlength' => 'El tipo de gestión no puede exceder los 20 caracteres.'
        ]
    ]);

    if ($validator->fails()) {
        $validator->sendJsonResponse(400);
        return;
    }

    $id                = (int)$validator->getValue('id');
    $tipo_documento    = strtoupper($validator->getValue('tipo_documento'));
    $documento         = $validator->getValue('documento');
    $nombre_completo   = $validator->getValue('nombre_completo');
    $tipo_organizacion = $validator->getValue('tipo_organizacion');
    $tipo_gestion      = $validator->getValue('tipo_gestion');

    // Verificar existencia de la institución y obtener su entidad_id real
    $instModel = new Institucion();
    $instModel->setId($id);
    $instActual = $instModel->obtenerPorId();
    if (!$instActual) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "La institución que intenta actualizar no existe en el sistema."
        ]);
        return;
    }

    $entidad_id = (int)$instActual['entidad_id'];

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

    // Validar si el RIF/documento ya pertenece a otra entidad o institución mediante el modelo
    $instModel = new Institucion();
    $instModel->setDocumento($documento);
    $instModel->setEntidadId($entidad_id);
    $entidadExistente = $instModel->buscarEntidadPorDocumento();
    if ($entidadExistente) {
        $tipoDocExistente = $entidadExistente['tipo_documento'] ?? $tipo_documento;
        $docCompleto = "{$tipoDocExistente}-{$documento}";
        $nombreEntidad = $entidadExistente['nombre_completo'] ?? '';

        if (!empty($entidadExistente['rol_personal'])) {
            $rolPersonal = ucfirst($entidadExistente['rol_personal']);
            $mensaje = "El documento {$docCompleto} ya se encuentra registrado por un personal del parque: \"{$nombreEntidad}\" ({$rolPersonal}). Por favor, ingrese un número de documento diferente.";
        } elseif (!empty($entidadExistente['institucion_activa_id'])) {
            $mensaje = "El RIF/documento {$docCompleto} ya se encuentra registrado con la institución: \"{$nombreEntidad}\".";
        } else {
            $mensaje = "El documento {$docCompleto} ya se encuentra registrado en el sistema a nombre de: \"{$nombreEntidad}\". Por favor, verifique el número ingresado.";
        }

        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $mensaje,
            "field" => "documento"
        ]);
        return;
    }

    try {
        $instModel->setId($id);
        $instModel->setEntidadId($entidad_id);
        $instModel->setTipoDocumento($tipo_documento);
        $instModel->setDocumento($documento);
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
            "message" => $e->getMessage(),
            "field" => "documento"
        ]);
    } catch (Exception $e) {
        error_log("Error en actualizarInstitucionAction: " . $e->getMessage());
        $isDuplicate = (stripos($e->getMessage(), 'Duplicate entry') !== false || stripos($e->getMessage(), '1062') !== false);
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $isDuplicate 
                ? "El documento ingresado ya se encuentra registrado en el sistema. Por favor verifique el número de RIF/Cédula." 
                : "No fue posible actualizar la institución debido a un inconveniente en el servidor. Por favor intente más tarde.",
            "field" => $isDuplicate ? "documento" : null
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