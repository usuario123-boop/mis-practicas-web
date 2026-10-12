<?php
// Panel de control: indicadores, últimos documentos y actividad.

$resumen     = resumenEstatus($conexion);
$recientes   = listarDocumentos($conexion, [], 5, 0, 'fecha_desc');
$movimientos = ultimosMovimientos($conexion, 6);

// Documentos registrados en los últimos 7 días.
$semana = (int) $conexion->query(
    'SELECT COUNT(*) AS t FROM documentos WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'
)->fetch_assoc()['t'];

$indicadores = [
    ['Total de documentos', $resumen['Total'],      'bi-files',          'ico-indigo'],
    ['Recibidos',           $resumen['Recibido'],   'bi-inbox-fill',     'ico-verde'],
    ['En proceso',          $resumen['En proceso'], 'bi-hourglass-split','ico-ambar'],
    ['Atendidos',           $resumen['Atendido'],   'bi-check2-circle',  'ico-azul'],
];
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-1">Panel de control</h5>
        <small class="text-muted">Resumen de la gestión documental al <?= date('d/m/Y') ?></small>
    </div>
    <a href="index.php?vista=nuevo" class="btn btn-primary px-3 rounded-3 fw-bold">
        <i class="bi bi-plus-lg me-1"></i> Nuevo Documento
    </a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($indicadores as [$titulo, $valor, $icono, $clase]): ?>
        <div class="col-6 col-xl-3">
            <div class="kpi d-flex align-items-center gap-3">
                <div class="kpi__icono <?= $clase ?>"><i class="bi <?= $icono ?>"></i></div>
                <div>
                    <div class="kpi__valor"><?= (int) $valor ?></div>
                    <div class="kpi__texto"><?= e($titulo) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">

    <div class="col-12 col-xl-8">
        <div class="main-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Documentos recientes</h6>
                <a href="index.php?vista=documentos" class="small text-decoration-none">Ver todos &rarr;</a>
            </div>
            <?php $documentos = $recientes; require __DIR__ . '/_tabla.php'; ?>
        </div>
    </div>

    <div class="col-12 col-xl-4">

        <div class="main-card mb-3">
            <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart text-primary me-2"></i>Distribución por estatus</h6>
            <?php
            $colores = ['Recibido' => '#15803d', 'En proceso' => '#b45309', 'Atendido' => '#1d4ed8'];
            foreach ($colores as $estatus => $color):
                $cantidad   = $resumen[$estatus];
                $porcentaje = $resumen['Total'] > 0 ? round($cantidad * 100 / $resumen['Total']) : 0;
            ?>
                <div class="barra-grupo">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= e($estatus) ?></span>
                        <span class="fw-bold"><?= $cantidad ?> · <?= $porcentaje ?>%</span>
                    </div>
                    <div class="barra-pista">
                        <div class="barra-valor" style="width:<?= $porcentaje ?>%; background:<?= $color ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="border-top pt-3 mt-3 small text-muted">
                <i class="bi bi-calendar-week me-1"></i>
                <strong class="text-dark"><?= $semana ?></strong> documento<?= $semana === 1 ? '' : 's' ?> en los últimos 7 días.
            </div>
        </div>

        <div class="main-card">
            <h6 class="fw-bold mb-3"><i class="bi bi-activity text-primary me-2"></i>Actividad reciente</h6>
            <?php if (empty($movimientos)): ?>
                <p class="small text-muted mb-0">Aún no hay movimientos registrados.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($movimientos as $mov): ?>
                        <li class="d-flex gap-3 pb-3 mb-3 border-bottom">
                            <i class="bi bi-dot fs-3 text-primary lh-1"></i>
                            <div class="small">
                                <div class="fw-semibold"><?= e($mov['accion']) ?> · <?= e($mov['no_oficio']) ?></div>
                                <div class="text-muted"><?= e($mov['detalle']) ?></div>
                                <div class="text-muted" style="font-size:.75rem">
                                    <?= date('d/m/Y H:i', strtotime($mov['registrado_en'])) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div>
</div>
