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
    deducciones: CobDeduccion[],
    tipoContrato: string | null = null,
): ResumenFinanciero {
    const presupuestoPartidas = partidas.reduce((sum, p) => sum + Number(p.monto), 0);

    // Los adicionales ahora son sub-obras (obras con sus propias partidas), por
    // lo que ya no se separan dentro de una obra.
    const partidasAdicionales = 0;

    // Los comparativos de ingeniería viven a nivel PROYECTO (ver
    // calcularResumenProyecto); una obra suelta ya no ajusta su presupuesto por
    // comparativa, así que su presupuesto a ejecutar es la suma de sus partidas.
    const presupuestoEjecutar = presupuestoPartidas + partidasAdicionales;

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

    // El estado 'generada' se eliminó del flujo; este bucket queda en 0 (la columna
    // "Gen. por cobrar" del index queda obsoleta — pendiente decidir si se quita).
    const estimacionesGeneradas = 0;

    const estimacionesIngresadas = estimaciones
        .filter((e) => e.estado === 'ingresada')
        .reduce((sum, e) => sum + Number(e.monto_estimado), 0);

    const facturadasPorCobrar = totalFacturado - totalCobrado;

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
        tieneComparativos: false,
        montoComparativo: 0,
        montoComparativoUltimo: 0,
        tieneComparativoCualquiera: false,
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
    const base = obras.find((o) => o.tipo !== 'adicional') ?? obras[0];
    const tipoContrato = base?.tipo_contrato ?? null;

    const porObra = obras.map((o) =>
        calcularResumen(o.partidas ?? [], [], o.anticipos ?? [], o.deducciones ?? [], o.tipo_contrato ?? null),
    );
    const sum = (f: (r: ResumenFinanciero) => number) => porObra.reduce((s, r) => s + f(r), 0);

    const presupuestoPartidas = sum((r) => r.presupuestoPartidas);
    const partidasAdicionales = sum((r) => r.partidasAdicionales);
    const totalDeducciones = sum((r) => r.totalDeducciones);
    const totalAnticiposFacturados = sum((r) => r.totalAnticiposFacturados);
    const totalAnticiposCobrados = sum((r) => r.totalAnticiposCobrados);

    // Comparativos de ingeniería, ligados a una obra (último por obra gana).
    const comparativos = proyecto.comparativos ?? [];
    const ultimoPorObra = new Map<number, CobComparativo>();
    for (const c of [...comparativos].sort((a, b) => b.id - a.id)) {
        if (c.obra_id != null && !ultimoPorObra.has(c.obra_id)) {
            ultimoPorObra.set(c.obra_id, c);
        }
    }

    // OJO: esta regla vive DUPLICADA en PHP, en
    // `app/Services/Cob/ValorAEjecutarService.php` (la consume el módulo ICSOE).
    // Si cambias una, cambia la otra y corre `tests/Feature/Cob/ValorAEjecutarTest.php`.
    //
    // Presupuesto a ejecutar = suma del valor de cada obra. Regla de negocio:
    //  - Si el proyecto no tiene ningún comparativo → todo por partidas (normal).
    //  - Obra a precio UNITARIO → su comparativo (último) si tiene; si no, 0
    //    (en unitario el precio final lo define la comparativa de ingeniería).
    //  - Obra a precio ALZADO (u otro) → siempre sus partidas (el comparativo es
    //    solo referencia; el monto está pactado fijo).
    const hayComparativos = ultimoPorObra.size > 0;
    const partidasDe = (o: Obra) => (o.partidas ?? []).reduce((s, p) => s + Number(p.monto), 0);
    const valorObra = (o: Obra): number => {
        if (!hayComparativos) {
            return partidasDe(o);
        }
        if (o.tipo_contrato === 'precio_unitario') {
            const c = ultimoPorObra.get(o.id);
            return c ? Number(c.monto_impacto) : 0;
        }
        return partidasDe(o);
    };
    const presupuestoEjecutar = obras.reduce((s, o) => s + valorObra(o), 0);

    const comparativosUltimos = [...ultimoPorObra.values()];
    const montoComparativoUltimo = comparativosUltimos.reduce((s, c) => s + Number(c.monto_impacto), 0);
    const montoComparativo = comparativosUltimos
        .filter((c) => c.estado === 'implementado' || c.estado === 'aprobado')
        .reduce((s, c) => s + Number(c.monto_impacto), 0);

    // Estimaciones del proyecto: las de cada obra (nivel obra/partida, que traen
    // obra_id) más las globales (nivel proyecto, sin obra) que cuelgan directo del
    // proyecto. Las globales viven en `proyecto.estimaciones`, no en ninguna obra,
    // por lo que hay que sumarlas aparte para que cuenten en el rollup.
    const estimacionesObra = obras.flatMap((o) => o.estimaciones ?? []);
    const estimacionesGlobales = (proyecto.estimaciones ?? []).filter((e) => e.obra_id == null);
    const est = calcularResumen([], [...estimacionesObra, ...estimacionesGlobales], [], [], tipoContrato);

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
        tieneComparativos: montoComparativo > 0,
        montoComparativo,
        montoComparativoUltimo,
        tieneComparativoCualquiera: comparativosUltimos.length > 0,
        tipoContrato,
    };
}
