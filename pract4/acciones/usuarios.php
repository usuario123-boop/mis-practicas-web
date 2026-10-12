<?php
/**
 * acciones/usuarios.php
 * Operaciones del catálogo de usuarios: alta, activación/desactivación y baja.
 */

require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../includes/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('../index.php?vista=usuarios');
}
validarCsrf();

$operacion = $_POST['operacion'] ?? '';

switch ($operacion) {

    // ---------------------------------------------------------------- alta --
    case 'crear':
        $nombre = trim($_POST['nombre'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $area   = trim($_POST['area'] ?? '') ?: 'ETDA - Campus IV';
        $rol    = $_POST['rol'] ?? 'Capturista';

        if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Captura un nombre y un correo electrónico válidos.');
            redirigir('../index.php?vista=usuarios');
        }
        if (!in_array($rol, ['Administrador', 'Capturista', 'Consulta'], true)) {
            $rol = 'Capturista';
        }

        $stmt = $conexion->prepare('INSERT INTO usuarios (nombre, correo, area, rol) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $nombre, $correo, $area, $rol);

        try {
            $stmt->execute();
            flash('success', 'Usuario <strong>' . e($nombre) . '</strong> registrado correctamente.');
        } catch (mysqli_sql_exception $ex) {
            // 1062 = entrada duplicada en un índice único (el correo ya existe).
            $mensaje = $ex->getCode() === 1062
                ? 'Ya existe un usuario con el correo ' . e($correo) . '.'
                : 'No fue posible registrar al usuario.';
            flash('danger', $mensaje);
        }
        $stmt->close();
        break;

    // ------------------------------------------- activar / desactivar --------
    case 'alternar':
        $id   = (int) ($_POST['id'] ?? 0);
        $stmt = $conexion->prepare('UPDATE usuarios SET activo = 1 - activo WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('success', 'El estado del usuario se actualizó.');
        break;

    // ---------------------------------------------------------------- baja --
    case 'eliminar':
        $id   = (int) ($_POST['id'] ?? 0);
        $stmt = $conexion->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        flash('success', 'El usuario se eliminó del catálogo.');
        break;

    default:
        flash('danger', 'Operación no reconocida.');
}

redirigir('../index.php?vista=usuarios');
