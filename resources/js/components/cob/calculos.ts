import type { CobAnticipo, CobComparativo, CobDeduccion, CobEstimacion, CobPartida, Obra } from '@/types/models';

export type ResumenFinanciero = {
    presupuestoPartidas: number;
    partidasAdicionales: number;
    presupuestoEjecutar: number;
    totalAnticiposFacturados: number;
    totalAnticiposCobrados: number;
    totalEstimacionesFacturadas: number;
    totalEstimacionesCobradas: number;
    totalFacturado: number;
    totalCobrado: number;
    totalDeducciones: number;
    porFacturar: number;
    porCobrar: number;
};

export function calcularResumen(
    partidas: CobPartida[],
    estimaciones: CobEstimacion[],
    anticipos: CobAnticipo[],
    comparativos: CobComparativo[],
    deducciones: CobDeduccion[],
): ResumenFinanciero {
    const presupuestoPartidas = partidas
        .filter((p) => !p.es_adicional)
        .reduce((sum, p) => sum + Number(p.monto), 0);

    const partidasAdicionales = partidas
        .filter((p) => p.es_adicional)
        .reduce((sum, p) => sum + Number(p.monto), 0);

    const lastComparativo = comparativos
        .filter((c) => c.estado === 'implementado' || c.estado === 'aprobado')
        .sort((a, b) => (a.id > b.id ? -1 : 1))[0];

    const presupuestoEjecutar = lastComparativo
        ? Number(lastComparativo.monto_impacto)
        : presupuestoPartidas + partidasAdicionales;

    const totalAnticiposFacturados = anticipos.reduce((sum, a) => sum + Number(a.monto), 0);

    const totalAnticiposCobrados = anticipos
        .filter((a) => a.fecha_pagado !== null)
        .reduce((sum, a) => sum + Number(a.monto), 0);

    const estadosFacturados = ['facturada', 'pago_parcial', 'pagado'];

    const totalEstimacionesFacturadas = estimaciones
        .filter((e) => estadosFacturados.includes(e.estado))
        .reduce((sum, e) => sum + Number(e.monto_estimado), 0);

    const totalEstimacionesCobradas = estimaciones
        .filter((e) => e.estado === 'pagado')
        .reduce((sum, e) => sum + Number(e.monto_estimado), 0)
        + estimaciones
            .filter((e) => e.estado === 'pago_parcial')
            .reduce((sum, e) => sum + (e.pagos?.reduce((s, p) => s + Number(p.monto_pagado), 0) ?? 0), 0);

    const totalFacturado = totalAnticiposFacturados + totalEstimacionesFacturadas;
    const totalCobrado = totalAnticiposCobrados + totalEstimacionesCobradas;

    const totalDeducciones = deducciones.reduce((sum, d) => sum + Number(d.monto), 0);

    const porFacturar = presupuestoEjecutar - totalFacturado - totalDeducciones;
    const porCobrar = presupuestoEjecutar - totalCobrado - totalDeducciones;

    return {
        presupuestoPartidas,
        partidasAdicionales,
        presupuestoEjecutar,
        totalAnticiposFacturados,
        totalAnticiposCobrados,
        totalEstimacionesFacturadas,
        totalEstimacionesCobradas,
        totalFacturado,
        totalCobrado,
        totalDeducciones,
        porFacturar,
        porCobrar,
    };
}

export type DatosProyecto = ResumenFinanciero & {
    obra: Obra;
    porcentajeFacturado: number;
    porcentajeCobrado: number;
};

export function calcularDatosProyecto(obra: Obra): DatosProyecto {
    const resumen = calcularResumen(
        obra.partidas ?? [],
        obra.estimaciones ?? [],
        obra.anticipos ?? [],
        obra.comparativos ?? [],
        obra.deducciones ?? [],
    );

    const porcentajeFacturado = resumen.presupuestoEjecutar > 0
        ? (resumen.totalFacturado / resumen.presupuestoEjecutar) * 100
        : 0;

    const porcentajeCobrado = resumen.presupuestoEjecutar > 0
        ? (resumen.totalCobrado / resumen.presupuestoEjecutar) * 100
        : 0;

    return {
        obra,
        ...resumen,
        porcentajeFacturado,
        porcentajeCobrado,
    };
}
