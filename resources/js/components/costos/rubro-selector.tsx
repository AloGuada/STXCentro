import type { ObraRubroOption } from '@/types/models';

type Props = {
    value: number | '';
    options: ObraRubroOption[];
    onChange: (value: number | '') => void;
};

/**
 * Selector de obra-rubro. Las opciones se pintan en rojo cuando el rubro
 * está sobregirado (disponible < 0) o no tiene presupuesto asignado.
 */
export function RubroSelector({ value, options, onChange }: Props) {
    const isAlerta = (r: ObraRubroOption) => r.sobregiro || r.presupuestado <= 0;
    const sufijo = (r: ObraRubroOption) => {
        if (r.sobregiro) return ' · ⚠ sobregiro';
        if (r.presupuestado <= 0) return ' · ⚠ sin presupuesto';
        return '';
    };

    return (
        <select
            className="select select-bordered select-sm w-full"
            value={value}
            onChange={(e) => onChange(e.target.value ? Number(e.target.value) : '')}
        >
            <option value="">Selecciona rubro...</option>
            {options.map((r) => (
                <option
                    key={r.id}
                    value={r.id}
                    style={isAlerta(r) ? { color: '#dc2626' } : undefined}
                >
                    {r.label}
                    {sufijo(r)}
                </option>
            ))}
        </select>
    );
}
