<?php
// este archivo es el vigilante de la puerta de entrada
// se encarga de revisar usuarios contrasenas subir papeles y recuperar claves
class AuthController {
    // guardamos la conexion a la base de datos y las funciones de usuarios
    private $modeloUsuario;
    private $db;

    // al arrancar conectamos con el modelo de usuarios
    public function __construct($db) {
        $this->db = $db;
        require_once 'app/models/Usuario.php';
        $this->modeloUsuario = new Usuario($db);
    }

    // funcion para iniciar sesion cuando alguien escribe su correo y clave
    public function ingresar() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $token_enviado = $_POST['csrf_token'] ?? '';
            // verificamos que la peticion sea legitima y no un ataque
            if (empty($token_enviado) || !hash_equals($_SESSION['csrf_token'], $token_enviado)) {
                $_SESSION['error'] = "Error de seguridad: Token CSRF invalido.";
                header('Location: index.php?page=login');
                exit;
            }
            $correo = trim($_POST['correo'] ?? '');
            $clave = $_POST['clave'] ?? '';
            $rol_seleccionado = Usuario::normalizarRol($_POST['rol'] ?? '');

            // le preguntamos a la base de datos si la clave coincide
            $usuario = $this->modeloUsuario->iniciarSesion($correo, $clave);
            if ($usuario) {
                $rolReal = Usuario::normalizarRol($usuario['rol']);

                // si eligio un rol en la lista que no es el que tiene en el sistema le avisamos
                if ($rol_seleccionado !== '' && $rolReal !== $rol_seleccionado) {
                    // si es un aspirante que trato de entrar como estudiante le recordamos que falta aprobacion
                    if ($rolReal === 'Aspirante' && $rol_seleccionado === 'Estudiante') {
                        $_SESSION['error'] = "Tu aspiracion aun no ha sido aprobada por el Coordinador Academico. Ingresa como Aspirante para gestionar tus documentos.";
                        header('Location: index.php?page=login');
                        exit;
                    }
                    registrarIntentoFallido($this->db, 'login', $correo);
                    $_SESSION['error'] = "Acceso denegado: El perfil seleccionado ({$rol_seleccionado}) no coincide con el rol de tu cuenta ({$rolReal}).";
                    header('Location: index.php?page=login');
                    exit;
                }

                // si la cuenta fue desactivada por coordinacion no lo dejamos pasar
                if ($usuario['estado'] === 'Inactivo') {
                    registrarIntentoFallido($this->db, 'login', $correo);
                    $_SESSION['error'] = "Su cuenta ha sido desactivada. Contacte a la coordinacion.";
                    header('Location: index.php?page=login');
                    exit;
                }

                // si todo esta bien borramos los intentos fallidos anteriores
                limpiarIntentosFallidos($this->db, 'login');
                session_regenerate_id(true);

                // si es aspirante o estudiante no aprobado lo mandamos directo a subir documentos
                if ($rolReal === 'Aspirante' || ($rolReal === 'Estudiante' && $usuario['estado'] !== 'Aprobado')) {
                    $_SESSION['id_usuario_temp'] = $usuario['id'];
                    $_SESSION['id_usuario'] = $usuario['id'];
                    $_SESSION['nombre_usuario'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                    $_SESSION['nombre_completo'] = trim("{$usuario['nombre']} {$usuario['segundo_nombre']} {$usuario['apellido']} {$usuario['segundo_apellido']}");
                    $_SESSION['tipo_doc'] = $usuario['tipo_documento'];
                    $_SESSION['rol_usuario'] = 'Aspirante';

                    AuditHelper::registrar($this->db, $usuario['id'], 'Inicio de Sesion Aspirante', "Ingreso al modulo de pre-inscripcion y carga de documentos");
                    header('Location: index.php?page=upload_docs');
                    exit;
                }

                // guardamos en la memoria de la sesion los datos de quien entro
                $_SESSION['id_usuario'] = $usuario['id'];
                $_SESSION['nombre_usuario'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                $_SESSION['nombre_completo'] = trim("{$usuario['nombre']} {$usuario['segundo_nombre']} {$usuario['apellido']} {$usuario['segundo_apellido']}");
                $_SESSION['tipo_doc'] = $usuario['tipo_documento'];
                $_SESSION['rol_usuario'] = $rolReal;

                // nos aseguramos de que tenga su credencial qr lista
                $this->modeloUsuario->obtenerCredencialQR($usuario['id']);

                // anotamos en la bitacora de seguridad
                AuditHelper::registrar($this->db, $usuario['id'], 'Inicio de Sesion', "Ingreso exitoso con rol: {$rolReal}");
                header('Location: index.php?page=dashboard');
                exit;
            } else {
                // si la clave o correo fallaron contamos el intento malo
                registrarIntentoFallido($this->db, 'login', $correo);
                $_SESSION['error'] = "Credenciales incorrectas. Verifique su usuario/correo y contrasena.";
                header('Location: index.php?page=login');
                exit;
            }
        }
    }

    // funcion para que un nuevo bachiller cree su cuenta de aspirante
    public function registrar() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $token_enviado = $_POST['csrf_token'] ?? '';
            if (empty($token_enviado) || !hash_equals($_SESSION['csrf_token'], $token_enviado)) {
                $_SESSION['error'] = "Error de seguridad: Token CSRF invalido.";
                header('Location: index.php?page=register');
                exit;
            }
            $rol = 'Aspirante';

            // revisamos que la contrasena tenga al menos 8 letras o numeros
            $clave = $_POST['clave'] ?? '';
            if (strlen($clave) < 8) {
                $_SESSION['error'] = "La contrasena debe tener al menos 8 caracteres.";
                header('Location: index.php?page=register');
                exit;
            }

            // quitamos cualquier letra o simbolo de la cedula y del telefono
            $cedula = preg_replace('/[^0-9]/', '', $_POST['cedula'] ?? '');
            $telefono = preg_replace('/[^0-9]/', '', $_POST['telefono'] ?? '');
            if (empty($cedula) || empty($telefono)) {
                $_SESSION['error'] = "La cedula y el telefono deben contener unicamente numeros.";
                header('Location: index.php?page=register');
                exit;
            }

            // revisamos que la cedula no sea un numero imposible
            if (strlen($cedula) < 6 || strlen($cedula) > 9) {
                $_SESSION['error'] = "La cedula debe tener entre 6 y 9 digitos.";
                header('Location: index.php?page=register');
                exit;
            }

            // empaquetamos todos los datos del formulario limpiecitos
            $datos = [
                'tipo_documento' => $_POST['tipo_documento'] ?? 'V',
                'cedula' => $cedula,
                'nombre' => trim($_POST['nombre'] ?? ''),
                'segundo_nombre' => trim($_POST['segundo_nombre'] ?? ''),
                'apellido' => trim($_POST['apellido'] ?? ''),
                'segundo_apellido' => trim($_POST['segundo_apellido'] ?? ''),
                'telefono' => $telefono,
                'direccion' => trim($_POST['direccion'] ?? ''),
                'correo' => trim($_POST['correo'] ?? ''),
                'nombre_usuario' => trim($_POST['correo'] ?? ''),
                'clave' => $clave,
                'rol' => $rol,
                'estado' => 'Pendiente',
                'id_carrera' => 1,
                'cargo' => 'Aspirante a Ingenieria de Sistemas',
                'preguntas' => [
                    ['id_pregunta' => $_POST['id_pregunta_1'] ?? 1, 'respuesta' => $_POST['respuesta_1'] ?? 'UNEFA'],
                    ['id_pregunta' => $_POST['id_pregunta_2'] ?? 2, 'respuesta' => $_POST['respuesta_2'] ?? 'UNEFA']
                ]
            ];

            // obligamos a que elija dos preguntas distintas para mayor seguridad
            if ($datos['preguntas'][0]['id_pregunta'] === $datos['preguntas'][1]['id_pregunta']) {
                $_SESSION['error'] = "Debe seleccionar dos preguntas de seguridad diferentes.";
                header('Location: index.php?page=register');
                exit;
            }

            try {
                // guardamos en la base de datos
                $id_nuevo = $this->modeloUsuario->registrar($datos);
                if ($id_nuevo) {
                    $_SESSION['id_usuario_temp'] = $id_nuevo;
                    $_SESSION['id_usuario'] = $id_nuevo;
                    $_SESSION['nombre_usuario'] = $datos['nombre'] . ' ' . $datos['apellido'];
                    $_SESSION['nombre_completo'] = trim("{$datos['nombre']} {$datos['segundo_nombre']} {$datos['apellido']} {$datos['segundo_apellido']}");
                    $_SESSION['tipo_doc'] = $datos['tipo_documento'];
                    $_SESSION['rol_usuario'] = 'Aspirante';

                    AuditHelper::registrar($this->db, $id_nuevo, 'Registro de Aspirante', "Nuevo postulante registrado: {$datos['correo']} (Cedula: {$datos['cedula']})");
                    header('Location: index.php?page=upload_docs');
                    exit;
                }
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
                header('Location: index.php?page=register');
                exit;
            }
        }
    }

    // funcion para subir todos los documentos juntos en el registro inicial
    public function subirDocumentos() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $token_enviado = $_POST['csrf_token'] ?? '';
            if (empty($token_enviado) || !hash_equals($_SESSION['csrf_token'], $token_enviado)) {
                $_SESSION['error'] = "Error de seguridad: Token CSRF invalido.";
                header('Location: index.php?page=login');
                exit;
            }
            $id_usuario = $_SESSION['id_usuario_temp'] ?? $_SESSION['id_usuario'] ?? null;
            if (!$id_usuario) {
                header('Location: index.php?page=register');
                exit;
            }

            $archivos = $_FILES['documentos'] ?? null;
            if ($archivos) {
                $usuario = $this->modeloUsuario->obtenerUsuarioPorId($id_usuario);
                $cedula_numerica = $usuario ? preg_replace('/[^0-9]/', '', $usuario['cedula']) : ('usr_' . (int)$id_usuario);
                // creamos la carpetita con la cedula del aspirante si todavia no existe
                $upload_dir = 'public/uploads/' . $cedula_numerica . '/';
                $abs_upload_dir = __DIR__ . '/../../' . $upload_dir;
                if (!is_dir($abs_upload_dir)) mkdir($abs_upload_dir, 0777, true);

                // recorremos cada uno de los archivos que subio el usuario
                foreach ($archivos['name'] as $key => $name) {
                    if ($archivos['error'][$key] === UPLOAD_ERR_OK) {
                        $tmp_name = $archivos['tmp_name'][$key];
                        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        
                        // verificamos el tipo real del archivo para que no suban programas raros
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $tmp_name);
                        finfo_close($finfo);
                        $mime_permitidos = [
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'image/jpeg',
                            'image/png',
                        ];
                        if (!in_array($mime_type, $mime_permitidos)) {
                            $_SESSION['error'] = "Formato de archivo no permitido. Use PDF, Word o imagenes.";
                            continue;
                        }
                        
                        if (in_array($extension, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])) {
                            $tipo = in_array($extension, ['pdf', 'doc', 'docx']) ? (($extension === 'pdf') ? 'PDF' : 'Word') : 'Imagen';
                            $safe_key = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$key);
                            // si ya habia un archivo viejo de este requisito lo borramos del disco
                            foreach (glob($abs_upload_dir . 'doc_' . $safe_key . '.*') ?: [] as $oldFile) {
                                if (is_file($oldFile)) @unlink($oldFile);
                            }
                            $nuevo_nombre = 'doc_' . $safe_key . '.' . $extension;
                            $ruta = $upload_dir . $nuevo_nombre;
                            
                            // movemos el archivo a la carpeta oficial
                            if (move_uploaded_file($tmp_name, $abs_upload_dir . $nuevo_nombre)) {
                                $this->modeloUsuario->subirDocumento($id_usuario, $name, $ruta, $tipo);
                            }
                        }
                    }
                }
                header('Location: index.php?page=upload_docs&msg=registro_completo');
                exit;
            }
        }
    }

    // funcion para subir o reemplazar un solo requisito con ajax sin recargar la pagina
    public function subirIndividual() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $token_enviado = $_POST['csrf_token'] ?? '';
            if (empty($token_enviado) || !hash_equals($_SESSION['csrf_token'], $token_enviado)) {
                echo json_encode(['success' => false, 'message' => 'Error de seguridad: Token CSRF invalido.']);
                exit;
            }
            $id_usuario = $_SESSION['id_usuario_temp'] ?? $_SESSION['id_usuario'] ?? null;
            if (!$id_usuario) {
                echo json_encode(['success' => false, 'message' => 'Sesion no valida']);
                exit;
            }

            $archivo = $_FILES['archivo'] ?? null;
            $requisito = $_POST['requisito'] ?? 'Documento';
            $requisito_id = $_POST['requisito_id'] ?? '';

            if ($archivo && $archivo['error'] === UPLOAD_ERR_OK) {
                // maximo 10 megabytes por documento para no llenar el disco duro
                $tamano_maximo = 10 * 1024 * 1024;
                if ($archivo['size'] > $tamano_maximo) {
                    echo json_encode(['success' => false, 'message' => 'El archivo excede el limite de 10MB']);
                    exit;
                }

                $usuario = $this->modeloUsuario->obtenerUsuarioPorId($id_usuario);
                if (!$usuario) {
                    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
                    exit;
                }

                $cedula_numerica = preg_replace('/[^0-9]/', '', $usuario['cedula']);
                $relative_upload_path = 'public/uploads/' . $cedula_numerica . '/';
                $absolute_upload_path = __DIR__ . '/../../' . $relative_upload_path;

                if (!is_dir($absolute_upload_path)) {
                    mkdir($absolute_upload_path, 0777, true);
                }

                $name = $archivo['name'];
                $tmp_name = $archivo['tmp_name'];
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $tmp_name);
                finfo_close($finfo);

                $mime_permitidos = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg',
                    'image/png',
                ];

                if (!in_array($mime_type, $mime_permitidos)) {
                    echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Use PDF, Word o imagenes.']);
                    exit;
                }
                
                if (in_array($extension, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])) {
                    $tipo = in_array($extension, ['pdf', 'doc', 'docx']) ? (($extension === 'pdf') ? 'PDF' : 'Word') : 'Imagen';
                    $safe_requisito = preg_replace('/[^A-Za-z0-9_\-]/', '_', $requisito);
                    $safe_req_id = !empty($requisito_id) ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $requisito_id) : '';

                    // buscamos si este papel ya habia sido subido antes para borrar el archivo viejo
                    $docs_existentes = $this->modeloUsuario->obtenerDocumentosUsuario($id_usuario);
                    $doc_a_actualizar = null;
                    $ids_duplicados = [];
                    foreach ($docs_existentes as $de) {
                        $nombre_db = $de['nombre_archivo'];
                        $coincide = ($nombre_db === $requisito
                            || (!empty($requisito_id) && $nombre_db === $requisito_id)
                            || stripos($nombre_db, $requisito) !== false
                            || stripos($requisito, $nombre_db) !== false
                            || (!empty($requisito_id) && stripos($nombre_db, $requisito_id) !== false));
                        if ($coincide) {
                            if (!empty($de['ruta'])) {
                                $absOld = __DIR__ . '/../../' . ltrim($de['ruta'], '/\\');
                                if (is_file($absOld)) {
                                    @unlink($absOld);
                                } elseif (is_file($de['ruta'])) {
                                    @unlink($de['ruta']);
                                }
                            }
                            if ($doc_a_actualizar === null) {
                                $doc_a_actualizar = $de['id'];
                            } else {
                                $ids_duplicados[] = (int)$de['id'];
                            }
                        }
                    }

                    // eliminamos cualquier copia suelta que haya quedado en la carpeta
                    $patrones = [$absolute_upload_path . '*' . $safe_requisito . '.*'];
                    if ($safe_req_id !== '') {
                        $patrones[] = $absolute_upload_path . '*' . $safe_req_id . '.*';
                    }
                    foreach ($patrones as $pat) {
                        foreach (glob($pat) ?: [] as $archivoViejo) {
                            if (is_file($archivoViejo)) {
                                @unlink($archivoViejo);
                            }
                        }
                    }

                    // si habian registros repetidos en la base de datos los limpiamos
                    if (!empty($ids_duplicados)) {
                        $inDup = implode(',', $ids_duplicados);
                        $this->db->exec("DELETE FROM registro_documentos WHERE id IN ($inDup)");
                    }

                    $nuevo_nombre = $safe_requisito . '.' . $extension;
                    $ruta = $relative_upload_path . $nuevo_nombre;
                    
                    // guardamos el archivo nuevo en su carpeta
                    if (move_uploaded_file($tmp_name, $absolute_upload_path . $nuevo_nombre)) {
                        if ($doc_a_actualizar) {
                            $exito = $this->modeloUsuario->actualizarRutaDocumento($doc_a_actualizar, $ruta, $tipo);
                        } else {
                            $exito = $this->modeloUsuario->subirDocumento($id_usuario, $requisito, $ruta, $tipo);
                        }

                        if ($exito) {
                            $this->modeloUsuario->actualizarEstadoUsuario($id_usuario, 'Pendiente');
                            AuditHelper::registrar($this->db, $id_usuario, 'Subida de Documento', "Subio recaudo en carpeta {$cedula_numerica}: {$requisito}");
                            echo json_encode(['success' => true, 'message' => 'Archivo subido correctamente', 'archivo' => $name, 'tipo' => $tipo]);
                            exit;
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al registrar el documento en la base de datos']);
                            exit;
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al mover el archivo al servidor']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Extension de archivo no permitida: ' . $extension]);
                    exit;
                }
            } else {
                $error_code = $archivo['error'] ?? 'unknown';
                echo json_encode(['success' => false, 'message' => 'Error de subida PHP: ' . $error_code]);
                exit;
            }
        }
    }

    // funcion para recuperar contrasena en 3 sencillos pasos
    public function resetPassword() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token_enviado = $_POST['csrf_token'] ?? '';
            if (empty($token_enviado) || !hash_equals($_SESSION['csrf_token'], $token_enviado)) {
                $_SESSION['error'] = "Error de seguridad: Token CSRF invalido.";
                header('Location: index.php?page=reset_password');
                exit;
            }
            $step = $_POST['step'] ?? '1';

            // paso 1: buscar si el correo existe y traer las preguntas secretas
            if ($step == '1') {
                $correo = trim($_POST['correo'] ?? '');
                $usuario = $this->modeloUsuario->obtenerUsuarioPorCorreo($correo);
                
                if (!$usuario) {
                    registrarIntentoFallido($this->db, 'reset_password', $correo);
                    $_SESSION['error'] = "El usuario o correo no esta registrado.";
                    header('Location: index.php?page=reset_password');
                    exit;
                }

                $preguntas = $this->modeloUsuario->obtenerPreguntasRespondidas($usuario['id']);
                if (empty($preguntas)) {
                    $_SESSION['error'] = "Su cuenta no tiene preguntas de seguridad configuradas. Contacte a soporte.";
                    header('Location: index.php?page=reset_password');
                    exit;
                }

                $_SESSION['reset_user_id'] = $usuario['id'];
                $_SESSION['reset_step'] = '2';
                $_SESSION['reset_questions'] = $preguntas;
                header('Location: index.php?page=reset_password');
                exit;

            // paso 2: revisar que las respuestas a las preguntas coincidan con las guardadas
            } elseif ($step == '2') {
                $uid = $_SESSION['reset_user_id'] ?? null;
                $respuestas_user = $_POST['respuestas'] ?? [];
                $preguntas_db = $_SESSION['reset_questions'] ?? [];

                if (!$uid || empty($preguntas_db)) {
                    header('Location: index.php?page=reset_password');
                    exit;
                }

                $todas_correctas = true;
                foreach ($preguntas_db as $p) {
                    $resp_proporcionada = strtoupper(trim($respuestas_user[$p['id']] ?? ''));
                    if (!password_verify($resp_proporcionada, $p['hash_respuesta'])) {
                        $todas_correctas = false;
                        break;
                    }
                }

                if (!$todas_correctas) {
                    registrarIntentoFallido($this->db, 'reset_password', 'user_' . $uid);
                    $_SESSION['error'] = "Respuestas de seguridad incorrectas.";
                    header('Location: index.php?page=reset_password');
                    exit;
                }

                $_SESSION['reset_step'] = '3';
                header('Location: index.php?page=reset_password');
                exit;

            // paso 3: guardar la nueva clave escrita por el usuario
            } elseif ($step == '3') {
                $uid = $_SESSION['reset_user_id'] ?? null;
                $nueva_clave = $_POST['clave'] ?? '';
                $confirm_clave = $_POST['confirm_clave'] ?? '';

                if (!$uid) {
                    header('Location: index.php?page=reset_password');
                    exit;
                }

                if ($nueva_clave !== $confirm_clave) {
                    $_SESSION['error'] = "Las contrasenas no coinciden.";
                    header('Location: index.php?page=reset_password');
                    exit;
                }

                if (strlen($nueva_clave) < 8) {
                    $_SESSION['error'] = "La contrasena debe tener al menos 8 caracteres.";
                    header('Location: index.php?page=reset_password');
                    exit;
                }

                if ($this->modeloUsuario->actualizarClave($uid, $nueva_clave)) {
                    limpiarIntentosFallidos($this->db, 'reset_password');
                    unset($_SESSION['reset_user_id'], $_SESSION['reset_step'], $_SESSION['reset_questions']);
                    $_SESSION['success'] = "Contrasena actualizada correctamente. Ya puedes iniciar sesion.";
                    header('Location: index.php?page=login&msg=clave_actualizada');
                } else {
                    $_SESSION['error'] = "No se pudo actualizar la contrasena.";
                    header('Location: index.php?page=reset_password');
                }
                exit;
            }
        }
    }

    // funcion para cerrar sesion borrar los datos de la memoria y salir al inicio
    public function salir() {
        session_destroy();
        header('Location: index.php');
        exit;
    }
}
