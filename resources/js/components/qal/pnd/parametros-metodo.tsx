import { Button } from '@/components/ui/button';
import { PlusIcon, TrashIcon } from 'lucide-react';

export type FilaParametro = { clave: string; valor: string };

type Props = {
    filas: FilaParametro[];
    onChange: (filas: FilaParametro[]) => void;
    /** Lo que suele traer el informe de este método. Es ayuda, no una lista cerrada. */
    sugerencias: string[];
};

/**
 * Con qué se corrió la prueba: frecuencia, palpador, penetrante, película…
 *
 * Van como clave/valor porque el juego de parámetros lo decide el método —y a
 * veces el laboratorio—, no nosotros. Con columnas fijas, cada método nuevo
 * sería una alteración de tabla y las columnas de los otros cuatro irían
 * siempre en nulo.
 *
 * Las sugerencias cambian al cambiar el método; lo ya tecleado no se borra,
 * porque cambiar el método de un informe capturado es casi siempre corregir un
 * dedazo y no volver a empezar.
 */
export function ParametrosMetodo({ filas, onChange, sugerencias }: Props) {
    const usadas = filas.map((fila) => fila.clave.trim().toUpperCase());
    const pendientes = sugerencias.filter((clave) => !usadas.includes(clave.toUpperCase()));

    const cambiar = (indice: number, cambios: Partial<FilaParametro>) => {
        onChange(filas.map((fila, i) => (i === indice ? { ...fila, ...cambios } : fila)));
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="font-semibold">Parámetros de la prueba</h2>
                    <p className="text-base-content/60 text-sm">Los que reporte el laboratorio. Un renglón sin valor se descarta.</p>
                </div>
                <Button type="button" variant="outline" size="sm" onClick={() => onChange([...filas, { clave: '', valor: '' }])}>
                    <PlusIcon className="size-4" /> Agregar
                </Button>
            </div>

            {pendientes.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-base-content/50 text-xs">Habituales de este método:</span>
                    {pendientes.map((clave) => (
                        <button
                            key={clave}
                            type="button"
                            className="badge badge-sm badge-outline hover:badge-primary"
                            onClick={() => onChange([...filas, { clave, valor: '' }])}
                        >
                            + {clave}
                        </button>
                    ))}
                </div>
            )}

            <div className="space-y-2">
                {filas.map((fila, indice) => (
                    <div key={indice} className="flex items-center gap-2">
                        <input
                            className="input input-sm input-bordered w-56"
                            placeholder="Clave"
                            value={fila.clave}
                            onChange={(e) => cambiar(indice, { clave: e.target.value })}
                        />
                        <input
                            className="input input-sm input-bordered flex-1"
                            placeholder="Valor"
                            value={fila.valor}
                            onChange={(e) => cambiar(indice, { valor: e.target.value })}
                        />
                        <button
                            type="button"
                            className="btn btn-ghost btn-xs text-error"
                            onClick={() => onChange(filas.filter((_, i) => i !== indice))}
                            title="Quitar el parámetro"
                        >
                            <TrashIcon className="size-3.5" />
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}
