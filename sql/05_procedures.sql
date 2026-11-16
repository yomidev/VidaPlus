USE vidaplus;

-- ============================================================
-- P1: Resumen del dashboard (OUT params)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_reporte_resumen_dashboard;

DELIMITER $$
CREATE PROCEDURE sp_reporte_resumen_dashboard(
    OUT p_ventas_hoy       INT,
    OUT p_ingreso_hoy      DECIMAL(12,2),
    OUT p_ventas_mes       INT,
    OUT p_ingreso_mes      DECIMAL(12,2),
    OUT p_clientes_total   INT,
    OUT p_stock_bajo       INT,
    OUT p_ticket_mes       DECIMAL(12,2)
)
BEGIN
    SELECT COUNT(*), COALESCE(SUM(total),0)
      INTO p_ventas_hoy, p_ingreso_hoy
      FROM ventas
     WHERE DATE(fecha_venta) = CURDATE() AND estado='completada';

    SELECT COUNT(*), COALESCE(SUM(total),0), COALESCE(AVG(total),0)
      INTO p_ventas_mes, p_ingreso_mes, p_ticket_mes
      FROM ventas
     WHERE DATE_FORMAT(fecha_venta,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
       AND estado='completada';

    SELECT COUNT(*) INTO p_clientes_total FROM clientes WHERE activo=1;
    SELECT COUNT(*) INTO p_stock_bajo FROM medicamentos WHERE activo=1 AND stock < stock_minimo;
END$$
DELIMITER ;

-- ============================================================
-- P2: Ventas por período (día a día)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_reporte_ventas_periodo;

DELIMITER $$
CREATE PROCEDURE sp_reporte_ventas_periodo(IN p_desde DATE, IN p_hasta DATE)
BEGIN
    SELECT 
        DATE(fecha_venta) AS fecha,
        COUNT(*) AS num_ventas,
        SUM(total) AS ingreso_total,
        AVG(total) AS ticket_promedio,
        COUNT(DISTINCT id_cliente) AS clientes_unicos,
        SUM(canal='local')  AS ventas_local,
        SUM(canal='online') AS ventas_online
    FROM ventas
    WHERE DATE(fecha_venta) BETWEEN p_desde AND p_hasta
      AND estado='completada'
    GROUP BY DATE(fecha_venta)
    ORDER BY fecha DESC;
END$$
DELIMITER ;

-- ============================================================
-- P3: Top N medicamentos más vendidos
-- ============================================================
DROP PROCEDURE IF EXISTS sp_reporte_top_medicamentos;

DELIMITER $$
CREATE PROCEDURE sp_reporte_top_medicamentos(IN p_limite INT)
BEGIN
    SELECT 
        m.id_medicamento,
        m.nombre,
        m.presentacion,
        c.nombre AS categoria,
        SUM(dv.cantidad) AS unidades_vendidas,
        COUNT(DISTINCT dv.id_venta) AS num_ventas,
        SUM(dv.subtotal) AS ingreso_generado
    FROM detalle_ventas dv
    INNER JOIN medicamentos m ON dv.id_medicamento = m.id_medicamento
    INNER JOIN categorias c ON m.id_categoria = c.id_categoria
    INNER JOIN ventas v ON dv.id_venta = v.id_venta
    WHERE v.estado='completada'
    GROUP BY m.id_medicamento
    ORDER BY unidades_vendidas DESC
    LIMIT p_limite;
END$$
DELIMITER ;

-- ============================================================
-- P4: Stock bajo
-- ============================================================
DROP PROCEDURE IF EXISTS sp_reporte_stock_bajo;

DELIMITER $$
CREATE PROCEDURE sp_reporte_stock_bajo()
BEGIN
    SELECT 
        m.id_medicamento,
        m.nombre,
        m.presentacion,
        c.nombre AS categoria,
        l.nombre AS laboratorio,
        m.stock,
        m.stock_minimo,
        (m.stock_minimo - m.stock) AS faltante,
        m.precio_compra,
        (m.stock_minimo - m.stock) * m.precio_compra AS inversion_reposicion
    FROM medicamentos m
    INNER JOIN categorias   c ON m.id_categoria   = c.id_categoria
    INNER JOIN laboratorios l ON m.id_laboratorio = l.id_laboratorio
    WHERE m.activo = 1 AND m.stock < m.stock_minimo
    ORDER BY faltante DESC;
END$$
DELIMITER ;

-- ============================================================
-- P5: Ventas por categoría con porcentaje
-- ============================================================
DROP PROCEDURE IF EXISTS sp_reporte_ventas_categoria;

DELIMITER $$
CREATE PROCEDURE sp_reporte_ventas_categoria(IN p_desde DATE, IN p_hasta DATE)
BEGIN
    DECLARE v_total_periodo DECIMAL(12,2);
    
    SELECT COALESCE(SUM(total),0) INTO v_total_periodo
      FROM ventas
     WHERE DATE(fecha_venta) BETWEEN p_desde AND p_hasta
       AND estado='completada';

    SELECT 
        c.id_categoria,
        c.nombre AS categoria,
        COUNT(DISTINCT dv.id_venta) AS num_ventas,
        SUM(dv.cantidad) AS unidades,
        SUM(dv.subtotal) AS ingreso_total,
        CASE WHEN v_total_periodo > 0
             THEN ROUND((SUM(dv.subtotal) / v_total_periodo) * 100, 2)
             ELSE 0
        END AS porcentaje_ingreso
    FROM detalle_ventas dv
    INNER JOIN medicamentos m ON dv.id_medicamento = m.id_medicamento
    INNER JOIN categorias   c ON m.id_categoria   = c.id_categoria
    INNER JOIN ventas       v ON dv.id_venta      = v.id_venta
    WHERE DATE(v.fecha_venta) BETWEEN p_desde AND p_hasta
      AND v.estado='completada'
    GROUP BY c.id_categoria
    ORDER BY ingreso_total DESC;
END$$
DELIMITER ;

-- ============================================================
-- P6: Top N clientes
-- ============================================================
DROP PROCEDURE IF EXISTS sp_reporte_clientes_top;

DELIMITER $$
CREATE PROCEDURE sp_reporte_clientes_top(IN p_limite INT)
BEGIN
    SELECT 
        cl.id_cliente,
        CONCAT(cl.nombre,' ',cl.apellido) AS cliente,
        cl.email,
        p.nombre AS pais,
        COUNT(v.id_venta) AS num_compras,
        COALESCE(SUM(v.total),0) AS total_gastado,
        MAX(v.fecha_venta) AS ultima_compra,
        cl.puntos_lealtad
    FROM clientes cl
    INNER JOIN paises p ON cl.id_pais = p.id_pais
    INNER JOIN ventas v ON cl.id_cliente = v.id_cliente
    WHERE v.estado='completada'
    GROUP BY cl.id_cliente
    ORDER BY total_gastado DESC
    LIMIT p_limite;
END$$
DELIMITER ;

-- ============================================================
-- P7: Ventas por usuario (empleado)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_reporte_ventas_usuario;

DELIMITER $$
CREATE PROCEDURE sp_reporte_ventas_usuario(IN p_desde DATE, IN p_hasta DATE)
BEGIN
    SELECT 
        u.id_usuario,
        u.nombre_completo,
        r.nombre AS rol,
        COUNT(v.id_venta) AS num_ventas,
        COALESCE(SUM(v.total),0) AS ingreso_total,
        COALESCE(AVG(v.total),0) AS ticket_promedio
    FROM usuarios u
    INNER JOIN roles r ON u.id_rol = r.id_rol
    LEFT JOIN ventas v ON u.id_usuario = v.id_usuario
        AND DATE(v.fecha_venta) BETWEEN p_desde AND p_hasta
        AND v.estado='completada'
    GROUP BY u.id_usuario
    HAVING num_ventas > 0
    ORDER BY ingreso_total DESC;
END$$
DELIMITER ;

-- ============================================================
-- P8: Productos nunca vendidos (subconsulta NOT IN)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_reporte_productos_sin_venta;

DELIMITER $$
CREATE PROCEDURE sp_reporte_productos_sin_venta()
BEGIN
    SELECT 
        m.id_medicamento,
        m.nombre,
        m.presentacion,
        c.nombre AS categoria,
        l.nombre AS laboratorio,
        m.stock,
        m.precio_venta
    FROM medicamentos m
    INNER JOIN categorias   c ON m.id_categoria   = c.id_categoria
    INNER JOIN laboratorios l ON m.id_laboratorio = l.id_laboratorio
    WHERE m.activo = 1
      AND m.id_medicamento NOT IN (
          SELECT DISTINCT dv.id_medicamento
          FROM detalle_ventas dv
          INNER JOIN ventas v ON dv.id_venta = v.id_venta
          WHERE v.estado='completada'
      )
    ORDER BY m.nombre;
END$$
DELIMITER ;

-- ============================================================
-- P9: Clientes sin compras (LEFT JOIN + IS NULL)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_reporte_clientes_sin_compras;

DELIMITER $$
CREATE PROCEDURE sp_reporte_clientes_sin_compras()
BEGIN
    SELECT 
        cl.id_cliente,
        CONCAT(cl.nombre,' ',cl.apellido) AS cliente,
        cl.email,
        cl.telefono,
        p.nombre AS pais,
        cl.fecha_registro,
        DATEDIFF(CURDATE(), cl.fecha_registro) AS dias_registrado
    FROM clientes cl
    INNER JOIN paises p ON cl.id_pais = p.id_pais
    LEFT JOIN ventas v ON cl.id_cliente = v.id_cliente AND v.estado='completada'
    WHERE cl.activo = 1 AND v.id_venta IS NULL
    ORDER BY cl.fecha_registro DESC;
END$$
DELIMITER ;

-- ============================================================
-- P10: Comparativa mensual por categoría (usa CURSOR)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_reporte_comparativa_mensual;

DELIMITER $$

CREATE PROCEDURE sp_reporte_comparativa_mensual()
BEGIN
    -- 1. Declaración de variables
    DECLARE v_id        INT;
    DECLARE v_cat       VARCHAR(60);
    DECLARE v_actual    DECIMAL(12,2);
    DECLARE v_anterior  DECIMAL(12,2);
    DECLARE v_var       DECIMAL(8,2);
    DECLARE v_fin       BOOLEAN DEFAULT FALSE;

    -- 2. Declaración del cursor
    DECLARE cur CURSOR FOR
        SELECT id_categoria, nombre
        FROM categorias
        WHERE activa = 1;

    -- 3. Declaración del manejador
    DECLARE CONTINUE HANDLER FOR NOT FOUND
        SET v_fin = TRUE;

    -- 4. Crear tabla temporal
    DROP TEMPORARY TABLE IF EXISTS tmp_comparativa;

    CREATE TEMPORARY TABLE tmp_comparativa (
        categoria       VARCHAR(60),
        mes_actual      DECIMAL(12,2),
        mes_anterior    DECIMAL(12,2),
        variacion_pct   DECIMAL(8,2)
    );

    -- 5. Abrir cursor
    OPEN cur;

    lectura: LOOP

        FETCH cur INTO v_id, v_cat;

        IF v_fin THEN
            LEAVE lectura;
        END IF;

        -- Ventas del mes actual
        SELECT COALESCE(SUM(dv.subtotal), 0)
        INTO v_actual
        FROM detalle_ventas dv
        INNER JOIN medicamentos m
            ON dv.id_medicamento = m.id_medicamento
        INNER JOIN ventas v
            ON dv.id_venta = v.id_venta
        WHERE m.id_categoria = v_id
          AND v.estado = 'completada'
          AND DATE_FORMAT(v.fecha_venta, '%Y-%m') =
              DATE_FORMAT(CURDATE(), '%Y-%m');

        -- Ventas del mes anterior
        SELECT COALESCE(SUM(dv.subtotal), 0)
        INTO v_anterior
        FROM detalle_ventas dv
        INNER JOIN medicamentos m
            ON dv.id_medicamento = m.id_medicamento
        INNER JOIN ventas v
            ON dv.id_venta = v.id_venta
        WHERE m.id_categoria = v_id
          AND v.estado = 'completada'
          AND DATE_FORMAT(v.fecha_venta, '%Y-%m') =
              DATE_FORMAT(
                  DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
                  '%Y-%m'
              );

        -- Calcular variación porcentual
        IF v_anterior > 0 THEN
            SET v_var = ROUND(
                ((v_actual - v_anterior) / v_anterior) * 100,
                2
            );
        ELSE
            SET v_var = NULL;
        END IF;

        -- Guardar resultado
        INSERT INTO tmp_comparativa
            (categoria, mes_actual, mes_anterior, variacion_pct)
        VALUES
            (v_cat, v_actual, v_anterior, v_var);

    END LOOP;

    -- 6. Cerrar cursor
    CLOSE cur;

    -- 7. Mostrar resultados
    SELECT *
    FROM tmp_comparativa
    ORDER BY mes_actual DESC;

    -- 8. Eliminar tabla temporal
    DROP TEMPORARY TABLE IF EXISTS tmp_comparativa;

END$$

DELIMITER ;


-- Verificación
SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = 'vidaplus' AND ROUTINE_TYPE = 'PROCEDURE'
ORDER BY ROUTINE_NAME;