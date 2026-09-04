import { ChevronDownIcon, Trash2Icon } from 'lucide-react';
import { Fragment, useState } from 'react';
import type { RegistroPreview } from '@/components/prod/grupo-destajo-card';
import { FormattedDate } from '@/components/ui/formatted-date';
import { etiquetaDePieza, etiquetaDeUnidad } from '@/lib/prod/piezas';

/**
 * La producción de un grupo, un renglón por marca en vez de uno por QR.
 *
 * Una marca de 200 piezas llenaba la pantalla del destajo con 200 renglones
 * idénticos salvo el QR. Se juntan las que comparten el mismo pago —misma
 * marca, mismo proceso, mismo paso, misma fecha y mismo porcentaje— y los QR
 * quedan detrás de un desplegable, que es donde se borran uno por uno.
 */
type Props = {
    registros: RegistroPreview[];
    onEliminar: (id: number) => void;
};

/** Lo que hace de un renglón un pago distinto, aunque la marca sea la misma. */
const claveDelRenglon = (r: RegistroPreview): string =>
    [
        r.pieza?.marca?.id ?? 'sin-marca',
        r.proceso_id,
        r.subproceso_id ?? '',
        String(r.fecha).slice(0, 10),
        Number(r.porcentaje ?? 100),
    ].join('|');

type Renglon = {
    clave: string;
    registros: RegistroPreview[];
};

function agrupar(registros: RegistroPreview[]): Renglon[] {
    const porClave = new Map<string, RegistroPreview[]>();

    for (const r of registros) {
        const clave = claveDelRenglon(r);
        porClave.set(clave, [...(porClave.get(clave) ?? []), r]);
    }

    return [...porClave.entries()].map(([clave, registros]) => ({ clave, registros }));
}

export function RegistrosPorMarca({ registros, onEliminar }: Props) {
    // Los desplegables abiertos, por clave de renglón. Sólo es estado de vista:
    // lo que se borra vuelve del servidor, no de aquí.
    const [abiertos, setAbiertos] = useState<string[]>([]);

    const renglones = agrupar(registros);

    const alternar = (clave: string) =>
        setAbiertos((previos) =>
            previos.includes(clave) ? previos.filter((c) => c !== clave) : [...previos, clave],
        );

    return (
        <div className="overflow-x-auto">
            <table className="table table-sm">
                <thead>
                    <tr>
                        <th>Pieza</th>
                        <th className="text-right">Piezas</th>
                        <th>Proceso</th>
                        <th>Fecha</th>
                        <th className="text-right">%</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {renglones.length === 0 ? (
                        <tr>
                            <td colSpan={6} className="text-base-content/50 py-4 text-center">
                                Sin producción capturada
                            </td>
                        </tr>
                    ) : (
                        renglones.map(({ clave, registros: delRenglon }) => {
                            const primero = delRenglon[0];
                            const abierto = abiertos.includes(clave);
                            const porcentaje = Number(primero.porcentaje ?? 100);

                            return (
                                <Fragment key={clave}>
                                    <tr className="hover">
                                        <td>
                                            <span className="font-medium">
                                                {etiquetaDePieza(
                                                    primero.pieza?.marca?.marca,
                                                    primero.pieza?.marca?.lote,
                                                )}
                                            </span>{' '}
                                            <span className="text-base-content/60">
                                                {primero.pieza?.marca?.descripcion}
                                            </span>
                                        </td>
                                        <td className="text-right font-mono">{delRenglon.length}</td>
                                        <td>
                                            <span className="badge badge-sm badge-ghost">
                                                {primero.proceso?.nombre ?? '—'}
                                            </span>
                                            {primero.subproceso && (
                                                <span className="badge badge-sm badge-ghost ml-1">
                                                    {primero.subproceso.nombre}
                                                </span>
                                            )}
                                        </td>
                                        <td className="font-mono text-xs">
                                            <FormattedDate value={primero.fecha} />
                                        </td>
                                        <td className="text-right font-mono">
                                            {porcentaje < 100 ? (
                                                <span className="badge badge-sm badge-warning">{porcentaje}%</span>
                                            ) : (
                                                '100%'
                                            )}
                                        </td>
                                        <td className="text-right">
                                            <button
                                                className="btn btn-ghost btn-xs"
                                                onClick={() => alternar(clave)}
                                                aria-expanded={abierto}
                                            >
                                                {abierto ? 'Ocultar' : 'Ver QR'}
                                                <ChevronDownIcon
                                                    className={`size-3.5 transition-transform ${abierto ? 'rotate-180' : ''}`}
                                                />
                                            </button>
                                        </td>
                                    </tr>
                                    {abierto && (
                                        <tr>
                                            <td colSpan={6} className="bg-base-200/50 p-0">
                                                <ul className="divide-base-300 divide-y">
                                                    {delRenglon.map((r) => (
                                                        <li
                                                            key={r.id}
                                                            className="flex items-center justify-between gap-2 px-4 py-1"
                                                        >
                                                            <span className="font-mono text-xs">
                                                                {etiquetaDeUnidad(r.pieza ?? {})}
                                                            </span>
                                                            <button
                                                                className="btn btn-ghost btn-xs text-error"
                                                                onClick={() => onEliminar(r.id)}
                                                                aria-label="Eliminar registro"
                                                            >
                                                                <Trash2Icon className="size-3.5" />
                                                            </button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            );
                        })
                    )}
                </tbody>
            </table>
        </div>
    );
}
