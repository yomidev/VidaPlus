-- ============================================================
-- FARMACIA VIDAPLUS — Usuarios y roles de MySQL
-- Archivo: 03_users_roles.sql
-- Requiere: MySQL 8.0.16+ (roles nativos)
-- EJECUTAR COMO root
-- ============================================================

-- ============================================================
-- SECCIÓN 1: CREAR ROLES DE MySQL
-- ============================================================
-- Nota: en MySQL 8.0 los roles NO llevan @host.
-- Son globales. Solo los usuarios llevan @host.

DROP ROLE IF EXISTS 'rol_admin';
DROP ROLE IF EXISTS 'rol_farmaceutico';
DROP ROLE IF EXISTS 'rol_cajero';
DROP ROLE IF EXISTS 'rol_auditor';
DROP ROLE IF EXISTS 'rol_portal';

CREATE ROLE 'rol_admin';
CREATE ROLE 'rol_farmaceutico';
CREATE ROLE 'rol_cajero';
CREATE ROLE 'rol_auditor';
CREATE ROLE 'rol_portal';

-- ============================================================
-- SECCIÓN 2: OTORGAR PRIVILEGIOS A CADA ROL
-- ============================================================

-- ------------------------------------------------------------
-- ROL_ADMIN: control total del esquema (solo para tareas DBA)
-- ------------------------------------------------------------
GRANT ALL PRIVILEGES ON vidaplus.* TO 'rol_admin';

-- ------------------------------------------------------------
-- ROL_FARMACEUTICO: lectura global + escritura en transacciones
-- y en medicamentos (para ajustes manuales de stock, precios)
-- ------------------------------------------------------------
GRANT SELECT ON vidaplus.* TO 'rol_farmaceutico';
GRANT INSERT, UPDATE ON vidaplus.ventas           TO 'rol_farmaceutico';
GRANT INSERT          ON vidaplus.detalle_ventas  TO 'rol_farmaceutico';
GRANT INSERT          ON vidaplus.pagos           TO 'rol_farmaceutico';
GRANT INSERT, UPDATE  ON vidaplus.medicamentos    TO 'rol_farmaceutico';
GRANT INSERT          ON vidaplus.compras         TO 'rol_farmaceutico';
GRANT INSERT          ON vidaplus.detalle_compras TO 'rol_farmaceutico';
GRANT UPDATE          ON vidaplus.compras         TO 'rol_farmaceutico';

-- ------------------------------------------------------------
-- ROL_CAJERO: lectura global + SOLO INSERT en transacciones
-- NO puede modificar medicamentos ni precios.
-- El descuento de stock lo hará el trigger con privilegios
-- elevados (ver sección 5).
-- ------------------------------------------------------------
GRANT SELECT ON vidaplus.* TO 'rol_cajero';
GRANT INSERT ON vidaplus.ventas          TO 'rol_cajero';
GRANT INSERT ON vidaplus.detalle_ventas  TO 'rol_cajero';
GRANT INSERT ON vidaplus.pagos           TO 'rol_cajero';

-- ------------------------------------------------------------
-- ROL_AUDITOR: SOLO lectura de todo el esquema
-- ------------------------------------------------------------
GRANT SELECT ON vidaplus.* TO 'rol_auditor';

-- ------------------------------------------------------------
-- ROL_PORTAL: cliente del portal en línea
-- Solo puede leer la vista de catálogo y crear sus pedidos
-- ------------------------------------------------------------
GRANT SELECT ON vidaplus.categorias              TO 'rol_portal';
GRANT SELECT ON vidaplus.laboratorios            TO 'rol_portal';
GRANT SELECT ON vidaplus.medicamentos            TO 'rol_portal';
GRANT INSERT ON vidaplus.ventas                  TO 'rol_portal';
GRANT INSERT ON vidaplus.detalle_ventas          TO 'rol_portal';
GRANT INSERT ON vidaplus.pagos                   TO 'rol_portal';
GRANT SELECT ON vidaplus.direcciones_cliente     TO 'rol_portal';
GRANT SELECT ON vidaplus.clientes                TO 'rol_portal';

-- Permisos para ejecutar procedimientos de reportes
GRANT EXECUTE ON vidaplus.* TO 'rol_admin';
GRANT EXECUTE ON vidaplus.* TO 'rol_farmaceutico';
GRANT EXECUTE ON vidaplus.* TO 'rol_auditor';

-- ============================================================
-- SECCIÓN 3: CREAR USUARIOS DE MySQL (cuentas de conexión)
-- ============================================================
-- Todos en localhost (el laboratorio no tiene red externa).
-- En producción, el host sería la IP del servidor PHP.
-- Las contraseñas cumplen política fuerte: 12+ caracteres,
-- mayúsculas, minúsculas, números y símbolos.

DROP USER IF EXISTS 'app_admin'@'localhost';
DROP USER IF EXISTS 'app_farmaceutico'@'localhost';
DROP USER IF EXISTS 'app_cajero'@'localhost';
DROP USER IF EXISTS 'app_auditor'@'localhost';
DROP USER IF EXISTS 'app_portal'@'localhost';

CREATE USER 'app_admin'@'localhost'
    IDENTIFIED BY 'Admin_VidaPlus#2026!';
CREATE USER 'app_farmaceutico'@'localhost'
    IDENTIFIED BY 'Farma_VidaPlus#2026!';
CREATE USER 'app_cajero'@'localhost'
    IDENTIFIED BY 'Caja_VidaPlus#2026!';
CREATE USER 'app_auditor'@'localhost'
    IDENTIFIED BY 'Audit_VidaPlus#2026!';
CREATE USER 'app_portal'@'localhost'
    IDENTIFIED BY 'Portal_VidaPlus#2026!';

-- ============================================================
-- SECCIÓN 4: ASIGNAR ROLES A USUARIOS
-- ============================================================

GRANT 'rol_admin'         TO 'app_admin'@'localhost';
GRANT 'rol_farmaceutico'  TO 'app_farmaceutico'@'localhost';
GRANT 'rol_cajero'        TO 'app_cajero'@'localhost';
GRANT 'rol_auditor'       TO 'app_auditor'@'localhost';
GRANT 'rol_portal'        TO 'app_portal'@'localhost';

-- Establecer rol por defecto (se activa automáticamente al conectar)
SET DEFAULT ROLE 'rol_admin'        TO 'app_admin'@'localhost';
SET DEFAULT ROLE 'rol_farmaceutico' TO 'app_farmaceutico'@'localhost';
SET DEFAULT ROLE 'rol_cajero'       TO 'app_cajero'@'localhost';
SET DEFAULT ROLE 'rol_auditor'      TO 'app_auditor'@'localhost';
SET DEFAULT ROLE 'rol_portal'       TO 'app_portal'@'localhost';

-- ============================================================
-- SECCIÓN 5: USUARIO DEDICADO A TRIGGERS (DEFINER)
-- ============================================================
-- Los triggers se ejecutan con los privilegios del DEFINER.
-- Este usuario solo existe para que los triggers puedan
-- modificar tablas que el usuario que los dispara no puede.

DROP USER IF EXISTS 'trigger_runner'@'localhost';

CREATE USER 'trigger_runner'@'localhost'
    IDENTIFIED BY 'Trig_VidaPlus#2026!';

GRANT SELECT, INSERT, UPDATE ON vidaplus.* TO 'trigger_runner'@'localhost';
GRANT TRIGGER ON vidaplus.* TO 'trigger_runner'@'localhost';
FLUSH PRIVILEGES;

-- ============================================================
-- SECCIÓN 6: APLICAR CAMBIOS
-- ============================================================

FLUSH PRIVILEGES;

DROP USER IF EXISTS 'app_login'@'localhost';
CREATE USER 'app_login'@'localhost' IDENTIFIED BY 'Login_VidaPlus#2026!';

GRANT SELECT           ON vidaplus.usuarios TO 'app_login'@'localhost';
GRANT SELECT           ON vidaplus.roles    TO 'app_login'@'localhost';
GRANT SELECT           ON vidaplus.clientes TO 'app_login'@'localhost';
GRANT UPDATE(ultimo_login) ON vidaplus.usuarios TO 'app_login'@'localhost';

FLUSH PRIVILEGES;

-- ============================================================
-- SECCIÓN 7: VERIFICACIÓN
-- ============================================================

SELECT 'Usuarios y roles creados correctamente' AS estado;

-- Listar usuarios creados
SELECT user, host, account_locked 
FROM mysql.user 
WHERE user LIKE 'app_%' OR user = 'trigger_runner'
ORDER BY user;

-- Ver qué roles tiene cada usuario
SELECT TO_USER AS usuario, FROM_USER AS rol
FROM mysql.role_edges
ORDER BY usuario;

-- Verificar privilegios de cada rol (uno por uno)
SHOW GRANTS FOR 'app_admin'@'localhost';
SHOW GRANTS FOR 'app_farmaceutico'@'localhost';
SHOW GRANTS FOR 'app_cajero'@'localhost';
SHOW GRANTS FOR 'app_auditor'@'localhost';
SHOW GRANTS FOR 'app_portal'@'localhost';

-- Usuario dedicado solo para backups
DROP USER IF EXISTS 'backup_user'@'localhost';
CREATE USER 'backup_user'@'localhost' IDENTIFIED BY 'Backup_VidaPlus#2026!';

-- Privilegios necesarios para mysqldump
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER, PROCESS
    ON vidaplus.* TO 'backup_user'@'localhost';

FLUSH PRIVILEGES;

-- Verificar
SHOW GRANTS FOR 'backup_user'@'localhost';

-- Lectura necesaria
GRANT SELECT ON vidaplus.paises              TO 'rol_portal';
GRANT SELECT ON vidaplus.ventas               TO 'rol_portal';
GRANT SELECT ON vidaplus.detalle_ventas       TO 'rol_portal';
GRANT SELECT ON vidaplus.envios               TO 'rol_portal';
GRANT SELECT ON vidaplus.pagos                TO 'rol_portal';

-- Escritura controlada
GRANT INSERT, UPDATE ON vidaplus.direcciones_cliente TO 'rol_portal';
GRANT INSERT         ON vidaplus.envios              TO 'rol_portal';

FLUSH PRIVILEGES;

-- Verificar
SHOW GRANTS FOR 'app_portal'@'localhost';

GRANT SELECT ON vidaplus.medicamentos TO 'app_portal'@'localhost';
GRANT SELECT ON vidaplus.categorias   TO 'app_portal'@'localhost';
GRANT SELECT ON vidaplus.laboratorios TO 'app_portal'@'localhost';
FLUSH PRIVILEGES;

USE vidaplus;

-- El cliente necesita INSERTAR en ventas, detalles, pagos (además de envíos que ya tenía)
GRANT INSERT ON vidaplus.ventas          TO 'rol_portal';
GRANT INSERT ON vidaplus.detalle_ventas  TO 'rol_portal';
GRANT INSERT ON vidaplus.pagos           TO 'rol_portal';

FLUSH PRIVILEGES;

-- Verificar
SHOW GRANTS FOR 'app_portal'@'localhost';

USE vidaplus;

-- ============================================================
-- PERMISOS PARA app_farmaceutico (proveedores, compras, envíos)
-- ============================================================
GRANT SELECT, INSERT, UPDATE ON vidaplus.proveedores     TO 'rol_farmaceutico';
GRANT SELECT, INSERT, UPDATE ON vidaplus.compras          TO 'rol_farmaceutico';
GRANT SELECT, INSERT, UPDATE ON vidaplus.detalle_compras  TO 'rol_farmaceutico';
GRANT SELECT, UPDATE         ON vidaplus.envios           TO 'rol_farmaceutico';
GRANT SELECT, UPDATE         ON vidaplus.ventas           TO 'rol_farmaceutico';

FLUSH PRIVILEGES;