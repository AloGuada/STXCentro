import { SearchSelect } from '@/components/ui/search-select';
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
 * Selector buscable de obra-rubro. Las opciones se pintan en rojo cuando el
 * rubro está sobregirado (disponible < 0) o no tiene presupuesto asignado.
 */
export function RubroSelector({ value, options, onChange, rubroOnly = false, disabled = false }: Props) {
    const isAlerta = (r: ObraRubroOption) => r.sobregiro || r.presupuestado <= 0;
    const sufijo = (r: ObraRubroOption) => {
        const partes: string[] = [];
        if (r.cerrado) partes.push('⚠ cerrado');
        if (r.sobregiro) partes.push('⚠ sobregiro');
        else if (r.presupuestado <= 0) partes.push('⚠ sin presupuesto');
        return partes.length ? ' · ' + partes.join(' · ') : '';
    };

    return (
        <SearchSelect
            value={value === '' ? '' : String(value)}
            onValueChange={(v) => onChange(v ? Number(v) : '')}
            disabled={disabled}
            placeholder="Selecciona centro de costos..."
            options={options.map((r) => ({
                value: String(r.id),
                label: `${rubroOnly ? r.rubro_label : r.label}${sufijo(r)}`,
                danger: isAlerta(r),
            }))}
        />
    );
}
