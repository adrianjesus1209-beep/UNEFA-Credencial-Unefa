# SISTEMA INTEGRAL DE CREDENCIALIZACION Y ASISTENCIA DIGITAL - UNEFA

Sistema universitario para la gestion, carnetizacion digital con codigo QR de alta resolucion y control de asistencia estudiantil y docente de la Universidad Nacional Experimental Politecnica de la Fuerza Armada Nacional Bolivariana (UNEFA).

---

## ESTRUCTURA DE LA COMUNIDAD Y DEPARTAMENTOS

### LIDER GENERAL DE TODOS LOS DEPARTAMENTOS
- **Adrian Bello**

---

### 1. DEPARTAMENTO DE BACKEND
- **Lider de departamento:** Gabriel Cobo
- **Sublider:** Cristian Trosel
- **Integrantes:**
  - Rutbelis Cordova
  - Jose Losad
  - Susana Acevedo
  - Nestor Marquina
  - Reyli Gonzales
- **Responsabilidad:** Logica de negocio (`index.php`), modelos de datos (`app/models/`), autenticacion, control de sesiones, validacion de permisos por rol, endpoints AJAX y generacion segura de tokens QR.

---

### 2. DEPARTAMENTO DE FRONTEND
- **Lider de departamento:** Deiber Vasquez
- **Sublider:** Jesus Pacheco
- **Integrantes:**
  - Keinserling Leama
  - Gregori Arrieta
  - Josfrancis Torres
  - Ismael Maestre
  - Jesus Parra
- **Responsabilidad:** Vistas de usuario (`app/views/`), diseño responsivo para movil y escritorio, renderizado del carnet digital con HTML5 Canvas sobre plantilla PDF, captura de fotos con camara web, interfaz del escaner QR y estilos visuales.

---

### 3. DEPARTAMENTO DE BASE DE DATOS
- **Lider de departamento:** John Frank Romero
- **Sublider / Co-lider:** Adrian Bello
- **Integrantes:**
  - Gabriel Hidalgo
  - Aura Izquierdo
  - Brigitte Rodriguez
  - Chukwuduben Onoh
  - Eduardo Martinez
- **Responsabilidad:** Mantenimiento del esquema relacional (`database/credencial_unefa_db.sql`), diseño de tablas, normalizacion, indices, integridad referencial y optimizacion de consultas SQL.

---

### 4. DEPARTAMENTO DE DOCUMENTACION
- **Lider de departamento:** Abel Leon
- **Integrantes:**
  - Gabriel Gonzalez
  - Osmely Sangronis
- **Responsabilidad:** Documentacion tecnica (`docs/`), diccionario de datos, manuales de usuario, requerimientos funcionales, guias de despliegue y actas de entrega.

---

## CREDENCIALES DEL SISTEMA

Las credenciales de acceso iniciales para pruebas y administracion se encuentran detalladas en el archivo:
`CREDENCIALES_SISTEMA.txt`

Resumen de cuentas iniciales:
- **Super Administrador:**
  - Usuario: `superadmin@unefa.edu.ve` (o `Super Administrador`)
  - Clave: `12345678`
- **Coordinador Academico:**
  - Usuario: `coordinador@unefa.edu.ve` (o `Coordinador Academico`)
  - Clave: `12345678`

---

## ESTRUCTURA DEL PROYECTO

```
Credencial Unefa/
├── app/                        # Nucleo de la aplicacion
│   ├── models/                 # Modelos de datos (Usuario, etc.)
│   └── views/                  # Vistas del sistema (auth, dashboard, coordinator, student, etc.)
├── database/                   # Archivos SQL y respaldo de la base de datos
│   └── credencial_unefa_db.sql # Estructura y datos iniciales de la base de datos
├── docs/                       # Documentacion general del sistema y diccionario de datos
├── libs/                       # Librerias PHP internas
├── public/                     # Recursos accesibles publicamente
│   ├── assets/                 # Imagenes, videos, banners y librerias JS (qrcode, pdfjs, etc.)
│   ├── carnet/                 # Plantillas oficiales en formato PDF para los carnets
│   └── uploads/                # Fotografias de perfil y carnetizacion
├── .gitignore                  # Exclusion de temporales y archivos locales
├── CREDENCIALES_SISTEMA.txt    # Accesos iniciales del sistema
├── index.php                   # Controlador principal y enrutador de la aplicacion
└── README.md                   # Guia general del proyecto
```

---

## POLITICA DE RAMAS EN GIT

Para mantener el orden entre los cuatro departamentos, el repositorio se organiza con el siguiente esquema de ramas:

1. **`main` (Produccion / Estable):**
   - Contiene unicamente versiones estables y aprobadas formalmente por el Lider General Adrian Bello.

2. **`develop` (Integracion General):**
   - Rama donde convergen las caracteristicas terminadas y revisadas de todos los departamentos.

3. **Ramas Fijas por Departamento:**
   - **`Backend`**: Base para el equipo de Backend.
   - **`Frontend`**: Base para el equipo de Frontend.
   - **`Database`**: Base para el equipo de Base de Datos.
   - **`Documentacion`**: Base para el equipo de Documentacion.

4. **Ramas de Trabajo Especificas (Features / Correcciones):**
   - Cada miembro debe crear una rama a partir de la rama de su departamento, siguiendo este formato:
     - `Backend-nombre-tarea`
     - `Frontend-nombre-tarea`
     - `Database-nombre-tarea`
     - `Documentacion-nombre-tarea`

---

## POLITICA DE REVISION DE COMMITS (CODE REVIEW)

Antes de fusionar cualquier cambio a las ramas departamentales o a `develop`:
1. El commit o pull request debe ser revisado y aprobado por el Lider General (Adrian Bello) y los Lideres Departamentales.
2. Se debe comprobar la sintaxis mediante `php -l`.
3. Se debe certificar la compatibilidad con el esquema de base de datos actual.
4. No se permite el uso de emojis en ninguna parte del codigo, comentarios o mensajes de commit.
5. El codigo debe mantener un estilo autodocumentado, con explicaciones sencillas y comprensibles para todo el equipo.

---

## REQUISITOS DE INSTALACION LOCAL (XAMPP)

1. Servidor web Apache con modulo `mod_rewrite` habilitado.
2. PHP 8.1 o superior con extensiones `pdo_mysql`, `gd`, `mbstring`.
3. Base de datos MySQL / MariaDB.
4. Importar el archivo `database/credencial_unefa_db.sql` en phpMyAdmin o consola MySQL:
   ```sql
   CREATE DATABASE IF NOT EXISTS credencial_unefa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE credencial_unefa_db;
   SOURCE database/credencial_unefa_db.sql;
   ```
5. Acceder en el navegador web:
   ```
   http://localhost/Proyectos/Credencial%20Unefa/
   ```
