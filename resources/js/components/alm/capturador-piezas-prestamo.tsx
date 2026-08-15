import { Input } from '@/components/ui/input';
import { Select, SelectItem } from '@/components/ui/select';
import type { AlmActivoDemo, AlmPrestamoPiezaBorrador } from '@/types/models';
import { PlusIcon, Trash2Icon, TriangleAlertIcon } from 'lucide-react';

type Props = {
    piezas: AlmPrestamoPiezaBorrador[];
    onChange: (piezas: AlmPrestamoPiezaBorrador[]) => void;
    /** Las que están en el almacén elegido y disponibles. */
    prestables: AlmActivoDemo[];
    /** Sin almacén no hay de dónde escoger; la tabla se queda apagada. */
    almacenElegido: boolean;
};

export const PIEZA_VACIA: AlmPrestamoPiezaBorrador = {
    activo_id: '',
    condicion_salida: '',
    observaciones: '',
};

/** Renglones con pieza elegida pero sin decir en qué condición sale. */
export function piezasSinCondicion(piezas: AlmPrestamoPiezaBorrador[]): AlmPrestamoPiezaBorrador[] {
    return piezas.filter((pieza) => pieza.activo_id !== '' && pieza.condicion_salida.trim() === '');
}

/**
 * Tabla editable de piezas a prestar, con la misma forma que el capturador de
 * partidas. Cambia lo que se captura: aquí no hay cantidad ni costo —se presta
 * una pieza con número de serie— y cada renglón se va con su propia condición
 * de salida, que es contra lo que se revisa cuando vuelve.
 */
export function CapturadorPiezasPrestamo({ piezas, onChange, prestables, almacenElegido }: Props) {
    const editar = (indice: number, cambio: Partial<AlmPrestamoPiezaBorrador>) =>
        onChange(piezas.map((p, i) => (i === indice ? { ...p, ...cambio } : p)));

    const agregar = () => onChange([...piezas, { ...PIEZA_VACIA }]);

    const quitar = (indice: number) => onChange(piezas.filter((_, i) => i !== indice));

    const activoDe = (id: string) => prestables.find((a) => String(a.id) === id);

    /** La misma pieza no se puede prestar dos veces en el mismo documento. */
    const yaElegidas = new Set(piezas.map((p) => p.activo_id).filter((id) => id !== ''));

    const disponiblesPara = (indice: number) =>
        prestables.filter((a) => !yaElegidas.has(String(a.id)) || String(a.id) === piezas[indice].activo_id);

    const sinAsignar = prestables.length - yaElegidas.size;

    /** La condición arranca con la que trae registrada la pieza. */
    const elegirActivo = (indice: number, valor: string) =>
        editar(indice, { activo_id: valor, condicion_salida: activoDe(valor)?.condicion ?? '' });

    return (
        <div className="space-y-3">
            <div className="rounded-box border-base-300 overflow-x-auto border">
                <table className="table table-sm">
                    <thead className="bg-base-200">
                        <tr>
                            <th className="w-[35%]">Pieza</th>
                            <th>Artículo</th>
                            <th>Ubicación</th>
                            <th className="w-[20%]">Condición de salida</th>
                            <th>Observaciones</th>
                            <th className="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {piezas.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="text-base-content/50 py-6 text-center">
                                    Sin piezas. Agrega la primera para registrar el préstamo.
                                </td>
                            </tr>
                        ) : (
                            piezas.map((pieza, i) => {
                                const activo = activoDe(pieza.activo_id);
                                // Sin condición de salida no hay contra qué
                                // reclamar un daño: el renglón queda detenido.
                                const faltaCondicion = activo !== undefined && pieza.condicion_salida.trim() === '';

                                return (
                                    <tr key={i} className={faltaCondicion ? 'bg-warning/10' : 'hover'}>
                                        <td>
                                            <Select
                                                value={pieza.activo_id}
                                                onValueChange={(v) => elegirActivo(i, v)}
                                                placeholder={
                                                    almacenElegido
                                                        ? disponiblesPara(i).length === 0
                                                            ? 'No quedan piezas disponibles'
                                                            : 'Selecciona la pieza'
                                                        : 'Elige primero el almacén'
                                                }
                                                className="select-sm"
                                                disabled={!almacenElegido || disponiblesPara(i).length === 0}
                                            >
                                                {disponiblesPara(i).map((a) => (
                                                    <SelectItem key={a.id} value={String(a.id)}>
                                                        {a.no_serie} — {a.descripcion}
                                                    </SelectItem>
                                                ))}
                                            </Select>
                                        </td>
                                        <td className="text-base-content/60 font-mono text-xs">
                                            {activo?.codigo ?? '—'}
                                        </td>
                                        <td className="text-base-content/60 text-xs">{activo?.ubicacion ?? '—'}</td>
                                        <td>
                                            <Input
                                                className="input-sm"
                                                value={pieza.condicion_salida}
                                                onChange={(e) => editar(i, { condicion_salida: e.target.value })}
                                                error={faltaCondicion}
                                                placeholder="Buena, disco gastado..."
                                                disabled={!activo}
                                            />
                                            {faltaCondicion && (
                                                <p className="text-warning mt-1 text-xs">
                                                    <TriangleAlertIcon className="mr-1 inline size-3" />
                                                    Sin esto no hay contra qué comparar el retorno.
                                                </p>
                                            )}
                                        </td>
                                        <td>
                                            <Input
                                                className="input-sm"
                                                value={pieza.observaciones}
                                                onChange={(e) => editar(i, { observaciones: e.target.value })}
                                                placeholder="Con extensión, en maletín..."
                                            />
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                className="btn btn-ghost btn-xs text-error"
                                                onClick={() => quitar(i)}
                                                aria-label="Quitar pieza"
                                            >
                                                <Trash2Icon className="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    className="btn btn-sm btn-outline"
                    onClick={agregar}
                    disabled={!almacenElegido || sinAsignar <= 0}
                >
                    <PlusIcon className="size-4" />
                    Agregar pieza
                </button>
                {almacenElegido && (
                    <span className="text-base-content/60 text-sm">
                        {sinAsignar > 0
                            ? `Quedan ${sinAsignar} ${sinAsignar === 1 ? 'pieza disponible' : 'piezas disponibles'} en el almacén.`
                            : 'Ya no quedan piezas disponibles en el almacén.'}
                    </span>
                )}
            </div>
        </div>
    );
}
