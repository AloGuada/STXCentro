import type { CobAnticipo, CobComparativo, CobDeduccion, CobEstimacion, CobPartida, Obra, Proyecto } from '@/types/models';

export type ResumenFinanciero = {
    presupuestoPartidas: number;
    partidasAdicionales: number;
    presupuestoEjecutar: number;
    ajustePresupuesto: number;
    presupuestoFinal: number;
    totalAnticiposFacturados: number;
    totalAnticiposCobrados: number;
    totalEstimacionesFacturadas: number;
    totalEstimacionesCobradas: number;
    totalFacturado: number;
    totalCobrado: number;
    totalDeducciones: number;
    porFacturar: number;
    porCobrar: number;
    estimacionesGeneradas: number;
    estimacionesIngresadas: number;
    facturadasPorCobrar: number;
    tieneComparativos: boolean;
    montoComparativo: number;
    montoComparativoUltimo: number;
    tieneComparativoCualquiera: boolean;
    tipoContrato: string | null;
};

export function calcularResumen(
    partidas: CobPartida[],
    estimaciones: CobEstimacion[],
    anticipos: CobAnticipo[],
    comparativos: CobComparativo[],
    deducciones: CobDeduccion[],
    tipoContrato: string | null = null,
): ResumenFinanciero {
    const presupuestoPartidas = partidas.reduce((sum, p) => sum + Number(p.monto), 0);

    // Los adicionales ahora son sub-obras (obras con sus propias partidas), por
    // lo que ya no se separan dentro de una obra.
    const partidasAdicionales = 0;

    const lastComparativo = comparativos
        .filter((c) => c.estado === 'implementado' || c.estado === 'aprobado')
        .sort((a, b) => (a.id > b.id ? -1 : 1))[0];

    const lastComparativoCualquiera = comparativos
        .sort((a, b) => (a.id > b.id ? -1 : 1))[0];

    const montoComparativo = lastComparativo ? Number(lastComparativo.monto_impacto) : 0;
    const montoComparativoUltimo = lastComparativoCualquiera ? Number(lastComparativoCualquiera.monto_impacto) : 0;
    const basePartidas = presupuestoPartidas + partidasAdicionales;

    // Si hay comparativo (cualquier estado) se usa como presupuesto base, EXCEPTO
    // en obras a precio alzado: ahí la comparativa de ingeniería es solo de
    // referencia y no reemplaza el monto a ejecutar (regla de negocio de cobranza;
    // en alzado el monto está pactado fijo). En precios unitarios/otros sí aplica.
    const esAlzado = tipoContrato === 'precio_alzado';
    const presupuestoEjecutar = lastComparativoCualquiera && !esAlzado
        ? montoComparativoUltimo
        : basePartidas;

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

    const ajustePresupuesto = presupuestoEjecutar - presupuestoPartidas;
    const presupuestoFinal = presupuestoEjecutar - totalDeducciones;
    const porFacturar = presupuestoFinal - totalFacturado;
    const porCobrar = presupuestoFinal - totalCobrado;

    const estimacionesGeneradas = estimaciones
        .filter((e) => e.estado === 'generada')
        .reduce((sum, e) => sum + Number(e.monto_estimado), 0);

    const estimacionesIngresadas = estimaciones
        .filter((e) => e.estado === 'ingresada')
        .reduce((sum, e) => sum + Number(e.monto_estimado), 0);

    const facturadasPorCobrar = totalFacturado - totalCobrado;

    const tieneComparativos = !!lastComparativo;
    const tieneComparativoCualquiera = !!lastComparativoCualquiera;

    return {
        presupuestoPartidas,
        partidasAdicionales,
        presupuestoEjecutar,
        ajustePresupuesto,
        presupuestoFinal,
        totalAnticiposFacturados,
        totalAnticiposCobrados,
        totalEstimacionesFacturadas,
        totalEstimacionesCobradas,
        totalFacturado,
        totalCobrado,
        totalDeducciones,
        porFacturar,
        porCobrar,
        estimacionesGeneradas,
        estimacionesIngresadas,
        facturadasPorCobrar,
        tieneComparativos,
        montoComparativo,
        montoComparativoUltimo,
        tieneComparativoCualquiera,
        tipoContrato,
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
        obra.tipo_contrato ?? null,
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

/**
 * Facturación a nivel proyecto: presupuesto, comparativos, deducciones,
 * anticipos y estimaciones se suman POR OBRA (cada obra/adicional con lo suyo).
 * El proyecto solo agrega; los datos financieros viven en la obra.
 */
export function calcularResumenProyecto(proyecto: Proyecto): ResumenFinanciero {
    const obras = proyecto.obras ?? [];
    const tipoContrato = (obras.find((o) => o.tipo !== 'adicional') ?? obras[0])?.tipo_contrato ?? null;

    const porObra = obras.map((o) =>
        calcularResumen(o.partidas ?? [], [], o.anticipos ?? [], o.comparativos ?? [], o.deducciones ?? [], o.tipo_contrato ?? null),
    );
    const sum = (f: (r: ResumenFinanciero) => number) => porObra.reduce((s, r) => s + f(r), 0);

    const presupuestoPartidas = sum((r) => r.presupuestoPartidas);
    const partidasAdicionales = sum((r) => r.partidasAdicionales);
    const presupuestoEjecutar = sum((r) => r.presupuestoEjecutar);
    const totalDeducciones = sum((r) => r.totalDeducciones);
    const totalAnticiposFacturados = sum((r) => r.totalAnticiposFacturados);
    const totalAnticiposCobrados = sum((r) => r.totalAnticiposCobrados);

    // Estimaciones: sumadas de todas las obras del proyecto.
    const est = calcularResumen([], obras.flatMap((o) => o.estimaciones ?? []), [], [], [], tipoContrato);

    const totalFacturado = totalAnticiposFacturados + est.totalEstimacionesFacturadas;
    const totalCobrado = totalAnticiposCobrados + est.totalEstimacionesCobradas;
    const presupuestoFinal = presupuestoEjecutar - totalDeducciones;
    const ajustePresupuesto = presupuestoEjecutar - presupuestoPartidas;

    return {
        presupuestoPartidas,
        partidasAdicionales,
        presupuestoEjecutar,
        ajustePresupuesto,
        presupuestoFinal,
        totalAnticiposFacturados,
        totalAnticiposCobrados,
        totalEstimacionesFacturadas: est.totalEstimacionesFacturadas,
        totalEstimacionesCobradas: est.totalEstimacionesCobradas,
        totalFacturado,
        totalCobrado,
        totalDeducciones,
        porFacturar: presupuestoFinal - totalFacturado,
        porCobrar: presupuestoFinal - totalCobrado,
        estimacionesGeneradas: est.estimacionesGeneradas,
        estimacionesIngresadas: est.estimacionesIngresadas,
        facturadasPorCobrar: totalFacturado - totalCobrado,
        tieneComparativos: porObra.some((r) => r.tieneComparativos),
        montoComparativo: sum((r) => r.montoComparativo),
        montoComparativoUltimo: sum((r) => r.montoComparativoUltimo),
        tieneComparativoCualquiera: porObra.some((r) => r.tieneComparativoCualquiera),
        tipoContrato,
    };
}
