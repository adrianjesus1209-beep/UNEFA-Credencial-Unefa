<?php
// este archivo es la portada o pagina principal de bienvenida del sitio web
// muestra el anuncio principal, los enlaces a las gacetas y pensum, y los proceres patrios
?>
    <div class="container px-3 px-lg-0 mb-5">
        <div class="contenedor-banner-principal d-flex align-items-center justify-content-center text-center fluido-entrada">
            <div class="text-white p-5 contenedor-texto-banner">
                <span class="badge bg-danger rounded-pill px-4 py-2 mb-3 fs-6 shadow fluido-entrada retraso-1">27 Aniversario</span>
                <h1 class="display-3 fw-bold mb-3 text-shadow fluido-entrada retraso-2">Educacion de Excelencia</h1>
                <p class="fs-4 fw-light shadow-sm fluido-entrada retraso-3">Formando profesionales para la construccion de una nueva sociedad.</p>
                <div class="mt-4 fluido-entrada retraso-4">
                    <a href="index.php?page=login" class="btn btn-light btn-lg px-5 py-3 rounded-pill fw-bold shadow">Inscripciones</a>
                </div>
            </div>
        </div>
    </div>

    <div class="relleno-seccion fluido-entrada retraso-2">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="insignia-seccion">Documentos Academicos</span>
                    <h2 class="titulo-seccion mb-4 text-shadow-sm">Recursos y Contenidos</h2>
                    <p class="lead text-muted mb-5">Descarga los documentos academicos oficiales de la carrera de Ingenieria de Sistemas.</p>
                    
                    <a href="<?php echo URLROOT; ?>/assets/gacetas/GACETA_001_2026.pdf?v=2" target="_blank" class="text-decoration-none transition-hover">
                        <div class="d-flex align-items-start gap-4 mb-4">
                            <div class="flex-shrink-0 bg-white p-3 rounded-4 shadow-sm transicion-suave border-light">
                                <i class="fas fa-file-pdf fs-2 text-danger"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 text-dark">Gaceta Universitaria #001-2026</h5>
                                <p class="small text-muted mb-0">Formato PDF - Documento Oficial</p>
                            </div>
                        </div>
                    </a>

                    <a href="<?php echo URLROOT; ?>/assets/pensum/pensum-sistemas-diurno-2010.pdf?v=2" target="_blank" class="text-decoration-none transition-hover">
                        <div class="d-flex align-items-start gap-4 mb-4">
                            <div class="flex-shrink-0 bg-white p-3 rounded-4 shadow-sm transicion-suave border-light">
                                <i class="fas fa-book-open fs-2 text-primary"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 text-dark">Pensum Ingeniería de Sistemas</h5>
                                <p class="small text-muted mb-0">Formato PDF - Plan de Estudio Diurno</p>
                            </div>
                        </div>
                    </a>

                    <a href="<?php echo URLROOT; ?>/assets/pensum/matriz-prelacion-sistemas-diurno-2010.pdf?v=2" target="_blank" class="text-decoration-none transition-hover">
                        <div class="d-flex align-items-start gap-4 mb-4">
                            <div class="flex-shrink-0 bg-white p-3 rounded-4 shadow-sm transicion-suave border-light">
                                <i class="fas fa-project-diagram fs-2 text-warning"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 text-dark">Matriz de Prelación</h5>
                                <p class="small text-muted mb-0">Formato PDF - Relacion de Asignaturas</p>
                            </div>
                        </div>
                    </a>

                    <div class="d-flex gap-2 mt-4 flex-wrap">
                        <a href="http://www.unefa.edu.ve/portal/plantillainter.php" target="_blank" class="btn btn-unefa-rojo">Ver Gacetas</a>
                        <a href="https://unefazuliaingsistemas.wordpress.com/la-ingenieria-de-sistemas/contenidos-programaticos/" target="_blank" class="btn btn-unefa-azul">Ver Pensum</a>
                        <a href="index.php?page=prelacion" class="btn btn-unefa-naranja">Ver Matriz</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="tarjeta-noticia transicion-suave shadow-hover">
                        <img src="<?php echo URLROOT; ?>/assets/img/noticias/noticia_principal.png" class="imagen-noticia" alt="UNEFA Noticia">
                        <div class="capa-superpuesta-noticia">
                            <span class="badge bg-danger mb-3">DESTACADO</span>
                            <h3 class="fw-bold mb-0">Impulsando la Innovacion Tecnologica en el pais</h3>
                            </div>
                        </div>
                    </a>
    </div>

    <div class="relleno-seccion seccion-relleno-formato-arriba fluido-entrada retraso-3">
        <div class="container text-center">
            <span class="insignia-seccion mb-3">Identidad</span>
            <h2 class="titulo-seccion">Heroes de la Patria</h2>
            
            <div class="d-flex flex-wrap flex-lg-nowrap justify-content-center gap-2 gap-lg-3 py-2 px-2">
                <div class="text-center heroe-item">
                    <a href="index.php?id=bolivar" class="text-decoration-none transition-hover">
                        <div class="envoltorio-avatar-heroe mb-3">
                            <img src="<?php echo URLROOT; ?>/assets/img/heroes/simon_bolivar.png" class="avatar-heroe" alt="Simon Bolivar">
                        </div>
                        <h6 class="fw-bold text-dark mb-0">Simon Bolivar</h6>
                    </a>
                </div>
                
                <div class="text-center heroe-item">
                    <a href="index.php?id=miranda" class="text-decoration-none transition-hover">
                        <div class="envoltorio-avatar-heroe mb-3">
                            <img src="<?php echo URLROOT; ?>/assets/img/heroes/francisco_de_miranda.png" class="avatar-heroe" alt="Francisco de Miranda">
                        </div>
                        <h6 class="fw-bold text-dark mb-0">Francisco de Miranda</h6>
                    </a>
                </div>
                
                <div class="text-center heroe-item">
                    <a href="index.php?id=sucre" class="text-decoration-none transition-hover">
                        <div class="envoltorio-avatar-heroe mb-3">
                            <img src="<?php echo URLROOT; ?>/assets/img/heroes/antonio_jose_de_sucre.png" class="avatar-heroe" alt="Antonio Jose de Sucre">
                        </div>
                        <h6 class="fw-bold text-dark mb-0">Antonio Jose de Sucre</h6>
                    </a>
                </div>
                
                <div class="text-center heroe-item">
                    <a href="index.php?id=urdaneta" class="text-decoration-none transition-hover">
                        <div class="envoltorio-avatar-heroe mb-3">
                            <img src="<?php echo URLROOT; ?>/assets/img/heroes/rafael_urdaneta.png" class="avatar-heroe" alt="Rafael Urdaneta">
                        </div>
                        <h6 class="fw-bold text-dark mb-0">Rafael Urdaneta</h6>
                    </a>
                </div>
                
                <div class="text-center heroe-item">
                    <a href="index.php?id=zamora" class="text-decoration-none transition-hover">
                        <div class="envoltorio-avatar-heroe mb-3">
                            <img src="<?php echo URLROOT; ?>/assets/img/heroes/ezequiel_zamora.png" class="avatar-heroe" alt="Ezequiel Zamora">
                        </div>
                        <h6 class="fw-bold text-dark mb-0">Ezequiel Zamora</h6>
                    </a>
                </div>

                <div class="text-center heroe-item">
                    <a href="index.php?id=chavez" class="text-decoration-none transition-hover">
                        <div class="envoltorio-avatar-heroe mb-3">
                            <img src="<?php echo URLROOT; ?>/assets/img/heroes/hugo_chavez.png" class="avatar-heroe" alt="Hugo Chavez">
                        </div>
                        <h6 class="fw-bold text-dark mb-0">Hugo Chavez</h6>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="relleno-seccion bg-white seccion-relleno-formato-abajo fluido-entrada retraso-4">
        <div class="container multimedia-wrapper">
            <div class="text-center mb-5">
                <span class="insignia-seccion mb-3">Multimedia</span>
                <h2 class="titulo-seccion">Audiovisual</h2>
            </div>

            <input type="radio" name="playlist" id="v1" checked hidden>
            <input type="radio" name="playlist" id="v2" hidden>
            <input type="radio" name="playlist" id="v3" hidden>

            <div class="row g-4 align-items-stretch">
                <div class="col-lg-7 order-1 order-lg-1">
                    <div class="slot-video slot-1">
                        <video controls playsinline class="slot-video-media">
                            <source src="<?php echo URLROOT; ?>/assets/video/27_aniversario.mp4" type="video/mp4">
                        </video>
                    </div>
                    <div class="slot-video slot-2">
                        <video controls playsinline class="slot-video-media">
                            <source src="<?php echo URLROOT; ?>/assets/video/pilares_rostros.mp4" type="video/mp4">
                        </video>
                    </div>
                    <div class="slot-video slot-3">
                        <video controls playsinline class="slot-video-media">
                            <source src="<?php echo URLROOT; ?>/assets/video/protocolo_aula.mp4" type="video/mp4">
                        </video>
                    </div>
                </div>

                <div class="col-lg-5 order-2 order-lg-2 ps-lg-4 lista-video-contenedor">
                    <label for="v1" class="elemento-lista-video" data-video="v1">
                        <div class="miniatura-wrapper">
                            <img src="<?php echo URLROOT; ?>/assets/img/banners/banner_principal.png" class="miniatura-lista-video" alt="Miniatura 1">
                            <span class="indicador-play"><i class="fas fa-play"></i></span>
                        </div>
                        <div class="info-video">
                            <span class="badge bg-primary mb-1 indicador-activo">Reproduciendo</span>
                            <h6 class="fw-bold mb-1 titulo-video">27 ANIVERSARIO UNEFA</h6>
                            <small class="text-muted d-flex align-items-center gap-1"><i class="fas fa-play-circle text-danger"></i> Excelencia Educativa</small>
                        </div>
                    </label>

                    <label for="v2" class="elemento-lista-video" data-video="v2">
                        <div class="miniatura-wrapper">
                            <img src="<?php echo URLROOT; ?>/assets/img/banners/banner_secundario.jpg" class="miniatura-lista-video" alt="Miniatura 2">
                            <span class="indicador-play"><i class="fas fa-play"></i></span>
                        </div>
                        <div class="info-video">
                            <span class="badge bg-primary mb-1 indicador-activo">Reproduciendo</span>
                            <h6 class="fw-bold mb-1 titulo-video">UNEFA: Pilares y Rostros</h6>
                            <small class="text-muted d-flex align-items-center gap-1"><i class="far fa-clock"></i> Hace 2 dias</small>
                        </div>
                    </label>

                    <label for="v3" class="elemento-lista-video" data-video="v3">
                        <div class="miniatura-wrapper">
                            <img src="<?php echo URLROOT; ?>/assets/img/noticias/noticia_principal.png" class="miniatura-lista-video" alt="Miniatura 3">
                            <span class="indicador-play"><i class="fas fa-play"></i></span>
                        </div>
                        <div class="info-video">
                            <span class="badge bg-primary mb-1 indicador-activo">Reproduciendo</span>
                            <h6 class="fw-bold mb-1 titulo-video">Protocolo de Aula UNEFA</h6>
                            <small class="text-muted d-flex align-items-center gap-1"><i class="far fa-clock"></i> Hace 1 semana</small>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="seccion-enlaces-externos">
        <div class="container">
            <div class="text-center mb-5">
                <span class="insignia-seccion mb-3">Conexiones</span>
                <h2 class="titulo-seccion">Enlaces Externos</h2>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://cneh.gob.ve/" target="_blank" class="enlace-horizontal">
                        <img src="<?php echo URLROOT; ?>/assets/img/enlaces_externos/cnh.jpg" alt="CNH" class="logo-enlace-h">
                        <div>
                            <h6 class="titulo-enlace-h">Centro Nacional de Historia</h6>
                            <small class="subtitulo-enlace-h">cneh.gob.ve</small>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://ceofanb.mil.ve/" target="_blank" class="enlace-horizontal">
                        <img src="<?php echo URLROOT; ?>/assets/img/enlaces_externos/ceofanb.jpg" alt="CEOFANB" class="logo-enlace-h">
                        <div>
                            <h6 class="titulo-enlace-h">CEOFANB</h6>
                            <small class="subtitulo-enlace-h">ceofanb.mil.ve</small>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://www.mindefensa.gob.ve/" target="_blank" class="enlace-horizontal">
                        <img src="<?php echo URLROOT; ?>/assets/img/enlaces_externos/mindefensa.jpg" alt="MINDEFENSA" class="logo-enlace-h">
                        <div>
                            <h6 class="titulo-enlace-h">Min. Defensa</h6>
                            <small class="subtitulo-enlace-h">mindefensa.gob.ve</small>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://aviacion.mil.ve/" target="_blank" class="enlace-horizontal">
                        <img src="<?php echo URLROOT; ?>/assets/img/enlaces_externos/aviacion.jpg" alt="AVIACION" class="logo-enlace-h">
                        <div>
                            <h6 class="titulo-enlace-h">Aviación Militar</h6>
                            <small class="subtitulo-enlace-h">aviacion.mil.ve</small>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://ipsfa.gob.ve/" target="_blank" class="enlace-horizontal">
                        <img src="<?php echo URLROOT; ?>/assets/img/enlaces_externos/ipsfa.jpg" alt="IPSFA" class="logo-enlace-h">
                        <div>
                            <h6 class="titulo-enlace-h">IPSFA</h6>
                            <small class="subtitulo-enlace-h">ipsfa.gob.ve</small>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://www.mincultura.gob.ve/" target="_blank" class="enlace-horizontal">
                        <img src="<?php echo URLROOT; ?>/assets/img/enlaces_externos/cultura.jpg" alt="CULTURA" class="logo-enlace-h">
                        <div>
                            <h6 class="titulo-enlace-h">Min. Cultura</h6>
                            <small class="subtitulo-enlace-h">mincultura.gob.ve</small>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://mindeporte.gob.ve/" target="_blank" class="enlace-horizontal">
                        <img src="<?php echo URLROOT; ?>/assets/img/enlaces_externos/deporte.jpg" alt="DEPORTE" class="logo-enlace-h">
                        <div>
                            <h6 class="titulo-enlace-h">Min. Deporte</h6>
                            <small class="subtitulo-enlace-h">mindeporte.gob.ve</small>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://sni.opsu.gob.ve/" target="_blank" class="enlace-horizontal">
                        <img src="<?php echo URLROOT; ?>/assets/img/enlaces_externos/opsu.jpg" alt="OPSU" class="logo-enlace-h">
                        <div>
                            <h6 class="titulo-enlace-h">OPSU</h6>
                            <small class="subtitulo-enlace-h">opsu.gob.ve</small>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <a href="https://mincyt.gob.ve/" target="_blank" class="enlace-horizontal">
                        <img src="<?php echo URLROOT; ?>/assets/img/enlaces_externos/mppeuct.jpg" alt="MINCYT" class="logo-enlace-h">
                        <div>
                            <h6 class="titulo-enlace-h">MINCYT</h6>
                            <small class="subtitulo-enlace-h">mincyt.gob.ve</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="seccion-accesos">
        <div class="container">
            <div class="text-center mb-5">
                <span class="insignia-seccion mb-3">Recursos</span>
                <h2 class="titulo-seccion">Enlaces de Interés</h2>
            </div>
            <div class="contenedor-accesos">
                <a href="http://www.unefa.edu.ve/portal/plantillainter.php" target="_blank" class="acceso-card acceso-rojo">
                    <div class="acceso-icono"><i class="fas fa-file-alt"></i></div>
                    <div class="acceso-texto">Gaceta Universitaria</div>
                </a>
                <a href="<?php echo URLROOT; ?>/assets/documentos/CALENDARIO-ACADEMICO-2026.pdf" target="_blank" class="acceso-card acceso-amarillo">
                    <div class="acceso-icono"><i class="fas fa-calendar-check"></i></div>
                    <div class="acceso-texto">Calendario Académico</div>
                </a>
                <a href="http://www.unefa.edu.ve/portal/plantilla_boletin.php" target="_blank" class="acceso-card acceso-azul">
                    <div class="acceso-icono"><i class="fas fa-atom"></i></div>
                    <div class="acceso-texto">Boletín Informativo</div>
                </a>
                <a href="<?php echo URLROOT; ?>/assets/documentos/ALIANZAS-ESTRATEGICAS-UNASUR.pdf" target="_blank" class="acceso-card acceso-naranja">
                    <div class="acceso-icono"><i class="fas fa-users"></i></div>
                    <div class="acceso-texto">Unasur Alianzas Estratégicas</div>
                </a>
                <a href="https://drive.google.com/drive/folders/1Ch_3uMYO88bDguhjOkf_Q1OJ8Eq5HEq1" target="_blank" class="acceso-card acceso-teal">
                    <div class="acceso-icono"><i class="fas fa-laptop-code"></i></div>
                    <div class="acceso-texto">Biblioteca Virtual del VIDI</div>
                </a>
            </div>
        </div>
    </div>


