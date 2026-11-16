-- ============================================================
-- FARMACIA VIDAPLUS — Esquema de base de datos
-- Archivo: 01_schema.sql
-- Requiere: MySQL 8.0.16+
-- ============================================================
-- ORDEN DE EJECUCIÓN DEL PROYECTO:
--   1. 01_schema.sql                    → DDL + índices
--   2. 02_seed.sql                      → datos de prueba
--   3. 03_users_roles.sql               → usuarios y roles de MySQL
--   4. 04_triggers.sql                  → triggers
--   5. 05_procedures.sql                → procedimientos almacenados
--   6. 06_backup.sh                     → script de backup
-- ============================================================

DROP DATABASE IF EXISTS vidaplus;
CREATE DATABASE vidaplus
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_0900_ai_ci;
USE vidaplus;

-- ============================================================
-- SECCIÓN 1: CATÁLOGOS
-- ============================================================

-- 1.1 PAÍSES
CREATE TABLE paises (
    id_pais      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    iso2         CHAR(2)     NOT NULL,
    iso3         CHAR(3)     NOT NULL,
    nombre       VARCHAR(80) NOT NULL,
    nacionalidad VARCHAR(50) NOT NULL,
    codigo_tel   VARCHAR(5)  NOT NULL,
    activo       TINYINT(1)  NOT NULL DEFAULT 1,
    CONSTRAINT uq_paises_iso2    UNIQUE (iso2),
    CONSTRAINT uq_paises_iso3    UNIQUE (iso3),
    CONSTRAINT uq_paises_nombre  UNIQUE (nombre),
    CONSTRAINT chk_paises_iso2 CHECK (iso2 = UPPER(iso2) AND CHAR_LENGTH(iso2) = 2),
    CONSTRAINT chk_paises_iso3 CHECK (iso3 = UPPER(iso3) AND CHAR_LENGTH(iso3) = 3)
);

-- 1.2 ROLES
CREATE TABLE roles (
    id_rol         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(30) NOT NULL,
    descripcion    TEXT        NULL,
    nivel_acceso   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    activo         TINYINT(1)  NOT NULL DEFAULT 1,
    fecha_creacion DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_roles_nombre UNIQUE (nombre),
    CONSTRAINT chk_roles_nivel CHECK (nivel_acceso BETWEEN 1 AND 10)
);

-- 1.3 CATEGORÍAS
CREATE TABLE categorias (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(60) NOT NULL,
    descripcion  TEXT        NULL,
    activa       TINYINT(1)  NOT NULL DEFAULT 1,
    CONSTRAINT uq_categorias_nombre UNIQUE (nombre)
);

-- 1.4 LABORATORIOS
CREATE TABLE laboratorios (
    id_laboratorio INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL,
    id_pais        INT UNSIGNED NOT NULL,
    telefono       VARCHAR(15)  NULL,
    email          VARCHAR(100) NULL,
    activo         TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uq_laboratorios_nombre UNIQUE (nombre),
    CONSTRAINT uq_laboratorios_email  UNIQUE (email),
    CONSTRAINT fk_laboratorios_pais FOREIGN KEY (id_pais)
    REFERENCES paises(id_pais) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- 1.5 PROVEEDORES
CREATE TABLE proveedores (
    id_proveedor INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(100) NOT NULL,
    id_pais      INT UNSIGNED NOT NULL,
    contacto     VARCHAR(100) NOT NULL,
    telefono     VARCHAR(15)  NOT NULL,
    email        VARCHAR(100) NULL,
    activo       TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uq_proveedores_nombre UNIQUE (nombre),
    CONSTRAINT uq_proveedores_email  UNIQUE (email),
    CONSTRAINT fk_proveedores_pais FOREIGN KEY (id_pais)
      REFERENCES paises(id_pais) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ============================================================
-- SECCIÓN 2: ACTORES
-- ============================================================

-- 2.1 USUARIOS (empleados)
CREATE TABLE usuarios (
    id_usuario      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(120) NOT NULL,
    email           VARCHAR(100) NOT NULL,
    id_rol          INT UNSIGNED NOT NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_login    DATETIME     NULL,
    fecha_creacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_usuarios_username UNIQUE (username),
    CONSTRAINT uq_usuarios_email    UNIQUE (email),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol)
      REFERENCES roles(id_rol) ON DELETE RESTRICT ON UPDATE CASCADE
);
use vidaplus;
-- 2.2 CLIENTES (login propio del portal en línea)
CREATE TABLE clientes (
    id_cliente       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(60)  NOT NULL,
    apellido         VARCHAR(60)  NOT NULL,
    email            VARCHAR(100) NOT NULL,
    telefono         VARCHAR(15)  NULL,
    id_pais          INT UNSIGNED NOT NULL,
    fecha_nacimiento DATE         NULL,
    puntos_lealtad   INT UNSIGNED NOT NULL DEFAULT 0,
    password_hash    VARCHAR(255) NULL,
    fecha_registro   DATE         NOT NULL DEFAULT (CURRENT_DATE),
    activo           TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uq_clientes_email UNIQUE (email),
    CONSTRAINT fk_clientes_pais FOREIGN KEY (id_pais)
      REFERENCES paises(id_pais) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_clientes_email CHECK (email LIKE '%@%.%')
);

-- 2.3 DIRECCIONES_CLIENTE (envíos del canal online)
CREATE TABLE direcciones_cliente (
    id_direccion      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cliente        INT UNSIGNED NOT NULL,
    alias             VARCHAR(30)  NOT NULL,
    calle             VARCHAR(200) NOT NULL,
    colonia           VARCHAR(100) NULL,
    ciudad            VARCHAR(80)  NOT NULL,
    estado_provincia  VARCHAR(80)  NOT NULL,
    codigo_postal     VARCHAR(10)  NOT NULL,
    id_pais           INT UNSIGNED NOT NULL,
    es_predeterminada TINYINT(1)   NOT NULL DEFAULT 0,
    activa            TINYINT(1)   NOT NULL DEFAULT 1,
    fecha_creacion    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_direcciones_cliente FOREIGN KEY (id_cliente)
        REFERENCES clientes(id_cliente) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_direcciones_pais FOREIGN KEY (id_pais)
        REFERENCES paises(id_pais) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- ============================================================
-- SECCIÓN 3: PRODUCTO
-- ============================================================

-- 3.1 MEDICAMENTOS
CREATE TABLE medicamentos (
    id_medicamento  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_barras   VARCHAR(20)   NOT NULL,
    nombre          VARCHAR(120)  NOT NULL,
    descripcion     TEXT          NULL,
    id_categoria    INT UNSIGNED  NOT NULL,
    id_laboratorio  INT UNSIGNED  NOT NULL,
    presentacion    VARCHAR(50)   NOT NULL,
    concentracion   VARCHAR(30)   NULL,
    precio_compra   DECIMAL(10,2) NOT NULL,
    precio_venta    DECIMAL(10,2) NOT NULL,
    stock           INT UNSIGNED  NOT NULL DEFAULT 0,
    stock_minimo    INT UNSIGNED  NOT NULL DEFAULT 10,
    requiere_receta TINYINT(1)    NOT NULL DEFAULT 0,
    activo          TINYINT(1)    NOT NULL DEFAULT 1,
    CONSTRAINT uq_medicamentos_codigo UNIQUE (codigo_barras),
    CONSTRAINT fk_medicamentos_categoria FOREIGN KEY (id_categoria)
        REFERENCES categorias(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_medicamentos_laboratorio FOREIGN KEY (id_laboratorio)
        REFERENCES laboratorios(id_laboratorio) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_medicamentos_precio_compra CHECK (precio_compra > 0),
    CONSTRAINT chk_medicamentos_precio_venta  CHECK (precio_venta > precio_compra)
);

-- ============================================================
-- SECCIÓN 4: TRANSACCIONES
-- ============================================================
use vidaplus;
-- 4.1 VENTAS
CREATE TABLE ventas (
    id_venta           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folio              VARCHAR(20)  NOT NULL,
    canal              ENUM('local','online') NOT NULL DEFAULT 'local',
    id_cliente         INT UNSIGNED NULL COMMENT 'NULL = público general (solo local)',
    id_usuario         INT UNSIGNED NOT NULL,
    id_direccion_envio INT UNSIGNED NULL,
    fecha_venta        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal           DECIMAL(12,2) NOT NULL DEFAULT 0,
    iva                DECIMAL(12,2) NOT NULL DEFAULT 0,
    descuento          DECIMAL(12,2) NOT NULL DEFAULT 0,
    costo_envio        DECIMAL(10,2) NOT NULL DEFAULT 0,
    total              DECIMAL(12,2) NOT NULL DEFAULT 0,
    estado             ENUM('pendiente','completada','cancelada') NOT NULL DEFAULT 'completada',
    CONSTRAINT uq_ventas_folio UNIQUE (folio),
    CONSTRAINT fk_ventas_cliente FOREIGN KEY (id_cliente)
        REFERENCES clientes(id_cliente) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ventas_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_ventas_direccion FOREIGN KEY (id_direccion_envio)
        REFERENCES direcciones_cliente(id_direccion) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_ventas_subtotal    CHECK (subtotal >= 0),
    CONSTRAINT chk_ventas_iva         CHECK (iva >= 0),
    CONSTRAINT chk_ventas_descuento   CHECK (descuento >= 0),
    CONSTRAINT chk_ventas_costo_envio CHECK (costo_envio >= 0),
    CONSTRAINT chk_ventas_total       CHECK (total >= 0)
);

-- 4.2 DETALLE_VENTAS
CREATE TABLE detalle_ventas (
    id_detalle_venta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta         INT UNSIGNED NOT NULL,
    id_medicamento   INT UNSIGNED NOT NULL,
    cantidad         INT UNSIGNED NOT NULL,
    precio_unitario  DECIMAL(10,2) NOT NULL,
    subtotal         DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_detventa_venta FOREIGN KEY (id_venta)
        REFERENCES ventas(id_venta) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detventa_medicamento FOREIGN KEY (id_medicamento)
        REFERENCES medicamentos(id_medicamento) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_detventa_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_detventa_precio   CHECK (precio_unitario > 0),
    CONSTRAINT chk_detventa_subtotal CHECK (subtotal >= 0)
);

-- 4.3 PAGOS
CREATE TABLE pagos (
    id_pago    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta   INT UNSIGNED NOT NULL,
    monto      DECIMAL(12,2) NOT NULL,
    metodo     ENUM('efectivo','tarjeta','transferencia','paypal','oxxo') NOT NULL,
    referencia VARCHAR(50)  NULL,
    fecha_pago DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagos_venta FOREIGN KEY (id_venta)
        REFERENCES ventas(id_venta) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_pagos_monto CHECK (monto > 0)
);

-- 4.4 ENVIOS
CREATE TABLE envios (
    id_envio               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_venta               INT UNSIGNED NOT NULL,
    empresa_paqueteria     VARCHAR(80) NOT NULL,
    numero_guia            VARCHAR(50) NULL,
    estado                 ENUM('pendiente','preparando','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
    fecha_envio            DATETIME    NULL,
    fecha_entrega_estimada DATE        NULL,
    fecha_entrega_real     DATETIME    NULL,
    notas                  TEXT        NULL,
    CONSTRAINT uq_envios_venta UNIQUE (id_venta),
    CONSTRAINT uq_envios_guia  UNIQUE (numero_guia),
    CONSTRAINT fk_envios_venta FOREIGN KEY (id_venta)
        REFERENCES ventas(id_venta) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 4.5 COMPRAS
CREATE TABLE compras (
    id_compra       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folio           VARCHAR(20)  NOT NULL,
    id_proveedor    INT UNSIGNED NOT NULL,
    id_usuario      INT UNSIGNED NOT NULL,
    fecha_compra    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    iva             DECIMAL(12,2) NOT NULL DEFAULT 0,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0,
    estado          ENUM('pendiente','recibida','cancelada') NOT NULL DEFAULT 'pendiente',
    fecha_recepcion DATETIME     NULL,
    CONSTRAINT uq_compras_folio UNIQUE (folio),
    CONSTRAINT fk_compras_proveedor FOREIGN KEY (id_proveedor)
        REFERENCES proveedores(id_proveedor) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_compras_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_compras_subtotal CHECK (subtotal >= 0),
    CONSTRAINT chk_compras_iva      CHECK (iva >= 0),
    CONSTRAINT chk_compras_total    CHECK (total >= 0)
);

-- 4.6 DETALLE_COMPRAS
CREATE TABLE detalle_compras (
    id_detalle_compra INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_compra         INT UNSIGNED NOT NULL,
    id_medicamento    INT UNSIGNED NOT NULL,
    cantidad          INT UNSIGNED NOT NULL,
    precio_unitario   DECIMAL(10,2) NOT NULL,
    subtotal          DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_detcompra_compra FOREIGN KEY (id_compra)
        REFERENCES compras(id_compra) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detcompra_medicamento FOREIGN KEY (id_medicamento)
        REFERENCES medicamentos(id_medicamento) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_detcompra_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_detcompra_precio   CHECK (precio_unitario > 0),
    CONSTRAINT chk_detcompra_subtotal CHECK (subtotal >= 0)
);

-- ============================================================
-- SECCIÓN 5: SOPORTE
-- ============================================================
USE vidaplus;
-- 5.1 AUDITORÍA
CREATE TABLE auditoria (
    id_auditoria   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_bd     VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(50)  NOT NULL,
    accion         VARCHAR(30)  NOT NULL,
    id_registro    INT UNSIGNED NULL,
    detalle        TEXT         NULL,
    fecha_evento   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 5.2 ALERTAS_STOCK
CREATE TABLE alertas_stock (
    id_alerta      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_medicamento INT UNSIGNED NOT NULL,
    stock_actual   INT UNSIGNED NOT NULL,
    stock_minimo   INT UNSIGNED NOT NULL,
    fecha_alerta   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atendida       TINYINT(1)   NOT NULL DEFAULT 0,
    CONSTRAINT fk_alertas_medicamento FOREIGN KEY (id_medicamento)
        REFERENCES medicamentos(id_medicamento) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- SECCIÓN 6: ÍNDICES ESTRATÉGICOS
-- ============================================================
-- Nota: MySQL crea índices automáticos en columnas con FK y UNIQUE.
-- Aquí solo los índices ADICIONALES que justifican optimización.

-- Búsqueda por nombre de medicamento (búsquedas frecuentes en POS)
CREATE INDEX idx_medicamentos_nombre ON medicamentos (nombre);

-- Búsqueda por rango de fechas en ventas (reportes)
CREATE INDEX idx_ventas_fecha ON ventas (fecha_venta);

-- Búsqueda por estado de venta (filtros de canceladas, pendientes)
CREATE INDEX idx_ventas_estado ON ventas (estado);

-- Búsqueda por canal (reportes de local vs online)
CREATE INDEX idx_ventas_canal ON ventas (canal);

-- Auditoría por rango de fechas (reportes de seguridad)
CREATE INDEX idx_auditoria_fecha ON auditoria (fecha_evento);

-- Auditoría por tabla afectada (investigación de incidentes)
CREATE INDEX idx_auditoria_tabla ON auditoria (tabla_afectada);

-- Clientes por país (reportes geográficos)
CREATE INDEX idx_clientes_pais ON clientes (id_pais);

-- Alertas pendientes (panel de inventario)
CREATE INDEX idx_alertas_atendida ON alertas_stock (atendida);

-- ============================================================
-- SECCIÓN 8: SEED MÍNIMO DE CATÁLOGOS
-- ============================================================
-- Solo catálogos indispensables para que la app arranque.

-- Roles internos
INSERT INTO roles (nombre, descripcion, nivel_acceso) VALUES
('admin',        'Administrador del sistema con acceso total', 10),
('farmaceutico', 'Farmacéutico con licencia para dispensar',    7),
('cajero',       'Cajero de mostrador',                         4),
('auditor',      'Auditor de solo lectura',                     2);

-- Categorías
INSERT INTO categorias (nombre, descripcion) VALUES
('Analgésicos',      'Medicamentos para el dolor'),
('Antiinflamatorios','Reducen la inflamación'),
('Antibióticos',     'Combaten infecciones bacterianas'),
('Antihistamínicos', 'Para alergias'),
('Gastrointestinal', 'Para problemas digestivos'),
('Vitaminas',        'Suplementos nutricionales'),
('Dermatológicos',   'Cuidado de la piel');