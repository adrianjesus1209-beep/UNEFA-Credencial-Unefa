<?php
// aqui empieza la tarjeta donde mostramos al profesor en el modulo de carnetizacion.
// primero revisamos si los datos del profesor llegaron bien desde la base de datos.
if (isset($est_data) && $est_data): 
    // esta variable sirve para saber si el profesor ya tiene foto y si esa foto de verdad esta guardada en la carpeta del servidor.
    // si tiene foto valida y fecha registrada, la variable se vuelve verdadera.
    $tieneFotoDoc = (!empty($est_data['foto_perfil']) && $est_data['foto_perfil'] !== 'default.svg' && !empty($est_data['fecha_carnetizacion']) && file_exists('public/uploads/profiles/' . $est_data['foto_perfil']));
?>
    <div class="card border-0 overflow-hidden animate__animated animate__fadeIn" style="box-shadow:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);border-radius:14px;">
        <!-- cabecera azul con el nombre del profesor y su cedula -->
        <div class="p-4" style="background:linear-gradient(135deg,#003366,#005c99);">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <div class="d-flex align-items-center justify-content-center rounded-3" style="width:40px;height:40px;background:rgba(255,255,255,0.15);">
                        <i class="fas fa-id-card text-white"></i>
                    </div>
                    <div class="min-w-0">
                        <h5 class="text-white fw-bold mb-0" style="font-size:1rem;"><?= htmlspecialchars(trim("{$est_data['nombre']} {$est_data['segundo_nombre']} {$est_data['apellido']} {$est_data['segundo_apellido']}")) ?></h5>
                        <small class="text-white opacity-75"><?= htmlspecialchars(($est_data['tipo_documento'] ?? 'V').'-'.$est_data['cedula']) ?> · <?= htmlspecialchars($est_data['correo']) ?></small>
                    </div>
                </div>
                <!-- si ya tiene foto oficial mostramos el boton para ver el carnet, sino mostramos un aviso gris de carnet bloqueado -->
                <?php if ($tieneFotoDoc): ?>
                    <a href="index.php?page=mi_carnet&id_usuario=<?= $est_data['id'] ?>" class="btn btn-sm btn-light rounded-pill px-3 fw-bold" style="color:#003366;">
                        <i class="fas fa-id-card me-1"></i>Ver Carnet Digital
                    </a>
                <?php else: ?>
                    <span class="badge rounded-pill px-3 py-2 fw-semibold" style="background:rgba(255,255,255,0.22);color:#fff;font-size:0.75rem;">
                        <i class="fas fa-lock me-1"></i>Carnet Bloqueado (Sin Foto)
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="p-4" style="background:#fafbfc;">
            <!-- si ya tiene foto guardada mostramos su fotito redonda con la fecha en que se tomo -->
            <?php if ($tieneFotoDoc): ?>
            <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded-3" style="background:#f0fdf4;">
                <img src="public/uploads/profiles/<?= htmlspecialchars($est_data['foto_perfil']) ?>"
                    class="rounded-circle border" style="width:48px;height:48px;object-fit:cover;border-color:#86efac !important;">
                <div>
                    <div style="color:#166534;font-weight:600;font-size:.9rem;">Foto registrada</div>
                    <div style="color:#6b7280;font-size:.8rem;">
                        <?= $est_data['fecha_carnetizacion'] ? 'Ultima carnetizacion: '.date('d/m/Y H:i', strtotime($est_data['fecha_carnetizacion'])) : 'N/A' ?>
                    </div>
                    <!-- este pequeno menu sirve para hacer pruebas de vencimiento como si hubieran pasado trece meses -->
                    <div class="mt-2">
                        <button class="btn btn-sm dropdown-toggle p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background:none;border:none;color:#64748b;font-size:.7rem;">
                            <i class="fas fa-magic me-1"></i>Simular escenario
                        </button>
                        <ul class="dropdown-menu border-0 shadow rounded-3" style="font-size:.8rem;">
                            <li><button onclick="simulate(EST_ID, 'total')" class="dropdown-item py-2"><i class="fas fa-calendar-times text-danger me-2"></i>Vencimiento de validez (13 meses)</button></li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- si no tiene foto todavia mostramos este cartel amarillo avisando que falta la foto -->
            <?php else: ?>
            <div class="p-3 rounded-3 mb-4 d-flex align-items-center gap-3 border border-warning" style="background:#fffbeb;">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;background:#fef3c7;">
                    <i class="fas fa-camera text-warning fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark" style="font-size:0.9rem;">Fotografia oficial pendiente</div>
                    <div class="text-muted small" style="font-size:0.8rem;">El Carnet Digital del docente permanece <strong>bloqueado</strong> hasta que captures su foto oficial o subas el archivo.</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- estas son dos pestanitas: una para tomar foto con la camara y otra para subir un archivo desde la computadora -->
            <ul class="nav nav-pills nav-justified mb-4 gap-2" style="background:#f1f5f9;padding:4px;border-radius:12px;" id="captureTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold" id="webcam-tab" data-bs-toggle="pill" data-bs-target="#tab-webcam" type="button" role="tab" onclick="stopCamera()" style="border-radius:10px;padding:8px 12px;font-size:.8rem;color:#475569;background:transparent;border:none;">
                        <i class="fas fa-camera me-1"></i>Camara
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold" id="upload-tab" data-bs-toggle="pill" data-bs-target="#tab-upload" type="button" role="tab" onclick="stopCamera()" style="border-radius:10px;padding:8px 12px;font-size:.8rem;color:#475569;background:transparent;border:none;">
                        <i class="fas fa-upload me-1"></i>Subir Archivo
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="captureTabsContent">
                <!-- pestanita de la camara -->
                <div class="tab-pane fade show active" id="tab-webcam" role="tabpanel">
                    <div class="p-3 rounded-3 mb-3 d-flex align-items-start gap-2" style="background:#f1f5f9;">
                        <i class="fas fa-info-circle" style="color:#2563eb;margin-top:2px;"></i>
                        <span style="color:#475569;font-size:.8rem;">Coloca al docente frente a un <strong>fondo blanco</strong>, centrado dentro del recuadro y circulo guia.</span>
                    </div>
                    <div class="position-relative d-flex justify-content-center">
                        <div class="position-relative" style="max-width:320px;width:100%;aspect-ratio:1;">
                            <!-- aqui se reproduce el video de la camara en vivo -->
                            <video id="video" playsinline autoplay muted class="rounded-4 bg-dark position-absolute shadow-sm" style="width:100%;height:100%;object-fit:cover;opacity:1;inset:0;z-index:0;display:none;"></video>
                            <!-- aqui se dibuja la foto cuando la congelamos -->
                            <canvas id="canvas" class="rounded-4 position-absolute shadow-sm" style="width:100%;height:100%;display:none;inset:0;z-index:2;"></canvas>
                            
                            <!-- este es el circulo azul guia que le indica a la persona donde acomodar la cara -->
                            <div id="guide-overlay" class="position-absolute" style="inset:0;pointer-events:none;display:none;border-radius:1rem;overflow:hidden;z-index:3;">
                                <svg width="100%" height="100%" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg" style="position:absolute;top:0;left:0;">
                                    <defs>
                                        <mask id="guide-mask-doc">
                                            <rect width="300" height="300" fill="white"/>
                                            <ellipse cx="150" cy="135" rx="85" ry="110" fill="black"/>
                                        </mask>
                                    </defs>
                                    <rect width="300" height="300" fill="rgba(0,0,0,0.55)" mask="url(#guide-mask-doc)"/>
                                    <ellipse cx="150" cy="135" rx="85" ry="110" fill="none" stroke="#38bdf8" stroke-width="3" stroke-dasharray="6,4"/>
                                    <line x1="135" y1="135" x2="165" y2="135" stroke="#38bdf8" stroke-width="1.5" opacity="0.7"/>
                                    <line x1="150" y1="120" x2="150" y2="150" stroke="#38bdf8" stroke-width="1.5" opacity="0.7"/>
                                    <rect x="50" y="260" width="200" height="26" rx="13" fill="rgba(0,0,0,0.7)"/>
                                    <text x="150" y="277" text-anchor="middle" fill="#ffffff" font-size="11" font-weight="bold" font-family="Arial, sans-serif">Centra el rostro en el circulo</text>
                                </svg>
                            </div>

                            <!-- este cuadro con lineas punteadas es lo que se ve antes de encender la camara -->
                            <div id="cam-placeholder" class="flex-column align-items-center justify-content-center rounded-4 position-absolute overflow-hidden p-3" style="display:flex;width:100%;height:100%;inset:0;background:#f8fafc;border:2px dashed #94a3b8;z-index:1;">
                                <div class="d-flex flex-column align-items-center justify-content-center p-3 rounded-circle shadow-xs" style="width:160px;height:190px;border:2.5px dashed #003366;background:rgba(0,51,102,0.03);">
                                    <i class="fas fa-user-circle text-primary opacity-50 mb-1" style="font-size:3.5rem;"></i>
                                    <small class="text-primary fw-bold text-center lh-1" style="font-size:0.75rem;">Circulo Guia Oficial</small>
                                </div>
                                <span class="text-muted small fw-semibold mt-2 text-center" style="font-size:0.78rem;">
                                    <i class="fas fa-camera text-primary me-1"></i>Pulsa «Activar Camara» para encuadrar aqui
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- pestanita para subir un archivo desde la computadora -->
                <div class="tab-pane fade" id="tab-upload" role="tabpanel">
                    <div class="p-4 rounded-3 d-flex flex-column align-items-center justify-content-center" id="drop-zone" style="border:2px dashed #cbd5e1;background:#f8fafc;min-height:260px;cursor:pointer;" onclick="document.getElementById('file-input').click()">
                        <div id="upload-ui" class="text-center">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-2" style="color:#94a3b8;"></i>
                            <div style="color:#475569;font-weight:600;font-size:.9rem;">Selecciona la foto</div>
                            <p style="color:#94a3b8;font-size:.8rem;">JPG o PNG · Max. 2MB</p>
                            <button class="btn btn-sm rounded-pill px-3" style="background:#003366;color:#fff;border:none;">
                                <i class="fas fa-file-image me-1"></i>Examinar
                            </button>
                        </div>
                        <img id="preview-upload" class="rounded-3 shadow-sm" style="max-width:100%;max-height:260px;display:none;object-fit:cover;">
                        <input type="file" id="file-input" class="d-none" accept="image/jpeg,image/png" onchange="handleFileUpload(this)">
                    </div>
                </div>
            </div>

            <!-- este campo escondido le pide al telefono celular que abra su camara nativa directamente -->
            <input type="file" id="camera-native-input" accept="image/*" capture="user" class="d-none" onchange="handleNativeCameraCapture(this)">

            <!-- aqui estan los botones que usa el usuario para interactuar con la camara -->
            <div class="mt-4 d-flex flex-column align-items-center gap-2">
                <!-- este boton azul grande abre directamente la camara en un telefono celular -->
                <button type="button" id="btn-native-cam" onclick="abrirCamaraNativa()" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm" style="background:#003366;border-color:#003366;">
                    <i class="fas fa-camera me-2"></i>Tomar Foto con Camara
                </button>

                <!-- este boton prende el video en vivo en la pantalla de una computadora -->
                <button type="button" id="btn-start" onclick="startCamera()" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold" style="font-size:0.78rem;">
                    <i class="fas fa-video me-1"></i>Activar Camara en Vivo
                </button>

                <!-- este cartelito verde aparece si el navegador necesita entrar con conexion segura https para dejar usar el video -->
                <div id="cam-https-switch-box" class="w-100 mt-2 text-center" style="max-width:320px;display:none;">
                    <a id="cam-btn-switch-https" href="#" class="btn btn-sm btn-success rounded-pill px-3 py-2 fw-bold w-100 shadow-sm">
                        <i class="fas fa-lock me-1"></i> Activar Camara en Vivo con HTTPS
                    </a>
                    <small class="text-muted d-block mt-1" style="font-size:0.70rem;">
                        (Al abrir, pulsa <em>Configuracion avanzada</em> y luego <em>Acceder</em> para transmitir video en vivo)
                    </small>
                </div>

                <!-- estos son los botones de accion para congelar la foto, cambiar de camara, repetir la foto si salio mal, o guardarla -->
                <div class="d-flex justify-content-center gap-2 flex-wrap mt-1">
                    <button id="btn-capture" onclick="capturePhoto()" class="btn btn-sm rounded-pill px-3 fw-semibold" style="display:none;background:#16a34a;color:#fff;border:none;">
                        <i class="fas fa-camera me-1"></i>Capturar
                    </button>
                    <button id="btn-switch" onclick="switchCamera()" class="btn btn-sm rounded-pill px-3" style="display:none;background:#e2e8f0;color:#475569;border:none;">
                        <i class="fas fa-sync-alt me-1"></i>Frontal
                    </button>
                    <button id="btn-retry" onclick="retryCamera()" class="btn btn-sm rounded-pill px-3 fw-semibold" style="display:none;background:#e2e8f0;color:#475569;border:none;">
                        <i class="fas fa-redo me-1"></i>Tomar Otra Foto
                    </button>
                    <button id="btn-save" onclick="savePhoto()" class="btn btn-sm rounded-pill px-4 py-2 fw-bold shadow-sm" style="display:none;background:#16a34a;color:#fff;border:none;">
                        <i class="fas fa-save me-1"></i>Guardar Foto Oficial
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
