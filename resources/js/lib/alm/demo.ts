/**
 * Datos de maqueta del módulo Almacén.
 *
 * Las pantallas todavía no tienen backend: se dibujan contra estas constantes
 * para poder revisar el diseño y el flujo. Cuando existan los controladores,
 * cada página recibe lo mismo por props de Inertia y este archivo se borra.
 */
import type {
    AlmActivoDemo,
    AlmActivoEstatus,
    AlmAjusteDemo,
    AlmAjusteMotivo,
    AlmArticuloDemo,
    AlmDevolucionDemo,
    AlmDocumentoTipo,
    AlmEntradaDemo,
    AlmExistenciaDemo,
    AlmMovimientoDemo,
    AlmPedidoDemo,
    AlmPedidoEstatus,
    AlmPrestamoDemo,
    AlmPrestamoEstatus,
    AlmProductoDemo,
    AlmProductoTipo,
    AlmReglaAprobacion,
    AlmSalidaDemo,
    AlmTransferenciaDemo,
    AlmUsuarioDemo,
} from '@/types/models';

export const ALMACENES_DEMO = [
    { id: 1, clave: 'AG', nombre: 'Almacén general', obra: null, tipo: 'insumos' as const },
    { id: 6, clave: 'HER', nombre: 'Pañol de herramienta', obra: null, tipo: 'herramienta' as const },
    { id: 2, clave: 'FAK', nombre: 'Fachadas', obra: 'T4', tipo: 'montaje' as const },
    { id: 3, clave: 'FAD', nombre: 'Fachada domo', obra: 'T4', tipo: 'montaje' as const },
    { id: 4, clave: 'A', nombre: 'Andamios', obra: 'T4', tipo: 'herramienta' as const },
    { id: 5, clave: 'E', nombre: 'Estructura', obra: 'MBP', tipo: 'montaje' as const },
];

export const PRODUCTOS_DEMO: AlmProductoDemo[] = [
    { id: 101, codigo: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA', requiere_verificacion: false },
    { id: 102, codigo: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', requiere_verificacion: false },
    { id: 103, codigo: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS', requiere_verificacion: false },
    { id: 104, codigo: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA', requiere_verificacion: false },
    { id: 105, codigo: 'GUA-CARN', descripcion: 'Guante de carnaza', unidad: 'PAR', requiere_verificacion: false },
    { id: 106, codigo: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', requiere_verificacion: false },
    { id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', unidad: 'PZA', requiere_verificacion: true },
    { id: 202, codigo: 'AND-MOD', descripcion: 'Módulo de andamio 1.90 m', unidad: 'PZA', requiere_verificacion: false },
    { id: 301, codigo: 'VEH-0007', descripcion: 'Camioneta Ford Ranger 2024', unidad: 'PZA', requiere_verificacion: true },
];

export const EXISTENCIAS_DEMO: AlmExistenciaDemo[] = [
    { almacen: 'AG', producto: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA', cantidad: 12780, costo_promedio: 4.35, ubicacion: 'Rack 3' },
    { almacen: 'AG', producto: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', cantidad: 340.5, costo_promedio: 62.1, ubicacion: 'Rack 1' },
    { almacen: 'AG', producto: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS', cantidad: 96, costo_promedio: 218.4, ubicacion: null },
    { almacen: 'FAK', producto: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', cantidad: 42, costo_promedio: 310.0, ubicacion: 'Contenedor 2' },
    { almacen: 'FAK', producto: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA', cantidad: 8, costo_promedio: 27.5, ubicacion: null },
    { almacen: 'FAD', producto: 'GUA-CARN', descripcion: 'Guante de carnaza', unidad: 'PAR', cantidad: 0, costo_promedio: 48.9, ubicacion: null },
    { almacen: 'E', producto: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', cantidad: 74.25, costo_promedio: 63.8, ubicacion: null },
];

export const MOVIMIENTOS_DEMO: AlmMovimientoDemo[] = [
    { id: 10, fecha: '2026-08-05 18:10', almacen: 'AG', producto: 'TOR-0012', tipo: 'devolucion', cantidad: 300, saldo_nuevo: 12780, referencia: 'DEV-2608-0006', usuario: 'M. Rangel', observaciones: 'Sobrante de T4' },
    { id: 9, fecha: '2026-08-05 16:40', almacen: 'AG', producto: 'TOR-0012', tipo: 'salida', cantidad: -1500, saldo_nuevo: 12480, referencia: 'SAL-2608-0031', usuario: 'M. Rangel', observaciones: 'Montaje eje 4' },
    { id: 8, fecha: '2026-08-05 11:02', almacen: 'AG', producto: 'TOR-0012', tipo: 'entrada', cantidad: 8000, saldo_nuevo: 13980, referencia: 'ENT-2608-0017', usuario: 'J. Briones', observaciones: null },
    { id: 7, fecha: '2026-08-04 17:15', almacen: 'FAK', producto: 'SIL-EST', tipo: 'transferencia_entrada', cantidad: 12, saldo_nuevo: 42, referencia: 'TRA-2608-0004', usuario: 'M. Rangel', observaciones: 'Desde AG' },
    { id: 6, fecha: '2026-08-04 17:15', almacen: 'AG', producto: 'SIL-EST', tipo: 'transferencia_salida', cantidad: -12, saldo_nuevo: 30, referencia: 'TRA-2608-0004', usuario: 'M. Rangel', observaciones: 'Hacia FAK' },
    { id: 5, fecha: '2026-08-03 09:20', almacen: 'AG', producto: 'ELE-7018', tipo: 'entrada', cantidad: 200, saldo_nuevo: 340.5, referencia: 'ENT-2608-0016', usuario: 'J. Briones', observaciones: null },
    { id: 4, fecha: '2026-08-02 13:44', almacen: 'FAD', producto: 'GUA-CARN', tipo: 'salida', cantidad: -24, saldo_nuevo: 0, referencia: 'SAL-2608-0028', usuario: 'L. Ortega', observaciones: 'Cuadrilla 2' },
    { id: 3, fecha: '2026-08-01 08:05', almacen: 'AG', producto: 'PIN-PRIM', tipo: 'ajuste', cantidad: -4, saldo_nuevo: 96, referencia: 'Conteo físico', usuario: 'J. Briones', observaciones: 'Diferencia de conteo' },
];

export const ENTRADAS_DEMO: AlmEntradaDemo[] = [
    { id: 17, folio: 'ENT-2608-0017', fecha: '2026-08-05', almacen: 'AG', proveedor: 'Aceros del Norte S.A.', renglones: 3, importe: 48920.5, recibio: 'J. Briones' },
    { id: 16, folio: 'ENT-2608-0016', fecha: '2026-08-03', almacen: 'AG', proveedor: 'Soldaduras Industriales', renglones: 1, importe: 12420.0, recibio: 'J. Briones' },
    { id: 15, folio: 'ENT-2608-0015', fecha: '2026-08-01', almacen: 'FAK', proveedor: 'Selladores del Golfo', renglones: 2, importe: 9300.0, recibio: 'M. Rangel' },
];

export const SALIDAS_DEMO: AlmSalidaDemo[] = [
    { id: 31, folio: 'SAL-2608-0031', fecha: '2026-08-05', almacen: 'AG', obra_destino: 'T4 — Torre 4', solicitante: 'M. Rangel', recibe: 'Cuadrilla 3', renglones: 2, motivo: 'Montaje eje 4', pedido_folio: 'PED-2608-0023' },
    { id: 30, folio: 'SAL-2608-0030', fecha: '2026-08-04', almacen: 'FAK', obra_destino: 'T4 — Torre 4', solicitante: 'L. Ortega', recibe: 'A. Pérez', renglones: 1, motivo: 'Sellado de fachada', pedido_folio: null },
    { id: 28, folio: 'SAL-2608-0028', fecha: '2026-08-02', almacen: 'FAD', obra_destino: 'T4 — Torre 4', solicitante: 'L. Ortega', recibe: 'Cuadrilla 2', renglones: 1, motivo: 'Equipo de protección', pedido_folio: null },
];

export const TRANSFERENCIAS_DEMO: AlmTransferenciaDemo[] = [
    { id: 4, folio: 'TRA-2608-0004', fecha: '2026-08-04', origen: 'AG', destino: 'FAK', renglones: 1, autorizo: 'J. Briones' },
    { id: 3, folio: 'TRA-2608-0003', fecha: '2026-07-30', origen: 'AG', destino: 'E', renglones: 2, autorizo: 'J. Briones' },
];

export const AJUSTES_DEMO: AlmAjusteDemo[] = [
    { id: 12, folio: 'AJU-2608-0012', fecha: '2026-08-01', almacen: 'AG', motivo: 'conteo_fisico', renglones: 1, diferencia_neta: -4, autorizo: 'J. Briones' },
    { id: 11, folio: 'AJU-2607-0011', fecha: '2026-07-28', almacen: 'FAK', motivo: 'merma', renglones: 2, diferencia_neta: -7, autorizo: 'J. Briones' },
    { id: 10, folio: 'AJU-2607-0010', fecha: '2026-07-21', almacen: 'AG', motivo: 'error_captura', renglones: 1, diferencia_neta: 150, autorizo: 'M. Rangel' },
];

export const DEVOLUCIONES_DEMO: AlmDevolucionDemo[] = [
    { id: 6, folio: 'DEV-2608-0006', fecha: '2026-08-05', almacen: 'AG', obra_origen: 'T4 — Torre 4', devolvio: 'Cuadrilla 3', renglones: 2, motivo: 'Sobrante de montaje eje 4' },
    { id: 5, folio: 'DEV-2607-0005', fecha: '2026-07-29', almacen: 'FAK', obra_origen: 'T4 — Torre 4', devolvio: 'A. Pérez', renglones: 1, motivo: 'Material equivocado' },
];

/** Cómo se lee cada motivo de ajuste en pantalla. */
export const MOTIVOS_AJUSTE: Record<AlmAjusteMotivo, string> = {
    conteo_fisico: 'Conteo físico',
    merma: 'Merma',
    error_captura: 'Error de captura',
    otro: 'Otro',
};

/**
 * Miniatura de ejemplo. La maqueta no sube archivos todavía, así que se dibuja
 * un SVG en línea en vez de referenciar una imagen que no existe.
 */
function imagenDemo(texto: string, color: string): string {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="8" fill="${color}"/><text x="32" y="40" font-family="sans-serif" font-size="16" font-weight="bold" text-anchor="middle" fill="#ffffff">${texto}</text></svg>`;

    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}

export const ARTICULOS_DEMO: AlmArticuloDemo[] = [
    { id: 101, codigo: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA', imagen_url: imagenDemo('TOR', '#64748b'), tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 5000, existencia_total: 12780 },
    { id: 102, codigo: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', imagen_url: imagenDemo('ELE', '#0f766e'), tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 200, existencia_total: 414.75 },
    { id: 103, codigo: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS', imagen_url: imagenDemo('PIN', '#b45309'), tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 50, existencia_total: 96 },
    { id: 104, codigo: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA', imagen_url: imagenDemo('DIS', '#7c3aed'), tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 40, existencia_total: 8 },
    { id: 105, codigo: 'GUA-CARN', descripcion: 'Guante de carnaza', unidad: 'PAR', imagen_url: null, tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 30, existencia_total: 0 },
    { id: 106, codigo: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', imagen_url: null, tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 20, existencia_total: 42 },
    // La pulidora se presta bajo resguardo: cada pieza lleva serie y dueño.
    { id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', unidad: 'PZA', imagen_url: imagenDemo('PUL', '#be123c'), tipo: 'herramienta', requiere_verificacion: true, controla_inventario: true, se_controla_por_pieza: true, stock_minimo: null, existencia_total: 14 },
    // El andamio también se presta, pero por bulto: serializarlo no aporta.
    { id: 202, codigo: 'AND-MOD', descripcion: 'Módulo de andamio 1.90 m', unidad: 'PZA', imagen_url: null, tipo: 'herramienta', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: null, existencia_total: 320 },
    { id: 301, codigo: 'VEH-0007', descripcion: 'Camioneta Ford Ranger 2024', unidad: 'PZA', imagen_url: imagenDemo('VEH', '#1d4ed8'), tipo: 'activo', requiere_verificacion: true, controla_inventario: true, se_controla_por_pieza: true, stock_minimo: null, existencia_total: 1 },
    { id: 302, codigo: 'SRV-FLET', descripcion: 'Flete foráneo', unidad: 'SRV', imagen_url: null, tipo: 'insumo', requiere_verificacion: false, controla_inventario: false, se_controla_por_pieza: false, stock_minimo: null, existencia_total: 0 },
];

/**
 * Piezas identificadas de los artículos marcados `se_controla_por_pieza`. Cada
 * una suma 1 a la existencia de su producto; el kardex por cantidad no cambia.
 */
export const ACTIVOS_DEMO: AlmActivoDemo[] = [
    { id: 1, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-07', almacen: 'HER', estatus: 'prestado', condicion: 'Buena' },
    { id: 2, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-08', almacen: 'HER', estatus: 'prestado', condicion: 'Buena' },
    { id: 3, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-11', almacen: 'HER', estatus: 'disponible', condicion: 'Buena' },
    { id: 4, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-12', almacen: 'HER', estatus: 'en_reparacion', condicion: 'Carbones gastados' },
    { id: 5, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-14', almacen: 'HER', estatus: 'prestado', condicion: 'Regular' },
    { id: 6, producto_id: 301, codigo: 'VEH-0007', descripcion: 'Camioneta Ford Ranger 2024', no_serie: '3FTTW8E9XRA12345', almacen: 'AG', estatus: 'prestado', condicion: 'Buena' },
];

export const PRESTAMOS_DEMO: AlmPrestamoDemo[] = [
    // Vencido: debía volver el 2026-08-04 y sigue afuera.
    { id: 14, folio: 'PRE-2607-0014', activo_id: 1, no_serie: 'PUL-4120-07', articulo: 'Pulidora 4 1/2" 850W', almacen: 'HER', responsable: 'R. Salas', destino: 'T4 — Torre 4', fecha_salida: '2026-07-28', fecha_retorno_esperada: '2026-08-04', fecha_retorno: null, condicion_salida: 'Buena', condicion_retorno: null, estatus: 'abierto' },
    { id: 16, folio: 'PRE-2608-0016', activo_id: 2, no_serie: 'PUL-4120-08', articulo: 'Pulidora 4 1/2" 850W', almacen: 'HER', responsable: 'A. Pérez', destino: 'T4 — Torre 4', fecha_salida: '2026-08-05', fecha_retorno_esperada: '2026-08-15', fecha_retorno: null, condicion_salida: 'Buena', condicion_retorno: null, estatus: 'abierto' },
    { id: 17, folio: 'PRE-2608-0017', activo_id: 5, no_serie: 'PUL-4120-14', articulo: 'Pulidora 4 1/2" 850W', almacen: 'HER', responsable: 'L. Ortega', destino: 'Fabricación Nave K', fecha_salida: '2026-08-06', fecha_retorno_esperada: '2026-08-20', fecha_retorno: null, condicion_salida: 'Regular', condicion_retorno: null, estatus: 'abierto' },
    { id: 18, folio: 'PRE-2608-0018', activo_id: 6, no_serie: '3FTTW8E9XRA12345', articulo: 'Camioneta Ford Ranger 2024', almacen: 'AG', responsable: 'C. Nava', destino: 'MBP — Museo Bellas Artes', fecha_salida: '2026-08-03', fecha_retorno_esperada: '2026-08-31', fecha_retorno: null, condicion_salida: 'Buena', condicion_retorno: null, estatus: 'abierto' },
    { id: 12, folio: 'PRE-2607-0012', activo_id: 3, no_serie: 'PUL-4120-11', articulo: 'Pulidora 4 1/2" 850W', almacen: 'HER', responsable: 'M. Rangel', destino: 'Pintura', fecha_salida: '2026-07-20', fecha_retorno_esperada: '2026-07-27', fecha_retorno: '2026-07-26', condicion_salida: 'Buena', condicion_retorno: 'Buena', estatus: 'devuelto' },
    { id: 9, folio: 'PRE-2607-0009', activo_id: 4, no_serie: 'PUL-4120-12', articulo: 'Pulidora 4 1/2" 850W', almacen: 'HER', responsable: 'A. Pérez', destino: 'T4 — Torre 4', fecha_salida: '2026-07-10', fecha_retorno_esperada: '2026-07-17', fecha_retorno: '2026-07-19', condicion_salida: 'Buena', condicion_retorno: 'Carbones gastados', estatus: 'devuelto' },
];

/** Cómo se lee cada estado de una pieza. */
export const ESTATUS_ACTIVO: Record<AlmActivoEstatus, { etiqueta: string; clase: string }> = {
    disponible: { etiqueta: 'Disponible', clase: 'badge-success' },
    prestado: { etiqueta: 'Prestado', clase: 'badge-warning' },
    en_reparacion: { etiqueta: 'En reparación', clase: 'badge-info' },
    baja: { etiqueta: 'Baja', clase: 'badge-ghost' },
};

/** Cómo se lee cada estado de un resguardo. */
export const ESTATUS_PRESTAMO: Record<AlmPrestamoEstatus, { etiqueta: string; clase: string }> = {
    abierto: { etiqueta: 'Afuera', clase: 'badge-warning' },
    devuelto: { etiqueta: 'Devuelto', clase: 'badge-success' },
    perdido: { etiqueta: 'Perdido', clase: 'badge-error' },
};

/**
 * Días que lleva fuera una pieza y si ya se pasó de la fecha. La maqueta fija
 * "hoy" a mano para que los ejemplos no se muevan solos.
 */
export const HOY_DEMO = '2026-08-11';

export function diasFuera(prestamo: AlmPrestamoDemo): { dias: number; vencido: boolean } {
    const desde = new Date(prestamo.fecha_salida);
    const hasta = new Date(prestamo.fecha_retorno ?? HOY_DEMO);
    const dias = Math.round((hasta.getTime() - desde.getTime()) / 86_400_000);
    const vencido = prestamo.estatus === 'abierto' && (prestamo.fecha_retorno_esperada < HOY_DEMO);

    return { dias, vencido };
}

/** Piezas que se pueden prestar: las que están en el almacén y disponibles. */
export function activosPrestables(claveAlmacen: string | undefined): AlmActivoDemo[] {
    if (!claveAlmacen) {
        return [];
    }

    return ACTIVOS_DEMO.filter((a) => a.almacen === claveAlmacen && a.estatus === 'disponible');
}

export const USUARIOS_DEMO: AlmUsuarioDemo[] = [
    { id: 1, nombre: 'J. Briones', puesto: 'Jefe de almacén' },
    { id: 2, nombre: 'M. Rangel', puesto: 'Almacenista AG' },
    { id: 3, nombre: 'L. Ortega', puesto: 'Almacenista de obra' },
    { id: 4, nombre: 'R. Salas', puesto: 'Superintendente' },
    { id: 5, nombre: 'C. Nava', puesto: 'Gerente de operaciones' },
    { id: 6, nombre: 'P. Duarte', puesto: 'Contralor' },
];

export const APROBACIONES_DEMO: AlmReglaAprobacion[] = [
    { documento: 'pedido', requiere: true, usuarios: [4, 5] },
    { documento: 'entrada', requiere: false, usuarios: [] },
    { documento: 'salida', requiere: true, usuarios: [1, 3] },
    { documento: 'transferencia', requiere: true, usuarios: [1] },
    { documento: 'devolucion', requiere: false, usuarios: [] },
    { documento: 'ajuste', requiere: true, usuarios: [1, 5, 6] },
    { documento: 'prestamo', requiere: true, usuarios: [1] },
];

export const PEDIDOS_DEMO: AlmPedidoDemo[] = [
    { id: 23, folio: 'PED-2608-0023', fecha: '2026-08-06', solicitante: 'M. Rangel', departamento: 'Montaje', obra: 'T4 — Torre 4', almacen: 'AG', fecha_requerida: '2026-08-08', estatus: 'aprobado', detalle: [
        // Surtido a medias: la salida nueva debe traer sólo lo que falta.
        { producto_id: 101, cantidad_solicitada: 2000, cantidad_surtida: 800 },
        { producto_id: 102, cantidad_solicitada: 50, cantidad_surtida: 50 },
        { producto_id: 104, cantidad_solicitada: 30, cantidad_surtida: 0 },
    ] },
    { id: 22, folio: 'PED-2608-0022', fecha: '2026-08-06', solicitante: 'L. Ortega', departamento: 'Montaje', obra: 'T4 — Torre 4', almacen: 'AG', fecha_requerida: '2026-08-08', estatus: 'pendiente', detalle: [
        { producto_id: 101, cantidad_solicitada: 500, cantidad_surtida: 0 },
        { producto_id: 103, cantidad_solicitada: 12, cantidad_surtida: 0 },
        { producto_id: 105, cantidad_solicitada: 24, cantidad_surtida: 0 },
        { producto_id: 201, cantidad_solicitada: 2, cantidad_surtida: 0 },
    ] },
    // Consumo interno: la nave de fabricación pide para sí misma, sin obra.
    { id: 24, folio: 'PED-2608-0024', fecha: '2026-08-06', solicitante: 'J. Briones', departamento: 'Fabricación Nave K', obra: null, almacen: 'AG', fecha_requerida: '2026-08-07', estatus: 'aprobado', detalle: [
        { producto_id: 102, cantidad_solicitada: 120, cantidad_surtida: 0 },
        { producto_id: 104, cantidad_solicitada: 60, cantidad_surtida: 0 },
    ] },
    { id: 25, folio: 'PED-2608-0025', fecha: '2026-08-05', solicitante: 'M. Rangel', departamento: 'Pintura', obra: null, almacen: 'AG', fecha_requerida: '2026-08-06', estatus: 'surtido', detalle: [
        { producto_id: 103, cantidad_solicitada: 40, cantidad_surtida: 40 },
    ] },
    { id: 21, folio: 'PED-2608-0021', fecha: '2026-08-05', solicitante: 'M. Rangel', departamento: 'Montaje', obra: 'MBP — Museo Bellas Artes', almacen: 'AG', fecha_requerida: '2026-08-07', estatus: 'aprobado', detalle: [
        { producto_id: 106, cantidad_solicitada: 8, cantidad_surtida: 0 },
        { producto_id: 103, cantidad_solicitada: 20, cantidad_surtida: 0 },
    ] },
    { id: 20, folio: 'PED-2608-0020', fecha: '2026-08-04', solicitante: 'L. Ortega', departamento: 'Montaje', obra: 'T4 — Torre 4', almacen: 'AG', fecha_requerida: '2026-08-05', estatus: 'surtido', detalle: [
        { producto_id: 101, cantidad_solicitada: 1200, cantidad_surtida: 1200 },
        { producto_id: 102, cantidad_solicitada: 40, cantidad_surtida: 40 },
        { producto_id: 104, cantidad_solicitada: 15, cantidad_surtida: 15 },
    ] },
    { id: 19, folio: 'PED-2607-0019', fecha: '2026-07-31', solicitante: 'R. Salas', departamento: 'Fachadas', obra: 'T4 — Torre 4', almacen: 'FAK', fecha_requerida: '2026-08-01', estatus: 'rechazado', detalle: [
        { producto_id: 106, cantidad_solicitada: 6, cantidad_surtida: 0 },
    ] },
];

/** Áreas de planta que piden material sin que haya una obra de por medio. */
export const DEPARTAMENTOS_DEMO = [
    { id: 1, nombre: 'Fabricación Nave K' },
    { id: 2, nombre: 'Fabricación Nave D' },
    { id: 3, nombre: 'Pintura' },
    { id: 4, nombre: 'Montaje' },
    { id: 5, nombre: 'Fachadas' },
    { id: 6, nombre: 'Mantenimiento' },
];

/**
 * Cómo se va a surtir un pedido, según a dónde va el material.
 *
 * Con obra hay que llevarlo a otro domicilio, así que lo surte una
 * transferencia y la obra tiene que confirmar la recepción. Sin obra el
 * material se queda en la planta y sale directo con una salida.
 */
export function comoSeSurte(obra: string | null): { documento: string; explicacion: string } {
    return obra
        ? {
              documento: 'Transferencia',
              explicacion: `El material va al almacén de ${obra}: se surte con una transferencia y la obra confirma cuando lo recibe.`,
          }
        : {
              documento: 'Salida',
              explicacion: 'El material se queda en planta: se surte con una salida directa del almacén.',
          };
}

/**
 * Pedidos que una salida puede surtir: aprobados, del almacén elegido y con
 * algo pendiente. Un pedido se surte en varias vueltas.
 */
export function pedidosSurtibles(claveAlmacen: string | undefined): AlmPedidoDemo[] {
    if (!claveAlmacen) {
        return [];
    }

    return PEDIDOS_DEMO.filter(
        (p) =>
            p.almacen === claveAlmacen &&
            p.estatus === 'aprobado' &&
            p.detalle.some((d) => d.cantidad_surtida < d.cantidad_solicitada),
    );
}

/** Cómo se lee cada tipo de producto en pantalla. */
export const TIPOS_ARTICULO: Record<AlmProductoTipo, string> = {
    insumo: 'Insumo',
    herramienta: 'Herramienta',
    activo: 'Activo',
};

/** Cómo se nombra cada documento en la pantalla de aprobaciones. */
export const DOCUMENTOS_ALM: Record<AlmDocumentoTipo, string> = {
    pedido: 'Pedido',
    entrada: 'Entrada',
    salida: 'Salida',
    transferencia: 'Transferencia',
    devolucion: 'Devolución',
    ajuste: 'Ajuste',
    prestamo: 'Préstamo',
};

/**
 * Qué se está pidiendo con la firma de cada documento. El préstamo es el caso
 * raro: quien se lleva la pieza siempre firma el resguardo (eso es el impreso),
 * y esta firma es aparte — la de alguien que autoriza que salga.
 */
export const AYUDA_DOCUMENTO: Partial<Record<AlmDocumentoTipo, string>> = {
    ajuste: 'El único movimiento que cambia la existencia sin un documento que lo respalde.',
    prestamo: 'Aparte del resguardo que firma quien se la lleva: esto es quién autoriza que salga del pañol.',
};

/** Color del badge de estatus de pedido. */
export const ESTATUS_PEDIDO: Record<AlmPedidoEstatus, { etiqueta: string; clase: string }> = {
    borrador: { etiqueta: 'Borrador', clase: 'badge-ghost' },
    pendiente: { etiqueta: 'Pendiente de firma', clase: 'badge-warning' },
    aprobado: { etiqueta: 'Aprobado', clase: 'badge-info' },
    surtido: { etiqueta: 'Surtido', clase: 'badge-success' },
    cancelado: { etiqueta: 'Cancelado', clase: 'badge-ghost' },
    rechazado: { etiqueta: 'Rechazado', clase: 'badge-error' },
};

/** Lo que hay del producto en ese almacén, para avisar en la captura de salidas. */
export function disponibleDemo(almacen: string, codigoProducto: string): number | null {
    const fila = EXISTENCIAS_DEMO.find((e) => e.almacen === almacen && e.producto === codigoProducto);

    return fila ? fila.cantidad : null;
}
