# Farmacia VidaPlus

Sistema de gestión farmacéutica desarrollado como **proyecto integrador** del curso **Taller de Base de Datos**. Implementa las cuatro unidades del temario sobre un mismo dominio de negocio: una farmacia con ventas locales y en línea, control de inventario, auditoría y reportes.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0.16%2B-4479A1?logo=mysql)
![Tailwind](https://img.shields.io/badge/Tailwind-3.x-38B2AC?logo=tailwind-css)
![License](https://img.shields.io/badge/Uso-Educativo-green)

---

## Tabla de contenidos

1. [Descripción general](#-descripción-general)
2. [Requisitos](#-requisitos)
3. [Instalación paso a paso](#-instalación-paso-a-paso)
4. [Credenciales de prueba](#-credenciales-de-prueba)
5. [Estructura del proyecto](#-estructura-del-proyecto)
6. [Módulos del sistema](#-módulos-del-sistema)
7. [Cobertura del temario](#-cobertura-del-temario)
8. [Guía de demostración](#-guía-de-demostración)
9. [Arquitectura de seguridad](#-arquitectura-de-seguridad)
10. [Solución de problemas](#-solución-de-problemas)
11. [Autoría](#-autoría)

---

## Descripción general

Farmacia VidaPlus es un sistema que permite:

- **Gestionar el catálogo de medicamentos** con control de stock y alertas de reabastecimiento.
- **Registrar ventas locales y en línea** con transacciones atómicas y descuento automático de inventario.
- **Administrar clientes** con múltiples direcciones de envío y acceso a un portal en línea.
- **Gestionar empleados** con roles diferenciados (admin, farmacéutico, cajero, auditor).
- **Auditar automáticamente** cada operación sensible mediante triggers de base de datos.
- **Generar reportes** de ventas, productos, clientes y comparativas mensuales mediante procedimientos almacenados.

El sistema está construido **sin frameworks** para que el alumno pueda rastrear el flujo completo: formulario HTML → PHP → SQL → MySQL → respuesta.

> **Regla de oro del proyecto:** la base de datos hace el trabajo pesado; PHP solo orquesta.

---

## Requisitos

| Software      | Versión mínima        | Notas                                              |
| :------------ | :-------------------- | :------------------------------------------------- |
| **Laragon**   | 4.x o superior        | Incluye Apache, PHP 8 y MySQL 8                    |
| **PHP**       | 8.0+                  | Con extensiones `pdo_mysql`, `mbstring`, `openssl` |
| **MySQL**     | **8.0.16+**           | **Obligatorio** para `CHECK` y `DEFAULT (expr)`    |
| **Navegador** | Chrome, Edge, Firefox | Compatible con ES2020                              |
| **Editor**    | VS Code (recomendado) | Con extensiones PHP y SQL                          |

### Verificar la versión de MySQL

```sql
SELECT VERSION();
```

Debe devolver `8.0.16` o superior. Si es menor, las restricciones `CHECK` no se aplican y las validaciones del proyecto fallarán.

---

## Instalación paso a paso

### 1. Clonar o copiar el proyecto

Copia la carpeta `vidaplus` dentro de `C:\laragon\www\`:

```
C:\laragon\www\vidaplus\
```

### 2. Iniciar Laragon

- Abre Laragon.
- Clic en **Start All**.
- Verifica que Apache y MySQL estén en verde.

### 3. Crear el host virtual

En Laragon: **Menu → Preferences → Services & Ports** o simplemente:

- Clic derecho en Laragon → **www → vidaplus**
- O acceder a `http://localhost/vidaplus/`

Si prefieres un dominio amigable (`http://vidaplus.test/`), Laragon lo configura automáticamente al reiniciar.

### 4. Ejecutar los scripts SQL en orden

Abre **HeidiSQL** (o tu cliente MySQL) conectado como `root` y ejecuta **en este orden**:

| Orden | Archivo                  | ¿Qué hace?                                                                               |
| :---- | :----------------------- | :--------------------------------------------------------------------------------------- |
| 1     | `sql/01_schema.sql`      | Crea la BD y las 14 tablas con CHECKs, FKs e índices                                     |
| 2     | `sql/02_seed.sql`        | Pobla catálogos (países, roles, categorías, laboratorios, proveedores) y datos de prueba |
| 3     | `sql/03_users_roles.sql` | Crea los 6 usuarios MySQL y los 5 roles con privilegios diferenciados                    |
| 4     | `sql/04_triggers.sql`    | Crea los 3 triggers de ventas (validaciones, descuento, devolución)                      |
| 5     | `sql/05_procedures.sql`  | Crea los 10 procedimientos almacenados de reportes                                       |

> ⚠️ **Importante:** los scripts 04 y 05 deben ejecutarse **después** de 03, porque los triggers usan `DEFINER = 'trigger_runner'` que se crea en el script 03.

### 5. Generar los hashes de contraseña

Los usuarios de prueba necesitan contraseñas hasheadas. Crea el archivo temporal `generar_hashes.php` en la raíz:

```php
<?php
header('Content-Type: text/plain; charset=utf-8');
$credenciales = [
    'admin'         => 'Admin#2026',
    'farmaceutico1' => 'Farma#2026',
    'cajero1'       => 'Caja#2026',
    'auditor1'      => 'Audit#2026',
    'cliente'       => 'Cliente#2026',
];
foreach ($credenciales as $user => $pass) {
    echo "$user → " . password_hash($pass, PASSWORD_DEFAULT) . "\n";
}
```

Abre `http://vidaplus.test/generar_hashes.php`, copia los hashes y ejecuta en MySQL:

```sql
USE vidaplus;

INSERT INTO usuarios (username, password_hash, nombre_completo, email, id_rol) VALUES
('admin',         '<HASH_ADMIN>',         'Administrador General', 'admin@vidaplus.test',  1),
('farmaceutico1', '<HASH_FARMACEUTICO>',  'Farmacéutico Prueba',   'farma@vidaplus.test',  2),
('cajero1',       '<HASH_CAJERO>',        'Cajero Prueba',         'caja@vidaplus.test',   3),
('auditor1',      '<HASH_AUDITOR>',       'Auditor Prueba',        'audit@vidaplus.test',  4);

INSERT INTO clientes (nombre, apellido, email, id_pais, password_hash) VALUES
('Ana', 'García', 'ana@mail.test', 1, '<HASH_CLIENTE>');
```

> **Borra `generar_hashes.php`** después de usarlo.

### 6. Verificar la instalación

Abre `http://vidaplus.test/` en el navegador. Debe aparecer la pantalla de login.

Prueba con:

- **Usuario:** `admin`
- **Contraseña:** `Admin#2026`

Si entra al dashboard, la instalación fue exitosa. 🎉

---

## Credenciales de prueba

| Rol              | Usuario / Email | Contraseña     | Puede hacer                                                                                            |
| :--------------- | :-------------- | :------------- | :----------------------------------------------------------------------------------------------------- |
| **Admin**        | `admin`         | `Admin#2026`   | Todo: gestionar medicamentos, clientes, usuarios, ver reportes, cancelar ventas, auditoría             |
| **Farmacéutico** | `farmaceutico1` | `Farma#2026`   | Crear/editar medicamentos, registrar ventas, ver reportes. **No** puede eliminar ni gestionar usuarios |
| **Cajero**       | `cajero1`       | `Caja#2026`    | Registrar ventas y consultar medicamentos. **No** puede editar catálogo ni ver reportes                |
| **Auditor**      | `auditor1`      | `Audit#2026`   | Ver auditoría y reportes. Solo lectura                                                                 |
| **Cliente**      | `ana@mail.test` | `Cliente#2026` | Ver catálogo en línea (solo sin receta), gestionar su perfil y compras                                 |

> **Nota:** el login es unificado. Empleados usan su `username`, clientes usan su `email`.

---

## Estructura del proyecto

```
vidaplus/
│
├── index.php                     # Punto de entrada (redirige a login o dashboard)
├── README.md                     # Este archivo
│
├── config/
│   └── database.php              # Credenciales de los 6 usuarios MySQL
│
├── includes/
│   ├── db.php                    # Conexión PDO dinámica por rol
│   ├── helpers.php               # Funciones auxiliares (e, redirect, flash, esRol…)
│   ├── auth.php                  # Verificación de sesión y revalidación de rol
│   ├── header.php                # Navbar + menú dinámico por rol
│   ├── footer.php                # Footer + JS global
│   ├── medicamentos_form.php     # Formulario compartido (crear/editar)
│   ├── clientes_form.php         # Formulario compartido (crear/editar)
│   ├── direcciones_form.php      # Formulario compartido (crear/editar)
│   └── usuarios_form.php         # Formulario compartido (crear/editar)
│
├── modules/
│   ├── auth/
│   │   ├── login.php             # Login unificado (empleado o cliente)
│   │   └── logout.php            # Cierre de sesión
│   ├── dashboard.php             # Panel diferenciado por rol con reportes
│   ├── medicamentos.php          # CRUD catálogo + control de acceso
│   ├── clientes.php              # CRUD clientes (solo admin)
│   ├── direcciones.php           # CRUD direcciones (solo admin)
│   ├── usuarios.php              # CRUD empleados (solo admin)
│   ├── ventas.php                # Listado, ver ticket, cancelar
│   ├── ventas_nueva.php          # Crear venta con transacción
│   ├── auditoria.php             # Bitácora de operaciones (admin + auditor)
│   ├── backups.php               # Panel de respaldos (solo admin)
│   ├── perfil.php                # Editar datos personales
│   ├── cambiar_password.php      # Cambiar contraseña (cierra sesión)
│   └── reportes/
│       ├── ..                     # (reportes.php está en modules/)
│       ├── ventas.php            # Reporte por período
│       ├── productos.php         # Top vendidos / stock bajo / sin ventas
│       ├── clientes.php          # Top compradores / sin compras
│       └── comparativa.php       # Comparativa mensual (CURSOR)
│
├── sql/
│   ├── 01_schema.sql             # DDL de las 14 tablas
│   ├── 02_seed.sql               # Datos de catálogos
│   ├── 03_users_roles.sql        # Usuarios MySQL y roles
│   ├── 04_triggers.sql           # Triggers de ventas
│   ├── 05_procedures.sql         # Procedimientos de reportes
│   ├── 06_backup.sh              # Script de respaldo (pendiente wrapper Windows)
│   └── 07_restore.sh             # Script de restauración
│
├── backups/                      # Archivos .sql.gz (bloqueado por .htaccess)
│
└── assets/
    └── css/
        └── styles.css            # Ajustes mínimos no cubiertos por Tailwind
```

---

## Módulos del sistema

### Dashboard

Panel adaptado al rol del usuario:

- **Admin y farmacéutico:** KPIs (ventas hoy, ingreso, stock bajo, clientes), gráfico 7 días, top 5 medicamentos, top 5 clientes, alertas de stock.
- **Cajero:** sus propias ventas (hoy, mes), historial de las últimas 5, CTA a nueva venta.
- **Auditor:** estadísticas de auditoría, últimos 5 eventos, ventas del mes.
- **Cliente:** hero de bienvenida, información del portal, sus compras y puntos.

### Medicamentos

- Catálogo con búsqueda, filtros (categoría, laboratorio, stock), ordenamiento por columnas.
- Tarjetas de estadísticas: total, stock bajo, agotados, categorías.
- **Crear/editar:** solo admin y farmacéutico.
- **Eliminar:** solo admin. Si tiene ventas → se desactiva (baja lógica).
- **Exportar CSV.**

### Clientes y Direcciones

- CRUD de clientes (solo admin).
- Cada cliente puede tener múltiples direcciones de envío (una predeterminada).
- Portal en línea: si el cliente tiene `password_hash`, puede iniciar sesión.

### Usuarios

- CRUD de empleados (solo admin).
- **Reglas de autoprotección:** un usuario no puede cambiarse su propio rol, ni desactivarse, ni eliminarse.
- No se puede eliminar el último admin activo.
- Baja lógica si tiene ventas/compras asociadas.

### Ventas

- **Listado** con filtros por canal, estado, fechas y "mis ventas".
- **Nueva venta:** carrito dinámico con validación en servidor.
- **Transacción completa:** INSERT venta → INSERT detalle (dispara trigger de stock) → INSERT pago → COMMIT o ROLLBACK.
- **Cancelar:** solo admin. El trigger devuelve el stock automáticamente.
- Ticket detallado con pagos, dirección de envío y datos del cliente.

### Auditoría

- Bitácora automática generada por triggers.
- Filtros por tabla, acción, usuario, rango de fechas.
- Paginación y exportación CSV.
- **Solo lectura** (nadie puede modificar la auditoría).

### Reportes

- **Ventas por período** (día a día, por canal).
- **Productos:** top vendidos, stock bajo, sin ventas.
- **Clientes:** top compradores, sin compras.
- **Comparativa mensual:** actual vs anterior por categoría (usa CURSOR).

### Perfil y Contraseña

- Editar datos personales según tipo de sesión.
- Cambio de contraseña con verificación actual + confirmación.
- Cierra sesión tras el cambio (buena práctica de seguridad).

---

## Cobertura del temario

### Unidad 1 — Introducción a las bases de datos

| Tema           | Implementación                                                                       |
| :------------- | :----------------------------------------------------------------------------------- |
| 1.1 DDL        | `sql/01_schema.sql` con 14 tablas, PK, FK, CHECK, UNIQUE, DEFAULT                    |
| 1.2 DML        | CRUD en todos los módulos con INSERT, SELECT, UPDATE, DELETE                         |
| 1.3 Integridad | `CHECK` complejos (precio, stock, condicionales de canal), `ON DELETE` diferenciados |
| 1.4 Índices    | 8 índices estratégicos justificados con `EXPLAIN`                                    |

### Unidad 2 — Operaciones de manipulación de datos

| Tema             | Implementación                                                    |
| :--------------- | :---------------------------------------------------------------- |
| 2.1 JOINs        | INNER, LEFT en listados, reportes, dashboard                      |
| 2.2 Subconsultas | `IN`, `EXISTS`, correlacionadas, tablas derivadas                 |
| 2.3 Conjuntos    | `UNION` en estadísticas de dashboard                              |
| 2.4 Agregación   | `COUNT`, `SUM`, `AVG`, `GROUP BY`, `HAVING` en todos los reportes |

### Unidad 3 — Administración de bases de datos

| Tema              | Implementación                                                                         |
| :---------------- | :------------------------------------------------------------------------------------- |
| 3.1 Seguridad     | 6 usuarios MySQL, 5 roles, control en 3 capas (UI + PHP + MySQL)                       |
| 3.2 Recuperación  | Scripts 06/07 + panel de administración de backups + RTO/RPO documentados              |
| 3.3 Transacciones | `beginTransaction`/`commit`/`rollBack` en ventas + cancelación con devolución de stock |

### Unidad 4 — SQL Procedural

| Tema               | Implementación                                                                       |
| :----------------- | :----------------------------------------------------------------------------------- |
| 4.1 Procedimientos | 10 `sp_reporte_*` con `IN`, `OUT`, `CURSOR`, `SIGNAL`, `HANDLER`                     |
| 4.2 Triggers       | 3 triggers en ventas (validar, descontar stock, devolver en cancelación) + auditoría |

---

## Arquitectura de seguridad

El proyecto demuestra **defensa en profundidad** con tres capas independientes:

### Capa 1 — Interfaz (UI)

Los botones y enlaces se ocultan según el rol. Un cajero **no ve** el botón "Editar" de medicamentos.

### Capa 2 — Aplicación (PHP)

Cada acción del controlador valida con `requiereRol(...)`. Aunque alguien escriba la URL manualmente, el PHP responde **403 Acceso denegado**.

### Capa 3 — Motor (MySQL)

Cada rol de la app se conecta con un **usuario MySQL distinto**:

| Rol app      | Usuario MySQL      | Privilegios                                    |
| :----------- | :----------------- | :--------------------------------------------- |
| admin        | `app_admin`        | Todos sobre `vidaplus.*`                       |
| farmacéutico | `app_farmaceutico` | SELECT global + escritura en catálogo y ventas |
| cajero       | `app_cajero`       | SELECT global + INSERT en ventas/detalle/pagos |
| auditor      | `app_auditor`      | Solo SELECT                                    |
| cliente      | `app_portal`       | SELECT sobre vistas + INSERT en ventas propias |

**Prueba de la capa 3:** incluso si alguien hackeara la sesión y forzara el rol de admin, la conexión a MySQL seguiría siendo `app_cajero` y el motor rechazaría el `UPDATE medicamentos` con `ERROR 1142`.

### Triggers con DEFINER

Los triggers usan `DEFINER = 'trigger_runner'` (con privilegios elevados) para poder ejecutar operaciones que el usuario que los dispara no tiene permiso de hacer (como `UPDATE medicamentos` desde un INSERT de cajero).

---

## Solución de problemas

### Error 1045 — Access denied al conectar

Verifica que los usuarios de MySQL existan:

```sql
SELECT user, host FROM mysql.user WHERE user LIKE 'app_%';
```

Debe devolver 6 usuarios. Si falta alguno, ejecuta `sql/03_users_roles.sql` como `root`.

### Error 3819 — CHECK constraint violated

MySQL **8.0.16+** es obligatorio. Verifica con `SELECT VERSION();`. Si es menor, actualiza MySQL.

### Error 1142 — Command denied for trigger_runner

Ejecuta:

```sql
GRANT TRIGGER ON vidaplus.* TO 'trigger_runner'@'localhost';
FLUSH PRIVILEGES;
```

### Error 1452 — Foreign key constraint fails al insertar detalle_compras

Los IDs de medicamentos no existen en la tabla `medicamentos`. Verifica con:

```sql
SELECT MIN(id_medicamento), MAX(id_medicamento), COUNT(*) FROM medicamentos;
```

### No puedo iniciar sesión como cliente

El cliente debe tener `password_hash` asignado. Verifica:

```sql
SELECT email, password_hash IS NOT NULL AS tiene_password FROM clientes;
```

Si es `0`, edita el cliente desde el admin y asígnale una contraseña.

### El rol aparece mal después del login

Verifica el `id_rol` real:

```sql
SELECT u.username, r.nombre AS rol
FROM usuarios u
INNER JOIN roles r ON u.id_rol = r.id_rol;
```

Corrige con `UPDATE usuarios SET id_rol = X WHERE username = 'Y';`.

### Los scripts `.sh` no corren en Windows

Los scripts de backup (`06_backup.sh` y `07_restore.sh`) requieren Git Bash o WSL. En Windows puro:

1. Instala **Git for Windows** (`https://git-scm.com/`).
2. Ejecuta con: `"C:\Program Files\Git\bin\bash.exe" sql/06_backup.sh full`
3. O usa el `.bat` wrapper (pendiente de implementar).

---

## Licencia

Proyecto de **uso educativo**. Puedes usarlo, modificarlo y adaptarlo con fines académicos citando la fuente.

---

**Última actualización:** Noviembre 2026
