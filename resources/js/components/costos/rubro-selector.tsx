import type { ObraRubroOption } from '@/types/models';

const fmtMoney = (n: number): string =>
    `$${Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2 })}`;

type Props = {
    value: number | '';
    options: ObraRubroOption[];
    onChange: (value: number | '') => void;
};

/**
 * Selector de obra-rubro con info presupuestal: muestra disponible en cada
 * opción y un panel debajo con presupuesto/acumulado/disponible. Si la obra
 * está en sobregiro pinta en rojo el select y el panel.
 */
export function RubroSelector({ value, options, onChange }: Props) {
    const selected = typeof value === 'number' ? options.find((o) => o.id === value) : undefined;

    return (
        <div>
            <select
                className={`select select-bordered select-sm w-full ${selected?.sobregiro ? 'border-error text-error' : ''}`}
                value={value}
                onChange={(e) => onChange(e.target.value ? Number(e.target.value) : '')}
            >
                <option value="">Selecciona rubro...</option>
                {options.map((r) => (
                    <option key={r.id} value={r.id}>
                        {r.label} — {r.sobregiro ? `SOBREGIRO ${fmtMoney(r.disponible)}` : `disp ${fmtMoney(r.disponible)}`}
                    </option>
                ))}
            </select>
            {selected && (
                <div className={`mt-1 text-[10px] ${selected.sobregiro ? 'text-error font-medium' : 'text-base-content/60'}`}>
                    Presup: {fmtMoney(selected.presupuestado)} · Acum: {fmtMoney(selected.acumulado)} · Disp: <strong>{fmtMoney(selected.disponible)}</strong>
                    {selected.sobregiro && ' · ⚠ sobregiro'}
                </div>
            )}
        </div>
    );
}
