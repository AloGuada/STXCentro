/**
 * Datos de maqueta del módulo Almacén.
 *
 * Las pantallas todavía no tienen backend: se dibujan contra estas constantes
 * para poder revisar el diseño y el flujo. Cuando existan los controladores,
 * cada página recibe lo mismo por props de Inertia y este archivo se borra.
 */
import type {
    AlmEntradaDemo,
    AlmExistenciaDemo,
    AlmMovimientoDemo,
    AlmProductoDemo,
    AlmSalidaDemo,
    AlmTransferenciaDemo,
} from '@/types/models';

export const ALMACENES_DEMO = [
    { id: 1, clave: 'AG', nombre: 'Almacén general', obra: null, tipo: 'insumos' as const },
    { id: 2, clave: 'FAK', nombre: 'Fachadas', obra: 'T4', tipo: 'montaje' as const },
    { id: 3, clave: 'FAD', nombre: 'Fachada domo', obra: 'T4', tipo: 'montaje' as const },
    { id: 4, clave: 'A', nombre: 'Andamios', obra: 'T4', tipo: 'herramienta' as const },
    { id: 5, clave: 'E', nombre: 'Estructura', obra: 'MBP', tipo: 'montaje' as const },
];

export const PRODUCTOS_DEMO: AlmProductoDemo[] = [
    { id: 101, codigo: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA' },
    { id: 102, codigo: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG' },
    { id: 103, codigo: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS' },
    { id: 104, codigo: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA' },
    { id: 105, codigo: 'GUA-CARN', descripcion: 'Guante de carnaza', unidad: 'PAR' },
    { id: 106, codigo: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO' },
];

export const EXISTENCIAS_DEMO: AlmExistenciaDemo[] = [
    { almacen: 'AG', producto: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA', cantidad: 12480, costo_promedio: 4.35, ubicacion: 'Rack 3' },
    { almacen: 'AG', producto: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', cantidad: 340.5, costo_promedio: 62.1, ubicacion: 'Rack 1' },
    { almacen: 'AG', producto: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS', cantidad: 96, costo_promedio: 218.4, ubicacion: null },
    { almacen: 'FAK', producto: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', cantidad: 42, costo_promedio: 310.0, ubicacion: 'Contenedor 2' },
    { almacen: 'FAK', producto: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA', cantidad: 8, costo_promedio: 27.5, ubicacion: null },
    { almacen: 'FAD', producto: 'GUA-CARN', descripcion: 'Guante de carnaza', unidad: 'PAR', cantidad: 0, costo_promedio: 48.9, ubicacion: null },
    { almacen: 'E', producto: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', cantidad: 74.25, costo_promedio: 63.8, ubicacion: null },
];

export const MOVIMIENTOS_DEMO: AlmMovimientoDemo[] = [
    { id: 9, fecha: '2026-08-05 16:40', almacen: 'AG', producto: 'TOR-0012', tipo: 'salida', cantidad: -1500, saldo_nuevo: 12480, referencia: 'VAL-2608-0031', usuario: 'M. Rangel', observaciones: 'Montaje eje 4' },
    { id: 8, fecha: '2026-08-05 11:02', almacen: 'AG', producto: 'TOR-0012', tipo: 'entrada', cantidad: 8000, saldo_nuevo: 13980, referencia: 'ENT-2608-0017', usuario: 'J. Briones', observaciones: null },
    { id: 7, fecha: '2026-08-04 17:15', almacen: 'FAK', producto: 'SIL-EST', tipo: 'transferencia_entrada', cantidad: 12, saldo_nuevo: 42, referencia: 'TRA-2608-0004', usuario: 'M. Rangel', observaciones: 'Desde AG' },
    { id: 6, fecha: '2026-08-04 17:15', almacen: 'AG', producto: 'SIL-EST', tipo: 'transferencia_salida', cantidad: -12, saldo_nuevo: 30, referencia: 'TRA-2608-0004', usuario: 'M. Rangel', observaciones: 'Hacia FAK' },
    { id: 5, fecha: '2026-08-03 09:20', almacen: 'AG', producto: 'ELE-7018', tipo: 'entrada', cantidad: 200, saldo_nuevo: 340.5, referencia: 'ENT-2608-0016', usuario: 'J. Briones', observaciones: null },
    { id: 4, fecha: '2026-08-02 13:44', almacen: 'FAD', producto: 'GUA-CARN', tipo: 'salida', cantidad: -24, saldo_nuevo: 0, referencia: 'VAL-2608-0028', usuario: 'L. Ortega', observaciones: 'Cuadrilla 2' },
    { id: 3, fecha: '2026-08-01 08:05', almacen: 'AG', producto: 'PIN-PRIM', tipo: 'ajuste', cantidad: -4, saldo_nuevo: 96, referencia: 'Conteo físico', usuario: 'J. Briones', observaciones: 'Diferencia de conteo' },
];

export const ENTRADAS_DEMO: AlmEntradaDemo[] = [
    { id: 17, folio: 'ENT-2608-0017', fecha: '2026-08-05', almacen: 'AG', proveedor: 'Aceros del Norte S.A.', renglones: 3, importe: 48920.5, recibio: 'J. Briones' },
    { id: 16, folio: 'ENT-2608-0016', fecha: '2026-08-03', almacen: 'AG', proveedor: 'Soldaduras Industriales', renglones: 1, importe: 12420.0, recibio: 'J. Briones' },
    { id: 15, folio: 'ENT-2608-0015', fecha: '2026-08-01', almacen: 'FAK', proveedor: 'Selladores del Golfo', renglones: 2, importe: 9300.0, recibio: 'M. Rangel' },
];

export const SALIDAS_DEMO: AlmSalidaDemo[] = [
    { id: 31, folio: 'VAL-2608-0031', fecha: '2026-08-05', almacen: 'AG', obra_destino: 'T4 — Torre 4', solicitante: 'M. Rangel', recibe: 'Cuadrilla 3', renglones: 2, motivo: 'Montaje eje 4' },
    { id: 30, folio: 'VAL-2608-0030', fecha: '2026-08-04', almacen: 'FAK', obra_destino: 'T4 — Torre 4', solicitante: 'L. Ortega', recibe: 'A. Pérez', renglones: 1, motivo: 'Sellado de fachada' },
    { id: 28, folio: 'VAL-2608-0028', fecha: '2026-08-02', almacen: 'FAD', obra_destino: 'T4 — Torre 4', solicitante: 'L. Ortega', recibe: 'Cuadrilla 2', renglones: 1, motivo: 'Equipo de protección' },
];

export const TRANSFERENCIAS_DEMO: AlmTransferenciaDemo[] = [
    { id: 4, folio: 'TRA-2608-0004', fecha: '2026-08-04', origen: 'AG', destino: 'FAK', renglones: 1, autorizo: 'J. Briones' },
    { id: 3, folio: 'TRA-2608-0003', fecha: '2026-07-30', origen: 'AG', destino: 'E', renglones: 2, autorizo: 'J. Briones' },
];

/** Lo que hay del producto en ese almacén, para avisar en la captura de salidas. */
export function disponibleDemo(almacen: string, codigoProducto: string): number | null {
    const fila = EXISTENCIAS_DEMO.find((e) => e.almacen === almacen && e.producto === codigoProducto);

    return fila ? fila.cantidad : null;
}
