USE vidaplus;

-- ============================================================
-- TRIGGER 1: Validaciones al insertar detalle_ventas
--   - Si canal=online y medicamento requiere receta → SIGNAL
--   - Si stock insuficiente → SIGNAL
-- ============================================================
DROP TRIGGER IF EXISTS tr_detalle_ventas_validaciones;

DELIMITER $$

CREATE DEFINER = 'trigger_runner'@'localhost'
TRIGGER tr_detalle_ventas_validaciones
BEFORE INSERT ON detalle_ventas
FOR EACH ROW
BEGIN
    DECLARE v_canal     VARCHAR(10);
    DECLARE v_receta    TINYINT;
    DECLARE v_stock     INT UNSIGNED;
    DECLARE v_nombre    VARCHAR(120);
    DECLARE v_mensaje   VARCHAR(128);

    -- Obtener datos de la venta
    SELECT canal
    INTO v_canal
    FROM ventas
    WHERE id_venta = NEW.id_venta;

    -- Obtener datos del medicamento
    SELECT stock, requiere_receta, nombre
    INTO v_stock, v_receta, v_nombre
    FROM medicamentos
    WHERE id_medicamento = NEW.id_medicamento;

    -- Validación 1: medicamentos con receta en ventas online
    IF v_canal = 'online' AND v_receta = 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Los medicamentos con receta no se pueden vender en línea';
    END IF;

    -- Validación 2: verificar stock disponible
    IF v_stock < NEW.cantidad THEN

        SET v_mensaje = CONCAT(
            'Stock insuficiente para "',
            v_nombre,
            '". Disponible: ',
            v_stock
        );

        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = v_mensaje;

    END IF;

END$$

DELIMITER ;

-- ============================================================
-- TRIGGER 2: Descontar stock + auditar
-- ============================================================
DROP TRIGGER IF EXISTS tr_detalle_ventas_descontar;

DELIMITER $$

CREATE DEFINER = 'trigger_runner'@'localhost' TRIGGER tr_detalle_ventas_descontar
AFTER INSERT ON detalle_ventas
FOR EACH ROW
BEGIN
    -- Descontar stock
    UPDATE medicamentos
       SET stock = stock - NEW.cantidad
     WHERE id_medicamento = NEW.id_medicamento;

    -- Auditoría
    INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
    VALUES (USER(), 'medicamentos', 'UPDATE_STOCK', NEW.id_medicamento,
            CONCAT('Descuento por venta #', NEW.id_venta, ': -', NEW.cantidad));
END$$

DELIMITER ;

-- ============================================================
-- TRIGGER 3: Devolver stock al cancelar venta
-- ============================================================
DROP TRIGGER IF EXISTS tr_ventas_cancelar_devolver_stock;

DELIMITER $$

CREATE DEFINER = 'trigger_runner'@'localhost' TRIGGER tr_ventas_cancelar_devolver_stock
AFTER UPDATE ON ventas
FOR EACH ROW
BEGIN
    IF OLD.estado != 'cancelada' AND NEW.estado = 'cancelada' THEN
        -- Devolver stock de todos los detalles
        UPDATE medicamentos m
          INNER JOIN detalle_ventas dv ON m.id_medicamento = dv.id_medicamento
           SET m.stock = m.stock + dv.cantidad
         WHERE dv.id_venta = NEW.id_venta;

        -- Auditoría
        INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
        VALUES (USER(), 'ventas', 'CANCELACION', NEW.id_venta,
                CONCAT('Venta ', NEW.folio, ' cancelada; stock devuelto'));
    END IF;
END$$

DELIMITER ;

-- Verificar
SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING, EVENT_OBJECT_TABLE
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'vidaplus';

-- ============================================================
-- TRIGGER 1: Al marcar una compra como 'recibida', sumar stock
-- ============================================================
DROP TRIGGER IF EXISTS tr_compras_recibir_sumar_stock;

DELIMITER $$
CREATE DEFINER = 'trigger_runner'@'localhost' TRIGGER tr_compras_recibir_sumar_stock
AFTER UPDATE ON compras
FOR EACH ROW
BEGIN
    IF OLD.estado != 'recibida' AND NEW.estado = 'recibida' THEN
        -- Sumar el stock de todos los detalles
        UPDATE medicamentos m
          INNER JOIN detalle_compras dc ON m.id_medicamento = dc.id_medicamento
           SET m.stock = m.stock + dc.cantidad
         WHERE dc.id_compra = NEW.id_compra;

        -- Registrar fecha de recepción si no está puesta
        IF NEW.fecha_recepcion IS NULL THEN
            UPDATE compras SET fecha_recepcion = NOW() WHERE id_compra = NEW.id_compra;
        END IF;

        -- Auditoría
        INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
        VALUES (USER(), 'compras', 'RECEPCION', NEW.id_compra,
                CONCAT('Compra ', NEW.folio, ' recibida; stock incrementado'));
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- TRIGGER 2: Si se cancela una compra recibida, restar stock
-- ============================================================
DROP TRIGGER IF EXISTS tr_compras_cancelar_restar_stock;

DELIMITER $$
CREATE DEFINER = 'trigger_runner'@'localhost' TRIGGER tr_compras_cancelar_restar_stock
AFTER UPDATE ON compras
FOR EACH ROW
BEGIN
    IF OLD.estado = 'recibida' AND NEW.estado = 'cancelada' THEN
        UPDATE medicamentos m
          INNER JOIN detalle_compras dc ON m.id_medicamento = dc.id_medicamento
           SET m.stock = GREATEST(0, m.stock - dc.cantidad)
         WHERE dc.id_compra = NEW.id_compra;

        INSERT INTO auditoria (usuario_bd, tabla_afectada, accion, id_registro, detalle)
        VALUES (USER(), 'compras', 'CANCELACION', NEW.id_compra,
                CONCAT('Compra ', NEW.folio, ' cancelada; stock devuelto'));
    END IF;
END$$
DELIMITER ;

-- Verificar
SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = 'vidaplus'
ORDER BY TRIGGER_NAME;