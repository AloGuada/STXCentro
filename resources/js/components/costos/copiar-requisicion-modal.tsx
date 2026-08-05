import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useEffect, useState } from 'react';

/** Fila del listado de requisiciones copiables. */
type Copiable = {
    id: number;
    folio: string;
    fecha: string | null;
    solicitante: string | null;
    departamento: string | null;
    estatus: string | null;
    partidas: number;
};

/** Partida tal como la espera el formulario de alta. */
export type PartidaCopiada = {
    producto_id: number | null;
    descripcion: string;
    unidad: string;
    cantidad: number;
    presupuesto_id: number | null;
    obra_rubro_id: number | null;
    uso_cfdi_id: number | null;
    notas: string | null;
};

export type RequisicionCopiada = {
    folio: string;
    cabecera: {
        departamento_id: number | null;
        presupuesto_id: number | null;
        sin_centro_costos: boolean;
        justificacion: string | null;
    };
    detalles: PartidaCopiada[];
};

type Props = {
    open: boolean;
    onClose: () => void;
    /** Recibe el contenido y si debe arrastrar el destino presupuestal. */
    onCopiar: (
        requisicion: RequisicionCopiada,
        conCentroCostos: boolean,
    ) => void;
};

/**
 * Copia una requisición anterior al formulario de alta.
 *
 * Sólo lista las que el usuario podría abrir: copiar trae las partidas
 * completas, así que no debe alcanzar a lo que no puede ver.
 */
export function CopiarRequisicionModal({ open, onClose, onCopiar }: Props) {
    const [busqueda, setBusqueda] = useState('');
    const [opciones, setOpciones] = useState<Copiable[]>([]);
    const [cargando, setCargando] = useState(false);
    const [seleccion, setSeleccion] = useState<Copiable | null>(null);
    const [contenido, setContenido] = useState<RequisicionCopiada | null>(null);
    const [conCentroCostos, setConCentroCostos] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) return;

        setCargando(true);
        setError(null);

        const params = busqueda
            ? `?search=${encodeURIComponent(busqueda)}`
            : '';

        fetch(`/admin/costos/requisiciones/copiables${params}`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) =>
                r.ok
                    ? r.json()
                    : Promise.reject(new Error('No se pudo cargar la lista.')),
            )
            .then((data) => setOpciones(data.requisiciones ?? []))
            .catch((e: Error) => setError(e.message))
            .finally(() => setCargando(false));
    }, [open, busqueda]);

    // Al cerrar se limpia todo: el modal se vuelve a abrir desde cero.
    useEffect(() => {
        if (open) return;

        setBusqueda('');
        setSeleccion(null);
        setContenido(null);
        setError(null);
    }, [open]);

    const elegir = (opcion: Copiable) => {
        setSeleccion(opcion);
        setContenido(null);
        setError(null);

        fetch(`/admin/costos/requisiciones/${opcion.id}/para-copiar`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) =>
                r.ok
                    ? r.json()
                    : Promise.reject(
                          new Error('No se pudo leer esa requisición.'),
                      ),
            )
            .then((data: RequisicionCopiada) => setContenido(data))
            .catch((e: Error) => setError(e.message));
    };

    const aplicar = () => {
        if (!contenido) return;

        onCopiar(contenido, conCentroCostos);
        onClose();
    };

    if (!open) return null;

    return (
        <dialog className="modal-open modal">
            <div className="modal-box max-w-3xl">
                <h3 className="text-lg font-semibold">
                    Copiar de otra requisición
                </h3>
                <p className="mt-1 text-sm text-base-content/60">
                    Trae las partidas de una requisición anterior al formulario.
                    Lo que ya hayas capturado se reemplaza.
                </p>

                <div className="mt-4 space-y-3">
                    <Input
                        placeholder="Buscar por folio, solicitante o justificación..."
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                    />

                    {error && (
                        <div className="alert text-sm alert-error">{error}</div>
                    )}

                    <div className="max-h-64 overflow-auto rounded-box border border-base-300">
                        {cargando ? (
                            <p className="py-6 text-center text-sm text-base-content/50">
                                Cargando...
                            </p>
                        ) : opciones.length === 0 ? (
                            <p className="py-6 text-center text-sm text-base-content/50">
                                No hay requisiciones que puedas copiar.
                            </p>
                        ) : (
                            <table className="table table-sm">
                                <thead className="sticky top-0 bg-base-200">
                                    <tr>
                                        <th></th>
                                        <th>Folio</th>
                                        <th>Solicitante</th>
                                        <th>Departamento</th>
                                        <th className="text-right">Partidas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {opciones.map((o) => (
                                        <tr
                                            key={o.id}
                                            className={`hover cursor-pointer ${seleccion?.id === o.id ? 'bg-base-200' : ''}`}
                                            onClick={() => elegir(o)}
                                        >
                                            <td className="w-8">
                                                <input
                                                    type="radio"
                                                    className="radio radio-xs"
                                                    checked={
                                                        seleccion?.id === o.id
                                                    }
                                                    onChange={() => elegir(o)}
                                                />
                                            </td>
                                            <td className="font-medium">
                                                {o.folio}
                                            </td>
                                            <td>{o.solicitante ?? '—'}</td>
                                            <td>{o.departamento ?? '—'}</td>
                                            <td className="text-right font-mono">
                                                {o.partidas}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    {seleccion && (
                        <div className="rounded-box border border-base-300 p-3">
                            {!contenido ? (
                                <p className="text-sm text-base-content/50">
                                    Leyendo {seleccion.folio}...
                                </p>
                            ) : (
                                <>
                                    <p className="mb-2 text-sm font-medium">
                                        {contenido.detalles.length} partida(s)
                                        de {contenido.folio}
                                    </p>
                                    <ul className="max-h-32 space-y-1 overflow-auto text-sm text-base-content/70">
                                        {contenido.detalles.map((d, i) => (
                                            <li key={i}>
                                                {d.cantidad} {d.unidad} ·{' '}
                                                {d.descripcion}
                                            </li>
                                        ))}
                                    </ul>

                                    <label className="mt-3 flex cursor-pointer items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            className="checkbox checkbox-sm"
                                            checked={conCentroCostos}
                                            onChange={(e) =>
                                                setConCentroCostos(
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        Copiar también el presupuesto y los
                                        centros de costo
                                    </label>
                                    <p className="mt-1 text-xs text-base-content/50">
                                        Desmárcalo si vas a pedir los mismos
                                        materiales para otra obra: se copian
                                        sólo los productos y las cantidades.
                                    </p>
                                </>
                            )}
                        </div>
                    )}
                </div>

                <div className="modal-action">
                    <Button variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button onClick={aplicar} disabled={!contenido}>
                        Copiar al formulario
                    </Button>
                </div>
            </div>
            <div className="modal-backdrop bg-black/40" onClick={onClose} />
        </dialog>
    );
}
