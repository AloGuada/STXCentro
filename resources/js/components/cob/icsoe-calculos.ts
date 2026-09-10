import type { CobIcsoeMes, CobIcsoeSeguimiento } from '@/types/models';

/**
 * Mismas fórmulas que `app/Services/Cob/IcsoeCalculadora.php`, para que la
 * tabla de captura muestre los totales antes de guardar. El servidor sigue
 * siendo la verdad: esto es solo feedback inmediato.
 *
 * Si cambia una fórmula en PHP, cambia aquí también.
 */

/** Cuotas obrero-patronales distintas del riesgo de trabajo, en porcentaje. */
export const CARGAS_SOCIALES_BASE = 26;

export type TotalesIcsoe = {
    moEstimadaTotal: number;
    moRealTotal: number;
    diferencia: number;
    montoRiesgo: number;
};

export function moRealDelMes(mes: Pick<CobIcsoeMes, 'dias_cotizados' | 'sbc_aplicado'>): number {
    return Number(mes.dias_cotizados) * Number(mes.sbc_aplicado);
}

export function calcularTotales(seguimiento: CobIcsoeSeguimiento, meses: CobIcsoeMes[]): TotalesIcsoe {
    const moEstimadaTotal = Number(seguimiento.mo_estimada_total);
    const moRealTotal = meses.reduce((suma, mes) => suma + moRealDelMes(mes), 0);

    // Contra el total exacto, no contra la suma de las metas mensuales: por
    // redondeo difieren en centavos.
    const diferencia = moEstimadaTotal - moRealTotal;
    const factor = (CARGAS_SOCIALES_BASE + Number(seguimiento.prima_riesgo)) / 100;

    return {
        moEstimadaTotal,
        moRealTotal,
        diferencia,
        montoRiesgo: diferencia > 0 ? diferencia * factor : 0,
    };
}

const NOMBRES_MES = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
];

export function nombreDelPeriodo(mes: Pick<CobIcsoeMes, 'anio' | 'mes'>): string {
    return `${NOMBRES_MES[mes.mes - 1] ?? mes.mes} ${mes.anio}`;
}
