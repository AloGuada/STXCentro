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
    AlmClasificacionAbc,
    AlmConteoDemo,
    AlmConteoEstatus,
    AlmDevolucionDemo,
    AlmDocumentoTipo,
    AlmEntradaDemo,
    AlmExistenciaDemo,
    AlmGrupoTrabajoDemo,
    AlmMovimientoDemo,
    AlmPedidoDemo,
    AlmPedidoEstatus,
    AlmPrecioDemo,
    AlmPrestamoDemo,
    AlmPrestamoEstatus,
    AlmProductoDemo,
    AlmProductoTipo,
    AlmReglaAbc,
    AlmReglaAprobacion,
    AlmSalidaDemo,
    AlmTransferenciaDemo,
    AlmTransferenciaEstatus,
    AlmUbicacionDemo,
    AlmUbicacionTipo,
    AlmUsuarioDemo,
} from '@/types/models';

export const ALMACENES_DEMO = [
    { id: 1, clave: 'AG', nombre: 'Almacén general', obra: null, tipo: 'insumos' as const },
    { id: 6, clave: 'HER', nombre: 'Almacén de herramienta', obra: null, tipo: 'herramienta' as const },
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

/**
 * Lugares físicos dentro de cada almacén. El almacén dice en qué bodega está el
 * material; esto dice en qué anaquel, que es lo que se necesita para ir por él
 * y para recorrer una zona contando.
 *
 * Cuelgan unos de otros: un nivel vive dentro de un rack y el rack dentro de un
 * pasillo. La ruta completa (`AG · Pasillo A / Rack A-1 / Nivel 2`) se arma con
 * `rutaUbicacion`.
 */
export const UBICACIONES_DEMO: AlmUbicacionDemo[] = [
    { id: 1, almacen: 'AG', codigo: 'A', nombre: 'Pasillo A', tipo: 'pasillo', padre_id: null, activa: true },
    { id: 2, almacen: 'AG', codigo: 'A-1', nombre: 'Rack A-1', tipo: 'rack', padre_id: 1, activa: true },
    { id: 3, almacen: 'AG', codigo: 'A-1-1', nombre: 'Nivel 1', tipo: 'nivel', padre_id: 2, activa: true },
    { id: 4, almacen: 'AG', codigo: 'A-1-2', nombre: 'Nivel 2', tipo: 'nivel', padre_id: 2, activa: true },
    { id: 5, almacen: 'AG', codigo: 'A-2', nombre: 'Rack A-2', tipo: 'rack', padre_id: 1, activa: true },
    { id: 6, almacen: 'AG', codigo: 'A-2-1', nombre: 'Nivel 1', tipo: 'nivel', padre_id: 5, activa: true },
    { id: 7, almacen: 'AG', codigo: 'B', nombre: 'Pasillo B', tipo: 'pasillo', padre_id: null, activa: true },
    { id: 8, almacen: 'AG', codigo: 'B-1', nombre: 'Rack B-1', tipo: 'rack', padre_id: 7, activa: true },
    { id: 9, almacen: 'AG', codigo: 'INT', nombre: 'Intemperie', tipo: 'zona', padre_id: null, activa: true },
    { id: 10, almacen: 'FAK', codigo: 'C1', nombre: 'Contenedor 1', tipo: 'contenedor', padre_id: null, activa: true },
    { id: 11, almacen: 'FAK', codigo: 'C2', nombre: 'Contenedor 2', tipo: 'contenedor', padre_id: null, activa: true },
    { id: 12, almacen: 'FAD', codigo: 'C1', nombre: 'Contenedor 1', tipo: 'contenedor', padre_id: null, activa: true },
    { id: 13, almacen: 'HER', codigo: 'EST', nombre: 'Estantería de herramienta', tipo: 'rack', padre_id: null, activa: true },
    { id: 14, almacen: 'HER', codigo: 'CAN', nombre: 'Canastilla de préstamo', tipo: 'zona', padre_id: null, activa: true },
    // Se dejó de usar cuando se vació el pasillo viejo: no se borra, porque hay
    // movimientos históricos que la mencionan.
    { id: 15, almacen: 'AG', codigo: 'OLD', nombre: 'Pasillo viejo', tipo: 'pasillo', padre_id: null, activa: false },
];

/** Cómo se lee cada tipo de lugar. */
export const TIPOS_UBICACION: Record<AlmUbicacionTipo, string> = {
    pasillo: 'Pasillo',
    rack: 'Rack',
    nivel: 'Nivel',
    contenedor: 'Contenedor',
    zona: 'Zona',
};

/** Las ubicaciones de un almacén, sin importar de quién cuelguen. */
export function ubicacionesDe(claveAlmacen: string): AlmUbicacionDemo[] {
    return UBICACIONES_DEMO.filter((u) => u.almacen === claveAlmacen);
}

/**
 * La ruta legible de una ubicación, subiendo por sus padres:
 * `Pasillo A / Rack A-1 / Nivel 2`.
 */
export function rutaUbicacion(ubicacionId: number | null): string | null {
    if (ubicacionId === null) {
        return null;
    }

    const partes: string[] = [];
    let actual = UBICACIONES_DEMO.find((u) => u.id === ubicacionId);

    while (actual) {
        partes.unshift(actual.nombre);
        actual = actual.padre_id === null ? undefined : UBICACIONES_DEMO.find((u) => u.id === actual?.padre_id);
    }

    return partes.length > 0 ? partes.join(' / ') : null;
}

export const EXISTENCIAS_DEMO: AlmExistenciaDemo[] = [
    { almacen: 'AG', producto: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA', cantidad: 12780, costo_promedio: 4.35, ubicacion_id: 4 },
    { almacen: 'AG', producto: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', cantidad: 340.5, costo_promedio: 62.1, ubicacion_id: 3 },
    { almacen: 'AG', producto: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS', cantidad: 96, costo_promedio: 218.4, ubicacion_id: 8 },
    { almacen: 'AG', producto: 'ART-00011', descripcion: 'Broca cobalto 1/4"', unidad: 'PZA', cantidad: 64, costo_promedio: 89.0, ubicacion_id: 6 },
    { almacen: 'AG', producto: 'AND-MOD', descripcion: 'Módulo de andamio 1.90 m', unidad: 'PZA', cantidad: 320, costo_promedio: 1850.0, ubicacion_id: 9 },
    // Sin lugar asignado: es justo lo que la pantalla debe hacer visible.
    { almacen: 'AG', producto: 'ART-00012', descripcion: 'Extensión eléctrica 25 m calibre 12', unidad: 'PZA', cantidad: 9, costo_promedio: 1240.0, ubicacion_id: null },
    { almacen: 'FAK', producto: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', cantidad: 42, costo_promedio: 310.0, ubicacion_id: 11 },
    { almacen: 'FAK', producto: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA', cantidad: 8, costo_promedio: 27.5, ubicacion_id: null },
    { almacen: 'FAD', producto: 'GUA-CARN', descripcion: 'Guante de carnaza', unidad: 'PAR', cantidad: 0, costo_promedio: 48.9, ubicacion_id: null },
    { almacen: 'E', producto: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', cantidad: 74.25, costo_promedio: 63.8, ubicacion_id: null },
    { almacen: 'HER', producto: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', unidad: 'PZA', cantidad: 14, costo_promedio: 2180.0, ubicacion_id: 13 },
];

export const MOVIMIENTOS_DEMO: AlmMovimientoDemo[] = [
    // El sobrante de una obra vuelve por transferencia, no por devolución: la
    // devolución es de piezas con serie y no mueve saldo.
    { id: 10, fecha: '2026-08-05 18:10', almacen: 'AG', producto: 'TOR-0012', tipo: 'transferencia_entrada', cantidad: 300, saldo_nuevo: 12780, referencia: 'TRA-2608-0006', usuario: 'M. Rangel', observaciones: 'Sobrante de T4, desde FAK' },
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

/**
 * Cada transferencia es un solo folio con dos firmas. La de arriba va en el
 * camión: ya salió de AG y todavía no es existencia de E, y ese es justo el
 * saldo que el kardex tiene que poder mostrar aparte.
 */
export const TRANSFERENCIAS_DEMO: AlmTransferenciaDemo[] = [
    {
        id: 7,
        folio: 'TRA-2608-0007',
        fecha_envio: '2026-08-10',
        fecha_recepcion: null,
        origen: 'AG',
        destino: 'E',
        estatus: 'en_transito',
        autorizo: 'J. Briones',
        envio: 'M. Rangel',
        recibio: null,
        pedido_folio: 'PED-2608-0021',
        faltante_responsable: null,
        observaciones: 'Va en la Ranger con el material de MBP',
        renglones: [
            { producto_id: 103, codigo: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS', cantidad_enviada: 20, cantidad_recibida: null },
            { producto_id: 102, codigo: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', cantidad_enviada: 60, cantidad_recibida: null },
        ],
    },
    // El sobrante de una obra vuelve por aquí, no por devolución: la devolución
    // es de piezas con serie y no mueve saldo.
    {
        id: 6,
        folio: 'TRA-2608-0006',
        fecha_envio: '2026-08-05',
        fecha_recepcion: '2026-08-05',
        origen: 'FAK',
        destino: 'AG',
        estatus: 'recibida',
        autorizo: 'J. Briones',
        envio: 'L. Ortega',
        recibio: 'M. Rangel',
        pedido_folio: null,
        faltante_responsable: null,
        observaciones: 'Sobrante de T4',
        renglones: [
            { producto_id: 101, codigo: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA', cantidad_enviada: 300, cantidad_recibida: 300 },
        ],
    },
    {
        id: 4,
        folio: 'TRA-2608-0004',
        fecha_envio: '2026-08-04',
        fecha_recepcion: '2026-08-04',
        origen: 'AG',
        destino: 'FAK',
        estatus: 'recibida',
        autorizo: 'J. Briones',
        envio: 'M. Rangel',
        recibio: 'L. Ortega',
        pedido_folio: null,
        faltante_responsable: null,
        observaciones: null,
        renglones: [
            { producto_id: 106, codigo: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', cantidad_enviada: 12, cantidad_recibida: 12 },
        ],
    },
    // Llegó menos de lo que salió: se recibió lo que había y la diferencia
    // quedó con dueño y fecha, que es lo que se pierde cuando el documento se
    // captura de un solo golpe.
    {
        id: 3,
        folio: 'TRA-2608-0003',
        fecha_envio: '2026-07-30',
        fecha_recepcion: '2026-07-31',
        origen: 'AG',
        destino: 'E',
        estatus: 'recibida',
        autorizo: 'J. Briones',
        envio: 'M. Rangel',
        recibio: 'L. Ortega',
        pedido_folio: null,
        faltante_responsable: 'M. Rangel',
        observaciones: 'Un tambo de electrodo llegó abierto',
        renglones: [
            { producto_id: 102, codigo: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', cantidad_enviada: 80, cantidad_recibida: 74.25 },
            { producto_id: 104, codigo: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA', cantidad_enviada: 12, cantidad_recibida: 12 },
        ],
    },
];

/**
 * Cómo se lee cada tiempo. «Recibida con faltante» no es un estatus aparte: es
 * una recepción cerrada en la que lo confirmado no alcanzó lo enviado, y se
 * distingue en pantalla porque es lo que alguien tiene que ir a explicar.
 */
export const ESTATUS_TRANSFERENCIA: Record<AlmTransferenciaEstatus, { etiqueta: string; clase: string }> = {
    en_transito: { etiqueta: 'En tránsito', clase: 'badge-warning' },
    recibida: { etiqueta: 'Recibida', clase: 'badge-success' },
};

/**
 * Lo enviado, lo confirmado y lo que se quedó en el camino.
 *
 * El faltante se suma por renglón y nunca se compensa entre renglones: que
 * llegara un disco de más no repone el electrodo que faltó.
 */
export function resumenTransferencia(transferencia: AlmTransferenciaDemo): {
    enviado: number;
    recibido: number;
    faltante: number;
    renglonesConFaltante: number;
} {
    return transferencia.renglones.reduce(
        (resumen, renglon) => {
            // Sin confirmar todavía no hay faltante: lo que va en el camión no
            // se le debe a nadie, está en tránsito.
            const confirmado = renglon.cantidad_recibida;
            const faltante = confirmado === null ? 0 : Math.max(0, renglon.cantidad_enviada - confirmado);

            return {
                enviado: resumen.enviado + renglon.cantidad_enviada,
                recibido: resumen.recibido + (confirmado ?? 0),
                faltante: resumen.faltante + faltante,
                renglonesConFaltante: resumen.renglonesConFaltante + (faltante > 0 ? 1 : 0),
            };
        },
        { enviado: 0, recibido: 0, faltante: 0, renglonesConFaltante: 0 },
    );
}

export const AJUSTES_DEMO: AlmAjusteDemo[] = [
    { id: 12, folio: 'AJU-2608-0012', fecha: '2026-08-01', almacen: 'AG', motivo: 'conteo_fisico', renglones: 1, diferencia_neta: -4, autorizo: 'J. Briones' },
    { id: 11, folio: 'AJU-2607-0011', fecha: '2026-07-28', almacen: 'FAK', motivo: 'merma', renglones: 2, diferencia_neta: -7, autorizo: 'J. Briones' },
    { id: 10, folio: 'AJU-2607-0010', fecha: '2026-07-21', almacen: 'AG', motivo: 'error_captura', renglones: 1, diferencia_neta: 150, autorizo: 'M. Rangel' },
];

// Salen de los préstamos ya cerrados: cada devolución es el otro extremo de un
// resguardo, por eso las fechas empatan con `fecha_retorno` de PRESTAMOS_DEMO.
export const DEVOLUCIONES_DEMO: AlmDevolucionDemo[] = [
    { id: 6, folio: 'DEV-2607-0006', fecha: '2026-07-26', devolvio: 'M. Rangel', recibio: 'J. Briones', piezas: 1, almacenes: ['HER'], con_dano: 0 },
    { id: 5, folio: 'DEV-2607-0005', fecha: '2026-07-19', devolvio: 'A. Pérez', recibio: 'J. Briones', piezas: 1, almacenes: ['HER'], con_dano: 1 },
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
    { id: 101, codigo: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA', codigo_barras: 'TOR-0012', marca: null, modelo: null, idsteelex: 'MAT-000412', area: 'Estructura', clasificacion_abc: 'A', precio_ultimo: 4.4, imagen_url: imagenDemo('TOR', '#64748b'), tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 5000, existencia_total: 12780 },
    { id: 102, codigo: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', codigo_barras: 'ELE-7018', marca: 'Infra', modelo: 'E7018', idsteelex: '7018-125', area: null, clasificacion_abc: 'A', precio_ultimo: 63.8, imagen_url: imagenDemo('ELE', '#0f766e'), tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 200, existencia_total: 414.75 },
    { id: 103, codigo: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS', codigo_barras: 'PIN-PRIM', marca: 'Comex', modelo: 'Epoxiprimer 300', idsteelex: null, area: 'Pintura', clasificacion_abc: 'B', precio_ultimo: 218.4, imagen_url: imagenDemo('PIN', '#b45309'), tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 50, existencia_total: 96 },
    { id: 104, codigo: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA', codigo_barras: 'DIS-0450', marca: 'Austromex', modelo: '742', idsteelex: null, area: null, clasificacion_abc: 'B', precio_ultimo: 27.5, imagen_url: imagenDemo('DIS', '#7c3aed'), tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 40, existencia_total: 8 },
    // El código de barras que ya venía impreso en la caja: se respeta en vez de
    // pegarle encima una etiqueta nuestra.
    { id: 105, codigo: 'GUA-CARN', descripcion: 'Guante de carnaza', unidad: 'PAR', codigo_barras: '7501234567890', marca: null, modelo: null, idsteelex: null, area: null, clasificacion_abc: 'C', precio_ultimo: 48.9, imagen_url: null, tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 30, existencia_total: 0 },
    { id: 106, codigo: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', codigo_barras: 'SIL-EST', marca: 'Sika', modelo: 'Sikasil SG-20', idsteelex: null, area: null, clasificacion_abc: 'A', precio_ultimo: 318.0, imagen_url: null, tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 20, existencia_total: 42 },
    // Los dos últimos que se dieron de alta ya nacieron con el consecutivo.
    { id: 107, codigo: 'ART-00011', descripcion: 'Broca cobalto 1/4"', unidad: 'PZA', codigo_barras: 'ART-00011', marca: 'DeWalt', modelo: 'DW1207', idsteelex: null, area: null, clasificacion_abc: 'C', precio_ultimo: 89.0, imagen_url: null, tipo: 'insumo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 20, existencia_total: 64 },
    { id: 108, codigo: 'ART-00012', descripcion: 'Extensión eléctrica 25 m calibre 12', unidad: 'PZA', codigo_barras: 'ART-00012', marca: 'Voltech', modelo: '48042', idsteelex: null, area: null, clasificacion_abc: 'B', precio_ultimo: 1240.0, imagen_url: null, tipo: 'activo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: 4, existencia_total: 9 },
    // La pulidora se presta bajo resguardo: cada pieza lleva serie y dueño.
    { id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', unidad: 'PZA', codigo_barras: 'PUL-4120', marca: 'DeWalt', modelo: 'DWE4120', idsteelex: 'HERR-0098', area: 'Herramienta', clasificacion_abc: 'A', precio_ultimo: 2180.0, imagen_url: imagenDemo('PUL', '#be123c'), tipo: 'activo', requiere_verificacion: true, controla_inventario: true, se_controla_por_pieza: true, stock_minimo: null, existencia_total: 14 },
    // El andamio también se presta, pero por bulto: serializarlo no aporta.
    { id: 202, codigo: 'AND-MOD', descripcion: 'Módulo de andamio 1.90 m', unidad: 'PZA', codigo_barras: 'AND-MOD', marca: null, modelo: null, idsteelex: null, area: null, clasificacion_abc: 'C', precio_ultimo: 1850.0, imagen_url: null, tipo: 'activo', requiere_verificacion: false, controla_inventario: true, se_controla_por_pieza: false, stock_minimo: null, existencia_total: 320 },
    { id: 301, codigo: 'VEH-0007', descripcion: 'Camioneta Ford Ranger 2024', unidad: 'PZA', codigo_barras: 'VEH-0007', marca: 'Ford', modelo: 'Ranger XL 2024', idsteelex: null, area: null, clasificacion_abc: 'A', precio_ultimo: 612000.0, imagen_url: imagenDemo('VEH', '#1d4ed8'), tipo: 'activo', requiere_verificacion: true, controla_inventario: true, se_controla_por_pieza: true, stock_minimo: null, existencia_total: 1 },
    // Sin kardex no hay nada que contar ni que etiquetar: un flete no se guarda.
    { id: 302, codigo: 'SRV-FLET', descripcion: 'Flete foráneo', unidad: 'SRV', codigo_barras: null, marca: null, modelo: null, idsteelex: null, area: null, clasificacion_abc: 'C', precio_ultimo: 8500.0, imagen_url: null, tipo: 'insumo', requiere_verificacion: false, controla_inventario: false, se_controla_por_pieza: false, stock_minimo: null, existencia_total: 0 },
];

/**
 * El código lo pone el sistema: un consecutivo global, sin familias. Sale del
 * mayor `ART-#####` que exista más uno, así que los códigos viejos con prefijo
 * propio (`TOR-0012`) conviven sin estorbar — simplemente ya no se generan.
 */
export function siguienteCodigoArticulo(): string {
    const ultimo = ARTICULOS_DEMO.reduce((mayor, articulo) => {
        const match = /^ART-(\d+)$/.exec(articulo.codigo);

        return match ? Math.max(mayor, Number(match[1])) : mayor;
    }, 0);

    return `ART-${String(ultimo + 1).padStart(5, '0')}`;
}

/**
 * Histórico de precios por artículo. En el mono ya existe
 * `costos_producto_precios`, que se alimenta solo desde las cotizaciones de
 * una requisición: aquí sólo se consulta, no se captura.
 */
export const PRECIOS_DEMO: Record<number, AlmPrecioDemo[]> = {
    101: [
        { id: 1, fecha: '2026-08-03', proveedor: 'Aceros del Norte S.A.', precio: 4.4, moneda: 'MXN', origen: 'OC-2608-0044' },
        { id: 2, fecha: '2026-06-18', proveedor: 'Tornillería Industrial', precio: 4.35, moneda: 'MXN', origen: 'Cotización REQ-2606-0112' },
        { id: 3, fecha: '2026-04-02', proveedor: 'Aceros del Norte S.A.', precio: 4.1, moneda: 'MXN', origen: 'Cotización REQ-2604-0071' },
        { id: 4, fecha: '2026-01-15', proveedor: 'Tornillería Industrial', precio: 3.9, moneda: 'MXN', origen: 'Cotización REQ-2601-0009' },
    ],
    102: [
        { id: 5, fecha: '2026-08-01', proveedor: 'Soldaduras Industriales', precio: 63.8, moneda: 'MXN', origen: 'OC-2608-0041' },
        { id: 6, fecha: '2026-05-22', proveedor: 'Soldaduras Industriales', precio: 62.1, moneda: 'MXN', origen: 'Cotización REQ-2605-0098' },
        { id: 7, fecha: '2026-02-10', proveedor: 'Infra Monterrey', precio: 59.5, moneda: 'MXN', origen: 'Cotización REQ-2602-0033' },
    ],
    106: [
        { id: 8, fecha: '2026-07-28', proveedor: 'Selladores del Golfo', precio: 318.0, moneda: 'MXN', origen: 'OC-2607-0038' },
        { id: 9, fecha: '2026-03-11', proveedor: 'Selladores del Golfo', precio: 310.0, moneda: 'MXN', origen: 'Cotización REQ-2603-0055' },
    ],
    201: [
        { id: 10, fecha: '2026-06-30', proveedor: 'Herramientas del Bajío', precio: 2180.0, moneda: 'MXN', origen: 'OC-2606-0029' },
        { id: 11, fecha: '2025-11-14', proveedor: 'Truper Distribuidor', precio: 1980.0, moneda: 'MXN', origen: 'Cotización REQ-2511-0201' },
    ],
};

/** Los precios de un artículo, del más reciente al más viejo. */
export function preciosDe(articuloId: number): AlmPrecioDemo[] {
    return PRECIOS_DEMO[articuloId] ?? [];
}

/**
 * Piezas identificadas de los artículos marcados `se_controla_por_pieza`. Cada
 * una suma 1 a la existencia de su producto; el kardex por cantidad no cambia.
 */
export const ACTIVOS_DEMO: AlmActivoDemo[] = [
    { id: 1, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-07', codigo_barras: 'PUL-4120-07', almacen: 'HER', ubicacion: 'Estantería de herramienta', estatus: 'prestado', condicion: 'Buena' },
    { id: 2, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-08', codigo_barras: 'PUL-4120-08', almacen: 'HER', ubicacion: 'Estantería de herramienta', estatus: 'prestado', condicion: 'Buena' },
    { id: 3, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-11', codigo_barras: 'PUL-4120-11', almacen: 'HER', ubicacion: 'Canastilla de préstamo', estatus: 'disponible', condicion: 'Buena' },
    { id: 4, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-12', codigo_barras: 'PUL-4120-12', almacen: 'HER', ubicacion: 'Estantería de herramienta', estatus: 'en_reparacion', condicion: 'Carbones gastados' },
    { id: 5, producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', no_serie: 'PUL-4120-14', codigo_barras: 'PUL-4120-14', almacen: 'HER', ubicacion: 'Estantería de herramienta', estatus: 'prestado', condicion: 'Regular' },
    // El VIN trae letras y números pero no guiones: se etiqueta con el nuestro
    // para que el lector no dependa de lo que traiga grabado el fabricante.
    { id: 6, producto_id: 301, codigo: 'VEH-0007', descripcion: 'Camioneta Ford Ranger 2024', no_serie: '3FTTW8E9XRA12345', codigo_barras: 'VEH-0007-01', almacen: 'AG', ubicacion: null, estatus: 'prestado', condicion: 'Buena' },
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

/** Lo que trae afuera una persona, para no tener que ir a buscarlo a Préstamos. */
export function prestamosAbiertosDe(responsable: string): AlmPrestamoDemo[] {
    return PRESTAMOS_DEMO.filter((p) => p.responsable === responsable && p.estatus === 'abierto');
}

/** Quién trae herramienta sin devolver, ordenado por quién trae más. */
export function responsablesConPrestamos(): string[] {
    const abiertos = PRESTAMOS_DEMO.filter((p) => p.estatus === 'abierto');

    return [...new Set(abiertos.map((p) => p.responsable))].sort(
        (a, b) => prestamosAbiertosDe(b).length - prestamosAbiertosDe(a).length || a.localeCompare(b),
    );
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
 * Las cuadrillas de planta, que en firme salen de `prod_grupos_trabajo`. Aquí
 * se copian a mano nada más para la maqueta: el almacén no las da de alta ni
 * las edita, sólo las nombra al prestar herramienta que se queda en planta.
 */
export const GRUPOS_TRABAJO_DEMO: AlmGrupoTrabajoDemo[] = [
    { id: 1, descripcion: 'Cuadrilla A · Armado', ubicaciones: ['Línea 1 · Módulo 1', 'Línea 1 · Módulo 2'], empleados: 4 },
    { id: 2, descripcion: 'Cuadrilla B · Soldadura', ubicaciones: ['Línea 2 · Módulo 1'], empleados: 3 },
    { id: 3, descripcion: 'Cuadrilla C · Habilitado', ubicaciones: ['Patio de habilitado'], empleados: 2 },
    { id: 4, descripcion: 'Cuadrilla D · Pintura', ubicaciones: ['Nave de pintura', 'Patio de habilitado'], empleados: 2 },
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

/**
 * En qué se puede medir un artículo. Lo comparten el alta y la edición: si cada
 * pantalla trajera su propia lista, un artículo dado de alta en MTS podría
 * quedarse sin esa opción al corregirlo.
 */
export const UNIDADES_ARTICULO = ['PZA', 'KG', 'LTS', 'MTS', 'PAR', 'CTO', 'SRV'];

/** Cómo se lee cada tipo de producto en pantalla. */
export const TIPOS_ARTICULO: Record<AlmProductoTipo, string> = {
    insumo: 'Insumo',
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
    prestamo: 'Aparte del resguardo que firma quien se la lleva: esto es quién autoriza que salga del almacén.',
    devolucion: 'Cierra el resguardo de una pieza. No mueve existencia, pero deja constancia de cómo volvió.',
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

/**
 * Cada cuánto se cuenta cada clase.
 *
 * La idea del inventario cíclico es no volver a parar el almacén un fin de
 * semana entero: se cuenta un pedazo cada semana, y lo caro se repasa más
 * seguido que lo barato. Los días son los de un ABC clásico y se pueden mover.
 */
export const REGLAS_ABC: AlmReglaAbc[] = [
    {
        clasificacion: 'A',
        frecuencia_dias: 30,
        etiqueta: 'Mensual',
        descripcion: 'Lo caro o de alta rotación. Un faltante aquí se nota en el costo de la obra.',
    },
    {
        clasificacion: 'B',
        frecuencia_dias: 90,
        etiqueta: 'Trimestral',
        descripcion: 'Movimiento y valor medios. Se repasa cada tres meses.',
    },
    {
        clasificacion: 'C',
        frecuencia_dias: 180,
        etiqueta: 'Semestral',
        descripcion: 'Lo barato o de poco movimiento. Contarlo seguido cuesta más de lo que vale.',
    },
];

/** Color de la clase en las tablas. */
export const CLASES_ABC: Record<AlmClasificacionAbc, string> = {
    A: 'badge-error',
    B: 'badge-warning',
    C: 'badge-ghost',
};

/**
 * Hojas de conteo. Las `programado` las generó el calendario ABC; la `manual`
 * la levantó el jefe de almacén porque sospechaba un faltante.
 *
 * `cantidad_sistema` viene congelada del momento en que se generó la hoja: si
 * se leyera al cerrar, cualquier salida capturada a media mañana convertiría un
 * conteo correcto en una diferencia inventada.
 */
export const CONTEOS_DEMO: AlmConteoDemo[] = [
    {
        id: 8,
        folio: 'CIC-2608-0008',
        origen: 'programado',
        almacen: 'AG',
        ubicacion: 'Pasillo A / Rack A-1',
        clasificacion: 'A',
        fecha_programada: '2026-08-11',
        fecha_cierre: null,
        responsable: 'M. Rangel',
        estatus: 'contando',
        ajuste_folio: null,
        renglones: [
            { producto_id: 101, codigo: 'TOR-0012', descripcion: 'Tornillo A325 3/4" x 2"', unidad: 'PZA', ubicacion: 'Nivel 2', cantidad_sistema: 12780, cantidad_contada: 12742 },
            { producto_id: 102, codigo: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', ubicacion: 'Nivel 1', cantidad_sistema: 340.5, cantidad_contada: 340.5 },
            { producto_id: 106, codigo: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', ubicacion: 'Nivel 2', cantidad_sistema: 0, cantidad_contada: null },
        ],
    },
    {
        id: 9,
        folio: 'CIC-2608-0009',
        origen: 'programado',
        almacen: 'AG',
        ubicacion: 'Pasillo B / Rack B-1',
        clasificacion: 'A',
        fecha_programada: '2026-08-12',
        fecha_cierre: null,
        responsable: 'M. Rangel',
        estatus: 'pendiente',
        ajuste_folio: null,
        renglones: [
            { producto_id: 103, codigo: 'PIN-PRIM', descripcion: 'Primario epóxico gris', unidad: 'LTS', ubicacion: 'Rack B-1', cantidad_sistema: 96, cantidad_contada: null },
        ],
    },
    {
        id: 10,
        folio: 'CIC-2608-0010',
        origen: 'manual',
        almacen: 'HER',
        ubicacion: 'Estantería de herramienta',
        clasificacion: null,
        fecha_programada: '2026-08-10',
        fecha_cierre: null,
        responsable: 'J. Briones',
        estatus: 'pendiente',
        ajuste_folio: null,
        renglones: [
            { producto_id: 201, codigo: 'PUL-4120', descripcion: 'Pulidora 4 1/2" 850W', unidad: 'PZA', ubicacion: 'Estantería', cantidad_sistema: 14, cantidad_contada: null },
        ],
    },
    // Vencido: tocaba el 4 y sigue sin contarse.
    {
        id: 6,
        folio: 'CIC-2608-0006',
        origen: 'programado',
        almacen: 'FAK',
        ubicacion: 'Contenedor 2',
        clasificacion: 'B',
        fecha_programada: '2026-08-04',
        fecha_cierre: null,
        responsable: 'L. Ortega',
        estatus: 'pendiente',
        ajuste_folio: null,
        renglones: [
            { producto_id: 106, codigo: 'SIL-EST', descripcion: 'Silicón estructural negro', unidad: 'CTO', ubicacion: 'Contenedor 2', cantidad_sistema: 42, cantidad_contada: null },
            { producto_id: 104, codigo: 'DIS-0450', descripcion: 'Disco de corte 4 1/2"', unidad: 'PZA', ubicacion: null, cantidad_sistema: 8, cantidad_contada: null },
        ],
    },
    {
        id: 5,
        folio: 'CIC-2608-0005',
        origen: 'programado',
        almacen: 'AG',
        ubicacion: 'Pasillo A / Rack A-2',
        clasificacion: 'C',
        fecha_programada: '2026-08-03',
        fecha_cierre: '2026-08-03',
        responsable: 'M. Rangel',
        estatus: 'cerrado',
        // Cerró con diferencia, así que dejó su ajuste: el conteo no toca el
        // saldo por su cuenta, lo mueve el ajuste que genera.
        ajuste_folio: 'AJU-2608-0012',
        renglones: [
            { producto_id: 107, codigo: 'ART-00011', descripcion: 'Broca cobalto 1/4"', unidad: 'PZA', ubicacion: 'Nivel 1', cantidad_sistema: 68, cantidad_contada: 64 },
        ],
    },
    {
        id: 4,
        folio: 'CIC-2607-0004',
        origen: 'manual',
        almacen: 'AG',
        ubicacion: null,
        clasificacion: null,
        fecha_programada: '2026-07-27',
        fecha_cierre: '2026-07-28',
        responsable: 'J. Briones',
        estatus: 'cerrado',
        ajuste_folio: null,
        renglones: [
            { producto_id: 102, codigo: 'ELE-7018', descripcion: 'Electrodo 7018 1/8"', unidad: 'KG', ubicacion: 'Nivel 1', cantidad_sistema: 140.5, cantidad_contada: 140.5 },
        ],
    },
];

/** Cómo se lee cada estado de una hoja de conteo. */
export const ESTATUS_CONTEO: Record<AlmConteoEstatus, { etiqueta: string; clase: string }> = {
    pendiente: { etiqueta: 'Por contar', clase: 'badge-warning' },
    contando: { etiqueta: 'Contando', clase: 'badge-info' },
    cerrado: { etiqueta: 'Cerrado', clase: 'badge-success' },
    cancelado: { etiqueta: 'Cancelado', clase: 'badge-ghost' },
};

/**
 * Resumen de una hoja: cuánto se lleva contado y cuánto se desvía.
 *
 * La diferencia se mide en valor absoluto por renglón, no en neto: 100 de más
 * en un artículo y 100 de menos en otro son dos errores, no un empate.
 */
export function resumenConteo(conteo: AlmConteoDemo): {
    total: number;
    contados: number;
    diferencias: number;
    vencido: boolean;
} {
    const contados = conteo.renglones.filter((r) => r.cantidad_contada !== null);

    return {
        total: conteo.renglones.length,
        contados: contados.length,
        diferencias: contados.filter((r) => r.cantidad_contada !== r.cantidad_sistema).length,
        vencido: conteo.estatus !== 'cerrado' && conteo.estatus !== 'cancelado' && conteo.fecha_programada < HOY_DEMO,
    };
}
