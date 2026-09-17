import { Button } from '@/components/ui/button';
import type { QalResultadoPnd, QalSoldador } from '@/types/models';
import { CopyIcon, PlusIcon, TrashIcon } from 'lucide-react';
import { descomponerReferencia } from './reglas';

/** Un renglón de la rejilla tal como se teclea (todo texto hasta que se envía). */
export type FilaJunta = {
    marca: string;
    /** La referencia como viene en el informe: `J-18-1-2`. */
    junta: string;
    /** Vacío = el que se deduzca de la referencia. Se teclea para corregirlo. */
    spot: string;
    modulo: string;
    resultado: QalResultadoPnd;
    discontinuidad: string;
    longitud_discontinuidad: string;
    espesor: string;
    soldador_id: string;
};

export const FILA_VACIA: FilaJunta = {
    marca: '',
    junta: '',
    spot: '',
    modulo: '',
    resultado: 'aceptada',
    discontinuidad: '',
    longitud_discontinuidad: '',
    espesor: '',
    soldador_id: '',
};

type Props = {
    filas: FilaJunta[];
    onChange: (filas: FilaJunta[]) => void;
    soldadores: QalSoldador[];
    /** Los errores de validación del servidor, tal como llegan: `juntas.3.marca`. */
    errores: Record<string, string>;
};

/**
 * La rejilla del informe: **un renglón es un punto examinado, no una junta**.
 *
 * `J-18-1-2` es el segundo punto de la junta `18-1`, y la pantalla lo separa
 * mientras se teclea para que el capturista vea qué se va a guardar. El spot
 * deducido se puede corregir: es una lectura de la referencia, no una regla.
 *
 * La marca se guarda como la escribió el laboratorio aunque esa pieza todavía
 * no exista dada de alta — el informe llega antes que las piezas, y no poder
 * capturarlo hasta que existan sería no poder capturarlo.
 */
export function RejillaJuntas({ filas, onChange, soldadores, errores }: Props) {
    const cambiar = (indice: number, cambios: Partial<FilaJunta>) => {
        onChange(filas.map((fila, i) => (i === indice ? { ...fila, ...cambios } : fila)));
    };

    const agregar = () => onChange([...filas, { ...FILA_VACIA }]);

    /** Duplicar sirve de verdad: un informe son veinte spots de la misma marca. */
    const duplicar = (indice: number) => {
        const copia = { ...filas[indice], junta: '', spot: '', discontinuidad: '', longitud_discontinuidad: '' };

        onChange([...filas.slice(0, indice + 1), copia, ...filas.slice(indice + 1)]);
    };

    const quitar = (indice: number) => onChange(filas.filter((_, i) => i !== indice));

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="font-semibold">Puntos examinados</h2>
                    <p className="text-base-content/60 text-sm">
                        Un renglón por punto. El porcentaje de rechazo se mide sobre puntos, no sobre juntas.
                    </p>
                </div>
                <Button type="button" variant="outline" size="sm" onClick={agregar}>
                    <PlusIcon className="size-4" /> Agregar punto
                </Button>
            </div>

            {errores.juntas && <p className="text-error text-sm">{errores.juntas}</p>}

            <div className="overflow-x-auto rounded-box border border-base-300">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th className="w-10"></th>
                            <th className="min-w-36">Marca</th>
                            <th className="min-w-32">Referencia</th>
                            <th className="min-w-28">Junta · spot</th>
                            <th className="w-24">Módulo</th>
                            <th className="w-32">Resultado</th>
                            <th className="min-w-36">Discontinuidad</th>
                            <th className="w-24">Long. (mm)</th>
                            <th className="w-24">Espesor</th>
                            <th className="min-w-40">Soldador</th>
                            <th className="w-20"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.length === 0 && (
                            <tr>
                                <td colSpan={11} className="text-base-content/60 py-6 text-center">
                                    El informe todavía no tiene puntos. Sin rejilla es sólo un encabezado.
                                </td>
                            </tr>
                        )}

                        {filas.map((fila, indice) => {
                            const leida = descomponerReferencia(fila.junta);
                            const spot = fila.spot === '' ? leida.spot : Number(fila.spot);

                            return (
                                <tr key={indice} className={fila.resultado === 'rechazada' ? 'bg-error/5' : undefined}>
                                    <td className="text-base-content/40 font-mono text-xs">{indice + 1}</td>

                                    <td>
                                        <input
                                            className={`input input-sm input-bordered w-full font-mono uppercase ${errores[`juntas.${indice}.marca`] ? 'input-error' : ''}`}
                                            value={fila.marca}
                                            placeholder="TP12-3"
                                            onChange={(e) => cambiar(indice, { marca: e.target.value.toUpperCase() })}
                                        />
                                    </td>

                                    <td>
                                        <input
                                            className={`input input-sm input-bordered w-full font-mono ${errores[`juntas.${indice}.junta`] ? 'input-error' : ''}`}
                                            value={fila.junta}
                                            placeholder="J-18-1-2"
                                            onChange={(e) => cambiar(indice, { junta: e.target.value })}
                                        />
                                    </td>

                                    <td className="text-xs">
                                        <span className="font-mono">{leida.junta || '—'}</span>
                                        <span className="text-base-content/50"> · spot </span>
                                        <input
                                            className="input input-xs input-bordered w-12 text-center font-mono"
                                            value={fila.spot === '' ? String(spot) : fila.spot}
                                            onChange={(e) => cambiar(indice, { spot: e.target.value })}
                                            title="Deducido de la referencia; se puede corregir"
                                        />
                                    </td>

                                    <td>
                                        <input
                                            className="input input-sm input-bordered w-full font-mono"
                                            value={fila.modulo}
                                            onChange={(e) => cambiar(indice, { modulo: e.target.value })}
                                        />
                                    </td>

                                    <td>
                                        <select
                                            className="select select-sm select-bordered w-full"
                                            value={fila.resultado}
                                            onChange={(e) =>
                                                cambiar(indice, { resultado: e.target.value as QalResultadoPnd })
                                            }
                                        >
                                            <option value="aceptada">Aceptada</option>
                                            <option value="rechazada">Rechazada</option>
                                        </select>
                                    </td>

                                    <td>
                                        <input
                                            className="input input-sm input-bordered w-full"
                                            value={fila.discontinuidad}
                                            placeholder={fila.resultado === 'rechazada' ? 'Porosidad…' : ''}
                                            onChange={(e) => cambiar(indice, { discontinuidad: e.target.value })}
                                        />
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="input input-sm input-bordered w-full text-right font-mono"
                                            value={fila.longitud_discontinuidad}
                                            onChange={(e) =>
                                                cambiar(indice, { longitud_discontinuidad: e.target.value })
                                            }
                                        />
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="input input-sm input-bordered w-full text-right font-mono"
                                            value={fila.espesor}
                                            onChange={(e) => cambiar(indice, { espesor: e.target.value })}
                                        />
                                    </td>

                                    <td>
                                        <select
                                            className="select select-sm select-bordered w-full"
                                            value={fila.soldador_id}
                                            onChange={(e) => cambiar(indice, { soldador_id: e.target.value })}
                                        >
                                            <option value="">—</option>
                                            {soldadores.map((soldador) => (
                                                <option key={soldador.id} value={String(soldador.id)}>
                                                    {soldador.clave ? `${soldador.clave} · ` : ''}
                                                    {soldador.nombre}
                                                </option>
                                            ))}
                                        </select>
                                    </td>

                                    <td>
                                        <div className="flex justify-end gap-1">
                                            <button
                                                type="button"
                                                className="btn btn-ghost btn-xs"
                                                onClick={() => duplicar(indice)}
                                                title="Duplicar el renglón (otro punto de la misma marca)"
                                            >
                                                <CopyIcon className="size-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-ghost btn-xs text-error"
                                                onClick={() => quitar(indice)}
                                                title="Quitar el renglón"
                                            >
                                                <TrashIcon className="size-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
