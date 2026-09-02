import type { CostosTipoFiscalPartida, Proveedor } from '@/types/models';

type ProveedorRet = Pick<Proveedor, 'tipo_persona' | 'regimen_fiscal'>;

export type RetencionLinea = { clave: string; concepto: string; tasa: number; monto: number };

export const IVA_RATE = 0.16;

export type LineaFiscal = {
    tipo_fiscal: CostosTipoFiscalPartida;
    subtotal: number;
    /** Partida exenta: no causa IVA ni entra a la base de retenciones. */
    sin_impuestos?: boolean;
};

/** Base gravable: el subtotal sin las partidas marcadas "sin impuestos". */
export function baseImpuestos(lines: LineaFiscal[]): number {
    return lines.reduce((acc, l) => (l.sin_impuestos ? acc : acc + l.subtotal), 0);
}

// Tasas de retención — reflejan config/costos.php (cálculo informativo).
const RET_TASAS = {
    isr_resico: 0.0125,
    isr_fletes: 0.04,
    isr_honorarios: 0.1,
    iva_honorarios: 0.1067,
    iva_renta: 0.1067,
};

/**
 * Espeja App\Services\Costos\RetencionCalculator para el preview de la OC.
 */
export function calcularRetenciones(
    proveedor: ProveedorRet | undefined,
    lines: LineaFiscal[],
): RetencionLinea[] {
    const esPF = proveedor?.tipo_persona === 'fisica';
    const esResico = esPF && proveedor?.regimen_fiscal?.clave === '626';
    const acc = new Map<string, RetencionLinea>();
    const add = (clave: string, concepto: string, tasa: number, base: number) => {
        if (base <= 0 || tasa <= 0) return;
        const prev = acc.get(clave) ?? { clave, concepto, tasa, monto: 0 };
        prev.monto += base * tasa;
        acc.set(clave, prev);
    };
    lines.forEach(({ tipo_fiscal, subtotal, sin_impuestos }) => {
        if (sin_impuestos) return;
        if (esResico) add('isr_resico', 'ISR RESICO', RET_TASAS.isr_resico, subtotal);
        if (tipo_fiscal === 'flete') add('isr_fletes', 'ISR Fletes', RET_TASAS.isr_fletes, subtotal);
        if (tipo_fiscal === 'servicio_profesional' && esPF) {
            if (!esResico) add('isr_honorarios', 'ISR Honorarios', RET_TASAS.isr_honorarios, subtotal);
            add('iva_honorarios', 'IVA Honorarios', RET_TASAS.iva_honorarios, subtotal);
        }
        if (tipo_fiscal === 'renta' && esPF) add('iva_renta', 'IVA Renta', RET_TASAS.iva_renta, subtotal);
    });
    return Array.from(acc.values()).map((r) => ({ ...r, monto: Math.round(r.monto * 100) / 100 }));
}
