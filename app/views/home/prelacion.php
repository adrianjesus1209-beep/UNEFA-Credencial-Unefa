<?php
// este archivo es el visor oficial de documentos pdf de la carrera
// muestra el mapa de materias que trancan a otras (prelaciones) y el pensum de estudios
$pdf_matriz = URLROOT . '/assets/pensum/matriz-prelacion-sistemas-diurno-2010.pdf';
$pdf_pensum = URLROOT . '/assets/pensum/pensum-sistemas-diurno-2010.pdf';
?>

<div class="container-fluid px-lg-5 py-3">
    <!-- Barra Superior Institucional y Opciones de Descarga -->
    <div class="card border-0 shadow-sm rounded-4 mb-3" style="background: linear-gradient(135deg, #002b5c 0%, #004b99 100%); color: #ffffff;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2.5 rounded-3 d-flex align-items-center justify-content-center" style="background: rgba(255,255,255,0.15); width: 48px; height: 48px;">
                        <i class="fas fa-project-diagram text-white fa-lg"></i>
                    </div>
                    <div>
                        <div class="badge bg-warning text-dark px-2.5 py-1 rounded-pill fw-bold text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            Ingeniería de Sistemas · Régimen Diurno
                        </div>
                        <h4 class="fw-bold mb-0 text-white" id="titulo-documento-pdf">Matriz de Prelación Oficial</h4>
                        <small class="text-white-50">Documento y flujograma curricular oficial de la Universidad Nacional Experimental Politécnica de la FANB</small>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <!-- Selector de Documento -->
                    <div class="btn-group p-1 rounded-pill" style="background: rgba(255,255,255,0.15);">
                        <button type="button" class="btn btn-sm btn-light rounded-pill px-3 fw-bold active text-primary" id="btn-tab-matriz" onclick="cambiarDocumentoPDF('matriz')">
                            <i class="fas fa-project-diagram me-1"></i>Matriz de Prelación
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold border-0 text-white" id="btn-tab-pensum" onclick="cambiarDocumentoPDF('pensum')">
                            <i class="fas fa-book-open me-1"></i>Pensum de Estudios
                        </button>
                    </div>

                    <!-- Botones de Acción -->
                    <a id="btn-descargar-pdf" href="<?= htmlspecialchars($pdf_matriz) ?>" download="Matriz_Prelacion_Sistemas_UNEFA.pdf" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="fas fa-download me-1 text-danger"></i>Descargar PDF
                    </a>
                    <a id="btn-abrir-nueva-pestana" href="<?= htmlspecialchars($pdf_matriz) ?>" target="_blank" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold">
                        <i class="fas fa-external-link-alt me-1"></i>Pantalla Completa
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor del Visor PDF -->
    <div class="card border-0 shadow-lg rounded-4 mb-4" style="background: #ffffff;">
        <div class="card-body p-0" style="height: 85vh; min-height: 850px;">
            <iframe id="iframe-visor-pdf"
                    src="<?= htmlspecialchars($pdf_matriz) ?>#toolbar=1&navpanes=0&scrollbar=1&view=FitH" 
                    style="width: 100%; height: 100%; border: none; display: block; zoom: 1 !important; border-top-left-radius: 1rem; border-top-right-radius: 1rem;"
                    title="Visor Oficial de Matriz de Prelación UNEFA">
            </iframe>
        </div>
        <div class="card-footer bg-light text-center py-2 border-0 small text-muted rounded-bottom-4">
            <i class="fas fa-info-circle me-1 text-primary"></i>
            Visualizando documento oficial en PDF. También puedes descargarlo o abrirlo en pantalla completa con los controles superiores.
        </div>
    </div>
</div>

<script>
const PDF_MATRIZ_URL = <?= json_encode($pdf_matriz) ?>;
const PDF_PENSUM_URL = <?= json_encode($pdf_pensum) ?>;

// esta funcion cambia el documento pdf en el visor cuando el usuario hace click en los botones superiores
function cambiarDocumentoPDF(tipo) {
    const iframe = document.getElementById('iframe-visor-pdf');
    const btnMatriz = document.getElementById('btn-tab-matriz');
    const btnPensum = document.getElementById('btn-tab-pensum');
    const btnDescargar = document.getElementById('btn-descargar-pdf');
    const btnAbrir = document.getElementById('btn-abrir-nueva-pestana');
    const titulo = document.getElementById('titulo-documento-pdf');

    if (tipo === 'matriz') {
        iframe.src = PDF_MATRIZ_URL + '#toolbar=1&navpanes=0&scrollbar=1&view=FitH';
        btnDescargar.href = PDF_MATRIZ_URL;
        btnDescargar.download = 'Matriz_Prelacion_Sistemas_UNEFA.pdf';
        btnAbrir.href = PDF_MATRIZ_URL;
        titulo.textContent = 'Matriz de Prelación Oficial';

        btnMatriz.className = 'btn btn-sm btn-light rounded-pill px-3 fw-bold active text-primary';
        btnPensum.className = 'btn btn-sm btn-outline-light rounded-pill px-3 fw-bold border-0 text-white';
    } else {
        iframe.src = PDF_PENSUM_URL + '#toolbar=1&navpanes=0&scrollbar=1&view=FitH';
        btnDescargar.href = PDF_PENSUM_URL;
        btnDescargar.download = 'Pensum_Estudios_Sistemas_UNEFA.pdf';
        btnAbrir.href = PDF_PENSUM_URL;
        titulo.textContent = 'Pensum de Estudios Oficial';

        btnPensum.className = 'btn btn-sm btn-light rounded-pill px-3 fw-bold active text-primary';
        btnMatriz.className = 'btn btn-sm btn-outline-light rounded-pill px-3 fw-bold border-0 text-white';
    }
}
</script>
