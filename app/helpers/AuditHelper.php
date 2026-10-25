<?php
// este archivo es como el cuaderno de notas o diario del sistema (la bitacora de auditoria).
// cada vez que alguien inicia sesion, aprueba a un estudiante o cambia algo importante,
// aqui se anota quien lo hizo, a que hora y desde cual computadora (direccion ip).
class AuditHelper {
    // esta funcion anota una accion nueva en la bitacora de la base de datos
    public static function registrar($conexion, $id_usuario, $accion, $detalle = '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $conexion->prepare(
            "INSERT INTO bitacora (id_usuario, accion, detalle, direccion_ip) VALUES (:uid, :accion, :detalle, :ip)"
        );
        $stmt->execute([
            ':uid' => $id_usuario,
            ':accion' => $accion,
            ':detalle' => $detalle,
            ':ip' => $ip
        ]);
    }

    // esta funcion busca las ultimas acciones anotadas en el cuaderno para mostrarselas al director o coordinador
    public static function obtenerUltimos($conexion, $limite = 100) {
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // esta funcion busca los registros filtrando por temas especificos (como aspirantes, asistencias o cuentas)
    public static function obtenerPorCategoria($conexion, $categoria = 'todos', $limite = 100) {
        $where = '';
        if ($categoria === 'aspirantes') {
            $where = "WHERE b.accion LIKE '%Aspirante%' OR b.accion LIKE '%Documento%' OR b.accion LIKE '%Expediente%'";
        } elseif ($categoria === 'asistencias') {
            $where = "WHERE b.accion LIKE '%Asistencia%' OR b.accion LIKE '%QR%' OR b.accion LIKE '%Clase%'";
        } elseif ($categoria === 'cuentas') {
            $where = "WHERE b.accion LIKE '%Usuario%' OR b.accion LIKE '%Docente%' OR b.accion LIKE '%Coordinador%' OR b.accion LIKE '%Cuenta%' OR b.accion LIKE '%Perfil%' OR b.accion LIKE '%Clave%'";
        }
        $stmt = $conexion->prepare(
            "SELECT b.id, b.accion, b.detalle, b.direccion_ip, b.fecha_hora,
                    u.correo, p.nombre, p.apellido, u.rol
            FROM bitacora b
            LEFT JOIN usuario u ON b.id_usuario = u.id
            LEFT JOIN perfil p ON u.id_perfil = p.id
            $where
            ORDER BY b.fecha_hora DESC
            LIMIT :lim"
        );
        $stmt->bindValue(':lim', (int)$limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
