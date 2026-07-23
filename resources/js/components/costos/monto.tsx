/**
 * Formato de dinero consciente de la moneda del documento (costos).
 *
 * Reemplaza los `formatMoney` locales duplicados por pantalla. MXN se muestra
 * limpio (`$1,000.00`); las divisas agregan el código (`$1,000.00 USD`). El
 * componente `<Monto>` puede además mostrar el equivalente en MXN usando el
 * tipo de cambio guardado del documento.
 */

/**
 * Moneda de un total agregado: una sola divisa si todas coinciden; MXN si se
 * mezclan (o no hay ninguna). Espeja `App\Support\Moneda::agregada`.
 */
export function monedaAgregada(monedas: Array<string | null | undefined>): string {
    const distintas = [...new Set(monedas.filter(Boolean).map((m) => (m as string).toLowerCase()))];

    return distintas.length === 1 ? distintas[0] : 'mxn';
}

export function formatMoney(monto: number | string, moneda: string = 'mxn'): string {
    const n = Number(monto) || 0;
    const cod = (moneda || 'mxn').toLowerCase();
    const base = `$${n.toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    return cod === 'mxn' ? base : `${base} ${cod.toUpperCase()}`;
}

interface MontoProps {
    valor: number | string | null | undefined;
    moneda?: string | null;
    /** Tipo de cambio guardado del documento (MXN por unidad de la divisa). */
    tc?: number | string | null;
    /** Muestra el equivalente en MXN junto al monto en divisa. */
    conMxn?: boolean;
    className?: string;
}

export function Monto({ valor, moneda = 'mxn', tc, conMxn = false, className }: MontoProps) {
    const cod = (moneda || 'mxn').toLowerCase();
    const principal = formatMoney(valor ?? 0, cod);

    if (!conMxn || cod === 'mxn' || !tc) {
        return <span className={className}>{principal}</span>;
    }

    const mxn = (Number(valor) || 0) * (Number(tc) || 0);

    return (
        <span className={className}>
            {principal}{' '}
            <span className="text-xs text-base-content/50">
                (≈ {formatMoney(mxn, 'mxn')})
            </span>
        </span>
    );
}
