<?php
// este archivo se encarga de fabricar todos los documentos en formato pdf
// dibuja las listas de estudiantes, reportes de profesores, bitacoras y constancias de estudio
require_once __DIR__ . '/../../libs/fpdf.php';

class ReportesPDF extends FPDF {
    protected $titulo_reporte = '';

    // esta funcion convierte las letras para que la libreria fpdf no las imprima con simbolos raros
    private function u($str) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str ?? '');
    }

    // aqui se dibuja la cabecera oficial de la unefa con la linea azul en la parte superior de cada hoja
    public function Header() {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 5, $this->u('REPUBLICA BOLIVARIANA DE VENEZUELA'), 0, 1, 'C');
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, $this->u('UNIVERSIDAD NACIONAL EXPERIMENTAL POLITECNICA DE LA FUERZA ARMADA'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->u('UNEFA - NUCLEO GUACARA'), 0, 1, 'C');
        $this->SetDrawColor(0, 51, 102);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 8, $this->u($this->titulo_reporte), 0, 1, 'C');
        $this->Ln(2);
    }

    // aqui se dibuja el pie de pagina con el numero de hoja abajo
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, $this->u('Pagina ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    public function setTitulo($titulo) {
        $this->titulo_reporte = $titulo;
    }

    private function th($w, $label, $size = 9) {
        $this->SetFont('Arial', 'B', $size);
        $this->SetFillColor(0, 51, 102);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($w, 7, $this->u($label), 1, 0, 'C', true);
    }

    private function td($w, $text, $size = 8, $align = 'C', $max = 50) {
        $this->SetFont('Arial', '', $size);
        $this->SetTextColor(40, 40, 40);
        $this->Cell($w, 5.5, $this->u(mb_substr($text, 0, $max, 'UTF-8')), 1, 0, $align);
    }

    private function section($label) {
        if ($this->GetY() > 210) $this->AddPage();
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(220, 230, 241);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 7, $this->u('  ' . $label), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    private function headerTableEstudiantes() {
        $this->th(7, '#');
        $this->th(68, 'NOMBRE COMPLETO', 8);
        $this->th(24, 'CEDULA');
        $this->th(18, 'TELF');
        $this->th(40, 'MATERIA', 7);
        $this->th(17, 'SECCION', 7);
        $this->th(46, 'CORREO', 7);
        $this->th(30, 'CARRERA', 7);
        $this->Ln();
    }

    public function reporteEstudiantes($conexion) {
        $this->AliasNbPages();
        $this->setTitulo('REPORTE DE ESTUDIANTES');
        $this->AddPage();

        $stmt = $conexion->query(
            "SELECT u.id, p.cedula, p.tipo_documento, p.nombre, p.segundo_nombre,
                    p.apellido, p.segundo_apellido, p.telefono, u.correo,
                    m.nombre_materia, m.semestre, s.nombre_seccion,
                    c.nombre_carrera
             FROM usuario u
             JOIN perfil p ON u.id_perfil = p.id
             LEFT JOIN carrera c ON p.id_carrera = c.id
             LEFT JOIN inscripcion_seccion ins ON ins.id_estudiante = u.id AND ins.estado = 'Inscrito'
             LEFT JOIN seccion s ON ins.id_seccion = s.id
             LEFT JOIN materia m ON s.id_materia = m.id
             WHERE u.rol = 'Estudiante' AND u.estado = 'Aprobado'
             ORDER BY m.semestre, m.nombre_materia, p.apellido, p.nombre"
        );
        $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($estudiantes)) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, $this->u('No hay estudiantes registrados.'), 0, 1, 'C');
            return $this->Output('S');
        }

        $semestre_actual = null;
        $i = 0;
        foreach ($estudiantes as $e) {
            $sem = $e['semestre'] ?? 'S/S';
            if ($sem !== $semestre_actual) {
                $semestre_actual = $sem;
                $this->section($sem !== 'S/S' ? "SEMESTRE $sem" : 'SIN ASIGNACION DE SECCION');
                $this->headerTableEstudiantes();
                $i = 0;
            }
            if ($this->GetY() > 192) { $this->AddPage(); $this->headerTableEstudiantes(); }
            $i++;
            $fill = $i % 2 === 0;
            $nombre = trim("{$e['apellido']} {$e['segundo_apellido']}, {$e['nombre']} {$e['segundo_nombre']}");
            $cedula = $e['tipo_documento'] . '-' . $e['cedula'];
            $telf = $e['telefono'] ?? '-';

            if ($fill) $this->SetFillColor(240, 245, 250);
            else $this->SetFillColor(255, 255, 255);

            $this->td(7, $e['id'], 8, 'C', 4);
            $this->td(68, $nombre, 7.5, 'L', 40);
            $this->td(24, $cedula, 8, 'C', 15);
            $this->td(18, $telf, 7, 'C', 12);
            $this->td(40, $e['nombre_materia'] ?? '-', 7, 'L', 24);
            $this->td(17, $e['nombre_seccion'] ?? '-', 7, 'C', 12);
            $this->td(46, $e['correo'], 7, 'L', 32);
            $this->td(30, $e['nombre_carrera'] ?? 'Ingenieria de Sistemas', 7, 'C', 22);
            $this->Ln();
        }
        return $this->Output('S');
    }

    private function headerTableDocentes() {
        $this->th(7, '#');
        $this->th(65, 'NOMBRE COMPLETO', 8);
        $this->th(24, 'CEDULA');
        $this->th(28, 'ROL', 7);
        $this->th(44, 'MATERIA', 7);
        $this->th(30, 'HORARIO', 7);
        $this->th(52, 'CORREO', 7);
        $this->Ln();
    }

    public function reporteDocentes($conexion) {
        $this->AliasNbPages();
        $this->setTitulo('REPORTE DE PROFESORES Y HORARIOS');
        $this->AddPage();

        $stmt = $conexion->query(
            "SELECT u.id, u.rol, p.cedula, p.tipo_documento, p.nombre, p.segundo_nombre,
                    p.apellido, p.segundo_apellido, p.telefono, u.correo,
                    m.nombre_materia, s.nombre_seccion, s.dia_semana, s.hora_inicio, s.hora_fin
             FROM usuario u
             JOIN perfil p ON u.id_perfil = p.id
             LEFT JOIN seccion s ON s.id_docente = u.id
             LEFT JOIN materia m ON s.id_materia = m.id
             WHERE u.rol IN ('Profesor', 'Docente')
             ORDER BY p.apellido, p.nombre"
        );
        $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($docentes)) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, $this->u('No hay profesores registrados.'), 0, 1, 'C');
            return $this->Output('S');
        }

        $this->headerTableDocentes();
        $i = 0;
        foreach ($docentes as $d) {
            if ($this->GetY() > 225) { $this->AddPage(); $this->headerTableDocentes(); }
            $i++;
            $fill = $i % 2 === 0;
            $nombre = trim("{$d['apellido']} {$d['segundo_apellido']}, {$d['nombre']} {$d['segundo_nombre']}");
            $cedula = $d['tipo_documento'] . '-' . $d['cedula'];
            $horario = !empty($d['dia_semana']) ? ($d['dia_semana'] . ' ' . substr($d['hora_inicio'] ?? '', 0, 5)) : '-';

            if ($fill) $this->SetFillColor(240, 245, 250);
            else $this->SetFillColor(255, 255, 255);

            $this->td(7, $d['id'], 8, 'C', 4);
            $this->td(65, $nombre, 7.5, 'L', 38);
            $this->td(24, $cedula, 8, 'C', 15);
            $this->td(28, $d['rol'], 7, 'C', 18);
            $this->td(44, $d['nombre_materia'] ?? '-', 7, 'L', 26);
            $this->td(30, $horario, 7, 'C', 18);
            $this->td(52, $d['correo'], 7, 'L', 36);
            $this->Ln();
        }
        return $this->Output('S');
    }

    private function headerTableBitacora() {
        $this->th(32, 'FECHA / HORA');
        $this->th(30, 'ROL');
        $this->th(60, 'USUARIO', 7);
        $this->th(40, 'ACCION', 8);
        $this->th(73, 'DETALLE', 7);
        $this->th(22, 'IP');
        $this->Ln();
    }

    // CONSTANCIA DE ESTUDIO
    public function constanciaEstudio($conexion, $id_usuario) {
        $this->AliasNbPages();
        $this->setTitulo('CONSTANCIA DE ESTUDIO');
        $this->AddPage('P');

        $stmt = $conexion->prepare(
            "SELECT u.correo, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido,
                    p.cedula, p.tipo_documento, p.semestre_actual, c.nombre_carrera
             FROM usuario u
             JOIN perfil p ON u.id_perfil = p.id
             LEFT JOIN carrera c ON p.id_carrera = c.id
             WHERE u.id = :id AND u.rol = 'Estudiante' AND u.estado = 'Aprobado'
             LIMIT 1"
        );
        $stmt->execute([':id' => $id_usuario]);
        $datos = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$datos) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, $this->u('Datos no encontrados.'), 0, 1, 'C');
            return $this->Output('S');
        }

        $this->Ln(8);
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 6, $this->u(
            'Quien suscribe, Coordinacion Academica de la Universidad Nacional Experimental Politecnica de la Fuerza Armada (UNEFA) - Nucleo Guacara, hace constar que:'
        ), 0, 'J');
        $this->Ln(4);

        $nombre_completo = trim("{$datos['nombre']} {$datos['segundo_nombre']} {$datos['apellido']} {$datos['segundo_apellido']}");
        $cedula_completa = ($datos['tipo_documento'] ?? 'V') . '-' . $datos['cedula'];
        $carrera_nombre = $datos['nombre_carrera'] ?: 'Ingenieria de Sistemas';

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, $this->u($nombre_completo), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->u("Titular de la Cedula de Identidad Nro. $cedula_completa"), 0, 1, 'C');
        $this->Ln(4);

        $this->MultiCell(0, 6, $this->u(
            "Es estudiante regular de esta casa de estudios, cursando la carrera de {$carrera_nombre} " .
            "en el " . labelSemestre($datos['semestre_actual'] ?? 1) . " semestre del Plan de Estudios vigente."
        ), 0, 'J');
        $this->Ln(4);

        $this->MultiCell(0, 6, $this->u(
            'Se expide la presente constancia a solicitud de la parte interesada, para los fines que considere pertinentes.'
        ), 0, 'J');
        $this->Ln(12);

        $this->Cell(0, 6, $this->u('Guacara, ' . date('d') . ' de ' . $this->mesEspanol(date('m')) . ' de ' . date('Y')), 0, 1, 'R');
        $this->Ln(20);
        $this->Cell(0, 6, $this->u('___________________________________'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->u('Coordinacion Academica UNEFA - Guacara'), 0, 1, 'C');

        return $this->Output('S');
    }

    // CONSTANCIA DE INSCRIPCION Y HORARIO
    public function constanciaInscripcion($conexion, $id_usuario) {
        $this->AliasNbPages();
        $this->setTitulo('CONSTANCIA DE INSCRIPCION Y HORARIO');
        $this->AddPage('P');

        $stmt = $conexion->prepare(
            "SELECT u.correo, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido,
                    p.cedula, p.tipo_documento, p.semestre_actual, c.nombre_carrera
             FROM usuario u
             JOIN perfil p ON u.id_perfil = p.id
             LEFT JOIN carrera c ON p.id_carrera = c.id
             WHERE u.id = :id AND u.rol = 'Estudiante' AND u.estado = 'Aprobado'
             LIMIT 1"
        );
        $stmt->execute([':id' => $id_usuario]);
        $datos = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$datos) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, $this->u('Datos no encontrados.'), 0, 1, 'C');
            return $this->Output('S');
        }

        $stmt_mat = $conexion->prepare(
            "SELECT m.nombre_materia, m.codigo_materia, s.nombre_seccion, s.dia_semana, s.hora_inicio, s.hora_fin, s.aula, ins.estado
             FROM inscripcion_seccion ins
             JOIN seccion s ON ins.id_seccion = s.id
             JOIN materia m ON s.id_materia = m.id
             WHERE ins.id_estudiante = :id AND ins.estado = 'Inscrito'"
        );
        $stmt_mat->execute([':id' => $id_usuario]);
        $materias = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

        $nombre_completo = trim("{$datos['nombre']} {$datos['segundo_nombre']} {$datos['apellido']} {$datos['segundo_apellido']}");
        $cedula_completa = ($datos['tipo_documento'] ?? 'V') . '-' . $datos['cedula'];
        $carrera_nombre = $datos['nombre_carrera'] ?: 'Ingenieria de Sistemas';

        $this->Ln(8);
        $this->SetFont('Arial', '', 10);
        $this->MultiCell(0, 6, $this->u(
            'Por medio de la presente, se deja constancia que el (la) ciudadano(a):'
        ), 0, 'J');
        $this->Ln(3);

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, $this->u($nombre_completo), 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->u("C.I. Nro. $cedula_completa"), 0, 1, 'C');
        $this->Ln(3);

        $this->Cell(0, 6, $this->u("Carrera: {$carrera_nombre}"), 0, 1, 'L');
        $this->Cell(0, 6, $this->u('Semestre: ' . labelSemestre($datos['semestre_actual'] ?? 1)), 0, 1, 'L');
        $this->Ln(3);
        $this->Cell(0, 6, $this->u('Se encuentra asignado(a) al siguiente horario de secciones:'), 0, 1, 'L');
        $this->Ln(2);

        $this->th(10, 'Nro', 9);
        $this->th(65, 'MATERIA', 9);
        $this->th(25, 'CODIGO', 9);
        $this->th(30, 'SECCION', 9);
        $this->th(35, 'HORARIO', 9);
        $this->th(25, 'AULA', 9);
        $this->Ln();

        $i = 0;
        foreach ($materias as $m) {
            $i++;
            if ($this->GetY() > 235) $this->AddPage();
            $fill = $i % 2 === 0;
            if ($fill) $this->SetFillColor(240, 245, 250);
            else $this->SetFillColor(255, 255, 255);
            $horario_str = ($m['dia_semana'] ?? '-') . ' ' . substr($m['hora_inicio'] ?? '', 0, 5);
            $this->td(10, (string)$i, 9, 'C', 3);
            $this->td(65, $m['nombre_materia'], 8, 'L', 40);
            $this->td(25, $m['codigo_materia'], 8, 'C', 12);
            $this->td(30, $m['nombre_seccion'], 8, 'C', 15);
            $this->td(35, $horario_str, 8, 'C', 18);
            $this->td(25, $m['aula'] ?? '-', 8, 'C', 12);
            $this->Ln();
        }

        $this->Ln(10);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->u('Guacara, ' . date('d') . ' de ' . $this->mesEspanol(date('m')) . ' de ' . date('Y')), 0, 1, 'R');
        $this->Ln(20);
        $this->Cell(0, 6, $this->u('___________________________________'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->u('Coordinacion Academica UNEFA - Guacara'), 0, 1, 'C');

        return $this->Output('S');
    }

    private function mesEspanol($mes) {
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $meses[intval($mes)] ?? $mes;
    }

    public function reporteAuditoriaAspirantes($conexion) {
        $this->AliasNbPages();
        $this->setTitulo('AUDITORIA: HISTORIAL DE ASPIRANTES VALIDADOS Y RECHAZADOS');
        $this->AddPage();

        $stmt = $conexion->query(
            "SELECT b.fecha_hora, b.accion, b.detalle, b.direccion_ip,
                    u.correo AS operador_correo, u.rol AS operador_rol
             FROM bitacora b
             LEFT JOIN usuario u ON b.id_usuario = u.id
             WHERE b.accion LIKE '%Aspirante%' OR b.accion LIKE '%Documento%' OR b.accion LIKE '%Expediente%'
             ORDER BY b.fecha_hora DESC
             LIMIT 300"
        );
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->th(32, 'FECHA / HORA');
        $this->th(30, 'OPERADOR (ROL)');
        $this->th(50, 'OPERADOR (CORREO)');
        $this->th(40, 'ACCION');
        $this->th(95, 'DETALLE DEL EVENTO');
        $this->th(20, 'IP');
        $this->Ln();

        if (empty($registros)) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, $this->u('No hay registros de validacion o rechazo de aspirantes en bitacora.'), 0, 1, 'C');
            return $this->Output('S');
        }

        $i = 0;
        foreach ($registros as $r) {
            if ($this->GetY() > 225) {
                $this->AddPage();
                $this->th(32, 'FECHA / HORA');
                $this->th(30, 'OPERADOR (ROL)');
                $this->th(50, 'OPERADOR (CORREO)');
                $this->th(40, 'ACCION');
                $this->th(95, 'DETALLE DEL EVENTO');
                $this->th(20, 'IP');
                $this->Ln();
            }
            $i++;
            $fill = $i % 2 === 0;
            if ($fill) $this->SetFillColor(240, 245, 250);
            else $this->SetFillColor(255, 255, 255);

            $this->td(32, date('d/m/Y H:i:s', strtotime($r['fecha_hora'])), 7, 'C', 19);
            $this->td(30, $r['operador_rol'] ?? 'Control de Estudios', 7, 'C', 20);
            $this->td(50, $r['operador_correo'] ?? 'Sistema', 7, 'L', 30);
            $this->td(40, $r['accion'], 7, 'L', 26);
            $this->td(95, $r['detalle'] ?? '-', 7, 'L', 65);
            $this->td(20, $r['direccion_ip'] ?? '127.0.0.1', 7, 'C', 15);
            $this->Ln();
        }
        return $this->Output('S');
    }

    public function reporteAuditoriaAsistencias($conexion) {
        $this->AliasNbPages();
        $this->setTitulo('AUDITORIA: REGISTRO DE MARCAJE DE ASISTENCIAS (QR Y MANUAL)');
        $this->AddPage();

        $stmt = $conexion->query(
            "SELECT a.hora_registro, a.estado_asistencia, a.metodo_registro, a.observacion,
                    sc.fecha_clase, s.nombre_seccion, m.nombre_materia,
                    pest.cedula AS cedula_est, CONCAT(pest.apellido, ' ', pest.nombre) AS estudiante_nom,
                    CONCAT(pdoc.apellido, ' ', pdoc.nombre) AS docente_nom
             FROM asistencia a
             JOIN sesion_clase sc ON a.id_sesion = sc.id
             JOIN seccion s ON sc.id_seccion = s.id
             JOIN materia m ON s.id_materia = m.id
             JOIN usuario uest ON a.id_estudiante = uest.id
             JOIN perfil pest ON uest.id_perfil = pest.id
             LEFT JOIN usuario udoc ON s.id_docente = udoc.id
             LEFT JOIN perfil pdoc ON udoc.id_perfil = pdoc.id
             ORDER BY a.hora_registro DESC
             LIMIT 300"
        );
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->th(28, 'FECHA / HORA');
        $this->th(56, 'ESTUDIANTE (NOMBRE Y C.I.)');
        $this->th(50, 'MATERIA');
        $this->th(22, 'SECCION');
        $this->th(50, 'DOCENTE RESPONSABLE');
        $this->th(25, 'METODO');
        $this->th(26, 'ESTADO');
        $this->Ln();

        if (empty($registros)) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, $this->u('No hay registros de asistencia en la base de datos.'), 0, 1, 'C');
            return $this->Output('S');
        }

        $i = 0;
        foreach ($registros as $r) {
            if ($this->GetY() > 225) {
                $this->AddPage();
                $this->th(28, 'FECHA / HORA');
                $this->th(56, 'ESTUDIANTE (NOMBRE Y C.I.)');
                $this->th(50, 'MATERIA');
                $this->th(22, 'SECCION');
                $this->th(50, 'DOCENTE RESPONSABLE');
                $this->th(25, 'METODO');
                $this->th(26, 'ESTADO');
                $this->Ln();
            }
            $i++;
            $fill = $i % 2 === 0;
            if ($fill) $this->SetFillColor(240, 245, 250);
            else $this->SetFillColor(255, 255, 255);

            $estStr = trim($r['estudiante_nom']) . ' (' . $r['cedula_est'] . ')';
            $this->td(28, date('d/m/Y H:i', strtotime($r['hora_registro'])), 7, 'C', 18);
            $this->td(56, $estStr, 7, 'L', 36);
            $this->td(50, $r['nombre_materia'], 7, 'L', 32);
            $this->td(22, $r['nombre_seccion'], 7, 'C', 14);
            $this->td(50, $r['docente_nom'] ?: 'Titular No Asignado', 7, 'L', 32);
            $this->td(25, $r['metodo_registro'] === 'QR' ? 'QR (Escaner)' : 'Manual (Toggle)', 7, 'C', 16);
            $this->td(26, $r['estado_asistencia'], 7, 'C', 16);
            $this->Ln();
        }
        return $this->Output('S');
    }

    public function reporteAuditoriaCuentas($conexion) {
        $this->AliasNbPages();
        $this->setTitulo('AUDITORIA: CREACION, EDICION Y BAJAS DE CUENTAS DE PERSONAL');
        $this->AddPage();

        $stmt = $conexion->query(
            "SELECT b.fecha_hora, b.accion, b.detalle, b.direccion_ip,
                    u.correo AS operador_correo, u.rol AS operador_rol
             FROM bitacora b
             LEFT JOIN usuario u ON b.id_usuario = u.id
             WHERE b.accion LIKE '%Usuario%' OR b.accion LIKE '%Docente%' OR b.accion LIKE '%Coordinador%' OR b.accion LIKE '%Cuenta%' OR b.accion LIKE '%Perfil%' OR b.accion LIKE '%Baja%' OR b.accion LIKE '%Estado%'
             ORDER BY b.fecha_hora DESC
             LIMIT 300"
        );
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->th(32, 'FECHA / HORA');
        $this->th(32, 'OPERADOR (ROL)');
        $this->th(52, 'OPERADOR (CORREO)');
        $this->th(44, 'ACCION EJECUTADA');
        $this->th(95, 'DETALLE DEL REGISTRO');
        $this->th(22, 'DIRECCION IP');
        $this->Ln();

        if (empty($registros)) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, $this->u('No hay registros de gestion de cuentas de personal en bitacora.'), 0, 1, 'C');
            return $this->Output('S');
        }

        $i = 0;
        foreach ($registros as $r) {
            if ($this->GetY() > 225) {
                $this->AddPage();
                $this->th(32, 'FECHA / HORA');
                $this->th(32, 'OPERADOR (ROL)');
                $this->th(52, 'OPERADOR (CORREO)');
                $this->th(44, 'ACCION EJECUTADA');
                $this->th(95, 'DETALLE DEL REGISTRO');
                $this->th(22, 'DIRECCION IP');
                $this->Ln();
            }
            $i++;
            $fill = $i % 2 === 0;
            if ($fill) $this->SetFillColor(240, 245, 250);
            else $this->SetFillColor(255, 255, 255);

            $this->td(32, date('d/m/Y H:i:s', strtotime($r['fecha_hora'])), 7, 'C', 19);
            $this->td(32, $r['operador_rol'] ?? 'Administrador', 7, 'C', 20);
            $this->td(52, $r['operador_correo'] ?? 'Sistema Central', 7, 'L', 32);
            $this->td(44, $r['accion'], 7, 'L', 28);
            $this->td(95, $r['detalle'] ?? '-', 7, 'L', 65);
            $this->td(22, $r['direccion_ip'] ?? '127.0.0.1', 7, 'C', 15);
            $this->Ln();
        }
        return $this->Output('S');
    }

    public function reporteBitacora($conexion, $limite = 300) {
        $this->AliasNbPages();
        $this->setTitulo('BITACORA DE AUDITORIA (Ultimos ' . $limite . ' registros)');
        $this->AddPage();

        $stmt = $conexion->prepare(
            "SELECT b.id, b.accion, b.detalle, b.direccion_ip, b.fecha_hora,
                    u.correo, p.nombre, p.apellido, u.rol
            FROM bitacora b
            LEFT JOIN usuario u ON b.id_usuario = u.id
            LEFT JOIN perfil p ON u.id_perfil = p.id
            ORDER BY b.fecha_hora DESC
            LIMIT :lim"
        );
        $stmt->bindValue(':lim', (int)$limite, PDO::PARAM_INT);
        $stmt->execute();
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($registros)) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 10, $this->u('No hay registros de actividad.'), 0, 1, 'C');
            return $this->Output('S');
        }

        $this->headerTableBitacora();
        $i = 0;
        foreach ($registros as $r) {
            if ($this->GetY() > 225) { $this->AddPage(); $this->headerTableBitacora(); }
            $i++;
            $fill = $i % 2 === 0;
            $fecha = date('d/m/Y H:i', strtotime($r['fecha_hora']));
            $usuario = $r['correo'] ?? 'Sistema';

            if ($fill) $this->SetFillColor(240, 245, 250);
            else $this->SetFillColor(255, 255, 255);

            $this->td(32, $fecha, 7, 'C', 18);
            $this->td(30, $r['rol'] ?? '-', 7, 'C', 20);
            $this->td(60, $usuario, 7, 'L', 38);
            $this->td(40, $r['accion'], 7, 'L', 28);
            $this->td(73, $r['detalle'] ?? '-', 7, 'L', 48);
            $this->td(22, $r['direccion_ip'], 7, 'C', 15);
            $this->Ln();
        }
        return $this->Output('S');
    }
}
