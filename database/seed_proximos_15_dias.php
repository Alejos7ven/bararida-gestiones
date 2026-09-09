<?php
/**
 * Seeder de Citas para los próximos 15 días (incluyendo hoy)
 * Compatible con entornos Local y Online (Producción)
 * 
 * Genera citas con estados diversos:
 * - Citas con guía asignado listas para "▶ Empezar Recorrido"
 * - Citas sin guía asignado para que el Admin/Super Admin las asigne en vivo
 * - Citas "en_recorrido" con temporizador de 2h activo
 * - Citas "completadas"
 * - Citas futuras para los próximos 15 días con visitantes asociados
 */

require_once __DIR__ . '/../config/Database.php';

try {
    $db = (new Database())->getConnection();
    echo "Conexión a BD exitosa.\n";

    // 1. Asegurar roles necesarios
    $stmtGuiaRol = $db->query("SELECT id FROM roles WHERE nombre = 'guia' LIMIT 1");
    $guiaRolId = $stmtGuiaRol->fetchColumn();
    if (!$guiaRolId) {
        $db->exec("INSERT INTO roles (nombre, descripcion) VALUES ('guia', 'Guía de Recorridos')");
        $guiaRolId = $db->lastInsertId();
    }

    // 2. Asegurar al menos 3 guías para enriquecer la selección
    $stmtGuias = $db->query("SELECT p.id, e.nombre_completo FROM personal p 
                             INNER JOIN roles r ON p.rol_id = r.id 
                             INNER JOIN entidades e ON p.entidad_id = e.id 
                             WHERE r.nombre = 'guia'");
    $guiasDisponibles = $stmtGuias->fetchAll(PDO::FETCH_ASSOC);

    $extraGuias = [
        ['doc' => '25441890', 'nombre' => 'Mariana Torres', 'tipo' => 'V'],
        ['doc' => '26992110', 'nombre' => 'Andrés Gómez',   'tipo' => 'V'],
        ['doc' => '27883441', 'nombre' => 'Valentina Mendoza', 'tipo' => 'V']
    ];

    $hashPassword = password_hash('123456', PASSWORD_BCRYPT);

    foreach ($extraGuias as $eg) {
        $stmtCheck = $db->prepare("SELECT id FROM entidades WHERE documento = :doc LIMIT 1");
        $stmtCheck->execute([':doc' => $eg['doc']]);
        $entId = $stmtCheck->fetchColumn();

        if (!$entId) {
            $stmtEnt = $db->prepare("INSERT INTO entidades (tipo_documento, documento, nombre_completo, tipo_entidad) 
                                     VALUES (:tipo, :doc, :nombre, 'persona')");
            $stmtEnt->execute([':tipo' => $eg['tipo'], ':doc' => $eg['doc'], ':nombre' => $eg['nombre']]);
            $entId = $db->lastInsertId();

            $stmtPers = $db->prepare("INSERT INTO personal (entidad_id, rol_id, password) VALUES (:ent_id, :rol_id, :pass)");
            $stmtPers->execute([':ent_id' => $entId, ':rol_id' => $guiaRolId, ':pass' => $hashPassword]);
            echo "Guía creado: {$eg['nombre']} (V-{$eg['doc']})\n";
        }
    }

    // Recargar guías disponibles
    $stmtGuias = $db->query("SELECT p.id, e.nombre_completo FROM personal p 
                             INNER JOIN roles r ON p.rol_id = r.id 
                             INNER JOIN entidades e ON p.entidad_id = e.id 
                             WHERE r.nombre = 'guia'");
    $guiasDisponibles = $stmtGuias->fetchAll(PDO::FETCH_ASSOC);
    echo "Total Guías disponibles: " . count($guiasDisponibles) . "\n";

    // 3. Obtener instituciones y sedes
    $stmtInst = $db->query("SELECT i.id AS institucion_id, s.id AS sede_id 
                            FROM instituciones i 
                            LEFT JOIN sedes s ON s.institucion_id = i.id 
                            ORDER BY i.id ASC");
    $instituciones = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

    if (empty($instituciones)) {
        throw new Exception("No hay instituciones registradas en la base de datos.");
    }

    // 4. Obtener recorridos
    $recorridos = $db->query("SELECT id, nombre FROM recorrido")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($recorridos)) {
        // Crear al menos 2 recorridos
        $db->exec("INSERT INTO recorrido (nombre, descripcion) VALUES ('Recorrido Botánico y Fauna', 'Ruta completa por áreas verdes y zoológico')");
        $db->exec("INSERT INTO recorrido (nombre, descripcion) VALUES ('Ruta Ecológica Infantil', 'Visita didáctica interactiva')");
        $recorridos = $db->query("SELECT id, nombre FROM recorrido")->fetchAll(PDO::FETCH_ASSOC);
    }
    $recorridosIds = array_column($recorridos, 'id');

    // 5. Asegurar visitantes para vincular
    $totalVisitantes = (int)$db->query("SELECT COUNT(*) FROM visitantes")->fetchColumn();
    if ($totalVisitantes < 30) {
        $nombresDemo = [
            ['Juan', 'Pérez', '2005-04-12'], ['María', 'González', '2008-07-23'],
            ['Carlos', 'Rodríguez', '2010-02-15'], ['Ana', 'Martínez', '2003-11-30'],
            ['Luis', 'Hernández', '2012-05-18'], ['Sofía', 'López', '2014-09-08'],
            ['Diego', 'Sánchez', '2007-03-22'], ['Camila', 'Ramírez', '2011-08-14'],
            ['Alejandro', 'Torres', '2004-06-19'], ['Valeria', 'Flores', '2009-12-05'],
            ['Gabriel', 'Benítez', '2006-01-28'], ['Daniela', 'Castro', '2013-10-11'],
            ['Mateo', 'Morales', '2015-04-03'], ['Lucía', 'Ortiz', '2002-09-17'],
            ['Santiago', 'Gutiérrez', '2008-11-25'], ['Elena', 'Navarro', '2010-07-07']
        ];
        $stmtInsVis = $db->prepare("INSERT INTO visitantes (cedula, nombres, apellidos, fecha_nacimiento) 
                                    VALUES (:cedula, :nombres, :apellidos, :fnac)");
        $baseCed = 31000000;
        foreach ($nombresDemo as $nd) {
            $baseCed++;
            try {
                $stmtInsVis->execute([
                    ':cedula' => (string)$baseCed,
                    ':nombres' => $nd[0],
                    ':apellidos' => $nd[1],
                    ':fnac' => $nd[2]
                ]);
            } catch (Exception $e) {}
        }
    }
    $visitantesIds = $db->query("SELECT id FROM visitantes")->fetchAll(PDO::FETCH_COLUMN);

    // 6. Fechas a poblar: desde 2026-09-08 hasta 2026-09-24 (17 días que cubren hoy local y hoy remote)
    $startDate = new DateTime('2026-09-08');
    $endDate   = new DateTime('2026-09-24');

    // Limpiar citas previas en ese rango respetando las FK
    $stmtGetIds = $db->query("SELECT id FROM citas WHERE fecha_visita >= '2026-09-08' AND fecha_visita <= '2026-09-24'");
    $idsToDelete = $stmtGetIds->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($idsToDelete)) {
        $inPlaceholders = implode(',', array_fill(0, count($idsToDelete), '?'));
        $stmtDelAsis = $db->prepare("DELETE FROM asistencia_cita WHERE cita_id IN ($inPlaceholders)");
        $stmtDelAsis->execute($idsToDelete);

        $stmtDelGuia = $db->prepare("DELETE FROM guia_asignado WHERE cita_id IN ($inPlaceholders)");
        $stmtDelGuia->execute($idsToDelete);

        $stmtDelCitas = $db->prepare("DELETE FROM citas WHERE id IN ($inPlaceholders)");
        $stmtDelCitas->execute($idsToDelete);
        echo "Citas previas (" . count($idsToDelete) . ") y sus relaciones limpiadas para regeneración fresca.\n";
    }

    $stmtInsertCita = $db->prepare("
        INSERT INTO citas (
            institucion_id, sede_id, recorrido_id, 
            fecha_solicitud, fecha_visita, hora_inicio, 
            estatus, metodo_pago, pago_estado
        ) VALUES (
            :institucion_id, :sede_id, :recorrido_id, 
            :fecha_solicitud, :fecha_visita, :hora_inicio, 
            :estatus, :metodo_pago, :pago_estado
        )
    ");

    $stmtAsigGuia = $db->prepare("
        INSERT INTO guia_asignado (cita_id, personal_id, rol_guia) 
        VALUES (:cita_id, :personal_id, :rol_guia)
    ");

    $stmtLinkVis = $db->prepare("
        INSERT INTO asistencia_cita (cita_id, visitante_id) 
        VALUES (:cita_id, :visitante_id)
    ");

    $currentDate = clone $startDate;
    $totalCitasCreadas = 0;
    $instCount = count($instituciones);
    $instIdx = 0;

    $metodosPago = ['transferencia', 'punto_de_venta', 'efectivo', 'pago_movil'];

    while ($currentDate <= $endDate) {
        $fechaStr = $currentDate->format('Y-m-d');
        $fechaSolicitud = (clone $currentDate)->modify('-5 days')->format('Y-m-d');

        // Determinar si es hoy (2026-09-08 o 2026-09-09)
        $esDiaHoy = ($fechaStr === '2026-09-08' || $fechaStr === '2026-09-09');

        if ($esDiaHoy) {
            // Generar 4 citas con escenarios clave:
            // 1. Lista para empezar (guía asignado)
            // 2. Sin guía asignado (para que el admin asigne)
            // 3. En recorrido (iniciado hace 20 minutos según el reloj de MySQL, temporizador activo de ~1h 40m)
            // 4. Completada

            $horaRecorridoIniciado = $db->query("SELECT SUBTIME(CURTIME(), '00:20:00')")->fetchColumn();

            $citasDelDia = [
                [
                    'hora' => '08:30:00',
                    'estatus' => 'aprobado',
                    'asignar_guia' => true,
                    'guia_idx' => 0,
                    'metodo_pago' => 'transferencia',
                    'pago_estado' => 'pagado'
                ],
                [
                    'hora' => '10:00:00',
                    'estatus' => 'pendiente',
                    'asignar_guia' => false,
                    'guia_idx' => null,
                    'metodo_pago' => 'punto_de_venta',
                    'pago_estado' => 'pendiente'
                ],
                [
                    'hora' => $horaRecorridoIniciado,
                    'estatus' => 'en_recorrido',
                    'asignar_guia' => true,
                    'guia_idx' => 1 % count($guiasDisponibles),
                    'metodo_pago' => 'pago_movil',
                    'pago_estado' => 'pagado'
                ],
                [
                    'hora' => '14:00:00',
                    'estatus' => 'completado',
                    'asignar_guia' => true,
                    'guia_idx' => 0,
                    'metodo_pago' => 'transferencia',
                    'pago_estado' => 'pagado'
                ]
            ];
        } else {
            // Días siguientes: 2 a 3 citas por día
            $numCitas = rand(2, 3);
            $horasPosibles = ['08:30:00', '10:00:00', '11:30:00', '14:00:00', '15:15:00'];
            shuffle($horasPosibles);

            $citasDelDia = [];
            for ($k = 0; $k < $numCitas; $k++) {
                $tieneGuia = (rand(1, 10) <= 7); // 70% tienen guía asignado
                $citasDelDia[] = [
                    'hora' => $horasPosibles[$k],
                    'estatus' => (rand(1, 10) <= 6 ? 'aprobado' : 'pendiente'),
                    'asignar_guia' => $tieneGuia,
                    'guia_idx' => $tieneGuia ? (rand(0, count($guiasDisponibles) - 1)) : null,
                    'metodo_pago' => $metodosPago[array_rand($metodosPago)],
                    'pago_estado' => (rand(1, 10) <= 7 ? 'pagado' : 'pendiente')
                ];
            }
        }

        foreach ($citasDelDia as $item) {
            $inst = $instituciones[$instIdx % $instCount];
            $instIdx++;

            $recId = $recorridosIds[array_rand($recorridosIds)];

            $stmtInsertCita->execute([
                ':institucion_id' => $inst['institucion_id'],
                ':sede_id'        => $inst['sede_id'] ?: null,
                ':recorrido_id'   => $recId,
                ':fecha_solicitud'=> $fechaSolicitud,
                ':fecha_visita'   => $fechaStr,
                ':hora_inicio'    => $item['hora'],
                ':estatus'        => $item['estatus'],
                ':metodo_pago'    => $item['metodo_pago'],
                ':pago_estado'    => $item['pago_estado']
            ]);

            $citaId = $db->lastInsertId();
            $totalCitasCreadas++;

            // Asignar guía si aplica
            if ($item['asignar_guia'] && $item['guia_idx'] !== null && isset($guiasDisponibles[$item['guia_idx']])) {
                $guia = $guiasDisponibles[$item['guia_idx']];
                $stmtAsigGuia->execute([
                    ':cita_id'     => $citaId,
                    ':personal_id' => $guia['id'],
                    ':rol_guia'    => 'principal'
                ]);

                // 30% de probabilidad de tener un segundo guía de apoyo
                if (count($guiasDisponibles) > 1 && rand(1, 10) <= 3) {
                    $apoyoIdx = ($item['guia_idx'] + 1) % count($guiasDisponibles);
                    $stmtAsigGuia->execute([
                        ':cita_id'     => $citaId,
                        ':personal_id' => $guiasDisponibles[$apoyoIdx]['id'],
                        ':rol_guia'    => 'apoyo'
                    ]);
                }
            }

            // Asociar entre 5 y 12 visitantes aleatorios
            if (!empty($visitantesIds)) {
                $numVis = rand(5, min(12, count($visitantesIds)));
                $visKeys = array_rand($visitantesIds, $numVis);
                if (!is_array($visKeys)) $visKeys = [$visKeys];

                foreach ($visKeys as $vk) {
                    $stmtLinkVis->execute([
                        ':cita_id'      => $citaId,
                        ':visitante_id' => $visitantesIds[$vk]
                    ]);
                }
            }
        }

        $currentDate->modify('+1 day');
    }

    echo "\n=== PROCESO COMPLETADO EXITOSAMENTE ===\n";
    echo "Total de citas creadas para los próximos 15 días: {$totalCitasCreadas}\n";
    echo "Rango de fechas poblado: {$startDate->format('Y-m-d')} hasta {$endDate->format('Y-m-d')}\n";

} catch (Exception $e) {
    echo "ERROR en seeder: " . $e->getMessage() . "\n";
    exit(1);
}
