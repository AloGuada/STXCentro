import type { ObraRubroOption } from '@/types/models';

type Props = {
    value: number | '';
    options: ObraRubroOption[];
    onChange: (value: number | '') => void;
    /** Muestra solo el rubro (centro de costo) en vez de la etiqueta obra·rubro. */
    rubroOnly?: boolean;
    disabled?: boolean;
};

/**
 * Selector de obra-rubro. Las opciones se pintan en rojo cuando el rubro
 * está sobregirado (disponible < 0) o no tiene presupuesto asignado.
 */
export function RubroSelector({ value, options, onChange, rubroOnly = false, disabled = false }: Props) {
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
            disabled={disabled}
            onChange={(e) => onChange(e.target.value ? Number(e.target.value) : '')}
        >
            <option value="">{rubroOnly ? 'Selecciona rubro...' : 'Selecciona rubro...'}</option>
            {options.map((r) => (
                <option
                    key={r.id}
                    value={r.id}
                    style={isAlerta(r) ? { color: '#dc2626' } : undefined}
                >
                    {rubroOnly ? r.rubro_label : r.label}
                    {sufijo(r)}
                </option>
            ))}
        </select>
    );
}
