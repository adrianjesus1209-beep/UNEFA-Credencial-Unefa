<?php
// este archivo es un ayudante que sirve para saber en que semana de clases estamos,
// cuando empieza y cuando termina el semestre universitario de la unefa.
class PeriodoHelper {

    // esta funcion busca en la base de datos el semestre que este marcado como Activo en este momento
    public static function getPeriodoActivo($conexion) {
        if (!$conexion) return null;
        $stmt = $conexion->query("SELECT * FROM periodo_academico WHERE estado = 'Activo' ORDER BY id DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // esta funcion calcula todos los detalles del semestre: en que semana vamos,
    // que porcentaje ha avanzado y cuantos dias faltan para que termine
    public static function getDetalleCiclo($periodo, $fechaRef = null) {
        $hoy = $fechaRef ? date('Y-m-d', strtotime($fechaRef)) : date('Y-m-d');

        if (!$periodo || empty($periodo['id'])) {
            return [
                'existe'             => false,
                'id'                 => 0,
                'nombre'             => 'Sin Ciclo Activo',
                'fecha_inicio'       => null,
                'fecha_fin'          => null,
                'semanas_duracion'   => 16,
                'total_semanas'      => 16,
                'semana_actual'      => 0,
                'fase'               => 'sin_periodo',
                'fase_label'         => 'Sin Ciclo Activo',
                'estado'             => 'Inactivo',
                'progreso'           => 0,
                'dias_totales'       => 0,
                'dias_transcurridos' => 0,
                'dias_restantes'     => 0,
                'es_vigente_hoy'     => false,
                'texto_estado'       => 'No hay ningun periodo academico activo configurado en el sistema.',
                'lista_semanas'      => []
            ];
        }

        $id          = (int)$periodo['id'];
        $nombre      = trim($periodo['nombre'] ?? 'Periodo');
        $fechaInicio = $periodo['fecha_inicio'];
        $semanasCfg  = max(1, (int)($periodo['semanas_duracion'] ?? 16));

        // calculamos la fecha de fin segun las semanas configuradas si no la escribieron
        if (!empty($periodo['fecha_fin'])) {
            $fechaFin = $periodo['fecha_fin'];
        } else {
            $diasSuma = ($semanasCfg * 7) - 1;
            $fechaFin = date('Y-m-d', strtotime("$fechaInicio + {$diasSuma} days"));
        }

        if (strtotime($fechaFin) < strtotime($fechaInicio)) {
            $fechaFin = $fechaInicio;
        }

        $tsInicio = strtotime($fechaInicio);
        $tsFin    = strtotime($fechaFin);
        $tsHoy    = strtotime($hoy);

        $diasTotales = max(1, (int)floor(($tsFin - $tsInicio) / 86400) + 1);
        $semanasCalculadas = max(1, (int)ceil($diasTotales / 7));
        $totalSemanas = max($semanasCfg, $semanasCalculadas);

        // revisamos si el ciclo todavia no empieza, si ya termino o si esta en curso
        if ($tsHoy < $tsInicio) {
            $fase         = 'por_iniciar';
            $faseLabel    = 'Por Iniciar';
            $semanaActual = 0;
            $progreso     = 0;
            $diasParaIni  = max(1, (int)floor(($tsInicio - $tsHoy) / 86400));
            $diasRest     = $diasTotales;
            $textoEstado  = "Inicia en {$diasParaIni} dia(s) (" . date('d/m/Y', $tsInicio) . ")";
            $esVigente    = false;
        } elseif ($tsHoy > $tsFin || ($periodo['estado'] ?? '') === 'Finalizado') {
            $fase         = 'finalizado';
            $faseLabel    = 'Ciclo Finalizado';
            $semanaActual = $totalSemanas;
            $progreso     = 100;
            $diasRest     = 0;
            $textoEstado  = "Finalizado el " . date('d/m/Y', $tsFin);
            $esVigente    = false;
        } else {
            $fase         = 'en_curso';
            $faseLabel    = 'En Curso';
            $diasTrans    = (int)floor(($tsHoy - $tsInicio) / 86400);
            $semanaActual = min($totalSemanas, (int)floor($diasTrans / 7) + 1);
            $progreso     = round(min(100, max(0, ($diasTrans + 1) / $diasTotales * 100)), 1);
            $diasRest     = max(0, (int)floor(($tsFin - $tsHoy) / 86400));
            $textoEstado  = "Semana {$semanaActual} de {$totalSemanas} ({$diasRest} dias restantes)";
            $esVigente    = true;
        }

        // armamos la lista semana por semana desde la primera hasta la ultima
        $listaSemanas = [];
        for ($i = 1; $i <= $totalSemanas; $i++) {
            $diasIniSem = ($i - 1) * 7;
            $diasFinSem = ($i * 7) - 1;
            $iniSemDate = date('Y-m-d', strtotime("$fechaInicio + {$diasIniSem} days"));
            $finSemDate = date('Y-m-d', strtotime("$fechaInicio + {$diasFinSem} days"));
            if ($i === $totalSemanas && strtotime($finSemDate) > $tsFin) {
                $finSemDate = $fechaFin;
            }

            $esAct = ($fase === 'en_curso' && $i === $semanaActual);
            $esPas = ($fase === 'finalizado' || ($fase === 'en_curso' && $i < $semanaActual));
            $esFut = ($fase === 'por_iniciar' || ($fase === 'en_curso' && $i > $semanaActual));

            $listaSemanas[$i] = [
                'numero'     => $i,
                'nombre'     => "Semana {$i}",
                'inicio'     => $iniSemDate,
                'fin'        => $finSemDate,
                'inicio_txt' => date('d/m/Y', strtotime($iniSemDate)),
                'fin_txt'    => date('d/m/Y', strtotime($finSemDate)),
                'es_actual'  => $esAct,
                'es_pasada'  => $esPas,
                'es_futura'  => $esFut
            ];
        }

        return [
            'existe'             => true,
            'id'                 => $id,
            'nombre'             => $nombre,
            'fecha_inicio'       => $fechaInicio,
            'fecha_fin'          => $fechaFin,
            'fecha_inicio_txt'   => date('d/m/Y', $tsInicio),
            'fecha_fin_txt'      => date('d/m/Y', $tsFin),
            'semanas_duracion'   => $semanasCfg,
            'total_semanas'      => $totalSemanas,
            'semana_actual'      => $semanaActual,
            'fase'               => $fase,
            'fase_label'         => $faseLabel,
            'estado'             => $periodo['estado'] ?? 'Activo',
            'progreso'           => $progreso,
            'dias_totales'       => $diasTotales,
            'dias_transcurridos' => $diasTrans ?? 0,
            'dias_restantes'     => $diasRest,
            'es_vigente_hoy'     => $esVigente,
            'texto_estado'       => $textoEstado,
            'lista_semanas'      => $listaSemanas
        ];
    }

    // esta funcion te dice en que semana de clases cae una fecha especifica
    public static function getSemanaDeFecha($periodo, $fecha) {
        if (!$periodo || empty($periodo['fecha_inicio'])) return 1;
        $tsIni = strtotime($periodo['fecha_inicio']);
        $tsFec = strtotime($fecha);
        if ($tsFec < $tsIni) return 0;
        $diasDiff = (int)floor(($tsFec - $tsIni) / 86400);
        $semana = (int)floor($diasDiff / 7) + 1;
        $maxSem = max(1, (int)($periodo['semanas_duracion'] ?? 16));
        return min($maxSem, $semana);
    }

    // esta funcion revisa si hoy esta permitido marcar asistencias o si el ciclo no esta activo
    public static function validarRegistroAsistencia($conexion, $fecha = null) {
        $fechaEval = $fecha ? date('Y-m-d', strtotime($fecha)) : date('Y-m-d');
        $periodo = self::getPeriodoActivo($conexion);

        if (!$periodo) {
            return [
                'permitido' => false,
                'motivo'    => 'sin_periodo',
                'mensaje'   => 'No es posible registrar asistencias: No existe un ciclo academico activo en el sistema. El Coordinador debe iniciar un ciclo oficial.',
                'periodo'   => null,
                'detalle'   => null,
                'semana'    => 0
            ];
        }

        $detalle = self::getDetalleCiclo($periodo, $fechaEval);

        if ($detalle['fase'] === 'por_iniciar') {
            return [
                'permitido' => false,
                'motivo'    => 'por_iniciar',
                'mensaje'   => "El ciclo academico «{$detalle['nombre']}» aun no ha comenzado (Inicio oficial: {$detalle['fecha_inicio_txt']}). Las asistencias se habilitaran automaticamente al iniciar la Semana 1.",
                'periodo'   => $periodo,
                'detalle'   => $detalle,
                'semana'    => 0
            ];
        }

        if ($detalle['fase'] === 'finalizado') {
            return [
                'permitido' => false,
                'motivo'    => 'finalizado',
                'mensaje'   => "El ciclo academico «{$detalle['nombre']}» concluyo el {$detalle['fecha_fin_txt']}. No se pueden asentar nuevas asistencias fuera del periodo oficial.",
                'periodo'   => $periodo,
                'detalle'   => $detalle,
                'semana'    => $detalle['total_semanas']
            ];
        }

        return [
            'permitido' => true,
            'motivo'    => 'ok',
            'mensaje'   => "Ciclo academico activo ({$detalle['texto_estado']}).",
            'periodo'   => $periodo,
            'detalle'   => $detalle,
            'semana'    => $detalle['semana_actual']
        ];
    }

    // esta funcion dibuja la tarjeta o barrita superior que muestra en que semana vamos
    public static function renderBannerCiclo($conexion, $rolUsuario, $returnUrl = 'index.php?page=dashboard') {
        $esCoordinacion = in_array(Usuario::normalizarRol($rolUsuario), ['Coordinador Academico', 'Coordinador', 'Super Administrador', 'Director'], true);
        $periodo = self::getPeriodoActivo($conexion);
        $ciclo   = self::getDetalleCiclo($periodo);
        ?>
        <div class="card border rounded-3 shadow-xs mb-3 bg-white">
            <div class="py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2 text-dark flex-wrap" style="font-size: 0.82rem;">
                    <i class="far fa-calendar-alt text-primary"></i>
                    <?php if ($ciclo['existe']): ?>
                        <span class="fw-bold">Ciclo <?= htmlspecialchars($ciclo['nombre']) ?></span>
                        <span class="text-muted">·</span>
                        <?php if ($ciclo['es_vigente_hoy']): ?>
                            <span class="fw-semibold text-success">Semana <?= $ciclo['semana_actual'] ?> de <?= $ciclo['total_semanas'] ?></span>
                        <?php elseif ($ciclo['fase'] === 'por_iniciar'): ?>
                            <span class="fw-semibold text-warning">Inicia el <?= $ciclo['fecha_inicio_txt'] ?></span>
                        <?php elseif ($ciclo['fase'] === 'finalizado'): ?>
                            <span class="fw-semibold text-muted">Finalizado el <?= $ciclo['fecha_fin_txt'] ?></span>
                        <?php endif; ?>
                        <span class="text-muted d-none d-sm-inline" style="font-size: 0.74rem;">
                            (<?= $ciclo['fecha_inicio_txt'] ?> al <?= $ciclo['fecha_fin_txt'] ?>)
                        </span>
                    <?php else: ?>
                        <span class="text-muted">Sin ciclo academico activo</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <?php if ($esCoordinacion): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-0.5 fw-semibold" 
                                style="font-size:0.72rem;" data-bs-toggle="modal" data-bs-target="#modalPersonalizarCiclo">
                            <i class="fas fa-sliders-h me-1"></i>Personalizar
                        </button>
                        <a href="index.php?page=gestion_periodos" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0.5 fw-semibold" style="font-size:0.72rem;">
                            <i class="fas fa-history me-1"></i>Historial
                        </a>
                    <?php elseif ($ciclo['existe']): ?>
                        <span class="text-muted small" style="font-size: 0.74rem;">
                            Avance: <strong><?= $ciclo['progreso'] ?>%</strong>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php 
        if ($esCoordinacion) {
            self::renderModalPersonalizarCiclo($conexion, $returnUrl);
        }
    }

    // esta funcion dibuja la ventana modal para que los coordinadores cambien las fechas del semestre
    public static function renderModalPersonalizarCiclo($conexion, $returnUrl = 'index.php?page=dashboard') {
        $periodo = self::getPeriodoActivo($conexion);
        $ciclo   = self::getDetalleCiclo($periodo);
        ?>
        <!-- ventanita modal para coordinadores -->
        <div class="modal fade" id="modalPersonalizarCiclo" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                    <div class="modal-header text-white border-0 py-3 px-4" style="background:#003366;">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width:34px;height:34px;background:rgba(255,255,255,0.15);">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <h6 class="modal-title fw-bold mb-0 text-white" style="font-size:1.05rem;">Personalizar Ciclo Academico</h6>
                                <small class="text-white-50" style="font-size:0.75rem;">Configuracion de fechas oficiales y conteo de semanas</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="index.php?action=save_ciclo_config">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="id_periodo" value="<?= (int)($ciclo['id'] ?? 0) ?>">
                        <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">

                        <div class="modal-body p-4 bg-light">
                            <div class="alert alert-info border-0 rounded-3 p-3 mb-3 small d-flex align-items-center gap-2" style="background:#e0f2fe;color:#0369a1;">
                                <i class="fas fa-info-circle fa-lg flex-shrink-0"></i>
                                <div>
                                    Establezca las fechas oficiales de inicio y fin. El sistema habilitara la toma de asistencias durante este rango y organizara el avance semana a semana.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-dark mb-1">Nombre o Codigo del Ciclo</label>
                                <input type="text" name="nombre" class="form-control form-control-sm rounded-3 shadow-none" 
                                       value="<?= htmlspecialchars($ciclo['nombre'] !== 'Sin Ciclo Activo' ? $ciclo['nombre'] : date('Y') . '-I') ?>" 
                                       placeholder="Ej: 2026-I, 2026-II, Intensivo 2026" required maxlength="50">
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-dark mb-1">Fecha de Inicio</label>
                                    <input type="date" name="fecha_inicio" id="modal_fecha_inicio" 
                                           class="form-control form-control-sm rounded-3 shadow-none" 
                                           value="<?= htmlspecialchars($ciclo['fecha_inicio'] ?: date('Y-m-d')) ?>" required onchange="calcularSemanasModal()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-dark mb-1">Fecha de Fin</label>
                                    <input type="date" name="fecha_fin" id="modal_fecha_fin" 
                                           class="form-control form-control-sm rounded-3 shadow-none" 
                                           value="<?= htmlspecialchars($ciclo['fecha_fin'] ?: date('Y-m-d', strtotime('+16 weeks'))) ?>" required onchange="calcularSemanasModal()">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-dark mb-1">Duracion Total Estimada (Semanas)</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="semanas_duracion" id="modal_semanas" 
                                           class="form-control rounded-start-3 shadow-none fw-bold" 
                                           value="<?= (int)$ciclo['total_semanas'] ?>" min="1" max="52" required onchange="ajustarFechaFinPorSemanas()">
                                    <span class="input-group-text bg-white">semanas</span>
                                </div>
                                <small class="text-muted" style="font-size:0.72rem;" id="modal_resumen_semanas">
                                    Aproximadamente <?= (int)$ciclo['total_semanas'] ?> semanas lectivas.
                                </small>
                            </div>

                            <div class="mb-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="hacer_activo" id="checkHacerActivo" value="1" checked>
                                    <label class="form-check-label fw-bold small text-dark" for="checkHacerActivo">
                                        Establecer como Ciclo Academico Activo
                                    </label>
                                </div>
                                <small class="text-muted" style="font-size:0.72rem;">
                                    Si se marca, se cerrara cualquier otro ciclo y este pasara a regir las asistencias y horarios de todo el campus.
                                </small>
                            </div>
                        </div>

                        <div class="modal-footer border-0 p-3 bg-white justify-content-between">
                            <button type="button" class="btn btn-sm btn-light rounded-pill px-3 fw-bold" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-sm rounded-pill px-4 fw-bold text-white shadow-xs" style="background:#003366;">
                                <i class="fas fa-check-circle me-1"></i>Guardar y Aplicar Ciclo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        // esta funcion calcula automaticamente cuantas semanas hay entre la fecha de inicio y la fecha de fin
        function calcularSemanasModal() {
            const fIni = document.getElementById('modal_fecha_inicio').value;
            const fFin = document.getElementById('modal_fecha_fin').value;
            if (!fIni || !fFin) return;
            const dIni = new Date(fIni);
            const dFin = new Date(fFin);
            const diffTime = dFin - dIni;
            if (diffTime < 0) return;
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)) + 1;
            const semanas = Math.max(1, Math.ceil(diffDays / 7));
            document.getElementById('modal_semanas').value = semanas;
            document.getElementById('modal_resumen_semanas').innerText = `${diffDays} dias totales = ${semanas} semanas de ciclo academico.`;
        }

        // esta funcion cambia la fecha de fin automaticamente si el usuario escribe un numero de semanas diferente
        function ajustarFechaFinPorSemanas() {
            const fIni = document.getElementById('modal_fecha_inicio').value;
            const sem = parseInt(document.getElementById('modal_semanas').value) || 16;
            if (!fIni || sem <= 0) return;
            const dIni = new Date(fIni);
            dIni.setDate(dIni.getDate() + (sem * 7) - 1);
            const yyyy = dIni.getFullYear();
            const mm = String(dIni.getMonth() + 1).padStart(2, '0');
            const dd = String(dIni.getDate()).padStart(2, '0');
            document.getElementById('modal_fecha_fin').value = `${yyyy}-${mm}-${dd}`;
            document.getElementById('modal_resumen_semanas').innerText = `${sem * 7} dias totales = ${sem} semanas de ciclo academico.`;
        }
        </script>
        <?php
    }
}
