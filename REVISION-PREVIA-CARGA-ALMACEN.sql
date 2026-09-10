-- Revisión previa a la carga de los layouts de Almacén (PostgreSQL).
--
-- TODO ES DE SOLO LECTURA. No hay un solo INSERT, UPDATE, DELETE ni TRUNCATE.
-- Se puede correr en producción sin riesgo; sirve para saber qué va a pasar
-- ANTES de correr:
--
--   php artisan db:seed --class="Database\Seeders\Alm\LayoutsSeeder"
--
-- Lo que el seeder hace, medido con el log de consultas en un simulacro:
--   INSERT  costos_productos, alm_existencias, alm_movimientos, alm_ajustes,
--           alm_ajuste_detalle, alm_activos, alm_ubicaciones
--   UPDATE  alm_existencias, alm_activos
--   DELETE / TRUNCATE: ninguno.
-- No escribe en costos_ordenes_compra ni en ninguna otra tabla de Costos.


-- 1. ¿Qué almacenes existen y cuál ya tiene carga inicial?
--    Los que ya la tienen SE VAN A SALTAR: el seeder no recarga un almacén
--    abierto. Es el punto que hay que mirar con más cuidado.
SELECT  a.clave,
        COALESCE(o.no, '(central / en planta)')          AS obra,
        a.nombre,
        aj.folio                                          AS carga_inicial,
        (SELECT count(*) FROM alm_existencias e WHERE e.almacen_id = a.id) AS existencias,
        (SELECT count(*) FROM alm_activos    v WHERE v.almacen_id = a.id) AS piezas
FROM    alm_almacenes a
LEFT JOIN obras   o  ON o.id = a.obra_id
LEFT JOIN alm_ajustes aj ON aj.almacen_id = a.id AND aj.motivo = 'carga_inicial'
ORDER BY a.clave, o.no NULLS FIRST;


-- 2. Los almacenes que el layout necesita. Los que salgan aquí como faltantes
--    los da de alta CatalogoLayoutSeeder; los que ya existan se respetan.
WITH pedidos(clave, obra) AS (
    VALUES ('CONS', NULL), ('INS', NULL), ('MON', NULL), ('MTO', NULL), ('PROD', NULL),
           ('CONST', 'AMPLIACION T4'), ('CONST', 'MBP'), ('CONST', 'PARKS NAVE A'),
           ('CONST', 'SIX PARKS CANCUN'), ('CONST', 'TERRAZA ERNESTO ROSADO'),
           ('CONST', 'TRES GUERRAS')
)
SELECT  p.clave,
        COALESCE(p.obra, '(central)') AS obra,
        CASE WHEN a.id IS NULL THEN 'SE VA A CREAR' ELSE 'ya existe' END AS estado
FROM    pedidos p
LEFT JOIN obras o ON o.no = p.obra
LEFT JOIN alm_almacenes a
       ON a.clave = p.clave
      AND ((p.obra IS NULL AND a.obra_id IS NULL) OR a.obra_id = o.id)
ORDER BY p.clave, p.obra NULLS FIRST;


-- 3. Las obras que el layout necesita. Las que falten se crean con su número.
WITH pedidas(no) AS (
    VALUES ('AMPLIACION T4'), ('MBP'), ('PARKS NAVE A'),
           ('SIX PARKS CANCUN'), ('TERRAZA ERNESTO ROSADO'), ('TRES GUERRAS')
)
SELECT  p.no,
        CASE WHEN o.id IS NULL THEN 'SE VA A CREAR' ELSE 'ya existe' END AS estado
FROM    pedidas p
LEFT JOIN obras o ON o.no = p.no
ORDER BY p.no;


-- 4. Las áreas que el layout necesita. Ojo con Tornillería: la usan 261
--    renglones sin que estuviera en el catálogo que se le entregó al área.
WITH pedidas(descripcion) AS (
    VALUES ('Consumibles'), ('Estructura'), ('Gases'), ('Herramienta'), ('Limpieza'),
           ('Mantenimiento'), ('Papelería'), ('Pintura'), ('Plasma'), ('Seguridad'),
           ('Soldadura'), ('Tornillería')
)
SELECT  p.descripcion,
        CASE WHEN a.id IS NULL THEN 'SE VA A CREAR' ELSE 'ya existe' END AS estado
FROM    pedidas p
LEFT JOIN alm_areas a ON a.descripcion = p.descripcion
ORDER BY p.descripcion;


-- 5. Cuánto va a crecer el catálogo de artículos. El consecutivo ART- arranca
--    donde va y suma 1,162.
SELECT  count(*)                                                   AS articulos_art_hoy,
        max(codigo)                                                AS ultimo_codigo,
        count(*) + 1162                                            AS articulos_despues
FROM    costos_productos
WHERE   codigo LIKE 'ART-%';


-- 6. Descripciones del layout que YA existen en el catálogo. El seeder no
--    deduplica —da de alta artículo nuevo con código nuevo— así que aquí sale
--    lo que quedaría repetido. Vacío = ninguno se repite.
--    (Ajustar la lista si se quiere revisar más; van las de mayor volumen.)
SELECT  p.codigo, p.descripcion, p.unidad, p.activo
FROM    costos_productos p
WHERE   upper(p.descripcion) IN (
            'ESMERILADORA ANGULAR 9', 'ARNES DE CUERPO COMPLETO',
            'CABLE DE USO RUDO 2X12', 'CABLE DE USO RUDO 2X8',
            'CABLE PORTAELECTRODO 1/0', 'ESCALERA DE TIJERA', 'ESCALERA TELESCOPICA',
            'ADAPTADOR FACIAL PARA CASCO', 'AISLADOR', 'BANDERA DE PRECAUCION'
        )
ORDER BY p.descripcion;


-- 7. Retrato de Costos ANTES de cargar. Corre esto mismo DESPUÉS y compara:
--    los cinco números tienen que salir idénticos.
SELECT  (SELECT count(*) FROM costos_ordenes_compra)                                   AS ordenes_compra,
        (SELECT count(*) FROM costos_ordenes_compra_detalle)                           AS renglones_oc,
        (SELECT count(*) FROM costos_ordenes_compra_detalle WHERE producto_id IS NULL) AS renglones_oc_sin_producto,
        (SELECT count(*) FROM costos_requisicion_detalle    WHERE producto_id IS NULL) AS renglones_req_sin_producto,
        (SELECT count(*) FROM costos_entregas               WHERE almacen_id IS NULL)  AS recepciones_sin_almacen;


-- 8. Valor del inventario ANTES. Después debería subir en $8,463,304.93
--    exactamente, menos lo que corresponda a los almacenes que se salten.
SELECT  a.clave,
        COALESCE(o.no, '(central)') AS obra,
        count(e.id)                 AS renglones,
        to_char(COALESCE(sum(e.valor), 0), 'FM999,999,999.00') AS valor
FROM    alm_almacenes a
LEFT JOIN obras o           ON o.id = a.obra_id
LEFT JOIN alm_existencias e ON e.almacen_id = a.id
GROUP BY a.clave, o.no
ORDER BY a.clave, o.no NULLS FIRST;


-- 9. Los artículos ART- que no tienen existencia en ningún almacén. En prod hay
--    415 ART- pero sólo 50 renglones de inventario (PIN 39 + SOL 11), así que
--    sobran ~365 sin saldo: escombro de la carga anterior. Saber qué son decide
--    si estorban o no.
SELECT  p.codigo,
        p.descripcion,
        p.unidad,
        p.activo,
        p.se_controla_por_pieza,
        p.created_at::date                                                        AS dado_de_alta,
        (SELECT count(*) FROM costos_ordenes_compra_detalle d WHERE d.producto_id = p.id) AS usos_en_oc,
        (SELECT count(*) FROM costos_requisicion_detalle   r WHERE r.producto_id = p.id) AS usos_en_req
FROM    costos_productos p
WHERE   p.codigo LIKE 'ART-%'
  AND   NOT EXISTS (SELECT 1 FROM alm_existencias e WHERE e.producto_id = p.id)
ORDER BY p.created_at, p.codigo;


-- 10. Resumen de los huérfanos por fecha de alta: si salieron todos el mismo
--     día son de una sola carga, y se pueden tratar en bloque.
SELECT  p.created_at::date AS dia,
        count(*)           AS articulos,
        min(p.codigo)      AS desde,
        max(p.codigo)      AS hasta
FROM    costos_productos p
WHERE   p.codigo LIKE 'ART-%'
  AND   NOT EXISTS (SELECT 1 FROM alm_existencias e WHERE e.producto_id = p.id)
GROUP BY p.created_at::date
ORDER BY dia;
