<?php
/**
 * DashboardController
 * Controlador del Dashboard — gestiona la carga de permisos RBAC desde la BD
 * para el usuario autenticado en sesión.
 * Se incluye mediante require_once desde view/dashboard.php.
 */

require_once __DIR__ . "/../config/Database.php";
require_once __DIR__ . "/../models/Permiso.php";

/**
 * Resuelve y retorna el array de permisos del usuario autenticado.
 * Si el rol_id no está en sesión (sesión antigua), lo busca en la BD por nombre de rol
 * y actualiza la sesión para las próximas peticiones.
 *
 * @param string $userRol   Nombre del rol (de la sesión)
 * @param int    $userRolId ID del rol (de la sesión, puede ser 0 si es sesión antigua)
 * @return array Array asociativo ['modulo' => bool, ...]
 */
function cargarPermisosUsuario($userRol, &$userRolId) {
    // Módulos que deben existir siempre en el array para evitar warnings
    $modulosRequeridos = ['dashboard', 'citas', 'instituciones', 'recorridos', 'visitantes', 'personal', 'app'];

    $userPermissions = [];

    try {
        $database     = new Database();
        $db           = $database->getConnection();
        $permisoModel = new Permiso($db);

        if ($userRolId > 0) {
            // Sesión actualizada: consultar directamente por rol_id
            $userPermissions = $permisoModel->getPermisosPorRol($userRolId);
        } else {
            // Sesión antigua sin rol_id: resolver el id desde la BD por nombre de rol
            $stmtRol = $db->prepare("SELECT id FROM roles WHERE nombre = :nombre LIMIT 1");
            $stmtRol->bindParam(":nombre", $userRol, PDO::PARAM_STR);
            $stmtRol->execute();
            $rolRow = $stmtRol->fetch(PDO::FETCH_ASSOC);

            if ($rolRow) {
                $userRolId = (int)$rolRow['id'];
                $_SESSION['personal']['rol_id'] = $userRolId; // Actualizar sesión
                $userPermissions = $permisoModel->getPermisosPorRol($userRolId);
            }
        }
    } catch (Exception $e) {
        error_log("Error en DashboardController::cargarPermisosUsuario(): " . $e->getMessage());
    }

    // Si el array sigue vacío (tablas no creadas o error de BD),
    // aplicar permisos por defecto basados en el nombre del rol
    if (empty($userPermissions)) {
        $fallback = [
            'superadmin' => ['dashboard'=>true,'citas'=>true,'instituciones'=>true,'recorridos'=>true,'visitantes'=>true,'personal'=>true,'app'=>true],
            'admin'      => ['dashboard'=>true,'citas'=>true,'instituciones'=>true,'recorridos'=>true,'visitantes'=>true,'personal'=>false,'app'=>false],
            'guia'       => ['dashboard'=>true,'citas'=>true,'instituciones'=>false,'recorridos'=>false,'visitantes'=>false,'personal'=>false,'app'=>false],
        ];
        $userPermissions = isset($fallback[$userRol])
            ? $fallback[$userRol]
            : array_fill_keys($modulosRequeridos, false);
    }

    // Garantizar que todas las claves siempre existan (evitar warnings de clave indefinida)
    foreach ($modulosRequeridos as $modulo) {
        if (!isset($userPermissions[$modulo])) {
            $userPermissions[$modulo] = false;
        }
    }

    return $userPermissions;
}
