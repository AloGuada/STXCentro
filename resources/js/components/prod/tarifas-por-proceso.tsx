import { FormField } from '@/components/form';
import { Input } from '@/components/ui/input';
import type { ProdProceso } from '@/types/models';

type Props = {
    /** Los procesos que paga la obra: son los únicos que necesitan tarifa. */
    procesos: ProdProceso[];
    /** procesoId => precio por kilo, como lo espera el backend. */
    valores: Record<number, string>;
    onChange: (procesoId: number, precioKilo: string) => void;
    errors?: Record<string, string>;
};

/**
 * El grupo de precios cobra una tarifa por proceso: soldar y pintar la misma
 * pieza no valen lo mismo.
 */
export function TarifasPorProceso({ procesos, valores, onChange, errors = {} }: Props) {
    if (procesos.length === 0) {
        return (
            <div className="alert alert-warning text-sm">
                La obra no tiene procesos configurados, así que no hay tarifas que capturar. Márcalos primero en el
                catálogo de la obra.
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div>
                <span className="label-text font-medium">Precio por kilo de cada proceso</span>
                <p className="text-base-content/60 text-sm">
                    Una pieza asignada a este grupo se paga con la tarifa del proceso en que se trabajó. Un proceso sin
                    tarifa se paga en cero, y el destajo avisa antes de cerrar.
                </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                {procesos.map((p) => (
                    <FormField
                        key={p.id}
                        label={p.nombre}
                        htmlFor={`precio-${p.id}`}
                        error={errors[`precios.${p.id}`]}
                    >
                        <Input
                            id={`precio-${p.id}`}
                            type="number"
                            step="0.0001"
                            min="0"
                            value={valores[p.id] ?? ''}
                            onChange={(e) => onChange(p.id, e.target.value)}
                            placeholder="0.0000"
                        />
                    </FormField>
                ))}
            </div>
        </div>
    );
}
