import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import type { ProdProceso } from '@/types/models';
import { PlusIcon, Trash2Icon } from 'lucide-react';

/** Un renglón del editor. `id` sólo viene en los que ya existen en la base. */
export type SubprocesoForm = {
    id?: number;
    proceso_id: string;
    nombre: string;
    precio: string;
    activo: boolean;
};

type Props = {
    /** Los procesos que paga la obra: son los únicos a los que se les puede colgar un paso. */
    procesos: ProdProceso[];
    valores: SubprocesoForm[];
    onChange: (valores: SubprocesoForm[]) => void;
    errors?: Record<string, string>;
};

/**
 * Los pasos con precio fijo por pieza de un grupo que paga por subproceso.
 *
 * El precio es por pieza, no por kilo: aquí el peso no explica lo que cuesta el
 * trabajo. Un paso desactivado sigue listado porque tiene producción capturada
 * que no se puede quedar sin precio.
 */
export function SubprocesosPorProceso({ procesos, valores, onChange, errors = {} }: Props) {
    if (procesos.length === 0) {
        return (
            <div className="alert alert-warning text-sm">
                La obra no tiene procesos configurados, así que no hay pasos que capturar. Márcalos primero en el
                catálogo de la obra.
            </div>
        );
    }

    const actualizar = (indice: number, campo: keyof SubprocesoForm, valor: string | boolean) => {
        onChange(valores.map((fila, i) => (i === indice ? { ...fila, [campo]: valor } : fila)));
    };

    const agregar = () => {
        onChange([
            ...valores,
            { proceso_id: String(procesos[0].id), nombre: '', precio: '', activo: true },
        ]);
    };

    const quitar = (indice: number) => {
        onChange(valores.filter((_, i) => i !== indice));
    };

    return (
        <div className="space-y-3">
            <div>
                <span className="label-text font-medium">Subprocesos y su precio por pieza</span>
                <p className="text-base-content/60 text-sm">
                    Cada paso se paga completo por pieza, sin importar el peso. Una pieza puede pagarse en varios pasos
                    del mismo proceso, y cada uno lleva su propio tope: armar al 100% no consume nada de puntear.
                </p>
            </div>

            {valores.length === 0 && (
                <div className="alert alert-warning text-sm">
                    Este grupo paga por subproceso pero no tiene ninguno capturado. Sin pasos, su producción se paga en
                    cero.
                </div>
            )}

            <div className="space-y-2">
                {valores.map((fila, indice) => (
                    <div key={fila.id ?? `nuevo-${indice}`} className="flex flex-wrap items-start gap-2">
                        <div className="min-w-40 flex-1">
                            <Select
                                value={fila.proceso_id}
                                onValueChange={(valor) => actualizar(indice, 'proceso_id', valor)}
                                error={!!errors[`subprocesos.${indice}.proceso_id`]}
                            >
                                {procesos.map((proceso) => (
                                    <SelectItem key={proceso.id} value={String(proceso.id)}>
                                        {proceso.nombre}
                                    </SelectItem>
                                ))}
                            </Select>
                        </div>

                        <div className="min-w-40 flex-[2]">
                            <Input
                                value={fila.nombre}
                                onChange={(e) => actualizar(indice, 'nombre', e.target.value)}
                                error={!!errors[`subprocesos.${indice}.nombre`]}
                                placeholder="Armado, punteado, soldado final..."
                            />
                        </div>

                        <div className="w-32">
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                value={fila.precio}
                                onChange={(e) => actualizar(indice, 'precio', e.target.value)}
                                error={!!errors[`subprocesos.${indice}.precio`]}
                                placeholder="$/pza"
                            />
                        </div>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => quitar(indice)}
                            aria-label={`Quitar ${fila.nombre || 'subproceso'}`}
                        >
                            <Trash2Icon className="size-4" />
                        </Button>
                    </div>
                ))}
            </div>

            {Object.entries(errors)
                .filter(([campo]) => campo.startsWith('subprocesos.'))
                .map(([campo, mensaje]) => (
                    <p key={campo} className="text-error text-sm">
                        {mensaje}
                    </p>
                ))}

            <Button type="button" variant="outline" size="sm" onClick={agregar}>
                <PlusIcon className="size-4" />
                Agregar subproceso
            </Button>

            <p className="text-base-content/60 text-xs">
                Quitar un paso que ya tiene producción capturada no lo borra: se desactiva, para que el renglón pagado
                no se quede sin precio.
            </p>
        </div>
    );
}
