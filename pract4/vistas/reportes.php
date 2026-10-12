<?php
/**
 * vistas/reportes.php
 * Estadísticas de la gestión documental y exportación a CSV.
 * Las gráficas se dibujan con CSS puro para no depender de librerías externas.
 */

$resumen     = resumenEstatus($conexion);
$porMes      = documentosPorMes($conexion, 6);
$remitentes  = remitentesFrecuentes($conexion, 6);

$maxMes = 0;
foreach ($porMes as $m) $maxMes = max($maxMes, (int) $m['total']);

$maxRem = 0;
foreach ($remitentes as $r) $maxRem = max($maxRem, (int) $r['total']);

/** Convierte "2026-10" en "Oct 2026". */
function etiquetaMes(string $periodo): string
{
    $meses = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun',
              '07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
    [$anio, $mes] = explode('-', $periodo);
    return ($meses[$mes] ?? $mes) . ' ' . $anio;
}

// Promedio de documentos por mes dentro del periodo analizado.
$promedio = count($porMes) > 0
    ? round(array_sum(array_column($porMes, 'total')) / count($porMes), 1)
    : 0;
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-bar-chart text-primary me-2"></i>Reportes</h5>
        <small class="text-muted">Indicadores de la gestión documental</small>
    </div>
    <a href="acciones/exportar.php" class="btn btn-outline-primary rounded-3 fw-bold">
        <i class="bi bi-filetype-csv me-1"></i> Exportar a CSV
    </a>
</div>

<div class="row g-3">

    <!-- ============ Documentos por mes ============ -->
    <div class="col-12 col-xl-7">
        <div class="main-card h-100">
            <h6 class="fw-bold mb-1">Documentos recibidos por mes</h6>
            <p class="small text-muted mb-4">Últimos 6 meses · promedio de <?= $promedio ?> por mes</p>

            <?php if (empty($porMes)): ?>
                <p class="text-muted small mb-0">Todavía no hay datos suficientes para generar la gráfica.</p>
            <?php else: ?>
                <?php foreach ($porMes as $mes): ?>
                    <?php $ancho = $maxMes > 0 ? round($mes['total'] * 100 / $maxMes) : 0; ?>
                    <div class="barra-grupo">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= e(etiquetaMes($mes['periodo'])) ?></span>
                            <span class="fw-bold"><?= (int) $mes['total'] ?></span>
                        </div>
                        <div class="barra-pista">
                            <div class="barra-valor" style="width:<?= $ancho ?>%; background:#2f7de1"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============ Estatus ============ -->
    <div class="col-12 col-xl-5">
        <div class="main-card h-100">
            <h6 class="fw-bold mb-1">Estado del seguimiento</h6>
            <p class="small text-muted mb-4">Total registrado: <?= $resumen['Total'] ?> documentos</p>

            <?php
            $colores = ['Recibido' => '#15803d', 'En proceso' => '#b45309', 'Atendido' => '#1d4ed8'];
            foreach ($colores as $estatus => $color):
                $cantidad   = $resumen[$estatus];
                $porcentaje = $resumen['Total'] > 0 ? round($cantidad * 100 / $resumen['Total']) : 0;
            ?>
                <div class="barra-grupo">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><span class="badge-status <?= claseEstatus($estatus) ?>"><?= e($estatus) ?></span></span>
                        <span class="fw-bold"><?= $cantidad ?> · <?= $porcentaje ?>%</span>
                    </div>
                    <div class="barra-pista">
                        <div class="barra-valor" style="width:<?= $porcentaje ?>%; background:<?= $color ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php $avance = $resumen['Total'] > 0 ? round($resumen['Atendido'] * 100 / $resumen['Total']) : 0; ?>
            <div class="border-top pt-3 mt-3">
                <div class="small text-muted mb-1">Porcentaje de atención</div>
                <div class="d-flex align-items-baseline gap-2">
                    <span class="fs-3 fw-bold text-primary"><?= $avance ?>%</span>
                    <span class="small text-muted">de los documentos ya fueron atendidos</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ Remitentes frecuentes ============ -->
    <div class="col-12">
        <div class="main-card">
            <h6 class="fw-bold mb-1">Remitentes más frecuentes</h6>
            <p class="small text-muted mb-4">Dependencias e instituciones que más documentos envían</p>

            <?php if (empty($remitentes)): ?>
                <p class="text-muted small mb-0">Sin datos.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle tabla-docs mb-0">
                        <thead>
                            <tr><th>Remitente</th><th style="width:45%">Volumen</th><th class="text-end">Documentos</th><th class="text-end">Acción</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($remitentes as $r): ?>
                                <?php $ancho = $maxRem > 0 ? round($r['total'] * 100 / $maxRem) : 0; ?>
                                <tr>
                                    <td class="small fw-semibold"><?= e($r['remitente']) ?></td>
                                    <td>
                                        <div class="barra-pista">
                                            <div class="barra-valor" style="width:<?= $ancho ?>%; background:#4338ca"></div>
                                        </div>
                                    </td>
                                    <td class="text-end small fw-bold"><?= (int) $r['total'] ?></td>
                                    <td class="text-end">
                                        <a class="small text-decoration-none"
                                           href="index.php?vista=buscar&enviado=1&remitente=<?= urlencode($r['remitente']) ?>">
                                            Ver documentos
                                        </a>
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
