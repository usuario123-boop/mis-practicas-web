<?php
/**
 * vistas/usuarios.php
 * Catálogo del personal que opera la plataforma (alta, activación y baja).
 */

$usuarios = $conexion->query('SELECT * FROM usuarios ORDER BY rol ASC, nombre ASC')
                     ->fetch_all(MYSQLI_ASSOC);

$roles = ['Administrador', 'Capturista', 'Consulta'];
?>

<div class="row g-3">

    <!-- ============ Alta de usuario ============ -->
    <div class="col-12 col-xl-4">
        <div class="main-card">
            <h6 class="fw-bold mb-3"><i class="bi bi-person-plus text-primary me-2"></i>Registrar usuario</h6>

            <form action="acciones/usuarios.php" method="POST">
                <?= campoCsrf() ?>
                <input type="hidden" name="operacion" value="crear">

                <div class="mb-3">
                    <label for="u-nombre" class="form-label small fw-bold">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="u-nombre" name="nombre" maxlength="120" required>
                </div>

                <div class="mb-3">
                    <label for="u-correo" class="form-label small fw-bold">Correo institucional <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="u-correo" name="correo" maxlength="150"
                           placeholder="nombre@unach.mx" required>
                </div>

                <div class="mb-3">
                    <label for="u-area" class="form-label small fw-bold">Área o dependencia</label>
                    <input type="text" class="form-control" id="u-area" name="area" maxlength="120"
                           value="ETDA - Campus IV">
                </div>

                <div class="mb-4">
                    <label for="u-rol" class="form-label small fw-bold">Rol <span class="text-danger">*</span></label>
                    <select class="form-select" id="u-rol" name="rol" required>
                        <?php foreach ($roles as $rol): ?>
                            <option value="<?= e($rol) ?>"><?= e($rol) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        Administrador: control total · Capturista: registra documentos · Consulta: solo lectura.
                    </small>
                </div>

                <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold">
                    <i class="bi bi-save me-1"></i> Guardar usuario
                </button>
            </form>
        </div>
    </div>

    <!-- ============ Listado de usuarios ============ -->
    <div class="col-12 col-xl-8">
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-people text-primary me-2"></i>Usuarios registrados</h6>
                <span class="badge bg-primary-subtle text-primary"><?= count($usuarios) ?></span>
            </div>

            <?php if (empty($usuarios)): ?>
                <p class="text-muted small mb-0">Aún no hay usuarios registrados.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle tabla-docs mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Área</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td class="small fw-semibold"><?= e($u['nombre']) ?></td>
                                    <td class="small"><?= e($u['correo']) ?></td>
                                    <td class="small"><?= e($u['area']) ?></td>
                                    <td class="small"><?= e($u['rol']) ?></td>
                                    <td>
                                        <span class="badge-status <?= $u['activo'] ? 'status-recibido' : 'status-proceso' ?>">
                                            <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <form action="acciones/usuarios.php" method="POST" class="d-inline">
                                            <?= campoCsrf() ?>
                                            <input type="hidden" name="operacion" value="alternar">
                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="btn-accion"
                                                    title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                                <i class="bi bi-toggle-<?= $u['activo'] ? 'on' : 'off' ?>"></i>
                                            </button>
                                        </form>
                                        <form action="acciones/usuarios.php" method="POST" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar a <?= e($u['nombre']) ?>?');">
                                            <?= campoCsrf() ?>
                                            <input type="hidden" name="operacion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                            <button type="submit" class="btn-accion text-danger" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
