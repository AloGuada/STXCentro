/**
 * Datos de maqueta del módulo Almacén.
 *
 * Las pantallas todavía no tienen backend: se dibujan contra estas constantes
 * para poder revisar el diseño y el flujo. Cuando existan los controladores,
 * cada página recibe lo mismo por props de Inertia y este archivo se borra.
 */
import type {
    AlmAjusteDemo,
    AlmAjusteMotivo,
    AlmDevolucionDemo,
    AlmDocumentoTipo,
    AlmEntradaDemo,
    AlmExistenciaDemo,
    AlmInsumoDemo,
    AlmMovimientoDemo,
    AlmProductoDemo,
    AlmProductoTipo,
    AlmReglaAprobacion,
    AlmRequisicionDemo,
    AlmRequisicionEstatus,
    AlmSalidaDemo,
    AlmTransferenciaDemo,
    AlmUsuarioDemo,
} from '@/types/models';

export const ALMACENES_DEMO = [
    { id: 1, clave: 'AG', nombre: 'Almacén general', obra: null, tipo: 'insumos' as const },
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
    { id: 31, folio: 'SAL-2608-0031', fecha: '2026-08-05', almacen: 'AG', obra_destino: 'T4 — Torre 4', solicitante: 'M. Rangel', recibe: 'Cuadrilla 3', renglones: 2, motivo: 'Montaje eje 4', requisicion_folio: 'REQ-2608-0023' },
    { id: 30, folio: 'SAL-2608-0030', fecha: '2026-08-04', almacen: 'FAK', obra_destino: 'T4 — Torre 4', solicitante: 'L. Ortega', recibe: 'A. Pérez', renglones: 1, motivo: 'Sellado de fachada', requisicion_folio: null },
    { id: 28, folio: 'SAL-2608-0028', fecha: '2026-08-02', almacen: 'FAD', obra_destino: 'T4 — Torre 4', solicitante: 'L. Ortega', recibe: 'Cuadrilla 2', renglones: 1, motivo: 'Equipo de protección', requisicion_folio: null },
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

export const INSUMOS_DEMO: AlmInsumoDemo[] = [
    { id: 101, codigo: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA', tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, stock_minimo: 5000, existencia_total: 12780 },
    { id: 102, codigo: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, stock_minimo: 200, existencia_total: 414.75 },
    { id: 103, codigo: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS', tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, stock_minimo: 50, existencia_total: 96 },
    { id: 104, codigo: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA', tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, stock_minimo: 40, existencia_total: 8 },
    { id: 105, codigo: 'GUA-CARN', descripcion: 'Guante de carnaza', unidad: 'PAR', tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, stock_minimo: 30, existencia_total: 0 },
    { id: 106, codigo: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, stock_minimo: 20, existencia_total: 42 },
    { id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', unidad: 'PZA', tipo: 'activo', requiere_verificacion: true, controla_inventario: true, stock_minimo: null, existencia_total: 14 },
    { id: 202, codigo: 'AND-MOD', descripcion: 'Módulo de andamio 1.90 m', unidad: 'PZA', tipo: 'activo', requiere_verificacion: false, controla_inventario: true, stock_minimo: null, existencia_total: 320 },
    { id: 301, codigo: 'VEH-0007', descripcion: 'Camioneta Ford Ranger 2024', unidad: 'PZA', tipo: 'activo', requiere_verificacion: true, controla_inventario: true, stock_minimo: null, existencia_total: 1 },
    { id: 302, codigo: 'SRV-FLET', descripcion: 'Flete foráneo', unidad: 'SRV', tipo: 'insumo', requiere_verificacion: false, controla_inventario: false, stock_minimo: null, existencia_total: 0 },
];

export const USUARIOS_DEMO: AlmUsuarioDemo[] = [
    { id: 1, nombre: 'J. Briones', puesto: 'Jefe de almacén' },
    { id: 2, nombre: 'M. Rangel', puesto: 'Almacenista AG' },
    { id: 3, nombre: 'L. Ortega', puesto: 'Almacenista de obra' },
    { id: 4, nombre: 'R. Salas', puesto: 'Superintendente' },
    { id: 5, nombre: 'C. Nava', puesto: 'Gerente de operaciones' },
    { id: 6, nombre: 'P. Duarte', puesto: 'Contralor' },
];

export const APROBACIONES_DEMO: AlmReglaAprobacion[] = [
    { documento: 'requisicion', requiere: true, usuarios: [4, 5] },
    { documento: 'entrada', requiere: false, usuarios: [] },
    { documento: 'salida', requiere: true, usuarios: [1, 3] },
    { documento: 'transferencia', requiere: true, usuarios: [1] },
    { documento: 'devolucion', requiere: false, usuarios: [] },
    { documento: 'ajuste', requiere: true, usuarios: [1, 5, 6] },
];

export const REQUISICIONES_DEMO: AlmRequisicionDemo[] = [
    { id: 23, folio: 'REQ-2608-0023', fecha: '2026-08-06', solicitante: 'M. Rangel', obra: 'T4 — Torre 4', almacen: 'AG', fecha_requerida: '2026-08-08', estatus: 'aprobada', detalle: [
        // Surtida a medias: la salida nueva debe traer sólo lo que falta.
        { producto_id: 101, cantidad_solicitada: 2000, cantidad_surtida: 800 },
        { producto_id: 102, cantidad_solicitada: 50, cantidad_surtida: 50 },
        { producto_id: 104, cantidad_solicitada: 30, cantidad_surtida: 0 },
    ] },
    { id: 22, folio: 'REQ-2608-0022', fecha: '2026-08-06', solicitante: 'L. Ortega', obra: 'T4 — Torre 4', almacen: 'AG', fecha_requerida: '2026-08-08', estatus: 'pendiente', detalle: [
        { producto_id: 101, cantidad_solicitada: 500, cantidad_surtida: 0 },
        { producto_id: 103, cantidad_solicitada: 12, cantidad_surtida: 0 },
        { producto_id: 105, cantidad_solicitada: 24, cantidad_surtida: 0 },
        { producto_id: 201, cantidad_solicitada: 2, cantidad_surtida: 0 },
    ] },
    { id: 21, folio: 'REQ-2608-0021', fecha: '2026-08-05', solicitante: 'M. Rangel', obra: 'MBP — Museo Bellas Artes', almacen: 'AG', fecha_requerida: '2026-08-07', estatus: 'aprobada', detalle: [
        { producto_id: 106, cantidad_solicitada: 8, cantidad_surtida: 0 },
        { producto_id: 103, cantidad_solicitada: 20, cantidad_surtida: 0 },
    ] },
    { id: 20, folio: 'REQ-2608-0020', fecha: '2026-08-04', solicitante: 'L. Ortega', obra: 'T4 — Torre 4', almacen: 'AG', fecha_requerida: '2026-08-05', estatus: 'surtida', detalle: [
        { producto_id: 101, cantidad_solicitada: 1200, cantidad_surtida: 1200 },
        { producto_id: 102, cantidad_solicitada: 40, cantidad_surtida: 40 },
        { producto_id: 104, cantidad_solicitada: 15, cantidad_surtida: 15 },
    ] },
    { id: 19, folio: 'REQ-2607-0019', fecha: '2026-07-31', solicitante: 'R. Salas', obra: 'T4 — Torre 4', almacen: 'FAK', fecha_requerida: '2026-08-01', estatus: 'rechazada', detalle: [
        { producto_id: 106, cantidad_solicitada: 6, cantidad_surtida: 0 },
    ] },
];

/**
 * Requisiciones que una salida puede surtir: aprobadas, del almacén elegido y
 * con algo pendiente. Una requisición se surte en varias vueltas.
 */
export function requisicionesSurtibles(claveAlmacen: string | undefined): AlmRequisicionDemo[] {
    if (!claveAlmacen) {
        return [];
    }

    return REQUISICIONES_DEMO.filter(
        (r) =>
            r.almacen === claveAlmacen &&
            r.estatus === 'aprobada' &&
            r.detalle.some((d) => d.cantidad_surtida < d.cantidad_solicitada),
    );
}

/** Cómo se lee cada tipo de producto en pantalla. */
export const TIPOS_INSUMO: Record<AlmProductoTipo, string> = {
    insumo: 'Insumo',
    activo: 'Activo',
};

/** Cómo se nombra cada documento en la pantalla de aprobaciones. */
export const DOCUMENTOS_ALM: Record<AlmDocumentoTipo, string> = {
    requisicion: 'Requisición',
    entrada: 'Entrada',
    salida: 'Salida',
    transferencia: 'Transferencia',
    devolucion: 'Devolución',
    ajuste: 'Ajuste',
};

/** Color del badge de estatus de requisición. */
export const ESTATUS_REQUISICION: Record<AlmRequisicionEstatus, { etiqueta: string; clase: string }> = {
    borrador: { etiqueta: 'Borrador', clase: 'badge-ghost' },
    pendiente: { etiqueta: 'Pendiente de firma', clase: 'badge-warning' },
    aprobada: { etiqueta: 'Aprobada', clase: 'badge-info' },
    surtida: { etiqueta: 'Surtida', clase: 'badge-success' },
    rechazada: { etiqueta: 'Rechazada', clase: 'badge-error' },
};

/** Lo que hay del producto en ese almacén, para avisar en la captura de salidas. */
export function disponibleDemo(almacen: string, codigoProducto: string): number | null {
    const fila = EXISTENCIAS_DEMO.find((e) => e.almacen === almacen && e.producto === codigoProducto);

    return fila ? fila.cantidad : null;
}
