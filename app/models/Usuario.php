<?php
// aqui empieza el modelo del usuario para todo el sistema de la unefa.
// este archivo se encarga de hablar directamente con la base de datos para guardar, buscar o actualizar personas.
class Usuario {
    // esta variable guarda la conexion con la base de datos mysql
    private $controlador;

    // cuando creamos un nuevo objeto usuario le pasamos la conexion de la base de datos
    public function __construct($db) {
        $this->controlador = $db;
    }

    // esta funcion sirve para que no importe como escriban el rol de la persona (con o sin tildes o abreviado),
    // siempre lo transforme al nombre oficial reconocido por el sistema
    public static function normalizarRol($rol) {
        $r = trim((string)$rol);
        $mapa = [
            'Super Administrador'   => 'Super Administrador',
            'SuperAdmin'            => 'Super Administrador',
            'Director'              => 'Director',
            'Coordinador Academico' => 'Coordinador Academico',
            'Coordinador Académico' => 'Coordinador Academico',
            'Coordinador'           => 'Coordinador Academico',
            'Profesor'              => 'Profesor',
            'Docente'               => 'Profesor',
            'Estudiante'            => 'Estudiante',
            'Aspirante'             => 'Aspirante',
        ];
        return $mapa[$r] ?? $r;
    }

    // esta funcion le quita las tildes, letras especiales y simbolos raros a los textos
    // para que cuando se cree el codigo qr no se confunda ningun escaner ni camara
    public static function limpiarTextoSinEspeciales($texto, $esCorreo = false) {
        $t = trim((string)$texto);
        $mapa = [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N'
        ];
        $t = strtr($t, $mapa);
        if ($esCorreo) {
            return preg_replace('/[^a-zA-Z0-9@._]/', '', $t);
        }
        $t = preg_replace('/[^a-zA-Z0-9 ]/', ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t);
        return trim($t);
    }

    // esta funcion busca toda la informacion del usuario (nombre, cedula, correo, seccion)
    // y la organiza en lineas limpias sin tildes para guardarla dentro del codigo qr
    public function obtenerDatosLimpiadosQR($id_usuario) {
        $stmt = $this->controlador->prepare("SELECT u.id, u.rol, u.estado, u.correo, p.cedula, p.tipo_documento, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.telefono, p.foto_perfil, p.fecha_carnetizacion, p.cargo FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.id = :id LIMIT 1");
        $stmt->execute([':id' => $id_usuario]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) return false;

        $rolNorm = self::normalizarRol($u['rol']);
        $nombreBruto = trim("{$u['nombre']} {$u['segundo_nombre']} {$u['apellido']} {$u['segundo_apellido']}");
        $nombreCompleto = self::limpiarTextoSinEspeciales($nombreBruto);
        $cedulaLimpia = preg_replace('/[^0-9]/', '', (string)$u['cedula']);
        $telefonoLimpio = preg_replace('/[^0-9]/', '', (string)($u['telefono'] ?? ''));
        if ($telefonoLimpio === '') {
            $telefonoLimpio = 'No registrado';
        }
        $correoLimpio = self::limpiarTextoSinEspeciales($u['correo'] ?? '', true);

        // aqui buscamos en que seccion esta metida la persona segun si es alumno o profesor
        $seccionBruta = '';
        if ($rolNorm === 'Estudiante') {
            $stmtSec = $this->controlador->prepare("SELECT s.nombre_seccion FROM inscripcion_seccion ins JOIN seccion s ON ins.id_seccion = s.id WHERE ins.id_estudiante = :id AND ins.estado = 'Inscrito' ORDER BY ins.id DESC LIMIT 1");
            $stmtSec->execute([':id' => $id_usuario]);
            $seccionBruta = (string)$stmtSec->fetchColumn();
            if ($seccionBruta === '') {
                $stmtDef = $this->controlador->query("SELECT nombre_seccion FROM seccion WHERE estado = 'Activa' ORDER BY id ASC LIMIT 1");
                $seccionBruta = (string)$stmtDef->fetchColumn() ?: '05S 2629 D1';
            }
        } else {
            $stmtSec = $this->controlador->prepare("SELECT s.nombre_seccion FROM seccion s WHERE s.id_docente = :id AND s.estado = 'Activa' AND s.id_materia IN (SELECT id_materia FROM docente_materia WHERE id_docente = :id2) ORDER BY s.id DESC LIMIT 1");
            $stmtSec->execute([':id' => $id_usuario, ':id2' => $id_usuario]);
            $seccionBruta = (string)$stmtSec->fetchColumn();
            if ($seccionBruta === '') {
                $stmtDef = $this->controlador->query("SELECT nombre_seccion FROM seccion WHERE estado = 'Activa' ORDER BY id ASC LIMIT 1");
                $seccionBruta = (string)$stmtDef->fetchColumn() ?: '05S 2629 D1';
            }
        }
        $seccionLimpia = self::limpiarTextoSinEspeciales($seccionBruta);

        // este es el texto en bloque que se mete dentro del dibujo del codigo qr
        $qrDatos = "Nombre Completo {$nombreCompleto}\n"
                 . "Cedula {$cedulaLimpia}\n"
                 . "Telefono {$telefonoLimpio}\n"
                 . "Correo {$correoLimpio}\n"
                 . "Seccion {$seccionLimpia}";

        return [
            'id_usuario'      => (int)$u['id'],
            'rol'             => $rolNorm,
            'estado'          => $u['estado'],
            'nombre_completo' => $nombreCompleto,
            'cedula'          => $cedulaLimpia,
            'telefono'        => $telefonoLimpio,
            'correo'          => $correoLimpio,
            'seccion'         => $seccionLimpia,
            'qr_token'        => $cedulaLimpia,
            'qr_datos'        => $qrDatos,
            'foto_perfil'     => $u['foto_perfil'],
            'fecha_carnetizacion' => $u['fecha_carnetizacion']
        ];
    }

    // esta funcion revisa si una persona ya tiene su foto oficial tomada y si el archivo de verdad existe en el disco
    public function tieneFotoCarnet($id_usuario) {
        $stmt = $this->controlador->prepare("SELECT u.rol, u.estado, p.foto_perfil, p.fecha_carnetizacion FROM usuario u JOIN perfil p ON u.id_perfil = p.id WHERE u.id = :id LIMIT 1");
        $stmt->execute([':id' => $id_usuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['rol'] === 'Aspirante' || $row['estado'] !== 'Aprobado') {
            return false;
        }
        if (in_array($row['rol'], ['Estudiante', 'Profesor', 'Docente'], true)) {
            return !empty($row['foto_perfil'])
                && $row['foto_perfil'] !== 'default.svg'
                && !empty($row['fecha_carnetizacion'])
                && file_exists('public/uploads/profiles/' . $row['foto_perfil']);
        }
        return true;
    }

    // esta funcion crea o actualiza el codigo qr en la tabla credencial_qr con validez de un ano
    public function generarCredencialQR($id_usuario) {
        $info = $this->obtenerDatosLimpiadosQR($id_usuario);
        if (!$info) return false;

        // si todavia es aspirante o no esta aprobado, no le dejamos tener codigo qr
        if ($info['rol'] === 'Aspirante' || $info['estado'] !== 'Aprobado') {
            return false;
        }

        $token   = $info['qr_token'];
        $qrDatos = $info['qr_datos'];

        $stmtCheck = $this->controlador->prepare("SELECT id FROM credencial_qr WHERE id_usuario = :id LIMIT 1");
        $stmtCheck->execute([':id' => $id_usuario]);
        if ($stmtCheck->fetch()) {
            $upd = $this->controlador->prepare("UPDATE credencial_qr SET qr_token = :token, qr_datos = :datos, estado = 'Activo', fecha_emision = NOW(), fecha_vencimiento = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id_usuario = :id");
            return $upd->execute([':token' => $token, ':datos' => $qrDatos, ':id' => $id_usuario]);
        } else {
            $ins = $this->controlador->prepare("INSERT INTO credencial_qr (id_usuario, qr_token, qr_datos, estado, fecha_emision, fecha_vencimiento) VALUES (:id, :token, :datos, 'Activo', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR))");
            return $ins->execute([':id' => $id_usuario, ':token' => $token, ':datos' => $qrDatos]);
        }
    }

    // esta funcion busca el codigo qr que ya tiene guardado el usuario en la base de datos
    public function obtenerCredencialQR($id_usuario) {
        $info = $this->obtenerDatosLimpiadosQR($id_usuario);
        if (!$info || $info['rol'] === 'Aspirante' || $info['estado'] !== 'Aprobado') {
            return false;
        }
        $stmt = $this->controlador->prepare("SELECT * FROM credencial_qr WHERE id_usuario = :id AND estado = 'Activo' LIMIT 1");
        $stmt->execute([':id' => $id_usuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['qr_datos'] !== $info['qr_datos'] || $row['qr_token'] !== $info['qr_token'])) {
            if ($this->generarCredencialQR($id_usuario)) {
                $stmt->execute([':id' => $id_usuario]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        return $row;
    }

    // esta funcion sirve para registrar a una persona nueva en el sistema guardando su perfil, su clave y sus preguntas
    public function registrar($datos) {
        try {
            $this->controlador->beginTransaction();

            $rol = self::normalizarRol($datos['rol'] ?? 'Aspirante');
            $esAprobado = in_array($rol, ['Super Administrador', 'Director', 'Coordinador Academico', 'Profesor'], true);
            $estadoInicial = $datos['estado'] ?? ($esAprobado ? 'Aprobado' : 'Pendiente');
            // solo las autoridades directivas tienen fecha inicial; los profesores y estudiantes deben carnetizarse para tener fecha oficial
            $esAutoridadDirectiva = in_array($rol, ['Super Administrador', 'Director', 'Coordinador Academico'], true);
            $fotoInicial = 'default.svg';
            $fechaCarnet = ($estadoInicial === 'Aprobado' && $esAutoridadDirectiva) ? date('Y-m-d H:i:s') : null;
            $idCarrera = 1; // exclusivamente ingenieria de sistemas

            $consulta_perfil = "INSERT INTO perfil (tipo_documento, cedula, nombre, segundo_nombre, apellido, segundo_apellido, telefono, direccion, id_carrera, foto_perfil, fecha_carnetizacion, cargo) 
                                VALUES (:tipo, :cedula, :nombre, :s_nombre, :apellido, :s_apellido, :telefono, :direccion, :id_carrera, :foto, :fecha_c, :cargo)";
            $sentencia_p = $this->controlador->prepare($consulta_perfil);
            $sentencia_p->execute([
                ':tipo'       => $datos['tipo_documento'] ?? 'V',
                ':cedula'     => $datos['cedula'],
                ':nombre'     => $datos['nombre'],
                ':s_nombre'   => $datos['segundo_nombre'] ?? '',
                ':apellido'   => $datos['apellido'],
                ':s_apellido' => $datos['segundo_apellido'] ?? '',
                ':telefono'   => $datos['telefono'] ?? '',
                ':direccion'  => $datos['direccion'] ?? '',
                ':id_carrera' => $idCarrera,
                ':foto'       => $fotoInicial,
                ':fecha_c'    => $fechaCarnet,
                ':cargo'      => $datos['cargo'] ?? $rol
            ]);
            $id_perfil = $this->controlador->lastInsertId();

            $nombreUsuario = $datos['nombre_usuario'] ?? ($datos['correo'] ?? ('user_' . $datos['cedula']));

            $consulta_usuario = "INSERT INTO usuario (id_perfil, nombre_usuario, correo, clave, rol, estado)
                                 VALUES (:id_perfil, :nombre_usuario, :correo, :clave, :rol, :estado)";
            $sentencia_u = $this->controlador->prepare($consulta_usuario);
            $clave_hasheada = password_hash($datos['clave'], PASSWORD_BCRYPT);
            $sentencia_u->execute([
                ':id_perfil'      => $id_perfil,
                ':nombre_usuario' => $nombreUsuario,
                ':correo'         => $datos['correo'],
                ':clave'          => $clave_hasheada,
                ':rol'            => $rol,
                ':estado'         => $estadoInicial
            ]);
            $id_usuario = $this->controlador->lastInsertId();

            $this->controlador->prepare("UPDATE perfil SET id_usuario = ? WHERE id = ?")->execute([$id_usuario, $id_perfil]);

            // guardamos las preguntas de seguridad elegidas por el usuario
            if (!empty($datos['preguntas']) && is_array($datos['preguntas'])) {
                foreach ($datos['preguntas'] as $p) {
                    if (empty($p['id_pregunta']) || empty($p['respuesta'])) continue;
                    $consulta_ans = "INSERT INTO respuestas_seguridad_usuario (id_usuario, id_pregunta, hash_respuesta) 
                                    VALUES (:uid, :qid, :ans)";
                    $sent_ans = $this->controlador->prepare($consulta_ans);
                    $ans_hash = password_hash(strtoupper(trim($p['respuesta'])), PASSWORD_BCRYPT);
                    $sent_ans->execute([
                        ':uid' => $id_usuario,
                        ':qid' => $p['id_pregunta'],
                        ':ans' => $ans_hash
                    ]);
                }
            }

            if ($rol !== 'Aspirante' && $estadoInicial === 'Aprobado') {
                $this->generarCredencialQR($id_usuario);
            }

            $this->controlador->commit();
            return $id_usuario;
        } catch (Exception $e) {
            if ($this->controlador->inTransaction()) {
                $this->controlador->rollBack();
            }
            if ($e->getCode() == 23000) {
                if (stripos($e->getMessage(), 'correo') !== false) {
                    throw new Exception("El correo electronico ya esta registrado.");
                } elseif (stripos($e->getMessage(), 'cedula') !== false) {
                    throw new Exception("La cedula de identidad ya esta registrada.");
                }
            }
            throw new Exception("Error en el registro: " . $e->getMessage());
        }
    }

    // esta funcion revisa si el correo o cedula y la clave escrita por el usuario coinciden con la base de datos
    public function iniciarSesion($identificador, $clave) {
        $ident = trim((string)$identificador);
        if ($ident === '' || (string)$clave === '') {
            return false;
        }
        $identSinAcentos = strtr($ident, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U'
        ]);
        $rolNorm = self::normalizarRol($identSinAcentos);

        $consulta = "SELECT u.*, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.cargo
                     FROM usuario u 
                     JOIN perfil p ON u.id_perfil = p.id 
                     WHERE LOWER(u.correo) = LOWER(:ident1)
                        OR LOWER(u.nombre_usuario) = LOWER(:ident2)
                        OR LOWER(u.nombre_usuario) = LOWER(:ident3)
                        OR LOWER(u.rol) = LOWER(:rolNorm)
                        OR p.cedula = :ident4
                     ORDER BY u.id ASC LIMIT 1";
        $sentencia = $this->controlador->prepare($consulta);
        $sentencia->execute([
            ':ident1'  => $ident,
            ':ident2'  => $ident,
            ':ident3'  => $identSinAcentos,
            ':rolNorm' => $rolNorm,
            ':ident4'  => preg_replace('/^[VEPM]-/i', '', $ident)
        ]);

        $fila = $sentencia->fetch(PDO::FETCH_ASSOC);
        if ($fila && password_verify($clave, $fila['clave'])) {
            return $fila;
        }
        return false;
    }

    // esta funcion aprueba el expediente de un aspirante y lo convierte en estudiante oficial con codigo qr
    public function aprobarAspiranteAEstudiante($id_usuario) {
        $stmt = $this->controlador->prepare(
            "UPDATE usuario
             SET rol = 'Estudiante',
                 estado = 'Aprobado'
             WHERE id = :id"
        );
        $res = $stmt->execute([':id' => $id_usuario]);
        if ($res) {
            $this->generarCredencialQR($id_usuario);
        }
        return $res;
    }

    // esta funcion busca toda la informacion de una persona usando su numero de id
    public function obtenerUsuarioPorId($id) {
        $consulta = "SELECT u.*, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.foto_perfil, p.fecha_carnetizacion, p.cargo
                    FROM usuario u 
                    JOIN perfil p ON u.id_perfil = p.id 
                    WHERE u.id = :id LIMIT 1";
        $sentencia = $this->controlador->prepare($consulta);
        $sentencia->execute([':id' => $id]);
        return $sentencia->fetch(PDO::FETCH_ASSOC);
    }

    // esta funcion anota en la base de datos la ruta y el nombre del papel o archivo que subio el usuario
    public function subirDocumento($id_usuario, $nombre_archivo, $ruta, $tipo) {
        $consulta = "INSERT INTO registro_documentos (id_usuario, nombre_archivo, ruta, tipo) VALUES (:id_usuario, :nombre_archivo, :ruta, :tipo)";
        $sentencia = $this->controlador->prepare($consulta);
        return $sentencia->execute([
            ':id_usuario' => $id_usuario,
            ':nombre_archivo' => $nombre_archivo,
            ':ruta' => $ruta,
            ':tipo' => $tipo
        ]);
    }

    // esta funcion busca a todos los usuarios que tienen sus documentos pendientes por revisar
    public function obtenerUsuariosPendientes() {
        $consulta = "SELECT u.id, u.correo, u.rol, u.estado, p.id as id_perfil, p.nombre, p.segundo_nombre, p.apellido, p.segundo_apellido, p.cedula, p.tipo_documento, p.telefono, p.direccion 
                    FROM usuario u 
                    JOIN perfil p ON u.id_perfil = p.id 
                    WHERE u.rol IN ('Aspirante', 'Estudiante') AND u.estado = 'Pendiente'
                    ORDER BY u.id DESC";
        $sentencia = $this->controlador->query($consulta);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    // esta funcion actualiza todos los datos del perfil de una persona (nombre, cedula, telefono, direccion)
    public function actualizarPerfilCompleto($id_perfil, $datos) {
        $consulta = "UPDATE perfil SET cedula = :cedula, nombre = :nombre, segundo_nombre = :s_nombre, apellido = :apellido, segundo_apellido = :s_apellido, telefono = :telefono, direccion = :direccion WHERE id = :id";
        $sentencia = $this->controlador->prepare($consulta);
        return $sentencia->execute([
            ':cedula' => $datos['cedula'],
            ':nombre' => $datos['nombre'],
            ':s_nombre' => $datos['segundo_nombre'] ?? null,
            ':apellido' => $datos['apellido'],
            ':s_apellido' => $datos['segundo_apellido'] ?? null,
            ':telefono' => $datos['telefono'] ?? null,
            ':direccion' => $datos['direccion'] ?? null,
            ':id' => $id_perfil
        ]);
    }

    // esta funcion busca todos los documentos que ha subido un usuario en especifico
    public function obtenerDocumentosUsuario($id_usuario) {
        $consulta = "SELECT * FROM registro_documentos WHERE id_usuario = :id_usuario";
        $sentencia = $this->controlador->prepare($consulta);
        $sentencia->execute([':id_usuario' => $id_usuario]);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    // esta funcion cambia el estado de un usuario (por ejemplo ponerlo en Aprobado o en Pendiente)
    public function actualizarEstadoUsuario($id, $estado) {
        if ($estado === 'Aprobado') {
            $u = $this->obtenerUsuarioPorId($id);
            if ($u && in_array($u['rol'], ['Aspirante', 'Estudiante'], true)) {
                return $this->aprobarAspiranteAEstudiante($id);
            }
        }
        $consulta = "UPDATE usuario SET estado = :estado WHERE id = :id";
        $sentencia = $this->controlador->prepare($consulta);
        return $sentencia->execute([':estado' => $estado, ':id' => $id]);
    }

    // esta funcion aprueba o rechaza un documento y si se rechaza borra el archivo viejo de la carpeta
    public function actualizarEstadoDocumento($id_doc, $estado, $observaciones = null) {
        if ($estado === 'Rechazado') {
            $stmtOld = $this->controlador->prepare("SELECT ruta FROM registro_documentos WHERE id = :id LIMIT 1");
            $stmtOld->execute([':id' => $id_doc]);
            $rutaAntigua = $stmtOld->fetchColumn();
            if (!empty($rutaAntigua)) {
                $absOld = __DIR__ . '/../../' . ltrim($rutaAntigua, '/\\');
                if (is_file($absOld)) {
                    @unlink($absOld);
                } elseif (is_file($rutaAntigua)) {
                    @unlink($rutaAntigua);
                }
            }
        }
        $consulta = "UPDATE registro_documentos SET estado = :estado, observaciones = :obs WHERE id = :id";
        $sentencia = $this->controlador->prepare($consulta);
        return $sentencia->execute([
            ':estado' => $estado, 
            ':obs' => $observaciones,
            ':id' => $id_doc
        ]);
    }

    // esta funcion actualiza la ubicacion de un documento si el alumno vuelve a subir una foto o archivo nuevo
    public function actualizarRutaDocumento($id_doc, $nueva_ruta, $nuevo_tipo = null) {
        $stmtOld = $this->controlador->prepare("SELECT ruta FROM registro_documentos WHERE id = :id LIMIT 1");
        $stmtOld->execute([':id' => $id_doc]);
        $rutaAntigua = $stmtOld->fetchColumn();
        if (!empty($rutaAntigua) && $rutaAntigua !== $nueva_ruta) {
            $absOld = __DIR__ . '/../../' . ltrim($rutaAntigua, '/\\');
            if (is_file($absOld)) {
                @unlink($absOld);
            } elseif (is_file($rutaAntigua)) {
                @unlink($rutaAntigua);
            }
        }
        if ($nuevo_tipo !== null) {
            $stmt = $this->controlador->prepare("UPDATE registro_documentos SET ruta = :ruta, tipo = :tipo, estado = 'Pendiente', observaciones = NULL WHERE id = :id");
            return $stmt->execute([
                ':ruta' => $nueva_ruta,
                ':tipo' => $nuevo_tipo,
                ':id'   => $id_doc
            ]);
        }
        $stmt = $this->controlador->prepare("UPDATE registro_documentos SET ruta = :ruta, estado = 'Pendiente', observaciones = NULL WHERE id = :id");
        return $stmt->execute([
            ':ruta' => $nueva_ruta,
            ':id' => $id_doc
        ]);
    }

    // esta funcion cambia el estado de una persona buscando por su correo
    public function actualizarEstadoUsuarioPorCorreo($correo, $estado) {
        $consulta = "UPDATE usuario SET estado = :estado WHERE correo = :correo";
        $sentencia = $this->controlador->prepare($consulta);
        return $sentencia->execute([':estado' => $estado, ':correo' => $correo]);
    }

    // esta funcion elimina por completo a un usuario segun su numero de id
    public function eliminarUsuario($id) {
        $consulta = "DELETE FROM usuario WHERE id = :id";
        $sentencia = $this->controlador->prepare($consulta);
        return $sentencia->execute([':id' => $id]);
    }

    // esta funcion busca en la base de datos la lista de preguntas de seguridad disponibles
    public function obtenerPreguntasSeguridad() {
        $consulta = "SELECT * FROM preguntas_seguridad";
        return $this->controlador->query($consulta)->fetchAll(PDO::FETCH_ASSOC);
    }

    // esta funcion busca si ya existe un usuario registrado con un correo o nombre de usuario
    public function obtenerUsuarioPorCorreo($correo) {
        $ident = trim((string)$correo);
        $consulta = "SELECT id, correo, nombre_usuario FROM usuario WHERE LOWER(correo) = LOWER(:c1) OR LOWER(nombre_usuario) = LOWER(:c2) LIMIT 1";
        $sentencia = $this->controlador->prepare($consulta);
        $sentencia->execute([':c1' => $ident, ':c2' => $ident]);
        return $sentencia->fetch(PDO::FETCH_ASSOC);
    }

    // esta funcion busca las preguntas que el usuario respondio cuando creo su cuenta
    public function obtenerPreguntasRespondidas($user_id) {
        $consulta = "SELECT sq.id, sq.texto_pregunta, usa.hash_respuesta 
                    FROM preguntas_seguridad sq
                    JOIN respuestas_seguridad_usuario usa ON sq.id = usa.id_pregunta
                    WHERE usa.id_usuario = :uid";
        $sentencia = $this->controlador->prepare($consulta);
        $sentencia->execute([':uid' => $user_id]);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    // esta funcion busca toda la informacion del perfil, su carrera y su credencial qr
    public function obtenerPerfilCompleto($user_id) {
        $consulta = "SELECT u.*, p.*, c.nombre_carrera, q.qr_token, q.qr_datos, q.fecha_vencimiento AS qr_vencimiento
                    FROM usuario u
                    JOIN perfil p ON u.id_perfil = p.id
                    LEFT JOIN carrera c ON p.id_carrera = c.id
                    LEFT JOIN credencial_qr q ON q.id_usuario = u.id
                    WHERE u.id = :id LIMIT 1";
        $sentencia = $this->controlador->prepare($consulta);
        $sentencia->execute([':id' => $user_id]);
        return $sentencia->fetch(PDO::FETCH_ASSOC);
    }

    // esta funcion guarda los cambios cuando una persona edita su propio perfil desde la pagina
    public function actualizarMiPerfil($id_perfil, $datos) {
        $consulta = "UPDATE perfil SET nombre = :nombre, segundo_nombre = :s_nombre, apellido = :apellido, segundo_apellido = :s_apellido, telefono = :telefono, direccion = :direccion WHERE id = :id";
        $sentencia = $this->controlador->prepare($consulta);
        return $sentencia->execute([
            ':nombre' => $datos['nombre'],
            ':s_nombre' => $datos['segundo_nombre'] ?? null,
            ':apellido' => $datos['apellido'],
            ':s_apellido' => $datos['segundo_apellido'] ?? null,
            ':telefono' => $datos['telefono'] ?? null,
            ':direccion' => $datos['direccion'] ?? null,
            ':id' => $id_perfil
        ]);
    }

    // esta funcion permite que un estudiante actualice unicamente su numero de telefono
    public function actualizarPerfilEstudiante($id_perfil, $telefono) {
        $consulta = "UPDATE perfil SET telefono = :telefono WHERE id = :id";
        $sentencia = $this->controlador->prepare($consulta);
        return $sentencia->execute([
            ':telefono' => $telefono,
            ':id' => $id_perfil
        ]);
    }

    // esta funcion guarda una clave secreta nueva cifrandola para proteger la seguridad
    public function actualizarClave($user_id, $nueva_clave) {
        $hash = password_hash($nueva_clave, PASSWORD_BCRYPT);
        $consulta = "UPDATE usuario SET clave = :hash WHERE id = :id";
        $sentencia = $this->controlador->prepare($consulta);
        return $sentencia->execute([':hash' => $hash, ':id' => $user_id]);
    }
}
